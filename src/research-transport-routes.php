<?php
require_once __DIR__.'/research-transport.php';
function rtx_response(array $value): void { header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo rtx_json($value); }
function rtx_send_part(array $manifest,int $index,string $body): void {
    rtx_verify_part($manifest,$index,$body);header('Content-Type: text/plain; charset=utf-8');header('Cache-Control: no-store');header('X-Transport-Part: '.$index);header('X-Transport-Bytes: '.strlen($body));header('X-Transport-SHA256: '.hash('sha256',$body));echo $body;
}
function rtx_route(array $r,string $suffix,string $method): bool {
    if(preg_match('~^(issued|packages)/([a-f0-9]{32})/transport(?:/(manifest|parts/([0-9]+)))?$~D',$suffix,$m)){
        if($method!=='GET')fail(405,'Public transport is read-only.');$id=$m[2];
        if($m[1]==='issued'){$source=wf_read_handoff($r['id'],$id);$body=$source['body'];$task=$source['task_id']?wf_task($r['id'],$source['task_id']):null;$binding=['research_id'=>$r['id'],'task_id'=>$source['task_id'],'task_revision'=>$task?(int)(array_column(json_decode(wf_read_snapshot($r['id'],$source['snapshot_id'])['package_json'],true)['workflow']['tasks'],null,'id')[$task['id']]['revision']??$task['revision']):null,'context_id'=>$source['snapshot_id'],'archive_id'=>$source['id'],'submission_key'=>$source['nonce']];}
        else{$source=wf_read_snapshot($r['id'],$id);$body=$source['package_json'];$binding=['research_id'=>$r['id'],'context_id'=>$id];}
        $base='/research/'.$r['slug'].'/'.$m[1].'/'.$id.'/transport';$manifest=rtx_manifest($body,$binding,$base.'/parts');
        if(($m[3]??'')==='manifest'){rtx_response($manifest);return true;}
        if(isset($m[4])&&$m[4]!==''){$index=(int)$m[4];$parts=rtx_parts($body);if(!isset($parts[$index]))fail(404,'Transport part not found.');rtx_send_part($manifest,$index,$parts[$index]);return true;}
        render('research-transport',['title'=>'Complete research transport','manifest'=>$manifest,'transportBase'=>$base,'private'=>false]);return true;
    }
    if(preg_match('~^packages/([a-f0-9]{32})/tasks/([a-f0-9]{32})/dependencies/([a-f0-9]{64})/(manifest|parts/([0-9]+))$~D',$suffix,$m)){
        if($method!=='GET')fail(405,'Dependencies are read-only.');$resource=tc_read_dependency($r['id'],$m[1],$m[3],$m[2]);$base='/research/'.$r['slug'].'/packages/'.$m[1].'/tasks/'.$m[2].'/dependencies/'.$m[3];$manifest=rtx_manifest($resource['body'],$resource['binding'],$base.'/parts');
        if($m[4]==='manifest')rtx_response($manifest);else{$parts=rtx_parts($resource['body']);$index=(int)$m[5];if(!isset($parts[$index]))fail(404,'Dependency part not found.');rtx_send_part($manifest,$index,$parts[$index]);}return true;
    }
    if(preg_match('~^return/([a-f0-9]{32})/transport(?:/([a-f0-9]{32})(?:/(parts/([0-9]+)|body))?)?$~D',$suffix,$m)){
        $g=ar_read($r,$m[1],true,false,true);return rtx_private_http($r,$g,$method,'/research/'.$r['slug'].'/return/'.$g['id'].'/transport',$m[2]??null,$m[3]??'',isset($m[4])?(int)$m[4]:null);
    }
    return false;
}
/** Coordinator dispatches this before the session router; scoped reads retain its ownership service. */
function rtx_session_route(string $path,string $method): bool {
    if(preg_match('~^/volunteer/([a-f0-9]{32})/instructions/transport(?:/(manifest|parts/([0-9]+)))?$~D',$path,$guide)){
        if($method!=='GET')fail(405,'Guidance transport is read-only.');$session=vs_read($guide[1]);$body=vo_authenticated_instructions($session);
        $base='/volunteer/'.$session['id'].'/instructions/transport';$manifest=rtx_manifest($body,['schema_version'=>'volunteer-guidance-v4'],$base.'/parts');
        if(($guide[2]??'')==='manifest')rtx_response($manifest);
        elseif(isset($guide[3])&&$guide[3]!==''){$parts=rtx_parts($body);$index=(int)$guide[3];if(!isset($parts[$index]))fail(404,'Guidance part not found.');rtx_send_part($manifest,$index,$parts[$index]);}
        else render('research-transport',['title'=>'Complete private AI guidance','manifest'=>$manifest,'transportBase'=>$base,'private'=>false]);return true;
    }
    if(!preg_match('~^/volunteer/([a-f0-9]{32})/assignments/([a-f0-9]{32})/transport(?:/([a-f0-9]{32})(?:/(parts/([0-9]+)|body))?)?$~D',$path,$m))return false;
    $session=vs_read($m[1]);$assignment=vs_assignment($session,$m[2]);$g=vs_assignment_grant($session,$assignment);$r=research($g['research_id']);
    return rtx_private_http($r,$g,$method,'/volunteer/'.$session['id'].'/assignments/'.$assignment['id'].'/transport',$m[3]??null,$m[4]??'',isset($m[5])?(int)$m[5]:null,$session);
}
function rtx_private_http(array $r,array $g,string $method,string $base,?string $id,string $tail,?int $index,?array $session=null): bool {
    if(!in_array($method,['GET','POST'],true))fail(405,'Use GET or a CSRF-protected POST.');
    if($method==='POST'){
        check_csrf();$operation=text_field($_POST,'operation',1,16);
        try{
            if($operation==='begin'){
                throttle('research-transport-create',60);throttle('research-transport-create-global',1000,3600,true);
                $manifest=null;if(($_POST['direction']??'')==='output'){try{$manifest=json_decode(text_field($_POST,'manifest',2,200000),true,32,JSON_THROW_ON_ERROR);}catch(JsonException){fail(422,'Manifest JSON is incomplete. Supply its complete declared descriptors.');}if(!is_array($manifest))fail(422,'Manifest must be an object.');}
                rtx_response(rtx_begin($r,$g,text_field($_POST,'direction',1,8),$manifest,$session));return true;
            }
            if(!$id)fail(422,'Select an existing private stream.');
            if($operation==='put'){$encoded=text_field($_POST,'body_base64',1,24000);$body=base64_decode($encoded,true);if($body===false)fail(422,'Part is not valid base64; original UTF-8 bytes are required.');$status=rtx_put($r,$g,$id,vs_integer($_POST,'index',0,0,511),$body,$session);}
            elseif($operation==='ack')$status=rtx_ack($r,$g,$id,vs_integer($_POST,'index',0,0,511),text_field($_POST,'sha256',64,64),$session);
            elseif($operation==='ack-resource')$status=rtx_ack_resource($r,$g,$id,text_field($_POST,'sha256',64,64),$session);
            elseif($operation==='finalize')$status=rtx_finalize($r,$g,$id,$session);
            elseif($operation==='delete'){$row=rtx_read($r,$g,$id,$session);query('DELETE FROM research_transports WHERE id=?',[$row['id']]);$status=['deleted'=>true,'notice'=>'Private checkpoint deleted. Research/receipts unchanged.'];}
            else fail(422,'Choose begin, put, ack, finalize or delete.');
            rtx_response($status);
        }catch(HttpError $error){http_response_code($error->status);rtx_response(['ok'=>false,'message'=>$error->getMessage()]);}return true;
    }
    if($id){$row=rtx_read($r,$g,$id,$session);$status=rtx_status($row);
        if(str_starts_with($tail,'parts/')){$part=one('SELECT body FROM research_transport_parts WHERE transport_id=? AND part_no=?',[$id,$index])??fail(404,'Part has not arrived.');rtx_send_part($status['manifest'],$index,$part['body']);}
        elseif($tail==='body'){if($row['state']!=='finalized')fail(409,'Finalize complete acknowledged transport before reading the assembled body.');header('Content-Type: text/plain; charset=utf-8');header('Cache-Control: no-store');echo rtx_body($row);}
        else rtx_response($status);return true;
    }
    $auth=rtx_authorize($r,$g,$session);$binding=rtx_binding($r,$auth['grant']);$streams=all('SELECT id,direction,state,expires_at FROM research_transports WHERE grant_id=? AND expires_at>? ORDER BY created_at DESC',[$g['id'],now()]);
    render('research-transport',['title'=>'Private packet recovery','transportBase'=>$base,'private'=>true,'binding'=>$binding,'streams'=>$streams,'returnPath'=>$session?'/volunteer/'.$session['id'].'/assignments/'.($g['scope']['volunteer_assignment_id']??''):'/research/'.$r['slug'].'/return/'.$g['id'].'/submit']);return true;
}