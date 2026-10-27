<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/admin-catalogue-fixture.php';require_once ROOT.'/src/view.php';
session_start_safe();$_SESSION['admin']=1;$checks=[];$sessions=[];$f=null;
function ac_check(bool $ok,string $label): void {global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
function ac_deny(callable $fn,int $status,string $label): void {try{$fn();}catch(HttpError $e){ac_check($e->status===$status,$label);return;}throw new RuntimeException('Expected rejection: '.$label);}
function ac_session(array $f,string $scope): array {
    global $sessions;$_SESSION['workflow_actor']=bin2hex(random_bytes(32));putenv('RATE_LIMIT_NAMESPACE=catalogue-session-'.uid());
    $s=vs_create(['scope_kind'=>$scope,'category_id'=>$f['category'],'research_id'=>$f['ids']['queue'],'session_preset'=>'quick','worker_choice'=>'astra_max','authorize'=>1,'terms'=>1,'authorized_resources'=>1,'policy_version'=>terms_version()]);$sessions[]=$s['id'];return $s;
}
try {
    $f=ac_fixture_seed();$rid=$f['ids']['usage'];$r=research($rid);$term=substr($r['title'],-32);$version=$r['context_version'];$status=$r['status'];
    ac_check((int)$r['discovery_listed']===1&&(int)research($f['ids']['private'],false)['discovery_listed']===1,'Existing migration default and new requests are listed without a title heuristic');
    $usage=admin_research_usage(array_values($f['ids']));$u=$usage[$rid];
    ac_check($u['workflow_total']===4&&$u['supplementary_total']===4,'Each record family counts once despite multiple records in both tables');
    ac_check($u['admission']===['accepted'=>2,'quarantined'=>1,'rejected'=>1]||($u['admission']['accepted']===2&&$u['admission']['quarantined']===1&&$u['admission']['rejected']===1),'Workflow admission distinguishes accepted, quarantined and rejected');
    ac_check($u['visibility']['public']===3&&$u['visibility']['removed']===1,'Visibility is independent of admission and does not imply a removed result remains public');
    ac_check($u['moderation']['Approved']===1&&$u['moderation']['Pending Approval']===1&&$u['moderation']['Rejected']===1&&$u['moderation']['Removed']===1,'Supplementary totals retain their moderation breakdown');
    ac_check($usage[$f['ids']['private']]['workflow_total']===0&&$usage[$f['ids']['private']]['supplementary_total']===0,'Records without usage show zero counts');
    $_SESSION['admin']=null;ac_deny(fn()=>admin_research_usage([$rid]),403,'Operational aggregates require admin authorization');ac_deny(fn()=>admin_set_discovery_listing($rid,false,'Unauthorized listing change.'),403,'Listing mutation requires admin authorization');$_SESSION['admin']=1;
    ac_deny(fn()=>admin_set_discovery_listing($rid,false,'short'),422,'Listing change requires a meaningful audit reason');
    ac_check(discover(['q'=>$term])['total']===1,'Listed synthetic request appears in keyword search');
    $defaultSessions=[];foreach(['all','category'] as $scope)$defaultSessions[$scope]=ac_session($f,$scope);
    ac_check(admin_set_discovery_listing($rid,false,'Hide explicitly identified synthetic local fixture.'),'Admin can unlist an approved request');
    ac_check(!admin_set_discovery_listing($rid,false,'Repeat should not append a duplicate event.'),'Repeating the same choice is idempotent');
    ac_check(discover(['q'=>$term])['total']===0,'Unlisted request is omitted from keyword search');
    admin_set_discovery_listing($f['ids']['queue'],false,'Hide synthetic queue fixture from the default pool.');
    $cats=array_column(discovery_categories(),null,'id');ac_check((int)$cats[$f['category']]['request_count']===0,'Category counts omit unlisted and pending requests');
    $after=research($rid);ac_check($after['id']===$rid&&$after['status']===$status&&$after['context_version']===$version&&$after['moderation']==='Approved','Direct parent access retains status, moderation and context version');
    ac_check(count(wf_public_data($rid)['submissions'])===1&&count(approved_contributions($rid))===1,'Direct public context retains only normally visible accepted children');
    ac_check(wf_read_snapshot($rid,one('SELECT snapshot_id FROM workflow_submissions WHERE id=?',[$f['result']])['snapshot_id'])['id']!=='','Archived accepted proof remains directly readable');
    ac_check(count(wf_ledger($rid,true))>count(wf_ledger($rid))&&!str_contains(json_encode(context_data($rid)),'Hide explicitly identified'),'Listing reason stays in the private audit ledger');
    $events=all("SELECT * FROM audit_events WHERE research_id=? AND action='Discovery listing changed'",[$rid]);ac_check(count($events)===1&&$events[0]['visibility']==='private'&&$events[0]['old_state']==='Listed'&&$events[0]['new_state']==='Unlisted','Listing change has one private append-only audit event');
    admin_set_discovery_listing($f['ids']['private'],false,'Synthetic pending request remains private while unlisted.');admin_set_discovery_listing($f['ids']['private'],true,'Listing cannot publish a pending request.');ac_deny(fn()=>research($f['ids']['private']),404,'Relisting does not publish a pending parent');
    $scoped=ac_session($f,'research');ac_check(vs_startup_preflight(vs_read($scoped['id']))['eligible']===1,'Explicit research scope preview retains eligible unlisted work');
    $assigned=vs_claim_next($scoped['id']);ac_check($assigned['research_id']===$f['ids']['queue']&&$assigned['task_id']===$f['queue_task'],'Explicit research scope can pick up unlisted work with normal consent and eligibility');
    vs_stop($scoped['id']);
    foreach(['all','category'] as $scope){$s=$defaultSessions[$scope];$p=vs_startup_preflight(vs_read($s['id']));ac_check($p['eligible']===0,'Default '.$scope.' preview omits unlisted requests');ac_deny(fn()=>vs_claim_next($s['id']),409,'Default '.$scope.' pickup omits unlisted requests');vs_stop($s['id']);}
    $single=wr_claim($f['ids']['queue'],$f['queue_task'],1,hash('sha256',uid()));ac_check($single['task_id']===$f['queue_task'],'Direct single-task pickup retains ordinary eligibility');wr_release($f['ids']['queue'],$f['queue_task'],$single['actor_hash']);
    admin_set_discovery_listing($f['ids']['queue'],true,'Restore local synthetic fixture to default discovery.');$s=ac_session($f,'category');$a=vs_claim_next($s['id']);admin_set_discovery_listing($f['ids']['queue'],false,'Unlisting must preserve an already authorized assignment.');ac_check(vs_claim_next($s['id'])['id']===$a['id']&&vs_handoff($s['id'],$a['id'])!=='','Unlisting does not revoke an already authorized assignment');vs_stop($s['id']);
    admin_set_discovery_listing($rid,true,'Restore the explicitly identified local fixture.');ac_check(discover(['q'=>$term])['total']===1,'Relisting restores the same record in keyword discovery');
    query("UPDATE research SET status='Withdrawn' WHERE id=?",[$f['ids']['queue']]);ac_deny(fn()=>research($f['ids']['queue']),404,'Unlisting does not bypass withdrawn parent access');
    file_put_contents(storage('qa/admin-catalogue-backend.json'),json_encode(['passed'=>count($checks),'checks'=>$checks,'scope'=>'Synthetic local records only.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo count($checks)." administration/discovery checks passed.\n";
} finally {
    foreach($sessions as $id){try{vs_stop($id);}catch(Throwable $e){}query('DELETE FROM volunteer_sessions WHERE id=?',[$id]);}
    if($f)ac_fixture_cleanup($f);
}
