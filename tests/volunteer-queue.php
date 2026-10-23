<?php
require __DIR__.'/bootstrap.php';
require __DIR__.'/workflow-fixture.php';
require __DIR__.'/tool-fixture.php';
session_start_safe(); $_SESSION['admin']=1;
$checks=[]; $projects=[]; $sessions=[];
$GLOBALS['vqq_now']=time(); $GLOBALS['wr_test_clock']=fn()=>$GLOBALS['vqq_now'];
function vqc(bool $ok,string $label): void {global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
function vqd(callable $call,int $status,string $label): void {try{$call();}catch(HttpError $e){vqc($e->status===$status,$label);return;}throw new RuntimeException('Expected rejection: '.$label);}
function vq_project(bool $plan=true,bool $tool=false): array {
    global $projects;
    $post=$tool?rt_test_request():['title'=>'[QA] Bounded volunteer queue '.uid(),'question'=>'Synthetic bounded volunteer workflow checks; no scientific verification is claimed.','category_id'=>1,'terms'=>1];
    $created=create_research($post); $projects[]=$created['id'];
    moderate('request',$created['id'],'Approved','Disposable volunteer-session fixture.');
    if ($plan) { $task=wf_tasks($created['id'])[0]; wf_test_submit($created['id'],$task['id'],wf_test_payload($created['id'],$task),hash('sha256',uid())); }
    return research($created['id']);
}
function vq_session(array $r,int $limit=3,int $minutes=60,bool $software=false,?string $actor=null): array {
    global $sessions;
    $_SESSION['workflow_actor']=$actor??bin2hex(random_bytes(32));
    putenv('RATE_LIMIT_NAMESPACE=volunteer-queue-'.uid());
    $created=vs_create(['scope_kind'=>'research','research_id'=>$r['id'],'assignment_limit'=>$limit,'minutes'=>$minutes,'model'=>'gpt-6-astra','reasoning'=>'max','tool'=>'Codex',
        'authorize'=>1,'terms'=>1,'authorized_resources'=>1,'tool_terms'=>$software?1:0,'policy_version'=>terms_version(),'human_level'=>'none']);
    $sessions[]=$created['id'];return $created;
}
function vq_packet(array $session,array $assignment,string $outcome='completed'): array {
    $g=vs_assignment_grant($session,$assignment);$t=wf_task($assignment['research_id'],$assignment['task_id']);
    $d=wf_test_payload($assignment['research_id'],$t,$outcome);
    $d['submission_key']=$g['scope']['submission_key'];$d['context_id']=$g['scope']['context_id'];$d['task_revision']=$g['scope']['revision'];
    $d['handoff_code']=wf_handoff_code($t,wf_read_snapshot($assignment['research_id'],$d['context_id']),$d['submission_key']);return $d;
}
try {
    $r=vq_project();$created=vq_session($r,2);$id=$created['id'];$session=vs_read($id);
    $a=vs_claim_next($id);$session=vs_read($id);$lease=wr_live($r['id'],$a['task_id']);$expiry=$lease['expires_at'];
    vqc((int)$session['assignments_used']===1 && $lease['actor_hash']===$session['actor_hash'],'Pickup atomically counts one assignment and reserves for the original volunteer');
    $text=vs_handoff($id,$a['id']);$again=vs_claim_next($id);
    vqc($again['id']===$a['id'] && (int)vs_read($id)['assignments_used']===1,'Concurrent-style repeated pickup reuses the current assignment');
    $GLOBALS['vqq_now']+=5;vqc(vs_handoff($id,$a['id'])===$text && wr_live($r['id'],$a['task_id'])['expires_at']===$expiry,'Recopy retains complete handoff, return credential and original expiry');
    $g=vs_assignment_grant($session,$a);$archive=wf_read_handoff($r['id'],$g['scope']['archive_id']);
    vqc(!str_contains($archive['body'],$id) && !str_contains($archive['body'],$g['id']) && !str_contains($archive['body'],vs_return_secret($g['id'])),'Public archive excludes session binding and private return capability');
    $packet=vq_packet($session,$a);$bad=$packet;$bad['body']='tiny';
    vqd(fn()=>vs_record($id,$a['id'],$bad,true),422,'Recoverable validation failure does not record work');
    vqc((int)vs_read($id)['assignments_used']===1 && !vs_assignment(vs_read($id),$a['id'])['ended_at'],'Validation and repair retain the assigned slot');
    vqc(vs_record($id,$a['id'],$packet,true)==='' && wr_live($r['id'],$a['task_id'])['expires_at']===$expiry,'Check packet is read-only and does not renew');
    $bad=$packet;$bad['body'].=' '.$created['link'];
    vqd(fn()=>vs_record($id,$a['id'],$bad,true),422,'Private session link is rejected before research recording');
    $bad=$packet;$bad['body'].=' '.parse_url($created['link'],PHP_URL_FRAGMENT);
    vqd(fn()=>vs_record($id,$a['id'],$bad,true),422,'Bare session secret is rejected before research recording');
    $packet['human_disclosure']=['level'=>'influenced','details'=>'Packet cannot replace the actual volunteer declaration.'];
    $first=vs_record($id,$a['id'],$packet,false);$result=one('SELECT * FROM workflow_submissions WHERE id=?',[$first]);
    vqc($result['admission']==='accepted' && $result['submission_method']==='ai_browser','Session return uses ordinary accepted AI browser delivery');
    vqc(json_decode($result['metadata'],true)['human_influence']['human_disclosure']['level']==='none','AI packet cannot replace independently captured session declaration');
    vqc(!wr_live($r['id'],$a['task_id']) && vs_assignment(vs_read($id),$a['id'])['ended_at'],'Recorded result ends reservation and current assignment');
    $b=vs_claim_next($id);vqc($b['task_id']!==$a['task_id'] && (int)vs_read($id)['assignments_used']===2,'A dependent second task is picked up without repeating consent');
    vqc(vs_state(vs_read($id))==='limit_reached','The last assigned task retains permission after allowance exhaustion');
    $secondPacket=vq_packet(vs_read($id),$b);$second=vs_record($id,$b['id'],$secondPacket,false);
    vqc((bool)$second,'The last assignment can finish before the deadline');
    vqd(fn()=>vs_claim_next($id),409,'No additional pickup is allowed after the assignment limit');
    vs_stop($id);
    vqc(vs_record($id,$b['id'],$secondPacket,false)===$second,'An identical recorded retry still recovers its receipt after Stop');
    $public=json_encode(context_data($r['id']));
    vqc(!str_contains($public,$id) && !str_contains($public,$session['secret_hash']) && !str_contains($public,$session['actor_hash']),'Public context excludes private session lineage and ownership material');
    $r2=vq_project();$c2=vq_session($r2,2);$id2=$c2['id'];$a2=vs_claim_next($id2);$s2=vs_read($id2);$originalArchive=vs_assignment_grant($s2,$a2)['scope'];
    $GLOBALS['vqq_now']+=3600;
    vqc(!wr_live($r2['id'],$a2['task_id']),'The physical sixty-minute lease expires without cleanup');
    // This first session ended at one hour, so use a longer session to exercise late task reacquisition.
    vqd(fn()=>vs_assignment_action($id2,$a2['id'],'reacquire'),409,'An expired session cannot reacquire even its own task');
    $r3=vq_project();$c3=vq_session($r3,2,240);$id3=$c3['id'];$a3=vs_claim_next($id3);$s3=vs_read($id3);$scope=vs_assignment_grant($s3,$a3)['scope'];
    $GLOBALS['vqq_now']+=3600;vs_assignment_action($id3,$a3['id'],'reacquire');
    vqc(vs_assignment_grant(vs_read($id3),vs_assignment(vs_read($id3),$a3['id']))['scope']===$scope && (int)vs_read($id3)['assignments_used']===1,'Explicit late reacquisition preserves signed identifiers and the original allowance');
    vs_assignment_action($id3,$a3['id'],'release');
    vqc((int)vs_read($id3)['assignments_used']===1 && !wr_live($r3['id'],$a3['task_id']),'Release frees the task without refunding an assignment');
    $new=vs_claim_next($id3);
    vqc($new['id']!==$a3['id'] && (int)vs_read($id3)['assignments_used']===2,'A later pickup after release consumes a new assignment');
    vqd(fn()=>vs_record($id3,$a3['id'],vq_packet(vs_read($id3),$a3,'progress'),true),409,'A released permission cannot submit under a replacement lease');
    vs_stop($id3);vqc(!wr_live($r3['id'],$new['task_id']),'Stop explicitly releases the held task');
    $r4=vq_project();$c4=vq_session($r4,3,5);$id4=$c4['id'];$start=$GLOBALS['vqq_now'];$a4=vs_claim_next($id4);$deadline=vs_read($id4)['expires_at'];
    vqc(wr_live($r4['id'],$a4['task_id'])['expires_at']===$deadline,'Effective task availability is bounded by the shorter session deadline');
    $GLOBALS['vqq_now']+=30;vs_assignment_action($id4,$a4['id'],'renew');
    vqc(vs_read($id4)['expires_at']===$deadline && wr_live($r4['id'],$a4['task_id'])['expires_at']===$deadline,'Explicit renewal never extends session authorization');
    $GLOBALS['vqq_now']=$start+300;
    vqc(vs_state(vs_read($id4))==='expired' && !wr_live($r4['id'],$a4['task_id']),'At exact session expiry its reservation becomes logically available');
    vqd(fn()=>vs_record($id4,$a4['id'],vq_packet(vs_read($id4),$a4,'progress'),false),409,'An expired session cannot submit an unrecorded packet');
    vqd(fn()=>vs_assignment_action($id4,$a4['id'],'renew'),409,'An expired session cannot renew');
    $other=hash('sha256',uid());$contender=wr_claim($r4['id'],$a4['task_id'],(int)$a4['task_revision'],$other);
    vqc($contender['actor_hash']===$other,'Another volunteer can claim immediately at the session deadline');
    vs_stop($id4);vqc(wr_live($r4['id'],$a4['task_id'])['actor_hash']===$other,'Stopping the expired session cannot release the new holder');
    wr_release($r4['id'],$a4['task_id'],$other);
    $r5=vq_project(false);$c5=vq_session($r5);$id5=$c5['id'];$budget=hash('sha256',env('RATE_LIMIT_NAMESPACE').'|handoff-bytes|'.gmdate('Y-m-d'));
    query('INSERT INTO rate_limits(bucket,hits,expires) VALUES (?,?,?) ON DUPLICATE KEY UPDATE hits=VALUES(hits)',[$budget,104857601,time()+3600]);
    vqd(fn()=>vs_claim_next($id5),503,'Failed complete handoff preparation rejects the pickup');
    vqc((int)vs_read($id5)['assignments_used']===0 && !vs_current(vs_read($id5)) && !wr_live($r5['id'],wf_tasks($r5['id'])[0]['id']),'Failed pickup leaves no reservation, assignment or consumed allowance');
    query('DELETE FROM rate_limits WHERE bucket=?',[$budget]);
    $tool=vq_project(false,true);$noSoftware=vq_session($tool);vqd(fn()=>vs_claim_next($noSoftware['id']),409,'Queue excludes actual tool projects without separate software consent');
    vqc((int)vs_read($noSoftware['id'])['assignments_used']===0,'An empty eligible queue consumes nothing');
    $yesSoftware=vq_session($tool,3,60,true);$toolAssignment=vs_claim_next($yesSoftware['id']);
    vqc($toolAssignment['research_id']===$tool['id'],'Current separate software consent permits an otherwise eligible tool task');vs_stop($yesSoftware['id']);
    $reviewProject=vq_project();$reviewSession=vq_session($reviewProject,8);$reviewId=$reviewSession['id'];
    for($i=0;$i<2;$i++){$assigned=vs_claim_next($reviewId);vs_record($reviewId,$assigned['id'],vq_packet(vs_read($reviewId),$assigned),false);}
    $synthesis=array_values(array_filter(wf_tasks($reviewProject['id']),fn($t)=>$t['kind']==='synthesis'))[0];
    wf_test_submit($reviewProject['id'],$synthesis['id'],wf_test_payload($reviewProject['id'],$synthesis),hash('sha256',uid()));
    $review=array_values(array_filter(wf_tasks($reviewProject['id']),fn($t)=>$t['kind']==='review'))[0];
    vqd(fn()=>vs_task_eligible(vs_read($reviewId),research($reviewProject['id']),$review),422,'Session workers cannot review their own research even when someone else synthesized it');
    vqd(fn()=>vs_claim_next($reviewId),409,'Queue does not turn the session into an independent reviewer');
    $checksFile=storage('qa/volunteer-draft/queue-tests.json');file_put_contents($checksFile,json_encode(['pass'=>true,'count'=>count($checks),'checks'=>$checks],JSON_PRETTY_PRINT));
    echo count($checks)." volunteer queue and lifecycle checks passed.\n";
} finally {
    unset($GLOBALS['wr_test_clock'],$GLOBALS['vqq_now']);
    foreach($sessions as $id)query('DELETE FROM volunteer_sessions WHERE id=?',[$id]);
    foreach($projects as $rid)if(one('SELECT id FROM research WHERE id=?',[$rid]))purge_request($rid,$rid);
}
