<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';

$_SESSION['admin']=1;$ids=[];$checks=[];putenv('RATE_LIMIT_NAMESPACE=handoff-unit-'.uid());
function hc(bool $v,string $label):void{global $checks;if(!$v)throw new RuntimeException($label);$checks[]=$label;}
function hd(callable $f,int $code,string $label):void{try{$f();}catch(HttpError $e){hc($e->status===$code,$label);return;}throw new RuntimeException($label);}
try{
 foreach(['primary','foreign'] as $name){$c=create_research(['title'=>'[QA] Exact handoff '.$name,'question'=>'Synthetic archive test with Unicode: ocean → carbon. No scientific conclusion.','category_id'=>1,'terms'=>1]);$ids[]=$c['id'];moderate('request',$c['id'],'Approved','Archive test topic approval.');}
 [$r,$foreign]=$ids;$task=wf_tasks($r)[0];
 $first=wf_issue_handoff($r);$second=wf_issue_handoff($r);
 hc($first['nonce']!==$second['nonce']&&$first['id']!==$second['id'],'Separate issued copies have unique identities');
 hc(hash('sha256',$first['body'])===$first['sha256']&&wf_read_handoff($r,$first['id'])['body']===$first['body'],'Exact UTF-8 bytes and checksum preserved');
 hc(str_contains($first['body'],'AI RESEARCH HANDOFF')&&str_contains($first['body'],'UNTRUSTED FULL CONTEXT PACKAGE')&&str_contains($first['body'],'editing the INITIAL prompt'),'Full wrapper, instructions and context archived');
 try{query("UPDATE workflow_handoffs SET body='altered' WHERE id=?",[$first['id']]);throw new RuntimeException('Archive update allowed');}catch(PDOException $e){hc($e->getCode()==='45000','Database blocks archive updates');}
 hd(fn()=>wf_read_handoff($foreign,$first['id']),404,'Archive enforces research parent');
 foreach([
 ['influenced','none','Edited the initial prompt before the first AI message.'],
 ['none','influenced','Deleted the instructions before pasting the first handoff.'],
 ['influenced','none','Added sources to the initial prompt to favor a preferred conclusion.'],
 ['none','unsure','Unsure whether initial instructions were removed before pasting.']
 ] as [$ai,$human,$details]){
  $archive=wf_issue_handoff($r);
  $schema=json_decode(explode("\n\nUNTRUSTED TASK DATA",explode("REQUIRED OUTPUT JSON\n",$archive['body'])[1])[0],true);
  $d=wf_test_payload($r,$task);foreach(['submission_key','handoff_code','task_revision','context_id'] as $k)$d[$k]=$schema[$k];
  $d['human_interaction']=['level'=>$ai,'details'=>$ai==='none'?'':$details];$d['human_disclosure']=['level'=>$human,'details'=>$human==='none'?'':$details];
  $id=wf_submit($r,$task['id'],$d,str_repeat('a',64));$row=one('SELECT * FROM workflow_submissions WHERE id=?',[$id]);$meta=json_decode($row['metadata'],true);
  hc($row['admission']==='quarantined'&&count(wf_tasks($r))===1,'Disclosed initial alteration quarantines without advancement: '.$details);
  hc($meta['verification']['task_signature']==='valid'&&$meta['verification']['conversation_integrity']==='self-reported'&&$meta['verification']['issued_handoff']['id']===$archive['id'],'Signature and issued archive do not imply conversation integrity');
  $background=wf_issue_handoff($r);hc($background['task_id']===null&&str_contains($background['body'],'NO READY TASK'),'Held background handoff archived');
  wf_review_influence($r,$id,'Reject synthetic altered-prompt fixture, preserving issued handoff.','reject');
  hc(wf_read_handoff($r,$archive['id'])['body']===$archive['body'],'Rejection does not rewrite issued handoff');
 }
 $direct=wf_issue_handoff($r,$task['id']);hc($direct['task_id']===$task['id']&&str_contains($direct['body'],'REQUIRED OUTPUT JSON'),'Direct task prompt archived');
 // Age only disposable fixture rows by delete/reinsert; immutable production updates stay prohibited.
 $oldUnused=$first;$oldUnused['created_at']=gmdate('Y-m-d H:i:s',time()-31*86400);
 query('DELETE FROM workflow_handoffs WHERE id=?',[$first['id']]);insert('workflow_handoffs',$oldUnused);
 $oldLinked=$archive;$oldLinked['created_at']=$oldUnused['created_at'];
 query('DELETE FROM workflow_handoffs WHERE id=?',[$archive['id']]);insert('workflow_handoffs',$oldLinked);
 wf_prune_handoffs();
 hc(!one('SELECT id FROM workflow_handoffs WHERE id=?',[$first['id']]),'Expired unused copy removed');
 hc(wf_read_handoff($r,$archive['id'])['body']===$archive['body'],'Expired rejected-packet archive preserved exactly');
 hc(wf_read_handoff($r,$second['id'])['body']===$second['body'],'Recent unused copy retained');
 $summary=wf_findings_overview(['reason'=>'A measured local pilot is warranted; benefits remain uncertain.','body'=>"REVISION 2 — RESPONSE TO INDEPENDENT REVIEW\n\nThe scientific conclusion is unchanged: A measured local pilot is warranted; benefits remain uncertain.\n\nMeasured gases. Methane and nitrous oxide must both be measured.\n\nYield. Yield preservation is not established."]);
 hc(str_starts_with($summary['summary'],'A measured local pilot')&&!str_contains(json_encode($summary),'REVISION 2'),'Concise conclusion precedes scientific excerpts, skipping revision heading');
 $first=$second;
 $old=wf_read_handoff($r,$first['id']);query('UPDATE workflow_snapshots SET public_allowed=0 WHERE id=?',[$old['snapshot_id']]);
 hd(fn()=>wf_read_handoff($r,$first['id']),404,'Revoked context makes exact archive unavailable');
 $current=wf_issue_handoff($r);query("UPDATE workflow_tasks SET visibility='private' WHERE id=?",[$task['id']]);
 hd(fn()=>wf_read_handoff($r,$current['id']),404,'Private task hides archive');
 query("UPDATE workflow_tasks SET visibility='public' WHERE id=?",[$task['id']]);moderate('request',$r,'Removed','Parent visibility test.');
 hd(fn()=>wf_read_handoff($r,$current['id']),404,'Removed parent hides archive');
 $beforeLarge=(int)one('SELECT COUNT(*) n FROM workflow_handoffs')['n'];
 query('UPDATE research SET context=? WHERE id=?',[str_repeat('x',4194305),$foreign]);touch_context($foreign,false);
 hd(fn()=>wf_issue_handoff($foreign),413,'Oversized handoff rejected without truncation');
 hc((int)one('SELECT COUNT(*) n FROM workflow_handoffs')['n']===$beforeLarge,'Oversized context stores no archive');
 query("UPDATE research SET context='' WHERE id=?",[$foreign]);touch_context($foreign,false);
 // Shared byte quota is transactional: failed issuance consumes no archive bytes.
 $budget=hash('sha256',env('RATE_LIMIT_NAMESPACE').'|handoff-bytes|'.gmdate('Y-m-d'));
 query('INSERT INTO rate_limits(bucket,hits,expires) VALUES (?,104857600,?) ON DUPLICATE KEY UPDATE hits=104857600',[$budget,time()+86400]);
 hd(fn()=>wf_issue_handoff($foreign),503,'Daily byte budget rejects additional archive text');
 hc((int)one('SELECT hits FROM rate_limits WHERE bucket=?',[$budget])['hits']===104857600,'Failed byte-budget issuance rolls back accounting');
 query('DELETE FROM rate_limits WHERE bucket=?',[$budget]);
 $global=hash('sha256',env('RATE_LIMIT_NAMESPACE').'|handoff-copy-global|account|'.intdiv(time(),3600));
 query('INSERT INTO rate_limits(bucket,hits,expires) VALUES (?,1000,?) ON DUPLICATE KEY UPDATE hits=1000',[$global,time()+3600]);
 hd(fn()=>wf_issue_handoff($foreign),429,'Shared issuance quota applies across client IPs');
 query('DELETE FROM rate_limits WHERE bucket=?',[$global]);
 $_SERVER['REMOTE_ADDR']='test-limit-'.uid();
 for($i=0;$i<60;$i++)throttle('handoff-copy',60);
 $before=(int)one('SELECT COUNT(*) n FROM workflow_handoffs')['n'];
 hd(fn()=>wf_issue_handoff($foreign),429,'IP issuance quota rejects before archive generation');
 hc((int)one('SELECT COUNT(*) n FROM workflow_handoffs')['n']===$before,'Rejected issuance stores no archive');
 echo count($checks)." exact handoff checks passed.\n";file_put_contents(storage('qa/handoff-unit.json'),json_encode($checks,JSON_PRETTY_PRINT));
}finally{foreach($ids as $id)purge_request($id,$id);}
