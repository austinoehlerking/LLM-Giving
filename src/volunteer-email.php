<?php
/** Optional private link delivery. No research/contact row and no plaintext capability storage. */
/** Pending includes an accepted transport whose final state could not be recorded. Never mint a recovery identity. */
class VolunteerEmailPending extends HttpError {
    public function __construct(public string $attempt) {
        parent::__construct(409,'The email outcome is pending or unknown. Retrying checks this same attempt; no new copy will be sent. You can continue without saving.');
    }
}
function vs_email_link(string $id, array $post): array {
    check_csrf();
    $session=vs_read($id);
    vs_assert_active($session);
    $secret=text_field($post,'session_secret',64,64);
    if (!preg_match('/^[a-f0-9]{64}$/D',$secret) || !hash_equals($session['secret_hash'],hash('sha256',$secret))) fail(403,'This session link does not match your current authorization.');
    $raw=$post['email']??null;
    if (!is_string($raw) || preg_match('/[\r\n\x00]/',$raw) || strlen($raw)>254) fail(422,'Enter a valid email address.');
    $email=trim($raw);
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) fail(422,'Enter a valid email address.');
    $attempt=text_field($post,'email_attempt',32,32);
    if (!preg_match('/^[a-f0-9]{32}$/D',$attempt)) fail(422,'Reload the private link form before sending.');
    $prefix=rtrim(env('APP_URL'),'/');
    if (!filter_var($prefix,FILTER_VALIDATE_URL) || !in_array(parse_url($prefix,PHP_URL_SCHEME),['https','http'],true) || parse_url($prefix,PHP_URL_USER)!==null || parse_url($prefix,PHP_URL_QUERY)!==null || parse_url($prefix,PHP_URL_FRAGMENT)!==null || preg_match('/[\r\n]/',$prefix)) fail(503,'Session email is unavailable. Copy your private link instead.');
    // Only hashes enter the rate/idempotency store. Never accept a client-supplied link.
    $key=hash('sha256',env('RATE_LIMIT_NAMESPACE').'|volunteer-email|'.$id.'|'.$attempt);
    $binding=hash('sha256',$key.'|'.strtolower($email));
    // Idempotency must outlive every possible authorized retry; an unlimited session has no safe timeout.
    $retainUntil=vs_unlimited($session)?'9223372036854775807':strtotime($session['expires_at'].' UTC')+86400;
    $state=tx(function() use($key,$binding,$id,$email,$retainUntil) {
        $created=query('INSERT IGNORE INTO rate_limits(bucket,hits,expires) VALUES (?,1,?)',[$key,$retainUntil])->rowCount();
        $record=one('SELECT hits FROM rate_limits WHERE bucket=? FOR UPDATE',[$key]);
        if (!$created) {
            if (!one('SELECT bucket FROM rate_limits WHERE bucket=?',[$binding])) fail(409,'This send attempt belongs to a different address. Use a new send attempt.');
            return (int)$record['hits'];
        }
        throttle('volunteer-email-ip',12);
        throttle('volunteer-email-session-'.$id,3,3600,true);
        throttle('volunteer-email-recipient-'.hash('sha256',strtolower($email)),3,3600,true);
        query('INSERT INTO rate_limits(bucket,hits,expires) VALUES (?,0,?)',[$binding,$retainUntil]);
        return 0;
    });
    if ($state===1) throw new VolunteerEmailPending($attempt);
    if ($state===3) fail(503,'That send failed. Copy your link or make a new explicit send attempt.');
    if ($state===0) {
        $link=$prefix.'/volunteer/'.$session['id'].'/open#'.$secret;
        try {
            mail_deliver($email,'Your private LLM.Giving session link',"You requested a copy of your private LLM.Giving session link.\n\n".$link."\n\nKeep this link private. It transfers your scoped session access. Stop the session from its dashboard and also stop your external AI. This message does not subscribe you to anything.\n",'volunteer-'.uid());
        } catch (Throwable) {
            query('UPDATE rate_limits SET hits=3 WHERE bucket=?',[$key]);
            fail(503,"We couldn't send the email. Try again, or copy your session link instead.");
        }
        try { query('UPDATE rate_limits SET hits=2 WHERE bucket=?',[$key]); }
        catch (Throwable) { throw new VolunteerEmailPending($attempt); }
    }
    return ['accepted'=>true,'message'=>'Email accepted by the configured transport for '.$email.'. Inbox delivery is not guaranteed.','email'=>$email];
}
function vs_email_route(string $path,string $method): bool {
    if (!preg_match('~^/volunteer/([a-f0-9]{32})/email-link$~D',$path,$match)) return false;
    if ($method!=='POST') fail(405,'Use Send my link to request an email.');
    $json=str_contains($_SERVER['HTTP_ACCEPT']??'','application/json');
    $result=null; $error=''; $pendingAttempt=null;
    try { $result=vs_email_link($match[1],$_POST); }
    catch (HttpError $caught) {
        http_response_code($caught->status); $error=$caught->getMessage();
        if ($caught instanceof VolunteerEmailPending) $pendingAttempt=$caught->attempt;
        elseif (!in_array($caught->status,[403,404],true) && is_string($_POST['email_attempt']??null) && preg_match('/^[a-f0-9]{32}$/D',$_POST['email_attempt'])) {
            $attemptKey=hash('sha256',env('RATE_LIMIT_NAMESPACE').'|volunteer-email|'.$match[1].'|'.$_POST['email_attempt']);
            if ((int)(one('SELECT hits FROM rate_limits WHERE bucket=?',[$attemptKey])['hits']??0)===1) {
                $pendingAttempt=$_POST['email_attempt'];
                $error=(new VolunteerEmailPending($pendingAttempt))->getMessage();
                http_response_code(409);
            }
        }
        // An unbound request must not receive private receipt/handoff material.
        if (!$json && in_array($caught->status,[403,404],true)) throw $caught;
    }
    $emailState=$pendingAttempt!==null?'pending':($result!==null?'accepted':'failed');
    $emailAttempt=$pendingAttempt??uid();
    if ($json) {
        header('Content-Type: application/json; charset=UTF-8'); header('Cache-Control: no-store');
        echo json_encode(['ok'=>$result!==null,'state'=>$emailState,'message'=>$result['message']??$error,'next_attempt'=>$emailAttempt],JSON_HEX_TAG|JSON_THROW_ON_ERROR);
    } else {
        $session=vs_read($match[1]); vs_assert_active($session);
        $secret=text_field($_POST,'session_secret',64,64);
        if (!preg_match('/^[a-f0-9]{64}$/D',$secret) || !hash_equals($session['secret_hash'],hash('sha256',$secret))) fail(403,'Session authorization does not match.');
        $created=['id'=>$session['id'],'link'=>rtrim(env('APP_URL'),'/').'/volunteer/'.$session['id'].'/open#'.$secret];
        render('volunteer-created',['title'=>'Private volunteer session','created'=>$created,'emailFeedback'=>$result['message']??$error,'emailAccepted'=>$result!==null,'emailValue'=>is_string($_POST['email']??null)?substr($_POST['email'],0,254):'','emailOpen'=>true,'emailState'=>$emailState,'emailAttempt'=>$emailAttempt]);
    }
    return true;
}
