<?php
// Criteria are stable within a task revision. One nonempty line is one requirement.
function wf_criteria(array $t): array {
 $out=[];foreach(preg_split('/\R/u',$t['acceptance']) as $line)if(trim($line)!=='')$out['criterion-'.(count($out)+1)]=trim($line);return $out;
}
function wf_check_targets(array $t): array {
 $out=[['task'=>$t,'result'=>null]];
 if($t['kind']==='review')foreach(wf_tasks($t['research_id']) as $target)if(in_array($target['kind'],['research','synthesis'],true)&&$target['visibility']==='public'&&$target['state']==='Completed'){
 $result=one("SELECT * FROM workflow_submissions WHERE id=? AND research_id=? AND visibility='public'",[$target['accepted_id'],$t['research_id']]);if($result&&ra_current($result))$out[]=['task'=>$target,'result'=>$result];
 }return $out;
}
function wf_check_template(array $t): array {
 $out=[];foreach(wf_check_targets($t) as $target)foreach(wf_criteria($target['task']) as $id=>$criterion)$out[]=['task_id'=>$target['task']['id'],'result_id'=>$target['result']['id']??'self','criterion_id'=>$id,'status'=>'','evidence_quote'=>'','reason'=>'','correction_response'=>''];return $out;
}
function wf_acceptance(array $t,array $d,array $row): array {
 $terminal=in_array($row['outcome'],['completed','inconclusive','verified'],true);$checks=$d['acceptance_checks']??[];$issues=$d['corrections']??[];
 if(!is_array($checks)||!array_is_list($checks)||count($checks)>300||!is_array($issues)||!array_is_list($issues)||count($issues)>300)fail(422,'Acceptance checks and corrections must be bounded lists.');
 $expected=[];$labels=[];foreach(wf_check_targets($t) as $target)foreach(wf_criteria($target['task']) as $id=>$criterion){$key=$target['task']['id'].'/'.($target['result']['id']??'self').'/'.$id;$expected[$key]=$target['result']??$row;$labels[$key]=$target['task']['title'].': '.$criterion;}
 $clean=[];$seen=[];foreach($checks as $c){if(!is_array($c))fail(422,'Each acceptance check must be an object.');$key=text_field($c,'task_id',32,32).'/'.text_field($c,'result_id',4,32).'/'.text_field($c,'criterion_id',1,50);if(!isset($expected[$key])||isset($seen[$key]))fail(422,'Acceptance check references an unknown, stale or duplicate criterion/result.');
 $quote=text_field($c,'evidence_quote',20,3000);$reason=text_field($c,'reason',10,1000);$status=text_field($c,'status',1,20);if(!in_array($status,['met','not_met'],true))fail(422,'Acceptance status must be met or not_met.');
 $e=$expected[$key];if(!str_contains(implode("\n",array_map(fn($f)=>$e[$f],['body','methodology','sources','limitations'])),$quote))fail(422,'Evidence quote must exactly match the referenced result for: '.$labels[$key].'. Ask the AI to quote its actual evidence; do not edit or invent a quote.');
 if($terminal&&$status!=='met')fail(422,'Unmet deliverable criteria cannot complete a task. Use progress, clarification or a targeted correction. Scientific uncertainty may still be a fully documented result.');
 $response=text_field($c,'correction_response',($terminal&&$c['result_id']==='self'&&(int)$t['revision']>1)?20:0,3000);
 $clean[]=['correction_response'=>$response,'task_id'=>$c['task_id'],'result_id'=>$c['result_id'],'criterion_id'=>$c['criterion_id'],'status'=>$status,'evidence_quote'=>$quote,'reason'=>$reason];$seen[$key]=true;
 }if($terminal&&count($seen)!==count($expected)){$missing=array_diff_key($labels,$seen);fail(422,'Complete every acceptance check with an evidence quote and assessment before completing this task. Missing '.count($missing).' check(s): '.implode(' | ',array_slice(array_values($missing),0,3)).(count($missing)>3?' | See the remaining criteria in the handoff.':''));}
 $targets=[];foreach($issues as $c){if($t['kind']!=='review'||$row['outcome']!=='needs_correction'||!is_array($c))fail(422,'Targeted corrections belong to a needs_correction review.');$target=wf_task($t['research_id'],text_field($c,'task_id',32,32));if($target['round_no']!=$t['round_no']||$target['visibility']!=='public'||!in_array($target['kind'],['research','synthesis'],true)||$target['state']!=='Completed'||$target['accepted_id']!==($c['result_id']??''))fail(422,'Correction target must be a current accepted research or synthesis result.');
 $criterion=text_field($c,'criterion_id',1,50);$claimId=text_field($c,'claim_id',0,40);$artifactId=text_field($c,'artifact_id',0,40);$calculationId=text_field($c,'calculation_id',0,40);$toolId=text_field($c,'tool_id',0,40);$derivationId=$criterion==='derivation-standard'?text_field($c,'derivation_id',1,40):'';
 if($criterion==='evidence-standard'){
  $accepted=one("SELECT metadata FROM workflow_submissions WHERE id=? AND research_id=? AND visibility='public' AND admission='accepted'",[$target['accepted_id'],$t['research_id']]);
  $claims=json_decode($accepted['metadata'],true)['evidence']['claims']??[['id'=>'legacy']];
  if(!in_array($claimId,array_column($claims,'id'),true))fail(422,'Evidence correction must identify a central claim in the current accepted result.');
 }elseif($criterion==='tool-standard'){
  $accepted=one("SELECT metadata FROM workflow_submissions WHERE id=? AND research_id=? AND visibility='public' AND admission='accepted'",[$target['accepted_id'],$t['research_id']]);
  $builds=json_decode($accepted['metadata'],true)['evidence']['tool_builds']??[];
  if(!in_array($toolId,array_column($builds,'id'),true))fail(422,'Tool correction must identify a current accepted build.');
 }elseif($criterion==='calculation-standard'){
  $accepted=one("SELECT metadata FROM workflow_submissions WHERE id=? AND research_id=? AND visibility='public' AND admission='accepted'",[$target['accepted_id'],$t['research_id']]);
  $calculations=json_decode($accepted['metadata'],true)['evidence']['calculations']??[];
  if(!in_array($calculationId,array_column($calculations,'id'),true))fail(422,'Calculation correction must identify a current accepted calculation.');
 }elseif($criterion==='derivation-standard'){
  $accepted=one("SELECT metadata FROM workflow_submissions WHERE id=? AND research_id=? AND visibility='public' AND admission='accepted'",[$target['accepted_id'],$t['research_id']]);
  $derivations=json_decode($accepted['metadata'],true)['evidence']['derivations']??[];
  if(!in_array($derivationId,array_column($derivations,'id'),true))fail(422,'Derivation correction must identify current accepted model-derived arithmetic.');
 }elseif($criterion==='artifact-standard'){
  $accepted=one("SELECT metadata FROM workflow_submissions WHERE id=? AND research_id=? AND visibility='public' AND admission='accepted'",[$target['accepted_id'],$t['research_id']]);
  $artifacts=json_decode($accepted['metadata'],true)['evidence']['artifacts']??[];
  if(!in_array($artifactId,array_column($artifacts,'id'),true))fail(422,'Visual correction must identify an artifact in the current accepted result.');
 }elseif($criterion==='supporting-materials'){
  if(!isset(wf_evidence_results($t)[$target['accepted_id']]))fail(422,'Material correction needs eligible current evidence.');
 }elseif(!isset(wf_criteria($target)[$criterion]))fail(422,'Correction criterion does not belong to its task.');
 $entry=['task_id'=>$target['id'],'result_id'=>$target['accepted_id'],'criterion_id'=>$criterion,'claim_id'=>$claimId,'artifact_id'=>$artifactId,'calculation_id'=>$calculationId,'tool_id'=>$toolId,'reason'=>text_field($c,'reason',10,1000),'required_change'=>text_field($c,'required_change',10,3000)];if($derivationId!=='')$entry['derivation_id']=$derivationId;$targets[]=$entry;
 }if($row['outcome']==='needs_correction'&&!$targets)fail(422,'Select at least one task, accepted result and criterion requiring correction.');
 return ['acceptance_checks'=>$clean,'corrections'=>$targets];
}
function wf_correct_tasks(string $rid,array $issues,string $reviewId): void {
 $tasks=wf_tasks($rid);$affected=array_fill_keys(array_column($issues,'task_id'),true);
 do{$changed=false;foreach($tasks as $t)if(!isset($affected[$t['id']])&&array_intersect(json_decode($t['dependencies'],true),array_keys($affected))){$affected[$t['id']]=true;$changed=true;}}while($changed);
 foreach($tasks as $t)if(isset($affected[$t['id']])){wr_invalidate($rid,$t['id']);query("UPDATE workflow_tasks SET state='Waiting',revision=revision+1,accepted_id=NULL WHERE id=?",[$t['id']]);audit_event($rid,'Task correction required','Independent review requires this task or its evidence dependencies to be revised.','task',$t['id'],$t['state'],'Waiting','Read the targeted correction and submit a new evidence-backed result.','system','LLM.Giving',['review_result_id'=>$reviewId,'previous_result_id'=>$t['accepted_id'],'corrections'=>$issues]);}
}
function wf_findings(string $rid): ?array {
 research($rid);foreach(wf_tasks($rid) as $t)if($t['kind']==='synthesis'&&$t['state']==='Completed'&&$t['visibility']==='public'){
  $result=one("SELECT * FROM workflow_submissions WHERE id=? AND research_id=? AND visibility='public' AND admission='accepted'",[$t['accepted_id'],$rid]);
  if($result&&ra_current($result))return $result;
 }return null;
}

