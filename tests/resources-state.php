<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/resources-fixture.php';$_SESSION['admin']=1;
$action=$argv[1]??'';
if($action==='create'){echo json_encode(rs_test_create());exit;}
$rid=$argv[2]??'';if(!one("SELECT id FROM research WHERE id=? AND title LIKE '[Resources QA]%'",[$rid]))exit(2);
if($action==='cleanup'){purge_request($rid,$rid);exit;}
if($action==='packet'){echo json_encode(wf_test_payload($rid,wf_task($rid,$argv[3])));exit;}
if($action==='catalogue'){echo json_encode(rs_catalogue($rid));exit;}
if($action==='mutate'){
    $mode=$argv[3];$target=$argv[4];$value=$argv[5];
    if($mode==='visibility'&&in_array($value,['public','removed'],true))query('UPDATE workflow_submissions SET visibility=? WHERE research_id=? AND id=?',[$value,$rid,$target]);
    elseif($mode==='admission'&&in_array($value,['accepted','quarantined','rejected'],true))query('UPDATE workflow_submissions SET admission=? WHERE research_id=? AND id=?',[$value,$rid,$target]);
    elseif($mode==='snapshot'&&in_array($value,['0','1'],true))query('UPDATE workflow_snapshots SET public_allowed=? WHERE research_id=? AND id=?',[(int)$value,$rid,$target]);
    elseif($mode==='task'&&in_array($value,['public','removed'],true))query('UPDATE workflow_tasks SET visibility=? WHERE research_id=? AND id=?',[$value,$rid,$target]);
    elseif($mode==='parent'&&in_array($value,['Needs Research','Withdrawn'],true))query('UPDATE research SET status=? WHERE id=?',[$value,$rid]);
    else exit(2);
    touch_context($rid);echo 'Fixture state changed.';
}
