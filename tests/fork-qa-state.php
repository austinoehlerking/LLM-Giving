<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';require __DIR__.'/fork-fixture.php';require __DIR__.'/resources-fixture.php';
$_SESSION['admin']=1;$action=$argv[1]??'';
if($action==='resources'){echo json_encode(rs_test_create());exit;}
if($action==='setup'){$p=rf_test_parent();echo json_encode(['id'=>$p['id'],'slug'=>$p['r']['slug']]);exit;}
$rid=$argv[2]??'';
$r=one("SELECT id FROM research WHERE id=? AND (title LIKE '[QA]%' OR title LIKE '[Resources QA]%')",[$rid])??fail(404,'Only synthetic QA research may be changed.');
if($action==='approve'){moderate('request',$rid,'Approved','Local synthetic fork browser QA.');echo 'approved';}
elseif($action==='plan'){$t=wf_tasks($rid)[0];$id=wf_submit($rid,$t['id'],wf_test_payload($rid,$t),str_repeat('d',64));echo $id;}
elseif($action==='withdraw'){change_status($rid,'Withdrawn','Local source visibility QA.','requester');}
elseif($action==='restore'){change_status($rid,'Open for Research','Local source restoration QA.','administrator');}
elseif($action==='revoke'){query('UPDATE workflow_snapshots SET public_allowed=0 WHERE id IN (SELECT snapshot_id FROM research_forks WHERE parent_id=?)',[$rid]);}
elseif($action==='cleanup'){foreach(all('SELECT research_id FROM research_forks WHERE parent_id=?',[$rid]) as $c)purge_request($c['research_id'],$c['research_id']);purge_request($rid,$rid);}
else fail(422,'Unknown local fork QA action.');
