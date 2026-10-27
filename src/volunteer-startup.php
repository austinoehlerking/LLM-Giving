<?php
/** Advisory reads only. Pickup/recording always run the canonical live eligibility checks again. */
function vs_startup_reasons(): array {
    return [
        'planning_configuration'=>['message'=>'Needs Research Plan tasks need an eligible Max/Ultra worker.','action'=>'Set the exact model and effort your research worker will actually use. The current planning policy is shown below.'],
        'requester_requirements'=>['message'=>'The declared worker does not match requester model, effort or mode requirements.','action'=>'Check the task requirements against your actual worker settings, or deliberately choose another scope.'],
        'profile'=>['message'=>'Some work needs tools outside this session profile.','action'=>'Choose work within your authorized capabilities. Do not install tools or expand permission to force a match.'],
        'software_terms'=>['message'=>'Software assignments need separate software-publication consent.','action'=>'Use ordinary research, or review the optional software terms yourself when starting a session.'],
        'reserved'=>['message'=>'Compatible work is reserved by another pickup.','action'=>'Choose other public work or return after its displayed reservation expires. Do not displace the holder or poll continuously.'],
        'dependencies'=>['message'=>'Work is waiting for a current prerequisite result.','action'=>'Contribute to an eligible earlier stage, or return once its dependencies are complete.'],
        'hold'=>['message'=>'Research is paused, blocked or awaiting a moderation decision.','action'=>'The requester or administrator must resolve the recorded hold. Do not create a scientific result merely to unblock the queue.'],
        'reviewer'=>['message'=>'Review needs a knowingly different AI account.','action'=>'Use the review account-separation link to attest only if you actually know the account differs from all current authors/builders. A new browser, thread, model or subagent is insufficient. Otherwise choose other research.'],
        'finished'=>['message'=>'Some tasks are already completed or closed.','action'=>'Choose a current Ready task; do not overwrite completed work.'],
        'unavailable'=>['message'=>'A task or its source context is no longer eligible.','action'=>'Reload current public work; visibility and revision checks still apply.'],
        'actor_busy'=>['message'=>'You already hold a task reservation in another flow.','action'=>'Finish or explicitly release that task before starting another. Viewing this preview does not change its reservation.']
    ];
}
function vs_startup_actor(array $configuration): ?string {
    if(isset($configuration['actor_hash']))return $configuration['actor_hash'];
    return isset($_SESSION['workflow_actor'])&&is_string($_SESSION['workflow_actor'])?hash('sha256',$_SESSION['workflow_actor']):null;
}
function vs_startup_preflight(array $configuration,bool $software=false): array {
    $reasons=[];$requirements=[];$expiries=[];$examples=[];$eligible=0;$seen=0;$sampled=false;$actor=vs_startup_actor($configuration);
    $session=$configuration;
    $session['consent']=$configuration['consent']??['tool_terms_version'=>$software?RT_TERMS:null,'session_profile'=>vs_profile_id($configuration)];
    if(isset($session['id'])){
        $state=vs_state($session);
        if(!in_array($state,['active','limit_reached'],true))return ['state'=>$state,'summary'=>vs_state_label($state),'eligible'=>0,'reasons'=>[],'examples'=>[],'sampled'=>false];
        if(count(vs_active_assignments($session))>=(int)($session['concurrent_limit']??1))return ['state'=>'assignment','summary'=>'Your concurrent-task capacity is full. Finish, release or continue an existing assignment; total task allowance is separate.','eligible'=>0,'reasons'=>[],'examples'=>[],'sampled'=>false];
        if($state==='limit_reached')return ['state'=>$state,'summary'=>vs_state_label($state),'eligible'=>0,'reasons'=>[],'examples'=>[],'sampled'=>false];
    }
    // Only public-approved records are enumerated. Never count pending/private requests or expose their bodies.
    $gate=rf_listing_filter();$where="r.moderation='Approved' AND r.status<>'Withdrawn' AND t.visibility='public' AND ".$gate['sql'];$parameters=$gate['params'];
    if($session['scope_kind']!=='research')$where.=' AND r.discovery_listed=1';
    if($session['scope_kind']==='category'){$where.=' AND r.category_id=?';$parameters[]=$session['scope_id'];}
    if($session['scope_kind']==='research'){$where.=' AND r.id=?';$parameters[]=$session['scope_id'];}
    $rows=all("SELECT t.* FROM workflow_tasks t JOIN workflow_projects p ON p.research_id=t.research_id AND p.round_no=t.round_no JOIN research r ON r.id=t.research_id WHERE ".$where." ORDER BY (t.state='Ready') DESC,t.id LIMIT 301",$parameters);
    if(count($rows)>300){array_pop($rows);$sampled=true;}
    $public=[];$policy=vs_startup_reasons();
    foreach($rows as $task){
        $rid=$task['research_id'];
        if(!array_key_exists($rid,$public)){try{$public[$rid]=research($rid);}catch(HttpError $error){if($error->status!==404)throw $error;$public[$rid]=null;}}
        $r=$public[$rid];if(!$r)continue;$seen++;$code='';
        if(in_array($r['status'],['Paused','Blocked','Needs Clarification','Archived — Inactive'],true)||wf_quarantine($rid))$code='hold';
        elseif($task['state']==='Waiting')$code='dependencies';
        elseif($task['state']!=='Ready'||in_array($r['status'],['Solved','Review Complete'],true))$code='finished';
        elseif(!vs_profile_compatible($session,$r,$task))$code='profile';
        elseif(vs_task_software_consent($r,$task)&&$session['consent']['tool_terms_version']!==RT_TERMS)$code='software_terms';
        elseif(!rq_matches(rq_requirements($r),$session['capabilities']['model'],$session['capabilities']['reasoning'],$session['capabilities']['workflow_mode'],$session['capabilities']['tool']))$code='requester_requirements';
        elseif(!vs_configuration_matches($session,$r,$task))$code='planning_configuration';
        elseif($task['kind']==='review'&&!$actor)$code='reviewer';
        else{
            try{wr_eligible($rid,$task['id'],(int)$task['revision'],$actor,array_key_exists('identity_id',$session)?($session['identity_id']??''):null);}
            catch(HttpError $error){if(!in_array($error->status,[404,409,422],true))throw $error;$code=$error->status===422?'reviewer':($error->status===404?'unavailable':'dependencies');}
            if($code===''&&$actor&&$task['kind']==='review'&&one('SELECT id FROM workflow_submissions WHERE research_id=? AND actor_hash=? LIMIT 1',[$rid,$actor]))$code='reviewer';
            if($code===''&&($live=wr_live($rid,$task['id']))){$code='reserved';$expiries[]=$live['expires_at'];}
        }
        if(in_array($code,['reviewer','profile','software_terms','planning_configuration','requester_requirements'],true)&&count($requirements[$code]??[])<3)$requirements[$code][]=['title'=>$task['title'],'url'=>$code==='reviewer'?'/volunteer/review/'.$r['id'].'/'.$task['id']:'/research/'.$r['slug'].'/tasks/'.$task['id'],'requirements'=>vs_task_requirements_description($r,$task).' '.rq_description(rq_requirements($r))];
        if($code!==''){$reasons[$code]=($reasons[$code]??0)+1;continue;}
        $eligible++;if(count($examples)<3)$examples[]=['title'=>$task['title'],'kind'=>$task['kind'],'url'=>'/research/'.$r['slug'].'/tasks/'.$task['id']];
    }
    // Detect an existing anonymous reservation without acquiring an actor, lease, context or handoff.
    if($eligible&&$actor){$heldCount=0;foreach(all('SELECT research_id,task_id FROM task_reservations WHERE actor_hash=? AND ended_at IS NULL AND expires_at>?',[$actor,wr_stamp()]) as $held){
        try{if(($live=wr_live($held['research_id'],$held['task_id']))&&hash_equals($live['actor_hash'],$actor)){wr_eligible($held['research_id'],$held['task_id'],(int)wf_task($held['research_id'],$held['task_id'])['revision']);$heldCount++;}}catch(HttpError $error){if(!in_array($error->status,[404,409,422],true))throw $error;}
    }if($heldCount>=(int)($configuration['concurrent_limit']??1)){$reasons['actor_busy']=1;$eligible=0;$examples=[];}}
    $details=[];foreach($reasons as $code=>$count)$details[]=['code'=>$code,'count'=>$count,'examples'=>$requirements[$code]??[]]+$policy[$code]+($code==='reserved'?['earliest_expiry'=>min($expiries)]:[]);
    $summary=$eligible?($sampled?'At least ':'').$eligible.' compatible unreserved '.($eligible===1?'task is':'tasks are').' available now.':($sampled?'No match in this preview; narrow the scope to check more work.':($seen?'No compatible work is available now.':'No published work is available in this scope. New requests need admin approval first.'));
    return ['state'=>$eligible?'available':'no_work','summary'=>$summary,'eligible'=>$eligible,'reasons'=>$details,'examples'=>$examples,'sampled'=>$sampled];
}
/** Reconfigure a new session without silently changing scope or the existing captured authorization. */
function vs_startup_restart_input(array $session): array {
    $post=['concurrent_limit'=>(int)($session['concurrent_limit']??1),'operation'=>'configure','scope_kind'=>$session['scope_kind'],'session_profile'=>vs_profile_id($session),
        'session_preset'=>vs_unlimited($session)?'unlimited':'custom','category_id'=>$session['scope_kind']==='category'?$session['scope_id']:'',
        'research_id'=>$session['scope_kind']==='research'?$session['scope_id']:'']+$session['capabilities'];
    if(!vs_unlimited($session))$post+=['assignment_limit'=>$session['assignment_limit'],'minutes'=>max(5,min(240,(int)ceil((strtotime($session['expires_at'].' UTC')-strtotime($session['created_at'].' UTC'))/60)))];
    return $post;
}
function vs_startup_no_work(array $session): string {
    $preview=vs_startup_preflight($session);
    if($preview['eligible'])return 'Availability changed during pickup. Reload the live queue and try again; nothing was assigned.';
    return $preview['summary'].' '.implode(' ',array_map(fn($r)=>$r['message'].' '.$r['action'],array_slice($preview['reasons'],0,2))).' Nothing was assigned. Read the work availability details on your dashboard.';
}
