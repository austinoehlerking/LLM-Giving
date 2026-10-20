<?php
// Archive the exact HTTP response body, not a later reconstruction of its template.
function wf_issue_handoff(string $rid,?string $tid=null): array {
    // Apply before expensive context generation. Never trust forwarded IP headers.
    research($rid);
    try { throttle('handoff-copy',60); throttle('handoff-copy-global',1000,3600,true); }
    catch(HttpError $e){if($e->status!==429)throw $e;header('Retry-After: 3600');fail(429,'Copy limit reached. Use your existing handoff or try again in one hour. Archived copies remain readable.');}
    return tx(function()use($rid,$tid){
        one('SELECT id FROM research WHERE id=? FOR UPDATE',[$rid])??fail(404,'Research not found.');
        $issuance=null;
        $body=$tid===null?wf_handoff_text($rid,$issuance):wf_prompt($rid,$tid,$issuance);
        if(!$issuance)throw new RuntimeException('Missing handoff issuance context.');
        if(strlen($body)>4194304)fail(413,'This research context exceeds the 4 MiB handoff limit. Ask the administrator to help scope the work. No partial prompt was copied.');
        $budget=hash('sha256',env('RATE_LIMIT_NAMESPACE').'|handoff-bytes|'.gmdate('Y-m-d'));
        query('INSERT INTO rate_limits(bucket,hits,expires) VALUES (?,?,?) ON DUPLICATE KEY UPDATE hits=hits+VALUES(hits)',[$budget,strlen($body),strtotime('tomorrow UTC')]);
        if((int)one('SELECT hits FROM rate_limits WHERE bucket=?',[$budget])['hits']>104857600)fail(503,'The daily archive storage budget is reached. Existing archives remain available. Try again after 00:00 UTC.');
        $row=['id'=>uid(),'research_id'=>$rid,'task_id'=>$issuance['task_id'],'snapshot_id'=>$issuance['snapshot_id'],'nonce'=>$issuance['nonce'],'body'=>$body,'sha256'=>hash('sha256',$body),'created_at'=>now()];
        insert('workflow_handoffs',$row);
        return $row;
    });
}
// Parent locking serializes retention with packet submission and issuance.
// Preserve all submitted evidence, irrespective of moderation or admission.
function wf_prune_handoffs(): int {
    $cutoff=gmdate('Y-m-d H:i:s',time()-30*86400);$deleted=0;
    $parents=all('SELECT DISTINCT h.research_id FROM workflow_handoffs h WHERE h.created_at<? AND NOT EXISTS (SELECT 1 FROM workflow_submissions s WHERE s.research_id=h.research_id AND s.nonce=h.nonce) LIMIT 100',[$cutoff]);
    foreach($parents as $parent){$deleted+=tx(function()use($parent,$cutoff){
        one('SELECT id FROM research WHERE id=? FOR UPDATE',[$parent['research_id']]);
        return query('DELETE h FROM workflow_handoffs h WHERE h.research_id=? AND h.created_at<? AND NOT EXISTS (SELECT 1 FROM workflow_submissions s WHERE s.research_id=h.research_id AND s.nonce=h.nonce)',[$parent['research_id'],$cutoff])->rowCount();
    });}
    query('DELETE FROM rate_limits WHERE expires<?',[time()]);
    set_setting('handoff_cleanup_at',now());set_setting('handoff_cleanup_deleted',(string)$deleted);
    return $deleted;
}
function wf_read_handoff(string $rid,string $id): array {
    research($rid);
    $row=one('SELECT * FROM workflow_handoffs WHERE id=? AND research_id=?',[$id,$rid])??fail(404,'Issued handoff not found.');
    wf_read_snapshot($rid,$row['snapshot_id']);
    if($row['task_id']&&wf_task($rid,$row['task_id'])['visibility']!=='public')fail(404,'Issued handoff not found.');
    if(!hash_equals($row['sha256'],hash('sha256',$row['body'])))throw new RuntimeException('Archived handoff integrity check failed.');
    return $row;
}
function wf_send_handoff(array $archive): void {
    header('Content-Type: text/plain; charset=utf-8');header('Cache-Control: no-store');
    header('X-Handoff-ID: '.$archive['id']);header('X-Handoff-SHA256: '.$archive['sha256']);
    echo $archive['body'];
}
