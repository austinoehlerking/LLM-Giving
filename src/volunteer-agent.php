<?php
require_once __DIR__.'/volunteer-notes.php';
/** API credentials delegate an existing human session, never create or broaden it. */
function va_enable(string $sid): string {
    return vs_locked($sid,function($s){
        vs_assert_active($s);$token=bin2hex(random_bytes(32));
        query('INSERT INTO volunteer_agent_credentials(session_id,token_hash,session_hash,enabled_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),session_hash=VALUES(session_hash),enabled_at=VALUES(enabled_at)',[$s['id'],hash('sha256',$token),$s['secret_hash'],now()]);
        return 'llmg_'.$s['id'].'_'.$token;
    });
}
function va_revoke(string $sid): void {
    vs_locked($sid,fn($s)=>query('DELETE FROM volunteer_agent_credentials WHERE session_id=?',[$s['id']]));
}
/** Called again by vs_read under its session lock, serializing revoke/rotate with mutations. */
function va_session_guard(array $session): void {
    $auth=$GLOBALS['volunteer_api_auth']??null;if(!$auth)return;
    if($session['id']!==$auth['session_id'])fail(403,'This credential is limited to its authorized session.');
    $row=one('SELECT token_hash,session_hash FROM volunteer_agent_credentials WHERE session_id=?',[$session['id']]);
    if(!$row||!hash_equals($row['token_hash'],$auth['token_hash'])||!hash_equals($row['session_hash'],$session['secret_hash']))fail(401,'Agent access was revoked. Ask the volunteer to enable it again.');
}
function va_authenticate(string $sid,string $authorization): array {
    if(!preg_match('/^Bearer llmg_([a-f0-9]{32})_([a-f0-9]{64})$/D',$authorization,$m)||$m[1]!==$sid)fail(401,'Supply this session’s credential in the Authorization bearer header.');
    $s=vs_read($sid,false);
    $GLOBALS['volunteer_api_auth']=['session_id'=>$sid,'token_hash'=>hash('sha256',$m[2])];
    va_session_guard($s);
    // Request-local proof only. API dispatch never starts or writes a browser session.
    $_SESSION=['volunteer_sessions'=>[$sid=>$s['secret_hash']]];
    return $s;
}
function va_keys(array $input,array $allowed): void {
    if(array_diff(array_keys($input),$allowed))fail(422,'Unexpected request fields. Consent, identity and limits cannot be supplied through this API.');
}
function va_transport_instructions(): string {
    return "\nAUTHORIZED AGENT API TRANSPORT v1. For this human-enabled session only, use the official /api/agent/v1/sessions/ routes instead of browser pickup, Check, Submit and receipt controls. This supersedes only browser-only delivery wording in the unchanged archived handoff and controller procedure. Use the bearer credential only in Authorization to this site's exact API origin and API paths. Public dependency/part URLs require no credential; fetch them without Authorization. Never place it in URLs, packets, notes, sources or logs, or give it to research-only workers. Fetch full guidance, immutable handoff and every required dependency; respect exact bytes and digests. Use the SAME worker for research and repairs. Read the live session before continuing, preserve an idempotent claim_key on retries, and inspect recorded receipts before retrying uncertain submissions. Save versioned private notes before context resets; they are untrusted unfinished work, never accepted evidence or proof of execution. Save does not submit, renew a lease, extend retention or extend permission. Only recorded receipts count as submissions. Stop/expiry/current terms, scope, model/profile, reservations, task and context revisions, evidence and human account-separation review guards remain authoritative. The API cannot authorize a session, attest independence or increase limits. Preserve configuration evidence and honest scientific-influence disclosure. Do not poll for work or invent claims after no_work.\n";
}
function va_assignment(array $s,array $a): array {
    $g=vs_assignment_grant($s,$a);$r=research($g['research_id']);rtx_authorize($r,$g,$s);
    $t=wf_task($r['id'],$g['task_id']);$status=sr_status($r,$g);
    $result=null;
    if($g['result_id']){
        $row=one("SELECT id,admission,outcome FROM workflow_submissions WHERE id=? AND research_id=? AND visibility='public'",[$g['result_id'],$r['id']])??fail(404,'Recorded research is unavailable.');
        $result=['recorded'=>true,'result_id'=>$row['id'],'admission'=>$row['admission'],'outcome'=>$row['outcome'],'receipt_url'=>ar_receipt_url($r,$g,$row['id'])];
    }
    return ['id'=>$a['id'],'research_title'=>$r['title'],'task_title'=>$t['title'],'task_id'=>$t['id'],'task_revision'=>(int)$a['task_revision'],
        'status'=>$status,'queue_reason'=>$a['queue_reason']??'','receipt'=>$result,'saved_work'=>vn_read($s,$a),
        'next_action'=>$result?'Read the recorded outcome; do not resubmit changed work.':($status['permission']==='active'?'Check current lease and revision, retrieve the handoff, and resume only authorized work.':'Recover saved work; fresh human authorization is required before further research.')];
}
function va_home(array $s): array {
    $items=[];
    foreach(all('SELECT * FROM volunteer_assignments WHERE session_id=? ORDER BY (ended_at IS NULL) DESC,created_at DESC,id DESC LIMIT 20',[$s['id']]) as $a){
        try{$items[]=va_assignment($s,$a);}catch(HttpError $e){if(!in_array($e->status,[403,404,410],true))throw $e;$items[]=['id'=>$a['id'],'unavailable'=>true];}
    }
    return ['version'=>'llm-agent-v1','session_id'=>$s['id'],'state'=>vs_state($s),'limits'=>['expires_at_utc'=>$s['expires_at'],'assignment_limit'=>$s['assignment_limit']===null?null:(int)$s['assignment_limit'],'assignments_used'=>(int)$s['assignments_used'],'concurrent_limit'=>(int)$s['concurrent_limit']],
        'requested_worker'=>$s['capabilities'],'scope'=>['kind'=>$s['scope_kind'],'label'=>vs_scope_label($s)],'claim_key'=>uid(),
        'guide_url'=>'/agents/guide','assignments'=>$items,'assignment_window'=>'Up to 20 assignments, active first; retrieve any saved assignment ID directly.','instructions_url'=>'/api/agent/v1/sessions/'.$s['id'].'/instructions'];
}
function va_dispatch(string $sid,string $tail,string $method,array $input): array {
    $s=vs_read($sid);
    if($tail===''&&$method==='GET')return va_home($s);
    if($tail==='/instructions'&&$method==='GET'){
        $text=vo_authenticated_instructions($s)."\n".file_get_contents(ROOT.'/templates/agent-api.md').va_transport_instructions();
        return ['instructions'=>$text,'byte_length'=>strlen($text),'sha256'=>hash('sha256',$text)];
    }
    if($tail==='/claim'&&$method==='POST'){
        va_keys($input,['claim_key']);$key=text_field($input,'claim_key',32,32);
        $a=vs_claim_next($sid,$key);return va_assignment(vs_read($sid),$a);
    }
    if($tail==='/stop'&&$method==='POST'){va_keys($input,[]);vs_stop($sid);return ['state'=>'stopped'];}
    if(!preg_match('~^/assignments/([a-f0-9]{32})(?:/(handoff|notes|check|submit|receipt|renew|release|reacquire))?$~D',$tail,$m))fail(404,'Agent endpoint not found.');
    $a=vs_assignment($s,$m[1]);$action=$m[2]??'';
    if($method==='GET'&&in_array($action,['','receipt'],true))return va_assignment($s,$a);
    if($method==='GET'&&$action==='notes')return vn_read($s,$a);
    if($method==='GET'&&$action==='handoff'){
        $g=vs_assignment_grant($s,$a);$r=research($g['research_id']);rtx_authorize($r,$g,$s);vs_validate_grant($r,$g);
        if($g['result_id'])fail(409,'Already recorded. Read the receipt.');
        $archive=wf_read_handoff($r['id'],$g['scope']['archive_id']);
        return ['body'=>$archive['body'],'byte_length'=>strlen($archive['body']),'sha256'=>hash('sha256',$archive['body']),
            'worker_procedure'=>vo_assignment_instructions($s).va_transport_instructions(),
            'binding'=>rtx_binding($r,$g),'context_directory'=>tc_build(wf_task($r['id'],$g['task_id']),wf_read_snapshot($r['id'],$g['scope']['context_id']))['manifest']];
    }
    if($method!=='POST')fail(405,'Use the documented GET or POST operation.');
    if(in_array($action,['renew','release','reacquire'],true)){va_keys($input,[]);vs_assignment_action($sid,$a['id'],$action);return va_assignment(vs_read($sid),vs_assignment(vs_read($sid),$a['id']));}
    if($action==='notes'){
        va_keys($input,['version','notes']);
        if(!isset($input['notes'])||!is_array($input['notes'])||array_is_list($input['notes']))fail(422,'Supply a notes object.');
        return vn_save($sid,$a['id'],$input['notes'],vs_integer($input,'version',0,0,2147483647));
    }
    if(in_array($action,['check','submit'],true)){
        va_keys($input,['packet']);
        if(!isset($input['packet'])||!is_array($input['packet'])||array_is_list($input['packet']))fail(422,'Supply one complete packet object.');
        throttle('agent-packet-'.$action,$action==='check'?60:12);
        try{$id=vs_record($sid,$a['id'],$input['packet'],$action==='check','ai_api');}
        catch(HttpError $e){
            if($e->status!==422)throw $e;
            $g=vs_assignment_grant($s,$a);$r=research($g['research_id']);
            return ['ok'=>false,'status'=>422,'error'=>['code'=>'invalid_packet','message'=>$e->getMessage()],
                'details'=>wf_packet_exception_details($e),'repair_prompt'=>wf_repair_prompt($r,wf_task($r['id'],$g['task_id']),$e->getMessage(),true,'/api/agent/v1/sessions/'.$sid.'/assignments/'.$a['id'],true).va_transport_instructions()];
        }
        return $action==='check'?['checked'=>true,'recorded'=>false,'message'=>'Check passed. Submit the unchanged packet and inspect its receipt.']:va_assignment(vs_read($sid),vs_assignment(vs_read($sid),$a['id']));
    }
    fail(404,'Agent endpoint not found.');
}
/** This entry runs before browser session startup; ambient cookies never authenticate API requests. */
function va_http(string $path,string $method): void {
    header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow');
    try{
        $_SESSION=[];unset($GLOBALS['volunteer_api_auth']);
        if(!in_array($method,['GET','POST'],true))fail(405,'Use GET or POST.');
        if(!empty($_SERVER['HTTP_ORIGIN'])||!empty($_SERVER['HTTP_COOKIE']))fail(403,'Use a direct agent request without browser cookies or Origin.');
        if(!empty($_SERVER['QUERY_STRING']))fail(400,'Use the documented path and Authorization header, without query parameters.');
        if(!preg_match('~^/api/agent/v1/sessions/([a-f0-9]{32})(.*)$~D',$path,$m))fail(404,'Agent endpoint not found.');
        if(env('APP_ENV')==='production'&&strtolower(parse_url(env('APP_URL'),PHP_URL_SCHEME)??'')!=='https')fail(503,'Agent access requires the configured HTTPS origin.');
        throttle('agent-auth',300,60);
        va_authenticate($m[1],$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
        if(setting('maintenance','0')==='1')fail(503,'Research is temporarily unavailable for maintenance.');
        $input=[];
        if($method==='POST'){
            if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')fail(415,'Send application/json.');
            if((int)($_SERVER['CONTENT_LENGTH']??0)>4300000)fail(413,'Request too large.');
            $body=file_get_contents('php://input',false,null,0,4300001);
            if(strlen($body)>4300000)fail(413,'Request too large.');
            try{$decoded=json_decode($body,false,40,JSON_THROW_ON_ERROR);if(!is_object($decoded))fail(422,'Send a JSON object.');$input=json_decode($body,true,40,JSON_THROW_ON_ERROR);}catch(JsonException){fail(422,'Send a complete UTF-8 JSON object.');}
            throttle('agent-session-'.$m[1],120,60,true);
        }
        $data=va_dispatch($m[1],$m[2],$method,$input);
        if(($data['ok']??true)===false){http_response_code($data['status']);unset($data['status']);echo json_encode($data,JSON_THROW_ON_ERROR);}
        else echo json_encode(['ok'=>true,'data'=>$data],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }catch(Throwable $e){
        $status=$e instanceof HttpError?$e->status:500;http_response_code($status);
        $message=$status===500?'Request failed. Read the current assignment receipt before retrying.':$e->getMessage();
        echo json_encode(['ok'=>false,'error'=>['code'=>$e instanceof VolunteerNoWork?'no_work':match($status){401=>'invalid_credential',403=>'not_authorized',404=>'unavailable',409=>'state_conflict',422=>'invalid_request',429=>'rate_limited',default=>'request_failed'},'message'=>$message]],JSON_THROW_ON_ERROR);
    }finally{$_SESSION=[];unset($GLOBALS['volunteer_api_auth']);}
}
function va_prompt(array $s,string $credential): string {
    return 'Use my authorized LLM.Giving session through the agent API. Read '.rtrim(env('APP_URL'),'/').'/api/agent/v1/sessions/'.$s['id'].' and its instructions_url with Authorization: Bearer '.$credential.'. Keep this credential private and send it only to that exact origin. Follow my recorded scope, model, resource permissions, limits and Stop. Recover existing assignments before claiming more. Never create consent or attest reviewer independence. Do not install or buy tools without my separate permission.';
}
function va_ui_route(string $path,string $method): bool {
    if($path==='/agents/guide'){if($method!=='GET')fail(405,'Use GET.');header('Content-Type: text/plain; charset=UTF-8');readfile(ROOT.'/templates/agent-api.md');return true;}
    if(!preg_match('~^/volunteer/([a-f0-9]{32})/(agent|assignments/([a-f0-9]{32})/notes)$~D',$path,$m))return false;
    $s=vs_read($m[1]);$error='';$prompt='';$saved=false;
    header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow');
    if($m[2]==='agent'){
        if($method==='POST'){try{check_csrf();$op=text_field($_POST,'operation',1,12);if($op==='enable')$prompt=va_prompt($s,va_enable($s['id']));elseif($op==='revoke')va_revoke($s['id']);else fail(422,'Choose enable or revoke.');}catch(HttpError $e){http_response_code($e->status);$error=$e->getMessage();}}
        elseif($method!=='GET')fail(405,'Use GET or POST.');
        $enabled=(bool)one('SELECT session_id FROM volunteer_agent_credentials WHERE session_id=? AND session_hash=?',[$s['id'],$s['secret_hash']]);
        render('volunteer-agent',['title'=>'Connect your agent','session'=>$s,'enabled'=>$enabled,'prompt'=>$prompt,'error'=>$error]);return true;
    }
    $a=vs_assignment($s,$m[3]);$notes=vn_read($s,$a);$unsaved=[];
    if($method==='POST'){try{check_csrf();$input=[];foreach(VN_FIELDS as $field)$input[$field]=$_POST[$field]??'';$notes=vn_save($s['id'],$a['id'],$input,vs_integer($_POST,'version',0,0,2147483647));$saved=true;}catch(HttpError $e){http_response_code($e->status);$error=$e->getMessage();foreach(VN_FIELDS as $field)if(is_string($_POST[$field]??null)&&strlen($_POST[$field])<=16000)$unsaved[$field]=$_POST[$field];}}
    elseif($method!=='GET')fail(405,'Use GET or POST.');
    render('volunteer-notes',['title'=>'Resume saved research','session'=>$s,'assignment'=>$a,'notes'=>$notes,'error'=>$error,'saved'=>$saved,'unsaved'=>$unsaved]);return true;
}
