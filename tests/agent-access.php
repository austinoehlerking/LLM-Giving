<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';session_start_safe();$_SESSION['admin']=1;
$checks=[];$projects=[];
function ac(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks[]=$message;}
function ad(callable $fn,int $code,string $message):void{try{$fn();}catch(HttpError $e){ac($e->status===$code,$message.' ('.$e->status.')');return;}throw new RuntimeException('Expected denial: '.$message);}
function ap():array{
    global $projects;unset($GLOBALS['volunteer_api_auth']);$_SESSION['admin']=1;$_SESSION['workflow_actor']=bin2hex(random_bytes(32));putenv('RATE_LIMIT_NAMESPACE=agent-'.uid());
    $m=create_research(['title'=>'[QA] Agent focused '.uid(),'question'=>'Synthetic agent API boundaries and research resumption test only.','category_id'=>1,'terms'=>1]);$projects[]=$m['id'];moderate('request',$m['id'],'Approved','Synthetic API tests.');
    $c=vs_create(['scope_kind'=>'research','research_id'=>$m['id'],'model'=>'gpt-6-astra','reasoning'=>'max','tool'=>'Codex','minutes'=>60,'assignment_limit'=>3,'terms'=>1,'authorized_resources'=>1,'authorize'=>1,'policy_version'=>terms_version(),'human_level'=>'none']);
    return [$m,vs_read($c['id']),$c];
}
try{
    [$m,$s,$c]=ap();$consent=$s['consent'];$token=va_enable($s['id']);$secret=substr($token,-64);
    ac(!str_contains(json_encode(one('SELECT * FROM volunteer_agent_credentials WHERE session_id=?',[$s['id']])),$secret),'Only hashed agent credential retained');
    ad(fn()=>va_authenticate($s['id'],'Bearer wrong'),401,'Invalid credential rejects');
    va_authenticate($s['id'],'Bearer '.$token);
    $home=va_home(vs_read($s['id']));ac($home['assignments']===[]&&(int)vs_read($s['id'])['assignments_used']===0,'GET state never claims work');
    ad(fn()=>va_dispatch($s['id'],'/claim','POST',['claim_key'=>uid(),'terms'=>1]),422,'API cannot supply consent fields');
    $key=uid();$a=vs_claim_next($s['id'],$key);$again=vs_claim_next($s['id'],$key);
    ac($a['id']===$again['id']&&(int)vs_read($s['id'])['assignments_used']===1,'Claim retry preserves assignment and allowance');
    ac(vs_read($s['id'])['consent']===$consent,'API claim preserves original consent');
    $handoff=va_dispatch($s['id'],'/assignments/'.$a['id'].'/handoff','GET',[]);
    ac(hash('sha256',$handoff['body'])===$handoff['sha256']&&!str_contains(json_encode($handoff),vs_return_secret($a['return_id'])),'API handoff is complete and contains no browser-return capability');
    $beforeLease=wr_live($m['id'],$a['task_id'])['expires_at'];$beforeResults=one('SELECT COUNT(*) n FROM workflow_submissions WHERE research_id=?',[$m['id']])['n'];
    $notes=['hypothesis'=>'Synthetic hypothesis','next_step'=>'Inspect the original fixture.'];
    $saved=vn_save($s['id'],$a['id'],$notes,0);ac($saved['version']===1,'Notes save version one');
    ac(vn_save($s['id'],$a['id'],$notes,0)['version']===1,'Identical interrupted-save retry is idempotent');
    ad(fn()=>vn_save($s['id'],$a['id'],['next_step'=>'Stale replacement'],0),409,'Stale notes cannot overwrite concurrent work');
    ac($beforeLease===wr_live($m['id'],$a['task_id'])['expires_at']&&$beforeResults===one('SELECT COUNT(*) n FROM workflow_submissions WHERE research_id=?',[$m['id']])['n'],'Saving notes neither renews nor publishes');
    ad(fn()=>vn_save($s['id'],$a['id'],['next_step'=>$token],1),422,'Private token cannot enter saved notes');
    ad(fn()=>vn_save($s['id'],$a['id'],['next_step'=>str_repeat('x',4001)],1),422,'Note budgets reject oversized work without truncation');
    $originalSession=$_SESSION;unset($GLOBALS['volunteer_api_auth']);$_SESSION=[];
    ad(fn()=>vn_read(vs_read($s['id'],false),$a),403,'Another browser cannot read private notes');$_SESSION=$originalSession;
    $replacement=va_enable($s['id']);ad(fn()=>va_authenticate($s['id'],'Bearer '.$token),401,'Replacing API token invalidates old access');unset($GLOBALS['volunteer_api_auth']);
    va_authenticate($s['id'],'Bearer '.$replacement);
    ac(vn_read(vs_read($s['id']),$a)['notes']['hypothesis']==='Synthetic hypothesis','New API connection resumes same authorized notes');
    $g=vs_assignment_grant($s,$a);$task=wf_task($m['id'],$a['task_id']);$packet=wf_test_payload($m['id'],$task);
    foreach(['model','reasoning','tool','workflow_mode'] as $field)$packet['metadata'][$field]=$s['capabilities'][$field];
    foreach(['task_revision'=>'revision','context_id'=>'context_id','submission_key'=>'submission_key'] as $field=>$bound)$packet[$field]=$g['scope'][$bound];
    $packet['handoff_code']=wf_handoff_code($task,wf_read_snapshot($m['id'],$packet['context_id']),$packet['submission_key']);
    vs_record($s['id'],$a['id'],$packet,true,'ai_api');ac(!vs_assignment_grant($s,$a)['result_id'],'API Check leaves grant unconsumed');
    $result=vs_record($s['id'],$a['id'],$packet,false,'ai_api');
    ac(one('SELECT submission_method FROM workflow_submissions WHERE id=?',[$result])['submission_method']==='ai_api','Shared recorder retains truthful API method');
    ac(vs_record($s['id'],$a['id'],$packet,false,'ai_api')===$result,'Identical Submit retry recovers exact receipt');
    $changed=$packet;$changed['reason']='Changed duplicate should never record.';
    ad(fn()=>vs_record($s['id'],$a['id'],$changed,false,'ai_api'),409,'Changed duplicate rejects');
    $next=vs_claim_next($s['id'],uid());vn_save($s['id'],$next['id'],['next_step'=>'Preserve unfinished work'],0);
    vs_stop($s['id']);
    ac(vn_read(vs_read($s['id']),$next)['notes']['next_step']==='Preserve unfinished work','Stop preserves bounded private recovery');
    ad(fn()=>vn_save($s['id'],$next['id'],['next_step'=>'Unauthorized work'],1),409,'Stop prevents new saved-work writes');
    ad(fn()=>vs_claim_next($s['id'],uid()),409,'Stop prevents API claims');
    ac(vs_record($s['id'],$a['id'],$packet,false,'ai_api')===$result,'Recorded receipt remains recoverable after Stop');
    va_revoke($s['id']);ad(fn()=>va_home(vs_read($s['id'])),401,'Revocation rejects existing request proof');
    unset($GLOBALS['volunteer_api_auth']);
    [$m2,$s2]=ap();$a2=vs_claim_next($s2['id'],uid());vn_save($s2['id'],$a2['id'],['next_step'=>'Private retained work'],0);
    query("UPDATE research SET status='Withdrawn' WHERE id=?",[$m2['id']]);
    ad(fn()=>vn_read($s2,$a2),404,'Withdrawn parent hides saved work');
    // Real candidate ranking, bounded bonuses and independent review eligibility.
    [$m3,$s3]=ap();$old=wf_tasks($m3['id'])[0];
    $young=wf_new_task($m3['id'],1,'young-review','review','Independent fixture review','Inspect synthetic results.','Provide exact checks.','Must be independent.');
    query('UPDATE workflow_tasks SET created_at=? WHERE id=?',[gmdate('Y-m-d H:i:s',time()-20*86400),$old['id']]);
    $rank=vq_candidates($s3);ac($rank[0]['id']===$old['id'],'Old ready work outranks maximum bounded new-work bonuses');
    query('UPDATE workflow_tasks SET created_at=? WHERE id=?',[now(),$old['id']]);$rank=vq_candidates($s3);
    ac($rank[0]['id']===$young,'Ready independent reviews receive priority');
    $picked=vs_claim_next($s3['id'],uid());ac($picked['task_id']===$old['id'],'Ranking cannot bypass reviewer independence or missing targets');
    ac(str_contains(strtolower($picked['queue_reason']),'waiting time'),'Recorded assignment explains its queue priority');
    query('UPDATE volunteer_research_notes SET expires_at=? WHERE assignment_id=?',[gmdate('Y-m-d H:i:s',time()-1),$a2['id']]);
    query("UPDATE research SET status='Open for Research' WHERE id=?",[$m2['id']]);$_SESSION['volunteer_sessions'][$s2['id']]=$s2['secret_hash'];
    ac(vn_read($s2,$a2)['notes']['next_step']==='','Expired private notes are not returned');
    [$m4,$s4]=ap();$token4=va_enable($s4['id']);va_authenticate($s4['id'],'Bearer '.$token4);
    $other=new PDO('mysql:host='.env('DB_HOST').';port='.env('DB_PORT').';dbname='.env('DB_NAME'),env('DB_USER'),env('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $other->beginTransaction();$q=$other->prepare('SELECT id FROM research WHERE id=? FOR UPDATE');$q->execute([$m4['id']]);
    try{vs_claim_next($s4['id'],uid());throw new RuntimeException('Busy parent should be skipped');}catch(VolunteerNoWork){ac((int)vs_read($s4['id'])['assignments_used']===0,'Busy ranked parent skips without waiting or consuming capacity');}finally{$other->rollBack();}
    $a4=vs_claim_next($s4['id'],uid());vn_save($s4['id'],$a4['id'],['next_step'=>'Recovery only'],0);
    query('UPDATE browser_returns SET revoked_at=? WHERE id=?',[now(),$a4['return_id']]);
    ad(fn()=>vn_read(vs_read($s4['id']),$a4),403,'Revoked assignment hides notes from authenticated API');
    ad(fn()=>va_dispatch($s4['id'],'/assignments/'.$a4['id'].'/handoff','GET',[]),403,'Revoked assignment denies API handoff');
    query('UPDATE browser_returns SET revoked_at=NULL WHERE id=?',[$a4['return_id']]);
    query('UPDATE volunteer_sessions SET expires_at=? WHERE id=?',[wr_stamp(wr_time()-1),$s4['id']]);
    ac(vn_read(vs_read($s4['id']),$a4)['notes']['next_step']==='Recovery only','Expired session retains private recovery reads');
    ad(fn()=>vn_save($s4['id'],$a4['id'],['next_step'=>'No further work'],1),409,'Expired session refuses saved-work mutations');
    unset($GLOBALS['volunteer_api_auth']);$_SESSION['admin']=1;
    purge_request($m4['id'],$m4['id']);ac(!one('SELECT assignment_id FROM volunteer_research_notes WHERE assignment_id=?',[$a4['id']]),'Research purge deletes private note bytes');
    query('UPDATE volunteer_assignments SET created_at=? WHERE id=?',[gmdate('Y-m-d H:i:s',time()-8*86400),$a2['id']]);
    query('DELETE FROM volunteer_research_notes WHERE assignment_id=?',[$a2['id']]);
    $_SESSION['volunteer_sessions'][$s2['id']]=$s2['secret_hash'];
    ad(fn()=>vn_save($s2['id'],$a2['id'],['next_step'=>'Cannot extend retention'],0),410,'Pruning cannot restart an old assignment saved-work window');
    echo json_encode(['status'=>'PASS','checks'=>$checks],JSON_PRETTY_PRINT);
}finally{
    unset($GLOBALS['volunteer_api_auth']);$_SESSION['admin']=1;
    foreach($projects as $rid)if(one('SELECT id FROM research WHERE id=?',[$rid]))purge_request($rid,$rid);
}
