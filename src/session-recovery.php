<?php
/** Internal binding data only; never include owner/credential fields in public manifests. */
function sr_binding(array $g): array {
    $s=$g['scope'];return ['research_id'=>$g['research_id'],'task_id'=>$g['task_id'],'task_revision'=>$s['revision']??null,'context_id'=>$s['context_id']??null,'submission_key'=>$s['submission_key']??null,'archive_id'=>$s['archive_id']??null,'owner_actor_hash'=>$g['actor_hash'],'session_id'=>$s['volunteer_session_id']??null,'grant_id'=>$g['id'],'schema_version'=>$s['schema_version']??'legacy-v1'];
}
/** A public identifier is only a lookup. Access requires a matching current private grant. */
function sr_access_proof(array $g): bool {
    $returnProof=$_SESSION['browser_returns'][$g['id']]??null;
    if(sr_return_access($g))return true;
    if(hash_equals($g['actor_hash'],wf_actor()))return true; // Original private browser actor capability.
    $sid=$g['scope']['volunteer_session_id']??null;$aid=$g['scope']['volunteer_assignment_id']??null;
    if(!is_string($sid)||!is_string($aid))return false;
    $proof=$_SESSION['volunteer_sessions'][$sid]??null;if(!is_string($proof))return false;
    $s=vs_read($sid,false);if(!hash_equals($s['secret_hash'],$proof)||!hash_equals($s['actor_hash'],$g['actor_hash']))return false;
    $a=vs_assignment($s,$aid);
    return $a['return_id']===$g['id']&&$a['research_id']===$g['research_id']&&$a['task_id']===$g['task_id'];
}
function sr_authority(array $r,string $grantId,bool $mutation=false): array {
    $g=ar_read($r,$grantId,false);
    if(!empty($g['revoked_at']))fail(403,'This private assignment capability was revoked.');
    if(!sr_access_proof($g))fail(403,'Open the private continuation or return link. A public URL cannot transfer a reservation.');
    if($mutation){if($g['result_id'])fail(409,'Already recorded. Open the existing receipt.');ar_validate($r,$g);}
    return ['binding'=>sr_binding($g),'grant'=>$g,'status'=>sr_status($r,$g)];
}

/** Read-only compatibility for the exact pre-reservation_id standalone scope.
 * A unique lease must span BOTH archived handoff issuance and stored grant creation.
 * Inclusive end timestamps deliberately reject same-second ambiguous histories.
 * Never infer ownership merely from the current same-actor lease or rewrite consent. */
function sr_expected_reservation(array $g): ?string {
    $scope=$g['scope'];
    if(array_key_exists('reservation_id',$scope))return is_string($scope['reservation_id'])&&preg_match('/^[a-f0-9]{32}$/D',$scope['reservation_id'])?$scope['reservation_id']:null;
    $legacy=['kind','revision','context_id','submission_key','archive_id','requirements'];
    if(!$g['task_id']||($scope['kind']??'')!=='task'||array_diff(array_keys($scope),$legacy)||array_diff($legacy,array_keys($scope))||!is_int($scope['revision'])||$scope['revision']<1)return null;
    $issued=one('SELECT created_at FROM workflow_handoffs WHERE id=? AND research_id=? AND task_id=? AND snapshot_id=? AND nonce=?',[$scope['archive_id'],$g['research_id'],$g['task_id'],$scope['context_id'],$scope['submission_key']]);
    if(!$issued||$issued['created_at']>$g['created_at'])return null;
    $candidates=all('SELECT id FROM task_reservations WHERE research_id=? AND task_id=? AND task_revision=? AND actor_hash=? AND created_at<=? AND expires_at>? AND (ended_at IS NULL OR ended_at>=?) ORDER BY created_at,id LIMIT 2',[$g['research_id'],$g['task_id'],$scope['revision'],$g['actor_hash'],$issued['created_at'],$g['created_at'],$g['created_at']]);
    return count($candidates)===1?$candidates[0]['id']:null;
}

