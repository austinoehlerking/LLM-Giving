<?php
// Calculation records are inert text/data. The web application never executes submitted code.
function rc_instructions(array $task): string {
    $r=research($task['research_id']);$policy=calculation_policy($r);$progress=$policy['allowed']?rc_progress($task):null;
    return file_get_contents(ROOT.'/templates/calculations.txt')."\nCalculation preference: ".EXTERNAL_HELP[$policy['mode']].". Calculations permitted: ".($policy['allowed']?'yes':'no')."; execution required by the request: ".($policy['required']?'yes':'no').". This task computational flag: ".(!empty($task['computational'])?'true':'false').($progress?"\nRESUME ACCEPTED CHECKPOINT: ".$progress['id'].". Read its complete retained record in the context before continuing; set resume_from_result_id to this result.":'');
}
function rc_close(float $a,float $b): bool {return abs($a-$b)<=1e-9+1e-6*max(abs($a),abs($b));}
function rc_factor(string $from,string $to): ?float {
    if($from===$to)return 1.0;
    if(preg_match('~^(mg|g|kg)( .+)?$~D',$from,$a)&&preg_match('~^(mg|g|kg)( .+)?$~D',$to,$b)&&($a[2]??'')===($b[2]??''))return ['mg'=>0.000001,'g'=>0.001,'kg'=>1][$a[1]]/['mg'=>0.000001,'g'=>0.001,'kg'=>1][$b[1]];
    return match($from.' -> '.$to){'kg CH4-C/ha/year -> kg CH4/ha/year'=>16/12,'kg N2O-N/ha/year -> kg N2O/ha/year'=>44/28,default=>null};
}
function rc_progress(array $task): ?array {
    research($task['research_id']);
    return one("SELECT * FROM workflow_submissions WHERE research_id=? AND task_id=? AND visibility='public' AND admission='accepted' AND outcome='progress' AND JSON_EXTRACT(metadata,'$.evidence.calculation_checkpoint') IS NOT NULL AND JSON_TYPE(JSON_EXTRACT(metadata,'$.evidence.calculation_checkpoint'))='OBJECT' AND JSON_EXTRACT(metadata,'$.task_revision')=? ORDER BY (SELECT MAX(a.id) FROM audit_events a WHERE a.entity_type='submission' AND a.entity_id=workflow_submissions.id) DESC,created_at DESC,id DESC LIMIT 1",[$task['research_id'],$task['id'],(int)$task['revision']]);
}
function rc_targets(array $task): array {
    $out=[];if($task['kind']!=='review')return $out;
    foreach(wf_evidence_results($task) as $target)foreach(json_decode($target['result']['metadata'],true)['evidence']['calculations']??[] as $calculation)$out[$target['task']['id'].'/'.$target['result']['id'].'/'.$calculation['id']]=$target+['calculation'=>$calculation];
    return $out;
}
function rc_template(array $task): array {
    $reviews=[];foreach(rc_targets($task) as $target)$reviews[]=['task_id'=>$target['task']['id'],'result_id'=>$target['result']['id'],'calculation_id'=>$target['calculation']['id'],'verdict'=>'','method'=>'','environment'=>'','run_log'=>'','observed_outputs'=>[]];
    $calculation=['id'=>'calculation','title'=>'','status'=>'','claim_ids'=>[],'source_ids'=>[],'method'=>'','environment'=>'','command'=>'','execution_note'=>'','limitations'=>'',
        'software'=>[['name'=>'','version'=>'']],'files'=>[['id'=>'input','name'=>'inputs.json','role'=>'input','content'=>'','sha256'=>''],['id'=>'code','name'=>'model.txt','role'=>'code','content'=>'','sha256'=>'']],
        'inputs'=>[['id'=>'input_quantity','value'=>null,'unit'=>'','file_id'=>'input','locator'=>'','source_id'=>'s1','source_locator'=>'','extraction'=>'','provenance'=>'','derivation'=>'']],
        'outputs'=>[],'unit_checks'=>[]];
    return ['calculations'=>!empty($task['computational'])?[$calculation]:[],'calculation_checkpoint'=>!empty($task['computational'])?['resume_from_result_id'=>'','completed'=>'','remaining'=>'','next_action'=>'','required_tools'=>'']:null,'calculation_reviews'=>$reviews];
}
function rc_quantities(array $data,string $key,array $files,array $sources=[],bool $historical=false): array {
    $out=[];foreach(ra_list($data,$key,30) as $quantity){
        ra_keys($quantity,['id','value','unit','file_id','locator','source_id','source_locator','extraction','provenance','derivation'],'calculation quantity');$id=wf_evidence_id($quantity);if(isset($out[$id]))fail(422,'Duplicate calculation quantity ID.');
        $file=text_field($quantity,'file_id',1,40);if(!isset($files[$file])||$files[$file]['role']!==($key==='inputs'?'input':'output'))fail(422,'Calculation quantities must reference a retained file of the matching input/output role.');
        $out[$id]=['id'=>$id,'value'=>ra_number($quantity['value']??null,'Calculation quantity'),'unit'=>text_field($quantity,'unit',1,80),'file_id'=>$file,'locator'=>text_field($quantity,'locator',3,200)];
        if($key==='inputs'&&!$historical){
            $sid=text_field($quantity,'source_id',1,40);
            if(!isset($sources[$sid])||$sources[$sid]['access']==='unavailable'||($sources[$sid]['use']??'')!=='used')fail(422,'Calculation inputs need an inspected-and-used accessible source with a precise extraction location.');
            $provenance=wf_evidence_choice($quantity,'provenance',['original','derived']);
            $out[$id]+=['source_id'=>$sid,'source_locator'=>text_field($quantity,'source_locator',3,500),'extraction'=>text_field($quantity,'extraction',10,2000),'provenance'=>$provenance,'derivation'=>text_field($quantity,'derivation',$provenance==='derived'?20:0,3000)];
        }
    }return $out;
}
function rc_validate(array $task,array $data,array $evidence,array $row,bool $historical=false): array {
    $calculations=[];$bytes=0;$terminal=in_array($row['outcome'],['completed','inconclusive','verified'],true);$required=!empty($task['computational']);
    foreach(ra_list($data,'calculations',4) as $c){
        if($task['kind']!=='research'||!calculation_policy(research($task['research_id']))['allowed'])fail(422,'This request does not permit computational research contributions.');
        ra_keys($c,['id','title','status','claim_ids','source_ids','method','environment','command','execution_note','limitations','files','inputs','outputs','unit_checks','software'],'calculation');
        $id=wf_evidence_id($c);if(isset($calculations[$id]))fail(422,'Duplicate calculation ID.');
        $clean=['id'=>$id,'title'=>text_field($c,'title',5,150),'status'=>wf_evidence_choice($c,'status',['proposed','executed','failed']),
            'claim_ids'=>ra_refs($c['claim_ids']??null,array_column($evidence['claims'],null,'id'),$terminal?1:0,20,'calculation claim'),
            'source_ids'=>ra_refs($c['source_ids']??null,array_column($evidence['sources'],null,'id'),($terminal||($c['status']??'')==='executed')?1:0,40,'calculation source')];
        foreach(['method','environment','command','execution_note','limitations'] as $key)$clean[$key]=text_field($c,$key,$key==='command'?3:20,3000);
        if(!$historical)$clean['software']=rs_software($c,$terminal);
        $files=[];$roles=[];$names=[];foreach(ra_list($c,'files',8) as $file){
            ra_keys($file,['id','name','role','content','sha256'],'calculation file');$fid=wf_evidence_id($file);if(isset($files[$fid]))fail(422,'Duplicate calculation file ID.');
            $name=text_field($file,'name',1,80);if(!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,79}$/D',$name)||str_contains($name,'..')||str_ends_with($name,'.')||preg_match('/^(con|prn|aux|nul|com[0-9]|lpt[0-9])(\.|$)/i',$name))fail(422,'Calculation filenames must be plain filenames without directories.');
            if(isset($names[strtolower($name)]))fail(422,'Calculation filenames must be unique, including case.');$names[strtolower($name)]=true;
            $role=wf_evidence_choice($file,'role',['input','code','output','environment','log']);
            $content=$file['content']??null;if(!is_string($content)||strlen($content)<1||strlen($content)>30000||str_contains($content,"\0"))fail(422,'Calculation files need 1–30,000 bytes of UTF-8 text.');
            $bytes+=strlen($content);if($bytes>100000)fail(422,'Calculation files exceed the 100,000-byte packet budget.');
            $hash=text_field($file,'sha256',64,64);if(!hash_equals(hash('sha256',$content),$hash))fail(422,'Calculation file checksum mismatch: '.$name.'. Preserve exact UTF-8 bytes.');
            $files[$fid]=['id'=>$fid,'name'=>$name,'role'=>$role,'content'=>$content,'sha256'=>$hash];$roles[$role]=true;
        }
        if($clean['status']==='executed'&&!isset($roles['input'],$roles['code']))fail(422,'Executed calculations need retained input and code files. Preserve unfinished preparation in progress without inventing missing files.');
        $inputs=rc_quantities($c,'inputs',$files,array_intersect_key(array_column($evidence['sources'],null,'id'),array_flip($clean['source_ids'])),$historical);$outputs=rc_quantities($c,'outputs',$files);$checks=[];
        foreach(ra_list($c,'unit_checks',30) as $check){
            ra_keys($check,['input_id','output_id','mode','explanation','assessment'],'unit check');$input=text_field($check,'input_id',1,40);$output=text_field($check,'output_id',1,40);$key=$input.'/'.$output;
            if(!isset($inputs[$input],$outputs[$output])||isset($checks[$key]))fail(422,'Unit checks need unique retained input/output references.');
            $mode=wf_evidence_choice($check,'mode',['automatic','review_required']);$factor=rc_factor($inputs[$input]['unit'],$outputs[$output]['unit']);
            if($mode==='automatic'&&($factor===null||!rc_close($inputs[$input]['value']*$factor,$outputs[$output]['value'])))fail(422,'Unit error in '.$id.': '.$input.' -> '.$output.'. Check mass scale, elemental-to-gas conversion and matching area/time boundaries.');
            $checks[$key]=['input_id'=>$input,'output_id'=>$output,'mode'=>$mode,'explanation'=>text_field($check,'explanation',20,2000),'assessment'=>$mode==='automatic'?'consistent':'independent review required'];
        }
        if($clean['status']==='failed'&&!isset($roles['log']))fail(422,'Failed calculations need a retained failure log so others can continue the work.');
        if($clean['status']==='executed'&&(!isset($roles['output'])||!$outputs||!$checks||!$inputs))fail(422,'Executed calculations need retained outputs, quantities and explicit unit checks.');
        if($clean['status']!=='executed'&&($outputs||$checks||isset($roles['output'])))fail(422,'Unexecuted or failed work cannot present successful outputs. Retain failures as log files.');
        if($terminal&&$clean['status']!=='executed')fail(422,'Unexecuted work cannot complete a computational task. Use progress, blocked or clarification.');
        $clean+=['files'=>array_values($files),'inputs'=>array_values($inputs),'outputs'=>array_values($outputs),'unit_checks'=>array_values($checks)];$calculations[$id]=$clean;
    }
    $usesCalculations=$required||!empty($calculations);
    if($required&&in_array($row['outcome'],['progress','completed','inconclusive'],true)&&!$calculations)fail(422,'Computational tasks need a calculation record and a resumable checkpoint.');
    $checkpoint=null;$raw=$data['calculation_checkpoint']??null;
    if($raw!==null){
        if(!$usesCalculations||!is_array($raw)||array_is_list($raw))fail(422,'Calculation checkpoints require a calculation record or a computational task.');
        ra_keys($raw,['resume_from_result_id','completed','remaining','next_action','required_tools'],'calculation checkpoint');
        $checkpoint=['resume_from_result_id'=>text_field($raw,'resume_from_result_id',0,32)];foreach(['completed','remaining','next_action','required_tools'] as $key)$checkpoint[$key]=text_field($raw,$key,20,3000);
        $snapshot=json_decode(wf_read_snapshot($task['research_id'],$row['snapshot_id'])['package_json'],true);$progress=[];
        foreach($snapshot['workflow']['submissions'] as $previous)if($previous['task_id']===$task['id']&&$previous['outcome']==='progress'&&!empty(json_decode($previous['metadata'],true)['evidence']['calculation_checkpoint'])&&(int)(json_decode($previous['metadata'],true)['task_revision']??0)===(int)$task['revision'])$progress[$previous['id']]=$previous;
        $ref=$checkpoint['resume_from_result_id'];if(($ref!==''&&!isset($progress[$ref]))||($progress&&$ref===''))fail(422,'Resume from an accepted progress result for this exact task revision in the supplied context package.');
    }
    if($usesCalculations&&in_array($row['outcome'],['progress','completed','inconclusive'],true)&&!$checkpoint)fail(422,'Preserve a calculation checkpoint before completing or interrupting work.');
    $targets=rc_targets($task);$reviews=[];$seen=[];$corrections=[];
    foreach(ra_list($data,'calculation_reviews',100) as $review){
        ra_keys($review,['task_id','result_id','calculation_id','verdict','method','environment','run_log','observed_outputs'],'calculation review');
        $tid=text_field($review,'task_id',32,32);$rid=text_field($review,'result_id',32,32);$cid=text_field($review,'calculation_id',1,40);$key=$tid.'/'.$rid.'/'.$cid;
        if(!isset($targets[$key])||isset($seen[$key]))fail(422,'Calculation review references an unknown, stale, foreign or duplicate result.');$seen[$key]=true;
        $verdict=wf_evidence_choice($review,'verdict',['reproduced','needs_correction','unverifiable']);$clean=['task_id'=>$tid,'result_id'=>$rid,'calculation_id'=>$cid,'verdict'=>$verdict];
        foreach(['method','environment','run_log'] as $field)$clean[$field]=text_field($review,$field,20,5000);
        $expected=array_column($targets[$key]['calculation']['outputs'],null,'id');$observed=[];
        foreach(ra_list($review,'observed_outputs',30) as $output){ra_keys($output,['id','value','unit'],'reproduced output');$id=wf_evidence_id($output);if(!isset($expected[$id])||isset($observed[$id]))fail(422,'Reproduction outputs must reference each original quantity once.');$unit=text_field($output,'unit',1,80);$value=ra_number($output['value']??null,'Reproduced value');if($verdict==='reproduced'&&($unit!==$expected[$id]['unit']||!rc_close($value,$expected[$id]['value'])))fail(422,'Reproduction disagrees with the accepted output value or units. Return needs_correction.');$observed[$id]=['id'=>$id,'value'=>$value,'unit'=>$unit];}
        if($verdict==='reproduced'&&count($observed)!==count($expected))fail(422,'Reproduction must report every retained output quantity.');$clean['observed_outputs']=array_values($observed);$reviews[]=$clean;
        if($verdict!=='reproduced'){
            if($row['outcome']!=='needs_correction')fail(422,'Unreproduced or incorrect calculations require needs_correction.');
            $corrections[]=['task_id'=>$tid,'result_id'=>$rid,'criterion_id'=>'calculation-standard','calculation_id'=>$cid,'reason'=>substr($clean['method'],0,1000),'required_change'=>'Correct calculation '.$cid.': '.substr($clean['method'],0,2200).' Retain corrected inputs, code, outputs and unit checks, and propagate changes.'];
        }
    }
    if($row['outcome']==='verified'&&count($seen)!==count($targets))fail(422,'Verified review must independently reproduce every current calculation.');
    return ['fields'=>['calculations'=>array_values($calculations),'calculation_checkpoint'=>$checkpoint,'calculation_reviews'=>$reviews],'corrections'=>$corrections];
}
function rc_read(string $rid,string $resultId): array {
    research($rid);$result=one("SELECT * FROM workflow_submissions WHERE research_id=? AND id=? AND visibility='public' AND admission='accepted'",[$rid,$resultId])??fail(404,'Calculation record unavailable.');
    rs_read($rid,$resultId);
    $task=wf_task($rid,$result['task_id']);$project=wf_project($rid);if($task['visibility']!=='public'||$task['round_no']!=$project['round_no']||(int)(json_decode($result['metadata'],true)['task_revision']??0)!==(int)$task['revision'])fail(404,'This calculation belongs to superseded work.');
    wf_read_snapshot($rid,$result['snapshot_id']);
    foreach(json_decode($result['metadata'],true)['evidence']['calculations']??[] as $calculation)foreach($calculation['files'] as $file)if(!hash_equals($file['sha256'],hash('sha256',$file['content'])))fail(409,'Stored calculation file checksum mismatch.');
    return $result;
}
function rc_route(array $r,string $suffix,string $method): bool {
    if(!preg_match('~^results/([a-f0-9]{32})/calculations/([a-z][a-z0-9_-]{0,39})/bundle\.json$~D',$suffix,$m))return false;
    if($method!=='GET')fail(405,'Calculation bundles are read-only.');$result=rc_read($r['id'],$m[1]);$evidence=json_decode($result['metadata'],true)['evidence'];
    foreach($evidence['calculations']??[] as $calculation)if($calculation['id']===$m[2]){
        header("Content-Security-Policy: sandbox; default-src 'none'");header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="'.$m[2].'-bundle.json"');header('Cache-Control: no-store');
        echo json_encode(['format'=>'llm-giving-calculation-v1','research_id'=>$r['id'],'task_id'=>$result['task_id'],'result_id'=>$result['id'],'license'=>$result['license']??null,'display_name'=>$result['display_name'],'context_id'=>$result['snapshot_id'],'calculation'=>$calculation,'checkpoint'=>$evidence['calculation_checkpoint'],'sources'=>$evidence['sources'],'claims'=>$evidence['claims'],'notice'=>'Inert untrusted text. Inspect code before running locally. Checksums establish byte identity; execution and scientific validity remain contributor declarations.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG);return true;
    }fail(404,'Calculation not found.');
}