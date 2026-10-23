<?php
/** Session-backed instructions and private supplement boundaries; no model work is simulated here. */
require __DIR__.'/bootstrap.php';
require_once __DIR__.'/../src/volunteer-sessions.php';
require_once __DIR__.'/../src/volunteer-orchestrator.php';
if (!function_exists('vs_create')) throw new RuntimeException('The volunteer session dependency must be integrated before this test runs.');
$count = 0;
$ids = [];
function vo_check(string $label, bool $pass): void {
    global $count;
    if (!$pass) throw new RuntimeException($label);
    $count++;
}
function vo_denied(string $label, callable $action): void {
    try { $action(); } catch (HttpError $error) { vo_check($label, in_array($error->status, [403,404,409,422], true)); return; }
    throw new RuntimeException($label.' was allowed');
}
function vo_reservation_storage(): array {
    $exists = (int)one("SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='task_reservations'")['n'] > 0;
    return $exists ? ['present'=>true,'count'=>(int)one('SELECT COUNT(*) AS n FROM task_reservations')['n']] : ['present'=>false];
}
$originalSession = $_SESSION ?? [];
$_SESSION ??= [];
$originalClock = $GLOBALS['vs_test_now'] ?? null;
try {
    $public = vo_instructions();
    $single = ar_instructions();
    $sessionInstructions = ar_instructions(true);
    vo_check('Default mode retains single-assignment stopping boundary', str_contains($single, 'Stop after this single assignment; do not begin another task or review your own work.'));
    vo_check('Session mode retains worker boundary and bounds orchestrator continuation', !str_contains($sessionInstructions, 'Stop after this single assignment;') && str_contains($sessionInstructions, 'The assigned worker stops after its recorded outcome.') && str_contains($sessionInstructions, 'server-validated session scope, allowance and deadline'));
    $_POST['volunteer_session'] = '1';
    vo_check('Packet or POST mode flags do not select session instructions', ar_instructions() === $single);
    unset($_POST['volunteer_session']);
    try {
        vo_return_instructions('A changed procedure without the required stopping boundary.');
        throw new LogicException('Unreconciled instructions were accepted.');
    } catch (RuntimeException $error) {
        vo_check('Changed templates fail closed instead of publishing conflicting session guidance', str_contains($error->getMessage(), 'must be reconciled'));
    }
    $archiveCount = (int)one('SELECT COUNT(*) AS n FROM workflow_handoffs')['n'];
    $reservationStorage = vo_reservation_storage();
    $GLOBALS['vs_test_now'] = time();
    $created = vs_create(['scope_kind'=>'all','assignment_limit'=>3,'minutes'=>60,
        'model'=>'gpt-6-astra','reasoning'=>'max','tool'=>'Codex','workflow_mode'=>'',
        'authorize'=>1,'terms'=>1,'authorized_resources'=>1,'policy_version'=>terms_version(),
        'human_level'=>'none','human_details'=>'']);
    $id = $created['id'];
    $ids[] = $id;
    $secret = substr($created['link'], strpos($created['link'], '#')+1);
    $before = vs_read($id);
    $text = vo_handoff($id, $created['link']);
    vo_check('Complete reusable instructions precede the private supplement', str_starts_with($text, $public."\nPRIVATE VOLUNTEER SESSION SUPPLEMENT"));
    vo_check('Private link is exact service-issued authorization', substr_count($text, $created['link']) === 1);
    vo_check('Public reference instructions have no session credential', !str_contains($public, $secret) && !str_contains($public, $id));
    vo_check('Supplement does not disclose actor or stored credential hash', !str_contains($text, $before['actor_hash']) && !str_contains($text, $before['secret_hash']));
    vo_check('Repeated handoff does not mutate session or extend its deadline', vo_handoff($id, $created['link']) === $text && vs_read($id) === $before);
    vo_check('Copy preparation issues no research archive', (int)one('SELECT COUNT(*) AS n FROM workflow_handoffs')['n'] === $archiveCount);
    vo_check('Supplement preparation neither creates nor changes reservation storage', vo_reservation_storage() === $reservationStorage);
    vo_denied('Alternate origin rejected', fn()=>vo_handoff($id, str_replace(rtrim(env('APP_URL'), '/'), 'https://example.invalid', $created['link'])));
    vo_denied('Changed credential rejected', fn()=>vo_handoff($id, explode('#', $created['link'])[0].'#'.str_repeat('0',64)));
    vo_denied('Additional fragment or suffix rejected', fn()=>vo_handoff($id, $created['link'].'/extra'));
    unset($_SESSION['volunteer_sessions'][$id]);
    vo_denied('Session ID and valid link cannot bypass required browser authorization', fn()=>vo_handoff($id, $created['link']));
    $_SESSION['volunteer_sessions'][$id] = $before['secret_hash'];
    $GLOBALS['vs_test_now'] = strtotime($before['expires_at'].' UTC');
    vo_denied('Exact session deadline denies new orchestrator handoff', fn()=>vo_handoff($id, $created['link']));
    $GLOBALS['vs_test_now'] = strtotime($before['created_at'].' UTC');
    query('UPDATE volunteer_sessions SET stopped_at=? WHERE id=?', [gmdate('Y-m-d H:i:s', $GLOBALS['vs_test_now']), $id]);
    vo_denied('Stopped session denies new orchestrator handoff', fn()=>vo_handoff($id, $created['link']));
    query('UPDATE volunteer_sessions SET stopped_at=NULL WHERE id=?', [$id]);
    $consent = $before['consent'];
    $consent['terms_version'] = 'obsolete-orchestrator-test';
    query('UPDATE volunteer_sessions SET consent=? WHERE id=?', [json_encode($consent, JSON_THROW_ON_ERROR), $id]);
    vo_denied('Changed terms require fresh real volunteer authorization', fn()=>vo_handoff($id, $created['link']));
    vo_check('Denied copies do not create reservations or archives',
        vo_reservation_storage() === $reservationStorage &&
        (int)one('SELECT COUNT(*) AS n FROM workflow_handoffs')['n'] === $archiveCount);
    echo $count." volunteer orchestrator authorization checks passed.\n";
} finally {
    foreach ($ids as $id) query('DELETE FROM volunteer_sessions WHERE id=?', [$id]);
    $_SESSION = $originalSession;
    if ($originalClock === null) unset($GLOBALS['vs_test_now']); else $GLOBALS['vs_test_now'] = $originalClock;
}