function sr_status(array $r,array $g): array {
    $permission='active';$reason='Captured consent applies to this exact assignment.';$assignment=null;
    if(!empty($g['revoked_at'])){$permission='revoked';$reason='Private capability revoked.';}
    elseif(!empty($g['scope']['volunteer_session_id'])){$s=vs_read($g['scope']['volunteer_session_id'],false);$assignment=vs_assignment($s,$g['scope']['volunteer_assignment_id']);$state=vs_state($s);if(in_array($state,['expired','stopped','terms_changed'],true)){$permission=$state;$reason='No ongoing research, renewal or submission is authorized.';}elseif($assignment['ended_at']&&!$g['result_id']){$permission='released';$reason='This assignment was released.';}}
    elseif($g['expires_at']!==null&&strtotime($g['expires_at'].' UTC')<=wr_time()){$permission='expired';$reason='Assignment authorization expired. Recovery does not extend it.';}
    if($permission==='active'&&($g['consent']['terms_version']!==terms_version()||($g['consent']['tool_terms_version']!==null&&$g['consent']['tool_terms_version']!==RT_TERMS))){$permission='terms_changed';$reason='Changed terms require fresh human authorization.';}
    $task=$g['task_id']?wf_task($r['id'],$g['task_id']):null;$taskState=$task?$task['state']:'supplementary';$lease='not_applicable';$leaseExpiry=null;$canReacquire=false;
    if($task){if((int)$task['revision']!==(int)$g['scope']['revision'])$taskState='revision_changed';
        $live=wr_live($r['id'],$task['id']);if($live){$expected=$assignment['reservation_id']??sr_expected_reservation($g);$lease=!hash_equals($live['actor_hash'],$g['actor_hash'])?'other_holder':($expected&&$live['id']===$expected?'owned':'other_assignment');$leaseExpiry=$live['expires_at'];}else{$last=one('SELECT end_reason,expires_at FROM task_reservations WHERE research_id=? AND task_id=? AND task_revision=? AND actor_hash=? ORDER BY created_at DESC,id DESC LIMIT 1',[$r['id'],$task['id'],$g['scope']['revision'],$g['actor_hash']]);$lease=$last['end_reason']??($last?(strtotime($last['expires_at'].' UTC')>wr_time()&&$permission!=='active'?'permission_ended':'expired'):'not_reserved');$leaseExpiry=$last['expires_at']??null;}
        try{wr_eligible($r['id'],$task['id'],(int)$g['scope']['revision'],$g['actor_hash'],$g['identity_id']??null);$canReacquire=$permission==='active'&&($assignment||sr_expected_reservation($g)!==null)&&!in_array($lease,['other_holder','other_assignment'],true)&&!$g['result_id'];}catch(HttpError $e){if(!in_array($e->status,[403,404,409,422],true))throw $e;if($taskState==='Ready')$taskState='unavailable';}
    }
    if($permission==='active'&&!$g['result_id']){try{ar_validate($r,$g,false);}catch(HttpError $e){if(!in_array($e->status,[403,404,409,422],true))throw $e;$permission='requires_review';$reason=$e->getMessage();$canReacquire=false;}}
    $result=$g['result_id']?one('SELECT admission,outcome FROM workflow_submissions WHERE id=? AND research_id=?',[$g['result_id'],$r['id']]):null;
    return ['permission'=>$permission,'permission_reason'=>$reason,'permission_expires_at'=>$g['expires_at'],'task_state'=>$taskState,'task_revision'=>$g['scope']['revision']??null,'lease'=>$lease,'lease_expires_at'=>$leaseExpiry,'can_renew'=>$permission==='active'&&$lease==='owned'&&!$g['result_id'],'can_reacquire'=>$canReacquire,'recorded'=>(bool)$g['result_id'],'result_id'=>$g['result_id'],'result_status'=>$result['admission']??($g['result_id']?'recorded':'not_submitted'),'worker_state'=>$assignment['worker_state']??'not_reported','worker_reported_at'=>$assignment['worker_reported_at']??null,'draft_available'=>function_exists('rtx_draft_available')?rtx_draft_available(sr_binding($g)):null];
}
function sr_secret(string $id): string {
    $h=fopen(storage('private/task-continuation.key'),'c+b');if(!$h||!flock($h,LOCK_EX))throw new RuntimeException('Private continuation is unavailable.');
    try{$key=stream_get_contents($h);if($key===''){$key=random_bytes(32);if(fwrite($h,$key)!==32||!fflush($h))throw new RuntimeException('Cannot save continuation key.');}if(strlen($key)!==32)throw new RuntimeException('Invalid continuation key.');return hash_hmac('sha256',"task-continuation-v1\n".$id,$key);}finally{flock($h,LOCK_UN);fclose($h);}
}
function sr_continue(array $r,string $grantId): array {
    $g=sr_authority($r,$grantId)['grant'];if(!$g['task_id']||empty($g['consent']['captured_at_utc'])||empty($g['consent']['terms_version']))fail(422,'The reserved task needs its first captured AI-submission authorization. A reservation alone is not consent.');
    return tx(function()use($g){wr_actor_lock($g['actor_hash'],true);one('SELECT id FROM research WHERE id=? FOR UPDATE',[$g['research_id']]);sr_authority(research($g['research_id']),$g['id']);$c=one('SELECT * FROM task_continuations WHERE grant_id=? FOR UPDATE',[$g['id']]);if($c&&$c['revoked_at'])fail(403,'Continuation revoked. It cannot be silently recreated.');
        if(!$c){$id=uid();$c=['id'=>$id,'grant_id'=>$g['id'],'secret_hash'=>hash('sha256',sr_secret($id)),'created_at'=>wr_stamp(),'revoked_at'=>null];insert('task_continuations',$c);}
        $secret=sr_secret($c['id']);if(!hash_equals($c['secret_hash'],hash('sha256',$secret)))fail(403,'Continuation key changed. Existing capability cannot be recovered.');$_SESSION['browser_returns'][$g['id']]=$g['secret_hash'];return $c+['link'=>rtrim(env('APP_URL'),'/').'/continue/'.$c['id'].'/open#'.$secret];
    });
}
function sr_read(string $id,bool $access=true): array {
    $c=one('SELECT * FROM task_continuations WHERE id=?',[$id])??fail(404,'Private continuation not found.');if($c['revoked_at'])fail(403,'Continuation revoked.');
    $hint=one('SELECT research_id FROM browser_returns WHERE id=?',[$c['grant_id']])??fail(404,'Assignment unavailable.');$r=research($hint['research_id']);$g=$access?sr_authority($r,$c['grant_id'])['grant']:ar_read($r,$c['grant_id'],false);return $c+['research'=>$r,'grant'=>$g];
}
function sr_exchange(string $id,string $secret): void {
    throttle('task-continuation-exchange',20);$c=sr_read($id,false);if(!preg_match('/^[a-f0-9]{64}$/D',$secret)||!hash_equals($c['secret_hash'],hash('sha256',$secret)))fail(403,'Invalid private continuation link.');
    session_regenerate_id(true);$_SESSION['browser_returns'][$c['grant_id']]=$c['grant']['secret_hash'];$_SESSION['continuation_grants'][$c['grant_id']]=['id'=>$id,'hash'=>$c['secret_hash']];
}
function sr_grant_action(array $r,string $grantId,string $operation): void {
    $hint=sr_authority($r,$grantId)['grant'];if(!$hint['task_id'])fail(422,'Only tasks have reservations.');
    tx(function()use($r,$grantId,$operation,$hint){wr_actor_lock($hint['actor_hash'],true);if(!empty($hint['scope']['volunteer_session_id']))vs_read($hint['scope']['volunteer_session_id'],false,true);one('SELECT id FROM research WHERE id=? FOR UPDATE',[$r['id']]);$g=sr_authority(research($r['id']),$grantId)['grant'];
        if($g['result_id'])fail(409,'Already recorded. Open the existing receipt.');
        $a=!empty($g['scope']['volunteer_assignment_id'])?vs_assignment(vs_read($g['scope']['volunteer_session_id'],false),$g['scope']['volunteer_assignment_id']):null;
        $expectedLease=$a['reservation_id']??sr_expected_reservation($g);$currentLease=wr_live($r['id'],$g['task_id']);
        if(!$a&&!$expectedLease)fail(409,'The original reservation cannot be proven from this legacy authorization. Do not adopt a current lease; obtain fresh scoped authorization when this task is unheld.');
        if($operation==='release'){if($a){vs_release_assignment(vs_read($g['scope']['volunteer_session_id'],false),$a);return;}if($currentLease&&(!$expectedLease||$currentLease['id']!==$expectedLease||!hash_equals($currentLease['actor_hash'],$g['actor_hash'])))fail(409,'This reservation belongs to another assignment. The old credential cannot release it.');if($currentLease)wr_release($r['id'],$g['task_id'],$g['actor_hash']);return;}
        if($currentLease&&(!$expectedLease||$currentLease['id']!==$expectedLease))fail(409,'A different assignment now holds this task. Do not adopt its reservation, even under the same owner.');
        ar_validate($r,$g,false);
        if($operation==='renew'){ar_validate($r,$g);wr_renew($r['id'],$g['task_id'],(int)$g['scope']['revision'],$g['actor_hash'],$g['identity_id']??'');}
        elseif($operation==='reacquire'){$lease=wr_claim($r['id'],$g['task_id'],(int)$g['scope']['revision'],$g['actor_hash'],$g['scope']['volunteer_session_id']??null,$g['identity_id']??'');if(!empty($g['scope']['volunteer_assignment_id']))query('UPDATE volunteer_assignments SET reservation_id=? WHERE id=?',[$lease['id'],$g['scope']['volunteer_assignment_id']]);elseif(array_key_exists('reservation_id',$g['scope'])||$lease['id']!==$expectedLease){$scope=$g['scope'];$scope['reservation_id']=$lease['id'];query('UPDATE browser_returns SET scope=? WHERE id=?',[json_encode($scope,JSON_THROW_ON_ERROR),$g['id']]);}}
        else fail(422,'Choose renew, reacquire or release.');
    });
}
function sr_route(string $path,string $method): bool {
    if(!preg_match('~^/continue/([a-f0-9]{32})(?:/(open|instructions))?$~D',$path,$m))return false;
    header('Cache-Control: private, no-store, max-age=0');header('X-Robots-Tag: noindex, nofollow');$id=$m[1];$part=$m[2]??'';
    if($part==='open'){if($method==='POST'){sr_exchange($id,text_field($_POST,'return_secret',64,64));redirect('/continue/'.$id);}if($method!=='GET')fail(405,'Use GET or POST.');render('volunteer-continue-open',['title'=>'Open private continuation','continuationId'=>$id]);return true;}
    $c=sr_read($id);$r=$c['research'];$g=$c['grant'];$error='';
    if($part==='instructions'){if($method!=='GET')fail(405,'Use GET.');sr_authority($r,$g['id'],true);$archive=wf_read_handoff($r['id'],$g['scope']['archive_id']);$instructions=$archive['body']."\nPRIVATE CONTINUATION — existing captured task consent, not new permission.\nUse the authenticated controls at ".rtrim(env('APP_URL'),'/').'/continue/'.$id.". Preserve complete output and signed identifiers.\n".ar_instructions().(function_exists('vo_formatting_guidance')?vo_formatting_guidance($g['consent']):'');render('volunteer-continuation-instructions',['title'=>'Complete task instructions','instructions'=>$instructions,'continuationId'=>$id]);return true;}
    if($method==='POST'){try{$operation=text_field($_POST,'operation',1,24);if($operation==='reauthorize'){$new=sr_reauthorize($r,$g['id'],$_POST);$ng=sr_authority($r,$new['grant_id'])['grant'];render('volunteer-continuation',['title'=>'Recovered assignment authorization','continuation'=>$new,'r'=>$r,'grant'=>$ng,'status'=>sr_status($r,$ng),'issuedLink'=>$new['link'],'error'=>'']);return true;}if($operation==='revoke'){query('UPDATE task_continuations SET revoked_at=? WHERE id=?',[wr_stamp(),$id]);redirect('/continue/'.$id);}sr_grant_action($r,$g['id'],$operation);redirect('/continue/'.$id);}catch(HttpError $e){http_response_code($e->status);$error=$e->getMessage();}}
    elseif($method!=='GET')fail(405,'Use GET or POST.');render('volunteer-continuation',['title'=>'Continue reserved task','continuation'=>$c,'r'=>$r,'grant'=>$g,'status'=>sr_status($r,$g),'error'=>$error]);return true;
}

