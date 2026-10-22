<?php
// Tool files are inert, bounded UTF-8 records. Execution happens only in contributor tools.
const RT_LICENSE='AGPL-3.0-only';
const RT_TERMS='research-tools-v1';
const RT_FIELDS=['intended_users'=>'Intended users and scientific use','requirements'=>'Bounded requirements and exclusions','inputs_outputs'=>'Inputs, outputs and units','validation_plan'=>'Reference cases and test expectations','maintenance'=>'Maintenance plan or experimental status'];
function rt_is_tool(array $r): bool {return ($r['project_type']??'research')==='research_tool';}
function rt_brief(array $r): array {return rt_is_tool($r)?json_decode($r['tool_requirements'],true):[];}
function rt_parse(array $d): array {
    $type=text_field($d,'project_type',0,24)?:'research';
    if(!in_array($type,['research','research_tool'],true))fail(422,'Choose a supported project type.');
    $brief=[];
    if($type==='research_tool')foreach(RT_FIELDS as $key=>$label)$brief[$key]=text_field($d,'tool_'.$key,20,3000);
    return ['project_type'=>$type,'tool_requirements'=>json_encode($brief?:new stdClass())];
}
function rt_instructions(array $task): string {
    if(!rt_is_tool(research($task['research_id'])))return '';
    return file_get_contents(ROOT.'/templates/research-tools.txt')."\nThis task builds_tool=".(!empty($task['builds_tool'])?'true':'false').". Stage: ".$task['kind'].'.';
}
function rt_targets(array $task): array {
    $out=[];if($task['kind']!=='review')return $out;
    foreach(wf_evidence_results($task) as $target)foreach(json_decode($target['result']['metadata'],true)['evidence']['tool_builds']??[] as $build)$out[$target['task']['id'].'/'.$target['result']['id'].'/'.$build['id']]=$target+['build'=>$build];
    return $out;
}
function rt_template(array $task): array {
    $reviews=[];
    foreach(rt_targets($task) as $target){$tests=[];foreach($target['build']['tests'] as $test)$tests[]=['id'=>$test['id'],'status'=>'','observed'=>'','run_log'=>''];
        $reviews[]=['task_id'=>$target['task']['id'],'result_id'=>$target['result']['id'],'tool_id'=>$target['build']['id'],'verdict'=>'','method'=>'','environment'=>'','command'=>'','run_log'=>'','scientific_assessment'=>'','limitations'=>'','test_results'=>$tests];}
    return ['tool_builds'=>!empty($task['builds_tool'])?[[
        'id'=>'tool','title'=>'','version'=>'','status'=>'','license'=>RT_LICENSE,'usage'=>'','environment'=>'','command'=>'','dependency_notes'=>'','scientific_assumptions'=>'','limitations'=>'','maintenance'=>'','source_ids'=>[],
        'software'=>[['name'=>'','version'=>'']],'files'=>[['id'=>'source','name'=>'tool.txt','role'=>'source','content'=>'','sha256'=>''],['id'=>'tests','name'=>'tests.txt','role'=>'test','content'=>'','sha256'=>'']],
        'tests'=>[['id'=>'reference','kind'=>'reference','purpose'=>'','file_id'=>'tests','input'=>'','expected'=>'','observed'=>'','comparison'=>'exact','absolute_tolerance'=>0,'relative_tolerance'=>0,'tolerance_reason'=>'','status'=>'','run_log'=>'']]
    ]]:[],'tool_reviews'=>$reviews];
}
function rt_value(array $data,string $key): string {
    $value=$data[$key]??null;if(!is_string($value)||strlen($value)>3000||str_contains($value,"\0")||!preg_match('//u',$value))fail(422,'Tool test '.$key.' must be at most 3,000 UTF-8 bytes, including exact whitespace.');return $value;
}
function rt_matches(array $test,string $observed): bool {
    if($test['comparison']==='exact')return $test['expected']===$observed;
    if(!is_numeric($observed)||!is_finite((float)$observed))return false;
    $expected=(float)$test['expected'];$actual=(float)$observed;
    return abs($actual-$expected)<=$test['absolute_tolerance']+$test['relative_tolerance']*abs($expected);
}
function rt_validate(array $task,array $data,array $evidence,array $row,bool $historical=false): array {
    $r=research($task['research_id']);$terminal=in_array($row['outcome'],['completed','inconclusive','verified'],true);$required=!empty($task['builds_tool']);$builds=[];$bytes=0;
    foreach(ra_list($data,'tool_builds',2) as $build){
        if(!$required||$task['kind']!=='research'||!rt_is_tool($r))fail(422,'Tool builds belong to a planned build task in a research-tool project.');
        ra_keys($build,['id','title','version','status','license','usage','environment','command','dependency_notes','scientific_assumptions','limitations','maintenance','source_ids','files','tests','software'],'tool build');
        $id=wf_evidence_id($build);if(isset($builds[$id]))fail(422,'Duplicate tool build ID.');
        if(($build['license']??'')!==RT_LICENSE)fail(422,'Original tool source and test files must use the project software license: '.RT_LICENSE.'.');
        $clean=['id'=>$id,'license'=>RT_LICENSE,'title'=>text_field($build,'title',5,150),'version'=>text_field($build,'version',1,80),'status'=>wf_evidence_choice($build,'status',['draft','tested']),
            'source_ids'=>ra_refs($build['source_ids']??null,array_column($evidence['sources'],null,'id'),$terminal?1:0,40,'tool reference source')];
        foreach(['usage','environment','command','dependency_notes','scientific_assumptions','limitations','maintenance'] as $key)$clean[$key]=text_field($build,$key,$key==='command'?3:20,3000);
        if(!$historical)$clean['software']=rs_software($build,$terminal);
        $files=[];$names=[];$roles=[];
        foreach(ra_list($build,'files',16) as $file){
            ra_keys($file,['id','name','role','content','sha256'],'tool file');$fid=wf_evidence_id($file);$name=text_field($file,'name',1,80);
            if(isset($files[$fid])||isset($names[strtolower($name)])||strtolower($name)==='license.txt'||!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,79}$/D',$name)||str_contains($name,'..')||str_ends_with($name,'.')||preg_match('/^(con|prn|aux|nul|com[0-9]|lpt[0-9])(\.|$)/i',$name))fail(422,'Tool files need unique IDs and plain unique filenames; LICENSE.txt is reserved.');
            $role=wf_evidence_choice($file,'role',['source','test','input','output','environment','documentation','log']);$content=$file['content']??null;
            if(!is_string($content)||strlen($content)<1||strlen($content)>30000||str_contains($content,"\0")||!preg_match('//u',$content))fail(422,'Tool files need 1–30,000 bytes of UTF-8 text.');
            $bytes+=strlen($content);if($bytes>100000)fail(422,'Tool files exceed the 100,000-byte packet budget.');
            $hash=text_field($file,'sha256',64,64);if(!hash_equals(hash('sha256',$content),$hash))fail(422,'Tool file checksum mismatch: '.$name.'. Preserve exact UTF-8 bytes.');
            $files[$fid]=['id'=>$fid,'name'=>$name,'role'=>$role,'content'=>$content,'sha256'=>$hash];$names[strtolower($name)]=true;$roles[$role]=true;
        }
        if(($terminal||$clean['status']==='tested')&&!isset($roles['source'],$roles['test']))fail(422,'A completed/tested tool build needs exact source and test files. Incomplete drafts can retain only the work actually performed.');
        $tests=[];$kinds=[];
        foreach(ra_list($build,'tests',30) as $test){
            ra_keys($test,['id','kind','purpose','file_id','input','expected','observed','comparison','absolute_tolerance','relative_tolerance','tolerance_reason','status','run_log'],'tool test');
            $testId=wf_evidence_id($test);if(isset($tests[$testId]))fail(422,'Tool test IDs must be unique.');
            $fileId=text_field($test,'file_id',1,40);if(!isset($files[$fileId])||$files[$fileId]['role']!=='test')fail(422,'Tool tests must reference a retained test file.');
            $t=['id'=>$testId,'kind'=>wf_evidence_choice($test,'kind',['reference','boundary','invalid_input']),'file_id'=>$fileId,'comparison'=>wf_evidence_choice($test,'comparison',['exact','numeric']),'status'=>wf_evidence_choice($test,'status',['passed','failed','not_run'])];
            foreach(['purpose','tolerance_reason','run_log'] as $key)$t[$key]=text_field($test,$key,20,3000);
            foreach(['input','expected','observed'] as $key)$t[$key]=rt_value($test,$key);
            foreach(['absolute_tolerance','relative_tolerance'] as $key){$t[$key]=ra_number($test[$key]??null,'Tool test tolerance');if($t[$key]<0)fail(422,'Tool tolerances cannot be negative.');}
            if($t['comparison']==='exact'&&($t['absolute_tolerance']!=0||$t['relative_tolerance']!=0))fail(422,'Exact tool tests must use zero tolerances.');
            if($t['comparison']==='numeric'&&(!is_numeric($t['expected'])||!is_finite((float)$t['expected'])))fail(422,'Numeric tool tests need a finite expected value.');
            if($t['status']==='passed'&&!rt_matches($t,$t['observed']))fail(422,'Tool test '.$testId.' does not match its expected result and declared tolerance.');
            if(($terminal||$clean['status']==='tested')&&$t['status']!=='passed')fail(422,'A tested or completed tool needs every test to have run and passed. Use progress for incomplete builds.');
            $tests[$testId]=$t;$kinds[$t['kind']]=true;
        }
        if(($terminal||$clean['status']==='tested')&&(!isset($kinds['reference'],$kinds['boundary'],$kinds['invalid_input'])||$clean['status']!=='tested'))fail(422,'Completed tools need tested status plus reference, boundary and invalid-input tests.');
        $builds[$id]=$clean+['files'=>array_values($files),'tests'=>array_values($tests)];
    }
    if($required&&in_array($row['outcome'],['progress','completed','inconclusive'],true)&&!$builds)fail(422,'Build tasks need a retained tool build; incomplete work uses a draft and progress.');
    $targets=rt_targets($task);$reviews=[];$seen=[];$corrections=[];
    foreach(ra_list($data,'tool_reviews',24) as $review){
        ra_keys($review,['task_id','result_id','tool_id','verdict','method','environment','command','run_log','scientific_assessment','limitations','test_results'],'tool review');
        $tid=text_field($review,'task_id',32,32);$rid=text_field($review,'result_id',32,32);$id=text_field($review,'tool_id',1,40);$key=$tid.'/'.$rid.'/'.$id;
        if(!isset($targets[$key])||isset($seen[$key]))fail(422,'Tool review references an unknown, stale, foreign or duplicate build.');
        $target=$targets[$key];$reviewer=json_decode($row['metadata'],true);
        $authors=all("SELECT actor_hash,metadata FROM workflow_submissions WHERE research_id=? AND task_id=? AND visibility='public' AND admission='accepted'",[$task['research_id'],$tid]);
        foreach($authors as $author){$authorMeta=json_decode($author['metadata'],true);if(empty($authorMeta['evidence']['tool_builds']))continue;
            if(hash_equals($author['actor_hash'],$row['actor_hash'])||$authorMeta['run_id']===$reviewer['run_id'])fail(422,'Tool review requires a different contributor browser and distinct AI run from current and earlier builders of the reviewed task. Identity remains unverified.');
        }
        $v=['task_id'=>$tid,'result_id'=>$rid,'tool_id'=>$id,'verdict'=>wf_evidence_choice($review,'verdict',['reproduced','needs_correction','unverifiable'])];
        foreach(['method','environment','command','run_log','scientific_assessment','limitations'] as $field)$v[$field]=text_field($review,$field,$field==='command'?3:20,5000);
        $expected=array_column($target['build']['tests'],null,'id');$observed=[];
        foreach(ra_list($review,'test_results',30) as $test){
            ra_keys($test,['id','status','observed','run_log'],'independent tool test');$testId=wf_evidence_id($test);
            if(!isset($expected[$testId])||isset($observed[$testId]))fail(422,'Independent tool tests must reference current original test IDs exactly once.');
            $entry=['id'=>$testId,'status'=>wf_evidence_choice($test,'status',['passed','failed','not_run']),'observed'=>rt_value($test,'observed'),'run_log'=>text_field($test,'run_log',20,3000)];
            if($entry['status']==='passed'&&!rt_matches($expected[$testId],$entry['observed']))fail(422,'Independent tool output disagrees with the expected result or tolerance.');
            if($v['verdict']==='reproduced'&&$entry['status']!=='passed')fail(422,'Reproduced tools require every independent test to pass.');
            $observed[$testId]=$entry;
        }
        if($v['verdict']==='reproduced'&&count($observed)!==count($expected))fail(422,'Independent reproduction must report every retained tool test.');
        if($v['verdict']!=='reproduced'){
            if($row['outcome']!=='needs_correction')fail(422,'Incorrect or unverifiable tools require needs_correction.');
            $corrections[]=['task_id'=>$tid,'result_id'=>$rid,'criterion_id'=>'tool-standard','tool_id'=>$id,'reason'=>substr($v['method'],0,1000),'required_change'=>'Revise tool '.$id.': '.substr($v['method'],0,2200).' Recheck scientific assumptions, retained files and reproducible tests.'];
        }
        $v['test_results']=array_values($observed);$reviews[]=$v;$seen[$key]=true;
    }
    if($row['outcome']==='verified'&&count($seen)!==count($targets))fail(422,'Verified review must independently reproduce every current tool build and assess its scientific assumptions.');
    return ['fields'=>['tool_builds'=>array_values($builds),'tool_reviews'=>$reviews],'corrections'=>$corrections];
}
function rt_read(string $rid,string $resultId): array {
    $result=rc_read($rid,$resultId);$task=wf_task($rid,$result['task_id']);
    if(($result['outcome']!=='progress'&&$task['accepted_id']!==$resultId)||($result['outcome']==='progress'&&$task['accepted_id']))fail(404,'This tool build has been superseded.');
    foreach(json_decode($result['metadata'],true)['evidence']['tool_builds']??[] as $build)foreach($build['files'] as $file)if(!hash_equals($file['sha256'],hash('sha256',$file['content'])))fail(409,'Stored tool file checksum mismatch.');
    return $result;
}
function rt_bundle(array $result,array $build): array {
    return ['format'=>'llm-giving-tool-v1','research_id'=>$result['research_id'],'task_id'=>$result['task_id'],'result_id'=>$result['id'],'context_id'=>$result['snapshot_id'],'display_name'=>$result['display_name'],
        'software_license'=>RT_LICENSE,'software_license_text'=>file_get_contents(ROOT.'/LICENSE'),'research_license'=>$result['license'],'build'=>$build,
        'notice'=>'Experimental research software. Files are untrusted inert text; inspect before running in your own tools. Checksums establish byte identity. Execution, reviewer independence and scientific validity remain contributor assessments.'];
}
function rt_route(array $r,string $suffix,string $method): bool {
    if(!preg_match('~^results/([a-f0-9]{32})/tools/([a-z][a-z0-9_-]{0,39})/(bundle\.json|files/([a-z][a-z0-9_-]{0,39}))$~D',$suffix,$m))return false;
    if($method!=='GET')fail(405,'Tool bundles are read-only.');$result=rt_read($r['id'],$m[1]);
    foreach(json_decode($result['metadata'],true)['evidence']['tool_builds']??[] as $build)if($build['id']===$m[2]){
        header("Content-Security-Policy: sandbox; default-src 'none'");
        if($m[3]!=='bundle.json'){foreach($build['files'] as $file)if($file['id']===$m[4]){header('Content-Type: text/plain; charset=utf-8');header('Content-Disposition: attachment; filename="'.$file['name'].'"');header('Cache-Control: no-store');echo $file['content'];return true;}fail(404,'Tool file not found.');}
        header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="'.$m[2].'-tool-bundle.json"');header('Cache-Control: no-store');
        echo json_encode(rt_bundle($result,$build),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG);return true;
    }fail(404,'Tool build not found.');
}