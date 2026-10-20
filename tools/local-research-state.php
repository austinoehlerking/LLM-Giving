<?php
require __DIR__.'/../tests/bootstrap.php';
if(PHP_SAPI!=='cli' || env('APP_ENV')==='production') exit(1);
$_SESSION['admin']=1;
$action=$argv[1]??'inspect';
if($action==='remove-demos') {
 $rows=all("SELECT id,title FROM research WHERE title LIKE '[Demo]%' AND display_name='Development example'");
 create_backup();
 foreach($rows as $r) purge_request($r['id'],$r['id']);
 echo json_encode(['removed'=>$rows]);
} else {
 echo json_encode(['research'=>all('SELECT id,slug,title,moderation,status FROM research ORDER BY created_at'),'admins'=>all('SELECT email FROM admins')]);
}
