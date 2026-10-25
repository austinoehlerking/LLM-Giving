<?php
/** Local synthetic browser fixtures only; never a real volunteer/model run. */
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';session_start_safe();$_SESSION['admin']=1;
$action=$argv[1]??'';
if($action==='create'){$r=create_research(['title'=>'[QA] Startup browser '.uid(),'question'=>'Synthetic task for the real browser pickup, Check, Submit and receipt lifecycle. No scientific findings or actual model provenance are claimed.','category_id'=>1,'terms'=>1]);moderate('request',$r['id'],'Approved','Synthetic startup browser fixture.');echo json_encode(['id'=>$r['id']]);exit;}
$id=$argv[2]??'';
if($action==='purge'){if(!one("SELECT id FROM research WHERE id=? AND title LIKE '[QA] Startup browser %'",[$id]))exit(2);foreach(all("SELECT id FROM volunteer_sessions WHERE scope_kind='research' AND scope_id=?",[$id]) as $s){query('DELETE FROM volunteer_sessions WHERE id=?',[$s['id']]);}purge_request($id,$id);exit;}
$s=vs_read($id,false);if($s['scope_kind']!=='research'||!one("SELECT id FROM research WHERE id=? AND title LIKE '[QA] Startup browser %'",[$s['scope_id']]))exit(2);
if($action==='inspect'){echo json_encode(['assignments'=>(int)$s['assignments_used'],'results'=>(int)one('SELECT COUNT(*) AS n FROM workflow_submissions WHERE research_id=?',[$s['scope_id']])['n'],'declaration'=>$s['consent']['human_disclosure']['level']]);exit;}
if($action==='packet'){$a=vs_current($s);$g=vs_assignment_grant($s,$a);$t=wf_task($a['research_id'],$a['task_id']);$d=wf_test_payload($a['research_id'],$t);foreach(['model','reasoning','tool','workflow_mode'] as $field)$d['metadata'][$field]=$s['capabilities'][$field];$d['submission_key']=$g['scope']['submission_key'];$d['context_id']=$g['scope']['context_id'];$d['task_revision']=$g['scope']['revision'];$d['handoff_code']=wf_handoff_code($t,wf_read_snapshot($a['research_id'],$d['context_id']),$d['submission_key']);echo json_encode($d);exit;}
exit(2);
