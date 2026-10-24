<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';
session_start_safe();$_SESSION['admin']=1;$ids=[];$checks=[];
function req_ok(bool $v,string $s):void{global $checks;if(!$v)throw new RuntimeException($s);$checks[]=$s;}
function req_deny(callable $f,int $status,string $s):void{try{$f();}catch(HttpError $e){req_ok($e->status===$status,$s);return;}throw new RuntimeException($s);}
try{
 $legacy=['model'=>'','mode'=>'none','effort'=>''];$reordered=['mode'=>'none','model'=>'','effort'=>''];
 req_ok(rq_same_requirements($legacy,$reordered),'Requirement object key order is not a semantic change');
 foreach(['model'=>'gpt-6-astra','mode'=>'exact','effort'=>'max'] as $key=>$value){$changed=$legacy;$changed[$key]=$value;req_ok(!rq_same_requirements($legacy,$changed),'Changed '.$key.' remains a real requirement change');}
 foreach([null,'{}',[],['none'],['model'=>'','mode'=>'none'],['model'=>'','mode'=>'none','effort'=>'','extra'=>true],['model'=>'','mode'=>'none','effort'=>false]] as $malformed)req_ok(!rq_same_requirements($malformed,$legacy),'Missing, extra, malformed or differently typed values never compare equal');
 $v2=['version'=>2,'models'=>['gpt-6-astra','claude-fable-5-1'],'efforts'=>[['level'=>'max','or_higher'=>false]],'modes'=>[],'registry'=>['gpt-6-astra'=>['efforts'=>['low','max','ultra'],'modes'=>[]]]];
 $v2Reordered=['registry'=>['gpt-6-astra'=>['modes'=>[],'efforts'=>['low','max','ultra']]],'modes'=>[],'efforts'=>[['or_higher'=>false,'level'=>'max']],'models'=>['gpt-6-astra','claude-fable-5-1'],'version'=>2];
 $before=json_encode($v2Reordered);req_ok(rq_same_requirements($v2,$v2Reordered),'Nested version2 registry and effort-object key order is ignored');req_ok(json_encode($v2Reordered)===$before,'Comparison does not mutate captured requirements');
 $changed=$v2;$changed['efforts'][0]['or_higher']=0;req_ok(!rq_same_requirements($v2,$changed),'Boolean effort policy is not coerced to an integer');
 $changed=$v2;$changed['registry']['gpt-6-astra']['efforts']=['max','low','ultra'];req_ok(!rq_same_requirements($v2,$changed),'Effort scale list ordering remains significant');
 $changed=$v2;$changed['version']='2';req_ok(!rq_same_requirements($v2,$changed),'Requirement version type remains significant');
 req_deny(fn()=>rq_parse(['required_model'=>'invented-model']),422,'Unknown model rejected');
 req_deny(fn()=>rq_parse(['reasoning_rule'=>'exact','required_reasoning'=>'medium']),422,'Incompatible exact reasoning rejected before topic creation');
 req_deny(fn()=>rq_parse(['required_model'=>'claude-opus-5-5','reasoning_rule'=>'exact','required_reasoning'=>'ultra']),422,'Incompatible model and effort rejected');
 $created=create_research(['title'=>'[QA] Requester AI requirements','question'=>'Synthetic requester model requirement testing only.','category_id'=>1,'terms'=>1,'required_model'=>'gpt-6-astra','reasoning_rule'=>'minimum','required_reasoning'=>'high']);$rid=$created['id'];$ids[]=$rid;moderate('request',$rid,'Approved','Synthetic requirement test.');$task=wf_tasks($rid)[0];
 $old=wf_test_payload($rid,$task);
 req_deny(fn()=>rq_update($rid,['reasoning_rule'=>'none']),403,'Only requester capability can update requirements');
 redeem($rid,parse_url($created['link'],PHP_URL_FRAGMENT));
 $reorderedText=json_encode(['effort'=>'high','mode'=>'minimum','model'=>'gpt-6-astra']);query('UPDATE research SET ai_requirements=? WHERE id=?',[$reorderedText,$rid]);
 $beforeNoop=[one('SELECT context_version FROM research WHERE id=?',[$rid])['context_version'],one('SELECT COUNT(*) n FROM audit_events WHERE research_id=?',[$rid])['n']];
 rq_update($rid,['required_model'=>'gpt-6-astra','reasoning_rule'=>'minimum','required_reasoning'=>'high']);
 req_ok($beforeNoop===[one('SELECT context_version FROM research WHERE id=?',[$rid])['context_version'],one('SELECT COUNT(*) n FROM audit_events WHERE research_id=?',[$rid])['n']]&&one('SELECT ai_requirements FROM research WHERE id=?',[$rid])['ai_requirements']===$reorderedText,'Reordered unchanged requirements do not rewrite history, context or audit');
 req_ok(wf_test_submit($rid,$task['id'],$old,str_repeat('a',64),true)==='','Existing snapshot accepts reordered semantically unchanged current requirements');
 rq_update($rid,['required_model'=>'gpt-6-astra','reasoning_rule'=>'minimum','required_reasoning'=>'max']);
 req_deny(fn()=>wf_test_submit($rid,$task['id'],$old,str_repeat('a',64)),409,'Changed requirements invalidate old handoffs');
 $d=wf_test_payload($rid,$task);$d['metadata']['model']='gpt-6.1-sol';req_deny(fn()=>wf_test_submit($rid,$task['id'],$d,str_repeat('a',64)),422,'Wrong exact model rejected');
 $d['metadata']['model']='gpt-6-astra';$d['metadata']['reasoning']='high';req_deny(fn()=>wf_test_submit($rid,$task['id'],$d,str_repeat('a',64)),422,'Below planning and requester minimum rejected');
 $d['metadata']['reasoning']='max';$id=wf_test_submit($rid,$task['id'],$d,str_repeat('a',64));req_ok((bool)$id,'Qualifying plan accepted');
 req_deny(fn()=>rq_update($rid,['reasoning_rule'=>'none']),409,'Requirements lock after first research submission');
 $research=array_values(array_filter(wf_tasks($rid),fn($t)=>$t['kind']==='research'&&$t['state']==='Ready'))[0];
 $d=wf_test_payload($rid,$research,'progress');$d['metadata']['reasoning']='high';req_deny(fn()=>wf_test_submit($rid,$research['id'],$d,str_repeat('b',64)),422,'Requester minimum enforced on non-planning tasks');
 $d['metadata']['reasoning']='ultra';req_ok((bool)wf_test_submit($rid,$research['id'],$d,str_repeat('b',64)),'Above-minimum research run accepted');
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
