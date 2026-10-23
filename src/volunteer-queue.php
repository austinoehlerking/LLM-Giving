<?php
/** All reservation mutations delegate to wr_*; only session permission/counting lives here. */
function vs_locked(string $id, callable $operation): mixed {
    if (!function_exists('wr_claim')) fail(503, 'Task reservations are not available yet.');
    if (db()->inTransaction()) throw new LogicException('Session mutations own their transaction.');
    $session=vs_read($id);
    // Candidate reads cannot pin old eligibility while waiting for the parent lock.
    query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    return tx(function() use($id,$session,$operation) {
        wr_actor_lock($session['actor_hash'],true);
        return $operation(vs_read($id,true,true));
    });
}
function vs_scope_matches(array $session, array $r): bool {
    return $session['scope_kind']==='all'
        || ($session['scope_kind']==='category' && (string)$r['category_id']===$session['scope_id'])
        || ($session['scope_kind']==='research' && $r['id']===$session['scope_id']);
}
function vs_task_eligible(array $session, array $r, array $task): void {
    vs_assert_active($session);
    if (!vs_scope_matches($session,$r) || !vs_configuration_matches($session,$r,$task)) fail(409,'This assignment does not match your session scope, software consent or worker configuration.');
    wr_eligible($r['id'],$task['id'],(int)$task['revision'],$session['actor_hash']);
    // A child worker uses this same immutable actor; a different browser does not make it independent.
    if ($task['kind']==='review' && one('SELECT id FROM workflow_submissions WHERE research_id=? AND actor_hash=? LIMIT 1',[$r['id'],$session['actor_hash']])) {
        fail(422,'This volunteer session or its workers already contributed to this study. Choose independent review elsewhere.');
    }
}
function vs_current(array $session): ?array {
    return one('SELECT * FROM volunteer_assignments WHERE session_id=? AND ended_at IS NULL ORDER BY created_at DESC,id DESC LIMIT 1',[$session['id']]);
}
function vs_assignment(array $session, string $id): array {
    return one('SELECT * FROM volunteer_assignments WHERE id=? AND session_id=?',[$id,$session['id']]) ?? fail(404,'Session assignment not found.');
}
function vs_assignment_grant(array $session,array $assignment): array {
    if (!$assignment['research_id'] || !$assignment['task_id'] || !$assignment['return_id']) fail(404,'This research assignment is no longer available.');
    $r=research($assignment['research_id']);
    $g=ar_read($r,$assignment['return_id'],false);
    if ($g['actor_hash']!==$session['actor_hash'] || ($g['scope']['volunteer_session_id']??null)!==$session['id'] || ($g['scope']['volunteer_assignment_id']??null)!==$assignment['id'] || $g['task_id']!==$assignment['task_id']) {
        fail(403,'The assignment is not bound to this volunteer session.');
    }
    return $g;
}
/** Reconstruct only a private return capability, using a separate persistent purpose key. */
function vs_return_secret(string $returnId): string {
    $handle=fopen(storage('private/volunteer-return.key'),'c+b');
    if (!$handle || !flock($handle,LOCK_EX)) throw new RuntimeException('Private assignment delivery is unavailable.');
    try {
        $key=stream_get_contents($handle);
        if ($key==='') {
            $key=random_bytes(32);
            if (fwrite($handle,$key)!==32 || !fflush($handle)) throw new RuntimeException('Private delivery key could not be saved.');
        }
        if (strlen($key)!==32) throw new RuntimeException('Invalid private delivery key.');
    } finally { flock($handle,LOCK_UN); fclose($handle); }
    return hash_hmac('sha256',"volunteer-task-return\n".$returnId,$key);
}
function vs_issue_assignment(array $session,array $r,array $task,array $reservation): array {
    $archive=wf_issue_handoff($r['id'],$task['id']);
    $id=uid(); $returnId=uid();
    $scope=['kind'=>'task','revision'=>(int)$task['revision'],'context_id'=>$archive['snapshot_id'],'submission_key'=>$archive['nonce'],'archive_id'=>$archive['id'],
        'requirements'=>rq_requirements($r),'volunteer_session_id'=>$session['id'],'volunteer_assignment_id'=>$id];
    insert('browser_returns',['id'=>$returnId,'research_id'=>$r['id'],'task_id'=>$task['id'],'secret_hash'=>hash('sha256',vs_return_secret($returnId)),
        'actor_hash'=>$session['actor_hash'],'scope'=>json_encode($scope,JSON_THROW_ON_ERROR),'consent'=>json_encode($session['consent'],JSON_THROW_ON_ERROR),
        'created_at'=>wr_stamp(),'expires_at'=>$session['expires_at']]);
    $assignment=['id'=>$id,'session_id'=>$session['id'],'research_id'=>$r['id'],'task_id'=>$task['id'],'return_id'=>$returnId,'task_revision'=>(int)$task['revision'],
        'reservation_id'=>$reservation['id'],'created_at'=>wr_stamp()];
    insert('volunteer_assignments',$assignment);
    query('UPDATE volunteer_sessions SET assignments_used=assignments_used+1 WHERE id=?',[$session['id']]);
    return vs_assignment($session,$id);
}
function vs_claim_next(string $id): array {
    return vs_locked($id,function(array $session) {
        vs_assert_active($session);
        if ($current=vs_current($session)) {
            $grant=vs_assignment_grant($session,$current);
            if (!$grant['result_id']) {
                vs_validate_grant(research($current['research_id']),$grant);
                return $current; // Recopy never consumes another assignment or renews.
            }
            vs_return_recorded($grant,$grant['result_id']);
        }
        vs_assert_active($session,true);
        $after='';
        do {
            $parameters=[$after];
            $scope='';
            if ($session['scope_kind']==='category') { $scope=' AND r.category_id=?'; $parameters[]=$session['scope_id']; }
            if ($session['scope_kind']==='research') { $scope=' AND r.id=?'; $parameters[]=$session['scope_id']; }
            $candidates=all("SELECT t.id,t.research_id FROM workflow_tasks t JOIN workflow_projects p ON p.research_id=t.research_id AND p.round_no=t.round_no JOIN research r ON r.id=t.research_id WHERE t.id>? AND t.state='Ready' AND t.visibility='public' AND r.moderation='Approved' AND r.status NOT IN ('Withdrawn','Paused','Solved','Archived — Inactive','Review Complete','Blocked','Needs Clarification')".$scope.' ORDER BY t.id LIMIT 50',$parameters);
            foreach ($candidates as $candidate) {
                $after=$candidate['id'];
                try {
                    $r=research($candidate['research_id']); $task=wf_task($r['id'],$candidate['id']);
                    vs_task_eligible($session,$r,$task);
                    $live=wr_live($r['id'],$task['id']);
                    if ($live) continue; // Never adopt another flow's reservation, even for this actor.
                } catch (HttpError $error) {
                    if (in_array($error->status,[404,409,422],true)) continue;
                    throw $error;
                }
                $reservation=wr_claim($r['id'],$task['id'],(int)$task['revision'],$session['actor_hash']);
                vs_task_eligible($session,research($r['id']),wf_task($r['id'],$task['id']));
                // The enclosing transaction rolls back a new claim/count if complete handoff preparation fails.
                return vs_issue_assignment($session,$r,$task,$reservation);
            }
        } while (count($candidates)===50);
        fail(409,'No eligible unreserved task matches this scope, software consent and worker configuration. Work may be reserved, waiting, completed or held. Nothing was assigned.');
    });
}
/** Called by ar_validate only for a trusted stored scope carrying volunteer_session_id. */
function vs_validate_grant(array $r,array $grant): void {
    $id=$grant['scope']['volunteer_session_id']??null;
    if (!$id) return;
    $session=vs_read($id,false);
    $assignment=vs_assignment($session,(string)($grant['scope']['volunteer_assignment_id']??''));
    if ($assignment['return_id']!==$grant['id'] || $session['actor_hash']!==$grant['actor_hash'] || $assignment['research_id']!==$r['id'] || $assignment['task_id']!==$grant['task_id']) fail(403,'Invalid volunteer assignment binding.');
    if ($grant['result_id']) return; // Receipt recovery precedes all ongoing permission checks.
    if ($assignment['ended_at']) fail(409,'This assignment was released. Save your work; this permission cannot submit it.');
    $task=wf_task($r['id'],$grant['task_id']);
    vs_task_eligible($session,$r,$task);
    $live=wr_require($r['id'],$task['id'],(int)$assignment['task_revision'],$session['actor_hash']);
    if ($live['id']!==$assignment['reservation_id']) fail(409,'The reservation expired or changed. Explicitly reacquire this assignment; preserve its original signed identifiers.');
}
/** Caller holds actor before session before research. Used by the browser-return integration. */
function vs_before_record(array $grant): void {
    if (empty($grant['scope']['volunteer_session_id']) || $grant['result_id']) return;
    wr_actor_lock($grant['actor_hash']);
    $session=vs_read($grant['scope']['volunteer_session_id'],false,true);
    vs_assert_active($session);
}
function vs_return_recorded(array $grant,string $resultId): void {
    if (empty($grant['scope']['volunteer_session_id'])) return;
    $result=one('SELECT admission FROM workflow_submissions WHERE id=? AND research_id=?',[$resultId,$grant['research_id']]) ?? fail(404,'Recorded result unavailable.');
    query('UPDATE volunteer_assignments SET ended_at=COALESCE(ended_at,?),outcome=? WHERE id=? AND session_id=? AND return_id=?',
        [wr_stamp(),$result['admission'],$grant['scope']['volunteer_assignment_id'],$grant['scope']['volunteer_session_id'],$grant['id']]);
}
/** Shared reservation read hook: deadline/Stop/policy change release availability without a cleanup job. */
function vs_reservation_projection(array $reservation): ?array {
    $binding=one('SELECT s.*,a.ended_at AS assignment_ended FROM volunteer_assignments a JOIN volunteer_sessions s ON s.id=a.session_id WHERE a.reservation_id=?',[$reservation['id']]);
    if (!$binding) return $reservation;
    $binding['consent']=json_decode($binding['consent'],true,16,JSON_THROW_ON_ERROR);
    if ($binding['assignment_ended'] || in_array(vs_state($binding),['expired','stopped','terms_changed'],true)) return null;
    $reservation['expires_at']=min($reservation['expires_at'],$binding['expires_at']);
    return $reservation;
}
function vs_release_current(array $session,string $reason): void {
    foreach (all('SELECT * FROM volunteer_assignments WHERE session_id=? AND ended_at IS NULL ORDER BY research_id,id',[$session['id']]) as $assignment) {
        if ($assignment['research_id'] && $assignment['task_id'] && $assignment['reservation_id']) {
            $latest=one('SELECT id,actor_hash FROM task_reservations WHERE research_id=? AND task_id=? AND ended_at IS NULL ORDER BY created_at DESC,id DESC LIMIT 1',[$assignment['research_id'],$assignment['task_id']]);
            if ($latest && $latest['id']===$assignment['reservation_id'] && hash_equals($latest['actor_hash'],$session['actor_hash'])) wr_release($assignment['research_id'],$assignment['task_id'],$session['actor_hash']);
        }
        query("UPDATE volunteer_assignments SET ended_at=?,outcome='not_submitted' WHERE id=?",[wr_stamp(),$assignment['id']]);
    }
}
function vs_stop(string $id): void {
    vs_locked($id,function(array $session) {
        if (!$session['stopped_at']) query('UPDATE volunteer_sessions SET stopped_at=?,stop_reason=? WHERE id=?',[wr_stamp(),'Volunteer stopped the session.',$session['id']]);
        vs_release_current($session,'stopped');
    });
}
function vs_assignment_action(string $id,string $assignmentId,string $operation): void {
    vs_locked($id,function(array $session) use($assignmentId,$operation) {
        $assignment=vs_assignment($session,$assignmentId);
        if ($operation==='release') {
            if (!$assignment['ended_at']) vs_release_current($session,'released');
            return;
        }
        $grant=vs_assignment_grant($session,$assignment);
        vs_assert_active($session);
        if ($assignment['ended_at'] || $grant['result_id']) fail(409,'This assignment already ended. Its receipt remains available.');
        $r=research($assignment['research_id']); $task=wf_task($r['id'],$assignment['task_id']);
        vs_task_eligible($session,$r,$task);
        if ((int)$task['revision']!==(int)$assignment['task_revision']) fail(409,'This task revision changed. Save your work and release the old assignment; do not replace signed identifiers.');
        if ($operation==='renew') {
            vs_validate_grant($r,$grant);
            wr_renew($r['id'],$task['id'],(int)$task['revision'],$session['actor_hash']);
        } elseif ($operation==='reacquire') {
            $reservation=wr_claim($r['id'],$task['id'],(int)$task['revision'],$session['actor_hash']);
            query('UPDATE volunteer_assignments SET reservation_id=? WHERE id=?',[$reservation['id'],$assignment['id']]);
        } else fail(422,'Choose renew, release or reacquire.');
    });
}
function vs_handoff(string $id,string $assignmentId): string {
    $session=vs_read($id); $assignment=vs_assignment($session,$assignmentId);
    $grant=vs_assignment_grant($session,$assignment); $r=research($assignment['research_id']);
    vs_validate_grant($r,$grant);
    if ($grant['result_id']) fail(409,'This assignment already has a recorded receipt.');
    $archive=wf_read_handoff($r['id'],$grant['scope']['archive_id']);
    $secret=vs_return_secret($grant['id']);
    if (!hash_equals($grant['secret_hash'],hash('sha256',$secret))) fail(409,'The private delivery key changed. Use your session dashboard; do not invent authorization.');
    $link=rtrim(env('APP_URL'),'/').'/research/'.$r['slug'].'/return/'.$grant['id'].'#'.$secret;
    return $archive['body']."\n\nPRIVATE VOLUNTEER SESSION ASSIGNMENT — not part of the public archive.\nThis assignment is authorized by the volunteer's bounded session until ".$session['expires_at']." UTC. The original volunteer identity and captured consent are preserved. This worker may perform this assignment, repair validation errors and return it through the private link below. A session deadline or Stop revokes ongoing permission even if a task reservation would otherwise last longer. Unchanged copying and operational browser permission are not scientific influence. Report later scientific input independently. No purchases, installations, unrelated work or account access are authorized.\nPrivate return link: ".$link."\nKeep this link and its credential out of research packets, public archives, citations, screenshots shared publicly, analytics and final public answers. Inspect the recorded receipt before retrying an uncertain response. Stop this worker after this assignment and return its recorded outcome to the session controller.\n";
}
