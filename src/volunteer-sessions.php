<?php
require_once __DIR__.'/research-identity.php';
require_once __DIR__.'/session-recovery.php';
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

/** Presets are server-owned; hidden/custom numeric values cannot change a selected preset. */
const VS_PRESETS = [
    'quick'=>['label'=>'Quick try','minutes'=>15,'tasks'=>1],
    'standard'=>['label'=>'Standard','minutes'=>60,'tasks'=>3],
    'long'=>['label'=>'Long haul','minutes'=>180,'tasks'=>10],
    'unlimited'=>['label'=>'No limit','minutes'=>null,'tasks'=>null],
];
function vs_unlimited(array $session): bool { return ($session['limit_mode']??'bounded')==='unlimited'; }
function vs_limits(array $post): array {
    $preset=isset($post['session_preset'])?text_field($post,'session_preset',1,16):'custom';
    if ($preset==='custom') return ['limit_mode'=>'bounded','assignment_limit'=>vs_integer($post,'assignment_limit',3,1,20),'minutes'=>vs_integer($post,'minutes',60,5,240)];
    if (!isset(VS_PRESETS[$preset])) fail(422,'Choose a session time preset or Custom limits.');
    return ['limit_mode'=>$preset==='unlimited'?'unlimited':'bounded','assignment_limit'=>VS_PRESETS[$preset]['tasks'],'minutes'=>VS_PRESETS[$preset]['minutes']];
}
function vs_scope_input(array $post): array {
    if (!isset($post['cause_choice'])) return $post;
    $choice=text_field($post,'cause_choice',1,40);
    if ($choice==='all') $post['scope_kind']='all';
    elseif (preg_match('/^category:([0-9]+)$/D',$choice,$match)) { $post['scope_kind']='category'; $post['category_id']=$match[1]; }
    elseif ($choice!=='custom') fail(422,'Choose where the session should help.');
    return $post;
}
function vs_limit_label(array $configuration): string {
    if (vs_unlimited($configuration)) return 'Until you press Stop · no session time or task limit';
    $minutes=$configuration['minutes']??(int)round((strtotime($configuration['expires_at'].' UTC')-strtotime($configuration['created_at'].' UTC'))/60);
    return $minutes.' min or '.$configuration['assignment_limit'].' '.((int)$configuration['assignment_limit']===1?'task':'tasks');
}
function vs_permission_end(array $session): string {
    return vs_unlimited($session)?'until you press Stop (no session deadline or assignment quota)': 'until '.$session['expires_at'].' UTC';
}

