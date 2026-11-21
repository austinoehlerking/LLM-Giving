<?php
require_once __DIR__.'/local-planner-dispatch.php';
/** The site grants a bounded plan lease. Inference runs exclusively on the local operator's computer. */
function lp_enabled(bool $current=false): bool {
    if(!$current)return setting('local_planner_enabled','0')==='1';
    lp_mode_lock(false);
    // The workflow may already have a repeatable-read snapshot. Check the protected mode
    // on an autocommit connection without locking the changed settings row in that snapshot.
    static $reader;
    $reader??=db_connect();
    return $reader->query("SELECT value FROM settings WHERE name='local_planner_enabled'")->fetchColumn()==='1';
}

function lp_mode_lock(bool $exclusive): void {
    if(!db()->inTransaction())throw new LogicException('Local planning mode requires the workflow transaction.');
    $sql="SELECT name FROM settings WHERE name='local_planner_mode_lock'";
    if(!one($sql.($exclusive?' FOR UPDATE':' LOCK IN SHARE MODE')))throw new LogicException('The local planner migration has not been applied.');
}

function lp_token_hash(): string {
    $hash=env('LOCAL_PLANNER_TOKEN_SHA256');
    if(!preg_match('/^[a-f0-9]{64}$/D',$hash))fail(503,'The local planner credential is not configured.');
    return $hash;
}

function lp_enable(): void {
    lp_token_hash();
    // Commit the mode before taking any research locks. Planning creation and new reservations
    // read the current mode while holding their research lock, so neither lock order can cycle.
    if(db()->inTransaction())throw new LogicException('Local planner activation owns its mode transaction.');
    tx(function(){
        lp_mode_lock(true);
        if(lp_enabled())return;
        set_setting('local_planner_enabled','1');
        audit_event(null,'Local planner enabled','New plans are prepared on the owner’s local Codex runner.','configuration','local-planner','','Enabled','','system','LLM.Giving',[],'private');
    });

    $legacy=all("SELECT t.research_id FROM workflow_tasks t JOIN workflow_projects p ON p.research_id=t.research_id AND p.round_no=t.round_no WHERE t.kind='planning' AND t.state='Ready' AND t.visibility='public' ORDER BY t.research_id");
    foreach($legacy as ['research_id'=>$rid]){
        tx(function()use($rid){
            $research=one('SELECT * FROM research WHERE id=? FOR UPDATE',[$rid]);
            $task=$research?lp_task($research,true):null;
            if($task&&$task['visibility']==='public')lp_adopt_plan($task);
        });
    }
}

function lp_pending(array $research): bool {
    return $research['status']==='Needs Research Plan'&&lp_enabled();
}

function lp_configuration(array $research): ?string {
    $requirements=rq_requirements($research);
    $policy=array_column(wf_policy(),null,'model');
    foreach(['gpt-6-astra','gpt-6.1-sol'] as $model){
        if(in_array('max',$policy[$model]['efforts']??[],true)&&rq_matches($requirements,$model,'max','','Codex'))return $model;
    }
    return null;
}

function lp_task(array $research,bool $includeLegacy=false): ?array {
    if($research['moderation']!=='Approved'||!lp_pending($research)||wf_quarantine($research['id']))return null;
    $visibility=$includeLegacy?'':" AND t.visibility='private'";
    return one("SELECT t.* FROM workflow_tasks t JOIN workflow_projects p ON p.research_id=t.research_id AND p.round_no=t.round_no WHERE t.research_id=? AND t.kind='planning' AND t.state='Ready'".$visibility,[$research['id']]);
}

/** Caller holds the research lock. A legacy volunteer can finish an existing reservation. */
function lp_adopt_plan(array $task): bool {
    $rid=$task['research_id'];
    if(wr_live($rid,$task['id']))return false;
    query("UPDATE workflow_tasks SET visibility='private' WHERE id=?",[$task['id']]);
    query('UPDATE workflow_snapshots SET public_allowed=0 WHERE research_id=? AND round_no=?',[$rid,$task['round_no']]);
    touch_context($rid,false);
    return true;
}

