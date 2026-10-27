<?php
/** Synthetic local authorization/eligibility checks, not a Cloud capability or scientific test. */
require __DIR__.'/bootstrap.php';
require __DIR__.'/workflow-fixture.php';
session_start_safe(); $_SESSION['admin']=1;
$checks=[];$projects=[];$sessions=[];
$GLOBALS['vp_now']=time();$GLOBALS['wr_test_clock']=fn()=>$GLOBALS['vp_now'];
function vp_check(bool $value,string $label): void {global $checks;if(!$value)throw new RuntimeException($label);$checks[]=$label;}
function vp_denied(callable $action,int $status,string $label): void {try{$action();}catch(HttpError $e){vp_check($e->status===$status,$label.' ('.$e->getMessage().')');return;}throw new RuntimeException('Expected denial: '.$label);}
function vp_project(array $extra=[],bool $plan=true): array {
    global $projects;
    $created=create_research($extra+['title'=>'[QA] Restricted profile '.uid(),'question'=>'Synthetic profile fixture; no real research, execution or provider capability is claimed.','category_id'=>1,'terms'=>1,'calculation_help'=>'maybe','external_tools'=>'none']);$projects[]=$created['id'];
    moderate('request',$created['id'],'Approved','Synthetic local profile fixture.');
    if($plan){$t=wf_tasks($created['id'])[0];wf_test_submit($created['id'],$t['id'],wf_test_payload($created['id'],$t),hash('sha256',uid()));}
    return research($created['id']);
}
function vp_post(array $r,string $profile='restricted_web_v1'): array {
    return ['session_profile'=>$profile,'session_preset'=>'long','scope_kind'=>'research','research_id'=>$r['id'],'model'=>'gpt-6-astra','reasoning'=>'max','tool'=>'Codex','authorize'=>1,'terms'=>1,'authorized_resources'=>1,'policy_version'=>terms_version(),'human_level'=>'none'];
}
function vp_session(array $r,string $profile='restricted_web_v1',?array $reviewTask=null): array {
    global $sessions;
    $_SESSION['workflow_actor']=bin2hex(random_bytes(32));putenv('RATE_LIMIT_NAMESPACE=profile-'.uid());
    if($reviewTask){
        $actor=wf_actor();$identity=ri_capture(['account_provider'=>'synthetic-qa','account_reference'=>'Synthetic profile reviewer '.uid(),'account_attestation'=>'1'],$actor);
        ri_capture_review($r,$reviewTask,$actor,$identity,['review_account_separation'=>'knowingly_different','review_target_digest'=>ri_review_target_digest($r,$reviewTask,$actor,$identity)]);
    }
    $created=vs_create(vp_post($r,$profile));$sessions[]=$created['id'];return $created;
}
function vp_packet(array $session,array $a): array {
    $g=vs_assignment_grant($session,$a);$t=wf_task($a['research_id'],$a['task_id']);$d=wf_test_payload($a['research_id'],$t);
    $d['submission_key']=$g['scope']['submission_key'];$d['context_id']=$g['scope']['context_id'];$d['task_revision']=$g['scope']['revision'];$d['handoff_code']=wf_handoff_code($t,wf_read_snapshot($a['research_id'],$d['context_id']),$d['submission_key']);return $d;
}
try {
    $r=vp_project();$t=wf_tasks($r['id'])[1];$post=vp_post($r);$configuration=vs_configuration($post);
    vp_check($configuration['session_profile']==='restricted_web_v1','Configuration validates explicit restricted profile');
    $legacy=$post;unset($legacy['session_profile']);vp_check(vs_configuration($legacy)['session_profile']==='general','Omitted profile preserves existing general sessions');
    foreach(['unrestricted','restricted_web_v2'] as $unknown){$bad=$post;$bad['session_profile']=$unknown;vp_denied(fn()=>vs_configuration($bad),422,'Unknown profile cannot authorize new capabilities');}
    $bad=$post;$bad['session_profile']=[];vp_denied(fn()=>vs_configuration($bad),422,'Non-string profile rejected');
    $consent=$post;unset($consent['authorized_resources']);vp_denied(fn()=>vs_create($consent),422,'Profile does not replace resource confirmation');
    $stale=$post;$stale['policy_version']='old';vp_denied(fn()=>vs_create($stale),409,'Profile does not replace current legal consent');
    $created=vp_session($r);$id=$created['id'];$session=vs_read($id);
    vp_check($session['session_profile']==='restricted_web_v1'&&$session['consent']['session_profile']==='restricted_web_v1','Stored session and captured consent bind validated profile');
    vp_check($session['consent']['human_disclosure']['level']==='none','Separate human declaration retained');
    vp_check(vs_profile_compatible($session,$r,$t),'Optional Maybe does not exclude narrative work');
    vp_check(vs_configuration_matches($session,$r,$t),'Restricted compatible work retains model/configuration matching');
    $changed=$r;$changed['external_requirements']=json_encode(['calculation_help'=>'run','external_tools'=>'none']);vp_check(vs_profile_compatible($session,$changed,$t),'Future project execution does not exclude a narrative task');
    $changed=$r;$changed['external_requirements']=json_encode(['calculation_help'=>'maybe','external_tools'=>'none','required_software'=>'Stale spreadsheet']);vp_check(vs_profile_compatible($session,$changed,$t),'Hidden stale software under no mandatory tools does not restrict work');
    $web=$r;$web['external_requirements']=json_encode(['calculation_help'=>'maybe','external_tools'=>'free','required_software'=>'Web browser']);vp_check(vs_profile_compatible($session,$web,$t),'Ordinary web browser dependency is within the allowed profile');
    $web['external_requirements']=json_encode(['calculation_help'=>'maybe','external_tools'=>'free','required_software'=>'Web browser with required scientific plugin']);vp_check(vs_profile_compatible($session,$web,$t),'Project-wide future software does not rewrite a narrative task capability');
    foreach(['free','licensed','mixed'] as $access){$changed=$r;$changed['external_requirements']=json_encode(['external_tools'=>$access,'required_software'=>'Required scientific software']);vp_check(vs_profile_compatible($session,$changed,$t),'Project software scope is not imposed on this narrative task: '.$access);}
    $changed=$r;$changed['external_requirements']=json_encode(['external_tools'=>'unknown','required_software'=>'Required local simulator']);vp_check(vs_profile_compatible($session,$changed,$t),'Future simulator does not turn narrative reading into execution');
    $changed=$r;$changed['external_requirements']=json_encode(['calculation_help'=>'maybe','external_tools'=>'unknown','setup_needs'=>'None specified']);vp_check(vs_profile_compatible($session,$changed,$t),'Unknown tools without concrete requirement do not invent dependencies');
    foreach(['computational','builds_tool'] as $flag){$task=$t;$task[$flag]=1;vp_check(!vs_profile_compatible($session,$r,$task),'Known task capability excluded: '.$flag);}
    $tool=$r;$tool['project_type']='research_tool';vp_check(vs_profile_compatible($session,$tool,$t),'Narrative research in a tool project is eligible by its own requirements');
    $general=$session;$general['session_profile']='general';$general['consent']['session_profile']='general';vp_check(vs_profile_compatible($general,$tool,$t),'General profile adds no new capability restriction');
    $forgedSession=$session;$forgedSession['session_profile']='general';vp_denied(fn()=>vs_profile_compatible($forgedSession,$tool,$t),403,'A profile inconsistent with captured authorization cannot expand capability');
    $derivationFields=['evidence'=>['derivations'=>[['version'=>1,'type'=>'model_derived']],'derivation_reviews'=>[]]];
    if(function_exists('rd_validate')){vs_profile_assert_packet($session,$derivationFields);vp_check(true,'Restricted profile delegates separate derivation fields to the available canonical arithmetic validator');}
    else vp_denied(fn()=>vs_profile_assert_packet($session,$derivationFields),503,'Missing canonical derivation validator cannot silently discard arithmetic');
    $prepare=$r;$prepare['external_requirements']=json_encode(['calculation_help'=>'prepare_review','external_tools'=>'none']);vp_check(vs_profile_compatible($session,$prepare,$t),'Explicit preparation-only permits compatible non-executed narrative work');
    $legacySession=$general;unset($legacySession['session_profile']);vp_check(vs_profile_id($legacySession)==='general','Historical profile omission resolves to general');
    $oldRow=one('SELECT * FROM volunteer_sessions WHERE id=?',[$session['id']]);$oldRow['id']=uid();$oldRow['secret_hash']=hash('sha256',uid());unset($oldRow['session_profile']);$oldConsent=json_decode($oldRow['consent'],true);unset($oldConsent['session_profile'],$oldConsent['capability_profile']);$oldRow['consent']=json_encode($oldConsent);insert('volunteer_sessions',$oldRow);$sessions[]=$oldRow['id'];$_SESSION['volunteer_sessions'][$oldRow['id']]=$oldRow['secret_hash'];$oldSession=vs_read($oldRow['id']);vp_check(vs_profile_id($oldSession)==='general'&&one('SELECT consent FROM volunteer_sessions WHERE id=?',[$oldRow['id']])['consent']===$oldRow['consent'],'Historical/default profile retains exact old consent and general capability');
    $unknown=$session;$unknown['session_profile']='forged';vp_denied(fn()=>vs_profile_compatible($unknown,$r,$t),403,'Unknown stored profile fails closed');
    $model=$r;$model['ai_requirements']=json_encode(['model'=>'claude-opus-5-5','mode'=>'exact','effort'=>'max']);vp_check(!vs_configuration_matches($session,$model,$t),'Restricted profile never overrides exact requester model');
    $model=$r;$model['ai_requirements']=json_encode(['model'=>'gpt-6-astra','mode'=>'exact','effort'=>'ultra']);vp_check(!vs_configuration_matches($session,$model,$t),'Restricted profile never overrides required reasoning');
    $planning=wf_tasks($r['id'])[0];$unknownModel=$session;$unknownModel['capabilities']['model']='';vp_check(!vs_configuration_matches($unknownModel,$r,$planning),'Unknown actual planning model is not a match');
    $a=vs_claim_next($id);$session=vs_read($id);$lease=wr_live($r['id'],$a['task_id']);
    vp_check($a['research_id']===$r['id']&&(int)$session['assignments_used']===1,'Compatible Maybe task picked up under stored profile');
    vp_check(strtotime($lease['expires_at'].' UTC')-$GLOBALS['vp_now']===3600,'Restricted pickup retains exact sixty-minute reservation');
    $text=vs_handoff($id,$a['id']);$g=vs_assignment_grant($session,$a);$archive=wf_read_handoff($r['id'],$g['scope']['archive_id']);
    vp_check(str_contains($text,'AUTHORIZED CAPABILITY PROFILE: restricted_web_v1')&&str_contains($text,'not a technical sandbox'),'Private handoff carries honest actual profile restrictions');
    vp_check(str_contains($text,'explicitly release')&&str_contains($text,'scientific blocked/needs_clarification'),'Hidden incompatibility instructions avoid falsely blocking research');
    vp_check(!str_contains($archive['body'],$id)&&!str_contains($archive['body'],$g['id']),'Public archive excludes private session binding');
    $controller=vo_handoff($id,$created['link']);vp_check(str_contains($controller,'"session_profile": "restricted_web_v1"')&&str_contains($controller,'not a technical sandbox')&&str_contains($controller,'never provider-verified'),'Controller uses server profile without claiming sandboxing or provider verification');
    $draft=vs_form_draft(['session_profile'=>'restricted_web_v1','session_secret'=>'not retained']);vp_check($draft===['session_profile'=>'restricted_web_v1'],'Profile draft allowlist excludes private credentials');
    $packet=vp_packet($session,$a);$packet['session_profile']='general';$packet['capabilities']=['execute_code'=>true];
    foreach(['calculations','calculation_reviews','tool_builds','tool_reviews','calculation_checkpoint'] as $field){$forged=$packet;$forged['evidence'][$field]=[['status'=>'executed','verdict'=>'reproduced']];vp_denied(fn()=>vs_record($id,$a['id'],$forged,true),422,'Forged packet profile cannot expand '.$field);}
    $worker=$packet;$worker['evidence']['calculations']=[['status'=>'executed']];$_SESSION['browser_returns'][$g['id']]=$g['secret_hash'];vp_denied(fn()=>ar_record($r,$g,$worker,true),422,'Separate browser return also enforces stored profile');
    vp_check(!one('SELECT id FROM workflow_submissions WHERE research_id=? AND nonce=?',[$r['id'],$g['scope']['submission_key']]),'Rejected expansion records no result');
    vp_check(vs_read($id)['session_profile']==='restricted_web_v1'&&vs_read($id)['consent']===$session['consent'],'Packet fields do not mutate profile or consent');
    vp_check(vs_record($id,$a['id'],$packet,true)==='','Narrative packet with optional Maybe passes ordinary schema');
    vp_check(wr_live($r['id'],$a['task_id'])['expires_at']===$lease['expires_at'],'Checks/recopy do not renew reservation');
    $GLOBALS['vp_now']+=30;vs_assignment_action($id,$a['id'],'renew');vp_check(wr_live($r['id'],$a['task_id'])['expires_at']>$lease['expires_at'],'Explicit restricted renewal uses shared service');
    $result=vs_record($id,$a['id'],$packet,false);$row=one('SELECT * FROM workflow_submissions WHERE id=?',[$result]);vp_check($row['admission']==='accepted'&&$row['submission_method']==='ai_browser','Narrative accepted through normal browser result pipeline');
    vs_stop($id);vp_check(vs_record($id,$a['id'],$packet,false)===$result,'Identical receipt recovery survives Stop');vp_denied(fn()=>vs_claim_next($id),409,'Stop denies restricted continuation');
    $r2=vp_project(['calculation_help'=>'run'],false);$s2=vp_session($r2);$planningAssignment=vs_claim_next($s2['id']);vp_check(wf_task($r2['id'],$planningAssignment['task_id'])['kind']==='planning','Needs Research Plan task is selected despite future calculations');vp_check((int)vs_read($s2['id'])['assignments_used']===1,'Planning pickup consumes exactly one allowance');vs_stop($s2['id']);
    $r3=vp_project();$c3=vp_session($r3);$a3=vs_claim_next($c3['id']);query('UPDATE workflow_tasks SET computational=1 WHERE id=?',[$a3['task_id']]);vp_denied(fn()=>vs_assignment_action($c3['id'],$a3['id'],'renew'),409,'Changed known execution dependency rechecked at renewal');
    $p3=vp_packet(vs_read($c3['id']),$a3);vp_denied(fn()=>vs_record($c3['id'],$a3['id'],$p3,true),409,'Changed known execution dependency rechecked at record');
    vs_assignment_action($c3['id'],$a3['id'],'release');vp_check(!wr_live($r3['id'],$a3['task_id'])&&research($r3['id'])['status']==='Open for Research','Incompatible worker releases without changing scientific status');
    $r4=vp_project();$task4=wf_tasks($r4['id'])[1];$s4=vp_session($r4);query('UPDATE workflow_tasks SET builds_tool=1 WHERE id=?',[$task4['id']]);vp_denied(fn()=>vs_claim_next($s4['id']),409,'Pickup excludes known tool building task');
    query('UPDATE workflow_tasks SET builds_tool=0,computational=1 WHERE id=?',[$task4['id']]);vp_denied(fn()=>vs_claim_next($s4['id']),409,'Pickup excludes known computational task');
    $r5=vp_project(['external_tools'=>'licensed','required_software'=>'Synthetic required scientific package']);$s5=vp_session($r5);$narrative=vs_claim_next($s5['id']);vp_check(empty(wf_task($r5['id'],$narrative['task_id'])['computational']),'Narrative task remains eligible independently of future project software');vs_stop($s5['id']);
    $r6=vp_project();$s6=vp_session($r6);query("UPDATE research SET moderation='Pending' WHERE id=?",[$r6['id']]);vp_denied(fn()=>vs_claim_next($s6['id']),409,'Private parent cannot enter restricted queue');query("UPDATE research SET moderation='Approved' WHERE id=?",[$r6['id']]);
    $synthProject=vp_project();$rid=$synthProject['id'];for($i=0;$i<3;$i++){$ready=array_values(array_filter(wf_tasks($rid),fn($x)=>$x['state']==='Ready'));$next=$ready[0];wf_test_submit($rid,$next['id'],wf_test_payload($rid,$next),hash('sha256',uid()));}
    $review=array_values(array_filter(wf_tasks($rid),fn($x)=>$x['kind']==='review'))[0];$reviewSession=vp_session(research($rid),'restricted_web_v1',$review);$ss=vs_read($reviewSession['id']);vp_check(vs_profile_compatible($ss,research($rid),$review),'Narrative independent review remains compatible');
    $target=array_values(wf_evidence_results($review))[0]['result'];$metadata=json_decode($target['metadata'],true);$metadata['evidence']['calculations']=[['id'=>'synthetic_legacy_execution_marker','status'=>'executed']];query('UPDATE workflow_submissions SET metadata=? WHERE id=?',[json_encode($metadata),$target['id']]);
    vp_check(!vs_profile_compatible($ss,research($rid),$review),'Review requiring legacy computational reproduction excluded');vp_denied(fn()=>vs_claim_next($reviewSession['id']),409,'Reproduction review excluded at pickup');
    $public=json_encode(context_data($r['id']));vp_check(!str_contains($public,$id)&&!str_contains($public,$session['secret_hash'])&&!str_contains($public,$session['actor_hash']),'Public context omits profile session credentials and lineage');
    echo json_encode(['pass'=>true,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
} finally {
    foreach($sessions as $sid)query('DELETE FROM volunteer_sessions WHERE id=?',[$sid]);
    foreach($projects as $rid)purge_request($rid,$rid);
}
