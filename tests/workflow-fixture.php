<?php
function wf_test_payload(string $rid,array $t,string $outcome='completed'): array {
    $s=wf_snapshot($rid);$d=wf_schema($t,$s);
    $d+=['terms'=>1,'human_disclosure'=>['level'=>'none','details'=>'']];
    $d['outcome']=$outcome;$d['reason']='Synthetic local workflow test outcome with a clear next action.';
    $d['body']='This synthetic test does not establish scientific facts. It exercises the local workflow and preserves traceable evidence.';
    $d['methodology']='Checked deterministic local fixture behavior and recorded the result.';
    $d['sources']='Synthetic test fixture; no scientific source claim is made.';
    $d['limitations']='Synthetic evidence only; no actual scientific investigation.';
    $d['next_action']='Continue with the next dependency-ready task or request the specified clarification.';
    $d['metadata']=['provider'=>'OpenAI','model'=>'gpt-6-astra','reasoning'=>'max','tool'=>'Codex','run_id'=>uid(),'run_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'run_evidence'=>'Synthetic local QA metadata; not a real AI run.'];
    if($t['kind']==='planning')$d['tasks']=[
        ['key'=>'exposure','title'=>'Establish marine exposure ranges','objective'=>'Extract marine plastic exposures by depth, particle size and sampling method.','deliverable'=>'A table of comparable observations and explicit data gaps.','acceptance'=>'Distinguish particle counts from mass and explain sampling limitations.','evidence_needs'=>'Inspect supplied observations and record the method, units and limits.','stopping_rule'=>'Stop when comparable observations and explicit access gaps are documented.','dependencies'=>[]],
        ['key'=>'transport','title'=>'Evaluate biological carbon transport','objective'=>'Compare transport mechanisms against the exposure baseline and report uncertainty.','deliverable'=>'An evidence table linking exposures to measured carbon endpoints.','acceptance'=>'Separate measured carbon flux from particle sinking proxies.','evidence_needs'=>'Inspect original carbon measurements and competing mechanisms.','stopping_rule'=>'Stop after comparing accessible evidence and recording unresolved endpoints.','dependencies'=>['exposure']]
    ];
    $d['evidence']=['version'=>1,'scope'=>'Exercise synthetic workflow behavior only; no scientific conclusion is established.',
        'stopping_rule'=>'Stop after the supplied deterministic fixture and current workflow references have been checked.',
        'search_log'=>[['query'=>'Inspect the supplied synthetic workflow fixture','where'=>'tests/workflow-fixture.php and the local task context','result'=>'Inspected the provided fixture text only; no external literature search or scientific verification was performed.']],
        'sources'=>[['id'=>'s1','title'=>'Explicitly synthetic local workflow fixture','url'=>'','type'=>'provided','access'=>'full_text','access_note'=>'Read the local synthetic fixture body in tests/workflow-fixture.php; no scientific source is claimed.','authors'=>'','publication_date'=>'','publisher'=>'','doi'=>'','edition'=>'','metadata_note'=>'Synthetic fixture: scholarly publication metadata is not applicable and was not collected.','use'=>'used']],
        'claims'=>[['id'=>'c1','statement'=>$d['body'],'kind'=>'finding','support'=>'supported','confidence'=>'moderate',
            'confidence_reason'=>'The provided fixture states its synthetic scope explicitly; this tests record structure only.','derivation'=>'',
            'limitations'=>'Synthetic QA cannot establish scientific facts, real model provenance or real source accuracy.',
            'counterevidence'=>'No external research search was performed; this bounded fixture makes no competing scientific claim.',
            'basis_result_ids'=>$t['kind']==='synthesis'?array_keys(wf_evidence_results($t)):[],
            'links'=>[['source_id'=>'s1','relation'=>'supports','locator'=>'Fixture body','excerpt'=>$d['body'],'explanation'=>'The supplied text explicitly identifies this as a synthetic local workflow exercise.']]]],
        'review_checks'=>wf_evidence_template($t)['review_checks'],'artifact_reviews'=>ra_review_template($t)];
    foreach($d['evidence']['review_checks'] as &$audit){
        $audit['verdict']=$audit['source_checks']?'supported':($audit['claim_id']==='legacy'?'unverifiable':'limited');$audit['reason']='Inspected the supplied synthetic text and its declared limits; this is not scientific verification.';
        foreach($audit['source_checks'] as &$inspection){$inspection['access']='full_text';$inspection['locator']='Synthetic fixture body';$inspection['excerpt']=$d['body'];$inspection['assessment']='The supplied fixture text explicitly limits its claim to deterministic workflow testing.';}unset($inspection);
    }unset($audit);
    foreach($d['evidence']['artifact_reviews'] as &$audit){$audit['verdict']='supported';$audit['reason']='Synthetic fixture audit checks local data fidelity, labels, assumptions and limits only; no real scientific verification is claimed.';}unset($audit);
    foreach($d['acceptance_checks'] as &$check){$check['status']='met';$e=$check['result_id']==='self'?$d:one('SELECT * FROM workflow_submissions WHERE id=?',[$check['result_id']]);$check['evidence_quote']=$e['body'];$check['reason']='Synthetic evidence pointer for deterministic workflow testing only.';$check['correction_response']='Synthetic correction response: reviewed the requested uncertainty and updated the evidence assessment.';}unset($check);
    if($outcome==='needs_correction'){$syn=wf_task($rid,json_decode($t['dependencies'],true)[0]);$d['corrections']=[['task_id'=>$syn['id'],'result_id'=>$syn['accepted_id'],'criterion_id'=>'criterion-1','reason'=>'Synthetic review requires a clearer synthesis.','required_change'=>'Clarify the synthetic result and retain its evidence.']];}
    return rs_test_materials($d,$t);
}
function rs_test_materials(array $d,?array $task=null): array {
    $e=$d['evidence']??[];$required=rs_required_materials($e);$items=[];
    foreach(RS_CATEGORIES as $category){$refs=$required[$category]??[];$items[]=['category'=>$category,'status'=>$refs?'supplied':'not_applicable','details'=>$refs?'Synthetic QA materials are retained at the exact structured references; no scientific verification is claimed.':'Not applicable to the synthetic work performed; no additional code, execution or unrecorded failed approaches are claimed.','refs'=>array_values(array_unique($refs)),'access_instructions'=>''];}
    $d['evidence']['supporting_materials']=['version'=>1,'work_performed'=>'narrative','items'=>$items,'files'=>[]];
    if($task&&$task['kind']==='review'){
        $d['evidence']['material_reviews']=[];foreach(wf_evidence_results($task) as $target){$result=$target['result'];$e=json_decode($result['metadata'],true)['evidence']??[];$support=$e['supporting_materials']??null;$refs=['body','methodology','sources'];foreach($support['items']??[] as $item)$refs=array_merge($refs,$item['refs']);
            $d['evidence']['material_reviews'][]=['task_id'=>$target['task']['id'],'result_id'=>$result['id'],'verdict'=>'complete','reason'=>'Synthetic test audit inspected the retained materials and their declared limits; historical completeness needs a manual whole-result audit.','checked_refs'=>array_values(array_unique($refs))];
        }
    }
    return wf_test_contract_fields($d,$task);
}
/** Only newly authored synthetic packets adopt the contract selected by their stored snapshot. */
function wf_test_contract_fields(array $d,?array $task): array {
    if(!$task||!function_exists('wf_packet_contract_version')||!is_string($d['context_id']??null))return $d;
    $snapshot=one('SELECT package_json FROM workflow_snapshots WHERE id=? AND research_id=?',[$d['context_id'],$task['research_id']]);
    if(!$snapshot||wf_packet_contract_version($snapshot)!==WF_PACKET_VERSION)return $d;
    $finding='This synthetic packet organizes a checkable workflow record and establishes no scientific conclusion.';
    $d['usefulness']=['version'=>1,'question_helped'=>'Which parts of this explicitly synthetic workflow are checkable?',
        'intended_user'=>'A local software reviewer exercising the declared workflow; this is not scientific research.','finding'=>$finding,
        'why_it_matters'=>'The retained fixture exposes exactly what software behavior was checked and its limits.',
        'increment'=>'This authored fixture organizes retained evidence and uncertainty into a reproducible local test.',
        'established'=>'Only deterministic local workflow behavior is exercised; no scientific result is established.',
        'uncertain'=>'Real-world effects, applicability and independent scientific source support remain unexamined.',
        'reusable_output'=>'The retained synthetic packet, declared method and evidence can be checked in a local regression.',
        'next_use'=>'A separate software reviewer can reproduce these local checks before real scientific work.'];
    if(!str_contains($d['body'],$finding))$d['body'].="\n\n".$finding;
    $d['evidence']['usefulness_reviews']=[];
    foreach(wf_usefulness_review_template($task) as $review){
        $target=one('SELECT body FROM workflow_submissions WHERE id=?',[$review['result_id']]);preg_match('/^.{1,500}/us',$target['body'],$quote);
        $review['verdict']='useful';$review['reason']='This explicitly synthetic output retains checkable local workflow evidence for the requested fixture deliverable; it establishes no real scientific usefulness.';
        $review['output_quote']=$quote[0];$review['next_use']='Reproduce the retained local software regression check only; no scientific verification is claimed.';
        $d['evidence']['usefulness_reviews'][]=$review;
    }
    return $d;
}
