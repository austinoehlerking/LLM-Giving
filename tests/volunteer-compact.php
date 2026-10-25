<?php
/** Compact sessions omit an upfront declaration without inventing one or weakening real holds. */
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';require_once ROOT.'/src/view.php';
session_start_safe();$_SESSION['admin']=1;$checks=[];$projects=[];$sessions=[];
function vc_check(bool $pass,string $label):void{global $checks;if(!$pass)throw new RuntimeException($label);$checks[]=$label;}
function vc_deny(callable $action,int $status,string $label):void{try{$action();}catch(HttpError $error){vc_check($error->status===$status,$label);return;}throw new RuntimeException('Expected rejection: '.$label);}
function vc_project():array{global $projects;$c=create_research(['title'=>'[QA] Compact volunteer '.uid(),'question'=>'Synthetic permission and disclosure regression only, without real research.','category_id'=>1,'terms'=>1,'calculation_help'=>'maybe']);$projects[]=$c['id'];moderate('request',$c['id'],'Approved','Synthetic compact-session fixture.');$t=wf_tasks($c['id'])[0];wf_test_submit($c['id'],$t['id'],wf_test_payload($c['id'],$t),hash('sha256',uid()));return research($c['id']);}
function vc_post(array $r,string $profile='general'):array{return ['session_profile'=>$profile,'session_preset'=>'standard','scope_kind'=>'research','research_id'=>$r['id'],'model'=>'gpt-6-astra','reasoning'=>'max','tool'=>'Codex','authorize'=>1,'terms'=>1,'authorized_resources'=>1,'policy_version'=>terms_version()];}
function vc_create(array $post):array{global $sessions;$_SESSION['workflow_actor']=bin2hex(random_bytes(32));putenv('RATE_LIMIT_NAMESPACE=compact-'.uid());$c=vs_create($post);$sessions[]=$c['id'];return $c;}
function vc_packet(array $s,array $a):array{$g=vs_assignment_grant($s,$a);$t=wf_task($a['research_id'],$a['task_id']);$d=wf_test_payload($a['research_id'],$t);$d['submission_key']=$g['scope']['submission_key'];$d['context_id']=$g['scope']['context_id'];$d['task_revision']=$g['scope']['revision'];$d['handoff_code']=wf_handoff_code($t,wf_read_snapshot($a['research_id'],$d['context_id']),$d['submission_key']);return $d;}
try{
 foreach(['general','restricted_web_v1']as$profile)foreach(['none','repair_only','influenced','unsure']as$level){
  $r=vc_project();$post=vc_post($r,$profile);$post['human_disclosure_collection']='explicit_v1';$post['human_disclosure']=['level'=>'none','details'=>'Forged packet claim'];$created=vc_create($post);$s=vs_read($created['id']);
  vc_check($s['consent']['human_disclosure_collection']==='session_not_collected_v1'&&$s['consent']['human_disclosure']['level']==='not_collected','New '.$profile.' session stores honest server-owned not-collected status: '.$level);
  vc_check(str_contains($s['consent']['permission'],'No purchases, installation, unrelated work'),'Removing footer does not broaden permission: '.$profile.' '.$level);
  $a=vs_claim_next($s['id']);$g=vs_assignment_grant($s,$a);vc_check($g['consent']===$s['consent'],'Assignment copies exact stored consent provenance');$d=vc_packet($s,$a);
  $d['human_interaction']=['level'=>$level,'details'=>in_array($level,['influenced','unsure'],true)?'Synthetic initial and later scientific steering or uncertainty.':''];$d['human_disclosure']=['level'=>'none','details'=>'Forged independent declaration'];$d['consent']=['human_disclosure_collection'=>'explicit_v1'];
  vc_check(vs_record($s['id'],$a['id'],$d,true)==='','Check accepts structurally valid '.$level.' work without writing');
  vc_check(!one('SELECT id FROM workflow_submissions WHERE research_id=? AND nonce=?',[$r['id'],$g['scope']['submission_key']]),'Check records no result');
  if($profile==='general'&&$level==='none'){
   $forged=$d;$forged['human_disclosure']=['level'=>'not_collected','details'=>'Claiming session provenance from a public packet.'];$forged['human_disclosure_collection']='session_not_collected_v1';
   vc_deny(fn()=>wf_submit($r['id'],$a['task_id'],$forged,$s['actor_hash'],true,'manual'),422,'Ordinary packet cannot forge not-collected provenance or consent');
  }
  // Exercise the worker return path as well as the controller path.
  $_SESSION['browser_returns'][$g['id']]=$g['secret_hash'];$id=$level==='repair_only'?ar_record($r,$g,$d,false):vs_record($s['id'],$a['id'],$d,false);$row=one('SELECT * FROM workflow_submissions WHERE id=?',[$id]);$meta=json_decode($row['metadata'],true);$influence=$meta['human_influence'];$held=in_array($level,['influenced','unsure'],true);
  vc_check($row['admission']===($held?'quarantined':'accepted'),'Actual '.$profile.' '.$level.' admission is correct');
  vc_check($influence['human_disclosure']['level']==='not_collected'&&$influence['human_interaction']['level']===$level,'Packet cannot invent an independent declaration or erase AI disclosure');
  vc_check(($influence['review_status']==='needed')===$held,'Only actual influenced/unsure self-report requests review');
  vc_check($meta['authorization']['human_disclosure_collection']==='session_not_collected_v1'&&str_contains($influence['basis'],'not collected'),'Result retains honest authorization and assessment basis');
  ob_start();include ROOT.'/views/workflow-influence.php';$html=ob_get_clean();vc_check(str_contains($html,'independent contributor declaration was not collected')&&!str_contains($html,'Self-reported by the AI and contributor.'),'Display does not invent an independent self-attestation');
  if($held)vc_check(wf_task($r['id'],$a['task_id'])['state']==='Awaiting Admin'&&!wf_task($r['id'],$a['task_id'])['accepted_id'],'Influenced/uncertain work cannot advance before admin review');
  else vc_check(wf_task($r['id'],$a['task_id'])['accepted_id']===$id,'Clean/repair-only work still advances normally');
  vs_stop($s['id']);vc_deny(fn()=>vs_claim_next($s['id']),409,'Stop revokes future pickup');
 }
 $r=vc_project();$post=vc_post($r);$created=vc_create($post+['human_level'=>'influenced','human_details'=>'A real explicit legacy declaration of initial source guidance.']);$s=vs_read($created['id']);vc_check($s['consent']['human_disclosure_collection']==='explicit_v1','Explicit older client declaration has explicit version');
 // Historical rows have no collection marker. Preserve their original semantics and text.
 $legacy=$s['consent'];unset($legacy['human_disclosure_collection']);query('UPDATE volunteer_sessions SET consent=? WHERE id=?',[json_encode($legacy),$s['id']]);$s=vs_read($s['id']);$a=vs_claim_next($s['id']);$d=vc_packet($s,$a);$id=vs_record($s['id'],$a['id'],$d,false);$row=one('SELECT admission,metadata FROM workflow_submissions WHERE id=?',[$id]);vc_check($row['admission']==='quarantined'&&json_decode($row['metadata'],true)['human_influence']['human_disclosure']===$legacy['human_disclosure'],'Historical explicit influence remains intact and held with clean AI report');
 foreach(['authorize','terms','authorized_resources']as$key){$bad=$post;unset($bad[$key]);putenv('RATE_LIMIT_NAMESPACE=compact-deny-'.uid());vc_deny(fn()=>vs_create($bad),422,'Missing '.$key.' still denies simplified authorization');}
 $bad=$post;$bad['human_level']='not_collected';putenv('RATE_LIMIT_NAMESPACE=compact-deny-'.uid());vc_deny(fn()=>vs_create($bad),422,'Client cannot declare not-collected as a human self-report');
 vc_deny(fn()=>ar_consent($r,$post),422,'Ordinary one-assignment consent still requires its separate human declaration');
 vc_deny(fn()=>wf_influence(['human_interaction'=>['level'=>'not_collected','details'=>''],'human_disclosure'=>['level'=>'none','details'=>'']]),422,'AI cannot report not-collected instead of its actual input');
 vc_deny(fn()=>wf_influence(['human_interaction'=>['level'=>'none','details'=>''],'human_disclosure'=>['level'=>'not_collected','details'=>''],'consent'=>['human_disclosure_collection'=>'session_not_collected_v1']]),422,'Public influence validator ignores packet-provided consent marker');
 vc_check(!str_contains(ar_instructions(true),'The captured volunteer declaration covers input through authorization')&&str_contains(ar_instructions(true),'ALL initial and later scientific steering'),'Session instructions reconcile initial/later AI reporting without false captured-declaration claim');
 vc_check(str_contains(ar_instructions(),'The captured volunteer declaration covers input through authorization'),'Single-assignment instructions preserve collected-declaration semantics');
 echo json_encode(['pass'=>true,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}finally{foreach($sessions as$id)query('DELETE FROM volunteer_sessions WHERE id=?',[$id]);foreach($projects as$id)purge_request($id,$id);}
