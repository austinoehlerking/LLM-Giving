<?php
class HttpError extends RuntimeException { public function __construct(public int $status,string $message) { parent::__construct($message); } }
function fail(int $code,string $message): never { throw new HttpError($code,$message); }
function e(mixed $s): string { return htmlspecialchars((string)($s??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function uid(): string { return bin2hex(random_bytes(16)); }
function now(): string { return gmdate('Y-m-d H:i:s'); }
function headers_secure(): void {
    $google = google_analytics_request();
    $scripts = $google ? ' https://www.googletagmanager.com' : '';
    $collection = $google ? ' https://www.google-analytics.com https://region1.google-analytics.com' : '';
    header("Content-Security-Policy: default-src 'self'; script-src 'self'".$scripts."; style-src 'self'; img-src 'self' data:".$collection."; connect-src 'self'".$collection."; frame-ancestors 'none'; base-uri 'none'; form-action 'self'; object-src 'none'");
    header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()'); header('X-Frame-Options: DENY');
    header('Cache-Control: no-store');
    if(env('APP_ENV')==='production') header('Strict-Transport-Security: max-age=31536000');
}
function session_start_safe(): void {
    session_save_path(storage('sessions'));
    ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
    session_name('llmgiving');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>env('APP_ENV')==='production','httponly'=>true,'samesite'=>'Lax']);
    session_start();
    if (isset($_SESSION['last_seen']) && time()-$_SESSION['last_seen'] > (int)env('SESSION_MINUTES','30')*60) { $_SESSION=[]; session_regenerate_id(true); }
    $_SESSION['last_seen']=time(); $_SESSION['csrf']??=bin2hex(random_bytes(32));
}
function csrf(): string { return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">'; }
function check_csrf(): void {
    $expected = $_SESSION['csrf'] ?? null;
    $provided = $_POST['csrf'] ?? null;
    if (!is_string($expected) || $expected === '' || !is_string($provided) || !hash_equals($expected, $provided)) {
        fail(403, 'Your form expired. Reload the page and try again.');
    }
}
function admin(): bool {
    if(!isset($_SESSION['admin'])) return false;
    if(PHP_SAPI==='cli') return true;
    $a=one('SELECT password_hash FROM admins WHERE id=?',[$_SESSION['admin']]);
    return $a && hash_equals(hash('sha256',$a['password_hash']),$_SESSION['admin_signature']??'');
}
function require_admin(): void { if(!admin()) fail(403,'Administrator access required.'); }
function require_manager(string $rid): void {
    $grant=$_SESSION['manage'][$rid]??null;
    $credential=one('SELECT token_hash FROM credentials WHERE research_id=?',[$rid]);
    if(!$grant || !$credential || $grant['expires']<time() || !hash_equals($credential['token_hash'],$grant['hash'])) fail(403,'Open your private management link to continue.');
}
function redeem(string $rid,string $token): void {
    throttle('capability',20);
    $c=one('SELECT token_hash FROM credentials WHERE research_id=?',[$rid]);
    if(!$c || !hash_equals($c['token_hash'],hash('sha256',$token))) fail(403,'This management link is invalid or has been revoked.');
    session_regenerate_id(true);
    $_SESSION['manage'][$rid]=['hash'=>$c['token_hash'],'expires'=>time()+1800];
}
function rotate_token(string $rid): string {
    $token=bin2hex(random_bytes(32));
    query('INSERT INTO credentials(research_id,token_hash) VALUES (?,?) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash)',[$rid,hash('sha256',$token)]);
    return env('APP_URL','http://127.0.0.1:8086').'/manage/'.$rid.'#'.$token;
}
function throttle(string $action,int $limit,int $seconds=3600,bool $accountWide=false): void {
    $key=hash('sha256',env('RATE_LIMIT_NAMESPACE').'|'.$action.'|'.($accountWide?'account':($_SERVER['REMOTE_ADDR']??'cli')).'|'.intdiv(time(),$seconds));
    query('INSERT INTO rate_limits(bucket,hits,expires) VALUES (?,1,?) ON DUPLICATE KEY UPDATE hits=hits+1',[$key,time()+$seconds]);
    if((int)one('SELECT hits FROM rate_limits WHERE bucket=?',[$key])['hits']>$limit) fail(429,'Too many attempts. Please try again later.');
}
function safe_url(string $url): string {
    $url=trim($url);
    if($url==='' ) return '';
    if(strlen($url)>2000 || !filter_var($url,FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($url,PHP_URL_SCHEME)??''),['https','http'],true) || parse_url($url,PHP_URL_USER)!==null) fail(422,'Links must be full HTTP or HTTPS URLs without credentials.');
    return $url;
}
function text_field(array $data,string $key,int $min=0,int $max=0): string {
    $value=$data[$key]??'';
    if(!is_scalar($value) && $value!==null) fail(422,'Invalid field: '.$key);
    $value=trim((string)$value); $max=$max ?: (int)env('MAX_TEXT','100000');
    if(strlen($value)<$min || strlen($value)>$max || str_contains($value,"\0") || preg_match('//u',$value)!==1) fail(422,'Check '.$key.': expected '.$min.'–'.$max.' characters.');
    return $value;
}
function suspicious(string $s): bool {
    return (bool)preg_match('/ignore.{0,40}(instructions|previous)|system\s*:?\s*role|environment.variable|exfiltrat|private.key|base64|<script|javascript:|execute.{0,20}(command|shell)/is',$s);
}
/** Shared configured transport. Callers provide a server-generated private sink name. */
function mail_deliver(string $to,string $subject,string $body,string $sink): void {
    if (strlen($to)>254 || preg_match('/[\r\n\x00]/',$to) || !filter_var($to,FILTER_VALIDATE_EMAIL) || strlen($subject)>180 || preg_match('/[\r\n\x00]/',$subject) || !preg_match('/^(?:[0-9]+|volunteer-[a-f0-9]{32})$/D',$sink)) throw new RuntimeException('Mail transport unavailable.');
    $driver=setting('mail_driver',env('MAIL_DRIVER','file'));
    if ($driver==='file') {
        if (@file_put_contents(storage('mail/'.$sink.'.json'),json_encode(['to'=>$to,'subject'=>$subject,'body'=>$body],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('Mail transport unavailable.');
    } elseif ($driver==='mail') {
        $from=setting('mail_from',env('MAIL_FROM'));
        if (preg_match('/[\r\n\x00]/',$from) || !filter_var($from,FILTER_VALIDATE_EMAIL) || !@mail($to,$subject,$body,['From'=>$from])) throw new RuntimeException('Mail transport unavailable.');
    } else throw new RuntimeException('Mail transport unavailable.');
}
function mail_notice(string $rid,string $subject,string $body): void {
    $r=one('SELECT email FROM research WHERE id=?',[$rid]); if(empty($r['email'])) return;
    $id=insert('emails',['research_id'=>$rid,'subject'=>$subject,'status'=>'queued','created_at'=>now()]);
    try {
        mail_deliver($r['email'],$subject,$body,$id);
        query("UPDATE emails SET status='sent' WHERE id=?",[$id]);
    } catch(Throwable) { query("UPDATE emails SET status='failed' WHERE id=?",[$id]); }
}
