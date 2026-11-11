<?php
const VN_FIELDS=['hypothesis','findings','attempted_approaches','open_questions','next_step'];
const VN_RETENTION=604800;
/** Notes are private working data, never evidence or permission. */
function vn_normalize(array $input): array {
    if(array_diff(array_keys($input),VN_FIELDS))fail(422,'Use only the documented research note fields.');
    $notes=[];
    foreach(VN_FIELDS as $field)$notes[$field]=text_field($input,$field,0,4000);
    return $notes;
}
function vn_read(array $session,array $assignment): array {
    $g=vs_assignment_grant($session,$assignment);$r=research($g['research_id']);
    rtx_authorize($r,$g,$session);
    $row=one('SELECT * FROM volunteer_research_notes WHERE assignment_id=?',[$assignment['id']]);
    if(!$row){$expiry=gmdate('Y-m-d H:i:s',strtotime($assignment['created_at'].' UTC')+VN_RETENTION);return ['version'=>0,'notes'=>array_fill_keys(VN_FIELDS,''),'expires_at_utc'=>$expiry,'expired'=>$expiry<=now()];}
    if(!hash_equals($g['secret_hash'],$row['grant_hash'])||!hash_equals($session['secret_hash'],$row['session_hash']))fail(403,'This saved work belongs to a revoked credential.');
    if($row['expires_at']<=now())return ['version'=>(int)$row['version'],'notes'=>array_fill_keys(VN_FIELDS,''),'expires_at_utc'=>$row['expires_at'],'expired'=>true];
    return ['version'=>(int)$row['version'],'notes'=>json_decode($row['notes'],true,16,JSON_THROW_ON_ERROR),'updated_at_utc'=>$row['updated_at'],'expires_at_utc'=>$row['expires_at']];
}
function vn_save(string $sid,string $aid,array $input,int $expected): array {
    $notes=vn_normalize($input);
    return vs_locked($sid,function($session)use($aid,$notes,$expected){
        $a=vs_assignment($session,$aid);$g=vs_assignment_grant($session,$a);$r=research($g['research_id']);
        one('SELECT id FROM research WHERE id=? FOR UPDATE',[$r['id']]);
        $auth=rtx_authorize($r,$g,$session);rtx_write_permission($r,$auth['grant'],$session);
        vs_private_packet($session,$g,$notes);
        $current=vn_read($session,$a);$row=one('SELECT * FROM volunteer_research_notes WHERE assignment_id=? FOR UPDATE',[$aid]);
        if(!empty($current['expired']))fail(410,'The seven-day saved-work window ended. Keep any local copy and use a fresh assignment.');
        $encoded=json_encode($notes,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        if($expected!==$current['version']){
            if($row&&$row['notes']===$encoded&&$expected===$current['version']-1)return $current;
            fail(409,'Saved work changed. Read the latest version before saving; no notes were overwritten.');
        }
        if($row)query('UPDATE volunteer_research_notes SET version=version+1,notes=?,updated_at=? WHERE assignment_id=?',[$encoded,now(),$aid]);
        else insert('volunteer_research_notes',['assignment_id'=>$aid,'research_id'=>$r['id'],'grant_hash'=>$g['secret_hash'],'session_hash'=>$session['secret_hash'],'version'=>1,'notes'=>$encoded,'created_at'=>now(),'updated_at'=>now(),'expires_at'=>gmdate('Y-m-d H:i:s',strtotime($a['created_at'].' UTC')+VN_RETENTION)]);
        return vn_read($session,$a);
    });
}
