<?php
function brand_wordmark(): string { return '<span class="brand-name">LLM.Giving<span class="brand-meter" aria-hidden="true"><span class="brand-meter-fill"></span></span></span>'; }
const SOURCE_REPOSITORY_URL='https://github.com/austinoehlerking/LLM-Giving';
function source_repository_url(): string {
    return setting('source_url') ?: SOURCE_REPOSITORY_URL;
}
function source_repository_label(): string {
    return strtolower((string)parse_url(source_repository_url(), PHP_URL_HOST)) === 'github.com' ? 'View code on GitHub' : 'View source code';
}
const HOME_TITLE='Folding@home, but for AI usage.';
const HOME_INTRO='Folding@home lets people donate unused computing power to advance scientific research. LLM.Giving brings that same concept to AI.';
function home_metadata(): array {
    $base=rtrim(env('APP_URL','http://127.0.0.1:8086'),'/');
    $description='Put your spare AI usage toward volunteer research with LLM.Giving. Investigate questions, share open-source findings, and independently review the evidence.';
    return [
        'title'=>'Spare AI Usage for Volunteer Research',
        'description'=>$description,
        'canonical'=>$base.'/',
        'socialType'=>'website',
        'socialImage'=>$base.'/assets/llm-giving-social-v1.jpg?v=progress1',
        'socialImageAlt'=>'LLM.Giving: Folding@home, but for AI usage. Put your spare AI usage to help open-source research.',
        'structuredData'=>['@context'=>'https://schema.org','@type'=>'WebSite','name'=>'LLM.Giving','url'=>$base.'/','description'=>$description],
    ];
}
function research_excerpt(string $text,int $length=155): string {
    $text=preg_replace('/\\s+/u',' ',trim($text));
    preg_match('/^.{0,'.$length.'}/us',$text,$match);
    return ($match[0]??'').(strlen($text)>strlen($match[0]??'')?'…':'');
}

function render(string $view,array $data=[]): void {
    global $path;
    $bufferLevel = ob_get_level();
    extract($data, EXTR_SKIP);
    try {
        ob_start();
        require ROOT.'/views/'.$view.'.php';
        $content = ob_get_clean();
        ob_start();
        require ROOT.'/views/layout.php';
        echo ob_get_clean();
    } catch (Throwable $error) {
        while (ob_get_level() > $bufferLevel) ob_end_clean();
        throw $error;
    }
}
/** The emergency page must work without settings, database access or user content. */
function render_error(string $title, string $message): void {
    while (ob_get_level() > 0) ob_end_clean();
    require ROOT.'/views/error.php';
}
function redirect(string $url): never { header('Location: '.$url,true,303); exit; }
function flash(string $message): void { $_SESSION['flash']=$message; }
function button(string $label,string $href,string $class='button'): string { return '<a class="'.e($class).'" href="'.e($href).'">'.e($label).'</a>'; }
function field(string $label,string $name,string $value='',string $type='text',bool $required=false): void {
    static $index=0;
    $id='f-'.str_replace(['[',']'],['-',''],$name).'-'.(++$index);
    echo '<label for="'.e($id).'">'.e($label).($required?' <span aria-hidden="true">*</span>':'').'</label>';
    if($type==='textarea') echo '<textarea id="'.e($id).'" name="'.e($name).'" '.($required?'required':'').'>'.e($value).'</textarea>';
    else echo '<input id="'.e($id).'" name="'.e($name).'" type="'.e($type).'" value="'.e($value).'" '.($required?'required':'').'>';
}
function select_field(string $label,string $name,array $options,string $selected='',bool $required=false): void {
    static $index=0; $id='select-'.$name.'-'.(++$index);
    echo '<label for="'.e($id).'">'.e($label).($required?' <span aria-hidden="true">*</span>':'').'</label><select id="'.e($id).'" name="'.e($name).'" '.($required?'required':'').'>';
    foreach($options as $k=>$v) echo '<option value="'.e($k).'" '.((string)$k===$selected?'selected':'').'>'.e($v).'</option>';
    echo '</select>';
}
function terms(bool $researchLicense=false): void {
    echo '<label class="check"><input type="checkbox" name="terms" value="1" required> <span>'.e(CONTRIBUTOR_RESOURCE_CONFIRMATION).' I accept the <a href="/legal/terms" target="_blank" rel="noopener">public-content terms</a>'.($researchLicense?' and <a href="/legal/licensing" target="_blank" rel="noopener">CC BY 4.0 license for eligible original research (credit contributors and LLM.Giving)</a>':'').'. I understand approved content is public. Third-party rights remain unchanged.</span></label>';
}
function author_fields(): void {
    field('Display name or pseudonym','display_name'); field('LinkedIn URL','linkedin','','url'); field('GitHub URL','github','','url'); field('Website URL','website','','url');
}
function status_badge(string $status): string { if(str_contains($status,'Awaiting')||in_array($status,['Quarantined','Rejected'],true))return '<span class="influence-badge">'.e($status).'</span>'; return '<span class="status '.($status==='Solved'?'solved':(str_contains($status,'Mixed')?'mixed':'')).'">'.e($status).'</span>'; }
function prose(string $text): string { return '<div class="prose">'.nl2br(e($text)).'</div>'; }
function profile_links(string $json): void {
    foreach(json_decode($json,true)??[] as $label=>$url) echo '<a href="'.e($url).'" rel="nofollow noopener noreferrer" target="_blank">'.e(ucfirst($label)).'</a> ';
}
const ADMIN_APPROVAL_REASON='Approved for publication by an administrator.';
/** Concise request decisions keep the existing endpoint and require a substantive nonroutine note. */
function moderation_reason(array $post): string {
    if (!isset($post['request_review'])) return text_field($post,'reason',1);
    if (($post['request_review']!=='1' && $post['request_review']!==1) || ($post['type']??'')!=='request') fail(422,'Invalid request review form.');
    return text_field($post,'reason',($post['state']??'')==='Approved'?1:10);
}
function moderation_form(string $type,string $id,string $state,string $back,bool $requestReview=false): void {
    echo '<form method="post" action="/admin/moderate" class="moderate">'.csrf().'<input type="hidden" name="type" value="'.e($type).'"><input type="hidden" name="id" value="'.e($id).'"><input type="hidden" name="back" value="'.e($back).'">';
    select_field('Moderation decision','state',array_combine(MODERATION,MODERATION),$state);
    if ($requestReview) echo '<input type="hidden" name="request_review" value="1"><label for="other-decision-reason">Decision reason (private)</label><textarea id="other-decision-reason" name="reason" required minlength="10"></textarea>';
    else field('Decision reason (private)','reason','','textarea',true);
    if($type==='contribution') field('Explicit metadata exception rationale, if needed','exception');
    echo '<button>Save moderation decision</button></form>';
}

