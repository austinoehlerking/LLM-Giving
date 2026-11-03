<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';session_start_safe();$_SESSION['admin']=1;
$checks=[];$rid=null;$sessions=[];
function wsc(bool $ok,string $label): void {global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
function wsd(callable $fn,string $label): void {try{$fn();}catch(HttpError $e){wsc($e->status===422,$label);return;}throw new RuntimeException('Expected rejection: '.$label);}
try {
 $base=['scope_kind'=>'all','session_preset'=>'standard'];
 foreach(VS_WORKERS as $choice=>$worker){
  $legacy=vs_configuration($base+['worker_choice'=>$choice]);wsc($legacy['capabilities']===$worker,$choice.' retains historical Max preset');
  foreach(['max','xhigh','high'] as $effort){
   $post=$base+['worker_choice'=>$choice,'worker_reasoning'=>$effort,'model'=>'forged-model','reasoning'=>'low','tool'=>'forged-tool'];
   $expected=array_replace($worker,['reasoning'=>$effort]);$configuration=vs_configuration($post);wsc($configuration['capabilities']===$expected,$choice.' / '.$effort.' overrides effort only and keeps exact model/tool');
   $draft=vs_form_draft($post);wsc($draft['worker_reasoning']===$effort&&$draft['reasoning']===$effort,$choice.' / '.$effort.' survives hidden draft');wsc(vs_configuration($draft)===$configuration,$choice.' / '.$effort.' survives authorization round trip');
  }
 }
 foreach(['', 'ultra', 'x-high', [], true] as $bad)wsd(fn()=>vs_configuration($base+['worker_choice'=>'sol_max','worker_reasoning'=>$bad]),'Reject invalid quick-select effort '.json_encode($bad));
 $custom=$base+['worker_choice'=>'custom','worker_reasoning'=>'high','model'=>'gpt-6-astra','reasoning'=>'ultra','tool'=>'Codex'];wsc(vs_configuration($custom)['capabilities']['reasoning']==='ultra','Custom effort takes precedence over inactive quick selector');
 $made=create_research(['title'=>'[QA] Worker settings '.uid(),'question'=>'Synthetic requested-worker settings and eligibility regression. No real AI run or scientific result.','category_id'=>1,'terms'=>1,'external_tools'=>'none']);$rid=$made['id'];moderate('request',$rid,'Approved','Synthetic worker settings fixture.');$r=research($rid);$planning=wf_tasks($rid)[0];
 foreach(VS_WORKERS as $choice=>$worker)foreach(['max','xhigh','high'] as $effort){$c=vs_configuration($base+['worker_choice'=>$choice,'worker_reasoning'=>$effort]);$c['consent']=['tool_terms_version'=>null];wsc(vs_configuration_matches($c,$r,$planning)===($effort==='max'),$choice.' / '.$effort.' preserves mandatory planning effort');}
 wf_test_submit($rid,$planning['id'],wf_test_payload($rid,$planning),wf_actor());$r=research($rid);$research=array_values(array_filter(wf_tasks($rid),fn($t)=>$t['kind']==='research'&&$t['state']==='Ready'))[0];
 foreach(VS_WORKERS as $choice=>$worker)foreach(['max','xhigh','high'] as $effort){$c=vs_configuration($base+['worker_choice'=>$choice,'worker_reasoning'=>$effort]);$c['consent']=['tool_terms_version'=>null];wsc(vs_configuration_matches($c,$r,$research),$choice.' / '.$effort.' permits unrestricted ordinary research');}
 foreach(['opus_max'=>'max','fable_max'=>'high','sol_max'=>'xhigh'] as $choice=>$effort){
  $_SESSION['workflow_actor']=bin2hex(random_bytes(32));putenv('RATE_LIMIT_NAMESPACE=worker-settings-'.uid());$post=['scope_kind'=>'research','research_id'=>$rid,'session_preset'=>'quick','worker_choice'=>$choice,'worker_reasoning'=>$effort,'authorize'=>1,'authorized_resources'=>1,'terms'=>1,'policy_version'=>terms_version()];$s=vs_create(vs_form_draft($post)+array_intersect_key($post,array_flip(['authorize','authorized_resources','terms','policy_version'])));$sessions[]=$s['id'];$stored=vs_read($s['id']);wsc($stored['capabilities']['model']===VS_WORKERS[$choice]['model']&&$stored['capabilities']['reasoning']===$effort,$choice.' persists the requested model/effort');wsc($stored['consent']['worker_settings_source']==='volunteer_requested_v1',$choice.' retains unverified requested provenance');$handoff=vo_start_message(vo_handoff($s['id'],$s['link']));wsc(str_contains($handoff,'reasoning='.$effort)&&str_contains($handoff,VS_WORKERS[$choice]['model']),$choice.' exact model/effort reaches the single-message handoff');vs_stop($s['id']);
 }
 if(!is_dir(storage('qa/worker-settings')))mkdir(storage('qa/worker-settings'),0770,true);file_put_contents(storage('qa/worker-settings/server-checks.json'),json_encode($checks,JSON_PRETTY_PRINT));echo count($checks)." worker settings checks passed.\n";
} finally {foreach($sessions as $id)query('DELETE FROM volunteer_sessions WHERE id=?',[$id]);if($rid&&one('SELECT id FROM research WHERE id=?',[$rid]))purge_request($rid,$rid);}
