<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';require_once ROOT.'/src/view.php';
session_start_safe();$_SESSION['admin']=1;putenv('RATE_LIMIT_NAMESPACE=manual-pickup-'.uid());
$checks=[];$ids=[];$clock=time();$GLOBALS['wr_test_clock']=function()use(&$clock){return $clock;};
function pc(bool $ok,string $label):void {global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
function pd(callable $f,int $code,string $label):void {try{$f();}catch(HttpError $e){pc($e->status===$code,$label.' ('.$e->getMessage().')');return;}throw new RuntimeException('Expected rejection: '.$label);}
function pf(): array {global $ids;$c=create_research(['title'=>'[QA] Manual reservation pickup '.uid(),'question'=>'Synthetic manual pickup and handoff preparation only; no scientific result.','category_id'=>1,'terms'=>1]);$ids[]=$c['id'];moderate('request',$c['id'],'Approved','Disposable manual pickup integration fixture.');$r=research($c['id']);return [$r,wf_tasks($r['id'])[0]];}
/** Render the real private controls; reads may never renew, release, submit or rewrite authority. */
function pview(array $r,array $task,array $grant,?array $continuation=null): string {
 $fingerprint=fn()=>[one('SELECT scope,consent,identity_id,result_id FROM browser_returns WHERE id=?',[$grant['id']]),all('SELECT * FROM task_reservations WHERE research_id=? AND task_id=? ORDER BY id',[$r['id'],$task['id']]),one('SELECT COUNT(*) n FROM workflow_submissions WHERE research_id=?',[$r['id']])];$before=$fingerprint();
 $reservationForm='workflow-result';$reservationMessage='';$reservationHandoff='';$error='';$status=$continuation?sr_status($r,$grant):null;$issuedLink=$continuation['link']??null;
 ob_start();include ROOT.'/views/'.($continuation?'volunteer-continuation':'task-reservation').'.php';$html=ob_get_clean();
 pc($fingerprint()===$before,'Rendering private recovery controls preserves leases, signed scope, consent, identity and result count');return $html;
}
function pactions(string $html): array {preg_match_all('~value="reservation-(renew|release|claim)"~',$html,$matches);return $matches[1];}
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
 pc(pactions(pview($r,$task,$grant))===['renew','release'],'Exact original active lease shows only Renew and Release authority');
 $public=wf_read_handoff($r['id'],$issued['archive_id'])['body'];
 pc(!str_contains($public,'PRIVATE VOLUNTEER AUTHORIZATION SUPPLEMENT')&&!str_contains($public,'Private return link:'),'Private authorization is not in public archives');
 mp_control($r,$task,'renew',1,$grant);
 pc(wr_live($r['id'],$task['id'])['actor_hash']===$originalActor,'Original volunteer can renew a just-issued handoff without a fragment exchange');
 $original=one('SELECT scope,consent,identity_id,expires_at FROM browser_returns WHERE id=?',[$grant['id']]);mp_control($r,$task,'release',1,$grant);
 pc(pactions(pview($r,$task,$grant))===['claim'],'Released original authorized handoff visibly offers explicit Reacquire reservation');
 $continuation=sr_continue($r,$grant['id']);query('UPDATE browser_returns SET expires_at=? WHERE id=?',[wr_stamp($clock),$grant['id']]);
 pc(pactions(pview($r,$task,$grant))===[],'Expired permission offers no lease mutation controls');
 $expiredGrant=ar_read($r,$grant['id'],false);$recoveryHtml=pview($r,$task,$expiredGrant,$continuation);
 pc(substr_count($recoveryHtml,'action="/continue/'.$continuation['id'].'"')===2&&str_contains($recoveryHtml,'class="continuation-controls"')&&str_contains($recoveryHtml,'name="operation" value="reauthorize"'),'Operational and human reauthorization forms use the explicit scoped continuation route, including the initial issuance response');
 preg_match_all('~<input[^>]*type="checkbox"[^>]*>~',$recoveryHtml,$agreements);pc(count($agreements[0])===3&&count(array_filter($agreements[0],fn($input)=>str_contains($input,'required')&&!str_contains($input,'checked')))===3&&str_contains($recoveryHtml,'name="csrf" value="'.e($_SESSION['csrf']).'"'),'Recovery retains deliberate unchecked legal/resource authorization and CSRF');
 query('UPDATE browser_returns SET expires_at=? WHERE id=?',[$original['expires_at'],$grant['id']]);$changedConsent=json_decode($original['consent'],true);$changedConsent['terms_version']='obsolete-qa-terms';query('UPDATE browser_returns SET consent=? WHERE id=?',[json_encode($changedConsent),$grant['id']]);
 pc(pactions(pview($r,$task,$grant))===[],'Changed terms cannot expose reacquisition through a released grant');query('UPDATE browser_returns SET consent=? WHERE id=?',[$original['consent'],$grant['id']]);
 query('UPDATE browser_returns SET revoked_at=? WHERE id=?',[wr_stamp(),$grant['id']]);pc(pactions(pview($r,$task,$grant))===[],'Revoked private grant exposes no lease mutation controls');query('UPDATE browser_returns SET revoked_at=NULL WHERE id=?',[$grant['id']]);
 query('UPDATE workflow_tasks SET revision=revision+1 WHERE id=?',[$task['id']]);pc(pactions(pview($r,wf_task($r['id'],$task['id']),$grant))===[],'Changed task revision cannot expose reacquisition');query('UPDATE workflow_tasks SET revision=? WHERE id=?',[$task['revision'],$task['id']]);
 $unproven=json_decode($original['scope'],true);unset($unproven['reservation_id']);$unproven['archive_id']=uid();query('UPDATE browser_returns SET scope=? WHERE id=?',[json_encode($unproven),$grant['id']]);pc(pactions(pview($r,$task,$grant))===[],'An unproven legacy original lease exposes no reacquisition authority');query('UPDATE browser_returns SET scope=? WHERE id=?',[$original['scope'],$grant['id']]);
 mp_control($r,$task,'claim',1,$grant);$recovered=one('SELECT scope,consent,identity_id FROM browser_returns WHERE id=?',[$grant['id']]);$signedBefore=json_decode($original['scope'],true);$signedAfter=json_decode($recovered['scope'],true);unset($signedBefore['reservation_id'],$signedAfter['reservation_id']);
 pc($signedBefore===$signedAfter&&$recovered['consent']===$original['consent']&&$recovered['identity_id']===$original['identity_id']&&pactions(pview($r,$task,$grant))===['renew','release'],'Explicit same-task reacquisition restores exact-bound controls without changing signed fields, consent or identity');
 $_SESSION['browser_returns'][$grant['id']]=$grant['secret_hash'];
 $_SESSION['workflow_actor']=bin2hex(random_bytes(32));
 $clock+=20;mp_control($r,$task,'renew',1,$grant);
 pc(wr_live($r['id'],$task['id'])['actor_hash']===$originalActor,'Authorized separate-browser controls preserve volunteer ownership');
 $clock+=3600;pc(mp_state($r,$task,$originalActor)['expired'],'Exact expiry offers explicit reacquisition');
 pc(pactions(pview($r,$task,$grant))===['claim'],'Expired lease with still-active captured permission visibly offers explicit reacquisition');
 $claim=mp_control($r,$task,'claim',1,$grant);
 pc($claim['handoff']===$public,'Reacquisition retains original authorized research instructions and identifiers');
 mp_control($r,$task,'release',1,$grant);$replacement=wr_claim($r['id'],$task['id'],1,$originalActor);
 pc(pactions(pview($r,$task,$grant))===[],'Same-actor replacement lease grants no Renew, Release or Reacquire control to the old credential');
 foreach(['renew','release','claim'] as $operation)pd(fn()=>mp_control($r,$task,$operation,1,$grant),409,'Old scoped credential cannot '.$operation.' another assignment lease');
 pc(wr_live($r['id'],$task['id'])['id']===$replacement['id']&&wr_live($r['id'],$task['id'])['expires_at']===$replacement['expires_at'],'Rejected old controls leave the same-owner replacement lease exact');
 wr_release($r['id'],$task['id'],$originalActor);wr_claim($r['id'],$task['id'],1,$other);
 pc(pactions(pview($r,$task,$grant))===[],'A different holder exposes no mutation controls to the original private grant');
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
