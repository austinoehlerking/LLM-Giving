<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/calculation-fixture.php';

if(($argv[1]??'')==='checkpoint-demo'){
 $_SESSION['admin']=1;$r=create_research(['title'=>'[Pilot] Climate calculation — saved checkpoint '.substr(uid(),0,6),'question'=>'Clearly labeled software pilot using the existing climate calculation. Baseline executed, then interrupted before paired yield allocation and sensitivity.','category_id'=>one('SELECT id FROM categories LIMIT 1')['id'],'computational_models'=>true,'terms'=>1]);
 moderate('request',$r['id'],'Approved','Approve the local saved-checkpoint demonstration.');$tasks=wf_tasks($r['id']);wf_test_submit($r['id'],$tasks[0]['id'],rc_test_plan($r['id'],$tasks[0]),str_repeat('a',64));
 $task=array_values(array_filter(wf_tasks($r['id']),fn($t)=>$t['kind']==='research'))[0];$progress=wf_test_submit($r['id'],$task['id'],rc_test_calculation($r['id'],$task),str_repeat('a',64));
 echo json_encode(['research_id'=>$r['id'],'task_id'=>$task['id'],'progress_id'=>$progress,'url'=>'/research/'.research($r['id'])['slug'].'/tasks/'.$task['id']]);exit;
}
$rid=$argv[2]??'';$r=research($rid,false);if(!str_starts_with($r['title'],'[Pilot] Climate calculation')&&!str_starts_with($r['title'],'[QA]'))exit(2);
$task=wf_task($rid,$argv[3]);$action=$argv[1];
if($action==='plan')$packet=rc_test_plan($rid,$task);
elseif($action==='baseline')$packet=rc_test_calculation($rid,$task);
elseif($action==='complete')$packet=rc_test_calculation($rid,$task,'complete',$argv[4]??'');
elseif($action==='review')$packet=rc_test_review($rid,$task);
elseif($action==='correction')$packet=rc_test_review($rid,$task,'needs_correction');
elseif($action==='synthesis')$packet=rc_test_synthesis($rid,$task);
else exit(2);
echo json_encode($packet,JSON_UNESCAPED_SLASHES);