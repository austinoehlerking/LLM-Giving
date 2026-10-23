<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';
$_SESSION['admin']=1;$action=$argv[1]??'';
if($action==='create'){
    $out=[];for($i=0;$i<2;$i++){$c=create_research(['title'=>'[QA] Reservation race '.uid(),'question'=>'Synthetic concurrent reservation fixture only.','category_id'=>1,'terms'=>1]);moderate('request',$c['id'],'Approved','Disposable race fixture.');$out[]=['rid'=>$c['id'],'tid'=>wf_tasks($c['id'])[0]['id']];}echo json_encode($out);
}elseif($action==='cleanup'){
    foreach(array_slice($argv,2) as $rid)if(one("SELECT id FROM research WHERE id=? AND title LIKE '[QA] Reservation race%'",[$rid]))purge_request($rid,$rid);
}elseif($action==='state'){
    echo json_encode(['leases'=>all('SELECT task_id,actor_hash,expires_at,ended_at FROM task_reservations WHERE research_id=?',[$argv[2]]),'results'=>all('SELECT id FROM workflow_submissions WHERE research_id=?',[$argv[2]])]);
}elseif($action==='worker'){
    [$rid,$tid,$actor,$at,$operation,$gate,$ready]=array_slice($argv,2);
    $GLOBALS['wr_test_clock']=fn()=>(int)$at;putenv('RATE_LIMIT_NAMESPACE=reservation-race-'.basename($gate));
    file_put_contents($ready,'ready');
    $deadline=microtime(true)+15;while(!is_file($gate)){if(microtime(true)>$deadline)throw new RuntimeException('Race gate timeout.');usleep(10000);}
    try{
        $result=match($operation){
            'claim'=>wr_claim($rid,$tid,1,$actor),
            'renew'=>wr_renew($rid,$tid,1,$actor),
            'release'=>wr_release($rid,$tid,$actor),
            'submit'=>wf_submit($rid,$tid,wf_test_payload($rid,wf_task($rid,$tid),'progress'),$actor),
            default=>throw new RuntimeException('Unknown worker action.')
        };
        echo json_encode(['status'=>200,'result'=>$result]);
    }catch(HttpError $e){echo json_encode(['status'=>$e->status,'message'=>$e->getMessage()]);}
}
