<?php
/** Private resource-account declarations. Never provider authentication or public packet metadata. */
function ri_account_policy(): string { return 'explicit_attestation'; }
function ri_key(): string {
    $h=fopen(storage('private/research-identity.key'),'c+b');
    if(!$h||!flock($h,LOCK_EX))throw new RuntimeException('Private account declarations are unavailable.');
    try{$key=stream_get_contents($h);if($key===''){$key=random_bytes(32);if(fwrite($h,$key)!==32||!fflush($h))throw new RuntimeException('Cannot save identity key.');}if(strlen($key)!==32)throw new RuntimeException('Invalid identity key.');return $key;}finally{flock($h,LOCK_UN);fclose($h);}
}
function ri_actor_identity(string $actor): ?string { return one('SELECT identity_id FROM research_actor_identities WHERE actor_hash=?',[$actor])['identity_id']??null; }
function ri_identity(?string $id): ?array { return $id?one('SELECT * FROM research_account_identities WHERE id=?',[$id]):null; }
/** Only human authorization forms call this. Raw account references never persist. */
function ri_capture(array $post,?string $actor=null): ?string {
    $provider=strtolower(text_field($post,'account_provider',0,80));$reference=text_field($post,'account_reference',0,160);
    if($provider===''&&$reference==='')return null;
    if($provider===''||$reference===''||empty($post['account_attestation']))fail(422,'Provide the AI account provider and your private account reference, and confirm the declaration yourself. No password or API key.');
    if(preg_match('/[\r\n]/',$provider.$reference))fail(422,'Use a single-line private account reference, never credentials.');
    $hash=hash_hmac('sha256',$provider."\n".strtolower($reference),ri_key());$existing=one('SELECT id FROM research_account_identities WHERE provider=? AND account_hash=?',[$provider,$hash]);
    if(!$existing){$id=uid();query('INSERT IGNORE INTO research_account_identities(id,provider,account_hash,basis,created_at) VALUES (?,?,?,?,?)',[$id,$provider,$hash,'self_attested',wr_stamp()]);$existing=one('SELECT id FROM research_account_identities WHERE provider=? AND account_hash=?',[$provider,$hash]);}
    $actor??=wf_actor();query('INSERT INTO research_actor_identities(actor_hash,identity_id) VALUES (?,?) ON DUPLICATE KEY UPDATE identity_id=VALUES(identity_id)',[$actor,$existing['id']]);
    return $existing['id'];
}
function ri_targets(array $task): array {
    $rid=$task['research_id'];$seen=[];$todo=json_decode($task['dependencies'],true);$ids=[];
    while($todo){$tid=array_pop($todo);if(isset($seen[$tid]))continue;$seen[$tid]=true;$t=wf_task($rid,$tid);foreach(json_decode($t['dependencies'],true) as $d)$todo[]=$d;
        if($t['accepted_id'])$ids[$t['accepted_id']]=true;
        // Reviews of tools must also remain separate from earlier accepted builders.
        if(!empty($t['builds_tool']))foreach(all("SELECT id FROM workflow_submissions WHERE research_id=? AND task_id=? AND admission='accepted' AND visibility='public'",[$rid,$tid]) as $row)$ids[$row['id']]=true;
    }
    return array_keys($ids);
}
/** Throws for known conflicts, even if a separation attestation was supplied. */
function ri_review_target_digest(array $r,array $task,string $actor,?string $identityId): string {
    research($r['id']);$reviewer=ri_identity($identityId);$targets=ri_targets($task);$bound=[];
    foreach($targets as $id){$source=one("SELECT actor_hash,snapshot_id FROM workflow_submissions WHERE id=? AND research_id=? AND admission='accepted' AND visibility='public'",[$id,$r['id']])??fail(409,'A review target is unavailable.');wf_read_snapshot($r['id'],$source['snapshot_id']);
        if(hash_equals($source['actor_hash'],$actor))fail(422,'This owner authored reviewed work. A different browser, thread, model or subagent is not independent.');
        $author=one('SELECT i.* FROM research_result_identities r JOIN research_account_identities i ON i.id=r.identity_id WHERE r.result_id=?',[$id]);
        if($reviewer&&$author&&$reviewer['provider']===$author['provider']&&hash_equals($reviewer['account_hash'],$author['account_hash']))fail(422,'Independent review cannot use the same AI account as any reviewed author or builder. Another thread, model or subagent does not change the account.');
        $bound[]=['result_id'=>$id,'owner'=>$source['actor_hash'],'identity_id'=>$author['id']??null];
    }
    if(!$bound)fail(409,'This review has no current accepted targets.');usort($bound,fn($x,$y)=>strcmp($x['result_id'],$y['result_id']));
    return hash('sha256',json_encode([$task['id'],(int)$task['revision'],$bound],JSON_THROW_ON_ERROR));
}
function ri_capture_review(array $r,array $task,string $actor,?string $identityId,array $post): void {
    if($task['kind']!=='review'||!$identityId||!ri_identity($identityId))fail(422,'Declare the AI account you will actually use before attesting review separation.');
    if(($post['review_account_separation']??'')!=='knowingly_different')fail(422,'Only a human who actually knows this AI account differs from every reviewed author/builder can attest. Missing or uncertain separation is not eligible.');
    $digest=ri_review_target_digest($r,$task,$actor,$identityId);
    if(!is_string($post['review_target_digest']??null)||!hash_equals($digest,$post['review_target_digest']))fail(409,'Review targets changed. Read the current author/builder scope and attest again yourself.');
    query('INSERT IGNORE INTO research_review_attestations(id,research_id,task_id,task_revision,owner_actor_hash,identity_id,target_digest,policy_version,captured_at) VALUES (?,?,?,?,?,?,?,?,?)',[uid(),$r['id'],$task['id'],(int)$task['revision'],$actor,$identityId,$digest,'ai-account-separation-v1',wr_stamp()]);
}
function ri_review_guard(array $research,array $task,string $ownerActor,?string $identityId=null): void {
    if($task['kind']!=='review')return;
    $digest=ri_review_target_digest($research,$task,$ownerActor,$identityId);
    if(!$identityId||!one('SELECT id FROM research_review_attestations WHERE task_id=? AND task_revision=? AND owner_actor_hash=? AND identity_id=? AND target_digest=? AND policy_version=?',[$task['id'],(int)$task['revision'],$ownerActor,$identityId,$digest,'ai-account-separation-v1']))fail(422,'AI-account separation is not established. A human who knowingly uses a different AI account must attest for these exact review targets. A different thread, browser or model is insufficient; the declaration is not provider verification.');
}
function ri_record_result(string $resultId,string $ownerActor,?string $identityId=null): void {
    if(!db()->inTransaction())throw new LogicException('Result identity must be recorded in the result transaction.');
    $row=one('SELECT actor_hash FROM workflow_submissions WHERE id=?',[$resultId])??fail(404,'Result unavailable.');
    if(!hash_equals($row['actor_hash'],$ownerActor))fail(403,'Result attribution differs from the authorized owner.');
    if($identityId&&!ri_identity($identityId))fail(403,'Captured account declaration is unavailable.');
    query('INSERT IGNORE INTO research_result_identities(result_id,identity_id,owner_actor_hash) VALUES (?,?,?)',[$resultId,$identityId,$ownerActor]);
}

