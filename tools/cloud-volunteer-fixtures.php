<?php
declare(strict_types=1);
require __DIR__.'/cloud-volunteer-contract.php';
try {
    cv_need(PHP_SAPI==='cli','CLI only.');$o=cv_args($argv);$scope=$o['scope']??bin2hex(random_bytes(16));cv_scope($scope);
    if($o['action']==='plan'||(in_array($o['action'],['create','cleanup'],true)&&empty($o['apply']))){echo json_encode(['mode'=>'DRY_RUN','action'=>$o['action'],'destination'=>$o['destination'],'scope'=>$scope,'spec'=>cv_spec($scope),'notice'=>'No database access, records, research answers, packets or phone/Cloud acceptance. Live setup remains pending authorized deployment and genuine Cloud browser support.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";exit;}
    cv_need(isset($o['scope']),'Mutation/read requires an explicit scope.');
    if($o['action']==='verify'){$m=cv_json_file($o['manifest']??'');cv_manifest_check($m,$scope,$o['destination']);$paths=cv_json_file($o['snapshots']??'');$snapshots=[];foreach($paths as $phase=>$file){cv_need(is_string($file),'Snapshot paths must be strings.');$snapshots[$phase]=cv_json_file($file);}$report=cv_verify($m,$snapshots,$o['result']??'');echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";exit($report['stored_result_verification']==='PASS'?0:1);}
    require __DIR__.'/../src/bootstrap.php';require __DIR__.'/cloud-volunteer-state.php';
    cv_guard($o,['environment'=>env('APP_ENV'),'host'=>env('DB_HOST'),'database'=>env('DB_NAME'),'url'=>env('APP_URL')]);
    if($o['destination']!=='local'){cv_need(function_exists('vs_profile_definition'),'Restricted-profile code is not deployed; live fixture work remains pending.');vs_profile_definition('restricted_web_v1');cv_need((bool)one("SHOW COLUMNS FROM volunteer_sessions LIKE 'session_profile'"),'Restricted-profile schema is not deployed.');}
    if($o['action']==='create'){$value=cv_create($o);}
    else{$m=cv_json_file($o['manifest']??'');cv_manifest_check($m,$scope,$o['destination']);if($o['action']!=='cleanup')cv_owned($m);
        $value=match($o['action']){'inspect'=>cv_inspect($m),'snapshot'=>cv_snapshot($m,$o['phase']??'', $o),'cleanup'=>cv_cleanup($m,$o),default=>throw new RuntimeException('Unsupported action.')};}
    echo json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){fwrite(STDERR,'Cloud synthetic tooling: '.$e->getMessage()."\n");exit(1);}