/** One public, read-only presentation of availability; never return held packet content. */
function reader_workflow_state(array $research): array {
    $r=research($research['id']);$base='/research/'.$r['slug'];
    $state=['label'=>$r['status'],'task'=>null,'guidance'=>'','unblocker'=>'','href'=>$base.'/work'];
    if(wf_quarantine($r['id']))return array_replace($state,['label'=>'Awaiting admin decision','guidance'=>'Research cannot advance while a packet is quarantined. Accepted earlier work remains provisional.','unblocker'=>'An administrator must approve or reject the held packet.']);
    if(in_array($r['status'],['Blocked','Needs Clarification'],true))return array_replace($state,['guidance'=>'Read the recorded blocker or clarification before starting more work.','unblocker'=>'The original requester can respond through their private management link, or an administrator can help.','href'=>$base.'/ledger']);
    if(in_array($r['status'],['Paused','Archived — Inactive','Solved'],true))return array_replace($state,['guidance'=>'This study is not accepting task results. You can read its available findings.','unblocker'=>'The requester or an administrator must resume or reopen research before new work can start.']);
    if($r['status']==='Review Complete')return array_replace($state,['guidance'=>'This research cycle is complete. Read the findings and their limitations; review completion does not guarantee scientific correctness.','unblocker'=>'A human can request another independent review for a specific concern.','href'=>$base.'/work#request-review']);
    if(!wf_project($r['id']))return array_replace($state,['guidance'=>STATUSES[$r['status']]??'Read the research brief and available contributions.','unblocker'=>'Contributors can inspect the brief and publication requirements.','href'=>$base.'/contribute']);
    $task=mp_next($r);
    if($task)return array_replace($state,['task'=>$task,'guidance'=>'A '.str_replace('_',' ',$task['kind']).' task is available: '.$task['title'],'unblocker'=>'A volunteer can authorize an AI handoff and reserve the current task.']);
    foreach(wf_tasks($r['id']) as $task){
        if($task['visibility']!=='public')continue;$availability=mp_state($r,$task);
        if($availability['eligible'])return array_replace($state,['label'=>'Work in progress','guidance'=>'All eligible tasks are currently reserved. No new task is available to you.','unblocker'=>'The current volunteer can finish or release their reservation; otherwise it must expire.']);
    }
    return array_replace($state,['label'=>'No task available','guidance'=>'No eligible task is ready. Read the task list and activity ledger for outstanding prerequisites.','unblocker'=>'Prerequisite work or an authorized workflow response is needed before another task becomes available.']);
}
/** Current public authored work only. No generated scientific summary or held evidence. */
function reader_findings_data(string $rid): array {
    research($rid);$items=[];
    foreach(rs_records($rid) as $record){
        $result=$record['result'];$task=$record['task'];
        if(!in_array($task['kind'],['research','synthesis'],true)||$task['state']!=='Completed'||$task['accepted_id']!==$result['id'])continue;
        $meta=json_decode($result['metadata'],true);$aid=($meta['usefulness']['version']??null)===1?$meta['usefulness']:null;
        $items[]=['id'=>$result['id'],'title'=>$task['title'],'author'=>$result['display_name']?:'Anonymous volunteer','created_at'=>$result['created_at'],'kind'=>$task['kind'],'contribution'=>false,'finding'=>$aid['finding']??$result['reason'],'established'=>$aid['established']??null,'uncertain'=>$aid['uncertain']??$result['limitations'],'next_use'=>$aid['next_use']??null,'workflow_next_actions'=>$result['next_action'],'aid'=>($aid['version']??null)===1?$aid:null];
    }
    foreach(rs_contributions($rid) as $result){
        if($result['kind']==='Independent review')continue;
        $items[]=['id'=>$result['id'],'title'=>$result['summary'],'author'=>$result['display_name']?:'Anonymous volunteer','created_at'=>$result['created_at'],'kind'=>'supplementary contribution','contribution'=>true,'finding'=>$result['summary'],'established'=>null,'uncertain'=>$result['uncertainty']?:$result['limitations'],'next_use'=>null,'workflow_next_actions'=>$result['next_actions'],'aid'=>null];
    }
    usort($items,fn($a,$b)=>(($b['kind']==='synthesis')<=>($a['kind']==='synthesis'))?:strcmp($b['created_at'].$b['id'],$a['created_at'].$a['id']));
    return $items;
}