function ri_route(string $path,string $method): bool {
    if(!preg_match('~^/volunteer/review/([a-f0-9]{32})/([a-f0-9]{32})$~D',$path,$m))return false;
    header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow');$r=research($m[1]);$task=wr_eligible($r['id'],$m[2],(int)wf_task($r['id'],$m[2])['revision']);if($task['kind']!=='review')fail(422,'This is not an independent-review task.');$actor=wf_actor();$error='';
    if($method==='POST'){try{if(($_POST['operation']??'')==='identity'){ri_capture($_POST,$actor)??fail(422,'Declare the actual AI account yourself.');redirect($path);}tx(function()use($r,$task,$actor){wr_actor_lock($actor,true);one('SELECT id FROM research WHERE id=? FOR UPDATE',[$r['id']]);$identity=ri_actor_identity($actor);ri_capture_review(research($r['id']),wf_task($r['id'],$task['id']),$actor,$identity,$_POST);});redirect('/research/'.$r['slug'].'/authorize/'.$task['id']);}catch(HttpError $e){http_response_code($e->status);$error=$e->getMessage();}}
    elseif($method!=='GET')fail(405,'Use GET or POST.');$targetDigest=ri_review_target_digest($r,$task,$actor,ri_actor_identity($actor));render('volunteer-review-attestation',['title'=>'Review account separation','r'=>$r,'task'=>$task,'targetDigest'=>$targetDigest,'error'=>$error]);return true;
}
