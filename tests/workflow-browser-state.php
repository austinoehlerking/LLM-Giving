<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';

$_SESSION['admin']=1;
$action=$argv[1]??'';
if($action==='limit'){echo setting('submission_limit','12');if(isset($argv[2]))set_setting('submission_limit',(string)max(1,min(1000,(int)$argv[2])));
}elseif($action==='create'){
 $r=create_research(['title'=>'[QA] Plastics and the deep-ocean carbon pump','question'=>'Assess whether marine plastics alter biological carbon export and identify evidence gaps. Synthetic browser workflow fixture, not a real result.','context'=>'Disposable local QA using synthetic planning and findings. No scientific conclusion is established.','category_id'=>one("SELECT id FROM categories WHERE name='Environment & Climate'")['id'],'terms'=>1]);
 echo json_encode($r+['slug'=>research($r['id'],false)['slug']]);
}elseif($action==='payload'){
 $rid=$argv[2];if(!one("SELECT id FROM research WHERE id=? AND title LIKE '[QA]%'",[$rid]))exit(2);
 echo json_encode(wf_test_payload($rid,wf_task($rid,$argv[3]),$argv[4]??'completed'),JSON_UNESCAPED_SLASHES);
}
