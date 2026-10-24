<?php
/** Non-executed model reasoning. Formula/steps are inert text, never evaluated. */
function rd_policy(): array {
    return ['version'=>1,'type'=>'model_derived','executed'=>false,'computationally_reproduced'=>false,'stages'=>['research','synthesis'],'satisfies_execution_requirement'=>false];
}
function rd_keys(array $data,array $keys,string $label): void {
    if(array_diff(array_keys($data),$keys))fail(422,'Unsupported '.$label.' field. Model-derived arithmetic cannot claim execution, files or software.');
    if(array_diff($keys,array_keys($data)))fail(422,'Missing required '.$label.' fields.');
}
function rd_text(array $data,string $key,int $min,int $max): string {
    if(!isset($data[$key])||!is_string($data[$key]))fail(422,'Model-derived '.$key.' must be plain text.');
    return text_field($data,$key,$min,$max);
}
function rd_list(array $data,string $key,int $max): array {
    return wf_evidence_list([$key=>array_key_exists($key,$data)?$data[$key]:[]],$key,$max);
}
function rd_steps(mixed $raw,bool $required): array {
    if(!is_array($raw)||!array_is_list($raw)||count($raw)>20||($required&&!$raw))fail(422,'Retain 1–20 actual worked model steps; never claim executed code.');
    $out=[];foreach($raw as $step)$out[]=rd_text(['step'=>$step],'step',10,2000);return $out;
}
function rd_quantities(array $data,string $key,array $sources=[]): array {
    $out=[];
    foreach(rd_list($data,$key,30) as $q){
        rd_keys($q,$key==='inputs'?['id','value','unit','source_id','source_locator','extraction']:['id','value','unit'],'derivation '.$key);
        $id=wf_evidence_id($q);if(isset($out[$id]))fail(422,'Duplicate derivation quantity ID.');
        $clean=['id'=>$id,'value'=>ra_number($q['value']??null,'Model-derived quantity'),'unit'=>rd_text($q,'unit',1,80)];
        if($key==='inputs'){
            $sid=rd_text($q,'source_id',1,40);
            if(!isset($sources[$sid])||($sources[$sid]['use']??'')!=='used'||($sources[$sid]['access']??'unavailable')==='unavailable')fail(422,'Derivation inputs need an inspected-and-used accessible source and exact extraction.');
            $clean+=['source_id'=>$sid,'source_locator'=>rd_text($q,'source_locator',3,500),'extraction'=>rd_text($q,'extraction',10,2000)];
        }
        $out[$id]=$clean;
    }
    if(!$out)fail(422,'A model-derived record needs cited inputs and explicit output quantities.');
    return array_values($out);
}
function rd_targets(array $task): array {
    $out=[];if($task['kind']!=='review')return $out;
    foreach(wf_evidence_results($task) as $target)foreach(json_decode($target['result']['metadata'],true)['evidence']['derivations']??[] as $d){
        if(($d['version']??null)!==1||($d['type']??null)!=='model_derived')fail(422,'Current derivation has an unsupported version/type; assess it without claiming verification.');
        $out[$target['task']['id'].'/'.$target['result']['id'].'/'.$d['id']]=$target+['derivation'=>$d];
    }
    return $out;
}
function rd_template(array $task): array {
    $reviews=[];
    foreach(rd_targets($task) as $target)$reviews[]=['version'=>1,'task_id'=>$target['task']['id'],'result_id'=>$target['result']['id'],'derivation_id'=>$target['derivation']['id'],'verdict'=>'','method'=>'','worked_steps'=>[],'observed_outputs'=>[],'limitations'=>''];
    return ['derivations'=>[],'derivation_reviews'=>$reviews];
}
function rd_validate(array $task,array $data,array $evidence,array $row,bool $historical=false): array {
    if($historical&&!array_key_exists('derivations',$data)&&!array_key_exists('derivation_reviews',$data))return ['fields'=>[],'corrections'=>[]];
    $records=[];$claims=array_column($evidence['claims']??[],null,'id');$sources=array_column($evidence['sources']??[],null,'id');
    foreach(rd_list($data,'derivations',4) as $d){
        if(!in_array($task['kind'],['research','synthesis'],true))fail(422,'Model-derived arithmetic accompanies research or synthesis; reviews use derivation_reviews.');
        rd_keys($d,['version','type','id','title','claim_ids','source_ids','inputs','formula','steps','outputs','assumptions','limitations'],'model-derived arithmetic');
        if($d['version']!==1||$d['type']!=='model_derived')fail(422,'Derivations require exact version 1 and type model_derived; they are never executed or computationally reproduced.');
        $id=wf_evidence_id($d);if(isset($records[$id]))fail(422,'Duplicate derivation ID.');
        $sourceIds=ra_refs($d['source_ids'],$sources,1,40,'derivation source');
        $used=array_intersect_key($sources,array_flip($sourceIds));
        foreach($used as $source)if(($source['use']??'')!=='used'||($source['access']??'unavailable')==='unavailable')fail(422,'Derivations must cite inspected-and-used accessible sources.');
        $records[$id]=['version'=>1,'type'=>'model_derived','id'=>$id,'title'=>rd_text($d,'title',5,150),
            'claim_ids'=>ra_refs($d['claim_ids'],$claims,1,20,'derivation claim'),'source_ids'=>$sourceIds,
            'inputs'=>rd_quantities($d,'inputs',$used),'formula'=>rd_text($d,'formula',3,3000),'steps'=>rd_steps($d['steps'],true),
            'outputs'=>rd_quantities($d,'outputs'),'assumptions'=>rd_text($d,'assumptions',20,3000),'limitations'=>rd_text($d,'limitations',20,3000)];
    }
    $targets=rd_targets($task);$reviews=[];$seen=[];$corrections=[];
    foreach(rd_list($data,'derivation_reviews',100) as $review){
        rd_keys($review,['version','task_id','result_id','derivation_id','verdict','method','worked_steps','observed_outputs','limitations'],'derivation review');
        if($review['version']!==1)fail(422,'Derivation reviews require integer version 1.');
        $tid=rd_text($review,'task_id',32,32);$rid=rd_text($review,'result_id',32,32);$did=rd_text($review,'derivation_id',1,40);$key=$tid.'/'.$rid.'/'.$did;
        if(!isset($targets[$key])||isset($seen[$key]))fail(422,'Derivation assessment must identify a unique current accepted research/synthesis derivation.');
        $verdict=wf_evidence_choice($review,'verdict',['assessed','needs_correction','unverifiable']);
        $clean=['version'=>1,'task_id'=>$tid,'result_id'=>$rid,'derivation_id'=>$did,'verdict'=>$verdict,
            'method'=>rd_text($review,'method',20,3000),'worked_steps'=>rd_steps($review['worked_steps'],$verdict==='assessed'),'observed_outputs'=>[],
            'limitations'=>rd_text($review,'limitations',20,3000)];
        $expected=array_column($targets[$key]['derivation']['outputs'],null,'id');$observed=[];
        foreach(rd_list($review,'observed_outputs',30) as $q){
            rd_keys($q,['id','value','unit'],'assessed output');$id=wf_evidence_id($q);
            if(!isset($expected[$id])||isset($observed[$id]))fail(422,'Assessed output IDs must refer once to the retained derivation.');
            $value=ra_number($q['value']??null,'Assessed model-derived value');$unit=rd_text($q,'unit',1,80);
            if($verdict==='assessed'&&($unit!==$expected[$id]['unit']||!rc_close($value,$expected[$id]['value'])))fail(422,'Derivation assessment disagrees with a reported value/unit; return needs_correction, not computational reproduction.');
            $observed[$id]=['id'=>$id,'value'=>$value,'unit'=>$unit];
        }
        if($verdict==='assessed'&&count($observed)!==count($expected))fail(422,'Assess every retained derivation output and unit.');
        $clean['observed_outputs']=array_values($observed);$reviews[]=$clean;$seen[$key]=true;
        if($verdict!=='assessed'){
            if($row['outcome']!=='needs_correction')fail(422,'Incorrect or unverifiable derivations require needs_correction.');
            $corrections[]=['task_id'=>$tid,'result_id'=>$rid,'criterion_id'=>'derivation-standard','derivation_id'=>$did,'reason'=>substr($clean['method'],0,1000),
                'required_change'=>'Correct model-derived arithmetic '.$did.': '.substr($clean['method'],0,2000).' Retain cited input extraction, units, formula, worked steps, outputs, assumptions and limitations; never invent execution.'];
        }
    }
    if($row['outcome']==='verified'&&count($seen)!==count($targets))fail(422,'Verified review must assess every current model-derived record separately from executed-calculation reproduction.');
    return ['fields'=>['derivations'=>array_values($records),'derivation_reviews'=>$reviews],'corrections'=>$corrections];
}
function rd_route(array $r,string $suffix,string $method): bool {
    if(!preg_match('~^results/([a-f0-9]{32})/derivations/([a-z][a-z0-9_-]{0,39})/bundle\.json$~D',$suffix,$m))return false;
    if($method!=='GET')fail(405,'Model-derived bundles are read-only.');
    $result=rc_read($r['id'],$m[1]);$evidence=json_decode($result['metadata'],true)['evidence'];
    foreach($evidence['derivations']??[] as $d)if($d['id']===$m[2]){
        if(($d['version']??null)!==1||($d['type']??null)!=='model_derived')fail(409,'Stored derivation version/type is unsupported.');
        header("Content-Security-Policy: sandbox; default-src 'none'");header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$m[2].'-derivation.json"');header('Cache-Control: no-store');
        echo json_encode(['format'=>'llm-giving-derivation-v1','research_id'=>$r['id'],'task_id'=>$result['task_id'],'result_id'=>$result['id'],'license'=>$result['license']??null,
            'display_name'=>$result['display_name'],'context_id'=>$result['snapshot_id'],'derivation'=>$d,'sources'=>$evidence['sources'],'claims'=>$evidence['claims'],
            'notice'=>'Model-derived arithmetic. Not executed or computationally reproduced. Formula and worked steps are inert text; structure and matching reported quantities do not establish scientific correctness.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG);return true;
    }
    fail(404,'Model-derived arithmetic not found.');
}
