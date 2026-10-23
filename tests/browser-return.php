<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';
session_start_safe();$_SESSION['admin']=1;putenv('RATE_LIMIT_NAMESPACE=browser-return-unit-'.uid());$checks=[];
function arc(bool $v,string $label):void{global $checks;if(!$v)throw new RuntimeException($label);$checks[]=$label;}
function ard(callable $f,int $code,string $label):void{try{$f();}catch(HttpError $e){arc($e->status===$code,$label.' ('.$e->getMessage().')');return;}throw new RuntimeException('Expected rejection: '.$label);}
function ar_test_grant(array $r,array $task,array $post):array{
 $issued=ar_issue($r,$task['id'],$post);preg_match('~/return/([a-f0-9]{32})#([a-f0-9]{64})~',$issued['handoff'],$m);
 $_SESSION['browser_returns'][$m[1]]=hash('sha256',$m[2]);return [ar_read($r,$m[1]),$issued,$m[2]];
}
function ar_test_packet(array $r,array $task,array $g):array{
 $d=wf_test_payload($r['id'],$task,'progress');$d['submission_key']=$g['scope']['submission_key'];$d['context_id']=$g['scope']['context_id'];$d['task_revision']=$g['scope']['revision'];$d['handoff_code']=wf_handoff_code($task,wf_read_snapshot($r['id'],$d['context_id']),$d['submission_key']);return $d;
}
db()->beginTransaction();
try{
 $created=create_research(['title'=>'[QA] Private AI browser return','question'=>'Synthetic consent, disclosure and return boundaries only.','category_id'=>1,'terms'=>1]);moderate('request',$created['id'],'Approved','Synthetic authorization test only.');$r=research($created['id']);$t=wf_tasks($r['id'])[0];
 $post=['authorize'=>1,'terms'=>1,'policy_version'=>terms_version(),'human_level'=>'none','human_details'=>''];
 foreach(['authorize','terms','human_level'] as $key){$bad=$post;unset($bad[$key]);ard(fn()=>ar_issue($r,$t['id'],$bad),422,'Missing '.$key.' cannot authorize');}
 $bad=$post;$bad['policy_version']='old';ard(fn()=>ar_issue($r,$t['id'],$bad),409,'Changed policy requires new consent');
 [$g,$issued,$secret]=ar_test_grant($r,$t,$post);$issuer=wf_actor();
 arc($g['actor_hash']===$issuer&&$g['consent']['terms_version']===terms_version(),'Grant binds issuer and exact accepted policy');
 arc($g['consent']['resource_confirmation']===CONTRIBUTOR_RESOURCE_CONFIRMATION && str_contains($issued['handoff'],CONTRIBUTOR_RESOURCE_CONFIRMATION),'Private consent and supplement retain the exact resource self-attestation');
 arc($g['consent']['disclosure_scope']==='Through authorization; the AI separately reports later input.','Volunteer declaration scope is explicit');
 arc(!str_contains(json_encode($g),$secret),'Database stores no raw return secret');
 $archive=wf_read_handoff($r['id'],$g['scope']['archive_id']);arc(!str_contains($archive['body'],$secret)&&!str_contains($archive['body'],$g['id']),'Public immutable archive contains no return credential');
 arc(str_contains($archive['body'],"Follow the provider's rules") && str_contains($archive['body'],'permission to publish publicly') && str_contains($archive['body'],'does not audit or police'),'Public handoff includes resource rules and does not claim permissions were audited');
 arc(str_contains($archive['body'],'AI BROWSER RETURN PROCEDURE')&&str_contains($archive['body'],'JSON is the payload format'),'Trusted prompt permits browser tools');
 $_SESSION['browser_returns']=[];ard(fn()=>ar_read($r,$g['id']),403,'Public authorization id alone is not permission');
 $_SESSION['workflow_actor']=bin2hex(random_bytes(32));$_SESSION['browser_returns'][$g['id']]=$g['secret_hash'];arc(wf_actor()!==$issuer,'Separate anonymous browser actor simulated');
 $d=ar_test_packet($r,$t,$g);$d['human_disclosure']=['level'=>'influenced','details'=>'AI cannot replace the independently sourced volunteer declaration.'];
 $before=[one('SELECT COUNT(*) n FROM workflow_submissions')['n'],one('SELECT COUNT(*) n FROM audit_events')['n'],one('SELECT COUNT(*) n FROM legal_versions')['n']];
 arc(ar_record($r,$g,$d,true)==='','Check validates without submission');
 arc($before===[one('SELECT COUNT(*) n FROM workflow_submissions')['n'],one('SELECT COUNT(*) n FROM audit_events')['n'],one('SELECT COUNT(*) n FROM legal_versions')['n']],'Check leaves results, audit and policy snapshots unchanged');
 arc(!ar_read($r,$g['id'])['result_id'],'Check does not consume authorization');
 $broken=$d;$broken['body']='tiny';ard(fn()=>ar_record($r,$g,$broken,true),422,'Recoverable packet error rejected');
 $broken=$d;$broken['submission_key']=uid();ard(fn()=>ar_record($r,$g,$broken,true),409,'Authorization binds submission key');
 $broken=$d;$broken['context_id']=uid();ard(fn()=>ar_record($r,$g,$broken,true),409,'Authorization binds context');
 $broken=$d;$broken['task_revision']++;ard(fn()=>ar_record($r,$g,$broken,true),409,'Authorization binds task revision');
 $id=ar_record($r,$g,$d,false);$row=one('SELECT * FROM workflow_submissions WHERE id=?',[$id]);$meta=json_decode($row['metadata'],true);
 arc($row['admission']==='accepted'&&$row['submission_method']==='ai_browser','Authorized AI return recorded with method');
 arc($row['actor_hash']===$issuer,'Browser handover preserves issuer actor for independence checks');
 arc($meta['human_influence']['human_disclosure']['level']==='none','Packet cannot replace captured human declaration');
 arc($meta['terms_version']===$g['consent']['terms_version']&&$meta['authorization']===$g['consent'],'Accepted policy and consent provenance retained');
 arc(ar_record($r,$g,$d,false)===$id,'Identical uncertain retry returns recorded result');
 $different=$d;$different['reason']='A different result using the consumed authorization.';ard(fn()=>ar_record($r,$g,$different,false),409,'Changed duplicate cannot reuse authorization');
 arc(ar_read($r,$g['id'])['result_id']===$id,'Used grant identifies existing receipt');
 $public=json_encode(context_data($r['id']));arc(!str_contains($public,$secret)&&!str_contains($public,$g['secret_hash'])&&!str_contains($public,$issuer)&&!str_contains($public,'browser_returns'),'Public context excludes credentials and actor capability');
 arc(str_contains($public,'ai_browser'),'Public context allowlists recorded method');
 [$held]=ar_test_grant($r,$t,$post);$packet=ar_test_packet($r,$t,$held);$packet['human_interaction']=['level'=>'influenced','details'=>'Synthetic later human source guidance after authorization.'];$qid=ar_record($r,$held,$packet,false);
 arc(one('SELECT admission FROM workflow_submissions WHERE id=?',[$qid])['admission']==='quarantined','Later substantive input quarantines AI browser result');
 arc(ar_read($r,$held['id'])['result_id']===$qid,'Quarantine consumes authorization once');
 arc(!in_array($qid,array_column(wf_public_data($r['id'])['submissions'],'id'),true),'Quarantined body excluded from evidence package');
 wf_review_influence($r['id'],$qid,'Reject this synthetic influenced packet for testing.','reject');
 arc(one('SELECT admission FROM workflow_submissions WHERE id=?',[$qid])['admission']==='rejected','Recorded quarantine can be rejected without new evidence');
 [$stale]=ar_test_grant($r,$t,$post);$packet=ar_test_packet($r,$t,$stale);query('UPDATE workflow_tasks SET revision=revision+1 WHERE id=?',[$t['id']]);ard(fn()=>ar_record($r,$stale,$packet,false),409,'Stale ready task cannot advance');query('UPDATE workflow_tasks SET revision=revision-1 WHERE id=?',[$t['id']]);
 query('UPDATE browser_returns SET expires_at=? WHERE id=?',['2000-01-01 00:00:00',$stale['id']]);ard(fn()=>ar_record($r,$stale,$packet,true),403,'Expired authorization cannot submit');
 [$changed]=ar_test_grant($r,$t,$post);$packet=ar_test_packet($r,$t,$changed);$old=setting('terms_version',TERMS_VERSION);set_setting('terms_version','changed-policy');ard(fn()=>ar_record($r,$changed,$packet,true),409,'Changed legal terms cannot reuse old consent');set_setting('terms_version',$old);
 $manual=wf_test_payload($r['id'],$t,'progress');$unconfirmed=$manual;$unconfirmed['terms']=null;ard(fn()=>wf_test_submit($r['id'],$t['id'],$unconfirmed,wf_actor(),true,'manual'),422,'Manual result requires the current resource/publication confirmation');
 $forged=parse_contribution(['json'=>json_encode(['terms'=>1]),'terms'=>null]);ard(fn()=>create_contribution($r['id'],$forged),422,'Packet-supplied confirmation cannot replace the supplementary form checkbox');
 $mid=wf_test_submit($r['id'],$t['id'],$manual,wf_actor(),false,'manual');arc(one('SELECT submission_method FROM workflow_submissions WHERE id=?',[$mid])['submission_method']==='manual','Unchanged manual return labeled separately without quarantine');
 arc(ar_method(null)==='Submission method not recorded'&&ar_method('manual')==='Manually submitted'&&ar_method('ai_browser')==='AI browser submission','Method display does not backfill history');
 $other=create_research(['title'=>'[QA] Foreign authorization parent','question'=>'Synthetic public parent boundary check for browser returns.','category_id'=>1,'terms'=>1]);moderate('request',$other['id'],'Approved','Synthetic foreign-parent test.');ard(fn()=>ar_read(research($other['id']),$g['id']),404,'Grant cannot cross research parents');
 query("UPDATE research SET status='Withdrawn' WHERE id=?",[$r['id']]);ard(fn()=>ar_read($r,$g['id']),404,'Withdrawal revokes public return access');query("UPDATE research SET status='Needs Research Plan' WHERE id=?",[$r['id']]);
 $tool=$r;$tool['project_type']='research_tool';$tool['tool_requirements']='{}';
 // Consent validation is independent of packet content and cannot be satisfied by an AI packet flag.
 if(rt_is_tool($tool)){ard(fn()=>ar_consent($tool,$post),422,'Separate software consent required');$toolPost=$post+['tool_terms'=>1];$c=ar_consent($tool,$toolPost);arc($c['tool_terms_version']===RT_TERMS&&$c['software_license']===RT_LICENSE,'Exact software consent version recorded');}
 $own=ar_issue($r,$t['id'],$post);preg_match('~#([a-f0-9]{64})~',$own['handoff'],$match);$_SESSION['browser_returns'][$own['id']]=hash('sha256',$match[1]);$ownGrant=ar_read($r,$own['id']);$ownPacket=ar_test_packet($r,$t,$ownGrant);$ownPacket['limitations']=$match[1];ard(fn()=>ar_record($r,$ownGrant,$ownPacket,true),422,'Private credential cannot enter public evidence');
 $supplement=ar_issue($r,null,$post+['contribution_kind'=>'Progress update']);preg_match('~#([a-f0-9]{64})~',$supplement['handoff'],$match);$_SESSION['browser_returns'][$supplement['id']]=hash('sha256',$match[1]);$sg=ar_read($r,$supplement['id']);
 $cd=contribution_schema();$cd['kind']='Progress update';arc($sg['scope']['contribution_kind']==='Progress update'&&str_contains($supplement['handoff'],'Progress update'),'Supplementary authorization preserves chosen contribution type');foreach(['summary','body','methodology','sources','uncertainty','limitations','previous_work','open_questions','next_actions','safety'] as $field)$cd[$field]='Synthetic supplementary fixture; no scientific claim or external evidence.';
 $cd=rs_test_supplement($cd);
 arc(ar_record($r,$sg,$cd,true)===''&&!ar_read($r,$sg['id'])['result_id'],'Supplementary check is read-only and does not consume consent');
 $cid=ar_record($r,$sg,$cd,false);$contribution=one('SELECT * FROM contributions WHERE id=?',[$cid]);arc($contribution['submission_method']==='ai_browser'&&$contribution['moderation']==='Needs Additional Review','Supplementary AI return keeps provenance moderation gate');
 arc(ar_record($r,$sg,$cd,false)===$cid,'Supplementary duplicate returns existing record');
 $bad=$cd;$bad['kind']='Independent review';ard(fn()=>ar_record($r,$sg,$bad,false),409,'Supplementary authorization cannot change kind or target');
 arc(!in_array($cid,array_column(context_data($r['id'])['contributions'],'id'),true),'Pending supplementary content remains private');
 $meta=json_decode($contribution['human_influence'],true);arc($meta['authorization']['terms_version']===$sg['consent']['terms_version'],'Supplementary consent version is retained');
 $sourceTask=wf_new_task($r['id'],1,'test-source','research','Synthetic independence source','Only a local identity fixture, with no scientific claim.','Retain the synthetic fixture evidence.','Record fixture scope and limitations.',[]);wf_test_submit($r['id'],$sourceTask,wf_test_payload($r['id'],wf_task($r['id'],$sourceTask)),$issuer);
 $syn=wf_new_task($r['id'],1,'test-synthesis','synthesis','Synthetic independence target','Synthetic testing of issuer actor preservation only.','Test-only synthesis placeholder.','Test actor preservation.',[]);$synTask=wf_task($r['id'],$syn);$synResult=wf_test_submit($r['id'],$syn,wf_test_payload($r['id'],$synTask),$issuer);
 $review=wf_new_task($r['id'],1,'test-review','review','Synthetic independent review','Only test contributor authorization boundaries.','A test review, never scientific evidence.','Different contributor required.',[]);query('UPDATE workflow_tasks SET dependencies=? WHERE id=?',[json_encode([$syn]),$review]);$reviewTask=wf_task($r['id'],$review);
 ard(fn()=>ar_ready($r,$reviewTask,$issuer),422,'Separate browser cannot shed original contributor for self-review');
 ar_ready($r,$reviewTask,hash('sha256','independent-test-actor'));arc(true,'A distinct contributor can authorize an independent review');
 echo count($checks)." scoped browser-return checks passed.\n";
}finally{if(db()->inTransaction())db()->rollBack();}
