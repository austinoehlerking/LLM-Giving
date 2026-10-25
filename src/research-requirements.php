<?php
const RQ_EFFORTS=['low','medium','high','xhigh','max','ultra'];
function rq_requirements(array $r): array {
    $v=json_decode($r['ai_requirements']??'{}',true)??[];
    // Do not rewrite historical records or immutable snapshots.
    return ($v['version']??null)===2?$v:$v+['model'=>'','mode'=>'none','effort'=>''];
}
/** JSON object key order is storage detail; lists, scalar types and every value remain exact. */
function rq_same_requirements(mixed $captured,mixed $current): bool {
    if (!is_array($captured) || !is_array($current) || array_is_list($captured) || array_is_list($current)) return false;
    $canonical = static function(array $value) use (&$canonical): array {
        foreach ($value as $key=>$item) if (is_array($item)) $value[$key]=$canonical($item);
        if (!array_is_list($value)) ksort($value,SORT_STRING);
        return $value;
    };
    return $canonical($captured)===$canonical($current);
}
function rq_choices(array $req): array {
    if(($req['version']??null)===2)return $req;
    return ['version'=>2,'models'=>$req['model']!==''?[$req['model']]:[],
        'efforts'=>$req['mode']==='none'?[]:[['level'=>$req['effort'],'or_higher'=>$req['mode']==='minimum'&&$req['effort']!=='ultra']],
        'modes'=>[],'registry'=>wf_model_registry()];
}
function rq_matches(array $req,string $model,string $effort,string $mode='',string $tool=''): bool {
    $effort=strtolower($effort);
    if(($req['version']??null)!==2){
        if($req['model']!==''&&$req['model']!==$model)return false;
        if($req['mode']==='none')return true;
        $actual=array_search($effort,RQ_EFFORTS,true);$minimum=array_search($req['effort'],RQ_EFFORTS,true);
        return $actual!==false&&$minimum!==false&&($req['mode']==='exact'?$actual===$minimum:$actual>=$minimum);
    }
    if($req['models']&&!in_array($model,$req['models'],true))return false;
    $support=$req['registry'][$model]??null;
    if($support&&in_array($effort,RQ_EFFORTS,true)&&!in_array($effort,$support['efforts'],true))return false;
    if($mode!==''&&($mode!=='ultracode'||!$support||!in_array($mode,$support['modes'],true)||!in_array($effort,$support['efforts'],true)||strcasecmp($tool,'Claude Code')!==0))return false;
    if($req['modes']&&!in_array($mode,$req['modes'],true))return false;
    if(!$req['efforts'])return true;
    if(!$support)return false;
    foreach($req['efforts'] as $choice){
        $floor=array_search($choice['level'],$support['efforts'],true);
        $actual=array_search($effort,$support['efforts'],true);
        if($floor!==false&&$actual!==false&&($choice['or_higher']?$actual>=$floor:$actual===$floor))return true;
    }
    return false;
}
function rq_list(array $d,string $key,array $allowed): array {
    $values=$d[$key]??[];
    if(!is_array($values)||!array_is_list($values)||count($values)>20)fail(422,'Invalid '.$key.' selection.');
    foreach($values as $value)if(!is_string($value)||!in_array($value,$allowed,true))fail(422,'Choose a supported '.$key.' option.');
    if(in_array('any',$values,true)&&count(array_unique($values))>1)fail(422,'Any cannot be combined with specific '.$key.' options.');
    return array_values(array_filter($allowed,fn($value)=>$value!=='any'&&in_array($value,$values,true)));
}
function rq_parse(array $d): array {
    if(!isset($d['ai_selection_version'])){
        if(array_intersect(['required_models','reasoning_levels','reasoning_or_higher'],array_keys($d)))fail(422,'Current AI selection fields require ai_selection_version=2. Refresh the form or setup template.');
        $req=['model'=>text_field($d,'required_model',0,100),'mode'=>text_field($d,'reasoning_rule',0,12)?:'none','effort'=>strtolower(text_field($d,'required_reasoning',0,20))];
        if(!in_array($req['mode'],['none','exact','minimum'],true))fail(422,'Choose no reasoning requirement, an exact level, or a minimum level.');
        if($req['mode']==='none')$req['effort']='';
        elseif(!in_array($req['effort'],RQ_EFFORTS,true))fail(422,'Choose a supported reasoning level.');
        if($req['model']!==''&&!in_array($req['model'],array_column(wf_policy(),'model'),true))fail(422,'Choose a model from the configured model list.');
    }else{
        if((string)$d['ai_selection_version']!=='2')fail(422,'Refresh the AI selection form.');
        foreach(['required_model','reasoning_rule','required_reasoning'] as $key)if(isset($d[$key]))fail(422,'Do not mix historical and current AI selection fields.');
        $registry=wf_model_registry();
        $models=rq_list($d,'required_models',['any',...array_keys($registry)]);
        $levels=rq_list($d,'reasoning_levels',['any',...RQ_EFFORTS,'ultracode']);
        $higher=rq_list($d,'reasoning_or_higher',array_slice(RQ_EFFORTS,0,-1));
        if(array_diff($higher,$levels))fail(422,'Select the effort level before enabling its or higher option.');
        $req=['version'=>2,'models'=>$models,'efforts'=>[],'modes'=>in_array('ultracode',$levels,true)?['ultracode']:[],'registry'=>$registry];
        foreach(RQ_EFFORTS as $level)if(in_array($level,$levels,true))$req['efforts'][]=['level'=>$level,'or_higher'=>in_array($level,$higher,true)];
    }
    $possible=false;
    foreach(wf_policy() as $p)foreach($p['efforts'] as $effort){
        foreach(['','ultracode'] as $mode){
            $tool=$mode==='ultracode'?'Claude Code':($effort==='ultra'?($p['ultra_tool']??''):'');
            if(rq_matches($req,$p['model'],$effort,$mode,$tool))$possible=true;
        }
    }
    if(!$possible)fail(422,'These choices leave no qualifying planning configuration. Planning requires an approved model at Max or Ultra. Select a compatible effort, enable or higher, add another model, or choose Any. Ultracode still requires an eligible actual effort and Claude Code.');
    return $req;
}
function rq_description(array $req): string {
    if(($req['version']??null)!==2){
        return 'Model: '.($req['model']?:'no requester restriction').'. Reasoning: '.($req['mode']==='none'?'no requester restriction':($req['mode']==='exact'?'exactly ':'at least ').$req['effort']).'. Applies to all task results and supplementary research submissions. Planning must additionally meet its platform Max/Ultra model and tool policy. Requirements are checked against self-reported metadata, not provider authentication.';
    }
    $resolved=[];foreach(($req['models']||$req['efforts']||$req['modes']?$req['registry']:[]) as $model=>$support){
        if($req['models']&&!in_array($model,$req['models'],true))continue;
        $mode=$req['modes'][0]??'';
        $levels=array_filter($support['efforts'],fn($effort)=>rq_matches($req,$model,$effort,$mode,$mode?'Claude Code':''));
        $resolved[]=$model.': '.($levels?implode(', ',$levels):'no matching effort');
    }
    $efforts=array_map(fn($c)=>$c['level'].($c['or_higher']?' or higher (supported levels of the actual model only)':' exactly'),$req['efforts']);
    return 'Models: '.($req['models']?implode(' OR ',$req['models']):'Any — no additional requester model restriction').
        '. Effort alternatives: '.($efforts?implode(' OR ',$efforts):'Any — no additional requester effort restriction').
        '. Workflow mode: '.($req['modes']?'Ultracode — Claude Code workflow mode REQUIRED, AND the effort alternatives above':'Any — no workflow mode restriction').
        '. Ultracode is not an effort level and never substitutes for Max or Ultra. Effort scales are per model, not equivalent capability across models. Applies to every task and supplementary submission; platform planning policy still applies. Settings are self-reported, not provider authenticated.'.($resolved?' Resolved requester settings: '.implode('; ',$resolved).'.':'');
}
function rq_preflight(array $req,array $policy,string $kind): string {
 $allowed=[];foreach($policy as $p)foreach($p['efforts'] as $effort){$mode=($req['modes']??[])?'ultracode':'';$tool=$mode?'Claude Code':($effort==='ultra'?($p['ultra_tool']??''):'');if(rq_matches($req,$p['model'],$effort,$mode,$tool))$allowed[]=$p['model'].' / '.$effort.($mode?' / Claude Code / ultracode':($effort==='ultra'?' / tool '.$tool:''));}
 return "MANDATORY AI CONFIGURATION PREFLIGHT — BEFORE ANY RESEARCH OR REPAIR\n".rq_description($req)."\n".($kind==='planning'?'Eligible planning configurations for this request: '.implode('; ',$allowed).'.':'For this non-planning task, satisfy the requester requirements above.')."\nCheck the assigned worker’s exact model, configured reasoning effort and any required tool before research. Use directly observed authoritative runtime/session settings OR a successful native controller launch record. For launch evidence inspect the ORIGINAL create request with explicit model/thinking and its actual successful response (threadId, turnId, admissionOutcome=started or another explicitly successful admission, isError=false). Bind that worker task to this website assignment/revision in the COMPLETE handed-off input; the provider need not return a website task ID. A request without successful admission, a prompt/human guess, a bare JSON assertion, missing exact settings, a different worker, a stale/unbound record or known fallback does not qualify. A controller-recorded exact model/effort selection qualifies only where the task permits it, never as a bypass of requester requirements or planning Max/Ultra policy.\nSAME-WORKER CONTINUATION: The successful original create+request pair remains the configured-launch basis for same-settings continuation. Send every research/repair follow-up to that same worker task with explicit model and thinking, and retain the returned turn record after success. Native admissionOutcome=steered may deliver input to an already admitted ACTIVE turn; retain that same turn ID, do not call it a fresh started turn or require another worker. Reject steering to an unknown/stale/different turn and duplicate fresh started receipts. Do not require the future admission response of a follow-up before it can be sent. A failed, mismatched or changed-setting follow-up must stop; an old unrelated launch cannot qualify it. Do not require or invent a fallback=false field when the native tool exposes none; reject known fallback or conflicting evidence.\nCONFIGURED LAUNCH IS NOT PROVIDER VERIFICATION. Missing provider-effective runtime readback alone does NOT block a qualifying controller-recorded launch. Explicitly disclose the unavailable readback. Worker metadata remains self-reported, not provider-authenticated. A sanitized run_evidence summary may state the controller-recorded model/thinking and bound task/turn basis plus that limitation; keep raw private logs, transport/configuration identifiers, session/return links and credentials out of public packets. Never claim provider-verified/effective settings from launch admission. If the required launch configuration is unknown, unverifiable or does not match, STOP IMMEDIATELY. Do not browse, research, calculate, review, repair or generate a submission packet. Never relabel a nonqualifying run or reuse its work as a qualifying run. Exact means that level only; a higher level does not satisfy exact. For current requirements, or higher expands only through supported levels of the actual model; an unsupported starting level never matches. Effort alternatives are OR; a workflow mode restriction is AND. Ultracode is separate from effort: record workflow_mode=ultracode, tool or harness=Claude Code, and the actual numeric reasoning level independently. Never normalize Ultracode to Ultra or Max. Do not use silent model/effort fallback; stop on changed or conflicting launch/runtime evidence. Historical minimum requirements retain their original low < medium < high < xhigh < max < ultra order.\nPREFLIGHT FAILURE OVERRIDES every JSON-only return instruction. Return a short plain-language message with these exact labels:\nBLOCKED — AI CONFIGURATION\nRequired: [copy the applicable exact model, exact/minimum reasoning and tool requirements above].\nActual: [observed settings or configured launch model/effort/tool and evidence basis; write UNKNOWN for missing required launch fields; disclose provider-effective readback unavailability separately].\nProblem: [identify every mismatch or unconfirmed required setting].\nFix: In your AI app select [a concrete eligible model, reasoning level and required tool from the requirements above]. Use direct settings evidence or the successful native launch record. Missing provider-effective readback alone does not require another tool when that configured launch qualifies.\nRe-run: Start a NEW conversation/run with those settings. On the research task page click Copy AI handoff again and paste the ENTIRE fresh handoff unchanged. Run the task from the beginning, including this preflight. Do not upload this blocker message or edit an old packet.\nStopped before doing any further work. This message is not a submitted result.\nThis is a run-configuration blocker, not a scientific project blocker. Do not claim the website recorded a blocker or changed project status. Only after preflight passes may you perform the task. In an explicitly authorized controller/worker workflow, the research-only worker authors the COMPLETE JSON packet and returns it, with same-worker repairs, to the browser-capable controller. That worker does not need browser tools or submission credentials. The authorized controller performs Check/Submit/receipt recovery and does not claim that the worker submitted. Otherwise retain the ordinary authorized browser path or manual fallback. Delivery changes no scientific requirement, consent, disclosure, signature, lease or idempotency rule.\n";
}
function rq_enforce(array $req,array $meta): void {
 $mode=$meta['workflow_mode']??'';
 if($mode){
  $support=($req['registry']??wf_model_registry())[$meta['model']??'']??null;
  if($mode!=='ultracode'||!$support||!in_array($mode,$support['modes'],true)||!in_array(strtolower((string)($meta['reasoning']??'')),$support['efforts'],true)||strcasecmp((string)($meta['tool']??$meta['harness']??''),'Claude Code')!==0)fail(422,'Ultracode requires a supported exact model, Claude Code, and a separately reported supported actual effort.');
 }

 if(!rq_matches($req,(string)($meta['model']??''),(string)($meta['reasoning']??''),(string)($meta['workflow_mode']??''),(string)($meta['tool']??$meta['harness']??'')))fail(422,'Requester AI requirement not met. '.rq_description($req).' Use a qualifying AI run; do not edit the declared metadata to bypass this check.');
}
function rq_update(string $rid,array $d): void {
 require_manager($rid);
 tx(function()use($rid,$d){$r=one('SELECT * FROM research WHERE id=? FOR UPDATE',[$rid])??fail(404,'Research not found.');
 if(one('SELECT id FROM workflow_submissions WHERE research_id=? LIMIT 1',[$rid])||one('SELECT id FROM contributions WHERE research_id=? LIMIT 1',[$rid]))fail(409,'AI requirements are locked after the first submitted research packet. Start a separate request for a different requirement.');
 $old=rq_requirements($r);
 if(($old['version']??null)!==2&&(string)($d['ai_selection_version']??'')==='2'&&!array_intersect(['required_model','reasoning_rule','required_reasoning'],array_keys($d))){
  $choices=rq_choices($old);
  $expected=['required_models'=>$choices['models'],'reasoning_levels'=>array_column($choices['efforts'],'level'),'reasoning_or_higher'=>array_column(array_filter($choices['efforts'],fn($c)=>$c['or_higher']),'level')];
  $same=true;foreach($expected as $key=>$values){$input=$d[$key]??[];if($input===['any'])$input=[];if(!is_array($input)||!array_is_list($input)||array_filter($input,fn($v)=>!is_string($v))){$same=false;break;}sort($input);sort($values);if($input!==$values)$same=false;}
  if($same)return;
 }
 $req=rq_parse($d);if(rq_same_requirements($old,$req))return;
 if(($old['version']??null)!==2&&($req['version']??null)===2){$oldChoices=rq_choices($old);if($oldChoices['models']===$req['models']&&$oldChoices['efforts']===$req['efforts']&&$oldChoices['modes']===$req['modes'])return;}
 query('UPDATE research SET ai_requirements=? WHERE id=?',[json_encode($req),$rid]);touch_context($rid,false);
 audit_event($rid,'Requester AI requirements updated',rq_description($req),'research',$rid,'Previous AI requirements','Updated AI requirements','Copy a fresh handoff before starting research.','requester','Original submitter',['previous'=>$old,'current'=>$req],$r['moderation']==='Approved'?'public':'private');
 });
}
