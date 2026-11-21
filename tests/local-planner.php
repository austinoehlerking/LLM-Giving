<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';

if(env('APP_ENV')!=='test')throw new RuntimeException('Use an isolated test database.');
$_SESSION['admin']=1;
putenv('LOCAL_PLANNER_TOKEN_SHA256='.hash('sha256','isolated-test'));
$checks=0;
function lp_check(bool $ok,string $message): void {
    global $checks;
    if(!$ok)throw new RuntimeException($message);
    $checks++;
}
function lp_expect(int $status,callable $fn): void {
    try{$fn();}
    catch(HttpError $error){lp_check($error->status===$status,$error->getMessage());return;}
    throw new RuntimeException('Expected HTTP '.$status);
}
$category=one("SELECT id FROM categories WHERE name='Education'")['id'];
$brief=['title'=>'Library event participation methods','question'=>'How can a small library compare participation across different after-school events?','category_id'=>$category,'terms'=>1];
$legacy=create_research($brief);
moderate('request',$legacy['id'],'Approved','Isolated test approval.');
$legacyTask=wf_tasks($legacy['id'])[0];
lp_check($legacyTask['visibility']==='public','Legacy volunteering mode remains available before activation.');
lp_enable();
lp_check(wf_task($legacy['id'],$legacyTask['id'])['visibility']==='private','Active legacy planning converts to a private job.');
$pending=create_research(array_replace($brief,['title'=>'A pending request with no authorized plan']));
$job=lp_claim();
lp_check($job['research_id']===$legacy['id']&&$job['model']==='gpt-6-astra','Claim selects an approved request and exact configured model.');
lp_check(lp_claim()===null,'Another worker cannot claim the same plan or see an unapproved request.');
lp_check(!array_key_exists('email',$job['context']['research']),'The claim omits private request credentials and contact information.');
lp_check(discover([])['total']===0,'The catalogue excludes requests waiting on a plan.');
lp_check(!wf_handoff_next(research($legacy['id'])),'Volunteers cannot receive a private planning assignment.');
$task=['key'=>'methods','title'=>'Identify comparable participation measures','objective'=>'Compare public methods for measuring participation at library events.','deliverable'=>'An annotated comparison table.',
    'acceptance'=>'Include at least two sources and explain denominator differences.','evidence_needs'=>'Public library program evaluation documentation.','stopping_rule'=>'Stop after three eligible sources or a documented search gap.','dependencies'=>[],'computational'=>false,'builds_tool'=>false];
$payload=['action'=>'finish','research_id'=>$job['research_id'],'lease'=>$job['lease'],'run_id'=>'12345678-91ab-4abc-a123-123456789abc',
    'tasks'=>[$task,array_replace($task,['key'=>'compare','title'=>'Compare definitions and limitations','dependencies'=>['methods']])]];
lp_expect(422,fn()=>lp_finish(array_replace($payload,['tasks'=>[array_replace($task,['dependencies'=>['methods']])]]),false));
lp_check(discover([])['total']===0,'An invalid plan does not publish any work.');
lp_expect(409,fn()=>lp_finish(array_replace($payload,['lease'=>str_repeat('b',64)]),false));
lp_check(lp_finish($payload,false)['recorded'],'A valid plan publishes research tasks in one commit.');
lp_check(lp_finish($payload,false)['recorded'],'The same completion may be retried by its lease holder.');
lp_check(discover([])['total']===1&&research($legacy['id'])['status']==='Open for Research','Ready request becomes discoverable.');
$ready=wf_handoff_next(research($legacy['id']));
lp_check($ready['kind']==='research'&&$ready['task_key']==='methods','The first volunteer action is a research task.');
$tasks=array_column(wf_tasks($legacy['id']),'state','task_key');
lp_check($tasks['compare']==='Waiting'&&$tasks['synthesis']==='Waiting'&&$tasks['review']==='Waiting','Dependencies, synthesis and independent review are preserved.');
moderate('request',$pending['id'],'Approved','Isolated test approval.');
$other=lp_claim();
lp_check($other['research_id']===$pending['id'],'A newly approved request automatically becomes a private job.');
touch_context($pending['id']);
lp_expect(409,fn()=>lp_finish(array_replace($payload,['research_id'=>$other['research_id'],'lease'=>$other['lease']]),false));
$new=lp_claim();
lp_check($new['research_id']===$pending['id']&&$new['lease']!==$other['lease'],'Changed briefs get a fresh lease.');
lp_finish(['research_id'=>$new['research_id'],'lease'=>$new['lease'],'failure'=>'runner'],true);
lp_check(lp_claim()===null&&discover([])['total']===1,'Failed runs stay out of discovery without automatic repeated attempts.');
lp_retry($pending['id']);
lp_check(lp_claim()['research_id']===$pending['id'],'An admin can retry the failed job.');
purge_request($legacy['id'],$legacy['id']);
purge_request($pending['id'],$pending['id']);
query("DELETE FROM settings WHERE name='local_planner_enabled'");
echo "$checks local planning assertions passed.\n";
