<?php
/** Pickup UI adapters. All lease decisions remain in the shared wr_* service. */
function mp_state(array $r,array $task,?string $actor=null): array {
    $actor??=wf_actor();
    try { wr_eligible($r['id'],$task['id'],(int)$task['revision'],$actor); }
    catch(HttpError $e) { return ['eligible'=>false,'owned'=>false,'available'=>false,'expired'=>false,'message'=>$e->getMessage()]; }
    $live=wr_live($r['id'],$task['id']);
    if($live){
        $owned=hash_equals($live['actor_hash'],$actor);
        return ['eligible'=>true,'owned'=>$owned,'available'=>false,'expired'=>false,'expires'=>$live['expires_at'],'message'=>($owned?'Reserved for you until ':'In progress — reserved until ').$live['expires_at'].' UTC'];
    }
    $expired=(bool)one('SELECT id FROM task_reservations WHERE research_id=? AND task_id=? AND task_revision=? AND actor_hash=? AND ended_at IS NULL AND expires_at<=? LIMIT 1',[$r['id'],$task['id'],$task['revision'],$actor,wr_stamp()]);
    return ['eligible'=>true,'owned'=>false,'available'=>true,'expired'=>$expired,'message'=>$expired?'Your reservation expired. Reacquire before submitting; your entered work and signed identifiers are unchanged.':'No active reservation. Reserve this task before starting or submitting.'];
}
function mp_next(array $r,?string $actor=null): ?array {
    $actor??=wf_actor();$available=null;
    foreach(wf_tasks($r['id']) as $task){
        if($task['visibility']!=='public')continue;
        $state=mp_state($r,$task,$actor);
        if($state['owned'])return $task;
        if($state['available']&&!$available)$available=$task;
    }
    return $available;
}
function mp_unavailable(array $r): string {
    foreach(wf_tasks($r['id']) as $task){
        if($task['visibility']!=='public')continue;
        $state=mp_state($r,$task);
        if($state['eligible']&&!$state['available']&&!$state['owned'])return $state['message'].'. All eligible tasks are currently reserved. Choose another study or check again after a reservation expires. No task was assigned to you.';
    }
    return 'No eligible task is available. Review the task list for prerequisites, a hold or the next step. No task was assigned to you.';
}
function mp_revision(array $post): int {
    $value=text_field($post,'task_revision',1,10);
    if(!preg_match('/^[1-9][0-9]{0,9}$/D',$value))fail(422,'Supply the exact positive task revision.');
    return (int)$value;
}
function mp_pickup(array $r,?string $tid,int $revision=0): array {
    $actor=wf_actor();
    return tx(function()use($r,$tid,$revision,$actor){
        wr_actor_lock($actor,true);
        one('SELECT id FROM research WHERE id=? FOR UPDATE',[$r['id']]);
        $r=research($r['id']);
        $task=$tid?wf_task($r['id'],$tid):mp_next($r,$actor);
        if(!$task)fail(409,mp_unavailable($r));
        wr_claim($r['id'],$task['id'],$tid?$revision:(int)$task['revision'],$actor);
        // Failure preparing the complete archive rolls back a new claim as well.
        $archive=wf_issue_handoff($r['id'],$task['id']);
        return ['task'=>$task,'archive'=>$archive];
    });
}
function mp_authorized_pickup(array $r,array $post): array {
    return tx(function()use($r,$post){
        wr_actor_lock(wf_actor(),true);
        one('SELECT id FROM research WHERE id=? FOR UPDATE',[$r['id']]);
        $r=research($r['id']);$task=mp_next($r);
        if(!$task)fail(409,mp_unavailable($r));
        return ar_issue($r,$task['id'],$post);
    });
}
function mp_control(array $r,array $task,string $operation,int $revision,?array $grant=null): array {
    if(!in_array($operation,['status','claim','renew','release'],true))fail(422,'Choose claim, renew or release.');
    if($operation==='status'&&!$grant)return ['message'=>'Availability refreshed. Entered work and signed identifiers are unchanged.'];
    if($grant){$g=sr_authority($r,$grant['id'])['grant'];if($revision!==(int)$g['scope']['revision']||$g['task_id']!==$task['id'])fail(409,'Preserve the authorized task and signed revision.');if($operation==='status'){$status=sr_status($r,$g);return ['message'=>'Permission '.$status['permission'].'; reservation '.$status['lease'].'. No work was recorded or renewed.'];}sr_grant_action($r,$g['id'],$operation==='claim'?'reacquire':$operation);$out=['message'=>$operation==='release'?'This assignment was released; other assignments are unchanged.':'Reservation updated explicitly; original signed identifiers and consent are unchanged.'];if($operation==='claim')$out['handoff']=wf_read_handoff($r['id'],$g['scope']['archive_id'])['body'];return $out;}
    $actor=$grant['actor_hash']??wf_actor();
    return tx(function()use($r,$task,$operation,$revision,$actor,$grant){
        wr_actor_lock($actor,$operation==='claim');
        one('SELECT id FROM research WHERE id=? FOR UPDATE',[$r['id']])??fail(404,'Research not found.');
        $r=research($r['id']);
        if($grant){
            // A preloaded grant is only a lookup/holder hint; the shared guard rereads it under locks.
            $grant=wr_current_grant($r,$task['id'],$grant);
            $actor=$grant['actor_hash'];
            if($revision!==$grant['scope']['revision'])fail(409,'Preserve the authorized task and exact signed revision.');
        }
        if($operation==='status')return ['message'=>'Availability refreshed. Entered work and signed identifiers are unchanged.'];
        if($operation==='renew'){
            wr_renew($r['id'],$task['id'],$revision,$actor);
            return ['message'=>'Reservation renewed for 60 minutes from server time.'];
        }
        if($operation==='release'){
            wr_release($r['id'],$task['id'],$actor);
            return ['message'=>'Task released. Your entered work is preserved; reacquire an eligible reservation before submitting.'];
        }
        wr_claim($r['id'],$task['id'],$revision,$actor);
        $archive=$grant&&!empty($grant['scope']['archive_id'])?wf_read_handoff($r['id'],$grant['scope']['archive_id']):wf_issue_handoff($r['id'],$task['id']);
        return ['message'=>'Task reserved. Entered work and signed identifiers were not replaced. Check current requirements before submitting.','handoff'=>$archive['body']];
    });
}
function mp_json(): bool { return str_contains($_SERVER['HTTP_ACCEPT']??'','application/json'); }
function mp_response(array $r,array $task,?array $grant,string $formId,array $result,bool $ok): void {
    $reservationForm=$formId;$reservationMessage=$result['message'];$reservationHandoff=$result['handoff']??'';
    ob_start();include ROOT.'/views/task-reservation.php';$html=ob_get_clean();
    header('Content-Type: application/json');header('Cache-Control: no-store');
    echo json_encode(['ok'=>$ok,'message'=>$result['message'],'html'=>$html],JSON_THROW_ON_ERROR);
}
function mp_issued(array $r,array $issued): array {
    $g=ar_read($r,$issued['id'],false);
    $issued['task']=$g['task_id']?wf_task($r['id'],$g['task_id']):null;
    $issued['grant']=$g;
    return $issued;
}
function mp_route(array $r,string $suffix,string $method): bool {
    if(preg_match('~^(?:pickup|tasks/([a-f0-9]{32})/pickup)$~D',$suffix,$match)){
        if($method!=='POST')fail(405,'Task pickup requires a CSRF-protected POST. Reference prompts remain readable.');
        $tid=$match[1]??null;$task=$tid?wf_task($r['id'],$tid):null;
        if($task&&$task['visibility']!=='public')fail(404,'Task not found.');
        $error='';$reservationMessage='';
        try{
            if(str_starts_with($_POST['operation']??'','reservation-')&&$task){
                $archive=wf_read_handoff($r['id'],text_field($_POST,'handoff_id',32,32));
                if($archive['task_id']!==$task['id'])fail(409,'This handoff belongs to another task.');
                $result=mp_control($r,$task,substr($_POST['operation'],12),mp_revision($_POST));
                if(mp_json()){mp_response($r,$task,null,'pickup-handoff',$result,true);return true;}
                $reservationMessage=$result['message'];
            }else{
                $picked=mp_pickup($r,$tid,$tid?mp_revision($_POST):0);$task=$picked['task'];$archive=$picked['archive'];
            }
        }catch(HttpError $e){
            http_response_code($e->status);$error=$e->getMessage();
            if($task&&mp_json()){mp_response($r,$task,null,'pickup-handoff',['message'=>$error],false);return true;}
            $archive=$task&&!empty($_POST['handoff_id'])?wf_read_handoff($r['id'],text_field($_POST,'handoff_id',32,32)):null;
        }
        render('task-pickup',['title'=>'Task handoff','r'=>$r,'task'=>$task,'archive'=>$archive,'error'=>$error,'reservationMessage'=>$reservationMessage]);return true;
    }
    if(preg_match('~^pickup/return/([a-f0-9]{32})$~D',$suffix,$match)){
        if($method!=='POST')fail(405,'Reservation changes require POST.');
        $g=ar_read($r,$match[1],false);
        $delegated=isset($_SESSION['browser_returns'][$g['id']])&&hash_equals($g['secret_hash'],$_SESSION['browser_returns'][$g['id']]);
        if(!hash_equals($g['actor_hash'],wf_actor())&&!$delegated)fail(403,'Only the original volunteer or its authorized AI may manage this reservation.');
        $task=$g['task_id']?wf_task($r['id'],$g['task_id']):null;
        if(!$task)fail(422,'Supplementary contributions do not reserve tasks.');
        $issued=['id'=>$g['id'],'archive_id'=>$g['scope']['archive_id']??null,'task'=>$task,'grant'=>$g,'handoff'=>text_field($_POST,'pickup_handoff',1,4259840)];
        $reservationMessage='';$error='';
        try{$result=mp_control($r,$task,substr(text_field($_POST,'operation',13,24),12),mp_revision($_POST),$g);$reservationMessage=$result['message'];}
        catch(HttpError $e){http_response_code($e->status);$error=$e->getMessage();$result=['message'=>$error];}
        if(mp_json()){mp_response($r,$task,$g,'pickup-handoff',$result,!$error);return true;}
        render('browser-handoff',['title'=>'Authorized AI handoff','r'=>$r,'issued'=>$issued,'reservationMessage'=>$reservationMessage,'error'=>$error]);return true;
    }
    return false;
}
