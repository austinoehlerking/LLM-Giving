<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';
session_start_safe();$_SESSION['admin']=1;putenv('RATE_LIMIT_NAMESPACE=manual-pickup-'.uid());
$checks=[];$ids=[];$clock=time();$GLOBALS['wr_test_clock']=function()use(&$clock){return $clock;};
function pc(bool $ok,string $label):void {global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
function pd(callable $f,int $code,string $label):void {try{$f();}catch(HttpError $e){pc($e->status===$code,$label.' ('.$e->getMessage().')');return;}throw new RuntimeException('Expected rejection: '.$label);}
function pf(): array {global $ids;$c=create_research(['title'=>'[QA] Manual reservation pickup '.uid(),'question'=>'Synthetic manual pickup and handoff preparation only; no scientific result.','category_id'=>1,'terms'=>1]);$ids[]=$c['id'];moderate('request',$c['id'],'Approved','Disposable manual pickup integration fixture.');$r=research($c['id']);return [$r,wf_tasks($r['id'])[0]];}
try{
 [$r,$task]=pf();$actor=wf_actor();$other=hash('sha256',uid());
 pc(mp_next($r)['id']===$task['id'],'Study pickup finds an eligible task');
 pc(mp_state($r,$task)['available']&&wr_live($r['id'],$task['id'])===null,'Availability reads do not reserve');
 $picked=mp_pickup($r,$task['id'],(int)$task['revision']);$lease=wr_live($r['id'],$task['id']);
 pc($picked['archive']['task_id']===$task['id']&&str_contains($picked['archive']['body'],'REQUIRED OUTPUT JSON'),'Pickup prepares the complete signed handoff before success');
 pc($lease['expires_at']===wr_stamp($clock+3600),'Manual pickup uses shared exact 60 minute lease');
 pc(str_starts_with(mp_state($r,$task)['message'],'Reserved for you until '),'Holder sees required reserved-until label');
 pc(str_starts_with(mp_state($r,$task,$other)['message'],'In progress — reserved until '),'Other browsers see required in-progress label');
 $clock+=120;$again=mp_pickup($r,$task['id'],(int)$task['revision']);
 pc(wr_live($r['id'],$task['id'])['expires_at']===$lease['expires_at'],'Recopy prepares a new full archive without renewing');
 pc($again['archive']['sha256']===hash('sha256',$again['archive']['body']),'Recopy retains exact archive integrity');
 pc(mp_next($r,$actor)['id']===$task['id']&&mp_next($r,$other)===null,'Study selection resumes own work and skips another holder');
 pc(str_contains(mp_unavailable($r),'reserved')===false,'Holder is not told all tasks belong to others');
 wr_release($r['id'],$task['id'],$actor);wr_claim($r['id'],$task['id'],1,$other);
 pc(str_contains(mp_unavailable($r),'currently reserved'),'No available assignment has an honest explanation');
 $second=wf_new_task($r['id'],1,'parallel','research','Synthetic second task','Synthetic objective for pickup selection.','A synthetic bounded review.','Preserve a synthetic method.',[]);
 pc(mp_next($r)['id']===$second,'Study-level pickup skips the reserved first task');
 $picked2=mp_pickup($r,null);pc($picked2['task']['id']===$second,'Atomic study pickup reserves the selected eligible task');wr_release($r['id'],$second,$actor);wr_release($r['id'],$task['id'],$other);
 $budget=hash('sha256',env('RATE_LIMIT_NAMESPACE').'|handoff-bytes|'.gmdate('Y-m-d'));
 query('INSERT INTO rate_limits(bucket,hits,expires) VALUES (?,104857600,?) ON DUPLICATE KEY UPDATE hits=104857600',[$budget,time()+86400]);
 pd(fn()=>mp_pickup($r,$task['id'],1),503,'Archive preparation failure rejects pickup');
 pc(wr_live($r['id'],$task['id'])===null,'Failed preparation rolls back its new reservation');
 query('DELETE FROM rate_limits WHERE bucket=?',[$budget]);
 $first=mp_pickup($r,$task['id'],1);$before=wr_live($r['id'],$task['id']);
 query('INSERT INTO rate_limits(bucket,hits,expires) VALUES (?,104857600,?) ON DUPLICATE KEY UPDATE hits=104857600',[$budget,time()+86400]);
 pd(fn()=>mp_pickup($r,$task['id'],1),503,'Failed recopy is rejected');
 pc(wr_live($r['id'],$task['id'])['id']===$before['id']&&wr_live($r['id'],$task['id'])['expires_at']===$before['expires_at'],'Failed recopy preserves the previously explained lease');
 query('DELETE FROM rate_limits WHERE bucket=?',[$budget]);
 mp_control($r,$task,'renew',1);pc(wr_live($r['id'],$task['id'])['expires_at']===wr_stamp($clock+3600),'Explicit UI renewal calls shared service');
 mp_control($r,$task,'release',1);pc(wr_live($r['id'],$task['id'])===null,'Explicit UI release calls shared service');
 $post=['authorize'=>1,'terms'=>1,'policy_version'=>terms_version(),'human_level'=>'none'];
 $issued=mp_issued($r,mp_authorized_pickup($r,$post));$grant=$issued['grant'];$originalActor=$grant['actor_hash'];
 pc($issued['task']['id']===$task['id'],'Authorized study pickup binds its actual selected task');
 pc($originalActor===$actor&&wr_live($r['id'],$task['id'])['actor_hash']===$actor,'Authorized pickup retains original volunteer actor');
 $public=wf_read_handoff($r['id'],$issued['archive_id'])['body'];
 pc(!str_contains($public,'PRIVATE VOLUNTEER AUTHORIZATION SUPPLEMENT')&&!str_contains($public,'Private return link:'),'Private authorization is not in public archives');
 mp_control($r,$task,'renew',1,$grant);
 pc(wr_live($r['id'],$task['id'])['actor_hash']===$originalActor,'Original volunteer can renew a just-issued handoff without a fragment exchange');
 $_SESSION['browser_returns'][$grant['id']]=$grant['secret_hash'];
 $_SESSION['workflow_actor']=bin2hex(random_bytes(32));
 $clock+=20;mp_control($r,$task,'renew',1,$grant);
 pc(wr_live($r['id'],$task['id'])['actor_hash']===$originalActor,'Authorized separate-browser controls preserve volunteer ownership');
 $clock+=3600;pc(mp_state($r,$task,$originalActor)['expired'],'Exact expiry offers explicit reacquisition');
 $claim=mp_control($r,$task,'claim',1,$grant);
 pc($claim['handoff']===$public,'Reacquisition retains original authorized research instructions and identifiers');
 mp_control($r,$task,'release',1,$grant);wr_claim($r['id'],$task['id'],1,$other);
 pd(fn()=>mp_control($r,$task,'claim',1,$grant),409,'Expired authorized worker cannot displace another holder');
 pc(wr_live($r['id'],$task['id'])['actor_hash']===$other,'Conflicting holder remains unchanged');
 pd(fn()=>mp_control($r,$task,'claim',2,$grant),409,'Control cannot silently replace a signed revision');
 [$consumedResearch,$consumedTask]=pf();
 $consumed=mp_issued($consumedResearch,mp_authorized_pickup($consumedResearch,$post))['grant'];
 $_SESSION['browser_returns'][$consumed['id']]=$consumed['secret_hash'];
 $packet=wf_test_payload($consumedResearch['id'],$consumedTask,'progress');
 $packet['task_revision']=$consumed['scope']['revision'];$packet['context_id']=$consumed['scope']['context_id'];$packet['submission_key']=$consumed['scope']['submission_key'];
 $packet['handoff_code']=wf_handoff_code($consumedTask,wf_read_snapshot($consumedResearch['id'],$packet['context_id']),$packet['submission_key']);
 $receipt=ar_record($consumedResearch,$consumed,$packet,false);
 pc($consumed['result_id']===null&&ar_read($consumedResearch,$consumed['id'])['result_id']===$receipt,'A preloaded grant stays stale after actual recorded progress consumes it');
 pd(fn()=>mp_control($consumedResearch,$consumedTask,'claim',1,$consumed),409,'Consumed stale grant cannot reacquire through pickup controls');
 pc(wr_live($consumedResearch['id'],$consumedTask['id'])===null,'Rejected consumed-grant claim leaves no new lease');
 $fresh=mp_issued($consumedResearch,mp_authorized_pickup($consumedResearch,$post))['grant'];$freshLease=wr_live($consumedResearch['id'],$consumedTask['id']);
 foreach(['renew','release'] as $operation){
  pd(fn()=>mp_control($consumedResearch,$consumedTask,$operation,1,$consumed),409,'Consumed stale grant cannot '.$operation.' a fresh authorized reservation');
  pc(wr_live($consumedResearch['id'],$consumedTask['id'])['id']===$freshLease['id']&&wr_live($consumedResearch['id'],$consumedTask['id'])['expires_at']===$freshLease['expires_at'],'Rejected stale '.$operation.' preserves the legitimate generation and expiry');
 }
 mp_control($consumedResearch,$consumedTask,'release',1,$fresh);
 pc(wr_live($consumedResearch['id'],$consumedTask['id'])===null,'Fresh original-owner authorization can still release its own reservation');
 echo count($checks)." manual pickup integration checks passed.\n";
 file_put_contents(storage('qa/manual-pickup-unit.json'),json_encode($checks,JSON_PRETTY_PRINT));
}finally{foreach($ids as $id)purge_request($id,$id);unset($GLOBALS['wr_test_clock']);}
