<?php
function wf_route(array $r,string $suffix,string $method,?array $grant=null): bool {
    $rid=$r['id'];$base='/research/'.$r['slug'];
    if(rtx_route($r,$suffix,$method))return true;
    if(!$grant&&mp_route($r,$suffix,$method))return true;
    if(!$grant&&ar_route($r,$suffix,$method))return true;
    if(rs_route($r,$suffix,$method)||ra_route($r,$suffix,$method)||rc_route($r,$suffix,$method)||rt_route($r,$suffix,$method))return true;
    if(preg_match('~^tasks/([a-f0-9]{32})/reservation$~D',$suffix,$reservationMatch)){wr_http($r,$reservationMatch[1],$method);return true;}
    if($suffix==='work'){
        if($method==='POST'){throttle('workflow-review-request',5);if(empty($_POST['terms']))fail(422,'Accept the public-content terms.');wf_request_review($rid,text_field($_POST,'reason',10,500),text_field($_POST,'display_name',0,120));flash('Another independent review is now available.');redirect($base.'/work');}
        render('workflow',['title'=>'Research tasks','r'=>$r]);return true;
    }
    if($suffix==='handoff.md'){wf_send_handoff(wf_issue_handoff($rid));return true;}
    if(preg_match('~^issued/([a-f0-9]{32})$~',$suffix,$m)){wf_send_handoff(wf_read_handoff($rid,$m[1]));return true;}
    if($suffix==='agents.md'){header('Content-Type: text/plain; charset=utf-8');echo file_get_contents(ROOT.'/templates/safety.txt')."\n".wf_agent_guide($r);return true;}
    if($suffix==='ledger'){
        render('workflow-ledger',['title'=>'UTC activity ledger','r'=>$r,'private'=>false]);return true;
    }
    if($suffix==='package.json'){
        $s=wf_snapshot($rid);header('Content-Type: application/json; charset=utf-8');header('X-Context-ID: '.$s['id']);header('X-Context-SHA256: '.$s['sha256']);echo $s['package_json'];return true;
    }
    if(preg_match('~^packages/([a-f0-9]{32})$~',$suffix,$m)){
        $s=wf_read_snapshot($rid,$m[1]);header('Content-Type: application/json; charset=utf-8');header('X-Context-SHA256: '.$s['sha256']);echo $s['package_json'];return true;
    }
    if(preg_match('~^results/([a-f0-9]{32})$~',$suffix,$m)){
        $result=one("SELECT * FROM workflow_submissions WHERE id=? AND research_id=? AND visibility='public'",[$m[1],$rid])??fail(404,'Result not found.');
        $resultTask=wf_task($rid,$result['task_id']);
        if($resultTask['visibility']!=='public')fail(404,'Result not found.');
        wf_read_snapshot($rid,$result['snapshot_id']);
        render('workflow-result',['title'=>'Research task result','r'=>$r,'result'=>$result]);return true;
    }
    if(preg_match('~^tasks/([a-f0-9]{32})(/prompt.md)?$~',$suffix,$m)){
        $t=wf_task($rid,$m[1]);if($t['visibility']!=='public')fail(404,'Task not found.');
        if(!empty($m[2])){wf_send_handoff(wf_issue_handoff($rid,$t['id']));return true;}
        $error='';$input='';$checked=false;$repair='';$reservationMessage='';$reservationHandoff='';
        if($method==='POST'&&str_starts_with($_POST['operation']??'','reservation-')){
            $input=text_field($_POST,'payload',0,RTX_MAX_BYTES);
            try{
                $result=mp_control($r,$t,substr($_POST['operation'],12),mp_revision($_POST),$grant);
                $reservationMessage=$result['message'];$reservationHandoff=$result['handoff']??'';
            }catch(HttpError $e){http_response_code($e->status);$error=$e->getMessage();$result=['message'=>$error];}
            if(mp_json()){mp_response($r,$t,$grant,'workflow-result',$result,!$error);return true;}
        }elseif($method==='POST'){
            $checkOnly=($_POST['operation']??'')==='check';
            $jsonCheck=$checkOnly&&str_contains($_SERVER['HTTP_ACCEPT']??'','application/json');
            throttle($checkOnly?'workflow-check':'workflow-result',$checkOnly?60:(int)setting('submission_limit','12'));
            try{
                $input=rtx_packet_input($r,$t,$grant,$_POST);
                try{$d=json_decode($input,true,32,JSON_THROW_ON_ERROR);}catch(JsonException){fail(422,'The AI output is not valid JSON. Paste only its complete JSON object, without code fences or extra text. Do not edit it yourself; return the repair prompt to your AI.');}
                if(!is_array($d)||array_is_list($d))fail(422,'The AI must return one complete JSON object.');
                // Authorization and the human declaration never come from AI packet fields.
                if($grant){$id=ar_record($r,$grant,$d,$checkOnly);$d['human_disclosure']=$grant['consent']['human_disclosure'];}
                else{
                    $d['human_disclosure']=ar_disclosure($_POST);
                    $d['terms']=$checkOnly?1:($_POST['terms']??null);
                    $d['tool_terms']=$checkOnly?1:($_POST['tool_terms']??null);
                    $id=wf_submit($rid,$t['id'],$d,wf_actor(),$checkOnly,'manual');
                }
                if($checkOnly){
                    $checked=true;
                    if($jsonCheck){header('Content-Type: application/json');header('Cache-Control: no-store');echo json_encode(['ok'=>true,'message'=>'Packet checks passed. Nothing has been submitted. Submit the unchanged packet and inspect its recorded receipt; check success alone is not submission.','influence_review'=>wf_influence($d,$grant['consent']??null)['review_status']==='needed','packet_contract'=>function_exists('wf_packet_contract')?wf_packet_contract($t,wf_read_snapshot($rid,$d['context_id'])):null]);return true;}
                }else{flash('Packet recorded. See its acceptance or quarantine status below.');redirect($base.'/results/'.$id);}
            }catch(HttpError $e){
                http_response_code($e->status);$error=$e->getMessage();// Only ar_read()'s stored private grant selects prose; packet fields never select this mode.
                $sessionReturn=$grant&&!empty($grant['scope']['volunteer_session_id'])&&!empty($grant['scope']['volunteer_assignment_id']);
                $repair=wf_repair_prompt($r,$t,$error,(bool)$grant,$grant?$base.'/return/'.$grant['id'].'/submit':null,$sessionReturn).($grant?vo_formatting_guidance($grant['consent']):'');
                if($jsonCheck){header('Content-Type: application/json');header('Cache-Control: no-store');echo json_encode(['ok'=>false,'message'=>$error,'repair_prompt'=>$repair,...(function_exists('wf_packet_exception_details')?wf_packet_exception_details($e):[]),'packet_contract'=>function_exists('wf_packet_contract')?wf_packet_contract($t,$grant?wf_read_snapshot($rid,$grant['scope']['context_id']):null):null]);return true;}
                // Private return drafts can contain pasted capabilities. Keep them in the response, never in attempt logs.
                if(!$grant&&!$checkOnly&&(!isset($d)||!is_array($d)||array_is_list($d))){
                    insert('workflow_attempts',['id'=>uid(),'research_id'=>$rid,'task_id'=>$t['id'],'payload'=>$input,'reason'=>$error,'created_at'=>now()]);
                    audit_event($rid,'Submission not accepted',$error,'task',$t['id'],'','No change','Return the repair prompt to your AI.','contributor','Anonymous volunteer',[],'private');
                }
            }
        }
        $s=wf_snapshot($rid);
        render('workflow-task',['title'=>$t['title'],'r'=>$r,'task'=>$t,'snapshot'=>$s,'error'=>$error,'input'=>$input,'checked'=>$checked,'repair'=>$repair,'grant'=>$grant,'reservationMessage'=>$reservationMessage,'reservationHandoff'=>$reservationHandoff]);return true;
    }
    return false;
}
