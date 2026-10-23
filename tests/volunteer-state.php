<?php
require __DIR__.'/bootstrap.php'; require __DIR__.'/workflow-fixture.php';
$_SESSION['admin']=1; $action=$argv[1]??'';
if($action==='create') {
    $fixtures=[];
    for($i=0;$i<3;$i++) {
        $created=create_research(['title'=>'[QA] Volunteer browser '.uid(),'question'=>'Synthetic anonymous volunteer browser workflow. This is not scientific validation or real AI execution.','category_id'=>1,'terms'=>1]);
        moderate('request',$created['id'],'Approved','Disposable volunteer browser fixture.');
        $task=wf_tasks($created['id'])[0];wf_test_submit($created['id'],$task['id'],wf_test_payload($created['id'],$task),hash('sha256',uid()));
        $r=research($created['id']);$fixtures[]=['id'=>$r['id'],'slug'=>$r['slug']];
    }
    echo json_encode($fixtures);
} elseif($action==='cleanup') {
    $data=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
    foreach($data['sessions']??[] as $id)query('DELETE FROM volunteer_sessions WHERE id=?',[$id]);
    foreach($data['fixtures']??[] as $fixture)if(one("SELECT id FROM research WHERE id=? AND title LIKE '[QA] Volunteer browser%'",[$fixture['id']]))purge_request($fixture['id'],$fixture['id']);
} elseif($action==='withdraw-study') {
    $rid=$argv[2]??'';
    if(!one("SELECT id FROM research WHERE id=? AND title LIKE '[QA] Volunteer browser%'",[$rid]))throw new RuntimeException('Not an owned volunteer browser fixture.');
    change_status($rid,'Withdrawn','Synthetic private-history browser check.','requester');echo 'withdrawn';
} else {
    $session=vs_read($argv[2]??'',false);$assignment=vs_current($session);
    if($action==='packet') {
        if(!$assignment)fail(409,'No current test assignment.');
        $grant=vs_assignment_grant($session,$assignment);$task=wf_task($assignment['research_id'],$assignment['task_id']);
        $packet=wf_test_payload($assignment['research_id'],$task,$argv[3]??'completed');
        $packet['submission_key']=$grant['scope']['submission_key'];$packet['context_id']=$grant['scope']['context_id'];$packet['task_revision']=$grant['scope']['revision'];
        $packet['handoff_code']=wf_handoff_code($task,wf_read_snapshot($assignment['research_id'],$packet['context_id']),$packet['submission_key']);
        echo json_encode($packet);
    } elseif($action==='expire-task') {
        if(!$assignment)fail(409,'No current test assignment.');
        query('UPDATE task_reservations SET expires_at=? WHERE id=?',[wr_stamp(wr_time()-1),$assignment['reservation_id']]);echo 'expired';
    } elseif($action==='expire-session') {
        query('UPDATE volunteer_sessions SET expires_at=? WHERE id=?',[wr_stamp(wr_time()-1),$session['id']]);echo 'expired';
    } elseif($action==='state') {
        $lease=$assignment?wr_live($assignment['research_id'],$assignment['task_id']):null;
        echo json_encode(['used'=>(int)$session['assignments_used'],'state'=>vs_state($session),'current'=>$assignment['id']??null,
            'task'=>$assignment['task_id']??null,'research'=>$assignment['research_id']??null,'lease_expiry'=>$lease['expires_at']??null,
            'deadline'=>$session['expires_at'],'outcomes'=>array_column(all('SELECT outcome FROM volunteer_assignments WHERE session_id=? ORDER BY created_at,id',[$session['id']]),'outcome'),
            'lineage_preserved'=>!(bool)one('SELECT a.id FROM volunteer_assignments a JOIN browser_returns b ON b.id=a.return_id WHERE a.session_id=? AND b.actor_hash<>? LIMIT 1',[$session['id'],$session['actor_hash']]),
            'recorded'=>(int)one('SELECT COUNT(*) n FROM volunteer_assignments a JOIN browser_returns b ON b.id=a.return_id JOIN workflow_submissions s ON s.id=b.result_id AND s.research_id=a.research_id WHERE a.session_id=?',[$session['id']])['n']]);
    } else throw new RuntimeException('Unknown volunteer browser fixture action.');
}
