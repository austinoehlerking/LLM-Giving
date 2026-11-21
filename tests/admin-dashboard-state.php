<?php
require __DIR__.'/bootstrap.php';
require __DIR__.'/workflow-fixture.php';
$_SESSION['admin']=1;
$action=$argv[1]??'';
$file=storage('qa/admin-dashboard/fixtures.json');

if($action==='seed'){
    if(is_file($file))fail(409,'The dashboard fixture already exists.');
    $ids=[];$titles=[
        'review'=>'Would more shade at small city libraries reduce summer heat during after-school activities?',
        'waiting'=>'A request waiting for a narrower research question',
        'withdrawn'=>'Withdrawn request must stay out of decisions',
        'held'=>'Effects of plastic pollution on the biological carbon pump in the deep ocean',
        'one'=>'Which public library events have comparable measures of attendance and repeat participation?',
        'two'=>'What can small towns learn from public reports on library opening hours?',
        'later'=>'Later approved study outside the first planning run'];
    foreach($titles as $key=>$title){
        $ids[$key]=create_research(['title'=>$title,'question'=>'Synthetic local request for reviewing administration and planning; use only lawful public sources.','category_id'=>1,'terms'=>1])['id'];
    }
    moderate('request',$ids['waiting'],'Changes Requested','Please supply a smaller scope.');
    change_status($ids['withdrawn'],'Withdrawn','Withdraw an isolated synthetic fixture.','administrator');
    moderate('request',$ids['held'],'Approved','Approved synthetic work for the packet hold case.');
    $held=wf_tasks($ids['held'])[0];
    $payload=wf_test_payload($ids['held'],$held,'progress');
    $payload['human_interaction']=['level'=>'influenced','details'=>'Synthetic request with an additional preference supplied during the run.'];
    $packet=wf_test_submit($ids['held'],$held['id'],$payload,hash('sha256',uid()));
    lp_enable();
    foreach(['one','two'] as $key)moderate('request',$ids[$key],'Approved','Approved synthetic brief for planning.');
    $seed=['ids'=>$ids,'titles'=>$titles,'packet'=>$packet,'slug'=>research($ids['one'])['slug']];
    file_put_contents($file,json_encode($seed,JSON_THROW_ON_ERROR),LOCK_EX);
    echo json_encode($seed,JSON_THROW_ON_ERROR);
}elseif($action==='approve-later'){
    $seed=json_decode(file_get_contents($file),true);
    moderate('request',$seed['ids']['later'],'Approved','Approved independently after the run was queued.');
}elseif($action==='cleanup'){
    if(is_file($file)){
        $seed=json_decode(file_get_contents($file),true);
        foreach($seed['ids'] as $id)purge_request($id,$id);
        unlink($file);
    }
    query("DELETE FROM settings WHERE name IN ('local_planner_run','local_planner_connection')");
    set_setting('local_planner_enabled','0');
    query("DELETE FROM admins WHERE email='admin-dashboard-qa@example.invalid'");
}else throw new RuntimeException('Unknown dashboard fixture action.');