// Use the submitted context, not today's task wording; revoked snapshots stay private.
function wf_assessment_tasks(array $result): array {
 research($result['research_id']);
 try {$snapshot=wf_read_snapshot($result['research_id'],$result['snapshot_id']);}
 catch(HttpError $error){if($error->status===404)return [];throw $error;}
 $package=json_decode($snapshot['package_json'],true);$out=[];
 $public=array_column(array_filter(wf_tasks($result['research_id']),fn($task)=>$task['visibility']==='public'),null,'id');
 foreach($package['workflow']['tasks']??[] as $task)if(isset($public[$task['id']])&&$task['research_id']===$result['research_id'])$out[$task['id']]=$task;
 return $out;
}
// A deterministic reading aid: retain complete sentences from the authored synthesis.
// The full, unchanged result remains visible below; no new scientific claims are generated.
function wf_findings_overview(array $finding): array {
 $paragraphs=preg_split('/\R\s*\R/u',trim($finding['body']),-1,PREG_SPLIT_NO_EMPTY);
 // Prefer an explicitly labeled scientific conclusion; skip editorial bookkeeping.
 $paragraphs=array_values(array_filter($paragraphs,function($p){
  $text=trim(preg_replace('/^#+\s*/m','',$p));
  return !preg_match('/^(?:REVISION\s+\d+|RESPONSE TO (?:INDEPENDENT )?REVIEW|CHANGELOG|CHANGE LOG|CORRECTION (?:RESPONSE|CHECKLIST)|This submission (?:amends|revises|updates))\b/iu',$text) && !preg_match('/^[A-Z\s\d—:–-]+$/u',$text);
 }));
 $summaryIndex=0;
 foreach($paragraphs as $i=>$p){if(preg_match('/^(?:(?:The )?scientific conclusion(?: is unchanged)?|Conclusion|Summary|Bottom line)\s*[:\n]/iu',trim($p))){$summaryIndex=$i;break;}}
 $summary=$paragraphs[$summaryIndex]??$finding['reason'];
 $summary=preg_replace('/^(?:(?:The )?scientific conclusion(?: is unchanged)?|Conclusion|Summary|Bottom line)\s*[:\n]\s*/iu','',trim($summary));
 unset($paragraphs[$summaryIndex]);$takeaways=[];
 foreach($paragraphs as $paragraph){
  $title='';$text=trim($paragraph);
  if(preg_match('/^([^.!?\n]{1,120})\.\s+(.+)$/us',$text,$match)){
   $title=trim(preg_replace('/\s*\([^)]*\)/u','',$match[1]));$text=$match[2];
  }
  $sentences=preg_split('/(?<=[.!?])\s+(?=[A-Z])/u',$text);
  $takeaways[]=['title'=>$title,'text'=>implode(' ',array_slice($sentences,0,2))];
  if(count($takeaways)===6)break;
 }
 return ['summary'=>$summary,'takeaways'=>$takeaways];
}
