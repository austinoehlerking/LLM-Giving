<?php
require __DIR__.'/bootstrap.php';
$_SESSION['admin']=1;
$action=$argv[1]??'';
if($action==='worker'){
    echo json_encode(['claimed'=>lp_dispatch_claim($argv[2]??null)!==null],JSON_THROW_ON_ERROR);
    exit;
}
if($action!=='race')throw new RuntimeException('Unknown planner race operation.');

function assert_race(bool $ok,string $message): void {
    if(!$ok)throw new RuntimeException($message);
}

lp_enable();
$rid=create_research(['title'=>'Synthetic dashboard and legacy planner race','question'=>'Compare documented public library attendance measures as an isolated workflow fixture.','category_id'=>1,'terms'=>1])['id'];
moderate('request',$rid,'Approved','Approve a disposable row for the planner race check.');
lp_dispatch_start();
$runId=lp_dispatch_heartbeat(true)['run_id'];
assert_race(is_string($runId),'The admin run must contain the approved fixture.');
$owner=(int)one('SELECT CONNECTION_ID() id')['id'];
$worker=null;
try {
    db()->beginTransaction();
    one('SELECT id FROM research WHERE id=? FOR UPDATE',[$rid]);
    $worker=proc_open([PHP_BINARY,__FILE__,'worker',$runId],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,ROOT,null,['bypass_shell'=>true]);
    assert_race(is_resource($worker),'Could not start the second planner connection.');
    fclose($pipes[0]);
    $deadline=microtime(true)+8;
    $waiting=false;
    do{
        foreach(all('SHOW FULL PROCESSLIST') as $row){
            $info=$row['Info']??'';
            if((int)$row['Id']!==$owner&&(str_contains($info,'SELECT * FROM research WHERE id=')&&str_contains($info,'FOR UPDATE'))){$waiting=true;break;}
        }
        if(!$waiting)usleep(10000);
    }while(!$waiting&&microtime(true)<$deadline);
    assert_race($waiting,'The scoped planner did not reach the held research lock.');
    $legacy=lp_claim([$rid]);
    assert_race($legacy!==null,'The legacy planner did not claim the fixture.');
    db()->commit();
    $message=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    $code=proc_close($worker);$worker=null;
    assert_race($code===0&&json_decode($message,true,16,JSON_THROW_ON_ERROR)['claimed']===false,'The paused dashboard claim replaced the legacy lease: '.$error);
    $row=one('SELECT lease_hash FROM local_planner_jobs WHERE research_id=?',[$rid]);
    assert_race(hash_equals($row['lease_hash'],hash('sha256',$legacy['lease'])),'The first runner no longer owns the exact lease.');
    assert_race(lp_dispatch_heartbeat(true)['run_id']===$runId,'The admin run closed while the legacy lease was active.');
    echo "5 planner dispatch concurrency checks passed.\n";
}finally{
    if(db()->inTransaction())db()->rollBack();
    if(is_resource($worker)){
        proc_terminate($worker);
        foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);
        proc_close($worker);
    }
    purge_request($rid,$rid);
    query("DELETE FROM settings WHERE name IN ('local_planner_run','local_planner_connection')");
    set_setting('local_planner_enabled','0');
}
