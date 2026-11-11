<?php
/** Private operator correspondence: no research, activity, email queue or public context rows. */
const CONTACT_LIMITS=['name'=>150,'email'=>254,'message'=>10000];
function contact_operator(): array {
    $email=setting('operator_email','info@llm.giving');
    return ['email'=>$email,'valid'=>mail_address_valid($email),'name'=>setting('operator_name','LLM.Giving'),'country'=>setting('operator_country','United States')];
}
/** Issued tokens are bounded, session-bound and expire before their durable acceptance records. */
function contact_attempt(): string {
    $attempts=array_filter($_SESSION['contact_attempts']??[],fn($expires)=>is_int($expires)&&$expires>time());
    if(isset($_SESSION['contact_pending']) && isset($attempts[$_SESSION['contact_pending']])) {
        $_SESSION['contact_attempts']=$attempts;
        return $_SESSION['contact_pending'];
    }
    unset($_SESSION['contact_pending']);
    $attempts=array_slice($attempts,-7,null,true);
    $token=uid();$attempts[$token]=time()+7200;$_SESSION['contact_attempts']=$attempts;
    return $token;
}
function contact_validate(array $post): array {
    $values=[];$clean=[];$errors=[];
    foreach(CONTACT_LIMITS as $key=>$limit) {
        $raw=$post[$key]??'';
        $values[$key]=is_string($raw)?substr($raw,0,$limit*4+4):'';
        if(!is_string($raw) || strlen($raw)>$limit*4 || str_contains($raw,"\0") || preg_match('//u',$raw)!==1) {
            $errors[$key]='Enter valid text of no more than '.$limit.' characters.';continue;
        }
        $value=preg_replace('/^\s+|\s+$/u','',$raw);
        if(preg_match_all('/./us',$value)>$limit) {$errors[$key]='Use no more than '.$limit.' characters.';continue;}
        if($key!=='message' && preg_match('/[\r\n]/',$raw)) {$errors[$key]='Enter a single line without line breaks.';continue;}
        if($key==='email' && !mail_address_valid($value)) {$errors[$key]='Enter a valid email address.';continue;}
        if($key==='message' && $value==='') {$errors[$key]='Enter a message.';continue;}
        $clean[$key]=$value;
    }
    return ['values'=>$values,'clean'=>$clean,'errors'=>$errors];
}
function contact_attempt_key(string $attempt): string {
    return hash('sha256',env('RATE_LIMIT_NAMESPACE').'|contact-attempt|'.($_SESSION['csrf']??'').'|'.$attempt);
}
/** Pending is written before calling transport; an unknown outcome is never automatically retried. */
function contact_submit(array $post): array {
    $result=contact_validate($post);
    $result+=['status'=>422,'accepted'=>false,'pending'=>false,'error'=>''];
    try {check_csrf();} catch(HttpError) {$result['status']=403;$result['error']='Your form expired. Reload the page and try again.';return $result;}
    $attempt=$post['contact_attempt']??null;
    if(!is_string($attempt) || !preg_match('/^[a-f0-9]{32}$/D',$attempt) || (int)($_SESSION['contact_attempts'][$attempt]??0)<=time()) {
        $result['status']=409;$result['error']='This form expired. Reload the page before sending.';return $result;
    }
    if($result['errors'])return $result;
    $key=contact_attempt_key($attempt);
    $binding=hash('sha256',$key.'|'.json_encode($result['clean'],JSON_THROW_ON_ERROR));
    try {
        $state=tx(function() use($key,$binding) {
            $created=query('INSERT IGNORE INTO rate_limits(bucket,hits,expires) VALUES (?,1,?)',[$key,time()+86400])->rowCount();
            $record=one('SELECT hits FROM rate_limits WHERE bucket=? FOR UPDATE',[$key]);
            if(!$created) {
                if(!one('SELECT bucket FROM rate_limits WHERE bucket=?',[$binding]))fail(409,'This attempt belongs to a different message. Reload the page before sending.');
                return (int)$record['hits'];
            }
            throttle('contact-ip',5);
            throttle('contact-global',100,3600,true);
            query('INSERT INTO rate_limits(bucket,hits,expires) VALUES (?,0,?)',[$binding,time()+86400]);
            return 0;
        });
    } catch(HttpError $error) {$result['status']=$error->status;$result['error']=$error->getMessage();return $result;}
    catch(Throwable) {$result['status']=503;$result['error']="We couldn't send your message. Please try again, or use the email below.";return $result;}
    if($state===1) {
        $_SESSION['contact_pending']=$attempt;
        $result['status']=409;$result['pending']=true;$result['error']='The send outcome is pending or unknown. This attempt will not be sent again. Please use the email below.';return $result;
    }
    if($state===3) {
        $result['status']=503;$result['error']="That send failed. Please use the form to make a new attempt, or use the email below.";return $result;
    }
    if($state===0) {
        $_SESSION['contact_pending']=$attempt;
        $operator=contact_operator();
        $body="LLM.Giving contact message\n\nName: ".($result['clean']['name']?:'(not provided)')."\nEmail: ".$result['clean']['email']."\n\n".$result['clean']['message']."\n";
        try {
            mail_deliver($operator['email'],'LLM.Giving contact message',$body,'contact-'.$attempt,$result['clean']['email']);
        } catch(Throwable) {
            // A failure to record the failed outcome leaves the conservative pending guard in place.
            try {query('UPDATE rate_limits SET hits=3 WHERE bucket=?',[$key]);unset($_SESSION['contact_pending']);}
            catch(Throwable) {$result['status']=409;$result['pending']=true;$result['error']='The send outcome is pending or unknown. This attempt will not be sent again. Please use the email below.';return $result;}
            $result['status']=503;$result['error']="We couldn't send your message. Please try again, or use the email below.";return $result;
        }
        try {query('UPDATE rate_limits SET hits=2 WHERE bucket=?',[$key]);}
        catch(Throwable) {$result['status']=409;$result['pending']=true;$result['error']='The send outcome is pending or unknown. This attempt will not be sent again. Please use the email below.';return $result;}
    }
    unset($_SESSION['contact_pending']);
    $result['status']=200;$result['accepted']=true;
    return $result;
}
function contact_route(string $method): void {
    if(!in_array($method,['GET','POST'],true)) {header('Allow: GET, POST');fail(405,'Use the contact form to send a message.');}
    $data=['values'=>['name'=>'','email'=>'','message'=>''],'errors'=>[],'error'=>'','pending'=>false];
    if($method==='POST') {
        if((int)($_SERVER['CONTENT_LENGTH']??0)>65536) {$data['error']='Your message is too large. Use no more than 10,000 characters.';http_response_code(413);}
        else {
            $data=contact_submit($_POST);http_response_code($data['status']);
            if($data['accepted']) {$_SESSION['contact_sent']=true;redirect('/contact');}
        }
    }
    $data['sent']=$method==='GET'&&!empty($_SESSION['contact_sent']);unset($_SESSION['contact_sent']);
    $data['attempt']=contact_attempt();
    if(isset($_SESSION['contact_pending'])) {
        $data['pending']=true;
        $data['error']='The send outcome is pending or unknown. This attempt will not be sent again. Please use the email below.';
    }
    render('contact',$data+['title'=>'Contact LLM.Giving','operator'=>contact_operator()]);
}
