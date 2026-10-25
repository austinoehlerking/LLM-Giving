<?php
require __DIR__.'/bootstrap.php';
$count=0; $ids=[]; $beforeFiles=glob(storage('mail/volunteer-*.json'))?:[];
$original=$_SESSION??[]; $_SESSION['csrf']=bin2hex(random_bytes(32));
$driver=setting('mail_driver',env('MAIL_DRIVER','file')); set_setting('mail_driver','file');
function ve_check(string $name,bool $pass): void { global $count; if(!$pass) throw new RuntimeException($name); $count++; }
function ve_deny(string $name,int $status,callable $fn): void { try{$fn();}catch(HttpError $e){ve_check($name,$e->status===$status);return;}throw new RuntimeException($name.' allowed'); }
function ve_create(): array {
    global $ids;
    $created=vs_create(['scope_kind'=>'all','session_preset'=>'standard','authorize'=>1,'terms'=>1,'authorized_resources'=>1,'policy_version'=>terms_version(),'human_level'=>'none','human_details'=>'']);
    $ids[]=$created['id'];return $created;
}
function ve_post(array $c,string $email='wizard-synthetic@example.invalid'): array { return ['csrf'=>$_SESSION['csrf'],'session_secret'=>explode('#',$c['link'])[1],'email'=>$email,'email_attempt'=>uid(),'url'=>'https://attacker.invalid/ignored']; }
try {
    $_SERVER['REMOTE_ADDR']='127.0.0.71';
    $c=ve_create();$post=ve_post($c);
    $counts=[one('SELECT COUNT(*) n FROM research')['n'],one('SELECT COUNT(*) n FROM emails')['n'],one('SELECT COUNT(*) n FROM task_reservations')['n'],one('SELECT COUNT(*) n FROM workflow_handoffs')['n']];
    $_POST=$post;
    $result=vs_email_link($c['id'],$post);
    ve_check('Configured private file transport accepts the requested synthetic address', $result['accepted']&&str_contains($result['message'],$post['email'])&&str_contains($result['message'],'not guaranteed'));
    $new=array_values(array_diff(glob(storage('mail/volunteer-*.json'))?:[],$beforeFiles));$message=json_decode(file_get_contents($new[0]),true);
    ve_check('Email contains exact canonical existing fragment link and no arbitrary client URL',$message['to']===$post['email']&&str_contains($message['body'],$c['link'])&&!str_contains($message['body'],'attacker.invalid'));
    ve_check('Transactional email has no AI handoff or subscription',!str_contains($message['body'],'PRIVATE VOLUNTEER SESSION SUPPLEMENT')&&str_contains($message['body'],'does not subscribe'));
    vs_email_link($c['id'],$post);
    ve_check('Identical accepted attempt recovers without duplicate send',count(array_diff(glob(storage('mail/volunteer-*.json'))?:[],$beforeFiles))===1);
    ve_deny('Attempt cannot be rebound to another recipient',409,fn()=>vs_email_link($c['id'],array_replace($post,['email'=>'different@example.invalid'])));
    ve_deny('CSRF mismatch rejected',403,function()use($c,$post){$_POST=array_replace($post,['csrf'=>'wrong']);vs_email_link($c['id'],$post);});$_POST=$post;
    ve_deny('Wrong current capability rejected',403,fn()=>vs_email_link($c['id'],array_replace($post,['session_secret'=>str_repeat('0',64)])));
    $grant=$_SESSION['volunteer_sessions'][$c['id']];unset($_SESSION['volunteer_sessions'][$c['id']]);
    ve_deny('Valid raw secret cannot bypass authorized browser session',403,fn()=>vs_email_link($c['id'],$post));$_SESSION['volunteer_sessions'][$c['id']]=$grant;
    foreach(['','invalid',"synthetic@example.invalid\r\nBcc: other@example.invalid",str_repeat('x',255).'@example.invalid'] as $index=>$invalid)ve_deny('Invalid/injection/oversized recipient rejected '.$index,422,fn()=>vs_email_link($c['id'],array_replace($post,['email'=>$invalid])));
    ve_deny('Array recipient rejected',422,fn()=>vs_email_link($c['id'],array_replace($post,['email'=>['bad']])));
    ve_deny('Malformed attempt rejected',422,fn()=>vs_email_link($c['id'],array_replace($post,['email_attempt'=>'bad'])));
    ve_deny('GET never sends',405,fn()=>vs_email_route('/volunteer/'.$c['id'].'/email-link','GET'));
    query('UPDATE volunteer_sessions SET stopped_at=? WHERE id=?',[now(),$c['id']]);
    ve_deny('Stopped session cannot send',409,fn()=>vs_email_link($c['id'],$post));query('UPDATE volunteer_sessions SET stopped_at=NULL WHERE id=?',[$c['id']]);
    $session=vs_read($c['id']);query('UPDATE volunteer_sessions SET expires_at=? WHERE id=?',[gmdate('Y-m-d H:i:s',time()-1),$c['id']]);
    ve_deny('Expired session cannot send',409,fn()=>vs_email_link($c['id'],$post));query('UPDATE volunteer_sessions SET expires_at=? WHERE id=?',[$session['expires_at'],$c['id']]);
    $consent=$session['consent'];$consent['terms_version']='obsolete';query('UPDATE volunteer_sessions SET consent=? WHERE id=?',[json_encode($consent),$c['id']]);
    ve_deny('Changed terms cannot send',409,fn()=>vs_email_link($c['id'],$post));query('UPDATE volunteer_sessions SET consent=? WHERE id=?',[json_encode($session['consent']),$c['id']]);
    $_SERVER['REMOTE_ADDR']='127.0.0.72';$failed=ve_create();$failPost=ve_post($failed,'failed-synthetic@example.invalid');$_POST=$failPost;set_setting('mail_driver','disabled');
    ve_deny('Disabled transport reports failure',503,fn()=>vs_email_link($failed['id'],$failPost));set_setting('mail_driver','file');
    $mailCount=count(glob(storage('mail/volunteer-*.json'))?:[]);
    ve_deny('Failed attempt cannot be misreported as accepted or automatically resent',503,fn()=>vs_email_link($failed['id'],$failPost));
    ve_check('Failed attempt creates no later message',count(glob(storage('mail/volunteer-*.json'))?:[])===$mailCount);
    $_SERVER['REMOTE_ADDR']='127.0.0.73';$limited=ve_create();$limitedPost=ve_post($limited,'limited-synthetic@example.invalid');$_POST=$limitedPost;
    for($i=0;$i<3;$i++){ $p=array_replace($limitedPost,['email_attempt'=>uid()]);vs_email_link($limited['id'],$p); }
    ve_deny('Per-session explicit resend limit enforced',429,fn()=>vs_email_link($limited['id'],array_replace($limitedPost,['email_attempt'=>uid(),'email'=>'new-address@example.invalid'])));
    $_SERVER['REMOTE_ADDR']='127.0.0.74';$other=ve_create();$otherPost=ve_post($other,'limited-synthetic@example.invalid');$_POST=$otherPost;
    ve_deny('Recipient limit spans sessions and IPs',429,fn()=>vs_email_link($other['id'],$otherPost));
    $_SERVER['REMOTE_ADDR']='127.0.0.75';$ip=ve_create();$ipPost=ve_post($ip,'ip-bound@example.invalid');$_POST=$ipPost;
    $ipBucket=hash('sha256',env('RATE_LIMIT_NAMESPACE').'|volunteer-email-ip|'.$_SERVER['REMOTE_ADDR'].'|'.intdiv(time(),3600));
    query('INSERT INTO rate_limits(bucket,hits,expires) VALUES (?,12,?) ON DUPLICATE KEY UPDATE hits=12',[$ipBucket,time()+3600]);
    ve_deny('IP send limit enforced before transport',429,fn()=>vs_email_link($ip['id'],$ipPost));
    $_SERVER['REMOTE_ADDR']='127.0.0.76';$pending=ve_create();$pendingPost=ve_post($pending,'pending-synthetic@example.invalid');$_POST=$pendingPost;
    $pendingKey=hash('sha256',env('RATE_LIMIT_NAMESPACE').'|volunteer-email|'.$pending['id'].'|'.$pendingPost['email_attempt']);
    $pendingBinding=hash('sha256',$pendingKey.'|'.strtolower($pendingPost['email']));
    query('INSERT INTO rate_limits(bucket,hits,expires) VALUES (?,1,?),(?,0,?)',[$pendingKey,time()+86400,$pendingBinding,time()+86400]);
    ve_deny('Concurrent pending attempt cannot duplicate or claim acceptance',409,fn()=>vs_email_link($pending['id'],$pendingPost));
    $countsAfter=[one('SELECT COUNT(*) n FROM research')['n'],one('SELECT COUNT(*) n FROM emails')['n'],one('SELECT COUNT(*) n FROM task_reservations')['n'],one('SELECT COUNT(*) n FROM workflow_handoffs')['n']];
    ve_check('Volunteer email creates no research record, research-email audit, reservation or archive',$countsAfter===$counts);
    ve_check('Rate and attempt store contains only hashed keys',array_reduce(all('SELECT bucket FROM rate_limits'),fn($ok,$row)=>$ok&&(bool)preg_match('/^[a-f0-9]{64}$/D',$row['bucket']),true));
    echo $count." volunteer email/transport authorization checks passed.\n";
} finally {
    foreach($ids as $id)query('DELETE FROM volunteer_sessions WHERE id=?',[$id]);
    foreach(array_diff(glob(storage('mail/volunteer-*.json'))?:[],$beforeFiles) as $file)unlink($file);
    set_setting('mail_driver',$driver);$_SESSION=$original;
}