const VS_WORKERS = [
    'sol_max'=>['model'=>'gpt-6.1-sol','reasoning'=>'max','tool'=>'Codex','workflow_mode'=>''],
    'astra_max'=>['model'=>'gpt-6-astra','reasoning'=>'max','tool'=>'Codex','workflow_mode'=>''],
];
function vs_worker_input(array $post): array {
    $choice=text_field($post,'worker_choice',0,24);
    if($choice!=='' && $choice!=='custom') {
        if(!isset(VS_WORKERS[$choice]))fail(422,'Choose an available research worker.');
        foreach(VS_WORKERS[$choice] as $field=>$value)$post[$field]=$value;
    }
    return $post;
}
function vs_worker_ready(array $configuration): bool {
    foreach(['model','reasoning','tool'] as $key)if(($configuration['capabilities'][$key]??'')==='')return false;
    return true;
}
function vs_require_worker(array $configuration): void {
    if(!vs_worker_ready($configuration))fail(422,'Choose the research worker model, reasoning effort and tool you will launch before continuing. These are requested settings, not provider verification.');
}
function vs_configuration(array $post): array {
    $post=vs_worker_input($post);
    $profile=text_field($post,'session_profile',0,32)?:'general';
    vs_profile_definition($profile);
    $post=vs_scope_input($post);
    $limits=vs_limits($post);
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
    return ['scope_kind'=>$scope, 'scope_id'=>$scopeId, 'capabilities'=>$capabilities,'session_profile'=>$profile,'concurrent_limit'=>vs_integer($post,'concurrent_limit',1,1,3)]+$limits;
}
function vs_create(array $post): array {
    throttle('volunteer-session-start', 10);
    $configuration = vs_configuration($post);
    vs_require_worker($configuration);
    $started = vs_now();
    if (empty($post['authorize']) || empty($post['terms']) || empty($post['authorized_resources'])) {
        fail(422, 'Confirm session authorization, public-content terms and authorized resource use.');
    }
    // Compare against the exact recorded version; never attribute acceptance to a later policy read.
    $policyVersion = terms_version(record:true);
    if (text_field($post, 'policy_version', 1, 64) !== $policyVersion) fail(409, 'The terms changed. Read the current terms and authorize again.');
    // Older explicit clients remain truthful; the compact form collects no independent declaration.
    $collected = array_key_exists('human_level', $post);
    $declaration = $collected ? ar_disclosure($post) : ['level'=>'not_collected','details'=>'Independent contributor declaration was not collected for this volunteer session.'];
    $software = !empty($post['tool_terms']);
    $consent = ['terms_version'=>$policyVersion, 'research_license'=>RESEARCH_LICENSE,
        'tool_terms_version'=>$software ? RT_TERMS : null, 'software_license'=>$software ? RT_LICENSE : null,
        'human_disclosure'=>$declaration, 'human_disclosure_collection'=>$collected?'explicit_v1':'session_not_collected_v1', 'captured_at_utc'=>gmdate('Y-m-d H:i:s', $started),
        'source'=>'Volunteer session authorization form (self-attested)',
        'influence_policy_version'=>'formatting-only-v1',
        'disclosure_scope'=>$collected?'Through session authorization; each worker separately reports later scientific input.':'Independent contributor declaration not collected for this session; the AI reports all initial and later scientific input or uncertainty.',
        'permission'=>'Eligible task selection, research, browser submission, platform validation repair and continuation only within the chosen scope. '.vs_limit_label($configuration).'. Stop and current terms remain authoritative. Up to '.$configuration['concurrent_limit'].' concurrent task(s). No purchases, installation, unrelated work or broader account access is authorized.',
        'session_profile'=>$configuration['session_profile'],
        'worker_settings_source'=>'volunteer_requested_v1',
        'capability_profile'=>vs_profile_definition($configuration['session_profile']),
        'session_limits'=>['mode'=>$configuration['limit_mode'],'assignment_limit'=>$configuration['assignment_limit'],'minutes'=>$configuration['minutes'],'concurrent_limit'=>$configuration['concurrent_limit']],
        'resource_confirmation'=>CONTRIBUTOR_RESOURCE_CONFIRMATION,
        'authorized_resources'=>'Volunteer confirmed own or explicitly authorized resources, provider rules and permission for public publication.'];
    // Recheck current public work before creating a session identity or capability.
    // The preview reserves nothing; pickup rechecks any subsequent queue change.
    $actor=vs_startup_actor($configuration);$identityId=ri_capture($post,$actor)??($actor?ri_actor_identity($actor):null);
    $configuration['identity_id']=$identityId;if($actor)$configuration['actor_hash']=$actor;
    $preview=vs_startup_preflight($configuration,$software);
    if(!$preview['eligible'])fail(409,$preview['summary'].' '.implode(' ',array_map(fn($reason)=>$reason['message'].' '.$reason['action'],array_slice($preview['reasons'],0,2))).' No session was created.');
    $id = uid();
    $secret = bin2hex(random_bytes(32));
    $row = ['id'=>$id, 'secret_hash'=>hash('sha256', $secret), 'actor_hash'=>wf_actor(),
        'scope_kind'=>$configuration['scope_kind'], 'scope_id'=>$configuration['scope_id'],
        'concurrent_limit'=>$configuration['concurrent_limit'],'identity_id'=>$identityId,
        'session_profile'=>$configuration['session_profile'],
        'limit_mode'=>$configuration['limit_mode'], 'assignment_limit'=>$configuration['assignment_limit'], 'assignments_used'=>0,
        'capabilities'=>json_encode($configuration['capabilities'], JSON_THROW_ON_ERROR), 'consent'=>json_encode($consent, JSON_THROW_ON_ERROR),
        'created_at'=>gmdate('Y-m-d H:i:s', $started), 'expires_at'=>vs_unlimited($configuration)?null:gmdate('Y-m-d H:i:s', $started+$configuration['minutes']*60)];
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
    if (!vs_unlimited($session) && strtotime($session['expires_at'].' UTC') <= vs_now()) return 'expired';
    if ($session['consent']['terms_version'] !== terms_version() || ($session['consent']['tool_terms_version'] !== null && $session['consent']['tool_terms_version'] !== RT_TERMS)) return 'terms_changed';
    return !vs_unlimited($session) && (int)$session['assignments_used'] >= (int)$session['assignment_limit'] ? 'limit_reached' : 'active';
}
function vs_assert_active(array $session, bool $claim=false): void {
    $state = vs_state($session);
    if (in_array($state, ['stopped','expired','terms_changed'], true)) fail(409, 'Volunteer session '.$state.'. No new claims, renewals or submissions are authorized. Existing receipts remain available.');
    if ($claim && $state === 'limit_reached') fail(409, 'Your assignment limit is reached. You may finish the current assignment before the session deadline.');
}
function vs_configuration_matches(array $session, array $research, array $task): bool {
    if (!vs_profile_compatible($session,$research,$task)) return false;
    $capability = $session['capabilities'];
    if (vs_task_software_consent($research,$task) && $session['consent']['tool_terms_version'] !== RT_TERMS) return false;
    if (!rq_matches(rq_requirements($research), $capability['model'], $capability['reasoning'], $capability['workflow_mode'], $capability['tool'])) return false;
    if ($task['kind'] === 'planning') {
        foreach (wf_policy() as $policy) {
            if ($capability['model'] === $policy['model'] && in_array($capability['reasoning'], $policy['efforts'], true) && ($capability['reasoning'] !== 'ultra' || strcasecmp($capability['tool'], $policy['ultra_tool'] ?? '') === 0)) return true;
        }
        return false;
    }
    return true;
}
