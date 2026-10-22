<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/resources-fixture.php';require __DIR__.'/fork-fixture.php';
session_start_safe();$_SESSION['admin']=1;$_SERVER['REMOTE_ADDR']='fork-resources-'.uid();$ids=[];$checks=0;
function fr_ok(bool $value,string $name):void{global $checks;if(!$value)throw new RuntimeException($name);$checks++;}
function fr_deny(callable $f,string $name):void{try{$f();}catch(HttpError $e){fr_ok($e->status===404,$name);return;}throw new RuntimeException($name);}
try{
 $p=rs_test_create();$ids[]=$p['id'];$draft=rf_prepare($p['id']);$bg=$draft['fork']['background'];$json=rf_json($bg);
 $resource=$bg['resources']['sources'][0]['contributions'][0]['source'];
 fr_ok(str_contains($json,'Original synthetic mass table'),'Original source and extraction retained');
 fr_ok(str_contains($json,'LLM.Giving synthetic QA fixture')&&str_contains($json,'synthetic-v1'),'Source authors, publisher and exact edition retained');
 fr_ok(str_contains($json,'mass = 2 kg')&&str_contains($json,'mass_kg'),'Source location, extraction and derived input reference retained');
 fr_ok(str_contains($json,'continuation.txt'),'Retained supporting material identified');
 fr_ok(!str_contains($json,'Useful synthetic intermediate notes: mass=2 kg')&&!str_contains($json,'<?php echo 2 * 1000;'),'Supporting file bytes are not copied');
 fr_ok(str_contains($json,'Synthetic testing only')||str_contains($json,'Synthetic fixture only'),'Original methodological limits retained');
 $child=rf_create($p['id'],rf_test_child_data($draft));$cid=$child['id'];$ids[]=$cid;moderate('request',$cid,'Approved','Resource fork fixture.');
 $s=wf_snapshot($cid);$h=wf_issue_handoff($cid);
 fr_ok(context_data($cid)['fork']['background']['resources']===$bg['resources'],'Pinned resources unchanged through publication and context');
 query("UPDATE contributions SET moderation='Removed' WHERE id=?",[$p['contribution_id']]);
 fr_deny(fn()=>context_data($cid),'Removed supplementary source body cannot leak even without blanket snapshot revocation');
 query("UPDATE contributions SET moderation='Approved' WHERE id=?",[$p['contribution_id']]);
 fr_ok(research($cid)['id']===$cid,'Restored source record eligible again');
 query("UPDATE workflow_submissions SET visibility='private' WHERE id=?",[$p['progress_id']]);
 fr_deny(fn()=>wf_read_snapshot($cid,$s['id']),'Hidden source progress blocks old inherited resource package');
 query("UPDATE workflow_submissions SET visibility='public' WHERE id=?",[$p['progress_id']]);
 query("UPDATE workflow_submissions SET admission='quarantined' WHERE id=?",[$p['result_id']]);
 fr_deny(fn()=>wf_read_handoff($cid,$h['id']),'Quarantined source cannot leak through inherited handoff');
 query("UPDATE workflow_submissions SET admission='accepted' WHERE id=?",[$p['result_id']]);
 query("UPDATE workflow_tasks SET visibility='private' WHERE id=?",[$p['task_id']]);
 fr_deny(fn()=>research($cid),'Hidden source task blocks descendant serving');
 query("UPDATE workflow_tasks SET visibility='public' WHERE id=?",[$p['task_id']]);
 moderate('request',$cid,'Removed','Remove synthetic child.');
 fr_deny(fn()=>context_data($cid),'Child removal applies independently');
 fr_ok(!rf_children($p['id']),'Removed child omitted from parent links');
 fr_ok(research($p['id'])['id']===$p['id'],'Child removal does not change parent');
 echo $checks." fork resource/material visibility checks passed.
";
}finally{foreach(array_reverse($ids) as $id)if(one('SELECT id FROM research WHERE id=?',[$id]))purge_request($id,$id);}
