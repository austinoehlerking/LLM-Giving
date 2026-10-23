<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/tool-fixture.php';
session_start_safe();$_SESSION['admin']=1;
$op=$argv[1]??'';$rid=$argv[2]??'';
if($op==='brief'){echo json_encode(rt_test_request());}
elseif($op==='http-fixture'){
 $created=create_research(rt_test_request());$rid=$created['id'];moderate('request',$rid,'Approved','Disposable tool HTTP fixture.');$task=rt_test_task($rid,'planning');wf_test_submit($rid,$task['id'],rt_test_plan($rid,$task),str_repeat('a',64));$task=rt_test_task($rid,'research');$result=wf_test_submit($rid,$task['id'],rt_test_build($rid,$task),str_repeat('a',64));echo json_encode(['id'=>$rid,'slug'=>research($rid)['slug'],'result'=>$result,'task'=>$task['id'],'snapshot'=>one('SELECT snapshot_id FROM workflow_submissions WHERE id=?',[$result])['snapshot_id']]);
}
elseif($op==='http-hide'){
 if(!one('SELECT id FROM research WHERE id=? AND title LIKE ?',[$rid,'[QA] Build a metric distance%']))exit(1);
 if(($argv[3]??'')==='snapshot')query('UPDATE workflow_snapshots SET public_allowed=0 WHERE research_id=?',[$rid]);
 else change_status($rid,'Withdrawn','Hide disposable HTTP tool fixture.','requester');
}
elseif($op==='approve'){moderate('request',$rid,'Approved','Approved disposable browser tool fixture.');echo json_encode(rt_test_plan($rid,rt_test_task($rid,'planning')));}
elseif($op==='build'){echo json_encode(rt_test_build($rid,rt_test_task($rid,'research')));}
elseif($op==='synthesis'){$task=rt_test_task($rid,'synthesis');$packet=wf_test_payload($rid,$task);$packet['tool_terms']=1;wf_test_submit($rid,$task['id'],$packet,str_repeat('b',64));$review=rt_test_task($rid,'review');echo json_encode(['task'=>$review,'packet'=>rt_test_review($rid,$review)]);}
elseif($op==='state'){echo json_encode(['research'=>research($rid,false),'tasks'=>wf_tasks($rid),'submissions'=>wf_public_data($rid)['submissions']]);}
elseif($op==='cleanup'){if(one('SELECT id FROM research WHERE id=? AND title LIKE ?',[$rid,'[QA] Build a metric distance%']))purge_request($rid,$rid);}
else exit(1);