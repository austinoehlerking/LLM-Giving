<?php
/** Shared anonymous task leases. Actor arguments come only from trusted sessions/grants. */
const WR_SECONDS=3600;
function wr_instructions(): string {
    return "TASK RESERVATION CONTRACT\nA task pickup reserves exactly its current revision for 60 server-timed minutes. Public prompt copies are reference only and never acquire or renew. Authorized task handoffs acquire for the volunteer; the private AI return capability preserves that actor. An expired lease must be explicitly reacquired; another holder cannot be displaced. Only an explicit renewal resets expiry to 60 minutes from renewal. Page reads, packet checks and recopying never renew. Recording any result, including progress or quarantine, ends the lease; validation errors retain it. Reservation is not consent, model eligibility or permission to submit. Preserve signed task identifiers and comply with current terms, disclosures and planning/model policies. Use the private return link for authorized AI operations, never a new browser actor; never expose private capabilities. Stop after the recorded receipt.\n";
}

function wr_time(): int {
    // No HTTP header, packet, environment option or request parameter can set the clock.
    if(PHP_SAPI==='cli'&&in_array(env('APP_ENV'),['local','test','testing'],true)&&isset($GLOBALS['wr_test_clock']))return ($GLOBALS['wr_test_clock'])();
    return (int)query('SELECT UNIX_TIMESTAMP()')->fetchColumn();
}
function wr_stamp(?int $at=null): string { return gmdate('Y-m-d H:i:s',$at??wr_time()); }
function wr_actor_lock(string $actor,bool $create=false): void {
    if(!preg_match('/^[a-f0-9]{64}$/D',$actor))throw new LogicException('Expected a server-owned actor hash.');
    if(!db()->inTransaction())throw new LogicException('Reservation locks require a transaction.');
    if($create)query('INSERT IGNORE INTO reservation_actors(actor_hash) VALUES (?)',[$actor]);
    one('SELECT actor_hash FROM reservation_actors WHERE actor_hash=? FOR UPDATE',[$actor]);
}
function wr_rate(string $operation,string $actor): void {
    // Independent autocommit counters survive failed/nested claim transactions.
    $pdo=new PDO('mysql:host='.env('DB_HOST','127.0.0.1').';port='.env('DB_PORT','3318').';dbname='.env('DB_NAME','llm_giving').';charset=utf8mb4',env('DB_USER','llm_giving'),env('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $at=wr_time();
    foreach([['actor:'.$actor,60],['ip:'.($_SERVER['REMOTE_ADDR']??'cli'),600]] as [$scope,$limit]){
        $key=hash('sha256',env('RATE_LIMIT_NAMESPACE').'|reservation:'.$operation.'|'.$scope.'|'.intdiv($at,3600));
        $s=$pdo->prepare('INSERT INTO rate_limits(bucket,hits,expires) VALUES (?,1,?) ON DUPLICATE KEY UPDATE hits=hits+1');$s->execute([$key,$at+3600]);
        $s=$pdo->prepare('SELECT hits FROM rate_limits WHERE bucket=?');$s->execute([$key]);
        if((int)$s->fetchColumn()>$limit)fail(429,'Too many reservation '.$operation.' attempts. Please try again later.');
    }
}
/** Caller holds this research row when admitting work. Public read callers never grant a lease. */
function wr_eligible(string $rid,string $tid,int $revision,?string $actor=null): array {
    $r=research($rid);$p=wf_project($rid);$t=wf_task($rid,$tid);
    if(!$p||$t['visibility']!=='public'||$t['round_no']!=$p['round_no']||$t['state']!=='Ready'||(int)$t['revision']!==$revision||wf_quarantine($rid)||in_array($r['status'],['Needs Clarification','Blocked','Paused','Archived — Inactive','Solved','Review Complete'],true))fail(409,'This task is unavailable or changed. Reload its current revision before reserving or submitting.');
    foreach(json_decode($t['dependencies'],true) as $id){
        $dep=wf_task($rid,$id);
        $result=one("SELECT * FROM workflow_submissions WHERE id=? AND research_id=? AND visibility='public' AND admission='accepted'",[$dep['accepted_id'],$rid]);
        if($dep['visibility']!=='public'||$dep['round_no']!=$p['round_no']||$dep['state']!=='Completed'||!$result||!ra_current($result))fail(409,'A prerequisite result is unavailable or no longer current.');
        wf_read_snapshot($rid,$result['snapshot_id']);
    }
    if($actor&&$t['kind']==='review'){
        $syn=wf_task($rid,json_decode($t['dependencies'],true)[0]);
        $original=one('SELECT actor_hash FROM workflow_submissions WHERE id=? AND research_id=?',[$syn['accepted_id'],$rid]);
        if($original&&hash_equals($original['actor_hash'],$actor))fail(422,'Independent review requires a different contributor browser. AI handover preserves the original volunteer identity.');
        foreach(rt_targets($t) as $target){
            foreach(all("SELECT actor_hash,metadata FROM workflow_submissions WHERE research_id=? AND task_id=? AND visibility='public' AND admission='accepted'",[$rid,$target['task']['id']]) as $author){
                if(!empty(json_decode($author['metadata'],true)['evidence']['tool_builds'])&&hash_equals($author['actor_hash'],$actor))fail(422,'Tool reviewers must be independent of current and earlier builders.');
            }
        }
    }
    return $t;
}
/** Pin source withdrawals across the full ancestry, including a withdrawal/restore race. */
function wr_fence(string $rid): string {
    $chain=[];$seen=[];$next=$rid;
    while($next){
        if(isset($seen[$next])||count($seen)>=1000)fail(409,'Research ancestry cannot be validated.');
        $seen[$next]=true;
        $event=one("SELECT MAX(id) AS id FROM status_history WHERE research_id=? AND new_status='Withdrawn'",[$next]);
        $chain[]=[$next,(int)($event['id']??0)];
        $next=one('SELECT parent_id FROM research_forks WHERE research_id=?',[$next])['parent_id']??null;
    }
    return hash('sha256',json_encode($chain,JSON_THROW_ON_ERROR));
}
function wr_live(string $rid,string $tid): ?array {
    $row=one('SELECT v.* FROM task_reservations v JOIN workflow_tasks t ON t.id=v.task_id AND t.revision=v.task_revision WHERE v.research_id=? AND v.task_id=? AND v.ended_at IS NULL AND v.expires_at>? ORDER BY v.created_at DESC,v.id DESC LIMIT 1',[$rid,$tid,wr_stamp()]);
    return $row&&hash_equals($row['eligibility_fence'],wr_fence($rid))?$row:null;
}
function wr_claim(string $rid,string $tid,int $revision,string $actor): array {
    wr_rate('claim',$actor);
    return tx(function()use($rid,$tid,$revision,$actor){
        wr_actor_lock($actor,true);
        one('SELECT id FROM research WHERE id=? FOR UPDATE',[$rid])??fail(404,'Research not found.');
        wr_eligible($rid,$tid,$revision,$actor);
        $live=wr_live($rid,$tid);
        if($live){
            if(!hash_equals($live['actor_hash'],$actor))fail(409,'This task is reserved by another volunteer until '.$live['expires_at'].' UTC.');
            if((int)$live['task_revision']===$revision)return $live; // Recopy/retry never renews.
        }
        $others=all("SELECT v.* FROM task_reservations v JOIN workflow_tasks t ON t.id=v.task_id AND t.revision=v.task_revision JOIN workflow_projects p ON p.research_id=t.research_id AND p.round_no=t.round_no WHERE v.actor_hash=? AND v.ended_at IS NULL AND v.expires_at>? AND v.task_id<>? AND t.state='Ready' AND t.visibility='public'",[$actor,wr_stamp(),$tid]);
        foreach($others as $other){
            if(!hash_equals($other['eligibility_fence'],wr_fence($other['research_id'])))continue;
            try{wr_eligible($other['research_id'],$other['task_id'],(int)$other['task_revision'],$actor);}
            catch(HttpError $error){if(in_array($error->status,[404,409,422],true))continue;throw $error;}
            fail(409,'Finish or release your existing task reservation before picking up another task.');
        }
        // Only obsolete generations of this task are retired; never displace a live other holder.
        query("UPDATE task_reservations SET ended_at=?,end_reason='replaced' WHERE research_id=? AND task_id=? AND ended_at IS NULL",[wr_stamp(),$rid,$tid]);
        $at=wr_time();$row=['id'=>uid(),'research_id'=>$rid,'task_id'=>$tid,'task_revision'=>$revision,'actor_hash'=>$actor,'eligibility_fence'=>wr_fence($rid),'created_at'=>wr_stamp($at),'expires_at'=>wr_stamp($at+WR_SECONDS)];
        insert('task_reservations',$row);
        return $row;
    });
}
function wr_require(string $rid,string $tid,int $revision,string $actor): array {
    wr_eligible($rid,$tid,$revision,$actor);
    $live=wr_live($rid,$tid);
    if(!$live||!hash_equals($live['actor_hash'],$actor)||(int)$live['task_revision']!==$revision)fail(409,'A live reservation for this task revision is required. Explicitly reacquire the current task; an expired handoff cannot submit or displace its holder.');
    return $live;
}
function wr_renew(string $rid,string $tid,int $revision,string $actor): array {
    wr_rate('renew',$actor);
    return tx(function()use($rid,$tid,$revision,$actor){
        wr_actor_lock($actor);
        one('SELECT id FROM research WHERE id=? FOR UPDATE',[$rid])??fail(404,'Research not found.');
        $live=wr_require($rid,$tid,$revision,$actor);
        $live['expires_at']=wr_stamp(wr_time()+WR_SECONDS);
        query('UPDATE task_reservations SET expires_at=? WHERE id=?',[$live['expires_at'],$live['id']]);
        return $live;
    });
}
function wr_release(string $rid,string $tid,string $actor): void {
    tx(function()use($rid,$tid,$actor){
        wr_actor_lock($actor);
        one('SELECT id FROM research WHERE id=? FOR UPDATE',[$rid])??fail(404,'Research not found.');
        $row=one('SELECT id,actor_hash FROM task_reservations WHERE research_id=? AND task_id=? AND ended_at IS NULL ORDER BY created_at DESC,id DESC LIMIT 1',[$rid,$tid]);
        if(!$row||!hash_equals($row['actor_hash'],$actor))fail(403,'Only the reservation holder or its authorized AI can release this task.');
        query("UPDATE task_reservations SET ended_at=?,end_reason='released' WHERE id=?",[wr_stamp(),$row['id']]);
    });
}
/** Lifecycle mutations already hold the parent lock; never acquire an actor lock here. */
function wr_invalidate(string $rid,?string $tid=null): void {
    if(!db()->inTransaction())throw new LogicException('Invalidation requires the research transaction.');
    query("UPDATE task_reservations SET ended_at=?,end_reason='invalidated' WHERE research_id=? AND ended_at IS NULL".($tid?' AND task_id=?':''),array_merge([wr_stamp(),$rid],$tid?[$tid]:[]));
}
function wr_finish(array $reservation,string $result): void {
    query("UPDATE task_reservations SET ended_at=?,end_reason='submitted',result_id=? WHERE id=? AND ended_at IS NULL",[wr_stamp(),$result,$reservation['id']]);
}
/** Public availability projection: no actor, reservation ID or browser capability. */
function wr_availability(string $rid,string $tid): array {
    $t=wf_task($rid,$tid);
    wr_eligible($rid,$tid,(int)$t['revision']);
    $live=wr_live($rid,$tid);
    return ['status'=>$live?'reserved':'available','expires_at_utc'=>$live['expires_at']??null,'task_revision'=>(int)$t['revision']];
}
/** Call only after the holder (and any session) mutex and research lock, in that order. */
function wr_current_grant(array $r,string $tid,array $hint): array {
    if(!db()->inTransaction())throw new LogicException('Scoped grant validation requires holder and research locks.');
    $r=one('SELECT * FROM research WHERE id=? FOR UPDATE',[$r['id']])??fail(404,'Research not found.');
    if($r['moderation']!=='Approved'||$r['status']==='Withdrawn')fail(404,'Research not found.');
    $current=ar_read($r,$hint['id'],forUpdate:true,allowHolder:true);
    if(!hash_equals($current['actor_hash'],$hint['actor_hash'])||$current['task_id']!==$tid||$current['scope']['kind']!=='task')fail(403,'This private authorization does not match the reservation holder and task.');
    if($current['result_id'])fail(409,'This authorization already has a recorded receipt.');
    ar_validate($r,$current);
    return $current;
}
/** CSRF is enforced by the common router. No packet-supplied actor is accepted. */
function wr_http(array $r,string $tid,string $method,?array $grant=null): void {
    if($method!=='POST')fail(405,'Reservation changes require POST.');
    $actor=$grant['actor_hash']??wf_actor();
    $operation=text_field($_POST,'operation',1,12);
    if(!in_array($operation,['claim','renew','release'],true))fail(422,'Choose claim, renew or release.');
    $revisionText=text_field($_POST,'task_revision',1,10);
    if(!preg_match('/^[1-9][0-9]{0,9}$/D',$revisionText))fail(422,'Supply the exact positive task revision.');
    $revision=(int)$revisionText;
    $availability=tx(function()use($r,$tid,$grant,$actor,$operation,$revision){
        wr_actor_lock($actor,$operation==='claim');
        one('SELECT id FROM research WHERE id=? FOR UPDATE',[$r['id']])??fail(404,'Research not found.');
        $r=research($r['id']);
        if($grant){
            // The pre-lock array is only a mutex/lookup hint, never current authorization.
            $current=wr_current_grant($r,$tid,$grant);
            if($revision!==$current['scope']['revision'])fail(409,'This authorized assignment changed. Obtain a fresh handoff.');
        }
        if($operation==='claim')wr_claim($r['id'],$tid,$revision,$actor);
        elseif($operation==='renew')wr_renew($r['id'],$tid,$revision,$actor);
        else wr_release($r['id'],$tid,$actor);
        return $operation==='release'?['status'=>'released','expires_at_utc'=>null]:wr_availability($r['id'],$tid);
    });
    header('Content-Type: application/json');header('Cache-Control: no-store');
    echo json_encode($availability,JSON_THROW_ON_ERROR);
}
