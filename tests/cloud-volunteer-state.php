<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/../tools/cloud-volunteer-contract.php';require __DIR__.'/../tools/cloud-volunteer-state.php';
$n=0;function cvi(bool $ok,string $label): void {global $n;if(!$ok)throw new RuntimeException($label);$n++;}
function cvid(callable $fn,string $label): void {try{$fn();}catch(Throwable){cvi(true,$label);return;}throw new RuntimeException('Expected rejection: '.$label);}
$o=['scope'=>uid(),'destination'=>'local','candidate'=>str_repeat('a',40),'tree'=>str_repeat('b',40),'apply'=>true];cv_guard($o,['environment'=>env('APP_ENV'),'host'=>env('DB_HOST'),'database'=>env('DB_NAME'),'url'=>env('APP_URL')]);$m=null;$foreign=null;
try{
    $before=(int)one('SELECT COUNT(*) AS n FROM research')['n'];$m=cv_create($o);cv_manifest_check($m,$o['scope'],'local');cvi((int)one('SELECT COUNT(*) AS n FROM research')['n']===$before+1,'Only one isolated study created');cvi(cv_create($o)===$m,'Repeated creation reuses exact identifiers without writing work');
    cvi(!isset($_SESSION['admin']),'Scoped operator flag is restored after setup');
    $actor=hash('sha256',uid());$task=$m['tasks']['narrative'];wr_claim($m['research']['id'],$task['id'],$task['revision'],$actor);
    try{cvid(fn()=>cv_cleanup($m,$o+['confirm-scope'=>$m['scope']]),'Cleanup refuses an active manual reservation');}finally{wr_release($m['research']['id'],$task['id'],$actor);}
    $inspect=cv_inspect($m);cvi($inspect['result_count']===0,'Setup generated no submissions');$r=research($m['research']['id']);cvi(external_requirements($r)['calculation_help']==='maybe'&&external_requirements($r)['external_tools']==='none','Maybe and no mandatory tools retained');
    foreach($m['tasks'] as $role=>$task){$row=wf_task($r['id'],$task['id']);cvi($row['state']==='Ready','Task ready: '.$role);cvi((bool)$row['computational']===($role!=='narrative'),'Computational control flag: '.$role);}
    cvi(count(wf_criteria(wf_task($r['id'],$m['tasks']['narrative']['id'])))===2,'Narrative acceptance has two distinct criteria');
    $snapshot=cv_snapshot($m,'before_check',$o);cvi($snapshot['study_result_count']===0&&$snapshot['results']===[],'Read-only independent observation sees no recorded result');cvi($snapshot['control_assignments']===0&&$snapshot['control_results']===0,'Control tasks untouched');cvi(!str_contains(json_encode($snapshot),'secret_hash')&&!str_contains(json_encode($snapshot),'actor_hash'),'Observation omits private authorization and actor hashes');
    cvi(cv_snapshot($m,'after_check',$o)['study_result_count']===0,'A second observation itself records no result');
    $foreign=cv_create(array_replace($o,['scope'=>uid()]));$tampered=$m;$tampered['research']['id']=$foreign['research']['id'];cvid(fn()=>cv_cleanup($tampered,$o+['confirm-scope'=>$m['scope']]),'Manifest cannot purge another scope');cvid(fn()=>cv_cleanup($m,$o+['confirm-scope'=>str_repeat('0',32)]),'Cleanup needs exact scope confirmation');cvi((bool)one('SELECT id FROM research WHERE id=?',[$foreign['research']['id']]),'Foreign fixture survives rejected cleanup');
    $removed=cv_cleanup($m,$o+['confirm-scope'=>$m['scope']]);cvi($removed['removed_research_id']===$m['research']['id'],'Cleanup names its one removed study');cvi(cv_cleanup($m,$o+['confirm-scope'=>$m['scope']])['already_absent']===true,'Cleanup is idempotent after explicit scoped removal');$m=null;
    echo $n." Cloud synthetic setup/ownership/read-only observation checks passed. No Cloud research packet or result was authored.\n";
}finally{foreach([$m,$foreign] as $fixture)if($fixture&&one('SELECT id FROM research WHERE id=?',[$fixture['research']['id']]))cv_cleanup($fixture,$o+['confirm-scope'=>$fixture['scope']]);}
