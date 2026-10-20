<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';
session_start_safe();$_SESSION['admin']=1;$ids=[];$checks=[];
function req_ok(bool $v,string $s):void{global $checks;if(!$v)throw new RuntimeException($s);$checks[]=$s;}
function req_deny(callable $f,int $status,string $s):void{try{$f();}catch(HttpError $e){req_ok($e->status===$status,$s);return;}throw new RuntimeException($s);}
try{
 req_deny(fn()=>rq_parse(['required_model'=>'invented-model']),422,'Unknown model rejected');
 req_deny(fn()=>rq_parse(['reasoning_rule'=>'exact','required_reasoning'=>'medium']),422,'Incompatible exact reasoning rejected before topic creation');
 req_deny(fn()=>rq_parse(['required_model'=>'claude-opus-5-5','reasoning_rule'=>'exact','required_reasoning'=>'ultra']),422,'Incompatible model and effort rejected');
 $created=create_research(['title'=>'[QA] Requester AI requirements','question'=>'Synthetic requester model requirement testing only.','category_id'=>1,'terms'=>1,'required_model'=>'gpt-6-astra','reasoning_rule'=>'minimum','required_reasoning'=>'high']);$rid=$created['id'];$ids[]=$rid;moderate('request',$rid,'Approved','Synthetic requirement test.');$task=wf_tasks($rid)[0];
 $old=wf_test_payload($rid,$task);
 req_deny(fn()=>rq_update($rid,['reasoning_rule'=>'none']),403,'Only requester capability can update requirements');
 redeem($rid,parse_url($created['link'],PHP_URL_FRAGMENT));
 rq_update($rid,['required_model'=>'gpt-6-astra','reasoning_rule'=>'minimum','required_reasoning'=>'max']);
 req_deny(fn()=>wf_submit($rid,$task['id'],$old,str_repeat('a',64)),409,'Changed requirements invalidate old handoffs');
 $d=wf_test_payload($rid,$task);$d['metadata']['model']='gpt-6.1-sol';req_deny(fn()=>wf_submit($rid,$task['id'],$d,str_repeat('a',64)),422,'Wrong exact model rejected');
 $d['metadata']['model']='gpt-6-astra';$d['metadata']['reasoning']='high';req_deny(fn()=>wf_submit($rid,$task['id'],$d,str_repeat('a',64)),422,'Below planning and requester minimum rejected');
 $d['metadata']['reasoning']='max';$id=wf_submit($rid,$task['id'],$d,str_repeat('a',64));req_ok((bool)$id,'Qualifying plan accepted');
 req_deny(fn()=>rq_update($rid,['reasoning_rule'=>'none']),409,'Requirements lock after first research submission');
 $research=array_values(array_filter(wf_tasks($rid),fn($t)=>$t['kind']==='research'&&$t['state']==='Ready'))[0];
 $d=wf_test_payload($rid,$research,'progress');$d['metadata']['reasoning']='high';req_deny(fn()=>wf_submit($rid,$research['id'],$d,str_repeat('b',64)),422,'Requester minimum enforced on non-planning tasks');
 $d['metadata']['reasoning']='ultra';req_ok((bool)wf_submit($rid,$research['id'],$d,str_repeat('b',64)),'Above-minimum research run accepted');
 req_deny(fn()=>create_contribution($rid,['kind'=>'Original research','summary'=>'Synthetic requirement test','body'=>'Synthetic test of supplementary requirements.','terms'=>1,'metadata'=>['model'=>'gpt-6.1-sol','reasoning'=>'max']]),422,'Supplementary path cannot bypass required model');
 req_ok(!rq_matches(['model'=>'gpt-6-astra','mode'=>'exact','effort'=>'max'],'gpt-6-astra','ultra'),'Exact mode rejects higher level');
 req_ok(rq_matches(['model'=>'gpt-6-astra','mode'=>'exact','effort'=>'max'],'gpt-6-astra','max'),'Exact mode accepts matching level');
 req_ok(!rq_matches(['model'=>'','mode'=>'minimum','effort'=>'high'],'gpt-6-astra','unknown'),'Unknown reasoning cannot satisfy minimum');
 $ctx=context_data($rid);req_ok($ctx['requester_ai_requirements']['model']==='gpt-6-astra','Context records exact requester requirement');
 req_ok(str_contains(wf_prompt($rid,$research['id']),'Reasoning: at least max'),'Task prompt contains precise minimum');
 req_ok(str_contains(wf_handoff_text($rid),'Reasoning: at least max'),'Copy handoff contains precise minimum');
 $pre=rq_preflight(['model'=>'gpt-6-astra','mode'=>'exact','effort'=>'max'],wf_policy(),'planning');
 req_ok(str_contains($pre,'gpt-6-astra / max')&&!str_contains($pre,'gpt-6.1-sol /')&&!str_contains($pre,'gpt-6-astra / ultra'),'Preflight lists only qualifying exact planning configurations');
 foreach(['BLOCKED — AI CONFIGURATION','unknown, unverifiable','STOP IMMEDIATELY','OVERRIDES every JSON-only','Required:','Actual:','Problem:','Fix:','Re-run:','NEW conversation/run','ENTIRE fresh handoff unchanged','Do not upload this blocker message','Never relabel'] as $phrase)req_ok(str_contains($pre,$phrase),'Preflight contract: '.$phrase);
 foreach([wf_prompt($rid,$research['id']),wf_handoff_text($rid),prompt_text($rid),wf_repair_prompt(research($rid),$research,'Synthetic mismatch')] as $i=>$prompt)req_ok(str_contains($prompt,'MANDATORY AI CONFIGURATION PREFLIGHT')&&str_contains($prompt,'Reasoning: at least max'),'Preflight in prompt surface '.$i);
 echo count($checks)." requester AI requirement checks passed.\n";
}finally{foreach($ids as $id)purge_request($id,$id);}
