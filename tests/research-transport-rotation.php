<?php
/** CR53-1 only: replacement proof never adopts revoked checkpoint bytes. */
require __DIR__.'/bootstrap.php';session_start_safe();$_SESSION['admin']=1;$checks=[];$rid=null;$sid=null;
function rrc(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
function rrd(callable $fn,string $label):void{try{$fn();}catch(HttpError $error){rrc($error->status===403,$label);return;}throw new RuntimeException('Expected revoked proof rejection: '.$label);}
function rr_pair(array $r,array $g,?array $session=null):array{
 $binding=rtx_binding($r,$g);$body=rtx_json(['task_revision'=>$binding['task_revision'],'context_id'=>$binding['context_id'],'submission_key'=>$binding['submission_key'],'body'=>'Synthetic credential rotation only; no scientific investigation or submission.']);$manifest=rtx_manifest($body,$binding);
 $output=rtx_begin($r,$g,'output',$manifest,$session);$input=rtx_begin($r,$g,'input',null,$session);rtx_put($r,$g,$output['id'],0,rtx_parts($body)[0],$session);return compact('body','manifest','output','input');
}
function rr_current(array $r,array $g,array $old,string $label,?array $session=null):array{
 $output=rtx_begin($r,$g,'output',$old['manifest'],$session);rrc($output['id']!==$old['output']['id'],$label.' replacement proof creates a fresh identical-manifest output checkpoint');rrc($output['received']===[],$label.' replacement does not adopt old uploaded bytes');
 $input=rtx_begin($r,$g,'input',null,$session);rrc($input['id']!==$old['input']['id'],$label.' replacement creates fresh original-input checkpoint');rrc(rtx_begin($r,$g,'output',$old['manifest'],$session)['id']===$output['id'],$label.' current proof repeatedly resumes a usable checkpoint');
 rrd(fn()=>rtx_read($r,$g,$old['output']['id'],$session),$label.' current proof cannot read revoked output checkpoint');rrd(fn()=>rtx_read($r,$g,$old['input']['id'],$session),$label.' current proof cannot read revoked input checkpoint');return $output;
}
function rr_finish(array $r,array $g,array $stream,array $old,string $label,?array $session=null):void{
 foreach(rtx_parts($old['body']) as $index=>$part)rtx_put($r,$g,$stream['id'],$index,$part,$session);
 rrc(rtx_finalize($r,$g,$stream['id'],$session)['state']==='finalized',$label.' replacement uploads/finalizes successfully');rrc(rtx_body(rtx_read($r,$g,$stream['id'],$session))===$old['body'],$label.' finalized bytes equal unchanged original authored packet');
}
try{
 $created=create_research(['title'=>'[QA] CR53-1 credential rotation '.uid(),'question'=>'Synthetic current-credential checkpoint reuse and revocation regression only.','category_id'=>1,'terms'=>1]);$rid=$created['id'];moderate('request',$rid,'Approved','Synthetic credential-rotation regression.');$r=research($rid);$task=wf_tasks($rid)[0];
 $issued=ar_issue($r,$task['id'],['authorize'=>1,'terms'=>1,'policy_version'=>terms_version(),'human_level'=>'none']);preg_match('~/return/([a-f0-9]{32})#([a-f0-9]{64})~',$issued['handoff'],$match);$issuerActor=$_SESSION['workflow_actor'];
 // A separate receiver has return proof, never the issuer's original actor capability.
 $_SESSION['workflow_actor']=bin2hex(random_bytes(32));$_SESSION['browser_returns'][$match[1]]=hash('sha256',$match[2]);$grant=ar_read($r,$match[1]);$old=rr_pair($r,$grant);$oldHash=$grant['secret_hash'];
 $newHash=hash('sha256',bin2hex(random_bytes(32)));query('UPDATE browser_returns SET secret_hash=? WHERE id=?',[$newHash,$grant['id']]);$_SESSION['browser_returns'][$grant['id']]=$newHash;$current=ar_read($r,$grant['id']);$fresh=rr_current($r,$current,$old,'Grant');
 $_SESSION['browser_returns'][$grant['id']]=$oldHash;rrd(fn()=>rtx_begin($r,$grant,'output',$old['manifest']),'Revoked return proof cannot begin/adopt replacement');rrd(fn()=>rtx_read($r,$grant,$fresh['id']),'Revoked return proof cannot read replacement');
 $_SESSION['browser_returns'][$grant['id']]=$newHash;rr_finish($r,$current,$fresh,$old,'Grant');rrc(one('SELECT credential_hash FROM research_transports WHERE id=?',[$old['output']['id']])['credential_hash']===$oldHash,'Old grant checkpoint fingerprint remains unchanged');
 // Exercise session rotation through actual create/claim/controller authorization.
 $_SESSION['workflow_actor']=$issuerActor;wr_release($rid,$task['id'],wf_actor());$started=vs_create(['scope_kind'=>'research','research_id'=>$rid,'session_preset'=>'standard','model'=>'gpt-6-astra','reasoning'=>'max','tool'=>'Codex','authorize'=>1,'terms'=>1,'authorized_resources'=>1,'policy_version'=>terms_version(),'human_level'=>'none']);$sid=$started['id'];$assignment=vs_claim_next($sid);$session=vs_read($sid);$grant=vs_assignment_grant($session,$assignment);$old=rr_pair($r,$grant,$session);$oldHash=$session['secret_hash'];
 $newHash=hash('sha256',bin2hex(random_bytes(32)));query('UPDATE volunteer_sessions SET secret_hash=? WHERE id=?',[$newHash,$sid]);$_SESSION['volunteer_sessions'][$sid]=$newHash;$currentSession=vs_read($sid);$fresh=rr_current($r,$grant,$old,'Session',$currentSession);
 $_SESSION['volunteer_sessions'][$sid]=$oldHash;rrd(fn()=>rtx_begin($r,$grant,'output',$old['manifest'],$session),'Revoked session proof cannot begin/adopt replacement');rrd(fn()=>rtx_read($r,$grant,$fresh['id'],$session),'Revoked session proof cannot read replacement');
 $_SESSION['volunteer_sessions'][$sid]=$newHash;rr_finish($r,$grant,$fresh,$old,'Session',$currentSession);rrc(one('SELECT session_credential_hash FROM research_transports WHERE id=?',[$old['output']['id']])['session_credential_hash']===$oldHash,'Old session checkpoint fingerprint remains unchanged');
 rrc((int)one('SELECT COUNT(*) AS n FROM workflow_submissions WHERE research_id=?',[$rid])['n']===0,'Credential recovery/finalization records no research submission');echo rtx_json(['status'=>'PASS','finding'=>'CR53-1','checks'=>$checks])."\n";
}finally{if($rid)purge_request($rid,$rid);if($sid)query('DELETE FROM volunteer_sessions WHERE id=?',[$sid]);}