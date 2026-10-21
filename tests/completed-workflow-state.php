<?php
/** Portable, explicitly synthetic completed workflow for citation/retention browser QA. */
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';$_SESSION['admin']=1;
$c=create_research(['title'=>'[QA] Synthetic completed workflow '.uid(),'question'=>'Synthetic local workflow evidence for browser regression, never a scientific finding.','category_id'=>1,'terms'=>1]);moderate('request',$c['id'],'Approved','Synthetic browser fixture publication.');$rid=$c['id'];$actor=hash('sha256','synthetic-builder-'.uid());
$plan=wf_tasks($rid)[0];wf_submit($rid,$plan['id'],wf_test_payload($rid,$plan),$actor);
foreach(['research','synthesis','review'] as $kind)foreach(wf_tasks($rid) as $task)if($task['kind']===$kind){$current=wf_task($rid,$task['id']);if($current['state']==='Ready')wf_submit($rid,$current['id'],wf_test_payload($rid,$current,$kind==='review'?'verified':'completed'),$kind==='review'?hash('sha256','synthetic-independent-reviewer-'.uid()):$actor);}
echo json_encode(['id'=>$rid,'slug'=>research($rid)['slug']]);
