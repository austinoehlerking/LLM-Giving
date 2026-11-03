<?php
require __DIR__.'/bootstrap.php';
require ROOT.'/src/view.php';
session_start_safe();$_SESSION['admin']=1;$_SERVER['REMOTE_ADDR']='contact-test-'.uid();
$checks=[];$keys=['operator_name','operator_country','operator_email','mail_driver','mail_from'];$saved=[];
foreach($keys as $key)$saved[$key]=one('SELECT value FROM settings WHERE name=?',[$key]);
function contact_ok(bool $value,string $name): void {global $checks;if(!$value)throw new RuntimeException($name);$checks[]=$name;}
function contact_post(array $overrides=[]): array {
    $_POST=array_replace(['csrf'=>$_SESSION['csrf'],'contact_attempt'=>contact_attempt(),'name'=>'Synthetic <visitor>','email'=>'visitor@example.invalid','message'=>"Synthetic contact body — café.\nSecond line."],$overrides);
    return contact_submit($_POST);
}
function contact_mail_count(): int {return count(glob(storage('mail/contact-*.json'))?:[]);}
try {
    set_setting('operator_name','Synthetic operator');set_setting('operator_country','Test country');set_setting('operator_email','operator@example.invalid');set_setting('mail_driver','file');set_setting('mail_from','site@example.invalid');
    $counts=[];foreach(['research','emails','activity','feedback'] as $table) {
        if(one('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]))$counts[$table]=(int)one('SELECT COUNT(*) n FROM '.$table)['n'];
    }
    $before=contact_mail_count();$r=contact_post();$post=$_POST;$sink='contact-'.$post['contact_attempt'].'.json';$mail=json_decode(file_get_contents(storage('mail/'.$sink)),true);
    contact_ok($r['accepted']&&contact_mail_count()===$before+1,'Valid native payload accepted once');
    contact_ok($mail['to']==='operator@example.invalid'&&$mail['subject']==='LLM.Giving contact message','Server owns recipient and fixed subject');
    contact_ok($mail['headers']===['From'=>'site@example.invalid','Reply-To'=>'visitor@example.invalid','MIME-Version'=>'1.0','Content-Type'=>'text/plain; charset=UTF-8','Content-Transfer-Encoding'=>'8bit'],'Configured From, validated visitor Reply-To and UTF-8 plain text');
    contact_ok(str_contains($mail['body'],"café.\nSecond line.")&&str_contains($mail['body'],'Synthetic <visitor>'),'UTF-8 plain body retains text without HTML interpretation');
    $_POST=$post;contact_ok(contact_submit($post)['accepted']&&contact_mail_count()===$before+1,'Duplicate returns acceptance without resend');
    $_POST=$post;$post['message']='Different message';contact_ok(contact_submit($post)['status']===409,'Attempt bound to original content');
    set_setting('operator_email','changed@example.invalid');contact_ok(contact_operator()['email']==='changed@example.invalid','Configured public email changes immediately');
    $r=contact_post(['name'=>'','to'=>'attacker@example.invalid','subject'=>'Injected subject','cc'=>'attacker@example.invalid']);$mail=json_decode(file_get_contents(storage('mail/contact-'.$_POST['contact_attempt'].'.json')),true);
    contact_ok($r['accepted']&&$mail['to']==='changed@example.invalid'&&$mail['subject']==='LLM.Giving contact message'&&!isset($mail['headers']['Cc']),'Settings own delivery; optional name and unsolicited headers never form relay');
    foreach([
        ['email'=>''],['message'=>" \n\t"],['email'=>'invalid'],['name'=>str_repeat('a',151)],['email'=>str_repeat('a',255)],['message'=>str_repeat('m',10001)],
        ['name'=>[]],['email'=>[]],['message'=>[]],['name'=>"X\r\nBcc: target@example.invalid"],['email'=>"visitor@example.invalid\r\nBcc: target@example.invalid"],['email'=>"visitor@example.invalid\n"],
        ['message'=>"abc\0def"],['name'=>"\xC3\x28"],['message'=>"\xC3\x28"]
    ] as $i=>$invalid) {
        $before=contact_mail_count();$r=contact_post($invalid);contact_ok($r['status']===422&&!$r['accepted']&&$r['errors']&&contact_mail_count()===$before,'Invalid payload rejected without delivery #'.$i);
    }
    $r=contact_post(['name'=>str_repeat('é',150)]);contact_ok($r['accepted'],'Unicode character limits match maxlength');
    $r=contact_post(['csrf'=>[]]);contact_ok($r['status']===403,'Nonscalar CSRF rejected');
    $r=contact_post(['csrf'=>'wrong']);contact_ok($r['status']===403,'Incorrect CSRF rejected');
    $r=contact_post(['contact_attempt'=>uid()]);contact_ok($r['status']===409,'Unissued attempt rejected');
    $r=contact_post();$post=$_POST;$before=contact_mail_count();
    query('UPDATE rate_limits SET hits=1 WHERE bucket=?',[contact_attempt_key($post['contact_attempt'])]);
    $_POST=$post;$r=contact_submit($post);contact_ok($r['pending']&&$r['status']===409&&contact_mail_count()===$before,'Pending/unknown outcome cannot resend');
    contact_ok(contact_attempt()===$post['contact_attempt'],'GET retains pending attempt guard');
    unset($_SESSION['contact_pending']);
    $_SERVER['REMOTE_ADDR']='contact-failure-'.uid();set_setting('mail_driver','disabled');
    $r=contact_post();$failed=$_POST;$before=contact_mail_count();contact_ok($r['status']===503&&!$r['accepted']&&$r['values']['message']===$failed['message'],'Failure truthful and preserves fields');
    set_setting('mail_driver','file');$_POST=$failed;contact_ok(contact_submit($failed)['status']===503&&contact_mail_count()===$before,'Failed attempt never silently replayed after recovery');
    foreach([['operator_email',"bad\r\nBcc: bad@example.invalid"],['mail_from','invalid'],['mail_from',"site@example.invalid\r\nX: bad"]] as [$key,$value]) {
        set_setting('operator_email','operator@example.invalid');set_setting('mail_from','site@example.invalid');set_setting($key,$value);
        $r=contact_post();contact_ok($r['status']===503&&!$r['accepted'],'Unsafe configured '.$key.' fails safely');
    }
    set_setting('operator_email','operator@example.invalid');set_setting('mail_from','site@example.invalid');$_SERVER['REMOTE_ADDR']='contact-throttle-'.uid();
    for($i=0;$i<5;$i++)contact_ok(contact_post()['accepted'],'Throttle permits bounded attempt '.$i);
    $before=contact_mail_count();contact_ok(contact_post()['status']===429&&contact_mail_count()===$before,'Sixth attempt throttled');
    foreach($counts as $table=>$count)contact_ok((int)one('SELECT COUNT(*) n FROM '.$table)['n']===$count,'No contact rows in '.$table);
    $fixture=create_research(['title'=>'[QA] Contact transport regression '.uid(),'question'=>'Synthetic existing research mail regression fixture, no factual claims.','category_id'=>1,'terms'=>1,'email'=>'research@example.invalid']);
    try {
        mail_notice($fixture['id'],'Research transport regression','Private regression body');
        $row=one('SELECT * FROM emails WHERE research_id=? ORDER BY id DESC LIMIT 1',[$fixture['id']]);
        contact_ok($row['status']==='sent'&&json_decode(file_get_contents(storage('mail/'.$row['id'].'.json')),true)===['to'=>'research@example.invalid','subject'=>'Research transport regression','body'=>'Private regression body'],'Existing research mail_notice accepted delivery preserves file payload');
        set_setting('mail_driver','disabled');mail_notice($fixture['id'],'Expected failure','Synthetic failure body');
        contact_ok(one('SELECT status FROM emails WHERE research_id=? ORDER BY id DESC LIMIT 1',[$fixture['id']])['status']==='failed','Existing research mail_notice records failure');
    } finally {set_setting('mail_driver','file');purge_request($fixture['id'],$fixture['id']);}
    foreach(['872938', 'volunteer-'.uid()] as $sink) {
        mail_deliver('legacy@example.invalid','Existing caller','Private legacy body',$sink);
        contact_ok(json_decode(file_get_contents(storage('mail/'.$sink.'.json')),true)===['to'=>'legacy@example.invalid','subject'=>'Existing caller','body'=>'Private legacy body'],'Four-argument file payload compatible: '.(str_starts_with($sink,'volunteer-')?'volunteer':'research'));
    }
    $report=['status'=>'PASS','checks'=>$checks,'count'=>count($checks)];file_put_contents(storage('qa/contact-server.json'),json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo json_encode($report)."\n";
} finally {
    foreach($saved as $key=>$row) {if($row)set_setting($key,$row['value']);else query('DELETE FROM settings WHERE name=?',[$key]);}
}
