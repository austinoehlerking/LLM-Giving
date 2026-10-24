<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/derivation-fixture.php';
session_start_safe();$_SESSION['admin']=1;
if(($argv[1]??'')==='setup'){
 $fixture=rd_test_create('Browser model-derived arithmetic');$rid=$fixture['id'];$task=$fixture['task'];
 $result=wf_test_submit($rid,$task['id'],rd_test_payload($rid,$task),str_repeat('b',64));
 $syn=rd_test_task($rid,'synthesis');wf_test_submit($rid,$syn['id'],rd_test_payload($rid,$syn),str_repeat('b',64));
 $review=rd_test_task($rid,'review');$assessed=wf_test_submit($rid,$review['id'],rd_test_review($rid,$review),str_repeat('c',64));$route='/research/'.research($rid)['slug'];
 echo json_encode(['id'=>$rid,'result'=>$route.'/results/'.$result,'review'=>$route.'/results/'.$assessed,'resources'=>$route.'/resources','bundle'=>$route.'/results/'.$result.'/derivations/share/bundle.json']);
}elseif(($argv[1]??'')==='cleanup'){
 $rid=$argv[2]??'';$r=one('SELECT * FROM research WHERE id=?',[$rid]);
 if($r&&str_starts_with($r['title'],'[QA] Browser model-derived arithmetic '))purge_request($rid,$rid);else exit(2);
}else exit(2);
