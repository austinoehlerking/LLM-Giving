<?php
require __DIR__.'/bootstrap.php';session_start_safe();$_SESSION['admin']=1;$checks=[];
function isc(bool $value,string $label): void {global $checks;if(!$value)throw new RuntimeException($label);$checks[]=$label;}
function iscounts():array{$out=[];foreach(['volunteer_sessions','volunteer_assignments','browser_returns','task_reservations','research'] as $table){$out[$table]=(int)one('SELECT COUNT(*) n FROM '.$table)['n'];}return $out;}
$before=iscounts();$fresh=vs_setup_defaults([]);$config=vs_configuration($fresh);
isc(!array_filter($config['capabilities']),'Fresh setup invents no worker metadata');
isc($config['limit_mode']==='bounded'&&$config['minutes']===15&&$config['assignment_limit']===1&&$config['session_profile']==='restricted_web_v1'&&$config['scope_kind']==='all','Fresh Quick try defaults to Research only');
isc(vs_configuration(['setup_version'=>'3','scope_kind'=>'all'])['session_profile']==='restricted_web_v1','Current form defaults safely when a profile is omitted');
isc(vs_configuration(['setup_version'=>'2','scope_kind'=>'all'])['session_profile']==='general','Legacy form without a profile retains its prior default');
$restored=['session_profile'=>'general','session_preset'=>'custom','minutes'=>'125','assignment_limit'=>'7','concurrent_limit'=>'2','scope_kind'=>'all'];
$draft=vs_setup_defaults($restored);$round=vs_configuration(vs_form_draft($draft));
isc($round['minutes']===125&&$round['assignment_limit']===7&&$round['concurrent_limit']===2&&$round['session_profile']==='general','Explicit capability, capacity and custom limits survive review');
foreach(['quick'=>[15,1],'standard'=>[60,3],'long'=>[180,10],'unlimited'=>[null,null]] as $preset=>$values){$p=vs_setup_defaults(['session_preset'=>$preset,'minutes'=>'99','assignment_limit'=>'19']);$c=vs_configuration($p);isc([$c['minutes'],$c['assignment_limit']]===$values,'Named '.$preset.' ignores stale custom numbers');}
foreach(VS_WORKERS as $preset=>$worker)isc(vs_configuration(['scope_kind'=>'all','session_preset'=>'standard','worker_choice'=>$preset])['capabilities']===$worker,'Legacy '.$preset.' retains its original informational settings');
$draft=$fresh;$error='';ob_start();include ROOT.'/views/volunteer-setup.php';$html=ob_get_clean();
isc(substr_count($html,'name="session_profile"')===1&&!preg_match('/name="(?:model|reasoning|worker_choice|tool|workflow_mode)"/',$html),'Rendered setup has capability selection and no worker settings fields');
isc(!str_contains($html,wf_research_guidance())&&preg_match('/<details[^>]+data-volunteer-advanced[^>]*>.*name="session_profile".*<\/details>/s',$html)===1,'Capability selection stays in Advanced options without reasoning advice');
foreach(['15 min or 1 task','60 min or 3 tasks','3 hrs or 10 tasks','∞ Until Stop'] as $caption)isc(str_contains($html,$caption),'Preset caption: '.$caption);
isc(!str_contains($html,'name="authorize"')&&!str_contains($html,'name="terms"')&&!str_contains($html,'name="human_level"'),'Setup never preauthorizes terms or invents an upfront declaration');
$draft=vs_setup_defaults(['session_profile'=>'invalid-profile','concurrent_limit'=>'99','scope_kind'=>'category','category_id'=>'999999']);$error='Review the invalid setting.';ob_start();include ROOT.'/views/volunteer-setup.php';$invalidHtml=ob_get_clean();
foreach(['invalid-profile','99','999999'] as $value)isc(str_contains($invalidHtml,'value="'.$value.'" selected'),'Invalid capability/scope value remains visible: '.$value);
$draft=$fresh;$configuration=$config;$startupEditable=true;$startupSummaryOnly=false;$startupDetailsOnly=false;ob_start();include ROOT.'/views/volunteer-startup.php';$recheck=ob_get_clean();
isc(str_contains($recheck,'id="startup-capabilities"')&&!str_contains($recheck,'name="authorize"'),'Availability recheck changes a capability without authorization');
isc(iscounts()===$before,'Rendering, defaults and configuration create no research/session/assignment/return/reservation');
if(!is_dir(storage('qa/volunteer-inline')))mkdir(storage('qa/volunteer-inline'),0770,true);
file_put_contents(storage('qa/volunteer-inline/server-checks.json'),json_encode($checks,JSON_PRETTY_PRINT));echo count($checks)." inline setup checks passed.\n";
