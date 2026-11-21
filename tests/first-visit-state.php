<?php
require __DIR__.'/happy-path-reader-state.php';
require_once ROOT.'/src/admin-catalogue.php';

$action=$argv[1]??'';
$id=$argv[2]??'';
if($action==='create'){
    // The QA driver owns an isolated empty database for this scenario.
    $category=one("SELECT id,name FROM categories WHERE name='Education'");
    $findings=reader_test_create();
    $otherTitle='A community study with public findings';
    query("UPDATE research SET title=?,created_at='2025-01-01 00:00:00' WHERE id=?",[$otherTitle,$findings['id']]);
    $title='Which air-cleaner configurations provide the most verified particle removal per dollar for a small community room during wildfire smoke events?';
    $brief=['title'=>$title,'question'=>'Compare measured evidence and costs for a small community room. Report uncertainty and source limitations.','category_id'=>$category['id'],'terms'=>1];
    $r=create_research($brief);
    moderate('request',$r['id'],'Approved','Synthetic open planning fixture.');
    $hidden=create_research(array_replace($brief,['title'=>'Synthetic designated test with a solved status']));
    admin_set_discovery_listing($hidden['id'],false,'Designated isolated QA fixture.');
    moderate('request',$hidden['id'],'Approved','Synthetic explicitly unlisted fixture.');
    change_status($hidden['id'],'Solved','Fixture for discovery exclusion.','requester');
    echo json_encode(['id'=>$r['id'],'study'=>'/research/'.research($r['id'])['slug'],'title'=>$title,'otherTitle'=>$otherTitle,'category'=>$category['id'],'categoryName'=>$category['name'],'withFindings'=>$findings['id'],'hidden'=>$hidden['id']],JSON_THROW_ON_ERROR);
    exit;
}
if(in_array($action,['solve','pause','resume'],true)){
    $status=match($action){'solve'=>'Solved','pause'=>'Paused',default=>'Needs Research Plan'};
    change_status($id,$status,'Change the isolated fixture status.',$action==='resume'?'administrator':'requester');
    exit;
}
if($action==='session'){
    $s=vs_read($id,false);
    echo json_encode(['minutes'=>(int)round((strtotime($s['expires_at'])-strtotime($s['created_at']))/60),'tasks'=>(int)$s['assignment_limit']],JSON_THROW_ON_ERROR);
    exit;
}
if($action==='acceptance'){
    $r=research($id,false);
    $audit=one("SELECT visibility,metadata FROM audit_events WHERE research_id=? AND action='Topic submitted'",[$id]);
    echo json_encode(['moderation'=>$r['moderation'],'version'=>$r['terms_version'],'visibility'=>$audit['visibility'],'acceptance'=>json_decode($audit['metadata'],true)],JSON_THROW_ON_ERROR);
    exit;
}
throw new RuntimeException('Unknown first-visit QA operation.');
