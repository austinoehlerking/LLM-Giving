<?php
declare(strict_types=1);
/** Operator-only synthetic fixture contract. Never loaded by the web application. */
function cv_need(bool $ok,string $message): void { if(!$ok)throw new RuntimeException($message); }
function cv_scope(string $scope): string { cv_need((bool)preg_match('/^[a-f0-9]{32}$/D',$scope),'An explicit 32-character lowercase hexadecimal synthetic scope is required.');return $scope; }
function cv_args(array $argv): array {
    $out=['action'=>'plan','destination'=>'local'];
    if(isset($argv[1])&&!str_starts_with($argv[1],'--')){$out['action']=$argv[1];array_splice($argv,1,1);}
    cv_need(in_array($out['action'],['plan','create','inspect','snapshot','verify','cleanup'],true),'Unknown action.');
    $seen=[];foreach(array_slice($argv,1) as $arg){
        cv_need(str_starts_with($arg,'--'),'Options must use --name=value.');$parts=explode('=',substr($arg,2),2);$key=$parts[0];
        cv_need(in_array($key,['scope','destination','candidate','tree','manifest','phase','snapshots','result','apply','allow-live-synthetic','confirm-scope'],true),'Unknown option: '.$key);
        cv_need(!isset($seen[$key]),'Duplicate option: '.$key);$seen[$key]=true;
        if(in_array($key,['apply','allow-live-synthetic'],true)){cv_need(count($parts)===1,'Boolean flags take no value.');$out[$key]=true;}
        else{cv_need(count($parts)===2&&$parts[1]!=='','An option value is required.');$out[$key]=$parts[1];}
    }
    return $out;
}
function cv_guard(array $options,array $runtime): void {
    cv_scope($options['scope']??'');
    foreach(['candidate','tree'] as $key)cv_need((bool)preg_match('/^[a-f0-9]{40}$/D',$options[$key]??''),'Explicit application candidate and tree SHAs are required.');
    if(($options['destination']??'local')==='local'){
        cv_need(in_array($runtime['environment']??'',['local','test','testing'],true),'Local tooling refuses a production environment.');
        cv_need(in_array(strtolower($runtime['host']??''),['127.0.0.1','localhost','::1'],true),'Local tooling requires a loopback database.');
        cv_need((bool)preg_match('/^llm_giving_cloud_qa_[a-z0-9_]+$/D',$runtime['database']??''),'Use a dedicated llm_giving_cloud_qa_* database, never the shared default.');
        cv_need((bool)preg_match('~^http://127\.0\.0\.1:[0-9]+/?$~D',$runtime['url']??''),'Local APP_URL must use loopback and an explicit port.');
    }else{
        cv_need($options['destination']==='https://llm.giving'&&!empty($options['allow-live-synthetic']),'Live access requires the exact destination and --allow-live-synthetic.');
        cv_need(($runtime['environment']??'')==='production'&&rtrim($runtime['url']??'','/')==='https://llm.giving','Live destination differs from the actual configured application.');
    }
}
function cv_spec(string $scope): array {
    cv_scope($scope);
    return ['title'=>'[QA Cloud Volunteer '.$scope.'] NASA source-reading acceptance',
        'question'=>'Synthetic software acceptance only: inspect the official NASA Apollo 11 and Apollo 12 mission pages, identify each stated crew count, and show their combined count using model reasoning without executing calculations.',
        'context'=>'ISOLATED SYNTHETIC ACCEPTANCE SCOPE '.$scope.'. This fixture supplies no answer, extracted values, research packet, source-inspection claim or model provenance. The volunteer must inspect accessible primary pages and author its own evidence and worked derivation. The two computational control tasks test exclusion only; the restricted volunteer must not attempt them. This is software QA, not scientific validation.',
        'references_text'=>"Candidate primary pages for the volunteer to inspect: https://www.nasa.gov/mission/apollo-11/ and https://www.nasa.gov/mission/apollo-12/ . Access is not asserted by this fixture. If inaccessible, record the limitation honestly.",
        'tasks'=>[
            'narrative'=>['key'=>'cloud-narrative','computational'=>false,'title'=>'Read primary sources and explain a model-derived sum','objective'=>'Read the two official NASA mission pages in the brief. Extract the crew counts with source locations, compare their scope, and work through their sum directly without tools. Do not invent access or execution.','deliverable'=>'A short cited narrative and honest non-executed arithmetic record with inputs, units, formula, worked steps, result, assumptions and limitations. Retain appropriate supporting materials.','acceptance'=>'Cite inspected primary source locations and retained inputs.\nExplain the addition with model-derived worked steps and scope limits, without code, calculators, invented files or execution/reproduction claims.'],
            'execution'=>['key'=>'cloud-execution','computational'=>true,'title'=>'INCOMPATIBLE CONTROL: execute a calculation','objective'=>'Synthetic exclusion control. This assignment requires executing code using scientific software and retaining actual code, software versions and outputs. It cannot be performed by the restricted web-research profile.','deliverable'=>'An actually executed calculation with reproducible supporting files; a narrative derivation does not satisfy this control.','acceptance'=>'Execute and retain the calculation, its exact code, environment and output.'],
            'reproduction'=>['key'=>'cloud-reproduction','computational'=>true,'title'=>'INCOMPATIBLE CONTROL: computational reproduction','objective'=>'Synthetic exclusion control. Independently execute an original primary-source reproducibility package and compare its computed outputs. No prior LLM.Giving execution or accepted result is claimed by this fixture.','deliverable'=>'Actual independent computational reproduction, with software, input/code/output files and comparison.','acceptance'=>'Execute the original package and retain independent reproduction evidence.']]];
}
function cv_json_file(string $file): array {
    cv_need(is_file($file)&&filesize($file)<=200000,'Expected a bounded local JSON evidence file.');$data=json_decode(file_get_contents($file),true,32,JSON_THROW_ON_ERROR);cv_need(is_array($data)&&!array_is_list($data),'Expected a JSON object.');return $data;
}
function cv_manifest_check(array $m,string $scope,string $destination): void {
    cv_need(in_array($destination,['local','https://llm.giving'],true),'Unknown acceptance destination.');
    foreach(['candidate','tree'] as $key)cv_need((bool)preg_match('/^[a-f0-9]{40}$/D',$m[$key]??''),'Manifest source identities are required.');
    cv_need(($m['version']??null)===1&&($m['scope']??'')===cv_scope($scope)&&($m['destination']??'')===$destination,'Manifest scope/destination/version mismatch.');
    cv_need((bool)preg_match('/^[a-f0-9]{32}$/D',$m['research']['id']??''),'Invalid synthetic study identity.');
    $roles=array_keys($m['tasks']??[]);sort($roles);cv_need($roles===['execution','narrative','reproduction'],'Manifest must name precisely the three fixture tasks.');$ids=[];
    foreach($m['tasks'] as $task){cv_need((bool)preg_match('/^[a-f0-9]{32}$/D',$task['id']??'')&&is_int($task['revision']??null)&&$task['revision']>0,'Invalid task identity or revision.');$ids[]=$task['id'];}
    cv_need(count(array_unique($ids))===3,'Fixture task identities must be distinct.');
}
/** Inspect retained derivation structure and citations; deliberately never evaluate a formula. */
function cv_model_derived_count(array $e): int {
    $derivations=$e['derivations']??[];if(!is_array($derivations)||!array_is_list($derivations)||count($derivations)>4)return 0;
    $sources=[];foreach($e['sources']??[] as $s)if(($s['use']??'')==='used'&&in_array($s['access']??'',['full_text','abstract_only'],true))$sources[$s['id']]=true;
    $claims=array_column($e['claims']??[],null,'id');$count=0;
    foreach($derivations as $d){
        $allowed=['version','type','id','title','claim_ids','source_ids','inputs','formula','steps','outputs','assumptions','limitations'];
        if(!is_array($d)||array_diff(array_keys($d),$allowed)||($d['version']??null)!==1||($d['type']??'')!=='model_derived')continue;
        $valid=true;foreach(['id','title','formula','assumptions','limitations'] as $field)$valid=$valid&&is_string($d[$field]??null)&&trim($d[$field])!=='';
        foreach(['claim_ids','source_ids','inputs','steps','outputs'] as $field)$valid=$valid&&is_array($d[$field]??null)&&array_is_list($d[$field])&&count($d[$field])>0;
        if(!$valid)continue;
        foreach($d['source_ids'] as $id)$valid=$valid&&is_string($id)&&isset($sources[$id]);foreach($d['claim_ids'] as $id)$valid=$valid&&is_string($id)&&isset($claims[$id]);
        foreach($d['inputs'] as $input){$valid=$valid&&is_array($input)&&in_array($input['source_id']??'', $d['source_ids'],true)&&isset($sources[$input['source_id']??'']);foreach(['unit','source_locator','extraction'] as $field)$valid=$valid&&is_string($input[$field]??null)&&trim($input[$field])!=='';$v=$input['value']??null;$valid=$valid&&(is_int($v)||is_float($v))&&is_finite((float)$v);}
        foreach($d['steps'] as $step)$valid=$valid&&is_string($step)&&trim($step)!=='';
        foreach($d['outputs'] as $output){$v=$output['value']??null;$valid=$valid&&(is_int($v)||is_float($v))&&is_finite((float)$v)&&is_string($output['unit']??null)&&trim($output['unit'])!=='';}
        if($valid)$count++;
    }return $count===count($derivations)?$count:0;
}
/** Checks normalized independent DB observations; never certifies handset, source access or AI authorship. */
function cv_verify(array $m,array $snapshots,string $result): array {
    cv_manifest_check($m,$m['scope']??'',$m['destination']??'');cv_need((bool)preg_match('/^[a-f0-9]{32}$/D',$result),'An explicit recorded result ID is required.');
    $phases=['before_check','after_check','after_submit','after_recovery','after_stop'];$keys=array_keys($snapshots);sort($keys);$expected=$phases;sort($expected);cv_need($keys===$expected,'Supply all five independent observations.');$checks=[];$fail=[];$previous='';
    $check=function(string $name,bool $pass)use(&$checks,&$fail){$checks[]=['name'=>$name,'pass'=>$pass];if(!$pass)$fail[]=$name;};
    foreach($phases as $phase){$s=$snapshots[$phase];
        $check($phase.': exact scope/destination/task/revision',($s['scope']??'')===$m['scope']&&($s['destination']??'')===$m['destination']&&($s['phase']??'')===$phase&&($s['research_id']??'')===$m['research']['id']&&($s['task_id']??'')===$m['tasks']['narrative']['id']&&($s['revision']??null)===$m['tasks']['narrative']['revision']);
        $check($phase.': same reported candidate/tree',($s['candidate']??'')===($m['candidate']??null)&&($s['tree']??'')===($m['tree']??null));
        $time=$s['observed_at']??'';$utc=is_string($time)?DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.u\Z',$time,new DateTimeZone('UTC')):false;$check($phase.': ordered actual capture time',$utc!==false&&$utc->format('Y-m-d\TH:i:s.u\Z')===$time&&($previous===''||strcmp($time,$previous)>=0));$previous=$time;
        $check($phase.': controls untouched',($s['control_assignments']??null)===0&&($s['control_results']??null)===0);
        if(in_array($phase,['before_check','after_check'],true)){$check($phase.': preview did not record a result',($s['study_result_count']??null)===0&&($s['results']??null)===[]);continue;}
        $rows=$s['results']??[];$check($phase.': exactly one result and no duplicate',($s['study_result_count']??null)===1&&count($rows)===1&&($rows[0]['id']??'')===$result);$r=$rows[0]??[];
        $check($phase.': accepted completed AI browser result',($r['admission']??'')==='accepted'&&($r['outcome']??'')==='completed'&&($r['submission_method']??'')==='ai_browser');
        $check($phase.': current admitted visible result',($s['accepted_id']??'')===$result&&($s['visible']??false)===true&&($r['visible']??false)===true);
        $check($phase.': signed stored restricted-worker binding',($r['task_signature_valid']??false)===true&&($r['restricted_binding']??false)===true);
        $check($phase.': honest arithmetic without executed evidence',($r['model_derived_count']??0)>0&&($r['executed_or_tool_evidence']??true)===false);
    }
    $check('Stop preserves the recorded outcome',($snapshots['after_stop']['session_stopped']??false)===true);
    return ['version'=>1,'scope'=>$m['scope'],'stored_result_verification'=>$fail?'FAIL':'PASS','checks'=>$checks,'failures'=>$fail,'real_phone_cloud_acceptance'=>'NOT_ESTABLISHED_BY_THIS_TOOL','limits'=>'Independent DB observations establish recorded state only. Handset launch, actual Cloud identity/browser support, source inspection, allowed tools, research quality and deployed source require separate genuine evidence. Candidate/tree are operator-reported, not host-attested.'];
}
