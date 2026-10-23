<?php
require __DIR__.'/bootstrap.php';
require_once ROOT.'/src/volunteer-sessions.php';
session_start_safe();
$_SESSION['admin']=1;
putenv('RATE_LIMIT_NAMESPACE=volunteer-session-test-'.uid());
$checks=[];
function vsc(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException($label);
    $checks[]=$label;
}
function vsd(callable $call, int $status, string $label): void {
    try { $call(); }
    catch (HttpError $error) { vsc($error->status===$status, $label); return; }
    throw new RuntimeException('Expected rejection: '.$label);
}
db()->beginTransaction();
try {
    $post=['scope_kind'=>'all','assignment_limit'=>3,'minutes'=>60,'authorize'=>1,'terms'=>1,'authorized_resources'=>1,'policy_version'=>terms_version(),'human_level'=>'none','model'=>'gpt-6-astra','reasoning'=>'max','tool'=>'Codex'];
    foreach (['authorize','terms','authorized_resources','human_level'] as $field) {
        $bad=$post; unset($bad[$field]);
        vsd(fn()=>vs_create($bad), 422, 'Missing '.$field.' cannot authorize a session');
    }
    $bad=$post; $bad['policy_version']='obsolete';
    vsd(fn()=>vs_create($bad), 409, 'Changed terms need fresh human authorization');
    foreach (['assignment_limit'=>[0,21,'3.5',[],true], 'minutes'=>[0,241,'60x',[]]] as $field=>$values) {
        foreach ($values as $value) {
            $bad=$post; $bad[$field]=$value;
            vsd(fn()=>vs_configuration($bad), 422, 'Invalid bounded '.$field.' rejected: '.json_encode($value));
        }
    }
    $GLOBALS['vs_test_now']=strtotime('2026-10-22 16:00:00 UTC');
    // Use a new test namespace after deliberate invalid authorization attempts.
    putenv('RATE_LIMIT_NAMESPACE=volunteer-session-valid-'.uid());
    $created=vs_create($post);
    $session=vs_read($created['id']);
    $originalActor=wf_actor();
    vsc($session['actor_hash']===$originalActor, 'Session pins original anonymous volunteer actor');
    vsc((int)$session['assignment_limit']===3 && (int)$session['assignments_used']===0, 'Defaults allow three assignments with zero consumed');
    vsc($session['expires_at']==='2026-10-22 17:00:00', 'Default deadline is exactly one server hour');
    vsc($session['consent']['human_disclosure']===['level'=>'none','details'=>''], 'Independent volunteer declaration is captured');
    vsc($session['consent']['tool_terms_version']===null, 'Software consent is not inferred');
    vsc(str_contains($session['consent']['permission'], 'No purchases, installation, unrelated work'), 'Permission is explicitly bounded against unrelated actions');
    $secret=parse_url($created['link'], PHP_URL_FRAGMENT);
    vsc(strlen($secret)===64 && !str_contains(json_encode($session),$secret), 'Only a hash of the random fragment credential is stored');
    $_SESSION['volunteer_sessions']=[];
    vsd(fn()=>vs_read($created['id']), 403, 'Public session identity alone grants no private access');
    vsd(fn()=>vs_exchange($created['id'], str_repeat('0',64)), 403, 'Incorrect session credential rejected');
    $_SESSION['workflow_actor']=bin2hex(random_bytes(32));
    vs_exchange($created['id'], $secret);
    vsc(wf_actor()!==$originalActor && vs_read($created['id'])['actor_hash']===$originalActor, 'Separate anonymous browser preserves original session lineage');
    $before=one('SELECT COUNT(*) n FROM volunteer_sessions')['n'];
    vs_read($created['id']);
    vsc($before===one('SELECT COUNT(*) n FROM volunteer_sessions')['n'], 'Reading a session does not create another authorization');
    $limit=$session; $limit['assignments_used']=3;
    vsc(vs_state($limit)==='limit_reached', 'Allowance exhaustion has its own state');
    vs_assert_active($limit);
    vsd(fn()=>vs_assert_active($limit,true), 409, 'Allowance exhaustion blocks more pickups but permits the current assignment');
    $GLOBALS['vs_test_now']++;
    vsc(vs_state($session)==='active', 'Session is active before the exact deadline');
    $GLOBALS['vs_test_now']=strtotime($session['expires_at'].' UTC');
    vsc(vs_state($session)==='expired', 'Expiry is inclusive at the exact deadline');
    vsd(fn()=>vs_assert_active($session), 409, 'Expired session cannot submit or renew');
    vsc(vs_read($created['id'])['id']===$created['id'], 'Expired session remains privately readable for receipts');
    $GLOBALS['vs_test_now']--;
    $stopped=$session; $stopped['stopped_at']=gmdate('Y-m-d H:i:s',vs_now());
    vsc(vs_state($stopped)==='stopped', 'Stop has a distinct terminal state');
    vsd(fn()=>vs_assert_active($stopped), 409, 'Stopped session cannot continue');
    $changed=$session; $changed['consent']['terms_version']='old';
    vsc(vs_state($changed)==='terms_changed', 'Policy change invalidates ongoing authorization');
    vsd(fn()=>vs_assert_active($changed), 409, 'Policy change requires new volunteer authorization');
    $createdResearch=create_research(['title'=>'[QA] Volunteer scope and capabilities','question'=>'Synthetic scope validation for volunteer session authorization.','category_id'=>1,'terms'=>1]);
    $config=$post; $config['scope_kind']='research'; $config['research_id']=$createdResearch['id'];
    vsd(fn()=>vs_configuration($config), 404, 'Private research cannot be selected through authorization');
    moderate('request',$createdResearch['id'],'Approved','Synthetic session scope test.');
    $research=research($createdResearch['id']); $task=wf_tasks($research['id'])[0];
    vsc(vs_configuration($config)['scope_id']===$research['id'], 'Public research scope resolves to immutable identity');
    $config['scope_kind']='category'; $config['category_id']=1;
    vsc(vs_configuration($config)['scope_id']==='1', 'Category scope is validated against existing taxonomy');
    $config['category_id']=2147483647;
    vsd(fn()=>vs_configuration($config), 422, 'Invented category rejected');
    vsc(vs_configuration_matches($session,$research,$task), 'Planning matches declared eligible exact model and Max effort');
    $unknown=$session; $unknown['capabilities']=['model'=>'','reasoning'=>'','tool'=>'','workflow_mode'=>''];
    vsc(!vs_configuration_matches($unknown,$research,$task), 'Unknown capability does not silently match planning');
    $lower=$session; $lower['capabilities']['reasoning']='high';
    vsc(!vs_configuration_matches($lower,$research,$task), 'Queue cannot bypass platform planning effort');
    $toolResearch=$research; $toolResearch['project_type']='research_tool';
    vsc(!vs_configuration_matches($session,$toolResearch,$task), 'Absent separate software consent excludes tool assignments');
    $withSoftware=$session; $withSoftware['consent']['tool_terms_version']=RT_TERMS;
    vsc(vs_configuration_matches($withSoftware,$toolResearch,$task), 'Current explicit software consent permits otherwise matching tool assignment');
    $restricted=$research; $restricted['ai_requirements']=json_encode(['model'=>'claude-opus-5-5','mode'=>'exact','effort'=>'max']);
    vsc(!vs_configuration_matches($session,$restricted,$task), 'Queue respects exact requester model restriction');
    $bad=$post; $bad['model']='claude-fable-5-1'; $bad['reasoning']='ultra';
    vsd(fn()=>vs_configuration($bad), 422, 'Unsupported model effort is rejected');
    $bad['reasoning']='max'; $bad['workflow_mode']='ultracode';
    vsd(fn()=>vs_configuration($bad), 422, 'Ultracode cannot be misrepresented as a generic effort or tool');
    $bad['tool']='Claude Code';
    vsc(vs_configuration($bad)['capabilities']['workflow_mode']==='ultracode', 'Ultracode remains a separate supported workflow mode');
    vsc(!str_contains(json_encode(context_data($research['id'])),$session['secret_hash']), 'Public research context contains no session ownership credential');
    echo json_encode(['pass'=>true,'checks'=>$checks],JSON_PRETTY_PRINT)."\n";
} finally {
    db()->rollBack();
    unset($GLOBALS['vs_test_now']);
}