function lp_candidates(?array $scope=null,int $limit=40): array {
    if($scope===[])return [];
    $scoped=$scope===null?'':' AND r.id IN ('.implode(',',array_fill(0,count($scope),'?')).')';
    return all("SELECT r.id FROM research r JOIN workflow_projects p ON p.research_id=r.id
        JOIN workflow_tasks t ON t.research_id=r.id AND t.round_no=p.round_no AND t.kind='planning' AND t.state='Ready'
        LEFT JOIN local_planner_jobs j ON j.research_id=r.id AND j.task_id=t.id AND j.context_version=r.context_version
        WHERE r.moderation='Approved' AND r.status='Needs Research Plan'
        AND NOT EXISTS (SELECT 1 FROM workflow_submissions s WHERE s.research_id=r.id AND s.visibility='public' AND s.admission='quarantined')
        AND (t.visibility='private' OR NOT EXISTS (SELECT 1 FROM task_reservations v WHERE v.task_id=t.id AND v.task_revision=t.revision AND v.ended_at IS NULL AND v.expires_at>?))
        AND (j.research_id IS NULL OR (j.state='running' AND j.expires_at<=? AND j.attempts<3)) " . $scoped . " ORDER BY r.created_at,r.id LIMIT ".$limit,[wr_stamp(),now(),...($scope??[])]);
}

function lp_claim(?array $scope=null): ?array {
    $candidates=lp_candidates($scope);
    foreach($candidates as ['id'=>$rid]){
        $job=tx(function()use($rid){
            $research=one('SELECT * FROM research WHERE id=? FOR UPDATE',[$rid]);
            if(!$research||!($task=lp_task($research,true)))return null;
            // Also reconcile public rows left by an interrupted activation or a legacy reservation.
            if($task['visibility']==='public'){
                if(!lp_adopt_plan($task))return null;
                $research=research($rid,false);
            }
            $backgroundPublic=true;
            try{rf_assert_background($rid);}catch(HttpError $error){if($error->status!==404)throw $error;$backgroundPublic=false;}
            $old=one('SELECT * FROM local_planner_jobs WHERE research_id=?',[$rid]);
            $current=$old&&$old['task_id']===$task['id']&&(int)$old['context_version']===(int)$research['context_version'];
            if($current&&($old['state']!=='running'||$old['expires_at']>now()||(int)$old['attempts']>=3))return null;
            $model=$backgroundPublic?lp_configuration($research):null;
            $failure=$backgroundPublic?'configuration':'background';
            $lease=bin2hex(random_bytes(32));
            query('DELETE FROM local_planner_jobs WHERE research_id=?',[$rid]);
            insert('local_planner_jobs',['research_id'=>$rid,'task_id'=>$task['id'],'context_version'=>$research['context_version'],
                'state'=>$model?'running':'failed','lease_hash'=>hash('sha256',$lease),'expires_at'=>gmdate('Y-m-d H:i:s',time()+900),
                'attempts'=>$current?(int)$old['attempts']+1:1,'model'=>$model??'','failure'=>$model?'':$failure,'updated_at'=>now()]);
            if(!$model)return null;
            // Only the approved public package is shared. Never include the requester email, capabilities, or private submissions.
            $context=context_data($rid);
            return ['research_id'=>$rid,'lease'=>$lease,'model'=>$model,'reasoning'=>'max','context'=>$context];
        });
        if($job)return $job;
    }
    return null;
}

function lp_finish(array $input,bool $failed): array {
    $rid=$input['research_id']??'';
    $lease=$input['lease']??'';
    if(!is_string($rid)||!preg_match('/^[a-f0-9]{32}$/D',$rid)||!is_string($lease)||!preg_match('/^[a-f0-9]{64}$/D',$lease))fail(422,'Supply the assigned research ID and lease.');
    return tx(function()use($rid,$lease,$input,$failed){
        $research=one('SELECT * FROM research WHERE id=? FOR UPDATE',[$rid]);
        $job=one('SELECT * FROM local_planner_jobs WHERE research_id=?',[$rid]);
        if(!$research||!$job||!hash_equals($job['lease_hash'],hash('sha256',$lease)))fail(409,'This plan lease is no longer current.');
        $hash=$failed?null:hash('sha256',json_encode($input,JSON_THROW_ON_ERROR));
        if(!$failed&&$job['state']==='complete'&&hash_equals($job['result_hash'],$hash))return ['recorded'=>true];
        if($job['state']!=='running'||$job['expires_at']<=now()||$job['context_version']!=$research['context_version']
            ||!($task=lp_task($research))||$task['id']!==$job['task_id'])fail(409,'The request, plan lease, or workflow changed.');
        rf_assert_background($rid);
        if($failed){
            $code=$input['failure']??'';
            if(!in_array($code,['runner','validation','context_size'],true))fail(422,'Unknown planning failure.');
            query("UPDATE local_planner_jobs SET state='failed',failure=?,updated_at=? WHERE research_id=?",[$code,now(),$rid]);
            return ['recorded'=>false];
        }
        if(lp_configuration($research)!==$job['model'])fail(409,'The planning model requirements changed.');
        $runId=$input['run_id']??'';
        if(!is_string($runId)||!preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/D',$runId))fail(422,'Supply the local Codex run ID.');
        $calculation=calculation_policy($research);
        $plan=wf_validate_plan($input['tasks']??null,$calculation['allowed'],rt_is_tool($research),$calculation['required']);
        query("UPDATE workflow_tasks SET state='Completed' WHERE id=?",[$task['id']]);
        wf_install_plan($rid,(int)$task['round_no'],$plan);
        wf_recompute($rid);
        touch_context($rid);
        query("UPDATE local_planner_jobs SET state='complete',result_hash=?,run_id=?,updated_at=? WHERE research_id=?",[$hash,$runId,now(),$rid]);
        audit_event($rid,'Research plan prepared','Local Codex prepared the task plan. The site validated the task structure.','research',$rid,'Preparing plan','Open for Research','Ready research tasks can be claimed.','system','LLM.Giving',['model'=>$job['model'],'reasoning'=>'max','configuration'=>'Local runner launch']);
        audit_event($rid,'Local planning run recorded','Plan installed from the leased, current context.','task',$task['id'],'','Completed','','system','LLM.Giving',[
            'context_version'=>(int)$job['context_version'],'model'=>$job['model'],'reasoning'=>'max','run_id'=>$runId], 'private');
        return ['recorded'=>true];
    });
}

function lp_retry(string $rid): void {
    require_admin();
    tx(function()use($rid){
        $research=one('SELECT * FROM research WHERE id=? FOR UPDATE',[$rid])??fail(404,'Research not found.');
        if(!lp_task($research))fail(409,'This request has no active local planning job.');
        $job=one('SELECT * FROM local_planner_jobs WHERE research_id=?',[$rid]);
        if($job&&$job['state']==='running'&&$job['expires_at']>now())fail(409,'A local run already holds this plan.');
        query('DELETE FROM local_planner_jobs WHERE research_id=?',[$rid]);
    });
}

function lp_http(string $method): void {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex');
    try{
        if($method!=='POST')fail(405,'Use POST.');
        if(!empty($_SERVER['QUERY_STRING'])||!empty($_SERVER['HTTP_ORIGIN'])||!empty($_SERVER['HTTP_COOKIE']))fail(403,'Use the direct local runner request.');
        if(!lp_enabled()||setting('maintenance','0')==='1')fail(503,'Local planning is unavailable.');
        if(env('APP_ENV')==='production'&&parse_url(env('APP_URL'),PHP_URL_SCHEME)!=='https')fail(503,'The planner requires HTTPS.');
        $auth=$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'';
        throttle('local-planner',120,60);
        if(!preg_match('/^Bearer ([a-f0-9]{64})$/D',$auth,$match)||!hash_equals(lp_token_hash(),hash('sha256',$match[1])))fail(401,'Invalid local planner credential.');
        if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')fail(415,'Send application/json.');
        $body=file_get_contents('php://input',false,null,0,524289);
        if(strlen($body)>524288)fail(413,'Planner request is too large.');
        try{$input=json_decode($body,true,32,JSON_THROW_ON_ERROR);}catch(JsonException){fail(422,'Send a valid JSON object.');}
        if(!is_array($input)||array_is_list($input))fail(422,'Send a JSON object.');
        $result=match($input['action']??null){
            'connection'=>lp_dispatch_heartbeat($input['signed_in']??null),
            'claim'=>['job'=>array_key_exists('run_id',$input)?lp_dispatch_claim($input['run_id']):lp_claim()],
            'finish'=>lp_finish($input,false),
            'fail'=>lp_finish($input,true),
            default=>fail(422,'Unknown planning action.')
        };
        echo json_encode($result,JSON_THROW_ON_ERROR);
    }catch(Throwable $error){
        $known=$error instanceof HttpError;
        http_response_code($known?$error->status:500);
        if(!$known)error_log('Local planning request failed: '.get_class($error));
        echo json_encode(['error'=>$known?$error->getMessage():'The planning service could not complete the request.']);
    }
}
