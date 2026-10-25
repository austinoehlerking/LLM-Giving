<?php
require __DIR__.'/bootstrap.php';
$action=$argv[1]??'';
if($action==='counts') echo json_encode(['sessions'=>(int)one('SELECT COUNT(*) n FROM volunteer_sessions')['n'],'assignments'=>(int)one('SELECT COUNT(*) n FROM volunteer_assignments')['n'],'reservations'=>(int)one('SELECT COUNT(*) n FROM task_reservations')['n'],'archives'=>(int)one('SELECT COUNT(*) n FROM workflow_handoffs')['n'],'mail'=>count(glob(storage('mail/volunteer-*.json'))?:[])]);
elseif($action==='driver'){if(!in_array($argv[2]??'', ['file','disabled'],true))throw new RuntimeException('Invalid local test driver');set_setting('mail_driver',$argv[2]);}
elseif($action==='inspect'){
    $input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$id=$input['id'];$session=one('SELECT * FROM volunteer_sessions WHERE id=?',[$id]);
    if(!$session||!hash_equals($session['secret_hash'],hash('sha256',$input['secret'])))throw new RuntimeException('Invalid fixture capability');
    $_SESSION['volunteer_sessions'][$id]=$session['secret_hash'];
    echo json_encode(['handoff'=>vo_handoff($id,rtrim(env('APP_URL'),'/').'/volunteer/'.$id.'/open#'.$input['secret']),'assignments_used'=>(int)$session['assignments_used']],JSON_THROW_ON_ERROR);
}elseif($action==='cleanup'){
    $ids=json_decode(stream_get_contents(STDIN),true,8,JSON_THROW_ON_ERROR);foreach($ids as $id){if(!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id))throw new RuntimeException('Invalid fixture');foreach(glob(storage('mail/volunteer-*.json'))?:[] as $file){$message=json_decode(file_get_contents($file),true);if(is_string($message['body']??null)&&str_contains($message['body'],'/volunteer/'.$id.'/open#'))unlink($file);}
        query('DELETE FROM volunteer_sessions WHERE id=?',[$id]);}
}
else throw new RuntimeException('Choose local wizard fixture operation.');
