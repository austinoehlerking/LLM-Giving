<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/calculation-fixture.php';

session_start_safe();$_SESSION['admin']=1;$checks=[];$ids=[];
function cc(bool $ok,string $label): void {global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
function cd(callable $fn,string $label,int $status=422): void {try{$fn();}catch(HttpError $e){cc($e->status===$status,$label.' ('.$e->getMessage().')');return;}throw new RuntimeException('Expected rejection: '.$label);}
function ct(string $rid,string $kind): array {foreach(wf_tasks($rid) as $t)if($t['kind']===$kind)return $t;throw new RuntimeException('Missing task '.$kind);}
function cp(string $rid,array $t,array $d,string $actor='a'): void {wf_submit($rid,$t['id'],$d,str_repeat($actor,64),true);}
try{
 foreach([false,true] as $models){$r=create_research(['title'=>'[QA] Climate calculation '.uid(),'question'=>'Reproduce the existing paired annual climate calculation with explicit units and bounded limitations.','category_id'=>one('SELECT id FROM categories LIMIT 1')['id'],'computational_models'=>$models,'terms'=>1]);$ids[]=$r['id'];moderate('request',$r['id'],'Approved','Approve a disposable climate calculation fixture.');}
 [$plain,$rid]=$ids;cc(!context_data($plain)['research']['computational_models']&&context_data($rid)['research']['computational_models']===true,'Opt-in is distinct, persistent and exported as a boolean');
 $plan=ct($rid,'planning');$packet=rc_test_plan($rid,$plan);$bad=$packet;$bad['tasks'][0]['computational']=false;cd(fn()=>cp($rid,$plan,$bad),'Flagged requests require a planned computational task');
 $plainTask=ct($plain,'planning');cd(fn()=>cp($plain,$plainTask,rc_test_plan($plain,$plainTask)),'Execution plan rejected without requester opt-in');
 $bad=$packet;$bad['tasks'][0]['computational']='true';cd(fn()=>cp($rid,$plan,$bad),'Task flags require a JSON boolean');wf_submit($rid,$plan['id'],$packet,str_repeat('a',64));$task=ct($rid,'research');cc((bool)$task['computational'],'Task execution requirement survives planning');
 $progress=rc_test_calculation($rid,$task);$bad=$progress;unset($bad['evidence']['calculations']);cd(fn()=>cp($rid,$task,$bad),'Computational progress needs retained calculation files');
 $bad=$progress;$bad['evidence']['calculations'][0]['files'][0]['content'].=' ';cd(fn()=>cp($rid,$task,$bad),'Changed file bytes fail SHA-256 identity');
 $bad=$progress;$bad['evidence']['calculations'][0]['files'][1]['name']='INPUTS.JSON';cd(fn()=>cp($rid,$task,$bad),'Duplicate case-insensitive filenames cannot overwrite archived inputs');
 foreach(['CON.mjs','inputs.json.'] as $unsafeName){$bad=$progress;$bad['evidence']['calculations'][0]['files'][0]['name']=$unsafeName;cd(fn()=>cp($rid,$task,$bad),'Nonportable calculation filename rejected: '.$unsafeName);}
 $bad=$progress;$bad['evidence']['calculations'][0]['outputs'][0]['value']/=1000;cd(fn()=>cp($rid,$task,$bad),'A factor-of-1000 gas mass error is rejected');
 $bad=$progress;$bad['evidence']['calculations'][0]['outputs'][0]['unit']='kg CH4/ha/season';cd(fn()=>cp($rid,$task,$bad),'Annual and seasonal boundaries cannot silently mix');
 $bad=$progress;$bad['evidence']['calculations'][0]['status']='proposed';cd(fn()=>cp($rid,$task,$bad),'Proposed code cannot carry successful execution outputs');
 $bad=$progress;$bad['evidence']['calculations'][0]['outputs'][0]['file_id']='code';cd(fn()=>cp($rid,$task,$bad),'Quantity pointers require matching input/output file roles');
 $bad=$progress;$bad['evidence']['calculation_checkpoint']=null;cd(fn()=>cp($rid,$task,$bad),'Interrupted work needs an actionable checkpoint');
 $before=count(wf_public_data($rid)['submissions']);cp($rid,$task,$progress);cc(count(wf_public_data($rid)['submissions'])===$before,'Check packet validates without recording work');
 $progressId=wf_submit($rid,$task['id'],$progress,str_repeat('a',64));cc(wf_task($rid,$task['id'])['state']==='Ready'&&rc_progress($task)['id']===$progressId,'Progress is retained without claiming task completion');
 $prompt=wf_prompt($rid,$task['id']);cc(str_contains($prompt,'RESUME ACCEPTED CHECKPOINT: '.$progressId)&&str_contains($prompt,'model.mjs'),'Fresh handoff includes complete checkpoint and reproducible files');
 $complete=rc_test_calculation($rid,$task,'complete',$progressId);$bad=$complete;$bad['evidence']['calculation_checkpoint']['resume_from_result_id']='';cd(fn()=>cp($rid,$task,$bad,'b'),'Resumption must link an accepted progress record in the exact context');
 $bad=$complete;$bad['evidence']['calculation_checkpoint']['resume_from_result_id']=uid();cd(fn()=>cp($rid,$task,$bad,'b'),'Foreign checkpoint references rejected');
 $completeId=wf_submit($rid,$task['id'],$complete,str_repeat('b',64));cc(wf_task($rid,$task['id'])['state']==='Completed','Another contributor can resume and complete the same task');
 $synthesis=ct($rid,'synthesis');wf_submit($rid,$synthesis['id'],wf_test_payload($rid,$synthesis),str_repeat('a',64));$review=ct($rid,'review');$packet=rc_test_review($rid,$review);
 $bad=$packet;$bad['evidence']['calculation_reviews']=[];cd(fn()=>cp($rid,$review,$bad,'c'),'Verification requires independent calculation reproduction');
 $bad=$packet;$bad['evidence']['calculation_reviews'][0]['observed_outputs'][0]['value']/=1000;cd(fn()=>cp($rid,$review,$bad,'c'),'A claimed reproduction cannot disagree by a unit factor');
 $bad=$packet;$bad['evidence']['calculation_reviews'][0]['observed_outputs']=[];cd(fn()=>cp($rid,$review,$bad,'c'),'Reproduction must report every output quantity');
 $bad=$packet;$bad['evidence']['calculation_reviews'][0]['result_id']=$progressId;cd(fn()=>cp($rid,$review,$bad,'c'),'Review cannot substitute progress for current accepted output');
 $correction=rc_test_review($rid,$review,'needs_correction');$correctionId=wf_submit($rid,$review['id'],$correction,str_repeat('c',64));$meta=json_decode(one('SELECT metadata FROM workflow_submissions WHERE id=?',[$correctionId])['metadata'],true);
 cc($meta['corrections'][0]['criterion_id']==='calculation-standard'&&$meta['corrections'][0]['calculation_id']==='climate','Calculation audit automatically generates a targeted correction');
 cc(ct($rid,'research')['state']==='Ready'&&ct($rid,'synthesis')['state']==='Waiting'&&wf_findings($rid)===null,'Calculation correction invalidates dependent synthesis and review');
 cd(fn()=>rc_read($rid,$completeId),'Superseded calculation bundle cannot be downloaded',404);cd(fn()=>rc_read($plain,$progressId),'Foreign result bundle cannot be downloaded',404);
 $task=ct($rid,'research');$fixed=rc_test_calculation($rid,$task,'complete');$fixed['human_interaction']=['level'=>'influenced','details'=>'Synthetic test: expert correction to the unit convention was disclosed.'];$fixedId=wf_submit($rid,$task['id'],$fixed,str_repeat('b',64));
 cd(fn()=>rc_read($rid,$fixedId),'Quarantined calculation bundle is not accepted evidence',404);wf_review_influence($rid,$fixedId,'Reviewed disclosed test correction, retained files and unit consistency.','approve');cc(ct($rid,'research')['state']==='Completed','Quarantine approval revalidates normalized calculation metadata');
 $synthesis=ct($rid,'synthesis');wf_submit($rid,$synthesis['id'],wf_test_payload($rid,$synthesis),str_repeat('a',64));$review=ct($rid,'review');wf_submit($rid,$review['id'],rc_test_review($rid,$review),str_repeat('c',64));cc(research($rid)['status']==='Review Complete','Corrected and reproduced work completes the review cycle');
 $result=rc_read($rid,$fixedId);ob_start();rc_route(research($rid),'results/'.$fixedId.'/calculations/climate/bundle.json','GET');$bundle=json_decode(ob_get_clean(),true);cc($bundle['calculation']['files'][0]['sha256']===hash('sha256',$bundle['calculation']['files'][0]['content']),'Download preserves exact file bytes and their manifest hashes');
 $meta=json_decode($result['metadata'],true);$meta['evidence']['calculations'][0]['title']='<img src=x onerror=alert(1)>';$meta['evidence']['calculations'][0]['files'][0]['content']='<script>alert(1)</script>';$r=research($rid);$base='/research/'.$r['slug'];$admission='accepted';ob_start();include ROOT.'/views/workflow-calculations.php';$html=ob_get_clean();cc(str_contains($html,'Download reproducibility bundle')&&str_contains($html,'&lt;script&gt;')&&!str_contains($html,'<script>')&&!str_contains($html,'<img src=x'),'Calculation record renders escaped code and download controls');
 change_status($rid,'Withdrawn','Hide this disposable calculation fixture.','requester');cd(fn()=>rc_read($rid,$fixedId),'Parent withdrawal revokes calculation downloads',404);
 file_put_contents(storage('qa/calculations/integration-checks.json'),json_encode($checks,JSON_PRETTY_PRINT));echo count($checks)." calculation workflow checks passed.\n";
}finally{foreach($ids as $id)if(one('SELECT id FROM research WHERE id=?',[$id]))purge_request($id,$id);}