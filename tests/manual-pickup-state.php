<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';$_SESSION['admin']=1;$action=$argv[1]??'';
if($action==='create'){
 $c=create_research(['title'=>'[QA] Manual pickup browser '.uid(),'question'=>'Synthetic browser reservation workflow only; no scientific conclusion.','category_id'=>1,'terms'=>1]);
 moderate('request',$c['id'],'Approved','Synthetic manual pickup browser fixture.');$r=research($c['id']);echo json_encode(['id'=>$r['id'],'slug'=>$r['slug'],'task'=>wf_tasks($r['id'])[0]['id']]);exit;
}
$rid=$argv[2]??'';if(!one("SELECT id FROM research WHERE id=? AND title LIKE '[QA] Manual pickup browser %'",[$rid]))exit(2);
$t=wf_tasks($rid)[0];
if($action==='lease')echo json_encode(['active_count'=>(int)query('SELECT COUNT(*) FROM task_reservations WHERE research_id=? AND ended_at IS NULL AND expires_at>?',[$rid,wr_stamp()])->fetchColumn(),'expires'=>wr_live($rid,$t['id'])['expires_at']??null]);
elseif($action==='expire'||$action==='shorten')query('UPDATE task_reservations SET expires_at=? WHERE research_id=? AND ended_at IS NULL',[wr_stamp(time()+($action==='expire'?-1:300)),$rid]);
elseif($action==='oversize'){query('UPDATE research SET context=? WHERE id=?',[str_repeat('x',4194305),$rid]);touch_context($rid,false);}
elseif($action==='payload'){
 $d=wf_test_payload($rid,$t,'progress');echo json_encode($d,JSON_UNESCAPED_SLASHES);
}elseif($action==='actor-retained'){
 $row=one('SELECT s.actor_hash result_actor,v.actor_hash holder_actor FROM workflow_submissions s JOIN task_reservations v ON v.result_id=s.id WHERE s.research_id=? ORDER BY s.created_at DESC LIMIT 1',[$rid]);
 echo json_encode(['retained'=>(bool)$row&&hash_equals($row['result_actor'],$row['holder_actor'])]);
}elseif($action==='purge')purge_request($rid,$rid);
