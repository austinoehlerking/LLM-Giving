<?php
/** CLI-only, owned synthetic HTTP/lifecycle barrier fixture. Never exposed by the router. */
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';
session_start_safe();$_SESSION['admin']=1;$action=$argv[1]??'';
function vwr_owned(string $rid): array {
    return one("SELECT * FROM research WHERE id=? AND title LIKE '[QA] Volunteer worker lifecycle %'",[$rid])??throw new RuntimeException('Not an owned worker lifecycle fixture.');
}
function vwr_state(string $rid): array {
    vwr_owned($rid);
    return ['tasks'=>all('SELECT id,revision,state FROM workflow_tasks WHERE research_id=? ORDER BY id',[$rid]),
        'leases'=>all('SELECT id,task_revision,created_at,expires_at,ended_at,end_reason FROM task_reservations WHERE research_id=? ORDER BY id',[$rid]),
        'results'=>(int)one('SELECT COUNT(*) n FROM workflow_submissions WHERE research_id=?',[$rid])['n'],
        'returns'=>all('SELECT result_id,payload_hash FROM browser_returns WHERE research_id=? ORDER BY id',[$rid]),
        'assignments'=>all('SELECT ended_at,outcome,reservation_id FROM volunteer_assignments WHERE research_id=? ORDER BY id',[$rid])];
}
if($action==='create') {
    $c=create_research(['title'=>'[QA] Volunteer worker lifecycle '.uid(),'question'=>'Synthetic separate-worker lifecycle conflict and native packet retention. No scientific work is claimed.','category_id'=>1,'terms'=>1]);
    moderate('request',$c['id'],'Approved','Owned worker race fixture.');
    $_SESSION['workflow_actor']=bin2hex(random_bytes(32));
    $session=vs_create(['scope_kind'=>'research','research_id'=>$c['id'],'assignment_limit'=>3,'minutes'=>60,'model'=>'gpt-6-astra','reasoning'=>'max','tool'=>'Codex','authorize'=>1,'terms'=>1,'authorized_resources'=>1,'policy_version'=>terms_version(),'human_level'=>'none']);
    $a=vs_claim_next($session['id']);$s=vs_read($session['id']);$g=vs_assignment_grant($s,$a);$r=research($c['id']);$t=wf_task($r['id'],$a['task_id']);
    $packet=wf_test_payload($r['id'],$t,'progress');
    $packet['submission_key']=$g['scope']['submission_key'];$packet['context_id']=$g['scope']['context_id'];$packet['task_revision']=$g['scope']['revision'];
    $packet['handoff_code']=wf_handoff_code($t,wf_read_snapshot($r['id'],$packet['context_id']),$packet['submission_key']);
    // Private link is returned only to the local parent process in memory; it is never written to evidence.
    echo json_encode(['rid'=>$r['id'],'tid'=>$t['id'],'session'=>$s['id'],'link'=>rtrim(env('APP_URL'),'/').'/research/'.$r['slug'].'/return/'.$g['id'].'#'.vs_return_secret($g['id']),'packet'=>$packet],JSON_THROW_ON_ERROR);
} elseif($action==='lifecycle') {
    $rid=$argv[2]??'';vwr_owned($rid);$connection=(int)one('SELECT CONNECTION_ID() id')['id'];
    db()->beginTransaction();
    try {
        one('SELECT id FROM research WHERE id=? FOR UPDATE',[$rid]);
        echo json_encode(['locked'=>true]).PHP_EOL;fflush(STDOUT);
        $deadline=microtime(true)+12;$waiter=null;$observed=[];
        do {
            $processes=all('SHOW FULL PROCESSLIST');
            $observed=array_map(fn($p)=>['command'=>$p['Command']??'', 'state'=>$p['State']??'', 'query'=>preg_replace('/[a-f0-9]{32,64}/i','[redacted]',$p['Info']??'')],$processes);
            foreach($processes as $process) {
                $info=$process['Info']??'';
                // MariaDB exposes native prepared statements with ? instead of bound values.
                // This isolated server has one in-flight worker and one fixture parent lock.
                if((int)$process['Id']!==$connection && (($info==='SELECT id FROM research WHERE id=? FOR UPDATE') || (str_contains($info,'SELECT id FROM research WHERE id=') && str_contains($info,$rid) && str_contains($info,'FOR UPDATE')))){$waiter=(int)$process['Id'];break;}
            }
            if($waiter!==null)break;
            usleep(10000);
        } while(microtime(true)<$deadline);
        if($waiter===null)throw new RuntimeException('Worker did not reach the held research lock. Observed: '.json_encode($observed));
        // Staged lifecycle transition: same parent-serialized revision + lease invalidation as review correction.
        query('UPDATE workflow_tasks SET revision=revision+1 WHERE research_id=?',[$rid]);
        wr_invalidate($rid);
        query('UPDATE research SET context_version=context_version+1 WHERE id=?',[$rid]);
        $after=vwr_state($rid);
        db()->commit();
        echo json_encode(['worker_wait_observed'=>true,'state_after_lifecycle'=>$after],JSON_THROW_ON_ERROR).PHP_EOL;fflush(STDOUT);
    } finally {if(db()->inTransaction())db()->rollBack();}
} elseif($action==='state') {
    echo json_encode(vwr_state($argv[2]??''),JSON_THROW_ON_ERROR);
} elseif($action==='cleanup') {
    $rid=$argv[2]??'';vwr_owned($rid);
    foreach(all('SELECT DISTINCT session_id FROM volunteer_assignments WHERE research_id=?',[$rid]) as $s)query('DELETE FROM volunteer_sessions WHERE id=?',[$s['session_id']]);
    purge_request($rid,$rid);
} else throw new RuntimeException('Unknown worker lifecycle fixture action.');
