<?php
/** Byte-exact transport only. No method in this module records scientific results. */
const RTX_VERSION = 'llm-transport-v1';
const RTX_PART_BYTES = 16384;
const RTX_MAX_BYTES = 4194304;
const RTX_RETENTION = 604800;
const RTX_OWNER_BYTES = 16777216;
const RTX_SITE_BYTES = 134217728;
function rtx_json(mixed $value): string { return json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_THROW_ON_ERROR); }
function rtx_utf8(string $body): void { if(preg_match('//u',$body)!==1||str_contains($body,"\0"))fail(422,'Transport requires complete UTF-8 text without NUL bytes; no bytes were repaired.'); }
/** Split on UTF-8 boundaries; lengths always mean bytes, never characters or tokens. */
function rtx_parts(string $body): array {
    rtx_utf8($body);
    if(strlen($body)<1||strlen($body)>RTX_MAX_BYTES)fail(413,'Transport accepts 1–4194304 UTF-8 bytes. Scope the work; nothing is truncated.');
    $parts=[];$offset=0;$length=strlen($body);
    while($offset<$length){
        $size=min(RTX_PART_BYTES,$length-$offset);
        while($offset+$size<$length && (ord($body[$offset+$size])&0xc0)===0x80)$size--;
        $parts[]=substr($body,$offset,$size);$offset+=$size;
    }
    return $parts;
}
/** Explicit allowlist: private credentials/actor/session/grant IDs never enter a manifest. */
function rtx_public_binding(array $binding): array {
    return array_intersect_key($binding,array_flip(['research_id','task_id','task_revision','context_id','submission_key','archive_id','schema_version','dependency_digest']));
}
function rtx_manifest(string $body,array $binding,string $partBase=''): array {
    $parts=rtx_parts($body);$descriptors=[];
    foreach($parts as $index=>$part){$entry=['index'=>$index,'byte_length'=>strlen($part),'sha256'=>hash('sha256',$part)];if($partBase!=='')$entry['url']=$partBase.'/'.$index;$descriptors[]=$entry;}
    return ['version'=>RTX_VERSION,'encoding'=>'UTF-8','length_unit'=>'bytes','part_limit'=>RTX_PART_BYTES,'binding'=>rtx_public_binding($binding),'part_count'=>count($parts),'byte_length'=>strlen($body),'sha256'=>hash('sha256',$body),'parts'=>$descriptors,'end_marker'=>'END LLM TRANSPORT '.hash('sha256',$body)];
}
function rtx_validate_manifest(array $manifest,array $binding): array {
    if(($manifest['version']??'')!==RTX_VERSION||($manifest['encoding']??'')!=='UTF-8'||($manifest['length_unit']??'')!=='bytes')fail(422,'Use the current UTF-8 byte transport manifest version.');
    if(!is_array($manifest['binding']??null)||rtx_json($manifest['binding'])!==rtx_json(rtx_public_binding($binding))) {
        // JSON object ordering is not an identity or authorization boundary.
        if(($manifest['binding']??null)!=rtx_public_binding($binding))fail(409,'Transport binding differs from this task/revision/context/nonce. Preserve the issued assignment.');
    }
    $count=$manifest['part_count']??null;$length=$manifest['byte_length']??null;
    if(!is_int($count)||$count<1||$count>512||!is_int($length)||$length<1||$length>RTX_MAX_BYTES||!is_string($manifest['sha256']??null)||!preg_match('/^[a-f0-9]{64}$/D',$manifest['sha256']))fail(422,'Manifest needs a bounded part count, total byte length and SHA-256.');
    $parts=$manifest['parts']??null;
    if(!is_array($parts)||!array_is_list($parts)||count($parts)!==$count)fail(422,'Manifest part count does not match its ordered descriptors.');
    $sum=0;$clean=[];
    foreach($parts as $i=>$part){
        if(!is_array($part)||($part['index']??null)!==$i||!is_int($part['byte_length']??null)||$part['byte_length']<1||$part['byte_length']>RTX_PART_BYTES||!is_string($part['sha256']??null)||!preg_match('/^[a-f0-9]{64}$/D',$part['sha256']))fail(422,'Manifest parts must have unique contiguous ordered indexes, byte lengths and SHA-256 values.');
        $sum+=$part['byte_length'];$clean[]=['index'=>$i,'byte_length'=>$part['byte_length'],'sha256'=>$part['sha256']];
    }
    if($sum!==$length||($manifest['end_marker']??'')!=='END LLM TRANSPORT '.$manifest['sha256'])fail(422,'Manifest total length or completion marker is missing/inconsistent. Do not transfer partial output.');
    return ['version'=>RTX_VERSION,'encoding'=>'UTF-8','length_unit'=>'bytes','part_limit'=>RTX_PART_BYTES,'binding'=>rtx_public_binding($binding),'part_count'=>$count,'byte_length'=>$length,'sha256'=>$manifest['sha256'],'parts'=>$clean,'end_marker'=>$manifest['end_marker']];
}
function rtx_verify_part(array $manifest,int $index,string $body): void {
    $part=$manifest['parts'][$index]??null;
    if(!$part)fail(422,'Unknown transport part index. Use the manifest order.');
    rtx_utf8($body);
    if(strlen($body)!==$part['byte_length']||!hash_equals($part['sha256'],hash('sha256',$body)))fail(422,'Part '.$index.' byte length or digest differs. Retrieve/retry the original complete bytes; never reconstruct scientific text.');
}
function rtx_verify(array $manifest,array $parts): string {
    if(count($parts)!==$manifest['part_count']||array_keys($parts)!==range(0,$manifest['part_count']-1))fail(409,'Missing, duplicated or reordered parts. Resume the declared indexes before finalizing.');
    foreach($parts as $index=>$part)rtx_verify_part($manifest,$index,$part);
    $body=implode('',$parts);
    if(strlen($body)!==$manifest['byte_length']||!hash_equals($manifest['sha256'],hash('sha256',$body)))fail(422,'Whole transport digest/byte length failed. Nothing was finalized.');
    return $body;
}
function rtx_binding(array $r,array $g): array {
    if(!$g['task_id']||($g['scope']['kind']??'')!=='task')fail(422,'Resumable task transport requires a scoped task assignment.');
    return ['research_id'=>$r['id'],'task_id'=>$g['task_id'],'task_revision'=>(int)$g['scope']['revision'],'context_id'=>$g['scope']['context_id'],'submission_key'=>$g['scope']['submission_key'],'archive_id'=>$g['scope']['archive_id'],'schema_version'=>function_exists('wf_packet_contract')?wf_packet_contract(wf_task($r['id'],$g['task_id']),wf_read_snapshot($r['id'],$g['scope']['context_id']))['version']:'legacy'];
}
/** Recovery validates credentials and source visibility, separately from research permission. */
function rtx_authorize(array $r,array $g,?array $session=null): array {
    if($session){$session=vs_read($session['id']);$assignment=one('SELECT * FROM volunteer_assignments WHERE session_id=? AND return_id=?',[$session['id'],$g['id']])??fail(403,'This return is outside the private session.');$g=vs_assignment_grant($session,$assignment);}
    else $g=ar_read($r,$g['id'],true,false,true);
    if(!empty($g['scope']['volunteer_session_id'])){
        $bound=vs_read($g['scope']['volunteer_session_id'],false);
        if(!hash_equals($bound['actor_hash'],$g['actor_hash']))fail(403,'The draft owner binding is no longer valid.');
        $session=$bound;
    }
    if(function_exists('sr_authority')){
        $authority=sr_authority($r,$g['id'],false);
        if(isset($authority['grant']))$g=$authority['grant'];
    }
    wf_read_handoff($r['id'],$g['scope']['archive_id']??'');
    return ['grant'=>$g,'session'=>$session];
}
function rtx_write_permission(array $r,array $g,?array $session): void {
    if(function_exists('sr_authority'))sr_authority($r,$g['id'],true);
    if($session)vs_assert_active($session);
    if($g['expires_at']!==null&&$g['expires_at']<=now())fail(409,'Authorization expired. Existing private drafts remain recoverable for seven days; recovery grants no research or submission permission.');
    if($g['result_id'])fail(409,'This assignment already has a receipt. Recover that receipt before any retry.');
    $current=wf_task($r['id'],$g['task_id']);
    if((int)$current['revision']!==(int)$g['scope']['revision'])fail(409,'This task revision changed. Recover existing bytes privately and reassess under a fresh authorized assignment.');
    if(($g['consent']['terms_version']??'')!==terms_version())fail(409,'Terms changed. Recover existing work privately; fresh authorization is required for further work.');
}
function rtx_prune(): int { return query('DELETE FROM research_transports WHERE expires_at<=?',[now()])->rowCount(); }
function rtx_begin(array $r,array $g,string $direction,?array $manifest=null,?array $session=null): array {
    $auth=rtx_authorize($r,$g,$session);$g=$auth['grant'];$session=$auth['session'];rtx_write_permission($r,$g,$session);
    if(!in_array($direction,['input','output'],true))fail(422,'Choose input or output transport.');
    $binding=rtx_binding($r,$g);$body=null;
    if($direction==='input'){
        $body=wf_read_handoff($r['id'],$binding['archive_id'])['body'];$manifest=rtx_manifest($body,$binding);
        $directory=tc_build(wf_task($r['id'],$g['task_id']),wf_read_snapshot($r['id'],$binding['context_id']))['manifest'];
        $manifest['required_resources']=array_map(fn($entry)=>['name'=>$entry['name'],'digest'=>$entry['digest'],'byte_length'=>$entry['byte_length'],'manifest_url'=>$entry['manifest_url']],$directory['required']);
        if($directory['full_public_history']['required_for_planning'])$manifest['required_resources'][]=['name'=>'complete_public_history','digest'=>$directory['full_public_history']['sha256'],'byte_length'=>$directory['full_public_history']['byte_length'],'manifest_url'=>$directory['full_public_history']['transport_manifest']];
        $manifest['context_directory_sha256']=$directory['directory_sha256'];
    }
    else $manifest=rtx_validate_manifest($manifest??[],$binding);
    return tx(function()use($r,$g,$session,$direction,$binding,$manifest,$body){
        // The shared actor lock precedes the parent lock and serializes per-owner quota/resume.
        wr_actor_lock($g['actor_hash'],true);one('SELECT id FROM research WHERE id=? FOR UPDATE',[$r['id']]);
        $auth=rtx_authorize($r,$g,$session);$g=$auth['grant'];$session=$auth['session'];rtx_write_permission($r,$g,$session);rtx_prune();
        $encoded=rtx_json($manifest);
        $existing=one('SELECT * FROM research_transports WHERE grant_id=? AND direction=? AND manifest=? AND expires_at>? ORDER BY created_at DESC LIMIT 1',[$g['id'],$direction,$encoded,now()]);
        if($existing)return rtx_status($existing);
        $quota=one('SELECT COUNT(*) AS streams,COALESCE(SUM(byte_length),0) AS bytes FROM research_transports WHERE owner_actor_hash=?',[$g['actor_hash']]);
        if((int)$quota['streams']>=8||(int)$quota['bytes']+$manifest['byte_length']>RTX_OWNER_BYTES)fail(429,'Private recovery quota reached (8 streams / 16 MiB per owner). Delete an unneeded draft or recover it before retrying.');
        // Single shared budget row serializes site quota, without retaining private content in logs.
        $key=hash('sha256','research-transport-quota');query('INSERT INTO rate_limits(bucket,hits,expires) VALUES (?,0,?) ON DUPLICATE KEY UPDATE hits=hits',[$key,time()+RTX_RETENTION]);one('SELECT hits FROM rate_limits WHERE bucket=? FOR UPDATE',[$key]);
        if((int)one('SELECT COALESCE(SUM(byte_length),0) AS bytes FROM research_transports')['bytes']+$manifest['byte_length']>RTX_SITE_BYTES)fail(503,'Private recovery storage is full. No partial draft was created.');
        $row=['id'=>uid(),'research_id'=>$r['id'],'task_id'=>$g['task_id'],'grant_id'=>$g['id'],'owner_actor_hash'=>$g['actor_hash'],'credential_hash'=>$g['secret_hash'],'session_id'=>$session['id']??null,'session_credential_hash'=>$session['secret_hash']??null,'direction'=>$direction,'binding'=>rtx_json($binding),'manifest'=>$encoded,'dependency_ack'=>'[]','byte_length'=>$manifest['byte_length'],'state'=>'receiving','created_at'=>now(),'expires_at'=>gmdate('Y-m-d H:i:s',time()+RTX_RETENTION)];
        insert('research_transports',$row);
        if($body!==null)foreach(rtx_parts($body) as $index=>$part)insert('research_transport_parts',['transport_id'=>$row['id'],'part_no'=>$index,'byte_length'=>strlen($part),'sha256'=>hash('sha256',$part),'body'=>$part]);
        return rtx_status($row);
    });
}
function rtx_read(array $r,array $g,string $id,?array $session=null,bool $lock=false): array {
    $auth=rtx_authorize($r,$g,$session);$g=$auth['grant'];$session=$auth['session'];
    $row=one('SELECT * FROM research_transports WHERE id=? AND research_id=? AND grant_id=?'.($lock?' FOR UPDATE':''),[$id,$r['id'],$g['id']])??fail(404,'Private draft not found or removed.');
    if($row['expires_at']<=now())fail(410,'The seven-day private recovery window ended. No retained draft is available.');
    if(!hash_equals($row['owner_actor_hash'],$g['actor_hash'])||!hash_equals($row['credential_hash'],$g['secret_hash'])||($row['session_id']!==null&&(!$session||$row['session_id']!==$session['id']||!hash_equals($row['session_credential_hash'],$session['secret_hash']))))fail(403,'This draft credential was revoked or belongs to another owner.');
    return $row;
}
function rtx_status(array $row): array {
    $parts=all('SELECT part_no,acknowledged_at FROM research_transport_parts WHERE transport_id=? ORDER BY part_no',[$row['id']]);
    return ['id'=>$row['id'],'direction'=>$row['direction'],'state'=>$row['state'],'expires_at_utc'=>$row['expires_at'],'manifest'=>json_decode($row['manifest'],true,32,JSON_THROW_ON_ERROR),'acknowledged_resources'=>json_decode($row['dependency_ack']??'[]',true),'received'=>array_map(fn($p)=>(int)$p['part_no'],$parts),'acknowledged'=>array_map(fn($p)=>(int)$p['part_no'],array_values(array_filter($parts,fn($p)=>$p['acknowledged_at']!==null))),'notice'=>'Private transport checkpoint only. Finalize never checks, submits, renews or extends research authority.'];
}
function rtx_put(array $r,array $g,string $id,int $index,string $body,?array $session=null): array {
    return tx(function()use($r,$g,$id,$index,$body,$session){
        $row=rtx_read($r,$g,$id,$session,true);rtx_write_permission($r,$g,!empty($g['scope']['volunteer_session_id'])?vs_read($g['scope']['volunteer_session_id'],false):$session);
        if($row['direction']!=='output')fail(409,'Input streams are immutable; acknowledge retrieved original parts.');
        $manifest=json_decode($row['manifest'],true);rtx_verify_part($manifest,$index,$body);
        $existing=one('SELECT body FROM research_transport_parts WHERE transport_id=? AND part_no=?',[$id,$index]);
        if($existing){if(!hash_equals($existing['body'],$body))fail(409,'Conflicting duplicate part. Start a new output revision; never overwrite an acknowledged part.');return rtx_status($row);}
        if($row['state']!=='receiving')fail(409,'This stream is finalized. Use a new manifest for same-worker repairs.');
        insert('research_transport_parts',['transport_id'=>$id,'part_no'=>$index,'byte_length'=>strlen($body),'sha256'=>hash('sha256',$body),'body'=>$body,'acknowledged_at'=>now()]);
        return rtx_status($row);
    });
}
function rtx_ack(array $r,array $g,string $id,int $index,string $digest,?array $session=null): array {
    return tx(function()use($r,$g,$id,$index,$digest,$session){$row=rtx_read($r,$g,$id,$session,true);$manifest=json_decode($row['manifest'],true);$part=$manifest['parts'][$index]??null;
        if(!$part||!hash_equals($part['sha256'],$digest)||!one('SELECT transport_id FROM research_transport_parts WHERE transport_id=? AND part_no=?',[$id,$index]))fail(422,'Receiver acknowledgment does not match a complete original part.');
        query('UPDATE research_transport_parts SET acknowledged_at=COALESCE(acknowledged_at,?) WHERE transport_id=? AND part_no=?',[now(),$id,$index]);return rtx_status($row);});
}
function rtx_ack_resource(array $r,array $g,string $id,string $digest,?array $session=null): array {
    return tx(function()use($r,$g,$id,$digest,$session){$row=rtx_read($r,$g,$id,$session,true);$status=rtx_status($row);
        $allowed=array_column($status['manifest']['required_resources']??[],'digest');
        if($row['direction']!=='input'||!in_array($digest,$allowed,true))fail(422,'The resource acknowledgment is outside this pinned required context.');
        $ack=$status['acknowledged_resources'];if(!in_array($digest,$ack,true)){$ack[]=$digest;query('UPDATE research_transports SET dependency_ack=? WHERE id=?',[rtx_json($ack),$id]);$row['dependency_ack']=rtx_json($ack);}return rtx_status($row);});
}
function rtx_body(array $row): string {
    $parts=all('SELECT part_no,body FROM research_transport_parts WHERE transport_id=? ORDER BY part_no',[$row['id']]);$ordered=[];foreach($parts as $part)$ordered[(int)$part['part_no']]=$part['body'];return rtx_verify(json_decode($row['manifest'],true),$ordered);
}
function rtx_finalize(array $r,array $g,string $id,?array $session=null): array {
    return tx(function()use($r,$g,$id,$session){$row=rtx_read($r,$g,$id,$session,true);$status=rtx_status($row);
        if(count($status['acknowledged'])!==$status['manifest']['part_count'])fail(409,'Receiver has not acknowledged every part. Resume the missing indexes; no research or submission is authorized by partial transport.');
        if(array_diff(array_column($status['manifest']['required_resources']??[],'digest'),$status['acknowledged_resources']))fail(409,'Required dependency resources have not all been verified and acknowledged. Research must wait for complete context.');
        $body=rtx_body($row);
        if($row['direction']==='output')rtx_packet_binding($body,json_decode($row['binding'],true));
        query("UPDATE research_transports SET state='finalized',finalized_at=COALESCE(finalized_at,?) WHERE id=?",[now(),$id]);$row['state']='finalized';return rtx_status($row);});
}
function rtx_packet_binding(string $body,array $binding): array {
    try{$packet=json_decode($body,true,64,JSON_THROW_ON_ERROR);}catch(JsonException){fail(422,'Complete transport received, but output is not one complete JSON packet. Return the exact bytes to the same worker for repair.');}
    if(!is_array($packet)||array_is_list($packet))fail(422,'Output must be one complete JSON object.');
    foreach(['task_revision','context_id','submission_key'] as $key)if((string)($packet[$key]??'')!==(string)$binding[$key])fail(409,'Final output identifiers differ from the original signed assignment. Nothing was submitted.');
    return $packet;
}
/** Side-effect-free recovery projection; never call sr_authority here (its status calls us). */
function rtx_recovery_rows(array $binding): array {
    foreach(['research_id','task_id','grant_id','context_id','submission_key','archive_id','owner_actor_hash'] as $key)if(!is_string($binding[$key]??null))return [];
    try{
        $r=research($binding['research_id']);$g=ar_read($r,$binding['grant_id'],false);$session=null;if(!empty($g['revoked_at']))return [];
        $proof=function_exists('sr_return_access')?sr_return_access($g):(is_string($_SESSION['browser_returns'][$g['id']]??null)&&hash_equals($g['secret_hash'],$_SESSION['browser_returns'][$g['id']]));
        $proof=$proof||hash_equals($g['actor_hash'],wf_actor());
        if(!empty($g['scope']['volunteer_session_id'])){
            $session=vs_read($g['scope']['volunteer_session_id'],false);
            try{$controller=vs_read($session['id']);$assignment=vs_assignment($controller,$g['scope']['volunteer_assignment_id']);$bound=vs_assignment_grant($controller,$assignment);if($bound['id']===$g['id'])$proof=true;}catch(HttpError){}
        }
        if(!$proof||!hash_equals($g['actor_hash'],$binding['owner_actor_hash'])||$g['task_id']!==$binding['task_id'])return [];
        foreach(['context_id','submission_key','archive_id'] as $key)if(($g['scope'][$key]??null)!==$binding[$key])return [];
        if((int)$g['scope']['revision']!==(int)($binding['task_revision']??0)||($g['scope']['volunteer_session_id']??null)!==($binding['session_id']??null))return [];
        return all('SELECT id,direction,state FROM research_transports WHERE grant_id=? AND task_id=? AND owner_actor_hash=? AND credential_hash=? AND ((session_id IS NULL AND ? IS NULL) OR (session_id=? AND session_credential_hash=?)) AND expires_at>?',[$g['id'],$g['task_id'],$g['actor_hash'],$g['secret_hash'],$session['id']??null,$session['id']??null,$session['secret_hash']??null,now()]);
    }catch(HttpError){return [];}
}
function rtx_draft_available(array $binding): bool {return count(rtx_recovery_rows($binding))>0;}
function rtx_recovery_url(array $binding): ?string {
    if(!rtx_draft_available($binding))return null;
    $r=research($binding['research_id']);
    if(!empty($binding['session_id'])){
        try{$session=vs_read($binding['session_id']);$assignment=one('SELECT id FROM volunteer_assignments WHERE session_id=? AND return_id=? AND research_id=? AND task_id=?',[$session['id'],$binding['grant_id'],$r['id'],$binding['task_id']]);if($assignment)return '/volunteer/'.$session['id'].'/assignments/'.$assignment['id'].'/transport';}catch(HttpError){}
    }
    return '/research/'.$r['slug'].'/return/'.$binding['grant_id'].'/transport';
}
/** Existing Check/Submit routes use these bytes; all authority/idempotency remains there. */
function rtx_packet_input(array $r,array $task,?array $grant,array $post,?array $session=null): string {
    if(empty($post['transport_id']))return text_field($post,'payload',2,RTX_MAX_BYTES);
    if(!$grant)fail(403,'Private transport requires the authorized assignment return.');
    $id=text_field($post,'transport_id',32,32);$row=rtx_read($r,$grant,$id,$session);
    if($row['task_id']!==$task['id']||$row['direction']!=='output'||$row['state']!=='finalized')fail(409,'Select a finalized complete output for this assigned task.');
    return rtx_body($row);
}