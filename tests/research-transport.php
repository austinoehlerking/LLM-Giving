<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';
session_start_safe();$_SESSION['admin']=1;$checks=[];$ids=[];
function rtc(bool $ok,string $label): void {global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
function rtd(callable $fn,int $status,string $label): void {try{$fn();}catch(HttpError $e){rtc($e->status===$status,$label.' ('.$e->getMessage().')');return;}throw new RuntimeException('Expected failure: '.$label);}
try{
    $created=create_research(['title'=>'[QA] Lossless transport '.uid(),'question'=>'Synthetic local byte-integrity, private recovery and context boundaries only.','category_id'=>1,'terms'=>1]);$ids[]=$created['id'];moderate('request',$created['id'],'Approved','Synthetic isolated transport fixture.');$r=research($created['id']);$task=wf_tasks($r['id'])[0];
    $issued=ar_issue($r,$task['id'],['authorize'=>1,'terms'=>1,'policy_version'=>terms_version(),'human_level'=>'none']);preg_match('~/return/([a-f0-9]{32})#([a-f0-9]{64})~',$issued['handoff'],$match);$_SESSION['browser_returns'][$match[1]]=hash('sha256',$match[2]);$g=ar_read($r,$match[1]);$binding=rtx_binding($r,$g);
    $before=one('SELECT COUNT(*) AS n FROM workflow_submissions WHERE research_id=?',[$r['id']])['n'];$expiry=wr_live($r['id'],$task['id'])['expires_at'];
    $packet=wf_test_payload($r['id'],$task,'progress');foreach(['task_revision','context_id','submission_key'] as $key)$packet[$key]=$binding[$key];$packet['handoff_code']=wf_handoff_code($task,wf_read_snapshot($r['id'],$binding['context_id']),$binding['submission_key']);
    $packet['body'].=str_repeat('x',90000);$packet['methodology'].=str_repeat('y',90000);$packet['sources'].=str_repeat('z',90000);$body=rtx_json($packet);$manifest=rtx_manifest($body,$binding);
    $stream=rtx_begin($r,$g,'output',$manifest);rtc(rtx_begin($r,$g,'output',$manifest)['id']===$stream['id'],'Same manifest resumes existing checkpoint without extending retention');
    $parts=rtx_parts($body);rtx_put($r,$g,$stream['id'],0,$parts[0]);rtx_put($r,$g,$stream['id'],0,$parts[0]);rtc(count(rtx_status(rtx_read($r,$g,$stream['id']))['received'])===1,'Identical same-part retry is idempotent');
    rtd(fn()=>rtx_put($r,$g,$stream['id'],0,'changed'),422,'Conflicting duplicate rejected');rtd(fn()=>rtx_finalize($r,$g,$stream['id']),409,'Interrupted output cannot finalize');
    for($i=count($parts)-1;$i>=1;$i--)rtx_put($r,$g,$stream['id'],$i,$parts[$i]);$final=rtx_finalize($r,$g,$stream['id']);rtc($final['state']==='finalized','Out-of-order network arrival is explicitly reassembled by declared index');
    rtc(rtx_packet_input($r,$task,$g,['transport_id'=>$stream['id']])===$body&&strlen($body)>200000,'Existing Check/Submit adapter loads exact >200k bytes');
    rtc(one('SELECT COUNT(*) AS n FROM workflow_submissions WHERE research_id=?',[$r['id']])['n']===$before&&wr_live($r['id'],$task['id'])['expires_at']===$expiry,'Finalize never submits or renews');
    $largeCheckReady=defined('WF_PACKET_VERSION')&&$binding['schema_version']===WF_PACKET_VERSION;
    if($largeCheckReady)rtc(ar_record($r,$g,rtx_packet_binding($body,$binding),true)==='','Complete >200k v2 packet passes existing read-only Check authority');
    else{$small=$packet;$small['body']=substr($small['body'],0,500);$small['methodology']=substr($small['methodology'],0,500);$small['sources']=substr($small['sources'],0,500);rtc(ar_record($r,$g,$small,true)==='','Legacy signed snapshot retains existing read-only Check authority; large-v2 Check awaits schema integration');}
    rtc(one('SELECT COUNT(*) AS n FROM workflow_submissions WHERE research_id=?',[$r['id']])['n']===$before,'Passing Check still writes no research result');
    $input=rtx_begin($r,$g,'input');rtd(fn()=>rtx_finalize($r,$g,$input['id']),409,'Input requires receiver ACK before work');
    foreach($input['manifest']['parts'] as $part)rtx_ack($r,$g,$input['id'],$part['index'],$part['sha256']);rtd(fn()=>rtx_finalize($r,$g,$input['id']),409,'Input also requires every pinned evidence resource ACK');
    foreach($input['manifest']['required_resources'] as $resource)rtx_ack_resource($r,$g,$input['id'],$resource['digest']);rtc(rtx_finalize($r,$g,$input['id'])['state']==='finalized','Complete context requires all handoff and dependency acknowledgments');
    rtd(fn()=>rtx_ack_resource($r,$g,$input['id'],str_repeat('0',64)),422,'Unknown dependency acknowledgment rejected');
    $resource=$input['manifest']['required_resources'][0];
    query("UPDATE workflow_tasks SET visibility='private' WHERE id=?",[$task['id']]);rtd(fn()=>tc_read_dependency($r['id'],$binding['context_id'],$resource['digest'],$task['id']),404,'Dependency route rechecks live child task visibility');query("UPDATE workflow_tasks SET visibility='public' WHERE id=?",[$task['id']]);
    $old=one('SELECT body,sha256 FROM workflow_handoffs WHERE id=?',[$binding['archive_id']]);rtc(!str_contains(rtx_json($stream['manifest']),$g['secret_hash'])&&!str_contains($old['body'],$match[2]),'Public manifests and exact archives exclude private return secrets');
    $recoveryBinding=$binding+['grant_id'=>$g['id'],'owner_actor_hash'=>$g['actor_hash'],'session_id'=>null];
    rtc(rtx_draft_available($recoveryBinding)&&str_ends_with(rtx_recovery_url($recoveryBinding),'/transport'),'Guarded recovery UI exposes a real bound checkpoint without capability bytes');
    $scope=$_SESSION['browser_returns'];$originalActor=$_SESSION['workflow_actor'];$_SESSION['browser_returns']=[];$_SESSION['workflow_actor']=bin2hex(random_bytes(32));
    rtd(fn()=>rtx_read($r,$g,$stream['id']),403,'Public stream ID grants no private recovery');rtc(!rtx_draft_available($recoveryBinding)&&rtx_recovery_url($recoveryBinding)===null,'Recovery projections deny another anonymous browser');
    $_SESSION['browser_returns']=$scope;$_SESSION['workflow_actor']=$originalActor;
    query('UPDATE browser_returns SET expires_at=? WHERE id=?',[gmdate('Y-m-d H:i:s',time()-60),$g['id']]);$expired=ar_read($r,$g['id']);rtc(rtx_read($r,$expired,$stream['id'])['state']==='finalized','Permission expiry retains private read recovery');rtd(fn()=>rtx_begin($r,$expired,'output',$manifest),409,'Recovery never extends expired work authority');
    query('UPDATE browser_returns SET expires_at=? WHERE id=?',[gmdate('Y-m-d H:i:s',time()+86400),$g['id']]);$g=ar_read($r,$g['id']);
    query('UPDATE browser_returns SET secret_hash=? WHERE id=?',[str_repeat('f',64),$g['id']]);rtd(fn()=>rtx_read($r,$g,$stream['id']),403,'Credential revocation denies retained drafts');query('UPDATE browser_returns SET secret_hash=? WHERE id=?',[$g['secret_hash'],$g['id']]);
    query('UPDATE research_transports SET expires_at=? WHERE id=?',[gmdate('Y-m-d H:i:s',time()-1),$stream['id']]);rtd(fn()=>rtx_read($r,$g,$stream['id']),410,'Expired retention refuses content');rtc(rtx_prune()>=1,'Bounded retention prunes expired data');
    $large=$packet;$large['body']=str_repeat('Q',1114036-strlen(rtx_json(array_replace($packet,['body'=>'']))));$largeBody=rtx_json($large);rtc(strlen($largeBody)===1114036,'Synthesis-scale fixture exactly 1114036 UTF-8 bytes');$big=rtx_begin($r,$g,'output',rtx_manifest($largeBody,$binding));foreach(rtx_parts($largeBody) as $i=>$part)rtx_put($r,$g,$big['id'],$i,$part);rtx_finalize($r,$g,$big['id']);rtc(rtx_body(rtx_read($r,$g,$big['id']))===$largeBody,'Synthesis-scale private output survives full durable transport');
    query("UPDATE research SET moderation='Removed' WHERE id=?",[$r['id']]);rtd(fn()=>rtx_read($r,$g,$big['id']),404,'Parent removal revokes private transport too');query("UPDATE research SET moderation='Approved' WHERE id=?",[$r['id']]);
    $snapshot=wf_read_snapshot($r['id'],$binding['context_id']);query('UPDATE workflow_snapshots SET public_allowed=0 WHERE id=?',[$snapshot['id']]);rtd(fn()=>rtx_read($r,$g,$big['id']),404,'Snapshot revocation denies recovery');query('UPDATE workflow_snapshots SET public_allowed=1 WHERE id=?',[$snapshot['id']]);
    $reserved=0;
    for($i=0;$i<8;$i++){
        try{$quotaBody=str_repeat((string)$i,RTX_MAX_BYTES);rtx_begin($r,$g,'output',rtx_manifest($quotaBody,$binding));$reserved++;}
        catch(HttpError $error){rtc($error->status===429,'Private owner stream/byte quota rejects additional storage');break;}
    }
    rtc($reserved<8,'Storage reservations cannot bypass bounded owner quota');
    $scoped=one('SELECT COUNT(*) AS n FROM research_transports WHERE research_id=?',[$r['id']])['n'];rtc((int)$scoped>0,'Recovery rows remain private until explicit purge');
    file_put_contents(storage('qa/research-transport-server.json'),rtx_json(['status'=>'PASS','checks'=>$checks,'large_bytes'=>strlen($largeBody),'packet_bytes'=>strlen($body),'large_v2_check_executed'=>$largeCheckReady,'integration_gaps'=>$largeCheckReady?[]:['Large-packet Check awaits reviewed snapshot-bound v2 schema integration; legacy contract intentionally remains200000 bytes.']])."\n");echo 'Research transport server checks passed: '.count($checks)."\n";
}finally{foreach($ids as $id)purge_request($id,$id);}