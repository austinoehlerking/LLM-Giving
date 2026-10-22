<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';
session_start_safe();$_SESSION['admin']=1;$ids=[];$checks=0;
function ai_ok(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function ai_deny(callable $fn,string $label,int $status=422):void{try{$fn();}catch(HttpError $e){ai_ok($e->status===$status,$label);return;}throw new RuntimeException($label);}
function ai_input(array $models=[],array $levels=[],array $higher=[]):array{return ['ai_selection_version'=>2,'required_models'=>$models,'reasoning_levels'=>$levels,'reasoning_or_higher'=>$higher];}
try{
 ai_deny(fn()=>rq_parse(['required_models'=>['claude-fable-5-1']]),'Missing selection version cannot silently discard a model restriction');
 $any=rq_parse(ai_input());ai_ok(!$any['models']&&!$any['efforts']&&!$any['modes'],'Empty selection means Any');
 ai_ok(rq_matches($any,'unlisted-provider-model','unknown'),'Any adds no requester restriction');
 ai_ok(!rq_matches($any,'claude-fable-5-1','ultra'),'Known unsupported settings cannot silently fall back');
 $union=rq_parse(ai_input(['gpt-6-astra','claude-fable-5-1'],['low','max']));
 foreach(['gpt-6-astra','claude-fable-5-1'] as $model){
  ai_ok(rq_matches($union,$model,'low'),'Exact alternative low '.$model);
  ai_ok(rq_matches($union,$model,'max'),'Exact alternative max '.$model);
  foreach(['medium','high','xhigh','ultra'] as $effort)ai_ok(!rq_matches($union,$model,$effort),'Exact alternatives exclude '.$model.'/'.$effort);
 }
 ai_ok(!rq_matches($union,'gpt-6.1-sol','max'),'Unselected model excluded');
 $floor=rq_parse(ai_input(['claude-fable-5-1'],['high'],['high']));
 foreach(['high','xhigh','max'] as $effort)ai_ok(rq_matches($floor,'claude-fable-5-1',$effort),'Supported higher effort '.$effort);
 foreach(['low','medium','ultra','ultracode','unknown'] as $effort)ai_ok(!rq_matches($floor,'claude-fable-5-1',$effort),'No unsupported/below-floor substitution '.$effort);
 $floor['registry']['claude-fable-5-1']['efforts']=['low','medium','max'];
 ai_ok(!rq_matches($floor,'claude-fable-5-1','max'),'Unsupported floor never becomes another model level');
 $mode=rq_parse(ai_input(['claude-fable-5-1'],['high','ultracode'],['high']));
 ai_ok(rq_matches($mode,'claude-fable-5-1','max','ultracode','Claude Code'),'Mode AND minimum accepts max');
 ai_ok(!rq_matches($mode,'claude-fable-5-1','low','ultracode','Claude Code'),'Mode cannot bypass effort minimum');
 ai_ok(!rq_matches($mode,'claude-fable-5-1','max'),'Mode is an explicit requirement');
 ai_ok(!rq_matches($mode,'claude-fable-5-1','max','ultracode','Codex'),'Mode requires Claude Code');
 $modeOnly=rq_parse(ai_input([],['ultracode']));
 ai_ok(rq_matches($modeOnly,'claude-fable-5-1','low','ultracode','Claude Code'),'Mode alone does not invent an effort minimum');
 foreach([
  ai_input(['any','gpt-6-astra']),ai_input([],['any','max']),ai_input(['claude-fable-5-1'],['ultra']),
  ai_input([],['high']),ai_input(['gpt-6-astra'],['ultracode']),ai_input([],['max'],['ultra']),
  ai_input([],['max'],['high']),ai_input(['invented-model']),ai_input([],['max','ultracode'],['ultracode'])
 ] as $i=>$input)ai_deny(fn()=>rq_parse($input),'Invalid/impossible configuration '.$i);
 ai_deny(fn()=>wf_save_policy(json_encode([['model'=>'claude-fable-5-1','efforts'=>['ultra'],'ultra_tool'=>'Claude Code']]),'QA'),'Policy rejects Fable Ultra');
 ai_deny(fn()=>wf_save_policy(json_encode([['model'=>'claude-fable-5-1','efforts'=>['high']]]),'QA'),'Policy cannot lower planning floor');
 ai_deny(fn()=>wf_save_policy(json_encode([['model'=>'gpt-6-astra','efforts'=>[['max']]]]),'QA'),'Policy rejects malformed effort type');
 ai_deny(fn()=>wf_save_policy(json_encode([['model'=>'gpt-6-astra','efforts'=>['ultra'],'ultra_tool'=>['Codex']]]),'QA'),'Policy rejects malformed tool type');
 ai_ok(in_array('claude-fable-5-1',array_column(wf_policy(),'model'),true),'Default policy includes exact Fable ID');
 $created=create_research(['title'=>'[QA] AI selection contract','question'=>'Synthetic testing of model alternatives and separate workflow mode enforcement.','category_id'=>1,'terms'=>1]+ai_input(['gpt-6-astra','claude-fable-5-1'],['max']));
 $rid=$created['id'];$ids[]=$rid;moderate('request',$rid,'Approved','Synthetic local testing.');redeem($rid,parse_url($created['link'],PHP_URL_FRAGMENT));
 $task=wf_tasks($rid)[0];$old=wf_test_payload($rid,$task);$snapshot=one('SELECT * FROM workflow_snapshots WHERE id=?',[$old['context_id']]);
 rq_update($rid,ai_input(['claude-fable-5-1'],['high','ultracode'],['high']));
 ai_ok(one('SELECT package_json FROM workflow_snapshots WHERE id=?',[$old['context_id']])['package_json']===$snapshot['package_json'],'Historical snapshot bytes unchanged');
 ai_deny(fn()=>wf_submit($rid,$task['id'],$old,str_repeat('a',64)),'Changed choices invalidate old handoff',409);
 $packet=wf_test_payload($rid,$task);$packet['metadata']['model']='claude-fable-5-1';$packet['metadata']['provider']='Anthropic';$packet['metadata']['tool']='Claude Code';$packet['metadata']['workflow_mode']='ultracode';
 $packet['metadata']['reasoning']='xhigh';ai_deny(fn()=>wf_submit($rid,$task['id'],$packet,str_repeat('a',64)),'Ultracode xhigh cannot bypass platform Max planning');
 $packet['metadata']['reasoning']='ultracode';ai_deny(fn()=>wf_submit($rid,$task['id'],$packet,str_repeat('a',64)),'Ultracode is never a numeric effort');
 $packet['metadata']['reasoning']='max';$result=wf_submit($rid,$task['id'],$packet,str_repeat('a',64));
 $stored=json_decode(one('SELECT metadata FROM workflow_submissions WHERE id=?',[$result])['metadata'],true);
 ai_ok($stored['reasoning']==='max'&&$stored['workflow_mode']==='ultracode','Actual effort and mode persisted separately');
 ai_deny(fn()=>rq_update($rid,ai_input()),'New requirement format locks after first result',409);
 $contribution=['kind'=>'Original research','summary'=>'Synthetic mode persistence','body'=>'This is synthetic local QA content with no scientific claims.','terms'=>1,'metadata'=>['model'=>'claude-fable-5-1','reasoning'=>'high','workflow_mode'=>'ultracode','harness'=>'Claude Code']];
 $cid=create_contribution($rid,$contribution);
 ai_ok(one('SELECT workflow_mode,reasoning FROM agent_metadata WHERE contribution_id=?',[$cid])===['workflow_mode'=>'ultracode','reasoning'=>'high'],'Supplementary mode and actual effort persist independently');
 $spoofed=$contribution;$spoofed['metadata']['harness']='Codex';$spoofed['metadata']['tool']='Claude Code';ai_deny(fn()=>create_contribution($rid,$spoofed),'Ignored supplementary tool key cannot bypass persisted harness');
 $contribution['metadata']['reasoning']='low';ai_deny(fn()=>create_contribution($rid,$contribution),'Supplementary mode cannot bypass floor');
 $contribution['metadata']['reasoning']='max';unset($contribution['metadata']['workflow_mode']);ai_deny(fn()=>create_contribution($rid,$contribution),'Supplementary path requires selected mode');
 foreach([prompt_text($rid),wf_handoff_text($rid),wf_repair_prompt(research($rid),$task,'Synthetic validation')] as $prompt)ai_ok(str_contains($prompt,'workflow_mode')&&str_contains($prompt,'never substitutes')&&str_contains($prompt,'AND'),'Prompt resolves modes separately');
 $historical=create_research(['title'=>'[QA] Historical selection preservation','question'=>'Synthetic unchanged legacy requirements must retain their stored meaning.','category_id'=>1,'terms'=>1,'required_model'=>'gpt-6-astra','reasoning_rule'=>'minimum','required_reasoning'=>'high']);$hid=$historical['id'];$ids[]=$hid;redeem($hid,parse_url($historical['link'],PHP_URL_FRAGMENT));$before=research($hid,false)['ai_requirements'];
 rq_update($hid,ai_input(['gpt-6-astra'],['high'],['high']));ai_ok(research($hid,false)['ai_requirements']===$before,'Unchanged legacy form preserves original raw JSON');
 $historicalCustom=['model'=>'historical-exact-version','mode'=>'exact','effort'=>'max'];query('UPDATE research SET ai_requirements=? WHERE id=?',[json_encode($historicalCustom),$hid]);rq_update($hid,ai_input(['historical-exact-version'],['max']));ai_ok(rq_requirements(research($hid,false))===$historicalCustom,'Unchanged historical model outside current registry is preserved');
 $legacy=['model'=>'','mode'=>'minimum','effort'=>'xhigh'];
 ai_ok(rq_matches($legacy,'historical-unlisted-model','ultra'),'Historical minimum keeps its original meaning');
 ai_ok(!rq_matches(['model'=>'gpt-6-astra','mode'=>'exact','effort'=>'max'],'gpt-6-astra','ultra'),'Historical exact remains exact');
 echo $checks." AI selection integration/matching checks passed.\n";
}finally{foreach($ids as $id)purge_request($id,$id);}
