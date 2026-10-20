<?php
const KINDS=['Original research','Follow-up research','Independent review','Progress update'];
const META_FIELDS=['provider','model','harness','harness_version','reasoning','input_tokens','output_tokens','total_tokens','measurement','duration','tools','prompt_version'];
function contribution_schema(): array {
    return [
        'kind'=>'Original research','target_id'=>null,'summary'=>'','body'=>'','methodology'=>'','sources'=>'',
        'uncertainty'=>'','limitations'=>'','previous_work'=>'','open_questions'=>'','next_actions'=>'',
        'suggested_status'=>'Still Unsolved','safety'=>'','recommendation'=>'',
        'display_name'=>'','linkedin'=>'','github'=>'','website'=>'',
        'metadata'=>array_fill_keys(META_FIELDS,null)
    ];
}
function parse_contribution(array $post): array {
    if(trim((string)($post['json']??''))==='') return $post;
    try { $d=json_decode($post['json'],true,32,JSON_THROW_ON_ERROR); }
    catch(JsonException) { fail(422,'The JSON could not be parsed. Correct it or use the individual fields.'); }
    if(!is_array($d) || array_is_list($d)) fail(422,'Submit a JSON object.');
    $d['terms']=$post['terms']??null;
    return $d;
}
function create_contribution(string $rid,array $d): string {
    if(!db()->inTransaction())return tx(fn()=>create_contribution($rid,$d));
    one('SELECT id FROM research WHERE id=? FOR UPDATE',[$rid]);
    $r=research($rid);
    if(in_array($r['status'],['Paused','Withdrawn'],true)) fail(422,'This request is not accepting research right now.');
    if(empty($d['terms'])) fail(422,'Accept the contribution and CC BY 4.0 terms.');
    $kind=text_field($d,'kind');
    if(!in_array($kind,KINDS,true)) fail(422,'Choose a supported contribution type.');
    $target=text_field($d,'target_id',0,32)?:null;
    if($kind==='Independent review' && !$target) fail(422,'Select the contribution being reviewed.');
    if($kind!=='Independent review' && $target) fail(422,'Only an independent review has a review target.');
    if($target && !one("SELECT id FROM contributions WHERE id=? AND research_id=? AND moderation='Approved' AND kind<>'Independent review'",[$target,$rid])) fail(422,'Review target must be published research in this request.');
    $row=['id'=>uid(),'research_id'=>$rid,'kind'=>$kind,'target_id'=>$target,'created_at'=>now(),'terms_version'=>terms_version(),'license'=>RESEARCH_LICENSE,'moderation_reason'=>'','metadata_exception'=>''];
    foreach(['summary','body','methodology','sources','uncertainty','limitations','previous_work','open_questions','next_actions','safety'] as $key) $row[$key]=text_field($d,$key,in_array($key,['summary','body'],true)?10:0);
    $row['suggested_status']=text_field($d,'suggested_status',0,80);
    if($row['suggested_status'] && !isset(STATUSES[$row['suggested_status']])) fail(422,'Invalid suggested status.');
    $row['recommendation']=text_field($d,'recommendation',0,40);
    if($kind==='Independent review' && !in_array($row['recommendation'],['Verified','Partially Verified','Needs Correction','Unverifiable'],true)) fail(422,'Choose a review recommendation.');
    $row+=attribution($d);
    $metadata=$d['metadata']??[]; if(!is_array($metadata)) fail(422,'Metadata must be an object.');
    rq_enforce(rq_requirements($r),$metadata);
    $meta=['contribution_id'=>$row['id'],'research_id'=>$rid]; $missing=[];
    foreach(META_FIELDS as $key) {
        $value=$metadata[$key]??null;
        if(is_array($value) && $key==='tools') $value=implode(', ',array_map(fn($v)=>is_scalar($v)?(string)$v:'',$value));
        if($value!==null && !is_scalar($value)) fail(422,'Invalid metadata: '.$key);
        $str=trim((string)$value);
        if($str==='' || in_array(strtolower($str),['unknown','not available','n/a','unavailable','null','none'],true)) $value=null;
        elseif(in_array($key,['input_tokens','output_tokens','total_tokens'],true)) {
            if(!ctype_digit($str) || strlen($str)>12) fail(422,'Token counts must be nonnegative integers or null.');
            $value=(int)$str;
        } else { if(strlen($str)>($key==='tools'?5000:($key==='model'||$key==='measurement'?200:($key==='provider'||$key==='harness'?150:($key==='prompt_version'?80:100))))) fail(422,'Metadata too long: '.$key); $value=$str; }
        $meta[$key]=$value;
        if($value===null && !in_array($key,['duration','harness_version'],true)) $missing[]=$key;
    }
    if($meta['input_tokens']!==null && $meta['output_tokens']!==null && $meta['total_tokens']!==null && $meta['input_tokens']+$meta['output_tokens']!==$meta['total_tokens']) fail(422,'Total tokens must equal input plus output tokens.');
    foreach(['methodology','sources','uncertainty','limitations','previous_work','open_questions','next_actions','safety'] as $k) if($row[$k]==='') $missing[]=$k;
    $meta['missing_fields']=json_encode($missing);
    $row['flags']=($missing?'Missing provenance/content: '.implode(', ',$missing).'. ':'').(suspicious(json_encode($d))?'Suspicious instruction-like content.':'');
    $row['moderation']=$row['flags']?'Needs Additional Review':'Pending Approval';
    if(!$row['flags'] && wf_project($rid)){$row['moderation']='Approved';$row['published_at']=now();}
    tx(function() use($row,$meta,$rid) { insert('contributions',$row); insert('agent_metadata',$meta);if($row['moderation']==='Approved')touch_context($rid);audit_event($rid,'Research contribution submitted',$row['moderation']==='Approved'?'Complete provenance supplied; evidence published automatically without changing task completion.':'Submission retained privately because provenance or safety checks need attention.','contribution',$row['id'],'',$row['moderation'],'Use task submissions to advance the planned workflow.','contributor',$row['display_name']?:'Anonymous volunteer',[],$row['moderation']==='Approved'?'public':'private'); });
    return $row['id'];
}
function create_feedback(string $rid,array $d): string {
    research($rid);
    if(empty($d['terms'])) fail(422,'Accept the public-content terms.');
    $target=text_field($d,'target_id',0,32)?:null; $finding=text_field($d,'finding_id',0,32)?:null;
    if($target && !one("SELECT id FROM contributions WHERE id=? AND research_id=? AND moderation='Approved'",[$target,$rid])) fail(422,'Invalid contribution target.');
    if($finding && !one('SELECT id FROM findings WHERE id=? AND research_id=?',[$finding,$rid])) fail(422,'Invalid finding target.');
    $kind=text_field($d,'kind');
    if(!in_array($kind,['Comment','Correction','Contradictory evidence','More research','Reopen request','Safety report'],true)) fail(422,'Choose a feedback type.');
    $body=text_field($d,'body',10); $id=uid();
    insert('feedback',['id'=>$id,'research_id'=>$rid,'target_id'=>$target,'finding_id'=>$finding,'kind'=>$kind,'body'=>$body,'display_name'=>text_field($d,'display_name',0,120),'moderation'=>suspicious($body)?'Needs Additional Review':'Pending Approval','moderation_reason'=>'','flags'=>suspicious($body)?'Suspicious content':'','terms_version'=>terms_version(),'created_at'=>now()]);
    audit_event($rid,'Feedback submitted','Feedback recorded for follow-up.','feedback',$id,'','Recorded','See the complete feedback record.','contributor',text_field($d,'display_name',0,120)?:'Anonymous volunteer',[],'private');
    return $id;
}
