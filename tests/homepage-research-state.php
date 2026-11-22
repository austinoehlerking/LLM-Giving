<?php
require __DIR__.'/bootstrap.php';
$_SESSION['admin']=1;

$operation=$argv[1]??'';
if($operation==='create'){
    $tag='homepage-browser-'.substr(uid(),0,8);
    $categories=all('SELECT id,name FROM categories WHERE enabled=1 ORDER BY id LIMIT 2');
    $questions=[
        ['Which marsh sites are keeping up with sea-level rise?','Identify a set of comparable marsh sites'],
        ['Can old well records predict methane leaks?','Find usable methane measurements'],
        ['Which coral reefs bleach less in similar heat?','Find comparable heat records'],
        ['How can a town compare its drinking water records?',null],
    ];
    $rows=[];
    for($i=0;$i<15;$i++){
        [$title,$taskTitle]=$questions[$i]??['How could a local team compare habitat records? '.($i+1),null];
        $category=$categories[$i%2]['id'];
        $created=create_research(['title'=>$title,'question'=>'Compare public data and explain the evidence and limits of this synthetic local browser fixture.','category_id'=>$category,'tags'=>$tag,'terms'=>1]);
        $rid=$created['id'];
        moderate('request',$rid,'Approved','Synthetic research discovery fixture.');
        $taskId=null;
        if($taskTitle){
            $round=(int)wf_project($rid)['round_no'];
            $taskId=wf_new_task($rid,$round,'compare-records','research',$taskTitle,'Identify suitable public source records.','A comparison table and source list.','Explain the shared comparison method.');
            query("UPDATE workflow_tasks SET state='Completed' WHERE research_id=? AND kind='planning'",[$rid]);
            wf_set_state($rid,'Open for Research','Synthetic research discovery tasks are ready.');
        }
        query('UPDATE research SET created_at=?,discovery_listed=? WHERE id=?',[sprintf('2026-01-%02d 12:00:00',28-$i),$i===14?0:1,$rid]);
        $rows[]=['id'=>$rid,'title'=>$title,'taskTitle'=>$taskTitle,'taskId'=>$taskId,'study'=>'/research/'.research($rid)['slug'],'category'=>(string)$category];
    }
    echo json_encode(['tag'=>$tag,'rows'=>$rows,'categories'=>$categories],JSON_THROW_ON_ERROR);
    exit;
}
$rid=$argv[2]??'';
if($operation==='counts'){
    echo json_encode([
        'reservations'=>(int)one('SELECT COUNT(*) n FROM task_reservations WHERE research_id=?',[$rid])['n'],
        'grants'=>(int)one('SELECT COUNT(*) n FROM browser_returns WHERE research_id=?',[$rid])['n'],
        'sessions'=>(int)one('SELECT COUNT(*) n FROM volunteer_sessions')['n'],
    ],JSON_THROW_ON_ERROR);
    exit;
}
if($operation==='reserve'){
    $task=wf_task($rid,$argv[3]??'');
    wr_claim($rid,$task['id'],(int)$task['revision'],hash('sha256','homepage-competing-review-'.$rid));
    exit;
}
if($operation==='pause'){
    change_status($rid,'Paused','Synthetic QA status change after the page was rendered.','requester');
    exit;
}
if($operation==='purge'){
    purge_request($rid,$rid);
    exit;
}
throw new RuntimeException('Unknown homepage discovery QA operation.');
