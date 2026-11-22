<?php
require __DIR__.'/bootstrap.php';
if(!in_array(env('APP_ENV'),['local','test'],true))throw new RuntimeException('Local requester fixture only.');
$command=$argv[1]??'';$rid=$argv[2]??'';
if(!preg_match('/^[a-f0-9]{32}$/D',$rid))throw new RuntimeException('Fixture ID required.');
$r=research($rid,false);
if($r['title']!=='[QA] Requester capability and historical settings')throw new RuntimeException('Not the requester capability fixture.');
switch($command){
 case 'history':
  $requirements=['version'=>2,'models'=>['legacy-model-identifier'],'efforts'=>[['level'=>'max','or_higher'=>false]],'modes'=>['ultracode'],'registry'=>[]];
  query('UPDATE research SET ai_requirements=? WHERE id=?',[json_encode($requirements,JSON_THROW_ON_ERROR),$rid]);
  break;
 case 'capture':
  $snapshot=wf_snapshot($rid);
  $package=json_decode($snapshot['package_json'],true,512,JSON_THROW_ON_ERROR);
  $package['requester_ai_requirements']=rq_requirements($r);
  $json=json_encode($package,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
  insert('workflow_snapshots',['id'=>uid(),'research_id'=>$rid,'round_no'=>$snapshot['round_no'],'context_version'=>$snapshot['context_version'],'package_json'=>$json,'sha256'=>hash('sha256',$json),'created_at'=>now()]);
  break;
 case 'cleanup':
  session_start_safe();$_SESSION['admin']=1;
  purge_request($rid,$rid);
  query('DELETE FROM admins WHERE email=?',['requester-capability-qa@example.invalid']);
  echo '{}';exit;
 case 'inspect':
  break;
 default:
  throw new RuntimeException('Unknown requester fixture operation.');
}
$r=research($rid,false);
echo json_encode([
 'requirements'=>$r['ai_requirements'],
 'external'=>external_requirements($r),
 'moderation'=>$r['moderation'],
 'status'=>$r['status'],
 'version'=>$r['context_version'],
 'domainAudit'=>(int)one("SELECT COUNT(*) n FROM audit_events WHERE research_id=? AND entity_type<>'route'",[$rid])['n'],
 'snapshots'=>array_map(fn($row)=>['digest'=>hash('sha256',$row['package_json']),'legacy'=>str_contains($row['package_json'],'legacy-model-identifier')],all('SELECT package_json FROM workflow_snapshots WHERE research_id=? ORDER BY id',[$rid])),
],JSON_THROW_ON_ERROR);
