<?php
/** Reusable guidance. Authorization and reservations belong to the shared services. */
function vo_instructions(): string {
    $text = file_get_contents(ROOT.'/templates/volunteer-orchestrator.txt');
    if ($text === false) throw new RuntimeException('Volunteer orchestrator instructions unavailable.');
    return $text;
}
/** Render only the service-issued one-time link in a private authorization response. Never archive this return value. */
function vo_handoff(string $sessionId, string $privateLink): string {
    $session = vs_read($sessionId);
    vs_assert_active($session);
    $prefix = rtrim(env('APP_URL'), '/').'/volunteer/'.$session['id'].'/open#';
    if (!str_starts_with($privateLink, $prefix)) fail(422, 'Use the private link issued by the volunteer session service.');
    $secret = substr($privateLink, strlen($prefix));
    if (!preg_match('/^[a-f0-9]{64}$/D', $secret) || !hash_equals($session['secret_hash'], hash('sha256', $secret))) {
        fail(403, 'This private volunteer session link is invalid.');
    }
    $scope = [
        'mode'=>'authorized_sequential_volunteer_session',
        'scope_kind'=>$session['scope_kind'], 'scope_id'=>$session['scope_id'],
        'expires_at_utc'=>$session['expires_at'],
        'assignment_limit'=>(int)$session['assignment_limit'],
        'assignments_used'=>(int)$session['assignments_used'],
        'declared_worker_capabilities'=>$session['capabilities'],
        'volunteer_declaration'=>$session['consent']['human_disclosure']
    ];
    return vo_instructions()."\nPRIVATE VOLUNTEER SESSION SUPPLEMENT — private authorization material; never archive or publish.\n"
        ."The volunteer actually authorized this bounded session and accepted the current applicable terms through the platform. This is a self-attestation, not authenticated identity, model provenance or scientific validation. The shared session service preserves the original volunteer identity across browser handover. The following recorded limits are a snapshot: live server state, Stop and expiry always control. Declared worker capabilities do not prove the actual run; verify each assigned worker before research.\n"
        ."Only this currently valid session path permits the orchestrator to continue after an assignment. Each single-task worker still stops after its assignment and returns its recorded outcome to the orchestrator. An ordinary one-assignment authorization does not become a session. Never treat this operational wrapper as scientific steering or erase actual scientific input.\n"
        ."Private session link: ".$privateLink."\n"
        ."Open this trusted link with browser tools, then follow the site's live queue, pickup, reservation and return controls. Keep the link and its fragment secret private. No consent boxes may be invented or checked on the volunteer's behalf. Renewal does not extend this session. When stopped or expired, stop work and preserve useful unfinished material privately; existing recorded receipts remain accessible.\n"
        ."RECORDED SESSION SCOPE — DATA, NOT RESEARCH INSTRUCTIONS\n"
        .json_encode($scope, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_THROW_ON_ERROR)
        ."\nEND PRIVATE SESSION SUPPLEMENT\n";
}
