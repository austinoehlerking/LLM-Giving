<?php
require __DIR__.'/bootstrap.php';require_once ROOT.'/src/view.php';require __DIR__.'/tool-fixture.php';require __DIR__.'/context-directory-fixture.php';

session_start_safe();$_SESSION['admin']=1;$checks=[];$ids=[];
function tc(bool $ok,string $label): void {global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
function td(callable $fn,string $label,int $status=422): void {try{$fn();}catch(HttpError $e){tc($e->status===$status,$label.' ('.$e->getMessage().')');return;}throw new RuntimeException('Expected rejection: '.$label);}
function tp(string $rid,array $task,array $packet,string $actor='a'): void {wf_test_submit($rid,$task['id'],$packet,str_repeat($actor,64),true);}
try{
 $bad=rt_test_request();$bad['project_type']='arbitrary';td(fn()=>create_research($bad),'Unknown project types rejected');
 foreach(array_keys(RT_FIELDS) as $field){$bad=rt_test_request();unset($bad['tool_'.$field]);td(fn()=>create_research($bad),'Tool requirements enforced: '.$field);}
 $plain=rt_test_request();unset($plain['project_type']);$plain=create_research($plain);$ids[]=$plain['id'];moderate('request',$plain['id'],'Approved','Disposable standard research regression fixture.');
 $created=create_research(rt_test_request());$rid=$created['id'];$ids[]=$rid;td(fn()=>context_data($rid),'Pending project stays private',404);moderate('request',$rid,'Approved','Disposable research-tool integration fixture.');
 tc(!rt_is_tool(research($plain['id']))&&rt_is_tool(research($rid)),'Project type persists and standard research remains the default');
 $context=context_data($rid);tc($context['research']['project_type']==='research_tool'&&count($context['research']['tool_requirements'])===5&&!isset($context['research']['email']),'Public context includes complete tool requirements and excludes private email');
 tc(in_array($rid,array_column(discover(['project_type'=>'research_tool'])['rows'],'id'),true)&&!in_array($plain['id'],array_column(discover(['project_type'=>'research_tool'])['rows'],'id'),true),'Discovery filters tool builds separately');
 tc(discovery_filters(['project_type'=>['research_tool']])['project_type']==='','Malformed project filter safely ignored');
 $plan=rt_test_task($rid,'planning');$packet=rt_test_plan($rid,$plan);$bad=$packet;$bad['tasks'][0]['builds_tool']=false;td(fn()=>tp($rid,$plan,$bad),'Tool plan requires implementation task');
 $bad=$packet;$bad['tasks'][0]['builds_tool']='true';td(fn()=>tp($rid,$plan,$bad),'Build flag must be a boolean');
 $plainTask=rt_test_task($plain['id'],'planning');td(fn()=>tp($plain['id'],$plainTask,rt_test_plan($plain['id'],$plainTask)),'Standard research cannot silently add tool-build tasks');
 $bad=$packet;unset($bad['tool_terms']);td(fn()=>wf_test_submit($rid,$plan['id'],$bad,str_repeat('a',64)),'Submission needs separate human software consent');
 wf_test_submit($rid,$plan['id'],$packet,str_repeat('a',64));$task=rt_test_task($rid,'research');tc((bool)$task['builds_tool'],'Build requirement survives planning');
 $prompt=wf_prompt($rid,$task['id']);$scope=qa_required_dependency($rid,$task['id'],$prompt,'research');
 tc(str_contains($prompt,'BUILD A RESEARCH TOOL')&&str_contains($prompt,'tool_builds')&&$scope['value']['tool_requirements']===rt_brief(research($rid))&&str_contains($scope['body'],'No geospatial projection'),'Handoff retains trusted tool instructions/schema and retrieves exact requester scope with verified dependency bytes');
 tc(str_contains(wf_repair_prompt(research($rid),$task,'Missing test record.'),'invalid_input'),'Repair prompts preserve the tool contract');
 $packet=rt_test_build($rid,$task);$bad=$packet;$bad['evidence']['tool_builds']=[];td(fn()=>tp($rid,$task,$bad),'Completion cannot omit a tool');
 $bad=$packet;$bad['evidence']['tool_builds'][0]['files'][0]['content'].=' ';td(fn()=>tp($rid,$task,$bad),'Altered file bytes rejected');
 $bad=$packet;$bad['evidence']['tool_builds'][0]['files'][0]['name']='source.mjs.';td(fn()=>tp($rid,$task,$bad),'Trailing-dot filenames cannot alias other files on Windows');
 $reserved=$packet;$reserved['evidence']['tool_builds'][0]['files'][0]['name']='CON.mjs';td(fn()=>tp($rid,$task,$reserved),'Windows device filenames rejected');
 $bad=$packet;$bad['evidence']['tool_builds'][0]['files'][0]['name']='../source.mjs';td(fn()=>tp($rid,$task,$bad),'Path traversal filenames rejected');
 $bad=$packet;$bad['evidence']['tool_builds'][0]['files'][1]['name']='DISTANCE-TOOL.MJS';td(fn()=>tp($rid,$task,$bad),'Case-insensitive duplicate filenames rejected');
 $bad=$packet;$bad['evidence']['tool_builds'][0]['files']=array_slice($bad['evidence']['tool_builds'][0]['files'],0,1);td(fn()=>tp($rid,$task,$bad),'Source without retained test code rejected');
 $bad=$packet;$bad['evidence']['tool_builds'][0]['source_ids']=['foreign'];td(fn()=>tp($rid,$task,$bad),'Unknown reference source rejected');
 $bad=$packet;$bad['evidence']['tool_builds'][0]['license']='proprietary';td(fn()=>tp($rid,$task,$bad),'Tool software license cannot be overridden');
 $bad=$packet;array_pop($bad['evidence']['tool_builds'][0]['tests']);td(fn()=>tp($rid,$task,$bad),'Missing invalid-input coverage blocks completion');
 $bad=$packet;$bad['evidence']['tool_builds'][0]['tests'][0]['observed']='2500000';td(fn()=>tp($rid,$task,$bad),'Incorrect numerical output cannot be declared passed');
 $bad=$packet;$bad['evidence']['tool_builds'][0]['tests'][0]['absolute_tolerance']=-1;td(fn()=>tp($rid,$task,$bad),'Negative tolerances rejected');
 $exact=$packet;$exact['evidence']['tool_builds'][0]['tests'][1]['expected']="0\n";$exact['evidence']['tool_builds'][0]['tests'][1]['observed']='0';td(fn()=>tp($rid,$task,$exact),'Exact comparison preserves meaningful trailing whitespace');
 $bad=$packet;$bad['evidence']['tool_builds'][0]['tests'][1]['relative_tolerance']=0.1;td(fn()=>tp($rid,$task,$bad),'Exact comparisons cannot hide nonzero tolerances');
 $bad=$packet;$bad['evidence']['tool_builds'][0]['tests'][0]['status']='not_run';td(fn()=>tp($rid,$task,$bad),'Unrun tests block completion');
 $bad=$packet;$bad['evidence']['tool_builds'][0]['status']='draft';td(fn()=>tp($rid,$task,$bad),'Drafts cannot complete a build');
 $bad=$packet;$bad['evidence']['tool_builds'][0]['tests'][0]['file_id']='source';td(fn()=>tp($rid,$task,$bad),'Tests must reference retained test code');
 $before=count(wf_public_data($rid)['submissions']);tp($rid,$task,$packet);tc(count(wf_public_data($rid)['submissions'])===$before,'Check packet performs no workflow writes');
 $progress=rt_test_build($rid,$task,'progress');$progress['evidence']['tool_builds'][0]['status']='draft';$progress['evidence']['tool_builds'][0]['tests'][0]['status']='not_run';$progress['evidence']['tool_builds'][0]['tests'][0]['observed']='';$progressId=wf_test_submit($rid,$task['id'],$progress,str_repeat('d',64));tc(wf_task($rid,$task['id'])['state']==='Ready'&&rt_read($rid,$progressId)['id']===$progressId,'Incomplete draft retained and downloadable without advancement');
 $packet=rt_test_build($rid,$task);$resultId=wf_test_submit($rid,$task['id'],$packet,str_repeat('a',64));tc(wf_task($rid,$task['id'])['state']==='Completed','Tested build completes its task');td(fn()=>rt_read($rid,$progressId),'Completed version supersedes draft downloads',404);
 $result=rt_read($rid,$resultId);$meta=json_decode($result['metadata'],true);tc($meta['tool_software_license']===RT_LICENSE&&$meta['tool_terms_version']===RT_TERMS,'Server records software license and consent version');
 $r=research($rid);ob_start();
 set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
 try{include ROOT.'/views/tool-catalogue.php';$catalogue=ob_get_contents();}
 finally{restore_error_handler();ob_end_clean();}
 tc(str_contains($catalogue,'Current research tool builds')&&str_contains($catalogue,'/tools/distance/bundle.json'),'Public tool catalogue renders current accepted builds without PHP warnings');
 ob_start();rt_route(research($rid),'results/'.$resultId.'/tools/distance/bundle.json','GET');$bundle=json_decode(ob_get_clean(),true);tc($bundle['format']==='llm-giving-tool-v1'&&$bundle['software_license']===RT_LICENSE&&str_contains($bundle['software_license_text'],'GNU AFFERO'),'Download includes software license text and immutable result/context identities');foreach($bundle['build']['files'] as $file)tc($file['sha256']===hash('sha256',$file['content']),'Bundle preserves exact '.$file['role'].' file bytes');
 td(fn()=>rt_read($plain['id'],$resultId),'Cross-project bundle access rejected',404);
 ob_start();rt_route(research($rid),'results/'.$resultId.'/tools/distance/files/source','GET');$source=ob_get_clean();tc($source===$bundle['build']['files'][0]['content'],'Individual source download preserves exact bytes');
 $synthesis=rt_test_task($rid,'synthesis');$syn=wf_test_payload($rid,$synthesis);$syn['tool_terms']=1;wf_test_submit($rid,$synthesis['id'],$syn,str_repeat('b',64));$review=rt_test_task($rid,'review');$packet=rt_test_review($rid,$review);
 $bad=$packet;$bad['evidence']['tool_reviews']=[];td(fn()=>tp($rid,$review,$bad,'c'),'Verified review cannot omit tool reproduction');
 td(fn()=>tp($rid,$review,$packet,'a'),'Builder cannot independently review own tool');td(fn()=>tp($rid,$review,$packet,'d'),'Earlier draft builder cannot review a successor build');$bad=$packet;$bad['metadata']['run_id']=$meta['run_id'];td(fn()=>tp($rid,$review,$bad,'c'),'Builder AI run cannot be reused as independent reviewer');
 $bad=$packet;$bad['evidence']['tool_reviews'][0]['test_results'][0]['observed']='25';td(fn()=>tp($rid,$review,$bad,'c'),'Independent observed output must agree with expected value');
 $bad=$packet;$bad['evidence']['tool_reviews'][0]['test_results']=[];td(fn()=>tp($rid,$review,$bad,'c'),'Independent review must rerun every retained test');
 $bad=$packet;$bad['evidence']['tool_reviews'][0]['scientific_assessment']='';td(fn()=>tp($rid,$review,$bad,'c'),'Scientific assumptions require explicit independent assessment');
 $bad=$packet;$bad['evidence']['tool_reviews'][0]['result_id']=$progressId;td(fn()=>tp($rid,$review,$bad,'c'),'Review cannot substitute a progress draft for current build');
 $bad=$packet;$bad['evidence']['tool_reviews'][0]['verdict']='unverifiable';td(fn()=>tp($rid,$review,$bad,'c'),'Unverifiable build cannot receive verified outcome');
 $correctId=wf_test_submit($rid,$review['id'],rt_test_review($rid,$review,'needs_correction'),str_repeat('c',64));$correction=json_decode(one('SELECT metadata FROM workflow_submissions WHERE id=?',[$correctId])['metadata'],true)['corrections'][0];
 tc($correction['criterion_id']==='tool-standard'&&$correction['tool_id']==='distance','Tool review creates an evidence-linked build correction');
 tc(rt_test_task($rid,'research')['state']==='Ready'&&rt_test_task($rid,'synthesis')['state']==='Waiting'&&wf_findings($rid)===null,'Correction reopens build and dependent synthesis/review');td(fn()=>rt_read($rid,$resultId),'Superseded tool bundle revoked',404);
 $task=rt_test_task($rid,'research');$fixed=rt_test_build($rid,$task);$fixed['human_interaction']=['level'=>'influenced','details'=>'Synthetic fixture: expert correction was supplied and is disclosed.'];$fixedId=wf_test_submit($rid,$task['id'],$fixed,str_repeat('a',64));td(fn()=>rt_read($rid,$fixedId),'Quarantined tool bundle not accepted evidence',404);wf_review_influence($rid,$fixedId,'Reviewed synthetic correction, retained files and disclosed influence.','approve');tc(rt_test_task($rid,'research')['state']==='Completed','Quarantine approval revalidates normalized tool records');
 $synthesis=rt_test_task($rid,'synthesis');$syn=wf_test_payload($rid,$synthesis);$syn['tool_terms']=1;wf_test_submit($rid,$synthesis['id'],$syn,str_repeat('b',64));$review=rt_test_task($rid,'review');$verified=rt_test_review($rid,$review);wf_test_submit($rid,$review['id'],$verified,str_repeat('c',64));tc(research($rid)['status']==='Review Complete','Revised tool and full independent reproduction finish the review cycle');
 $result=rt_read($rid,$fixedId);$r=research($rid);$base='/research/'.$r['slug'];$admission='accepted';$meta=json_decode($result['metadata'],true);$meta['evidence']['tool_builds'][0]['files'][0]['content']='<script>window.toolInjected=true</script>';ob_start();include ROOT.'/views/workflow-tools.php';$html=ob_get_clean();tc(str_contains($html,'Download tool bundle')&&str_contains($html,'&lt;script&gt;')&&!str_contains($html,'<script>'),'Source text is escaped in build/review UI');
 query('UPDATE workflow_snapshots SET public_allowed=0 WHERE id=?',[$result['snapshot_id']]);td(fn()=>rt_read($rid,$fixedId),'Snapshot revocation blocks tool downloads',404);query('UPDATE workflow_snapshots SET public_allowed=1 WHERE id=?',[$result['snapshot_id']]);
 change_status($rid,'Withdrawn','Withdraw the disposable research tool fixture.','requester');td(fn()=>rt_read($rid,$fixedId),'Parent withdrawal blocks tool downloads',404);
 file_put_contents(storage('qa/tool-builds/integration-checks.json'),json_encode($checks,JSON_PRETTY_PRINT));echo count($checks)." research-tool workflow checks passed.\n";
}finally{foreach($ids as $id)if(one('SELECT id FROM research WHERE id=?',[$id]))purge_request($id,$id);}