<?php
require_once __DIR__.'/workflow-fixture.php';
function rt_test_request(): array {
    $d=['title'=>'[QA] Build a metric distance research tool '.uid(),'question'=>'Build a bounded reusable distance conversion tool with reference cases, unit assumptions and independently reproducible tests.','category_id'=>one('SELECT id FROM categories LIMIT 1')['id'],'project_type'=>'research_tool','terms'=>1];
    foreach(RT_FIELDS as $key=>$label)$d['tool_'.$key]=match($key){
        'intended_users'=>'Researchers preparing metric-distance inputs for a documented calculation.',
        'requirements'=>'Convert finite nonnegative kilometres into metres; reject invalid and negative inputs. No geospatial projection or uncertainty propagation.',
        'inputs_outputs'=>'Input: one nonnegative finite number in kilometres. Output: the same distance in metres; invalid input produces an explicit error.',
        'validation_plan'=>'Reference 2.5 km = 2500 m, boundary 0 km = 0 m, negative input rejected. SI prefix definition provides the independent reference expectation.',
        default=>'Experimental synthetic QA tool; no ongoing maintenance is promised and no scientific result is established.'};
    return $d;
}
function rt_test_plan(string $rid,array $task): array {
    $d=wf_test_payload($rid,$task);$d['tool_terms']=1;$d['tasks']=[['key'=>'build','title'=>'Build and test metric conversion','objective'=>'Implement the bounded metric-distance specification and run reproducible tests.','deliverable'=>'Exact source/tests, reference/boundary/error cases, usage, environment, limitations and maintenance status.','acceptance'=>'Retain reproducible source and tests with independent reference expectations.','evidence_needs'=>'Inspect the supplied requirements and independently derive the SI prefix conversion.','stopping_rule'=>'Stop after all reference, boundary and invalid-input cases run with disclosed limitations.','builds_tool'=>true,'dependencies'=>[]]];return $d;
}
function rt_test_build(string $rid,array $task,string $outcome='completed'): array {
    $d=wf_test_payload($rid,$task,$outcome);$d['tool_terms']=1;$run=json_decode(file_get_contents(storage('qa/tool-builds/execution/builder/run.json')),true);$files=[];
    foreach([['source','distance-tool.mjs','source'],['tests','distance-tool-test.mjs','test']] as [$id,$name,$role]){$content=file_get_contents(ROOT.'/tests/fixtures/'.$name);$files[]=['id'=>$id,'name'=>$name,'role'=>$role,'content'=>$content,'sha256'=>hash('sha256',$content)];}
    $tests=[];foreach($run['tests'] as $test)$tests[]=$test+['purpose'=>'Check the independently specified '.$test['kind'].' behavior for a bounded metric-distance conversion.','file_id'=>'tests','absolute_tolerance'=>0,'relative_tolerance'=>0,'tolerance_reason'=>'Exact SI scaling on these representable fixture inputs; no rounding tolerance is needed.'];
    $d['evidence']['tool_builds']=[['id'=>'distance','title'=>'Metric distance conversion tool','version'=>'0.1.0','software'=>[['name'=>'Node.js','version'=>preg_match('/Node\.js (v[0-9.]+)/',$run['environment'],$softwareVersion)?$softwareVersion[1]:'unknown']],'status'=>'tested','license'=>RT_LICENSE,'source_ids'=>['s1'],'files'=>$files,'tests'=>$tests,
        'usage'=>'Import metres from distance-tool.mjs, call with finite nonnegative kilometres and read the metre output.','environment'=>$run['environment'],'command'=>$run['command'],
        'dependency_notes'=>'No third-party packages or vendored code; only Node.js built-ins for the synthetic test runner.',
        'scientific_assumptions'=>'One kilometre equals exactly one thousand metres. This fixture does not model measurement error, projections or scientific observations.',
        'limitations'=>'Synthetic software fixture only. Passing these tests is not scientific validation or comprehensive numerical coverage.','maintenance'=>'Experimental synthetic QA tool; no maintainer commitment or production readiness is claimed.']];return rs_test_materials($d,$task);
}
function rt_test_review(string $rid,array $task,string $verdict='reproduced'): array {
    $d=wf_test_payload($rid,$task,$verdict==='reproduced'?'verified':'needs_correction');$d['tool_terms']=1;$d['corrections']=[];$run=json_decode(file_get_contents(storage('qa/tool-builds/execution/reviewer/run.json')),true);
    $d['evidence']['tool_reviews']=[];
    foreach(rt_targets($task) as $target){$tests=[];foreach($run['tests'] as $test)$tests[]=array_intersect_key($test,array_flip(['id','status','observed','run_log']));
        $d['evidence']['tool_reviews'][]=['task_id'=>$target['task']['id'],'result_id'=>$target['result']['id'],'tool_id'=>$target['build']['id'],'verdict'=>$verdict,
            'method'=>$verdict==='reproduced'?'Inspected the retained source and tests, copied exact bytes into a separate directory and reran all cases in a fresh Node.js process.':'Synthetic review: reference coverage needs another independently derived case before this tool should be accepted.',
            'environment'=>$run['environment'],'command'=>$run['command'],'run_log'=>'Fresh independent-process test runner exited 0; reference, boundary and invalid-input assertions passed. Software fixture only.',
            'scientific_assessment'=>'The exact metric prefix definition independently establishes the expected conversion. This fixture makes no scientific-data or domain-validation claim. Zero tolerance is appropriate for these representable numbers.',
            'limitations'=>'Separate local processes test reproducibility only; declared contributor/browser/run separation does not authenticate independent people.',
            'test_results'=>$verdict==='reproduced'?$tests:[]];}
    return $d;
}
function rt_test_task(string $rid,string $kind): array {foreach(wf_tasks($rid) as $task)if($task['kind']===$kind)return $task;throw new RuntimeException('Missing task '.$kind);}