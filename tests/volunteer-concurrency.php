<?php
require __DIR__.'/bootstrap.php'; require __DIR__.'/workflow-fixture.php';
session_start_safe(); $_SESSION['admin']=1;
$action=$argv[1]??'';
function vrc_session(string $rid,?string $actor=null): string {
    $_SESSION['workflow_actor']=$actor??bin2hex(random_bytes(32));
    return vs_create(['scope_kind'=>'research','research_id'=>$rid,'assignment_limit'=>3,'minutes'=>60,'model'=>'gpt-6-astra','reasoning'=>'max','tool'=>'Codex','authorize'=>1,'terms'=>1,'authorized_resources'=>1,'policy_version'=>terms_version(),'human_level'=>'none'])['id'];
}
if($action==='create') {
    $projects=[];for($i=0;$i<7;$i++){$c=create_research(['title'=>'[QA] Volunteer race '.uid(),'question'=>'Synthetic race test for bounded volunteer sessions; no scientific research is claimed.','category_id'=>1,'terms'=>1]);moderate('request',$c['id'],'Approved','Disposable session race fixture.');$projects[]=$c['id'];}
    $sameActor=bin2hex(random_bytes(32));
    $sessions=[vrc_session($projects[0]),vrc_session($projects[1]),vrc_session($projects[1]),vrc_session($projects[2],$sameActor),vrc_session($projects[3],$sameActor),vrc_session($projects[4]),vrc_session($projects[5]),vrc_session($projects[6]),vrc_session($projects[6])];
    echo json_encode(['projects'=>$projects,'sessions'=>$sessions]);
} elseif($action==='worker') {
    $spec=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
    $session=vs_read($spec['session'],false);
    $_SESSION['volunteer_sessions'][$session['id']]=$session['secret_hash'];
    $directory=realpath(storage('qa/volunteers/concurrency'));
    if(!$directory || realpath(dirname($spec['gate']))!==$directory || realpath(dirname($spec['ready']))!==$directory)throw new RuntimeException('Unsafe synthetic race barrier.');
    file_put_contents($spec['ready'],'ready');$deadline=microtime(true)+15;
    while(!is_file($spec['gate'])){if(microtime(true)>$deadline)throw new RuntimeException('Synthetic session race timed out.');usleep(10000);}
    try {
        if($spec['operation']==='claim')$result=vs_claim_next($session['id'])['id'];
        elseif($spec['operation']==='stop'){vs_stop($session['id']);$result='stopped';}
        elseif($spec['operation']==='renew'){$a=vs_current($session);vs_assignment_action($session['id'],$a['id'],'renew');$result='renewed';}
        elseif($spec['operation']==='record') {
            $a=vs_current($session);if(!$a)fail(409,'No current assignment.');$g=vs_assignment_grant($session,$a);$t=wf_task($a['research_id'],$a['task_id']);
            $d=wf_test_payload($a['research_id'],$t,'progress');$d['submission_key']=$g['scope']['submission_key'];$d['context_id']=$g['scope']['context_id'];$d['task_revision']=$g['scope']['revision'];$d['handoff_code']=wf_handoff_code($t,wf_read_snapshot($a['research_id'],$d['context_id']),$d['submission_key']);
            $result=vs_record($session['id'],$a['id'],$d,false);
        } else throw new RuntimeException('Unknown synthetic race operation.');
        echo json_encode(['status'=>200,'result'=>$result]);
    } catch(HttpError $error) {echo json_encode(['status'=>$error->status]);}
} elseif($action==='claim') {
    $session=vs_read($argv[2],false);$_SESSION['volunteer_sessions'][$session['id']]=$session['secret_hash'];echo json_encode(vs_claim_next($session['id'])['id']);
} elseif($action==='expire-task') {
    $s=vs_read($argv[2],false);query('UPDATE task_reservations SET expires_at=? WHERE actor_hash=? AND ended_at IS NULL',[wr_stamp(wr_time()-1),$s['actor_hash']]);
} elseif($action==='state') {
    $s=vs_read($argv[2],false);
    echo json_encode(['used'=>(int)$s['assignments_used'],'stopped'=>(bool)$s['stopped_at'],'assignments'=>(int)one('SELECT COUNT(*) n FROM volunteer_assignments WHERE session_id=?',[$s['id']])['n'],
        'live'=>(int)one('SELECT COUNT(*) n FROM task_reservations WHERE actor_hash=? AND ended_at IS NULL AND expires_at>?',[$s['actor_hash'],wr_stamp()])['n'],
        'results'=>(int)one('SELECT COUNT(*) n FROM workflow_submissions WHERE actor_hash=?',[$s['actor_hash']])['n']]);
} elseif($action==='cleanup') {
    $data=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
    foreach($data['sessions'] as $id)query('DELETE FROM volunteer_sessions WHERE id=?',[$id]);
    foreach($data['projects'] as $id)if(one("SELECT id FROM research WHERE id=? AND title LIKE '[QA] Volunteer race%'",[$id]))purge_request($id,$id);
} else throw new RuntimeException('Unknown session concurrency fixture action.');
