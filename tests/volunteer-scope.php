<?php
require __DIR__.'/bootstrap.php';
session_start_safe();$_SESSION['admin']=1;
$projects=[];$sessions=[];$categories=[];$checks=[];
function vscq(bool $ok,string $label): void {global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
function vscq_session(array $scope): string {
    global $sessions;$_SESSION['workflow_actor']=bin2hex(random_bytes(32));
    $created=vs_create($scope+['assignment_limit'=>3,'minutes'=>60,'model'=>'gpt-6-astra','reasoning'=>'max','tool'=>'Codex','authorize'=>1,'terms'=>1,'authorized_resources'=>1,'policy_version'=>terms_version(),'human_level'=>'none']);
    $sessions[]=$created['id'];return $created['id'];
}
try {
    foreach(['selected','other'] as $label)$categories[]=insert('categories',['name'=>'QA volunteer scope '.$label.' '.uid()]);
    foreach([$categories[0],$categories[1],$categories[0]] as $i=>$category){
        $r=create_research(['title'=>'[QA] Volunteer scope queue '.uid(),'question'=>'Synthetic category and question queue boundary checks, without scientific claims.','category_id'=>$category,'terms'=>1]);$projects[]=$r['id'];
        if($i!==2)moderate('request',$r['id'],'Approved','Owned synthetic scope fixture.');
    }
    $id=vscq_session(['scope_kind'=>'category','category_id'=>$categories[0]]);$a=vs_claim_next($id);
    vscq($a['research_id']===$projects[0],'Category queue selects matching public work and excludes private/different-category work');
    $other=research($projects[1]);$otherTask=wf_tasks($other['id'])[0];
    try{vs_task_eligible(vs_read($id),$other,$otherTask);throw new RuntimeException('Unrelated task admitted');}catch(HttpError $e){vscq($e->status===409,'Session authorization cannot be repurposed for another category');}
    vs_stop($id);
    $id=vscq_session(['scope_kind'=>'research','research_id'=>$projects[0]]);
    try{vs_task_eligible(vs_read($id),$other,$otherTask);throw new RuntimeException('Unrelated research admitted');}catch(HttpError $e){vscq($e->status===409,'One-question scope rejects otherwise eligible unrelated research');}
    vscq(vs_claim_next($id)['research_id']===$projects[0],'One-question queue assigns the exact authorized immutable research identity');vs_stop($id);
    $id=vscq_session(['scope_kind'=>'all']);$s=vs_read($id);
    foreach(array_slice($projects,0,2) as $rid){$r=research($rid);vs_task_eligible($s,$r,wf_tasks($rid)[0]);}
    vscq(true,'Help wherever needed permits eligible work from both categories');
    $a=vs_claim_next($id);$r=research($a['research_id']);$t=wf_task($r['id'],$a['task_id']);
    vscq($r['moderation']==='Approved'&&$t['state']==='Ready'&&$t['visibility']==='public','Unscoped actual pickup still passes public Ready-work gates');vs_stop($id);
    change_status($projects[0],'Paused','Synthetic scope exhaustion.','requester');
    $id=vscq_session(['scope_kind'=>'category','category_id'=>$categories[0]]);
    try{vs_claim_next($id);throw new RuntimeException('Empty category invented work');}catch(HttpError $e){vscq($e->status===409,'Unavailable matching work never falls back to an unrelated category');}
    vscq((int)vs_read($id)['assignments_used']===0,'No eligible task consumes no category-session allowance');vs_stop($id);
    echo count($checks)." volunteer scope queue checks passed.\n";
    if(!is_dir(storage('qa/volunteers')))mkdir(storage('qa/volunteers'),0700,true);
    file_put_contents(storage('qa/volunteers/scope-tests.json'),json_encode(['pass'=>true,'checks'=>$checks],JSON_PRETTY_PRINT));
} finally {
    foreach($sessions as $id){try{vs_stop($id);}catch(HttpError){}query('DELETE FROM volunteer_sessions WHERE id=?',[$id]);}
    foreach($projects as $rid)if(one('SELECT id FROM research WHERE id=?',[$rid]))purge_request($rid,$rid);
    foreach($categories as $id)query('DELETE FROM categories WHERE id=?',[$id]);
}
