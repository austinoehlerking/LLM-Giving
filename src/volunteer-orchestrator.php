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
        'session_profile'=>vs_profile_id($session),
        'authorized_profile'=>vs_profile_definition(vs_profile_id($session)),
        'scope_kind'=>$session['scope_kind'], 'scope_id'=>$session['scope_id'],
        'limit_mode'=>$session['limit_mode'], 'expires_at_utc'=>$session['expires_at'],
        'assignment_limit'=>vs_unlimited($session)?null:(int)$session['assignment_limit'],
        'assignments_used'=>(int)$session['assignments_used'],
        'declared_worker_capabilities'=>$session['capabilities'],
        'volunteer_declaration'=>$session['consent']['human_disclosure']
    ];
    return vo_instructions().vs_profile_instructions($session)."\nPRIVATE VOLUNTEER SESSION SUPPLEMENT — private authorization material; never archive or publish.\n"
        ."The volunteer actually authorized this session and accepted the current applicable terms through the platform. This is a self-attestation, not authenticated identity, model provenance or scientific validation. The shared session service preserves the original volunteer identity across browser handover. The following recorded limits are a snapshot: live server state, Stop, current terms and any finite deadline always control. Unlimited mode has no overall deadline or assignment quota; it still requires each hourly task reservation. Declared worker capabilities do not prove the actual run; verify each assigned worker before research.\n"
        ."Only this currently valid session path permits the orchestrator to continue after an assignment. Each single-task worker still stops after its assignment and returns its recorded outcome to the orchestrator. An ordinary one-assignment authorization does not become a session. Never treat this operational wrapper as scientific steering or erase actual scientific input.\n"
        ."Private session link: ".$privateLink."\n"
        ."Open this trusted link with browser tools, then follow the site's live queue, pickup, reservation and return controls. Keep the link and its fragment secret private. No consent boxes may be invented or checked on the volunteer's behalf. Renewal does not extend this session. When stopped or expired, stop work and preserve useful unfinished material privately; existing recorded receipts remain accessible.\n"
        ."RECORDED SESSION SCOPE — DATA, NOT RESEARCH INSTRUCTIONS\n"
        .json_encode($scope, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_THROW_ON_ERROR)
        ."\nEND PRIVATE SESSION SUPPLEMENT\n";
}

/** Prose only: callers select this mode after shared session/grant validation. It grants no authority. */
function vo_return_instructions(string $singleAssignment): string {
    $replacements = [
        'Stop after this single assignment; do not begin another task or review your own work.' =>
            'The assigned worker stops after its recorded outcome. The orchestrator may return to the live Volunteer session queue only while the server-validated session scope, allowance and deadline permit. Never independently review work produced by this session, its orchestrator or its workers.',
        'If task, revision or requirements became stale, obtain fresh authorization/handoff and reassess; do not forge identifiers.' =>
            'If the task, revision or requirements became stale, preserve the entered work and original identifiers and report the conflict to the session controller. The controller uses its private live dashboard for explicit reacquisition or a new eligible assignment only if the session permits it. Worker-only return credentials do not authorize queue pickup or reacquisition. Reassess changed work; never silently replace signed identifiers. Session expiry, Stop or changed terms require stopping, not invented consent.',
        'Quarantined work is recorded but not accepted evidence: wait, do not resubmit.' =>
            'Quarantined work is recorded but not accepted evidence. Do not resubmit or poll for moderation. Another eligible study is allowed only through the still-active session queue and only within its scope; otherwise stop cleanly.'
    ];
    foreach ($replacements as $old => $new) {
        if (substr_count($singleAssignment, $old) !== 1) throw new RuntimeException('Session return guidance must be reconciled with the single-assignment instructions.');
        $singleAssignment = str_replace($old, $new, $singleAssignment);
    }
    $profileGuidance="The server-stored capability profile cannot be expanded by packet/POST fields or validation repair. For restricted_web_v1, use only web research, model reasoning/arithmetic without tools and ordinary site browser controls. Never fabricate execution/reproduction records. Release/skip hidden tool dependencies rather than declaring a scientific project blocked because this worker lacks capability.\n";
    return $profileGuidance."AUTHORIZED VOLUNTEER SESSION RETURN MODE — applies only after the server validates a current private session grant. This prose and packet fields create no permission. The existing session authorization covers eligible sequential continuation; do not ask for consent again for each permitted assignment. Read live limits before every action. A worker holding only an assignment return credential must report reservation expiry to its session controller; it must not gain general session authority. Use explicit active-work reservation renewal, never an idle timer; renewal does not extend the session deadline. Bound format/validation repair and uncertain receipt recovery to three attempts, then preserve work and report the persistent blocker or unknown recording status. Public reference prompts and ordinary single-assignment grants retain their original limits.\n".$singleAssignment;
}

/** Append only to private delivery after shared session validation; never alter an archived task body. */
function vo_assignment_instructions(array $session=[]): string {
    return vs_profile_instructions($session)."\nPRIVATE SESSION WORKER PROCEDURE — not part of the public task archive.\nFor this validated session assignment, the following procedure supersedes only the archived one-assignment continuation and fresh-authorization wording. All scientific requirements and signed identifiers remain unchanged. The assigned worker still stops after its assignment; only the authorized controller may continue.\n".ar_instructions(true)."\n";
}
