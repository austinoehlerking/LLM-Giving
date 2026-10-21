<?php
const RQ_EFFORTS=['low','medium','high','xhigh','max','ultra'];
function rq_requirements(array $r): array {
 $v=json_decode($r['ai_requirements']??'{}',true)??[];
 return $v+['model'=>'','mode'=>'none','effort'=>''];
}
function rq_matches(array $req,string $model,string $effort): bool {
 if($req['model']!==''&&$req['model']!==$model)return false;
 if($req['mode']==='none')return true;
 $actual=array_search(strtolower($effort),RQ_EFFORTS,true);$minimum=array_search($req['effort'],RQ_EFFORTS,true);
 return $actual!==false&&$minimum!==false&&($req['mode']==='exact'?$actual===$minimum:$actual>=$minimum);
}
function rq_parse(array $d): array {
 $req=['model'=>text_field($d,'required_model',0,100),'mode'=>text_field($d,'reasoning_rule',0,12)?:'none','effort'=>strtolower(text_field($d,'required_reasoning',0,20))];
 if(!in_array($req['mode'],['none','exact','minimum'],true))fail(422,'Choose no reasoning requirement, an exact level, or a minimum level.');
 if($req['mode']==='none')$req['effort']='';
 elseif(!in_array($req['effort'],RQ_EFFORTS,true))fail(422,'Choose a supported reasoning level.');
 if($req['model']!==''&&!in_array($req['model'],array_column(wf_policy(),'model'),true))fail(422,'Choose a model from the configured model list.');
 $possible=false;foreach(wf_policy() as $p)foreach($p['efforts'] as $effort)if(rq_matches($req,$p['model'],$effort))$possible=true;
 if(!$possible)fail(422,'These requirements conflict with mandatory planning. Choose a model and reasoning combination that permits Max or Ultra planning. For a lower reasoning floor, choose Minimum rather than Exact.');
 return $req;
}
function rq_description(array $req): string {
 return 'Model: '.($req['model']?:'no requester restriction').'. Reasoning: '.($req['mode']==='none'?'no requester restriction':($req['mode']==='exact'?'exactly ':'at least ').$req['effort']).'. Applies to all task results and supplementary research submissions. Planning must additionally meet its platform Max/Ultra model and tool policy. Requirements are checked against self-reported metadata, not provider authentication.';
}
function rq_preflight(array $req,array $policy,string $kind): string {
 $allowed=[];foreach($policy as $p)foreach($p['efforts'] as $effort)if(rq_matches($req,$p['model'],$effort))$allowed[]=$p['model'].' / '.$effort.($effort==='ultra'?' / tool '.$p['ultra_tool']:'');
 return "MANDATORY AI CONFIGURATION PREFLIGHT — BEFORE ANY RESEARCH OR REPAIR\n".rq_description($req)."\n".($kind==='planning'?'Eligible planning configurations for this request: '.implode('; ',$allowed).'.':'For this non-planning task, satisfy the requester requirements above.')."\nCheck your actual exact model ID, active reasoning level and any required tool using authoritative runtime/session configuration available to you. The requested settings in this prompt, your own inference, a model name in a packet, or a human assertion are not proof of your active configuration. If any required setting is unknown, unverifiable or does not match, STOP IMMEDIATELY. Do not browse, research, calculate, review, repair or generate a submission packet. Never relabel a nonqualifying run or reuse its work as a qualifying run. Exact means that level only; a higher level does not satisfy exact. Minimum uses low < medium < high < xhigh < max < ultra.\nPREFLIGHT FAILURE OVERRIDES every JSON-only return instruction. Return a short plain-language message with these exact labels:\nBLOCKED — AI CONFIGURATION\nRequired: [copy the applicable exact model, exact/minimum reasoning and tool requirements above].\nActual: [observed model, reasoning and tool; write UNKNOWN for anything you cannot confirm].\nProblem: [identify every mismatch or unconfirmed required setting].\nFix: In your AI app select [a concrete eligible model, reasoning level and required tool from the requirements above]. If the app cannot expose/confirm a required setting, use a tool that can.\nRe-run: Start a NEW conversation/run with those settings. On the research task page click Copy AI handoff again and paste the ENTIRE fresh handoff unchanged. Run the task from the beginning, including this preflight. Do not upload this blocker message or edit an old packet.\nStopped before doing any further work. This message is not a submitted result.\nThis is a run-configuration blocker, not a scientific project blocker. Do not claim the website recorded a blocker or changed project status. Only after preflight passes may you perform the task and follow the normal authorized browser return procedure (or the manual fallback when browser control is unavailable).\n";
}
function rq_enforce(array $req,array $meta): void {
 if(!rq_matches($req,(string)($meta['model']??''),(string)($meta['reasoning']??'')))fail(422,'Requester AI requirement not met. '.rq_description($req).' Use a qualifying AI run; do not edit the declared metadata to bypass this check.');
}
function rq_update(string $rid,array $d): void {
 require_manager($rid);$req=rq_parse($d);
 tx(function()use($rid,$req){$r=one('SELECT * FROM research WHERE id=? FOR UPDATE',[$rid])??fail(404,'Research not found.');
 if(one('SELECT id FROM workflow_submissions WHERE research_id=? LIMIT 1',[$rid])||one('SELECT id FROM contributions WHERE research_id=? LIMIT 1',[$rid]))fail(409,'AI requirements are locked after the first submitted research packet. Start a separate request for a different requirement.');
 $old=rq_requirements($r);if($old===$req)return;
 query('UPDATE research SET ai_requirements=? WHERE id=?',[json_encode($req),$rid]);touch_context($rid,false);
 audit_event($rid,'Requester AI requirements updated',rq_description($req),'research',$rid,json_encode($old),json_encode($req),'Copy a fresh handoff before starting research.','requester','Original submitter',[],$r['moderation']==='Approved'?'public':'private');
 });
}
