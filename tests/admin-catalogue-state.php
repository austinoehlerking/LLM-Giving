<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/admin-catalogue-fixture.php';$_SESSION['admin']=1;
$manifest=storage('qa/admin-catalogue-fixture.json');$action=$argv[1]??'';
if($action==='seed'){
    if(is_file($manifest))fail(409,'Owned catalogue fixture already exists; preserve it.');
    $f=ac_fixture_seed();file_put_contents($manifest,json_encode($f,JSON_THROW_ON_ERROR));echo json_encode($f,JSON_THROW_ON_ERROR);
}elseif($action==='inspect'){
    $f=json_decode(file_get_contents($manifest),true,512,JSON_THROW_ON_ERROR);$rows=[];
    foreach($f['ids'] as $key=>$id){$r=research($id,false);$rows[$key]=['listed'=>(int)$r['discovery_listed'],'moderation'=>$r['moderation'],'status'=>$r['status'],'context_version'=>$r['context_version']];}
    echo json_encode(['rows'=>$rows,'usage'=>admin_research_usage(array_values($f['ids'])),'audit'=>all("SELECT old_state,new_state,reason,visibility FROM audit_events WHERE research_id=? AND action='Discovery listing changed' ORDER BY id",[$f['ids']['usage']])],JSON_THROW_ON_ERROR);
}elseif($action==='cleanup'){
    if(is_file($manifest)){ac_fixture_cleanup(json_decode(file_get_contents($manifest),true,512,JSON_THROW_ON_ERROR));unlink($manifest);}
    query("DELETE FROM admins WHERE email='catalogue-qa@example.invalid'");
}else fail(422,'Choose a local catalogue fixture operation.');
