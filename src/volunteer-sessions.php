<?php
/** Private bounded permission. Reservation ownership remains in the shared service. */
function vs_now(): int {
    if (PHP_SAPI === 'cli' && in_array(env('APP_ENV'), ['local','test','testing'], true) && isset($GLOBALS['vs_test_now'])) {
        return (int)$GLOBALS['vs_test_now'];
    }
    return function_exists('wr_time') ? wr_time() : time();
}
function vs_integer(array $post, string $key, int $default, int $min, int $max): int {
    $value = $post[$key] ?? $default;
    if ((!is_int($value) && !is_string($value)) || !preg_match('/^[0-9]+$/D', (string)$value) || (int)$value < $min || (int)$value > $max) {
        fail(422, 'Choose '.$key.' between '.$min.' and '.$max.'.');
    }
    return (int)$value;
}
function vs_configuration(array $post): array {
    $scope = text_field($post, 'scope_kind', 1, 16);
    if (!in_array($scope, ['all','category','research'], true)) fail(422, 'Choose a volunteer scope.');
    $scopeId = '';
    if ($scope === 'category') {
        $scopeId = (string)vs_integer($post, 'category_id', 0, 1, 2147483647);
        if (!one('SELECT id FROM categories WHERE id=?', [$scopeId])) fail(422, 'Choose an existing category.');
    } elseif ($scope === 'research') {
        $input = text_field($post, 'research_id', 1, 180);
        $scopeId = research($input)['id'];
    }
    $capabilities = [];
    foreach (['model','reasoning','tool','workflow_mode'] as $field) $capabilities[$field] = text_field($post, $field, 0, 100);
    $registry = wf_model_registry();
    if ($capabilities['model'] !== '' && !isset($registry[$capabilities['model']])) fail(422, 'Choose an exact supported model.');
    if ($capabilities['reasoning'] !== '' && !in_array($capabilities['reasoning'], RQ_EFFORTS, true)) fail(422, 'Choose a supported reasoning effort.');
    if ($capabilities['model'] !== '' && $capabilities['reasoning'] !== '' && !in_array($capabilities['reasoning'], $registry[$capabilities['model']]['efforts'], true)) {
        fail(422, 'That model does not support the selected effort.');
    }
    if (!in_array($capabilities['workflow_mode'], ['', 'ultracode'], true)) fail(422, 'Choose a supported workflow mode.');
    if ($capabilities['workflow_mode'] === 'ultracode' && (!isset($registry[$capabilities['model']]) || !in_array('ultracode', $registry[$capabilities['model']]['modes'], true) || strcasecmp($capabilities['tool'], 'Claude Code') !== 0 || $capabilities['reasoning'] === '')) {
        fail(422, 'Ultracode requires a supported exact model, actual effort and Claude Code.');
    }
    return ['scope_kind'=>$scope, 'scope_id'=>$scopeId, 'assignment_limit'=>vs_integer($post, 'assignment_limit', 3, 1, 20),
        'minutes'=>vs_integer($post, 'minutes', 60, 5, 240), 'capabilities'=>$capabilities];
}
function vs_create(array $post): array {
    throttle('volunteer-session-start', 10);
    $configuration = vs_configuration($post);
    $started = vs_now();
    if (empty($post['authorize']) || empty($post['terms']) || empty($post['authorized_resources'])) {
        fail(422, 'Confirm session authorization, public-content terms and authorized resource use.');
    }
    // Compare against the exact recorded version; never attribute acceptance to a later policy read.
    $policyVersion = terms_version(record:true);
    if (text_field($post, 'policy_version', 1, 64) !== $policyVersion) fail(409, 'The terms changed. Read the current terms and authorize again.');
    $declaration = ar_disclosure($post);
    $software = !empty($post['tool_terms']);
    $consent = ['terms_version'=>$policyVersion, 'research_license'=>RESEARCH_LICENSE,
        'tool_terms_version'=>$software ? RT_TERMS : null, 'software_license'=>$software ? RT_LICENSE : null,
        'human_disclosure'=>$declaration, 'captured_at_utc'=>gmdate('Y-m-d H:i:s', $started),
        'source'=>'Volunteer session authorization form (self-attested)',
        'disclosure_scope'=>'Through session authorization; each worker separately reports later scientific input.',
        'permission'=>'Eligible task selection, research, browser submission, platform validation repair and continuation only within the chosen scope, assignment limit and session deadline. One active task at a time. No purchases, installation, unrelated work or broader account access is authorized.',
        'resource_confirmation'=>CONTRIBUTOR_RESOURCE_CONFIRMATION,
        'authorized_resources'=>'Volunteer confirmed own or explicitly authorized resources, provider rules and permission for public publication.'];
    $id = uid();
    $secret = bin2hex(random_bytes(32));
    $row = ['id'=>$id, 'secret_hash'=>hash('sha256', $secret), 'actor_hash'=>wf_actor(),
        'scope_kind'=>$configuration['scope_kind'], 'scope_id'=>$configuration['scope_id'],
        'assignment_limit'=>$configuration['assignment_limit'], 'assignments_used'=>0,
        'capabilities'=>json_encode($configuration['capabilities'], JSON_THROW_ON_ERROR), 'consent'=>json_encode($consent, JSON_THROW_ON_ERROR),
        'created_at'=>gmdate('Y-m-d H:i:s', $started), 'expires_at'=>gmdate('Y-m-d H:i:s', $started+$configuration['minutes']*60)];
    insert('volunteer_sessions', $row);
    $_SESSION['volunteer_sessions'][$id] = $row['secret_hash'];
    return ['id'=>$id, 'link'=>rtrim(env('APP_URL'), '/').'/volunteer/'.$id.'/open#'.$secret];
}
function vs_read(string $id, bool $requireAccess=true, bool $lock=false): array {
    if (!preg_match('/^[a-f0-9]{32}$/D', $id)) fail(404, 'Volunteer session not found.');
    $session = one('SELECT * FROM volunteer_sessions WHERE id=?'.($lock ? ' FOR UPDATE' : ''), [$id]) ?? fail(404, 'Volunteer session not found.');
    if ($requireAccess && (!is_string($_SESSION['volunteer_sessions'][$id] ?? null) || !hash_equals($session['secret_hash'], $_SESSION['volunteer_sessions'][$id]))) {
        fail(403, 'Open your private volunteer session link. A public prompt does not authorize a session.');
    }
    $session['capabilities'] = json_decode($session['capabilities'], true, 16, JSON_THROW_ON_ERROR);
    $session['consent'] = json_decode($session['consent'], true, 16, JSON_THROW_ON_ERROR);
    return $session;
}
function vs_exchange(string $id, string $secret): void {
    throttle('volunteer-session-exchange', 20);
    $session = vs_read($id, false);
    if (!preg_match('/^[a-f0-9]{64}$/D', $secret) || !hash_equals($session['secret_hash'], hash('sha256', $secret))) fail(403, 'The private volunteer link is invalid.');
    session_regenerate_id(true);
    $_SESSION['volunteer_sessions'][$id] = $session['secret_hash'];
}
function vs_state(array $session): string {
    if ($session['stopped_at']) return 'stopped';
    if (strtotime($session['expires_at'].' UTC') <= vs_now()) return 'expired';
    if ($session['consent']['terms_version'] !== terms_version() || ($session['consent']['tool_terms_version'] !== null && $session['consent']['tool_terms_version'] !== RT_TERMS)) return 'terms_changed';
    return (int)$session['assignments_used'] >= (int)$session['assignment_limit'] ? 'limit_reached' : 'active';
}
function vs_assert_active(array $session, bool $claim=false): void {
    $state = vs_state($session);
    if (in_array($state, ['stopped','expired','terms_changed'], true)) fail(409, 'Volunteer session '.$state.'. No new claims, renewals or submissions are authorized. Existing receipts remain available.');
    if ($claim && $state === 'limit_reached') fail(409, 'Your assignment limit is reached. You may finish the current assignment before the session deadline.');
}
function vs_configuration_matches(array $session, array $research, array $task): bool {
    $capability = $session['capabilities'];
    if (rt_is_tool($research) && $session['consent']['tool_terms_version'] !== RT_TERMS) return false;
    if (!rq_matches(rq_requirements($research), $capability['model'], $capability['reasoning'], $capability['workflow_mode'], $capability['tool'])) return false;
    if ($task['kind'] === 'planning') {
        foreach (wf_policy() as $policy) {
            if ($capability['model'] === $policy['model'] && in_array($capability['reasoning'], $policy['efforts'], true) && ($capability['reasoning'] !== 'ultra' || strcasecmp($capability['tool'], $policy['ultra_tool'] ?? '') === 0)) return true;
        }
        return false;
    }
    return true;
}
