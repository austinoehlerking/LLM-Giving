<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';
session_start_safe();$_SESSION['admin']=1;$checks=[];$ids=[];
function ec(bool $ok,string $label): void {global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
function ed(callable $fn,string $label,int $status=422): void {try{$fn();}catch(HttpError $error){ec($error->status===$status,$label.' ('.$error->getMessage().')');return;}throw new RuntimeException('Expected evidence rejection: '.$label);}
function et(string $rid,string $kind): array {foreach(wf_tasks($rid) as $task)if($task['kind']===$kind)return $task;throw new RuntimeException('Missing task');}
function ep(string $rid,array $task,array $packet,string $actor='a'): void {wf_submit($rid,$task['id'],$packet,str_repeat($actor,64),true);}
try {
 $category=(int)one('SELECT id FROM categories LIMIT 1')['id'];
 foreach(['main','foreign'] as $name){$r=create_research(['title'=>'[QA] Evidence contract '.$name.' '.uid(),'question'=>'Test source-linked conclusions and correction propagation with explicitly synthetic local records.','category_id'=>$category,'terms'=>1]);$ids[]=$r['id'];moderate('request',$r['id'],'Approved','Approve a disposable evidence contract fixture.');}
 [$rid,$foreign]=$ids;$plan=et($rid,'planning');$packet=wf_test_payload($rid,$plan);
 $bad=$packet;unset($bad['evidence']);ed(fn()=>ep($rid,$plan,$bad),'Evidence envelope is mandatory');
 $bad=$packet;$bad['evidence']['version']=2;ed(fn()=>ep($rid,$plan,$bad),'Unknown evidence version rejected');
 $bad=$packet;unset($bad['tasks'][0]['evidence_needs']);ed(fn()=>ep($rid,$plan,$bad),'Plan requires task evidence needs');
 $bad=$packet;unset($bad['tasks'][0]['stopping_rule']);ed(fn()=>ep($rid,$plan,$bad),'Plan requires bounded task stopping rules');
 $bad=$packet;$bad['evidence']['search_log']=[];ed(fn()=>ep($rid,$plan,$bad),'Terminal plan needs an actual search or access record');
 $packet['evidence']['sources']=[];$packet['evidence']['claims']=[];ep($rid,$plan,$packet);ec(count(wf_tasks($rid))===1,'Plan without scientific conclusions can validate without invented sources or mutation');
 $planId=wf_submit($rid,$plan['id'],$packet,str_repeat('a',64));
 $research=array_values(array_filter(wf_tasks($rid),fn($t)=>$t['kind']==='research'));[$a,$b]=$research;
 ec(str_contains($a['deliverable'],'Evidence needs:')&&str_contains($a['deliverable'],'Stopping rule:'),'Evidence needs and stopping rules reach the actual research task');
 foreach(['planning'=>$plan,'research'=>$a] as $kind=>$task)ec(str_contains(wf_prompt($rid,$task['id']),strtoupper($kind).':')&&str_contains(wf_prompt($rid,$task['id']),'TRUSTED RESEARCH EVIDENCE STANDARD'),'Stage-specific evidence instructions: '.$kind);
 $packet=wf_test_payload($rid,$a);$bad=$packet;$bad['evidence']['claims']=[];ed(fn()=>ep($rid,$a,$bad),'Terminal research needs central claims');
 $bad=$packet;$bad['evidence']['claims'][0]['id']='legacy';ed(fn()=>ep($rid,$a,$bad),'Historical audit ID cannot be impersonated by a new claim');
 $bad=$packet;$bad['evidence']['claims'][]=$bad['evidence']['claims'][0];ed(fn()=>ep($rid,$a,$bad),'Duplicate claim IDs rejected');
 $bad=$packet;$bad['evidence']['claims'][0]['statement']='A new claim that does not appear in the body.';ed(fn()=>ep($rid,$a,$bad),'Claim must trace to exact body text');
 $bad=$packet;$bad['evidence']['sources'][]=$bad['evidence']['sources'][0];ed(fn()=>ep($rid,$a,$bad),'Duplicate source IDs rejected');
 $bad=$packet;$bad['evidence']['sources'][0]['url']='javascript:alert(1)';ed(fn()=>ep($rid,$a,$bad),'Executable evidence URLs rejected');
 $bad=$packet;$bad['evidence']['claims'][0]['links'][0]['source_id']='missing';ed(fn()=>ep($rid,$a,$bad),'Unknown claim source rejected');
 $bad=$packet;$bad['evidence']['sources'][0]['access']='unavailable';ed(fn()=>ep($rid,$a,$bad),'Unopened sources cannot supply evidence quotations');
 $bad=$packet;$bad['evidence']['claims'][0]['links']=[];ed(fn()=>ep($rid,$a,$bad),'Supported claim needs an inspected supporting source');
 $bad=$packet;$bad['evidence']['sources'][0]['access']='abstract_only';ed(fn()=>ep($rid,$a,$bad),'Abstract-only access cannot claim full support');
 $bad['evidence']['claims'][0]['support']='limited';ep($rid,$a,$bad);ec(true,'Abstract-only limited findings remain useful');
 $bad['evidence']['claims'][0]['confidence']='high';ed(fn()=>ep($rid,$a,$bad),'Confidence cannot exceed limited support');
 $bad=$packet;$bad['evidence']['claims'][0]['kind']='inference';ed(fn()=>ep($rid,$a,$bad),'Interpretations require an explicit derivation');
 $bad=$packet;$bad['evidence']['claims'][0]['support']='unsupported';ed(fn()=>ep($rid,$a,$bad),'Unsupported conclusions cannot complete work');
 $null=$packet;$null['evidence']['sources']=[];$null['evidence']['claims'][0]['links']=[];$null['evidence']['claims'][0]['support']='unresolved';$null['evidence']['claims'][0]['confidence']='low';$null['outcome']='inconclusive';
 $aId=wf_submit($rid,$a['id'],$null,str_repeat('a',64));ec(wf_task($rid,$b['id'])['state']==='Ready','Documented unresolved evidence completes a deliverable without inventing support');
 $b=wf_task($rid,$b['id']);$bPacket=wf_test_payload($rid,$b);$contrarySource=$bPacket['evidence']['sources'][0];$contrarySource['id']='s2';$contrarySource['title']='Synthetic contrary-source audit fixture';$bPacket['evidence']['sources'][]=$contrarySource;$contraryLink=$bPacket['evidence']['claims'][0]['links'][0];$contraryLink['source_id']='s2';$contraryLink['relation']='contradicts';$contraryLink['explanation']='This synthetic second-source relationship exercises contradictory-source audit coverage only; it establishes no scientific result.';$bPacket['evidence']['claims'][0]['links'][]=$contraryLink;$bId=wf_submit($rid,$b['id'],$bPacket,str_repeat('a',64));
 $syn=et($rid,'synthesis');$packet=wf_test_payload($rid,$syn);
 $bad=$packet;$bad['evidence']['claims'][0]['basis_result_ids']=[];ed(fn()=>ep($rid,$syn,$bad),'Synthesis claims require current accepted upstream evidence');
 $bad=$packet;$bad['evidence']['claims'][0]['basis_result_ids']=[uid()];ed(fn()=>ep($rid,$syn,$bad),'Foreign or stale basis references rejected');
 ec(str_contains(wf_prompt($rid,$syn['id']),'SYNTHESIS:'),'Synthesis receives evidence reconciliation instructions');
 wf_submit($rid,$syn['id'],$packet,str_repeat('a',64));$review=et($rid,'review');
 $packet=wf_test_payload($rid,$review,'verified');$index=array_search($bId,array_column($packet['evidence']['review_checks'],'result_id'),true);
 ec(str_contains(wf_prompt($rid,$review['id']),'REVIEW:')&&count($packet['evidence']['review_checks'])===3,'Review template enumerates all current central claims');
 $bad=$packet;$bad['evidence']['review_checks']=[];ed(fn()=>ep($rid,$review,$bad,'b'),'Verified review must audit all current claims');
 $bad=$packet;$bad['evidence']['review_checks'][$index]['source_checks']=[];ed(fn()=>ep($rid,$review,$bad,'b'),'Verified review must inspect linked sources');
 $bad=$packet;$bad['evidence']['review_checks'][$index]['source_checks'][0]['access']='abstract_only';ed(fn()=>ep($rid,$review,$bad,'b'),'Full-text contradictory sources cannot replace inspection of supporting evidence');
 $bad=$packet;$bad['evidence']['review_checks'][$index]['verdict']='unverifiable';ed(fn()=>ep($rid,$review,$bad,'b'),'Unverifiable conclusion must return for correction');
 $bad=$packet;$bad['evidence']['review_checks'][$index]['source_checks']=[$bad['evidence']['review_checks'][$index]['source_checks'][0]];ed(fn()=>ep($rid,$review,$bad,'b'),'Contradictory source must be independently audited');
 $bad=$packet;$bad['outcome']='needs_correction';$bad['corrections']=[['task_id'=>$b['id'],'result_id'=>$bId,'criterion_id'=>'evidence-standard','claim_id'=>'unknown','reason'=>'Synthetic correction points to an unknown claim.','required_change'=>'Reinspect the source and revise this nonexistent claim.']];ed(fn()=>ep($rid,$review,$bad,'b'),'Manual evidence correction cannot invent a claim target');
 $bad=$packet;$bad['evidence']['review_checks'][$index]['claim_id']='unknown';ed(fn()=>ep($rid,$review,$bad,'b'),'Unknown review claim rejected');
 $bad=$packet;$bad['evidence']['review_checks'][$index]['result_id']=uid();ed(fn()=>ep($rid,$review,$bad,'b'),'Foreign or stale review result rejected');
 $bad=$packet;$bad['evidence']['review_checks'][$index]['verdict']='unsupported';ed(fn()=>ep($rid,$review,$bad,'b'),'Unsupported conclusion cannot receive verified outcome');
 $bad['outcome']='needs_correction';$bad['corrections']=[];$reviewId=wf_submit($rid,$review['id'],$bad,str_repeat('b',64));
 $stored=json_decode(one('SELECT metadata FROM workflow_submissions WHERE id=?',[$reviewId])['metadata'],true);
 ec($stored['corrections'][0]['claim_id']==='c1'&&$stored['corrections'][0]['criterion_id']==='evidence-standard','Source audit generates a claim-specific evidence correction automatically');
 ec(wf_task($rid,$a['id'])['accepted_id']===$aId&&wf_task($rid,$b['id'])['state']==='Ready'&&et($rid,'synthesis')['state']==='Waiting'&&et($rid,'review')['state']==='Waiting'&&wf_findings($rid)===null,'Unsupported upstream conclusion reopens exactly the affected task and dependent work');
 $b=wf_task($rid,$b['id']);wf_submit($rid,$b['id'],wf_test_payload($rid,$b),str_repeat('a',64));$syn=et($rid,'synthesis');wf_submit($rid,$syn['id'],wf_test_payload($rid,$syn),str_repeat('a',64));$review=et($rid,'review');
 $packet=wf_test_payload($rid,$review,'verified');$bad=$packet;$bad['evidence']['review_checks'][0]['result_id']=$bId;ed(fn()=>ep($rid,$review,$bad,'b'),'Replacement review cannot reuse superseded evidence IDs');
 wf_submit($rid,$review['id'],$packet,str_repeat('b',64));ec(research($rid)['status']==='Review Complete','Revised evidence can complete a new independent review cycle');
 $oldMeta=one('SELECT metadata FROM workflow_submissions WHERE id=?',[$aId])['metadata'];$historical=json_decode($oldMeta,true);unset($historical['evidence']);query('UPDATE workflow_submissions SET metadata=? WHERE id=?',[json_encode($historical),$aId]);touch_context($rid);
 wf_request_review($rid,'Test explicit historical source audit without inventing prior claim records.','Synthetic QA');$review=array_values(array_filter(wf_tasks($rid),fn($t)=>$t['kind']==='review'&&$t['state']==='Ready'))[0];$packet=wf_test_payload($rid,$review,'verified');
 $legacy=array_search('legacy',array_column($packet['evidence']['review_checks'],'claim_id'),true);ec($legacy!==false,'Historical results receive an explicit legacy review target');
 $packet['evidence']['review_checks'][$legacy]['verdict']='limited';ed(fn()=>ep($rid,$review,$packet,'b'),'Unverified historical support cannot silently receive verification');
 $packet['evidence']['review_checks'][$legacy]['verdict']='supported';$packet['evidence']['review_checks'][$legacy]['source_checks']=[['source_id'=>'s1','access'=>'full_text','locator'=>'Synthetic fixture body','excerpt'=>$packet['body'],'assessment'=>'Inspected the original supplied synthetic fixture; this does not verify any scientific proposition.']];ep($rid,$review,$packet,'b');ec(true,'Historical records can be explicitly audited using newly inspected source records');
 query('UPDATE workflow_submissions SET metadata=? WHERE id=?',[$oldMeta,$aId]);touch_context($rid);
 $ctx=wf_public_data($rid);ec(str_contains(json_encode($ctx),'confidence_reason')&&!str_contains(json_encode($ctx),'actor_hash'),'Full context preserves structured evidence without private actor identifiers');
 $repair=wf_repair_prompt(research($rid),$review,'Missing evidence record.');ec(str_contains($repair,'EVIDENCE OBJECT TEMPLATE')&&str_contains($repair,'do not change conclusions')&&str_contains($repair,'evidence_needs'),'Repair prompt teaches the evidence contract without steering conclusions');
 moderate('request',$rid,'Removed','Evidence parent visibility regression.');ed(fn()=>wf_public_data($rid),'Evidence inherits parent visibility',404);ed(fn()=>wf_evidence_results($review),'Evidence helpers enforce parent visibility',404);
 file_put_contents(storage('qa/evidence-integration.json'),json_encode($checks,JSON_PRETTY_PRINT));echo count($checks)." research evidence checks passed.\n";
} finally {foreach($ids as $id)if(one('SELECT id FROM research WHERE id=?',[$id]))purge_request($id,$id);}
