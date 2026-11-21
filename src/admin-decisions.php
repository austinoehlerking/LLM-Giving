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
        ORDER BY CASE moderation WHEN 'Needs Additional Review' THEN 0 ELSE 1 END,created_at,id LIMIT $pageSize OFFSET $offset");
    return ['items'=>$items,'held'=>$page===1?$held:[],'page'=>$page,'pages'=>$pages,
        'review'=>(int)($counts['Pending Approval']??0)+(int)($counts['Needs Additional Review']??0)+($waiting?count(wf_influence_pending()):count($held)),
        'waiting'=>(int)($counts['Changes Requested']??0)];
}

function admin_planner_line(array $status): string {
    if(!$status['configured'])return 'Local planner setup required.';
    $number=$status['pending'];
    $count=$number.($status['more']?'+':'');
    $queue=$number?$count.' approved '.($number===1?'request ready':'requests ready').' to plan.':'No approved requests ready to plan.';
    if($status['active'])$queue=$status['run']==='running'?'Planning in progress.':'Plan run queued.';
    if(!$status['active']&&$status['run']==='complete'&&!$number)$queue='Last planning run finished.';
    if($status['failed'])$queue.=' '.$status['failed'].' need attention.';
    return $queue;
}
