<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';require __DIR__.'/artifact-fixture.php';
$_SESSION['admin']=1;
if(($argv[1]??'')==='create'){echo json_encode(ra_test_create(),JSON_UNESCAPED_SLASHES);}
elseif(($argv[1]??'')==='cleanup'){$rid=$argv[2]??'';$row=one('SELECT title FROM research WHERE id=?',[$rid]);if(!$row||$row['title']!=='[Artifact QA] Visual artifact evidence and controls')exit(2);purge_request($rid,$rid);echo 'Removed only the disposable visual artifact fixture.'.PHP_EOL;}
