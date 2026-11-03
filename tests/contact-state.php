<?php
/** Local-only synthetic contact browser fixture. Never emits configuration secrets. */
require __DIR__.'/bootstrap.php';
$action=$argv[1]??'';
if($action==='configure') {
    $email=$argv[2]??'operator@example.invalid';$driver=$argv[3]??'file';
    if(!preg_match('/^[a-z-]+@example\.invalid$/D',$email)||!in_array($driver,['file','disabled'],true))throw new RuntimeException('Synthetic file fixture only.');
    set_setting('operator_name','Synthetic operator');set_setting('operator_country','Test country');set_setting('operator_email',$email);set_setting('mail_from','site@example.invalid');set_setting('mail_driver',$driver);
}
$selected=null;
if($action==='inspect'&&isset($argv[2])) {
    if(!preg_match('/^[a-f0-9]{32}$/D',$argv[2]))throw new RuntimeException('Invalid synthetic attempt.');
    $file=storage('mail/contact-'.$argv[2].'.json');
    if(is_file($file))$selected=json_decode(file_get_contents($file),true,16,JSON_THROW_ON_ERROR);
}
$messages=[];foreach(glob(storage('mail/contact-*.json'))?:[] as $file)$messages[]=json_decode(file_get_contents($file),true,16,JSON_THROW_ON_ERROR);
echo json_encode(['driver'=>setting('mail_driver',env('MAIL_DRIVER')),'from'=>setting('mail_from',env('MAIL_FROM')),'operator'=>contact_operator(),'count'=>count($messages),'last'=>$selected??($messages?end($messages):null)],JSON_THROW_ON_ERROR);
