<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';require __DIR__.'/fork-fixture.php';
session_start_safe();$_SESSION['admin']=1;putenv('RATE_LIMIT_NAMESPACE=reservations-'.uid());
$checks=[];$ids=[];$clock=time();$GLOBALS['wr_test_clock']=function()use(&$clock){return $clock;};
function vcheck(bool $ok,string $label): void {global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
function vdeny(callable $f,int $code,string $label): void {try{$f();}catch(HttpError $e){vcheck($e->status===$code,$label.' ('.$e->getMessage().')');return;}throw new RuntimeException('Expected rejection: '.$label);}
function vfixture(): array {global $ids;$c=create_research(['title'=>'[QA] Reservation '.uid(),'question'=>'Synthetic reservation state and concurrency verification only.','category_id'=>1,'terms'=>1]);$ids[]=$c['id'];moderate('request',$c['id'],'Approved','Disposable reservation fixture.');return [$c['id'],wf_tasks($c['id'])[0]];}
function vactor(): string {return hash('sha256',uid());}
try{
 [$rid,$t]=vfixture();[$foreign,$ft]=vfixture();$a=vactor();$b=vactor();$revision=(int)$t['revision'];
 $d=wf_test_payload($rid,$t,'progress');
 vdeny(fn()=>wf_submit($rid,$t['id'],$d,$a),409,'Unreserved valid packet cannot record');
 $first=wr_claim($rid,$t['id'],$revision,$a);$start=$clock;
 vcheck($first['created_at']===wr_stamp($clock)&&$first['expires_at']===wr_stamp($clock+3600),'Pickup lasts exactly 3600 server seconds');
 vcheck(wr_availability($rid,$t['id'])===['status'=>'reserved','expires_at_utc'=>wr_stamp($clock+3600),'task_revision'=>1],'Public status excludes identity and capabilities');
 vdeny(fn()=>wr_claim($rid,$t['id'],$revision,$b),409,'Another holder cannot claim');
 vdeny(fn()=>wr_claim($foreign,$ft['id'],1,$a),409,'One actor cannot accumulate project reservations');
 vdeny(fn()=>wr_renew($rid,$t['id'],$revision,$b),409,'Other actor cannot renew');
 vdeny(fn()=>wr_release($rid,$t['id'],$b),403,'Other actor cannot release');
 $clock+=120;
 vcheck(wr_claim($rid,$t['id'],$revision,$a)['expires_at']===$first['expires_at'],'Repeated pickup does not renew');
 wf_issue_handoff($rid,$t['id']);wf_snapshot($rid);
 vcheck(wr_live($rid,$t['id'])['expires_at']===$first['expires_at'],'Copies, context reads and viewing do not renew');
 $renewed=wr_renew($rid,$t['id'],$revision,$a);
 vcheck($renewed['expires_at']===wr_stamp($clock+3600),'Renewal resets to renewal time plus 60 minutes');
 $clock=$start+3719;vcheck(wr_availability($rid,$t['id'])['status']==='reserved','One second before renewed expiry is reserved');
 $clock++;vcheck(wr_availability($rid,$t['id'])['status']==='available','Exact expiry boundary is available without cleanup');
 vdeny(fn()=>wr_renew($rid,$t['id'],$revision,$a),409,'Expired reservation cannot renew');
 vdeny(fn()=>wf_submit($rid,$t['id'],$d,$a),409,'Expired holder cannot submit');
 wr_claim($rid,$t['id'],$revision,$b);
 vdeny(fn()=>wr_claim($rid,$t['id'],$revision,$a),409,'Expired holder cannot displace new holder');
 wr_release($rid,$t['id'],$b);vcheck(wr_availability($rid,$t['id'])['status']==='available','Release immediately restores availability');
 $lease=wr_claim($rid,$t['id'],$revision,$a);
 $before=count(all('SELECT id FROM task_reservations WHERE research_id=?',[$rid]));
 $clock+=10;vcheck(wf_submit($rid,$t['id'],$d,$a,true)==='','Check passes without recording');
 vcheck(wr_live($rid,$t['id'])['expires_at']===$lease['expires_at']&&count(all('SELECT id FROM task_reservations WHERE research_id=?',[$rid]))===$before,'Check neither renews nor consumes nor creates a lease');
 $bad=$d;$bad['body']='short';vdeny(fn()=>wf_submit($rid,$t['id'],$bad,$a),422,'Recoverable validation error rejected');
 vcheck(wr_live($rid,$t['id'])['id']===$lease['id'],'Validation error retains reservation');
 $bad=$d;$bad['terms']=0;vdeny(fn()=>wf_submit($rid,$t['id'],$bad,$a),422,'Reservation does not replace terms');
 $bad=$d;$bad['metadata']['reasoning']='low';vdeny(fn()=>wf_submit($rid,$t['id'],$bad,$a),422,'Reservation does not bypass planning policy');
 $result=wf_submit($rid,$t['id'],$d,$a);
 vcheck(wr_live($rid,$t['id'])===null&&one('SELECT end_reason,result_id FROM task_reservations WHERE id=?',[$lease['id']])===['end_reason'=>'submitted','result_id'=>$result],'Recorded progress consumes reservation');
 $next=wr_claim($rid,$t['id'],$revision,$b);$clock+=3601;
 vcheck(wf_submit($rid,$t['id'],$d,$a)===$result,'Recorded duplicate recovers receipt after expiry');
 vcheck(one('SELECT ended_at FROM task_reservations WHERE id=?',[$next['id']])['ended_at']===null,'Receipt recovery does not consume another lease');
 $bad=$d;$bad['body'].=' changed';vdeny(fn()=>wf_submit($rid,$t['id'],$bad,$a),409,'Changed duplicate cannot overwrite receipt');
 wr_claim($rid,$t['id'],$revision,$a);
 $held=wf_test_payload($rid,$t,'progress');$held['human_interaction']=['level'=>'influenced','details'=>'Synthetic human scientific steering requiring moderation.'];
 $heldId=wf_submit($rid,$t['id'],$held,$a);
 vcheck(wr_live($rid,$t['id'])===null&&one('SELECT admission FROM workflow_submissions WHERE id=?',[$heldId])['admission']==='quarantined','Quarantined recording consumes lease');
 vcheck(wf_submit($rid,$t['id'],$held,$a)===$heldId,'Quarantined duplicate recovers before current hold checks');
 vdeny(fn()=>wr_claim($rid,$t['id'],1,$b),409,'Moderation hold prevents pickup');
 wf_review_influence($rid,$heldId,'Reject disposable influenced fixture; fresh work is required.','reject');
 wr_claim($rid,$t['id'],1,$a);query('UPDATE workflow_tasks SET revision=revision+1 WHERE id=?',[$t['id']]);
 $stale=wf_test_payload($rid,$t,'progress');vdeny(fn()=>wf_submit($rid,$t['id'],$stale,$a),409,'New packet cannot use stale task revision');
 vcheck(wf_submit($rid,$t['id'],$d,$a)===$result,'Historical exact retry survives revision change');
 vdeny(fn()=>wr_claim($rid,$t['id'],1,$a),409,'Old revision cannot be reacquired');
 $new=wr_claim($rid,$t['id'],2,$b);vcheck($new['task_revision']===2,'New revision immediately available despite obsolete lease');
 wr_release($rid,$t['id'],$b);wr_claim($rid,$t['id'],2,$a);
 change_status($rid,'Withdrawn','Synthetic withdrawal.','administrator');
 vdeny(fn()=>wr_renew($rid,$t['id'],2,$a),404,'Withdrawal invalidates continuation');
 change_status($rid,'Needs Research Plan','Restore synthetic fixture.','administrator');
 vcheck(wr_live($rid,$t['id'])===null,'Restoring availability does not resurrect withdrawn lease');
 wr_claim($rid,$t['id'],2,$a);tx(fn()=>wf_restart($rid,'Synthetic supersession.'));
 vdeny(fn()=>wr_renew($rid,$t['id'],2,$a),409,'Superseded task cannot renew');
 vcheck(wr_live($rid,$t['id'])===null,'Supersession ends the old lease');
 $current=wf_tasks($rid)[0];wr_claim($rid,$current['id'],1,$a);
 query("UPDATE workflow_tasks SET visibility='private' WHERE id=?",[$current['id']]);
 vdeny(fn()=>wr_claim($rid,$current['id'],1,$b),409,'Private task cannot be reserved');
 query("UPDATE workflow_tasks SET visibility='public' WHERE id=?",[$current['id']]);wr_release($rid,$current['id'],$a);
 // Complete a real fixture DAG to test dependency and reviewer checks at pickup.

 $fd=wf_test_payload($foreign,$ft);$builder=vactor();wr_claim($foreign,$ft['id'],1,$builder);wf_submit($foreign,$ft['id'],$fd,$builder);
 $tasks=wf_tasks($foreign);$researchTasks=array_values(array_filter($tasks,fn($x)=>$x['kind']==='research'));
 $waiting=$researchTasks[1];vdeny(fn()=>wr_claim($foreign,$waiting['id'],1,$a),409,'Waiting dependency prevents pickup');
 foreach($researchTasks as $task){wr_claim($foreign,$task['id'],1,$builder);wf_submit($foreign,$task['id'],wf_test_payload($foreign,$task),$builder);}
 $syn=array_values(array_filter(wf_tasks($foreign),fn($x)=>$x['kind']==='synthesis'))[0];wr_claim($foreign,$syn['id'],1,$builder);wf_submit($foreign,$syn['id'],wf_test_payload($foreign,$syn),$builder);
 $review=array_values(array_filter(wf_tasks($foreign),fn($x)=>$x['kind']==='review'))[0];
 vdeny(fn()=>wr_claim($foreign,$review['id'],1,$builder),422,'Synthesis author cannot pick up independent review');
 $reviewer=vactor();wr_claim($foreign,$review['id'],1,$reviewer);wr_release($foreign,$review['id'],$reviewer);
 query("UPDATE workflow_submissions SET visibility='private' WHERE id=?",[$syn['accepted_id']??wf_task($foreign,$syn['id'])['accepted_id']]);
 vdeny(fn()=>wr_claim($foreign,$review['id'],1,$reviewer),409,'Missing public prerequisite prevents pickup even when task says Ready');
 // Browser authorization delegates use of the original identity, never creates a second owner.
 [$arid,$at]=vfixture();$ar=research($arid);$_SESSION['workflow_actor']=bin2hex(random_bytes(32));$volunteer=wf_actor();
 $post=['authorize'=>1,'terms'=>1,'policy_version'=>terms_version(),'human_level'=>'none','human_details'=>''];
 $issued=ar_issue($ar,$at['id'],$post);preg_match('~/return/([a-f0-9]{32})#([a-f0-9]{64})~',$issued['handoff'],$match);
 $grantId=$match[1];$secret=$match[2];$lease=wr_live($arid,$at['id']);
 vcheck($lease['actor_hash']===$volunteer,'Authorized pickup reserves for the volunteer');
 $stored=one('SELECT scope,consent FROM browser_returns WHERE id=?',[$grantId]);
 $_SESSION['workflow_actor']=bin2hex(random_bytes(32));$aiActor=wf_actor();
 vdeny(fn()=>ar_read($ar,$grantId),403,'AI browser requires the private grant capability');
 $_SESSION['browser_returns'][$grantId]=hash('sha256',$secret);$grant=ar_read($ar,$grantId);
 vdeny(fn()=>wr_renew($arid,$at['id'],1,$aiActor),409,'Separate AI browser identity is not the owner');
 $clock+=20;ar_validate($ar,$grant);wf_issue_handoff($arid,$at['id']);
 vcheck(wr_live($arid,$at['id'])['expires_at']===$lease['expires_at'],'Grant checks and handoff copies do not renew');
 wr_renew($arid,$at['id'],1,$grant['actor_hash']);wr_release($arid,$at['id'],$grant['actor_hash']);
 // Existing/historical handoff and consent bytes remain unchanged while explicitly reacquiring.
 wr_claim($arid,$at['id'],1,$grant['actor_hash']);
 vcheck(one('SELECT scope,consent FROM browser_returns WHERE id=?',[$grantId])===$stored,'Reservation operations never rewrite historical scope or consent');
 $packet=wf_test_payload($arid,$at,'progress');
 foreach(['submission_key','context_id'] as $key)$packet[$key]=$grant['scope'][$key];
 $packet['handoff_code']=wf_handoff_code($at,wf_read_snapshot($arid,$packet['context_id']),$packet['submission_key']);
 $record=ar_record($ar,$grant,$packet,false);
 vcheck(one('SELECT actor_hash FROM workflow_submissions WHERE id=?',[$record])['actor_hash']===$volunteer,'AI recording retains volunteer actor and independent-review identity');
 vcheck(wr_live($arid,$at['id'])===null,'AI recording consumes original volunteer lease');
 $clock+=7200;vcheck(ar_record($ar,$grant,$packet,false)===$record,'Consumed grant recovers receipt after reservation expiry');
 // A source availability loss must not resurrect a descendant lease after restoration.
 [$source,$sourceTask]=vfixture();$draft=rf_prepare($source);$child=rf_create($source,rf_test_child_data($draft));$ids[]=$child['id'];
 moderate('request',$child['id'],'Approved','Disposable source visibility fence fixture.');$childTask=wf_tasks($child['id'])[0];$childActor=vactor();
 wr_claim($child['id'],$childTask['id'],1,$childActor);
 change_status($source,'Paused','Pause source task work without withdrawing public background.','administrator');
 vcheck(wr_renew($child['id'],$childTask['id'],1,$childActor)['actor_hash']===$childActor,'Source task pause does not cancel independent public child work');
 change_status($source,'Needs Research Plan','Resume source task work.','administrator');
 vcheck(wr_live($child['id'],$childTask['id'])!==null,'Source resume preserves a valid child reservation');
 change_status($source,'Withdrawn','Synthetic inherited source withdrawal.','administrator');
 vdeny(fn()=>wr_renew($child['id'],$childTask['id'],1,$childActor),404,'Source withdrawal prevents descendant renewal');
 change_status($source,'Needs Research Plan','Restore source availability without reviving its leases.','administrator');
 vdeny(fn()=>wr_renew($child['id'],$childTask['id'],1,$childActor),409,'Restored source cannot revive old descendant lease');
 vcheck(wr_availability($child['id'],$childTask['id'])['status']==='available','Stale descendant lease does not block public availability');
 $reacquired=wr_claim($child['id'],$childTask['id'],1,vactor());vcheck(!empty($reacquired['eligibility_fence']),'Fresh descendant claim pins current source history');
 wr_claim($source,$sourceTask['id'],1,$childActor);vcheck(true,'Invalid descendant lease does not strand holder from a different task');wr_release($source,$sourceTask['id'],$childActor);
 [$hidden,$hiddenTask]=vfixture();$hiddenActor=vactor();wr_claim($hidden,$hiddenTask['id'],1,$hiddenActor);
 moderate('request',$hidden,'Rejected','Synthetic publication withdrawal.');
 vdeny(fn()=>wr_claim($hidden,$hiddenTask['id'],1,vactor()),404,'Unpublished research cannot be reserved');
 moderate('request',$hidden,'Approved','Restore publication for local fixture.');
 vcheck(wr_live($hidden,$hiddenTask['id'])===null,'Restored moderation cannot revive cancelled ownership');
 // Shared rate counters survive caller rollback and are bounded independently by actor and IP.
 [$lrid,$lt]=vfixture();$limited=vactor();putenv('RATE_LIMIT_NAMESPACE=reservation-limit-'.uid());
 for($i=0;$i<60;$i++){try{tx(function()use($lrid,$lt,$limited){wr_claim($lrid,$lt['id'],1,$limited);throw new RuntimeException('Synthetic rollback');});}catch(RuntimeException $error){if($error instanceof HttpError)throw $error;}}
 vdeny(fn()=>wr_claim($lrid,$lt['id'],1,$limited),429,'Claim attempts remain capped despite transaction rollback');
 putenv('RATE_LIMIT_NAMESPACE=reservation-renew-limit-'.uid());wr_claim($lrid,$lt['id'],1,$limited);
 for($i=0;$i<60;$i++)wr_renew($lrid,$lt['id'],1,$limited);
 vdeny(fn()=>wr_renew($lrid,$lt['id'],1,$limited),429,'Renewals have a bounded actor rate limit');

}catch(Throwable $e){throw $e;}
finally{unset($GLOBALS['wr_test_clock']);foreach($ids as $id)purge_request($id,$id);}
echo count($checks)." reservation checks passed.\n";
file_put_contents(storage('qa/reservations-unit.json'),json_encode($checks,JSON_PRETTY_PRINT));
