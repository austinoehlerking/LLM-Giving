<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';$_SESSION['admin']=1;$action=$argv[1]??'';
if($action==='create'){
 $d=['title'=>'[QA] AI browser return '.uid(),'question'=>'Synthetic browser handover verification only; no scientific findings.','context'=>'Clearly labeled local test fixture.','category_id'=>1,'terms'=>1];
 if(($argv[2]??'')==='tool'){$d['project_type']='research_tool';foreach(RT_FIELDS as $key=>$label)$d['tool_'.$key]='Synthetic local tool requirement, not a scientific claim.';}
 $c=create_research($d);moderate('request',$c['id'],'Approved','Synthetic local browser authorization fixture.');$r=research($c['id']);echo json_encode(['id'=>$r['id'],'slug'=>$r['slug'],'task'=>wf_tasks($r['id'])[0]['id']]);
}elseif($action==='payload'){
 $rid=$argv[2];if(!one("SELECT id FROM research WHERE id=? AND title LIKE '[QA]%'",[$rid]))exit(2);$g=one('SELECT * FROM browser_returns WHERE id=? AND research_id=?',[$argv[3],$rid]);$scope=json_decode($g['scope'],true);$t=wf_task($rid,$g['task_id']);$d=wf_test_payload($rid,$t,'progress');$d['submission_key']=$scope['submission_key'];$d['context_id']=$scope['context_id'];$d['task_revision']=$scope['revision'];$d['handoff_code']=wf_handoff_code($t,wf_read_snapshot($rid,$scope['context_id']),$d['submission_key']);echo json_encode($d);
 }elseif($action==='contribution'){
 $rid=$argv[2];$id=$argv[3];
 if(!one("SELECT id FROM research WHERE id=? AND title LIKE '[QA]%'",[$rid]))exit(2);
 $row=one('SELECT moderation,submission_method,human_influence FROM contributions WHERE id=? AND research_id=?',[$id,$rid]);
 if(!$row)exit(2);echo json_encode($row);
}elseif($action==='count'){echo one('SELECT COUNT(*) n FROM workflow_submissions WHERE research_id=?',[$argv[2]])['n'];}
