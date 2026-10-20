<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';

$_SESSION['admin']=1;$ids=[];$checks=[];
function qc(bool $v,string $s):void{global $checks;if(!$v)throw new RuntimeException($s);$checks[]=$s;}
function qdeny(callable $f,int $code,string $s):void{try{$f();}catch(HttpError $e){qc($e->status===$code,$s);return;}throw new RuntimeException($s);}
function qt(string $r,string $kind):array{foreach(wf_tasks($r) as $t)if($t['kind']===$kind)return $t;throw new RuntimeException('No task');}
function qp(string $r,array $t,string $out='completed'):array{$d=wf_test_payload($r,$t,$out);$d['human_disclosure']=['level'=>'unsure','details'=>'Synthetic uncertain substantive input for quarantine test.'];return $d;}
try{
 foreach(['completed','progress','blocked','needs_clarification'] as $out){
  $c=create_research(['title'=>'[QA] Quarantine '.$out,'question'=>'Synthetic quarantine admission and state-transition test only.','category_id'=>1,'terms'=>1]);$r=$c['id'];$ids[]=$r;moderate('request',$r,'Approved','Approve disposable quarantine QA topic.');
  $t=qt($r,'planning');$d=qp($r,$t,$out);$id=wf_submit($r,$t['id'],$d,str_repeat('a',64));
  qc(wf_task($r,$t['id'])['state']==='Awaiting Admin'&&!wf_task($r,$t['id'])['accepted_id']&&count(wf_tasks($r))===1&&research($r)['status']==='Needs Research Plan','No outcome applied while quarantined: '.$out);
  qc(wf_handoff_next(research($r))===null&&wf_public_data($r)['submissions']===[],'Handoff/evidence excludes held packet: '.$out);
  qc(wf_submit($r,$t['id'],$d,str_repeat('a',64))===$id,'Exact retry returns same held receipt');
  qdeny(fn()=>wf_submit($r,$t['id'],wf_test_payload($r,$t),str_repeat('b',64)),409,'Cannot bypass hold with another packet');
  unset($_SESSION['admin']);qdeny(fn()=>wf_review_influence($r,$id,'Unauthorized decision attempt.','approve'),403,'Admin authorization');$_SESSION['admin']=1;
  qdeny(fn()=>wf_review_influence($r,$id,'No decision selected by admin.',''),422,'Explicit decision required');
  wf_review_influence($r,$id,'Synthetic QA: reject potentially steered packet without applying it.','reject');
  qc(!wf_quarantine($r)&&wf_task($r,$t['id'])['state']==='Ready'&&wf_public_data($r)['submissions']===[],'Reject preserves evidence and reopens task');
  qc(wf_submit($r,$t['id'],$d,str_repeat('a',64))===$id&&!wf_quarantine($r),'Rejected retry cannot revive packet');
  qdeny(fn()=>wf_review_influence($r,$id,'Duplicate admin decision must fail.','approve'),409,'Decision cannot replay');
  $d=qp($r,$t,$out);$id=wf_submit($r,$t['id'],$d,str_repeat('a',64));wf_review_influence($r,$id,'Synthetic QA: inspected disclosure and approve the bounded fixture.','approve');
  qc(one('SELECT admission FROM workflow_submissions WHERE id=?',[$id])['admission']==='accepted'&&!wf_quarantine($r),'Approval admits packet');
  qc(research($r)['status']===match($out){'completed'=>'Open for Research','blocked'=>'Blocked','needs_clarification'=>'Needs Clarification',default=>'Needs Research Plan'},'Approval applies correct outcome: '.$out);
  qc(count(wf_public_data($r)['submissions'])===1,'Only approved packet enters context');
  qdeny(fn()=>wf_review_influence($r,$id,'Duplicate approval cannot duplicate tasks.','approve'),409,'Approval exactly once');
  if($out==='completed'){
   foreach(['research','research','synthesis'] as $kind){$task=null;foreach(wf_tasks($r) as $candidate)if($candidate['kind']===$kind&&$candidate['state']==='Ready'){$task=$candidate;break;}wf_submit($r,$task['id'],wf_test_payload($r,$task),str_repeat('a',64));}
   $rev=qt($r,'review');$syn=qt($r,'synthesis');$old=$syn['accepted_id'];
   $review=qp($r,$rev,'needs_correction');$reviewId=wf_submit($r,$rev['id'],$review,str_repeat('b',64));
   qc(qt($r,'synthesis')['accepted_id']===$old&&research($r)['status']==='Independent Verification','Quarantined correction cannot invalidate evidence');
   wf_review_influence($r,$reviewId,'Reject synthetic biased correction request.','reject');
   qc(qt($r,'synthesis')['accepted_id']===$old,'Rejected correction preserves accepted synthesis');
   $review=qp($r,qt($r,'review'),'needs_correction');$reviewId=wf_submit($r,$rev['id'],$review,str_repeat('b',64));
   wf_review_influence($r,$reviewId,'Approve synthetic correction after inspecting the targeted evidence.','approve');
   qc(!qt($r,'synthesis')['accepted_id']&&qt($r,'synthesis')['state']==='Ready','Approved correction invalidates target only on admission');
   $syn=qt($r,'synthesis');wf_submit($r,$syn['id'],wf_test_payload($r,$syn),str_repeat('a',64));$rev=qt($r,'review');
   $review=qp($r,qt($r,'review'),'verified');$reviewId=wf_submit($r,$rev['id'],$review,str_repeat('b',64));
   qc(research($r)['status']==='Independent Verification','Quarantined verification cannot complete cycle');
   wf_review_influence($r,$reviewId,'Approve synthetic independent verification after disclosure review.','approve');
   qc(research($r)['status']==='Review Complete','Approved verification completes cycle');
  }
 }
 $c=create_research(['title'=>'[QA] Stale quarantine','question'=>'Synthetic stale packet and frozen project verification only.','category_id'=>1,'terms'=>1]);$r=$c['id'];$ids[]=$r;moderate('request',$r,'Approved','Approve stale fixture.');$t=qt($r,'planning');$id=wf_submit($r,$t['id'],qp($r,$t),str_repeat('a',64));
 wf_restart($r,'Admin restart while packet is held.');
 qdeny(fn()=>wf_review_influence($r,$id,'Stale packet must not apply.','approve'),409,'Stale round cannot be approved');
 wf_review_influence($r,$id,'Reject stale packet; use fresh planning round.','reject');
 qc(qt($r,'planning')['state']==='Ready'&&count(wf_tasks($r))===1,'Reject stale packet does not mutate new task');
 echo count($checks)." quarantine checks passed.\n";file_put_contents(storage('qa/quarantine-unit.json'),json_encode($checks,JSON_PRETTY_PRINT));
}finally{foreach($ids as $id)purge_request($id,$id);}
