<?php
/** Select compact records for the private decision queue. */
function admin_decisions(bool $waiting,int $page): array {
    require_admin();
    $types=[
        'request'=>['research','s.id','s.title','s.status'],
        'contribution'=>['contributions','s.research_id','s.summary','r.status'],
        'feedback'=>['feedback','s.research_id','s.kind','r.status'],
        'revision'=>['revisions','s.research_id','s.title','r.status'],
        'attachment'=>['attachments','s.research_id','s.original_name','r.status'],
    ];
    $queries=[];
    foreach($types as $type=>[$table,$parent,$title,$status]){
        $parentJoin=$type==='request'?'':" JOIN research r ON r.id=$parent";
        $queries[]="SELECT '$type' kind,s.id,$parent research_id,$title title,s.moderation,s.created_at
          FROM $table s$parentJoin
          WHERE $status<>'Withdrawn' AND s.moderation IN ('Pending Approval','Needs Additional Review','Changes Requested')";
    }
    $query=implode(' UNION ALL ',$queries);
    $counts=all("SELECT moderation,COUNT(*) n FROM ($query) AS decisions GROUP BY moderation");
    $counts=array_column($counts,'n','moderation');
    $held=$waiting?[]:wf_influence_pending();
    $total=$waiting?(int)($counts['Changes Requested']??0):(int)($counts['Pending Approval']??0)+(int)($counts['Needs Additional Review']??0);
    $pageSize=20;
    $pages=max(1,(int)ceil($total/$pageSize));
    $page=max(1,min($pages,$page));
    $offset=($page-1)*$pageSize;
    $items=all("SELECT * FROM ($query) AS decisions WHERE moderation ".($waiting?'=':'<>')." 'Changes Requested'
        ORDER BY CASE WHEN kind='request' AND moderation='Pending Approval' THEN 0 WHEN moderation='Needs Additional Review' THEN 1 ELSE 2 END,created_at,id LIMIT $pageSize OFFSET $offset");
    $approvable=$waiting?[]:admin_routine_request_ids();
    return ['items'=>$items,'held'=>$page===1?$held:[],'page'=>$page,'pages'=>$pages,
        'approvable'=>count($approvable),'approvalSnapshot'=>admin_approval_snapshot($approvable),
        'review'=>(int)($counts['Pending Approval']??0)+(int)($counts['Needs Additional Review']??0)+($waiting?count(wf_influence_pending()):count($held)),
        'waiting'=>(int)($counts['Changes Requested']??0)];
}

function admin_routine_request_ids(): array {
    return array_column(all("SELECT id FROM research WHERE moderation='Pending Approval' AND status<>'Withdrawn' ORDER BY id"),'id');
}

function admin_approval_snapshot(array $ids): string {
    return hash('sha256',implode(',',$ids));
}

/** Approve and queue in the same transaction. Routine queue actions recheck the locked request. */
function admin_approve_research(string $rid,string $reason,bool $routine=false): ?bool {
    require_admin();
    return tx(function()use($rid,$reason,$routine){
        $request=one('SELECT status,moderation FROM research WHERE id=? FOR UPDATE',[$rid]);
        if($routine&&(!$request||$request['status']==='Withdrawn'||$request['moderation']!=='Pending Approval'))return null;
        moderate('request',$rid,'Approved',$reason);
        return lp_request_approved_plan($rid);
    });
}

function admin_approve_from_queue(array $post): string {
    require_admin();
    $mode=$post['approval']??'one';
    if($mode==='all'){
        $ids=admin_routine_request_ids();
        if(!is_string($post['snapshot']??null)||!hash_equals(admin_approval_snapshot($ids),$post['snapshot'])){
            return 'The queue changed. Review the current requests before approving all.';
        }
    }elseif($mode==='selected'){
        $ids=$post['selected']??[];
        if(!is_array($ids)||!array_is_list($ids)||count($ids)>20)fail(422,'Select requests from this page.');
        if(!$ids)return 'Select at least one research request to approve.';
    }elseif($mode==='one'){
        $ids=[$post['approve_request']??null];
    }else{
        fail(422,'Choose how to approve research requests.');
    }
    foreach($ids as $id){
        if(!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id))fail(422,'Invalid research request.');
    }
    $approved=$queued=$skipped=0;
    foreach(array_unique($ids) as $id){
        try{
            $plan=admin_approve_research($id,ADMIN_APPROVAL_REASON,true);
        }catch(HttpError $error){
            if(!in_array($error->status,[404,409,422],true))throw $error;
            $plan=null;
        }
        if($plan===null){$skipped++;continue;}
        $approved++;
        if($plan)$queued++;
    }
    $message=$approved===1?'1 research request approved.':$approved.' research requests approved.';
    if($queued)$message.=$queued===1?' 1 research plan queued.':' '.$queued.' research plans queued.';
    if($skipped)$message.=$skipped===1?' 1 request skipped. Open it for individual review.':' '.$skipped.' requests skipped. Open them for individual review.';
    return $message;
}

function admin_planner_line(array $status): string {
    if(!$status['configured'])return 'Local planner setup required.';
    $number=$status['pending'];
    $count=$number.($status['more']?'+':'');
    $queue=$number?$count.' approved '.($number===1?'request ready':'requests ready').' to plan.':'No approved requests ready to plan.';
    if($status['active'])$queue=$status['run']==='running'?'Planning in progress.':'Plan run queued.';
    if(!$status['active']&&$status['automatic']){
        $queue=$status['connection']==='connected'?'Approved plans queued.':'Approved plans queued. Waiting for ChatGPT.';
    }
    if(!$status['active']&&$status['run']==='complete'&&!$number)$queue='Last planning run finished.';
    if($status['failed'])$queue.=' '.$status['failed'].' need attention.';
    return $queue;
}