function sr_return_access(array $g): bool {
    $proof=$_SESSION['browser_returns'][$g['id']]??null;if(!is_string($proof)||!hash_equals($g['secret_hash'],$proof)||!empty($g['revoked_at']))return false;
    $origin=$_SESSION['continuation_grants'][$g['id']]??null;if(!$origin)return true;
    $c=one('SELECT grant_id,secret_hash,revoked_at FROM task_continuations WHERE id=?',[$origin['id']??'']);return $c&&!$c['revoked_at']&&$c['grant_id']===$g['id']&&is_string($origin['hash']??null)&&hash_equals($c['secret_hash'],$origin['hash']);
}

/** Explicit fresh human authorization resumes only this unrecorded task; never edits the old consent. */
function sr_reauthorize(array $r,string $grantId,array $post): array {
    $hint=sr_authority($r,$grantId)['grant'];if(!$hint['task_id'])fail(422,'Same-task recovery requires an existing task assignment.');$key=text_field($post,'reauthorization_key',32,32);if(!ctype_xdigit($key))fail(422,'Keep the original recovery request key.');
    return tx(function()use($r,$grantId,$post,$hint,$key){wr_actor_lock($hint['actor_hash'],true);one('SELECT id FROM research WHERE id=? FOR UPDATE',[$r['id']]);$r=research($r['id']);$old=sr_authority($r,$grantId)['grant'];
        if($saved=one('SELECT new_grant_id FROM task_reauthorizations WHERE old_grant_id=? AND request_key=?',[$grantId,$key])){$_SESSION['browser_returns'][$saved['new_grant_id']]=ar_read($r,$saved['new_grant_id'],false)['secret_hash'];return sr_continue($r,$saved['new_grant_id']);}
        if($old['result_id'])fail(409,'Already recorded. Recover its existing receipt; do not authorize another submission.');
        if(!in_array(sr_status($r,$old)['permission'],['expired','stopped','terms_changed','released'],true))fail(409,'Current authorization still applies. Continue it rather than creating another grant.');
        if(empty($post['authorize'])||empty($post['terms'])||empty($post['authorized_resources']))fail(422,'A human must explicitly authorize the renewed time window, current terms and resource use. Existing consent cannot imply this extension.');
        $version=terms_version(record:true);if(text_field($post,'policy_version',1,64)!==$version)fail(409,'Terms changed. Read and confirm the current terms yourself.');
        $t=wf_task($r['id'],$old['task_id']);wr_eligible($r['id'],$t['id'],(int)$old['scope']['revision'],$old['actor_hash'],$old['identity_id']??'');
        if(!rq_same_requirements($old['scope']['requirements']??null,rq_requirements($r)))fail(409,'Requirements changed. A fresh complete task handoff is needed; do not relabel old work.');
        wf_read_handoff($r['id'],$old['scope']['archive_id']);$software=vs_task_software_consent($r,$t);if($software&&empty($post['tool_terms']))fail(422,'Confirm applicable software-publication terms yourself.');
        $minutes=vs_integer($post,'recovery_minutes',60,5,240);$scope=$old['scope'];$sourceSession=!empty($scope['volunteer_session_id'])?vs_read($scope['volunteer_session_id'],false):null;
        unset($scope['volunteer_session_id'],$scope['volunteer_assignment_id']);$scope['recovery_source_grant']=$old['id'];
        if($sourceSession){$scope['recovery_profile']=vs_profile_id($sourceSession);$scope['recovery_worker']=$sourceSession['capabilities'];}
        $consent=$old['consent'];$consent['terms_version']=$version;$consent['captured_at_utc']=wr_stamp();$consent['source']='Human same-assignment recovery authorization (self-attested)';$consent['influence_policy_version']='formatting-only-v1';$consent['tool_terms_version']=$software?RT_TERMS:null;$consent['software_license']=$software?RT_LICENSE:null;$consent['permission']='Only the same previously authored assignment, same worker and signed identifiers; '.$minutes.' minutes. No queue access or parallel-worker expansion.';
        // Keep initial disclosure/collection status exactly as originally captured.
        $oldAssignment=$sourceSession?vs_assignment($sourceSession,$old['scope']['volunteer_assignment_id']):null;$expected=$oldAssignment['reservation_id']??sr_expected_reservation($old);$live=wr_live($r['id'],$t['id']);
        if($live&&(!$expected||$live['id']!==$expected||!hash_equals($live['actor_hash'],$old['actor_hash'])))fail(409,'Another assignment holds this task. Fresh authorization cannot adopt or displace its reservation.');
        $lease=wr_claim($r['id'],$t['id'],(int)$scope['revision'],$old['actor_hash'],null,$old['identity_id']??'');$scope['reservation_id']=$lease['id'];$id=uid();$secret=bin2hex(random_bytes(32));
        insert('browser_returns',['id'=>$id,'research_id'=>$r['id'],'task_id'=>$t['id'],'secret_hash'=>hash('sha256',$secret),'actor_hash'=>$old['actor_hash'],'identity_id'=>$old['identity_id']??null,'scope'=>json_encode($scope,JSON_THROW_ON_ERROR),'consent'=>json_encode($consent,JSON_THROW_ON_ERROR),'created_at'=>wr_stamp(),'expires_at'=>wr_stamp(wr_time()+$minutes*60)]);
        insert('task_reauthorizations',['old_grant_id'=>$old['id'],'request_key'=>$key,'new_grant_id'=>$id]);$_SESSION['browser_returns'][$id]=hash('sha256',$secret);return sr_continue($r,$id);
    });
}

function sr_start_message(array $c): string {
    $prefix=rtrim(env('APP_URL'),'/').'/continue/'.$c['id'].'/open#';$link=$c['link']??'';if(!str_starts_with($link,$prefix)||!hash_equals($c['secret_hash'],hash('sha256',substr($link,strlen($prefix)))))fail(403,'Use the exact issued continuation link.');
    $text='Please continue my reserved LLM.Giving task using this exact private link: '.$link.'. I authorize opening it and completing only the existing captured assignment within its live permission and reservation limits. Read the COMPLETE task instructions from that private page before work. Preserve the original signed identifiers, same-worker authorship and honest disclosures; check and submit only the complete worker packet, then inspect its recorded receipt. Expired or stopped permission requires fresh human authorization; never invent consent or broaden access. Keep this link private.';
    if(strlen($text)>1200)throw new RuntimeException('Continuation message exceeds the short-message limit.');return $text;
}

function sr_schema_version(array $task,string $contextId): string {
    if(!function_exists('wf_packet_contract'))return 'legacy-v1';$contract=wf_packet_contract($task,wf_read_snapshot($task['research_id'],$contextId));return (string)$contract['version'];
}
