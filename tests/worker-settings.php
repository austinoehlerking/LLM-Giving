<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';session_start_safe();$_SESSION['admin']=1;
$checks=[];$rid=null;$sessions=[];
function wsc(bool $ok,string $label): void {global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
try {
 $base=['scope_kind'=>'all','session_preset'=>'standard'];
 foreach(VS_WORKERS as $choice=>$worker){
  wsc(vs_configuration($base+['worker_choice'=>$choice])['capabilities']===$worker,$choice.' retains historical Max metadata');
  $post=$base+['worker_choice'=>$choice,'worker_reasoning'=>'high'];
  $configuration=vs_configuration($post);
  wsc($configuration['capabilities']===array_replace($worker,['reasoning'=>'high']),$choice.' explicit old effort is retained as metadata');
  wsc(vs_configuration(vs_form_draft($post))===$configuration,$choice.' legacy client round trip is compatible');
 }
 $old=$base+['setup_version'=>'2','model'=>'claude-fable-5-1','reasoning'=>'high','tool_source'=>'default','tool'=>'Codex','tool_default'=>'Codex'];
 wsc(vs_configuration($old)['capabilities']['tool']==='Claude Code','Legacy untouched tool still normalizes without changing permissions');
 $old['tool']='Custom harness';
 wsc(vs_configuration($old)['capabilities']['tool']==='Custom harness','Legacy explicit tool metadata survives');
 $custom=$base+['model'=>'new-model','reasoning'=>'provider-adaptive','tool'=>'New app','workflow_mode'=>'new-mode'];
 $configuration=vs_configuration($custom);
 wsc($configuration['capabilities']['model']==='new-model'&&$configuration['capabilities']['reasoning']==='provider-adaptive','Later metadata has no registry gate');
 $made=create_research(['title'=>'[QA] Capability-only '.uid(),'question'=>'Synthetic retired model equality regression with existing legacy client data.','category_id'=>1,'terms'=>1,'external_tools'=>'none']);
 $rid=$made['id'];moderate('request',$rid,'Approved','Synthetic capability-only fixture.');$r=research($rid);$planning=wf_tasks($rid)[0];
 foreach([$configuration,vs_configuration($base)] as $proposal){
  $proposal['consent']=['tool_terms_version'=>null];
  wsc(vs_configuration_matches($proposal,$r,$planning),'Public planning eligibility ignores absent or nonregistry metadata');
 }
 $_SESSION['workflow_actor']=bin2hex(random_bytes(32));putenv('RATE_LIMIT_NAMESPACE=worker-settings-'.uid());
 $post=['scope_kind'=>'research','research_id'=>$rid,'session_preset'=>'quick','worker_choice'=>'sol_max','worker_reasoning'=>'high','authorize'=>1,'authorized_resources'=>1,'terms'=>1,'policy_version'=>terms_version()];
 $s=vs_create($post);$sessions[]=$s['id'];$stored=vs_read($s['id']);
 wsc($stored['capabilities']['model']===VS_WORKERS['sol_max']['model']&&$stored['capabilities']['reasoning']==='high','Legacy client metadata is still readable');
 $handoff=vo_start_message(vo_handoff($s['id'],$s['link']));
 wsc(str_contains($handoff,$s['link'])&&!str_contains($handoff,'reasoning=')&&!str_contains($handoff,VS_WORKERS['sol_max']['model']),'Legacy metadata does not become a launch requirement');
 vs_stop($s['id']);
 if(!is_dir(storage('qa/worker-settings')))mkdir(storage('qa/worker-settings'),0770,true);
 file_put_contents(storage('qa/worker-settings/server-checks.json'),json_encode($checks,JSON_PRETTY_PRINT));echo count($checks)." legacy worker metadata checks passed.\n";
} finally {foreach($sessions as $id)query('DELETE FROM volunteer_sessions WHERE id=?',[$id]);if($rid&&one('SELECT id FROM research WHERE id=?',[$rid]))purge_request($rid,$rid);}
