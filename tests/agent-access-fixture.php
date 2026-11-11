<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';
session_start_safe();$_SESSION['admin']=1;
$action=$argv[1]??'create';
if($action==='create'){
    $m=create_research(['title'=>'[QA] Agent access '.uid(),'question'=>'Synthetic session API and saved-work verification. No real scientific finding.','category_id'=>1,'terms'=>1]);
    moderate('request',$m['id'],'Approved','Private synthetic local API fixture.');
    $made=vs_create(['scope_kind'=>'research','research_id'=>$m['id'],'session_preset'=>'long','model'=>'gpt-6-astra','reasoning'=>'max','tool'=>'Codex','authorize'=>1,'terms'=>1,'authorized_resources'=>1,'policy_version'=>terms_version(),'human_level'=>'none']);
    $s=vs_read($made['id']);$token=va_enable($s['id']);
    echo json_encode(['research_id'=>$m['id'],'session_id'=>$s['id'],'link'=>$made['link'],'token'=>$token]);exit;
}
$s=vs_read($argv[2]??'',false);$_SESSION['volunteer_sessions'][$s['id']]=$s['secret_hash'];
if($action==='cleanup'){if(one('SELECT id FROM research WHERE id=?',[$s['scope_id']]))purge_request($s['scope_id'],$s['scope_id']);query('DELETE FROM volunteer_sessions WHERE id=?',[$s['id']]);echo 'removed owned fixture';exit;}
if($action==='packet'){
    $a=vs_assignment($s,$argv[3]);$g=vs_assignment_grant($s,$a);$t=wf_task($a['research_id'],$a['task_id']);
    $d=wf_test_payload($a['research_id'],$t);foreach(['model','reasoning','tool','workflow_mode'] as $key)$d['metadata'][$key]=$s['capabilities'][$key];
    foreach(['task_revision'=>'revision','context_id'=>'context_id','submission_key'=>'submission_key'] as $key=>$field)$d[$key]=$g['scope'][$field];
    $d['handoff_code']=wf_handoff_code($t,wf_read_snapshot($a['research_id'],$d['context_id']),$d['submission_key']);
    echo json_encode($d);exit;
}
if($action==='revoke'){va_revoke($s['id']);echo 'revoked';exit;}
if($action==='expire'){query('UPDATE volunteer_sessions SET expires_at=? WHERE id=?',[wr_stamp(wr_time()-1),$s['id']]);echo 'expired';exit;}
if($action==='rotate'){echo va_enable($s['id']);exit;}
if($action==='claim'){echo json_encode(vs_claim_next($s['id'],uid()));exit;}
throw new RuntimeException('Unknown fixture operation.');
