<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';require __DIR__.'/fork-fixture.php';
session_start_safe();$_SESSION['admin']=1;$_SERVER['REMOTE_ADDR']='fork-test-'.uid();
$ids=[];$checks=[];
function fork_ok(bool $value,string $name):void{global $checks;if(!$value)throw new RuntimeException($name);$checks[]=$name;}
function fork_deny(callable $f,int $status,string $name):void{try{$f();}catch(HttpError $e){fork_ok($e->status===$status,$name.': '.$e->getMessage());return;}throw new RuntimeException('Expected denial: '.$name);}
try{
    $p=rf_test_parent();$rid=$p['id'];$ids[]=$rid;
    $original=research($rid,false);$credential=one('SELECT * FROM credentials WHERE research_id=?',[$rid]);$tasks=wf_tasks($rid);
    $pending=create_contribution($rid,['kind'=>'Original research','summary'=>'Private pending source record','body'=>'PRIVATE_PENDING_FORK_BODY','terms'=>1,'metadata'=>[]]);
    $draft=rf_prepare($rid);$bg=$draft['fork']['background'];$json=rf_json($draft);
    fork_ok($draft['fork']['available'],'Public question can prepare a fork');
    fork_ok(!str_contains($json,'PRIVATE_PENDING_FORK_BODY'),'Pending contribution body never copied');
    fork_ok($bg['question']['question']===$original['question'],'Original question retained verbatim');
    fork_ok($bg['question']['display_name']==='Original synthetic requester','Requester attribution retained');
    fork_ok(count($bg['results'])===2,'Accepted plan and current progress background retained');
    fork_ok(in_array('Synthetic research contributor',array_column($bg['results'],'display_name'),true),'Contributor attribution retained');
    foreach(['private-fork@example.invalid','token_hash','actor_hash','owner_hash','nonce_hash','management_link','moderation_reason','run_evidence'] as $private)fork_ok(!str_contains($json,$private),'Private field excluded: '.$private);
    $rollbackDraft=rf_prepare($rid);$rollbackData=rf_test_child_data($rollbackDraft);
    $before=(int)one('SELECT COUNT(*) n FROM research')['n'];$tmp=storage('private/fork-upload-'.uid().'.txt');file_put_contents($tmp,'Synthetic test only');
    try{fork_deny(fn()=>rf_create($rid,$rollbackData,[['tmp'=>$tmp,'original_name'=>'synthetic.txt','mime'=>'text/plain','bytes'=>30000000,'scan_status'=>'Not scanned']]),422,'Upload failure rolls back complete fork transaction');}finally{unlink($tmp);}
    fork_ok((int)one('SELECT COUNT(*) n FROM research')['n']===$before&&!one('SELECT child_id FROM research_fork_intents WHERE nonce_hash=?',[hash('sha256',$rollbackDraft['nonce'])])['child_id'],'Failed upload leaves no child or consumed intent');
    $data=rf_test_child_data($draft);$created=rf_create($rid,$data);$child=$created['id'];$ids[]=$child;
    fork_ok($child!==$rid&&$created['link']!==$p['link'],'Independent identity and management access');
    fork_ok($created['created'],'First request creates one child');
    fork_ok(research($child,false)['moderation']==='Pending Approval','Fork awaits normal review');
    fork_ok(!wf_project($child)&&!wf_tasks($child),'No inherited workflow or completion state');
    fork_ok(!one('SELECT id FROM workflow_submissions WHERE research_id=?',[$child]),'No inherited results in child results table');
    fork_ok(research($child,false)['email']===''&&research($child,false)['display_name']==='','Own contact and attribution start blank');
    fork_ok(research($child,false)['context']==='','Inherited background stays separate from editable context');
    fork_ok(research($rid,false)===$original&&wf_tasks($rid)===$tasks&&one('SELECT * FROM credentials WHERE research_id=?',[$rid])===$credential,'Parent question, tasks and credentials unchanged');
    fork_ok(!rf_children($rid),'Pending fork title and link omitted');
    fork_deny(fn()=>research($child),404,'Pending fork not public');
    $retry=rf_create($rid,$data);
    fork_ok(!$retry['created']&&$retry['id']===$child&&$retry['link']===$created['link'],'Identical retry returns same child and management receipt');
    fork_ok((int)one('SELECT COUNT(*) n FROM research_forks WHERE parent_id=?',[$rid])['n']===1,'Retry creates no duplicate relationship');
    $changed=$data;$changed['question'].=' Different';fork_deny(fn()=>rf_create($rid,$changed),409,'Changed duplicate rejected');
    $other=rf_test_request('unapproved');$ids[]=$other['id'];
    fork_deny(fn()=>rf_create($other['id'],$data),403,'Nonce cannot move to another source');
    fork_deny(fn()=>rf_prepare($other['id']),404,'Private source cannot be forked');
    $owner=session_id();session_write_close();session_id('fork-other-'.uid());session_start();fork_deny(fn()=>rf_create($rid,$data),403,'Another session cannot replay management receipt');
    // Restore owner only in the local test process.
    session_write_close();session_id($owner);session_start();
    moderate('request',$child,'Approved','Independent child approval.');
    fork_ok(count(rf_children($rid))===1,'Public parent lists approved fork');
    fork_ok(wf_project($child)['round_no']===1&&count(wf_tasks($child))===1,'Own planning starts at approval');
    $ctx=context_data($child);fork_ok($ctx['fork']['parent']['id']===$rid,'Context records parent relationship');
    fork_ok($ctx['fork']['snapshot_id']===$draft['fork']['snapshot_id']&&$ctx['fork']['context_version']===$draft['fork']['context_version'],'Exact inherited context identity and version preserved');
    fork_ok($ctx['fork']['background_sha256']===hash('sha256',rf_json($bg)),'Pinned background checksum reproducible');
    try{query("UPDATE research_forks SET context_version=context_version+1 WHERE research_id=?",[$child]);throw new RuntimeException('Fork provenance update accepted');}catch(PDOException $e){fork_ok($e->getCode()==='45000','Database independently rejects provenance mutation');}
    fork_ok(str_contains(wf_handoff_text($child),'Inherited background evidence'),'Handoff supplies attributed background with non-result warning');
    $snap=wf_snapshot($child);$archive=wf_issue_handoff($child);
    $childTask=wf_tasks($child)[0];$packet=wf_test_payload($child,$childTask);$childResult=wf_submit($child,$childTask['id'],$packet,str_repeat('c',64));
    fork_ok((bool)$childResult,'Fork accepts own independent planning result');
    $chainDraft=rf_prepare($child);$grand=rf_create($child,rf_test_child_data($chainDraft));$grandId=$grand['id'];$ids[]=$grandId;moderate('request',$grandId,'Approved','Fork chain fixture.');
    fork_ok(context_data($grandId)['fork']['background']['inherited_background']['snapshot_id']===$draft['fork']['snapshot_id'],'Fork chain preserves exact ancestor snapshot');
    $gSnapshot=wf_snapshot($grandId);
    touch_context($rid);
    fork_ok((bool)rf_context($child)['warnings'],'Later parent changes prominently warned');
    fork_ok((bool)rf_context($grandId)['warnings'],'Later ancestor corrections visible through fork chain');
    fork_ok(rf_context($child)['background']===$bg,'Later parent changes do not rewrite inherited bytes');
    query("UPDATE workflow_tasks SET revision=revision+1,accepted_id=NULL WHERE id=?",[$p['task_id']]);
    fork_ok(count(rf_context($child)['warnings'])>=2,'Reopened historical source result explicitly flagged');
    fork_ok(research($child)['id']===$child,'Corrected historical background remains accessible with warning');
    query('UPDATE workflow_snapshots SET public_allowed=0 WHERE id=?',[$draft['fork']['snapshot_id']]);
    foreach([$child,$grandId] as $id){
        fork_deny(fn()=>research($id),404,'Source snapshot revocation blocks descendant page');
        fork_deny(fn()=>context_data($id),404,'Source snapshot revocation blocks descendant current context');
        fork_deny(fn()=>wf_handoff_text($id),404,'Source snapshot revocation blocks new descendant handoff');
    }
    fork_deny(fn()=>wf_read_snapshot($child,$snap['id']),404,'Earlier child package cannot bypass revocation');
    fork_deny(fn()=>wf_read_handoff($child,$archive['id']),404,'Exact issued archive cannot bypass revocation');
    fork_deny(fn()=>wf_read_snapshot($grandId,$gSnapshot['id']),404,'Transitive archived context cannot bypass revocation');
    fork_ok(!rf_context($child)['available']&&!isset(rf_context($child)['background']),'Private management shows unavailable notice without removed source body');
    fork_ok(!rf_children($rid),'Revoked descendants omitted from public related forks');
    query('UPDATE workflow_snapshots SET public_allowed=1 WHERE id=?',[$draft['fork']['snapshot_id']]);
    change_status($rid,'Withdrawn','Synthetic source withdrawal.','requester');
    fork_deny(fn()=>research($grandId),404,'Ancestor withdrawal blocks transitive reuse');
    fork_deny(fn()=>rf_prepare($rid),404,'Withdrawn parent cannot issue a fork');
    change_status($rid,'Open for Research','Synthetic restoration.','administrator');
    fork_ok(research($child)['id']===$child,'Restored visible source can be read again');
    rotate_token($child);fork_deny(fn()=>rf_create($rid,$data),409,'Retry cannot restore a rotated management credential');
    $newDraft=rf_prepare($rid);$newData=rf_test_child_data($newDraft);
    $waiting=rf_create($rid,$newData);$ids[]=$waiting['id'];
    purge_request($rid,$rid);
    fork_deny(fn()=>moderate('request',$waiting['id'],'Approved','Cannot publish purged source.'),404,'Publication rechecks source purge');
    fork_deny(fn()=>research($child),404,'Source purge cannot be bypassed through child');
    fork_deny(fn()=>wf_read_snapshot($grandId,$gSnapshot['id']),404,'Source purge blocks transitive immutable copies');
    fork_ok(research($child,false)['id']===$child,'Child identity remains independent after parent purge');
}
finally{
    foreach(array_reverse($ids) as $id)if(one('SELECT id FROM research WHERE id=?',[$id]))purge_request($id,$id);
}
echo count($checks)." fork creation, provenance, retry and visibility checks passed.
";
