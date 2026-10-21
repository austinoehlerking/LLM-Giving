<?php
/** Plan validation, task transitions, evidence acceptance and atomic submission. */
function wf_validate_plan(mixed $tasks,bool $modelsRequested=false,bool $toolRequested=false): array {
    if(!is_array($tasks)||!array_is_list($tasks)||count($tasks)<1||count($tasks)>12)fail(422,'A completed plan needs 1–12 research tasks.');
    $out=[];
    foreach($tasks as $t){
        if(!is_array($t))fail(422,'Every task must be an object.');
        $key=text_field($t,'key',1,40);
        if(!preg_match('/^[a-z][a-z0-9_-]*$/',$key)||isset($out[$key])||in_array($key,['plan','synthesis','review'],true))fail(422,'Use unique research task keys; plan, synthesis and review are reserved.');
        $computational=$t['computational']??false;if(!is_bool($computational))fail(422,'Task computational must be a JSON boolean.');
        if($computational&&!$modelsRequested)fail(422,'Running computational models requires the requester opt-in flag.');
        $buildsTool=$t['builds_tool']??false;if(!is_bool($buildsTool))fail(422,'Task builds_tool must be a JSON boolean.');
        if($buildsTool&&!$toolRequested)fail(422,'Tool build tasks require the Build a research tool project type.');
        $row=['key'=>$key,'computational'=>$computational,'builds_tool'=>$buildsTool];
        foreach(['title'=>[5,180],'objective'=>[15,5000],'deliverable'=>[10,5000],'acceptance'=>[10,5000],'evidence_needs'=>[20,3000],'stopping_rule'=>[20,3000]] as $f=>$bounds)$row[$f]=text_field($t,$f,...$bounds);
        if(count(wf_criteria($row))<1||count(wf_criteria($row))>20)fail(422,'Each task needs 1–20 acceptance criteria, one per line.');
        $deps=$t['dependencies']??[];
        if(!is_array($deps)||!array_is_list($deps)||count($deps)>12)fail(422,'Dependencies must be a list of task keys.');
        foreach($deps as $dep)if(!is_string($dep))fail(422,'Dependency keys must be strings.');
        $row['dependencies']=array_values(array_unique($deps));$out[$key]=$row;
    }
    $seen=[];$visiting=[];
    $visit=function($key)use(&$visit,&$seen,&$visiting,$out){
        if(isset($visiting[$key]))fail(422,'Research tasks must not contain circular dependencies.');
        if(isset($seen[$key]))return;
        if(!isset($out[$key]))fail(422,'Every dependency must belong to this plan.');
        $visiting[$key]=true;foreach($out[$key]['dependencies'] as $d)$visit($d);unset($visiting[$key]);$seen[$key]=true;
    };
    if($modelsRequested&&!array_filter($out,fn($task)=>$task['computational']))fail(422,'This request includes running computational models. Mark at least one planned task computational=true.');
    if($toolRequested&&!array_filter($out,fn($task)=>$task['builds_tool']))fail(422,'A research-tool plan must include at least one builds_tool=true implementation task.');
    foreach(array_keys($out) as $key)$visit($key);
    return array_values($out);
}
function wf_metadata(array $d,array $snapshot,bool $planning): array {
    $m=$d['metadata']??null;if(!is_array($m)||array_is_list($m))fail(422,'Supply model and run metadata.');
    $out=[];foreach(['provider','model','reasoning','tool','run_id','run_at_utc','run_evidence'] as $key)$out[$key]=text_field($m,$key,$key==='run_evidence'?0:1,$key==='run_evidence'?5000:200);
    if(!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',$out['run_at_utc'])||!($date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$out['run_at_utc']))||$date->format('Y-m-d\TH:i:s\Z')!==$out['run_at_utc']||$date->getTimestamp()>time()+300)fail(422,'Run time must be a real UTC timestamp, for example 2026-10-19T16:00:00Z, not a future time.');
    if($planning){
        $ctx=json_decode($snapshot['package_json'],true);$allowed=false;
        foreach($ctx['workflow']['planning_model_policy'] as $p)if($out['model']===$p['model']&&in_array(strtolower($out['reasoning']),$p['efforts'],true)&&
            (strtolower($out['reasoning'])!=='ultra'||strcasecmp($out['tool'],$p['ultra_tool']??'')===0))$allowed=true;
        if(!$allowed)fail(422,'Planning requires an exact model and Max/Ultra tool combination allowed by the context package policy.');
    }
    $ctx=json_decode($snapshot['package_json'],true);rq_enforce($ctx['requester_ai_requirements']??rq_requirements([]),$out);
    $out['provenance']='Self-reported'.($out['run_evidence']!==''?'; supporting run evidence supplied (not authenticated)':'');
    return $out;
}
function wf_request_review(string $rid,string $reason,string $actorName): void {
    research($rid);
    tx(function()use($rid,$reason,$actorName){
        $r=one('SELECT * FROM research WHERE id=? FOR UPDATE',[$rid]);
        if(wf_quarantine($rid))fail(409,'An admin must decide the quarantined packet first.');
        if($r['status']!=='Review Complete')fail(409,'Another review can be requested after the current cycle completes.');
        $p=wf_project($rid);$synthesis=null;foreach(wf_tasks($rid) as $t)if($t['kind']==='synthesis'&&$t['state']==='Completed')$synthesis=$t;
        if(!$synthesis)fail(409,'A completed synthesis is required.');
        $id=wf_new_task($rid,(int)$p['round_no'],'review-'.substr(uid(),0,12),'review','Additional independent verification','Recheck the existing synthesis and the reason for this additional review: '.$reason,'A verification recommendation or evidence-linked corrections.','Use a separate contributor and AI run from the synthesis. Address the stated concern.',[$synthesis['id']]);
        wf_recompute($rid);touch_context($rid);audit_event($rid,'Additional review requested',$reason,'task',$id,'Review Complete','Ready','An independent contributor can take this review.','contributor',$actorName?:'Anonymous volunteer');
    });
}

function wf_recompute(string $rid): void {
    if(wf_quarantine($rid))return;
    $p=wf_project($rid);$tasks=wf_tasks($rid);
    foreach($tasks as $t){
        if($t['state']!=='Waiting')continue;$ready=true;
        foreach(json_decode($t['dependencies'],true) as $dep)if((wf_task($rid,$dep)['state'])!=='Completed')$ready=false;
        if($ready){query("UPDATE workflow_tasks SET state='Ready' WHERE id=?",[$t['id']]);audit_event($rid,'Dependencies satisfied','All prerequisite tasks have accepted results.','task',$t['id'],'Waiting','Ready','Copy the task prompt to begin.');}
    }
    $tasks=wf_tasks($rid);$state='Open for Research';
    foreach($tasks as $t)if($t['kind']==='review'&&$t['state']==='Completed')$state='Review Complete';
    foreach($tasks as $t)if($t['kind']==='review'&&$t['state']==='Ready')$state='Independent Verification';
    foreach($tasks as $t)if($t['kind']==='synthesis'&&$t['state']==='Ready')$state='Synthesis';
    wf_set_state($rid,$state,'Task outcomes determine the next available stage; acceptance is not factual verification.');
}
// Called only inside the parent research lock, after admission.
function wf_apply_result(array $row,array $t): void {
    if((one('SELECT admission FROM workflow_submissions WHERE id=? AND research_id=?',[$row['id'],$row['research_id']])['admission']??'')!=='accepted')fail(409,'Only an accepted packet can apply a task outcome.');
    $rid=$row['research_id'];$tid=$t['id'];$outcome=$row['outcome'];
    $p=wf_project($rid);$plan=json_decode($row['plan_json'],true);$assessment=json_decode($row['metadata'],true);
        if(in_array($outcome,['needs_clarification','blocked'],true)){
            query('UPDATE workflow_projects SET blocker_id=? WHERE research_id=?',[$row['id'],$rid]);
            wf_set_state($rid,$outcome==='blocked'?'Blocked':'Needs Clarification','A task identified an actionable obstacle. See its result for the response needed.');
            mail_notice($rid,'LLM.Giving needs your response',$row['reason']."\n".$row['next_action']."\nUse your saved private management link to respond, or recover it through the site.");
        }elseif($outcome!=='progress'){
            if($outcome==='needs_correction'){
                wf_correct_tasks($rid,$assessment['corrections'],$row['id']);
            }else{
                query("UPDATE workflow_tasks SET state='Completed',accepted_id=? WHERE id=?",[$row['id'],$tid]);
                if($plan){
                    $ids=[];foreach($plan as $n)$ids[$n['key']]=wf_new_task($rid,(int)$p['round_no'],$n['key'],'research',$n['title'],$n['objective'],$n['deliverable']."\nEvidence needs: ".$n['evidence_needs']."\nStopping rule: ".$n['stopping_rule'],$n['acceptance'],[],(bool)$n['computational'],(bool)$n['builds_tool']);
                    foreach($plan as $n){$deps=array_map(fn($key)=>$ids[$key],$n['dependencies']);query('UPDATE workflow_tasks SET dependencies=?,state=? WHERE id=?',[json_encode($deps),$deps?'Waiting':'Ready',$ids[$n['key']]]);}
                    $syn=wf_new_task($rid,(int)$p['round_no'],'synthesis','synthesis','Synthesize the research','Reconcile the task results against the full research objective, including null findings and disagreements.','An evidence-linked synthesis with confidence, gaps and recommended next steps.','No unsupported global conclusions. Trace every substantive claim to task evidence.',array_values($ids));
                    wf_new_task($rid,(int)$p['round_no'],'review','review','Independently verify the synthesis','Audit the synthesis against its sources and original objective using a separate contributor and AI run.','A verification recommendation or specific corrections with evidence.','Check citations, contradictory findings, limitations and reproducibility. Do not infer certainty from workflow completion.',[$syn]);
                }
            }
            wf_recompute($rid);
        }

}
function wf_submit(string $rid,string $tid,array $d,string $actor,bool $checkOnly=false,string $method='unknown',?array $consent=null): string {
    research($rid);$hash=hash('sha256',json_encode($d,JSON_THROW_ON_ERROR));
    try{return tx(function()use($rid,$tid,$d,$actor,$hash,$checkOnly,$method,$consent){
        $r=one('SELECT * FROM research WHERE id=? FOR UPDATE',[$rid])??fail(404,'Research not found.');
        if($r['moderation']!=='Approved'||$r['status']==='Withdrawn')fail(404,'Research not found.');
        $nonce=text_field($d,'submission_key',32,32);if(!ctype_xdigit($nonce))fail(422,'Submission key must be 32 hexadecimal characters.');
        $existing=one('SELECT id,payload_hash,actor_hash FROM workflow_submissions WHERE research_id=? AND nonce=?',[$rid,$nonce]);
        if($existing){if(!hash_equals($existing['actor_hash'],$actor)||!hash_equals($existing['payload_hash'],$hash))fail(409,'Submission key already used for a different result.');return $existing['id'];}
        if(in_array($r['status'],['Needs Clarification','Blocked','Paused','Archived — Inactive','Solved','Review Complete'],true))fail(409,'Research is not accepting task results in its current state.');
        if(wf_quarantine($rid))fail(409,'Research is awaiting an admin decision on a quarantined packet. No task results can advance yet.');
        $p=wf_project($rid)??fail(422,'Topic must first be approved.');
        $t=wf_task($rid,$tid);
        if($t['visibility']!=='public'||$t['round_no']!=$p['round_no']||$t['state']!=='Ready'||(int)($d['task_revision']??0)!=(int)$t['revision'])fail(409,'This task changed or is not ready. Refresh its prompt before submitting.');
        $s=wf_read_snapshot($rid,text_field($d,'context_id',32,32));
        if($s['round_no']!=$p['round_no'])fail(409,'This context belongs to an earlier planning round.');
        $source=json_decode($s['package_json'],true);
        $matching=array_values(array_filter($source['workflow']['tasks'],fn($x)=>$x['id']===$tid&&$x['revision']==$t['revision']&&$x['state']==='Ready'));
        if(!$matching)fail(409,'The context package did not contain this ready task revision.');
        $code=$d['handoff_code']??null;
        if(!is_string($code)||!hash_equals(wf_handoff_code($t,$s,$nonce),$code))fail(422,'Handoff code does not match this task, revision, context and submission key. Copy a fresh AI handoff and ask your AI to reassess against it; preserve its identifiers exactly.');
        if(empty($d['terms']))fail(422,'Accept the public-content and original-work terms.');
        $outcome=text_field($d,'outcome',0,40);
        $allowed=['progress','needs_clarification','blocked'];
        $allowed=array_merge($allowed,$t['kind']==='review'?['verified','needs_correction']:($t['kind']==='planning'?['completed']:['completed','inconclusive']));
        if(!in_array($outcome,$allowed,true))fail(422,'The AI must select one of these outcomes for this task: '.implode(', ',$allowed).'.');
        $snapshotRequirements=json_decode($s['package_json'],true)['requester_ai_requirements']??rq_requirements([]);
        if($snapshotRequirements!==rq_requirements(research($rid)))fail(409,'Requester AI requirements changed. Copy a fresh handoff and use a qualifying AI run.');
        $meta=wf_metadata($d,$s,$t['kind']==='planning');
        if(!in_array($method,['unknown','manual','ai_browser'],true))throw new LogicException('Invalid submission method.');
        if($consent){if($consent['terms_version']!==terms_version())fail(409,'Fresh volunteer authorization is required for changed terms.');$meta['authorization']=$consent;}
        $meta['contribution_license']=RESEARCH_LICENSE;$meta['terms_version']=terms_version(record: !$checkOnly);
        if(rt_is_tool($r)){
            if(!$checkOnly&&empty($d['tool_terms']))fail(422,'Accept the separate software terms for original tool source and tests.');
            $meta['tool_software_license']=RT_LICENSE;$meta['tool_terms_version']=RT_TERMS;
        }
        $row=['id'=>uid(),'research_id'=>$rid,'task_id'=>$tid,'snapshot_id'=>$s['id'],'nonce'=>$nonce,'payload_hash'=>$hash,'actor_hash'=>$actor,'outcome'=>$outcome,'display_name'=>text_field($d,'display_name',0,120),'created_at'=>now(),'metadata'=>json_encode($meta),'license'=>RESEARCH_LICENSE,'plan_json'=>'[]','submission_method'=>$method==='unknown'?null:$method];
        foreach(['reason'=>[10,500],'body'=>[30,100000],'methodology'=>[10,30000],'sources'=>[5,30000],'limitations'=>[5,10000],'next_action'=>[10,5000]] as $field=>$bounds)$row[$field]=text_field($d,$field,...$bounds);
        $plan=[];
        if($t['kind']==='planning'&&$outcome==='completed'){$plan=wf_validate_plan($d['tasks']??null,(bool)$r['computational_models'],rt_is_tool($r));$row['plan_json']=json_encode($plan);}
        if($t['kind']==='review'){
            $deps=json_decode($t['dependencies'],true);$synthesis=wf_task($rid,$deps[0]);
            $original=one('SELECT actor_hash,metadata FROM workflow_submissions WHERE id=? AND visibility=\'public\'',[$synthesis['accepted_id']])??fail(409,'Synthesis is not available for review.');
            if(hash_equals($original['actor_hash'],$actor)||json_decode($original['metadata'],true)['run_id']===$meta['run_id'])fail(422,'Independent review requires a different contributor browser and a distinct AI run. Identity remains unverified.');
        }
        $evidence=wf_validate_evidence($t,$d,$row);
        $validationPacket=$d;
        if(!is_array($d['corrections']??null)||!array_is_list($d['corrections']))fail(422,'Corrections must be a list.');
        $validationPacket['corrections']=array_merge($d['corrections'],$evidence['corrections']);
        $assessment=wf_acceptance($t,$validationPacket,$row);
        $assessment['evidence']=$evidence['evidence'];
        $influence=wf_influence($d);
        $meta['human_influence']=$influence;
        if($consent)$meta['human_influence']['volunteer_declaration_scope']=$consent['disclosure_scope'];
        $meta['task_revision']=(int)$t['revision'];
        $issued=one('SELECT id,sha256,created_at FROM workflow_handoffs WHERE research_id=? AND nonce=? AND task_id=? AND snapshot_id=?',[$rid,$nonce,$tid,$s['id']]);
        $meta['verification']=['task_signature'=>'valid','conversation_integrity'=>'self-reported','issued_handoff'=>$issued];
        $row['metadata']=json_encode($meta+$assessment);
        $row['admission']=$influence['review_status']==='needed'?'quarantined':'accepted';
        if($checkOnly)return '';

        insert('workflow_submissions',$row);
        if($row['admission']==='quarantined'){
            query("UPDATE workflow_tasks SET state='Awaiting Admin' WHERE id=?",[$tid]);
            touch_context($rid);
            audit_event($rid,'Packet quarantined','Additional human input or uncertainty requires an admin decision. No task outcome has been applied.','submission',$row['id'],'Received','Quarantined','Wait for an administrator to approve or reject this packet.','contributor',$row['display_name']?:'Anonymous volunteer',['submission_method'=>$method]);
            return $row['id'];
        }
        wf_apply_result($row,$t);
        touch_context($rid);
        audit_event($rid,'Task result submitted',$row['reason'],'submission',$row['id'],$t['state'],$outcome,$row['next_action'],'contributor',$row['display_name']?:'Anonymous volunteer',$meta+['submission_method'=>$method,'task_id'=>$tid,'context_id'=>$s['id'],'task_revision'=>$t['revision']]);
        return $row['id'];
    });}catch(HttpError $e){
        if($checkOnly)throw $e;
        // Retain invalid attempts privately, without CSRF, private management tokens or request headers.
        unset($d['terms']);$payload=json_encode($d,JSON_INVALID_UTF8_SUBSTITUTE);
        insert('workflow_attempts',['id'=>uid(),'research_id'=>$rid,'task_id'=>$tid,'payload'=>substr($payload,0,200000),'reason'=>substr($e->getMessage(),0,500),'created_at'=>now()]);
        audit_event($rid,'Submission not accepted',$e->getMessage(),'task',$tid,'','No change','Return the error to your AI; paste its complete revised packet without editing it.','contributor','Anonymous volunteer',[],'private');
        throw $e;
    }
}
