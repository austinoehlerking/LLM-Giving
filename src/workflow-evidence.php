<?php
// The server validates references and disclosures. Source support is a reviewer assessment.
function wf_evidence_list(array $data,string $key,int $max): array {
    $items=$data[$key]??null;
    if(!is_array($items)||!array_is_list($items)||count($items)>$max)fail(422,'Evidence '.$key.' must be a list with at most '.$max.' items.');
    foreach($items as $item)if(!is_array($item)||array_is_list($item))fail(422,'Each evidence '.$key.' entry must be an object.');
    return $items;
}
function wf_evidence_choice(array $data,string $key,array $choices): string {
    $value=text_field($data,$key,1,40);
    if(!in_array($value,$choices,true))fail(422,'Evidence '.$key.' must be '.implode(', ',$choices).'.');
    return $value;
}
function wf_evidence_id(array $data): string {
    $id=text_field($data,'id',1,40);
    if(!preg_match('/^[a-z][a-z0-9_-]*$/D',$id))fail(422,'Evidence IDs must start with a lowercase letter and use letters, digits, underscores or hyphens.');
    return $id;
}
function wf_evidence_results(array $task): array {
    research($task['research_id']);$out=[];
    if(($task['kind']??'')==='contribution_review'){
        foreach(rs_contributions($task['research_id']) as $target)if($target['id']===($task['target_id']??'')&&!$target['superseded_by'])return [$target['id']=>['task'=>['id'=>$target['id'],'research_id'=>$target['research_id'],'kind'=>'supplementary'],'result'=>$target+['metadata'=>json_encode(['evidence'=>$target['evidence']?json_decode($target['evidence'],true):null])]]];
        fail(422,'Independent review target is unavailable or superseded.');
    }
    foreach(wf_tasks($task['research_id']) as $target)if($target['visibility']==='public'&&$target['state']==='Completed'&&in_array($target['kind'],['research','synthesis'],true)){
        $result=one("SELECT * FROM workflow_submissions WHERE id=? AND research_id=? AND visibility='public' AND admission='accepted'",[$target['accepted_id'],$task['research_id']]);
        if($result&&ra_current($result))$out[$result['id']]=['task'=>$target,'result'=>$result];
    }
    return $out;
}
function wf_evidence_review_targets(array $task): array {
    if(!in_array($task['kind'],['review','contribution_review'],true))return [];$out=[];
    foreach(wf_evidence_results($task) as $target){
        $evidence=json_decode($target['result']['metadata'],true)['evidence']??null;
        // Historical results receive an explicit whole-result audit, never invented claims.
        $claims=$evidence['claims']??[['id'=>'legacy','statement'=>'Historical result without structured claims','support'=>'unknown','links'=>[]]];
        foreach($claims as $claim){$key=$target['task']['id'].'/'.$target['result']['id'].'/'.$claim['id'];$out[$key]=$target+['claim'=>$claim,'sources'=>array_column($evidence['sources']??[],null,'id')];}
    }
    return $out;
}
function wf_evidence_template(array $task): array {
    $reviews=[];foreach(wf_evidence_review_targets($task) as $target){
        $checks=[];foreach(array_unique(array_column($target['claim']['links'],'source_id')) as $id)$checks[]=['source_id'=>$id,'access'=>'','locator'=>'','excerpt'=>'','assessment'=>''];
        $reviews[]=['task_id'=>$target['task']['id'],'result_id'=>$target['result']['id'],'claim_id'=>$target['claim']['id'],'verdict'=>'','reason'=>'','source_checks'=>$checks];
    }
    return ['version'=>1,'scope'=>'','stopping_rule'=>'','search_log'=>[['query'=>'','where'=>'','result'=>'']],
        'sources'=>[['id'=>'s1','authors'=>'','title'=>'','publication_date'=>'','publisher'=>'','doi'=>'','url'=>'','edition'=>'','metadata_note'=>'','type'=>'','access'=>'','access_note'=>'','use'=>'']],
        'claims'=>[['id'=>'c1','statement'=>'','kind'=>'','support'=>'','confidence'=>'','confidence_reason'=>'','derivation'=>'','limitations'=>'','counterevidence'=>'','basis_result_ids'=>[],
            'links'=>[['source_id'=>'s1','relation'=>'','locator'=>'','excerpt'=>'','explanation'=>'']]]],
        'supporting_materials'=>rs_material_template(),'material_reviews'=>rs_review_template($task),'review_checks'=>$reviews,'datasets'=>[],'artifacts'=>[],'artifact_reviews'=>ra_review_template($task)]+rc_template($task)+rt_template($task);
}
function wf_evidence_instructions(string $kind): string {
    $stage=match($kind){
        'planning'=>'PLANNING: Inspect the brief and prior evidence before choosing tasks. Define evidence needs and a stopping_rule for every planned task. Include tests of competing explanations and null findings. Claims may be empty when the plan asserts no research conclusions; sources may be empty with an honest access/search record.',
        'synthesis'=>'SYNTHESIS: Reconcile disagreements, null findings and study conditions. Reinspect the original sources for central conclusions. Each supported or limited finding/inference needs basis_result_ids pointing to current accepted research results. Explain inferential steps and avoid generalizing beyond the studied population, endpoint or setting.',
        'review'=>'REVIEW: Independently open the underlying sources. Complete every review_checks template item before verified, inspecting every linked source, including contradictory evidence. Report supported, limited, unsupported or unverifiable. Unsupported/unverifiable claims, or a supported claim downgraded to limited, require needs_correction; the server generates claim-specific corrections and reopens the responsible task and dependents. Historical legacy items require a whole-result source audit using your own source registry. Documented uncertainty can be assessed limited without forcing a positive finding.',
        default=>'RESEARCH: Search and inspect sources before drawing conclusions. Extract methods, population, conditions, endpoints and numerical units. Seek credible contrary findings and alternative explanations. A well-documented null or unresolved result is useful research.'
    };
    return file_get_contents(ROOT.'/templates/evidence.txt')."\n".$stage."\n".file_get_contents(ROOT.'/templates/artifacts.txt');
}
function wf_validate_evidence(array $task,array $packet,array $row,bool $historical=false): array {
    $data=$packet['evidence']??null;
    if(!is_array($data)||($data['version']??null)!==1)fail(422,'Structured evidence version 1 is required. Return the platform repair prompt to the AI; do not edit its packet.');
    $terminal=in_array($row['outcome'],['completed','inconclusive','verified'],true);
    $e=['version'=>1,'scope'=>text_field($data,'scope',$terminal?20:0,3000),'stopping_rule'=>text_field($data,'stopping_rule',$terminal?20:0,3000),'search_log'=>[],'sources'=>[],'claims'=>[],'review_checks'=>[]];
    foreach(wf_evidence_list($data,'search_log',30) as $entry){$item=[];foreach(['query','where','result'] as $key)$item[$key]=text_field($entry,$key,5,1500);$e['search_log'][]=$item;}
    if($terminal&&!$e['search_log'])fail(422,'A terminal result needs an actual search/access record, including failed searches or unavailable tools.');
    $sources=[];
    foreach(wf_evidence_list($data,'sources',40) as $source){
        $id=wf_evidence_id($source);if(isset($sources[$id]))fail(422,'Duplicate evidence source ID: '.$id);
        $item=['id'=>$id,'title'=>text_field($source,'title',5,500),'url'=>safe_url(text_field($source,'url',0,2000)),
            'type'=>wf_evidence_choice($source,'type',['primary','dataset','review','official','provided','other']),
            'access'=>wf_evidence_choice($source,'access',['full_text','abstract_only','unavailable']),'access_note'=>text_field($source,'access_note',10,1500)];
        if($item['url']===''&&$item['type']!=='provided')fail(422,'External evidence sources need a URL. Provided material needs an explicit location in access_note.');
        if(!$historical||isset($source['use']))$item+=rs_attribution($source);
        if(($item['use']??'')==='used'&&$item['access']==='unavailable')fail(422,'Unavailable sources cannot be classified as inspected and used.');
        $sources[$id]=$item;$e['sources'][]=$item;
    }
    $basis=wf_evidence_results($task);$claimIds=[];
    foreach(wf_evidence_list($data,'claims',20) as $claim){
        $id=wf_evidence_id($claim);if($id==='legacy')fail(422,'Claim ID legacy is reserved for historical whole-result audits.');if(isset($claimIds[$id]))fail(422,'Duplicate evidence claim ID: '.$id);$claimIds[$id]=true;
        $item=['id'=>$id,'statement'=>text_field($claim,'statement',20,2000),'kind'=>wf_evidence_choice($claim,'kind',['finding','inference','proposal']),
            'support'=>wf_evidence_choice($claim,'support',['supported','limited','unresolved','unsupported']),
            'confidence'=>wf_evidence_choice($claim,'confidence',['low','moderate','high']),
            'confidence_reason'=>text_field($claim,'confidence_reason',20,2000),'limitations'=>text_field($claim,'limitations',20,3000),
            'counterevidence'=>text_field($claim,'counterevidence',20,3000),'links'=>[]];
        if(!str_contains($row['body'],$item['statement']))fail(422,'Claim '.$id.' must appear verbatim in the full result body so readers can trace it.');
        $item['derivation']=text_field($claim,'derivation',$item['kind']==='finding'?0:20,3000);
        $refs=$claim['basis_result_ids']??null;
        if(!is_array($refs)||!array_is_list($refs)||count($refs)>20)fail(422,'Claim basis_result_ids must be a bounded list of current accepted result IDs.');
        $item['basis_result_ids']=[];foreach($refs as $ref){if(!is_string($ref)||!isset($basis[$ref])||in_array($ref,$item['basis_result_ids'],true))fail(422,'Unknown, stale, foreign or duplicate claim basis result.');$item['basis_result_ids'][]=$ref;}
        if($terminal&&$task['kind']==='synthesis'&&$item['kind']!=='proposal'&&in_array($item['support'],['supported','limited'],true)&&!$refs)fail(422,'Synthesis claim '.$id.' needs a current accepted research result in basis_result_ids.');
        $supports=[];$linkKeys=[];
        foreach(wf_evidence_list($claim,'links',12) as $link){
            $sourceId=text_field($link,'source_id',1,40);if(!isset($sources[$sourceId]))fail(422,'Claim links reference an unknown evidence source.');
            $relation=wf_evidence_choice($link,'relation',['supports','contradicts','context']);$key=$sourceId.'/'.$relation;
            if(isset($linkKeys[$key]))fail(422,'Duplicate claim/source relationship.');$linkKeys[$key]=true;
            $source=$sources[$sourceId];if((!$historical||isset($source['use']))&&($source['use']??'')!=='used'&&$relation!=='context')fail(422,'Background or rejected sources cannot support or contradict an active claim.');$excerpt=text_field($link,'excerpt',$source['access']==='unavailable'?0:10,1500);
            if($source['access']==='unavailable'&&($excerpt!==''||$relation==='supports'))fail(422,'An unavailable source cannot supply a quotation or support a claim. Record it as context and disclose the gap.');
            $item['links'][]=['source_id'=>$sourceId,'relation'=>$relation,'locator'=>text_field($link,'locator',3,500),'excerpt'=>$excerpt,'explanation'=>text_field($link,'explanation',20,2000)];
            if($relation==='supports')$supports[]=$source['access'];
        }
        if(in_array($item['support'],['supported','limited'],true)&&!$supports)fail(422,'Claim '.$id.' needs inspected supporting evidence or an unresolved classification.');
        if($item['support']==='supported'&&!in_array('full_text',$supports,true))fail(422,'Abstract-only evidence supports a limited assessment; inspect the full source before classifying claim '.$id.' as supported.');
        if(($item['support']!=='supported'&&$item['confidence']==='high')||($item['support']==='unresolved'&&$item['confidence']!=='low'))fail(422,'Claim confidence exceeds its declared evidence support.');
        if($terminal&&$item['support']==='unsupported')fail(422,'Unsupported claim '.$id.' cannot complete a task. Investigate it, revise the conclusion, or use progress; disclose unresolved evidence honestly.');
        $e['claims'][]=$item;
    }
    if($terminal&&$task['kind']!=='planning'&&!$e['claims'])fail(422,'Research, synthesis and review terminal results need structured central claims, including documented uncertainty.');
    $targets=wf_evidence_review_targets($task);$seen=[];$corrections=[];
    foreach(wf_evidence_list($data,'review_checks',300) as $review){
        $key=text_field($review,'task_id',32,32).'/'.text_field($review,'result_id',32,32).'/'.text_field($review,'claim_id',1,40);
        if(!isset($targets[$key])||isset($seen[$key]))fail(422,'Evidence review references an unknown, stale, foreign or duplicate claim/result.');$seen[$key]=true;$target=$targets[$key];
        $item=['task_id'=>$review['task_id'],'result_id'=>$review['result_id'],'claim_id'=>$review['claim_id'],
            'verdict'=>wf_evidence_choice($review,'verdict',['supported','limited','unsupported','unverifiable']),'reason'=>text_field($review,'reason',20,3000),'source_checks'=>[]];
        $required=array_unique(array_column($target['claim']['links'],'source_id'));
        $supporting=$review['claim_id']==='legacy'?array_keys($sources):array_column(array_filter($target['claim']['links'],fn($link)=>$link['relation']==='supports'),'source_id');
        $checked=[];$accessible=false;$fullText=false;
        foreach(wf_evidence_list($review,'source_checks',40) as $check){
            $sid=text_field($check,'source_id',1,40);$known=$review['claim_id']==='legacy'?$sources:$target['sources'];
            if(!isset($known[$sid])||isset($checked[$sid])||($review['claim_id']!=='legacy'&&!in_array($sid,$required,true)))fail(422,'Source audit must reference each cited source once within its target claim.');$checked[$sid]=true;
            $access=wf_evidence_choice($check,'access',['full_text','abstract_only','unavailable']);
            $excerpt=text_field($check,'excerpt',$access==='unavailable'?0:10,1500);if($access==='unavailable'&&$excerpt!=='')fail(422,'An unavailable review source cannot have an inspected quotation.');
            if(in_array($sid,$supporting,true)){if($access!=='unavailable')$accessible=true;if($access==='full_text')$fullText=true;}
            $item['source_checks'][]=['source_id'=>$sid,'access'=>$access,'locator'=>text_field($check,'locator',3,500),'excerpt'=>$excerpt,'assessment'=>text_field($check,'assessment',20,3000)];
        }
        if($row['outcome']==='verified'&&array_diff($required,array_keys($checked)))fail(422,'Verified review must inspect every linked source, including contradictory evidence.');
        if($item['verdict']==='supported'&&!$accessible)fail(422,'A supported review assessment needs an inspected source passage. Use limited for documented uncertainty or unverifiable when the claim cannot be checked.');
        if($item['verdict']==='supported'&&!$fullText)fail(422,'Abstract-only source audits allow limited support. Inspect the full source or return the overstated conclusion for correction.');
        $needs=in_array($item['verdict'],['unsupported','unverifiable'],true)||($item['verdict']==='limited'&&in_array($target['claim']['support'],['supported','unknown'],true));
        if($needs){
            if($row['outcome']!=='needs_correction')fail(422,'Unsupported, unverifiable or overstated conclusions require outcome needs_correction. Their task and dependent results must be corrected before verification.');
            $corrections[]=['task_id'=>$item['task_id'],'result_id'=>$item['result_id'],'criterion_id'=>'evidence-standard','claim_id'=>$item['claim_id'],'reason'=>substr($item['reason'],0,1000),
                'required_change'=>'Reassess claim '.$item['claim_id'].' ('.$item['verdict'].'): '.substr($item['reason'],0,2000).' Inspect the underlying evidence, revise the conclusion and propagate changes to dependent work.'];
        }
        $e['review_checks'][]=$item;
    }
    if(!in_array($task['kind'],['review','contribution_review'],true)&&$e['review_checks'])fail(422,'Only review tasks can audit other results.');
    if($row['outcome']==='verified'&&count($seen)!==count($targets))fail(422,'Verified review must assess every current research and synthesis claim. Historical results need an explicit legacy audit.');
    $visuals=ra_validate($task,$data,$e,$row,$historical);
    $calculations=rc_validate($task,$data,$e,$row,$historical);
    $tools=rt_validate($task,$data,$e,$row,$historical);
    $complete=$e+$visuals['fields']+$calculations['fields']+$tools['fields'];
    $materials=$historical?['fields'=>[],'corrections'=>[]]:rs_validate($task,$data,$complete,$row);
    return ['evidence'=>$complete+$materials['fields'],'corrections'=>array_merge($corrections,$visuals['corrections'],$calculations['corrections'],$tools['corrections'],$materials['corrections'])];
}
function wf_correction_label(?array $task,string $criterion): string {
    if($criterion==='supporting-materials')return 'Supporting material completeness and reproducibility';
    if($criterion==='tool-standard')return 'Research tool: reproducible tests and scientific assumptions';
    if($criterion==='calculation-standard')return 'Calculation reproducibility, output values and unit consistency';
    if($criterion==='artifact-standard')return 'Visual artifact: data fidelity, evidence links, assumptions and limitations';
    if($criterion==='evidence-standard')return 'Research evidence: source support, uncertainty and scope of conclusions';
    return $task?(wf_criteria($task)[$criterion]??'Historical criterion'):'Criterion text is no longer publicly available';
}
