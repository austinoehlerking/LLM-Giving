<?php
/** Separate authorized request workers reproduce a grant loaded before progress commits. */
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';
$_SESSION['admin']=1;
$action=$argv[1]??'';
if($action==='create'){
    $created=create_research(['title'=>'[QA] Consumed grant reservation race '.uid(),'question'=>'Synthetic scoped grant consumption and reservation mutation race only.','category_id'=>1,'terms'=>1]);
    moderate('request',$created['id'],'Approved','Disposable consumed-grant race fixture.');
    $r=research($created['id']);$task=wf_tasks($r['id'])[0];
    $issued=ar_issue($r,$task['id'],['authorize'=>1,'terms'=>1,'policy_version'=>terms_version(),'human_level'=>'none','human_details'=>'']);
    preg_match('~/return/([a-f0-9]{32})#([a-f0-9]{64})~',$issued['handoff'],$match);
    $_SESSION['browser_returns'][$match[1]]=hash('sha256',$match[2]);$g=ar_read($r,$match[1]);
    // This fixture is passed privately to workers, never retained in reports.
    echo json_encode(['rid'=>$r['id'],'tid'=>$task['id'],'grant_id'=>$g['id'],'session_hash'=>$g['secret_hash'],'actor'=>$g['actor_hash'],'owner_cap'=>$_SESSION['workflow_actor'],'lease_id'=>wr_live($r['id'],$task['id'])['id']]);
}elseif($action==='cleanup'){
    $f=json_decode(file_get_contents($argv[2]),true);
    if(one("SELECT id FROM research WHERE id=? AND title LIKE '[QA] Consumed grant reservation race%'",[$f['rid']]))purge_request($f['rid'],$f['rid']);
}else{
    $f=json_decode(file_get_contents($argv[2]),true);
    if(!one("SELECT id FROM research WHERE id=? AND title LIKE '[QA] Consumed grant reservation race%'",[$f['rid']]))exit(2);
    $_SESSION['browser_returns'][$f['grant_id']]=$f['session_hash'];
    $r=research($f['rid']);$t=wf_task($f['rid'],$f['tid']);
    if($action==='state'){
        $g=one('SELECT * FROM browser_returns WHERE id=?',[$f['grant_id']]);
        $leases=all('SELECT * FROM task_reservations WHERE research_id=? ORDER BY id',[$f['rid']]);
        $original=one('SELECT ended_at,end_reason FROM task_reservations WHERE id=?',[$f['lease_id']]);
        echo json_encode(['consumed'=>(bool)$g['result_id'],'old_lease_ended'=>$original['ended_at']!==null&&$original['end_reason']==='submitted','lease_hash'=>hash('sha256',json_encode($leases)),'grant_hash'=>hash('sha256',json_encode($g)),'result_count'=>(int)one('SELECT COUNT(*) n FROM workflow_submissions WHERE research_id=?',[$f['rid']])['n']]);
    }elseif($action==='record'){
        $clock=(int)$argv[3];$GLOBALS['wr_test_clock']=fn()=>$clock;
        $g=ar_read($r,$f['grant_id']);$d=wf_test_payload($r['id'],$t,'progress');
        foreach(['submission_key','context_id'] as $key)$d[$key]=$g['scope'][$key];
        $d['handoff_code']=wf_handoff_code($t,wf_read_snapshot($r['id'],$d['context_id']),$d['submission_key']);
        $result=ar_record($r,$g,$d,false);
        echo json_encode(['recorded'=>true,'result_id'=>$result]);
    }elseif($action==='owner-reacquire'){
        $clock=(int)$argv[3];$GLOBALS['wr_test_clock']=fn()=>$clock;
        $_SESSION['workflow_actor']=$f['owner_cap'];unset($_SESSION['browser_returns'][$f['grant_id']]);
        wr_claim($r['id'],$t['id'],1,wf_actor());echo json_encode(['reacquired_by_original_holder'=>true]);
    }elseif($action==='holder-controls'){
        $_SESSION['workflow_actor']=$f['owner_cap'];unset($_SESSION['browser_returns'][$f['grant_id']]);
        $strict=false;try{ar_read($r,$f['grant_id']);}catch(HttpError $e){$strict=$e->status===403;}
        $hint=ar_read($r,$f['grant_id'],allowHolder:true);$statuses=[];
        foreach(['renew','release','claim'] as $operation){
            $_POST=['operation'=>$operation,'task_revision'=>'1'];ob_start();
            try{wr_http($r,$t['id'],'POST',$hint);ob_end_clean();$statuses[$operation]=200;}
            catch(HttpError $e){ob_end_clean();$statuses[$operation]=$e->status;}
        }
        echo json_encode(['strict_return_rejected'=>$strict,'scoped_session_present'=>isset($_SESSION['browser_returns'][$f['grant_id']]),'statuses'=>$statuses,'owner_preserved'=>wr_live($r['id'],$t['id'])['actor_hash']===$f['actor']]);
    }elseif($action==='unauthorized'){
        $_SESSION['workflow_actor']=bin2hex(random_bytes(32));unset($_SESSION['browser_returns'][$f['grant_id']]);
        $hint=ar_read($r,$f['grant_id'],false);$before=hash('sha256',json_encode(all('SELECT * FROM task_reservations WHERE research_id=? ORDER BY id',[$r['id']])));
        $_POST=['operation'=>'renew','task_revision'=>'1'];ob_start();$status=200;
        try{wr_http($r,$t['id'],'POST',$hint);ob_end_clean();}
        catch(HttpError $e){ob_end_clean();$status=$e->status;}
        echo json_encode(['status'=>$status,'leases_unchanged'=>$before===hash('sha256',json_encode(all('SELECT * FROM task_reservations WHERE research_id=? ORDER BY id',[$r['id']])))]);
    }elseif($action==='request'){
        [$operation,$gate,$ready,$clock,$transport]=array_slice($argv,3);$GLOBALS['wr_test_clock']=fn()=>(int)$clock;
        if($transport==='holder'){$_SESSION['workflow_actor']=$f['owner_cap'];unset($_SESSION['browser_returns'][$f['grant_id']]);}
        $stale=ar_read($r,$f['grant_id'],allowHolder:$transport==='holder');file_put_contents($ready,json_encode(['loaded_unused'=>!$stale['result_id']]));
        $deadline=microtime(true)+20;while(!is_file($gate)){if(microtime(true)>$deadline)throw new RuntimeException('Grant race gate timeout.');usleep(10000);}
        $_POST=['operation'=>$operation,'task_revision'=>'1'];ob_start();
        try{wr_http($r,$t['id'],'POST',$stale);$body=ob_get_clean();echo json_encode(['status'=>200,'body'=>json_decode($body,true)]);}
        catch(HttpError $e){ob_end_clean();echo json_encode(['status'=>$e->status,'message'=>$e->getMessage()]);}
    }
}
