<?php
require __DIR__.'/bootstrap.php';session_start_safe();$_SESSION['admin']=1;
if(($argv[1]??'')==='create'){echo json_encode(create_research(['title'=>'[QA] Happy path forms management fixture','question'=>'A synthetic private management fixture for focused status and native-interface checks only.','category_id'=>1,'terms'=>1]));exit;}
$id=$argv[2]??'';$r=one("SELECT id,title FROM research WHERE id=? AND title LIKE '[QA] Happy path forms%'",[$id])??fail(404,'Owned happy-path fixture not found.');
if(($argv[1]??'')==='lookup'){echo json_encode(array_intersect_key(research($id,false),array_flip(['id','title','question','status','moderation'])));}
elseif($argv[1]==='cleanup')purge_request($id,$id);
else fail(422,'Unknown fixture operation.');
