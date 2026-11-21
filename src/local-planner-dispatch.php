<?php
/** Admin dispatch uses a bounded snapshot of the existing planner queue. */
const LP_RUN_SIZE=20;
const LP_HEARTBEAT_TTL=60;

function lp_control_lock(): array {
    query("INSERT INTO settings(name,value) VALUES ('local_planner_run','{}') ON DUPLICATE KEY UPDATE name=VALUES(name)");
    $row=one("SELECT value FROM settings WHERE name='local_planner_run' FOR UPDATE");
    return json_decode($row['value'],true,32,JSON_THROW_ON_ERROR);
}

function lp_run_current(array $run): bool {
    return in_array($run['state']??'', ['queued','running'],true)&&($run['expires']??0)>time();
}

function lp_dispatch_status(): array {
    require_admin();
    $configured=lp_enabled()&&preg_match('/^[a-f0-9]{64}$/D',env('LOCAL_PLANNER_TOKEN_SHA256'));
    $heartbeat=json_decode(setting('local_planner_connection','{}'),true,16,JSON_THROW_ON_ERROR);
    $run=json_decode(setting('local_planner_run','{}'),true,32,JSON_THROW_ON_ERROR);
    $active=lp_run_current($run);
    $fresh=$configured&&($heartbeat['at']??0)>time()-LP_HEARTBEAT_TTL;
    $connection=$fresh?($heartbeat['signed_in']?'connected':'sign-in-required'):'disconnected';
    $pending=$configured?count(lp_candidates(limit:LP_RUN_SIZE+1)):0;
    $failed=$configured?(int)one("SELECT COUNT(*) n FROM local_planner_jobs j JOIN research r ON r.id=j.research_id AND r.context_version=j.context_version
        WHERE r.moderation='Approved' AND r.status='Needs Research Plan' AND (j.state='failed' OR (j.state='running' AND j.attempts>=3 AND j.expires_at<=?))",[now()])['n']:0;
    return ['configured'=>(bool)$configured,'connection'=>$connection,'pending'=>min($pending,LP_RUN_SIZE),'more'=>$pending>LP_RUN_SIZE,
        'failed'=>$failed,'active'=>$active,'run'=>$active?$run['state']:($run['state']??null)];
}

function lp_dispatch_start(): string {
    require_admin();
    if(!lp_enabled()||setting('maintenance','0')==='1')fail(409,'Local planning is unavailable.');
    lp_token_hash();
    return tx(function(){
        $current=lp_control_lock();
        if(lp_run_current($current))return 'A planning run is already queued.';
        $ids=array_column(lp_candidates(limit:LP_RUN_SIZE),'id');
        if(!$ids)return 'No approved requests are ready for planning.';
        $run=['id'=>uid(),'state'=>'queued','expires'=>time()+6*3600,'ids'=>$ids];
        set_setting('local_planner_run',json_encode($run,JSON_THROW_ON_ERROR));
        audit_event(null,'Local planning run queued','An administrator started planning for approved requests.','configuration','local-planner-run','','Queued','','administrator','Administrator',['run_id'=>$run['id'],'count'=>count($ids)],'private');
        return 'Planning queued for '.count($ids).' '.(count($ids)===1?'request.':'requests.');
    });
}

function lp_dispatch_heartbeat(mixed $signedIn): array {
    if(!is_bool($signedIn))fail(422,'Report whether the local Codex CLI is signed in with ChatGPT.');
    $at=time();
    set_setting('local_planner_connection',json_encode(['at'=>$at,'signed_in'=>$signedIn],JSON_THROW_ON_ERROR));
    $run=json_decode(setting('local_planner_run','{}'),true,32,JSON_THROW_ON_ERROR);
    return ['run_id'=>$signedIn&&lp_run_current($run)?$run['id']:null];
}

function lp_dispatch_claim(mixed $id): ?array {
    if(!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id))fail(422,'Supply the issued planning run ID.');
    return tx(function()use($id){
        $run=lp_control_lock();
        if(!lp_run_current($run)||!hash_equals($run['id'],$id))return null;
        $placeholders=implode(',',array_fill(0,count($run['ids']),'?'));
        if(one("SELECT j.research_id FROM local_planner_jobs j JOIN research r ON r.id=j.research_id AND r.context_version=j.context_version
            WHERE j.research_id IN ($placeholders) AND j.state='running' AND j.expires_at>? LIMIT 1",[...$run['ids'],now()]))return null;
        $job=lp_claim($run['ids']);
        $state=$job?'running':'complete';
        if($run['state']!==$state){
            $run['state']=$state;
            set_setting('local_planner_run',json_encode($run,JSON_THROW_ON_ERROR));
        }
        return $job;
    });
}
