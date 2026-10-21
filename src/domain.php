<?php

const EXTERNAL_HELP=['unspecified'=>'Not specified','prepare_review'=>'Prepare inputs or review results only','run'=>'Run calculations and share reproducible results'];
const EXTERNAL_ACCESS=['unspecified'=>'Not specified','none'=>'No external tools required','free'=>'Free external tools required','licensed'=>'Licensed or paid external tools required','mixed'=>'Mixed free and licensed tools','unknown'=>'Tool availability needs checking'];
const EXTERNAL_TEXT=['required_software'=>'Required software and versions','software_licensing'=>'License eligibility and access','computing_resources'=>'Computing resources and budget','setup_needs'=>'Downloads and setup needed'];
function external_requirements(array $r): array {return json_decode($r['external_requirements']??'{}',true)?:[];}
function external_parse(array $d): array {
 $out=[];
 foreach(['calculation_help'=>EXTERNAL_HELP,'external_tools'=>EXTERNAL_ACCESS] as $key=>$options){$value=$d[$key]??'unspecified';if(!is_string($value)||!isset($options[$value]))fail(422,'Choose a valid '.$key.'.');$out[$key]=$value;}
 foreach(EXTERNAL_TEXT as $key=>$label)$out[$key]=text_field($d,$key,0,3000);
 if($out['calculation_help']==='prepare_review'&&!empty($d['computational_models']))fail(422,'Preparation/review only cannot also request running computational models.');
 if(in_array($out['external_tools'],['free','licensed','mixed'],true)&&$out['required_software']==='')fail(422,'Name the required external software.');
 return $out;
}

const TERMS_VERSION='2026-10-v1-ccby';
function save_operator_details(array $data): void {
 $name=text_field($data,'operator_name',1,150);$country=text_field($data,'operator_country',1,100);
 $email=text_field($data,'operator_email',1,254);$source=text_field($data,'source_url',0,2000);
 if(!filter_var($email,FILTER_VALIDATE_EMAIL))fail(422,'Enter a valid public contact email.');
 if($source!==''&&(!str_starts_with($source,'https://')||safe_url($source)!==$source))fail(422,'Use a complete HTTPS source repository URL.');
 tx(function()use($name,$country,$email,$source){foreach(['operator_name'=>$name,'operator_country'=>$country,'operator_email'=>$email,'source_url'=>$source] as $key=>$value)set_setting($key,$value);});
}
function legal_pages(): array {
    $pages = [];
    foreach (['terms', 'privacy', 'licensing', 'moderation', 'copyright'] as $page) {
        $pages[$page] = setting('page_'.$page, file_get_contents(ROOT.'/content/'.$page.'.txt'));
    }
    $notice = google_privacy_notice();
    $pages['privacy'] .= "\n\n".$notice['title']."\n".$notice['body'];
    return $pages;
}
function legal_policy_version(array $pages, string $base): string {
    $json = json_encode($pages, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    return 'policy-'.substr(hash('sha256', $base."\n".$json), 0, 32);
}
function terms_version(bool $record = false): string {
    $pages = legal_pages();
    $version = legal_policy_version($pages, setting('terms_version', TERMS_VERSION));
    // Rendering and packet checks stay read-only; actual acceptance records the exact bundle.
    if ($record) {
        query('INSERT IGNORE INTO legal_versions(version,pages,created_at) VALUES (?,?,?)', [
            $version, json_encode($pages, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), now(),
        ]);
    }
    return $version;
}
function save_public_page(string $key, string $body): void {
    tx(function() use ($key, $body) {
        terms_version(record: true);
        set_setting('page_'.$key, $body);
        if ($key !== 'about') {
            set_setting('terms_version', 'policy-'.gmdate('Ymd-His').'-'.substr(uid(), 0, 8));
            terms_version(record: true);
        }
    });
}
const MODERATION=['Pending Approval','Needs Additional Review','Changes Requested','Approved','Rejected','Removed'];
const STATUSES=[
'Needs Research Plan'=>'Help plan bounded research tasks with an eligible Max/Ultra model.',
'Open for Research'=>'Choose a ready research task and contribute evidence.',
'Synthesis'=>'Combine the task results into an evidence-linked synthesis.',
'Independent Verification'=>'Independently check the synthesis with a separate run.',
'Needs Clarification'=>'The original submitter or an administrator must clarify the brief.',
'Blocked'=>'A concrete obstacle requires a response before planning resumes.',
'Review Complete'=>'An independent review was submitted; this is not proof of scientific certainty.',
'Awaiting First Research'=>'Be the first to investigate this research request.',
'Still Unsolved'=>'Build on the research and explore another approach.',
'Mixed Results — More Research Needed'=>'Resolve conflicting evidence and remaining uncertainty.',
'Potential Answer — Needs Verification'=>'Independently check a promising answer.',
'Under Independent Review'=>'Check the evidence and review existing work.',
'Solved'=>'Read the resolution and its supporting evidence.',
'Paused'=>'Work is temporarily paused.',
'Archived — Inactive'=>'Inactive, not necessarily complete. New work may reopen this research request.',
'Withdrawn'=>'Unavailable publicly; retained privately.'
];
function research(string $id,bool $public=true): array {
    $sql='SELECT r.*, c.name category FROM research r JOIN categories c ON c.id=r.category_id WHERE (r.id=? OR r.slug=?)';
    if($public) $sql.=" AND r.moderation='Approved' AND r.status<>'Withdrawn'";
    return one($sql,[$id,$id]) ?? fail(404,'Research not found.');
}
function touch_context(string $rid,bool $activity=true): void {
    query('UPDATE research SET context_version=context_version+1,updated_at=?'.($activity?',activity_at=?':'').' WHERE id=?',$activity?[now(),now(),$rid]:[now(),$rid]);
}
function attribution(array $d): array {
    $profiles=[];
    foreach(['linkedin','github','website'] as $key) {
        $url=safe_url(text_field($d,$key,0,2000));
        if($url) $profiles[$key]=$url;
    }
    return ['display_name'=>text_field($d,'display_name',0,120),'profiles'=>json_encode($profiles)];
}
function set_tags(string $rid,string $names): void {
    $tags=array_values(array_unique(array_filter(array_map('trim',explode(',',$names)))));
    if(count($tags)>12) fail(422,'Use at most 12 tags.');
    foreach($tags as $tag) if(strlen($tag)>60) fail(422,'Tags may have at most 60 characters.');
    query('DELETE FROM research_tags WHERE research_id=?',[$rid]);
    foreach($tags as $tag) {
        query('INSERT IGNORE INTO tags(name) VALUES (?)',[$tag]);
        query('INSERT INTO research_tags(research_id,tag_id) SELECT ?,id FROM tags WHERE name=?',[$rid,$tag]);
    }
}
function create_research(array $d): array {
    if(empty($d['terms'])) fail(422,'Accept the public-content terms to submit.');
    $category=one('SELECT id FROM categories WHERE id=? AND enabled=1',[(int)($d['category_id']??0)]);
    if(!$category) fail(422,'Choose an available category.');
    $email=text_field($d,'email',0,254);
    if($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) fail(422,'Enter a valid private email address.');
    $title=text_field($d,'title',8,180); $question=text_field($d,'question',20);
    $row=['id'=>uid(),'title'=>$title,'question'=>$question,'category_id'=>$category['id'],'email'=>$email,
        'moderation'=>'Pending Approval','moderation_reason'=>'','status'=>'Needs Research Plan','terms_version'=>terms_version(),
        'created_at'=>now(),'updated_at'=>now(),'activity_at'=>now()];
    $row['slug']=substr(trim(preg_replace('/[^a-z0-9]+/','-',strtolower($title)),'-'),0,120).'-'.substr($row['id'],0,10);
    foreach(['context','success','previous','questions','references_text'] as $key) $row[$key]=text_field($d,$key);
    $row['constraints_text']=text_field($d,'constraints_text');
    if(isset($d['computational_models'])&&!in_array($d['computational_models'],['1',1,true,'0',0,false],true))fail(422,'Computational models must be an explicit checkbox selection.');
    $row['computational_models']=!empty($d['computational_models'])?1:0;
    $external=external_parse($d);
    if($external['calculation_help']==='run')$row['computational_models']=1;
    if($row['computational_models'])$external['calculation_help']='run';
    $row['external_requirements']=json_encode($external);
    $row+=rt_parse($d);
    $row['ai_requirements']=json_encode(rq_parse($d));
    $row+=attribution($d);
    $row['flags']=suspicious(json_encode($row))?'Suspected instruction manipulation; inspect as untrusted data.':'';
    if($row['flags']) $row['moderation']='Needs Additional Review';
    $link=tx(function() use($row,$d) { $row['terms_version']=terms_version(record: true); insert('research',$row); set_tags($row['id'],text_field($d,'tags',0,800)); return rotate_token($row['id']); });
    audit_event($row['id'],'Topic submitted','Awaiting initial publication approval.','research',$row['id'],'','Pending Approval','An administrator reviews the topic.','requester',$row['display_name']?:'Original submitter',[],'private');
    return ['id'=>$row['id'],'link'=>$link];
}
function change_status(string $rid,string $status,string $reason,string $actor): void {
    if(!isset(STATUSES[$status]) || trim($reason)==='') fail(422,'Choose a valid status and explain the change.');
    if($actor==='requester' && !in_array($status,['Solved','Paused','Withdrawn'],true)) fail(403,'Requester status change not permitted.');
    tx(function() use($rid,$status,$reason,$actor) {
        $r=one('SELECT status FROM research WHERE id=? FOR UPDATE',[$rid]) ?? fail(404,'Research not found.');
        query('UPDATE research SET status=? WHERE id=?',[$status,$rid]);
        insert('status_history',['research_id'=>$rid,'old_status'=>$r['status'],'new_status'=>$status,'actor'=>$actor,'reason'=>$reason,'created_at'=>now()]);
        touch_context($rid);
        audit_event($rid,'Status updated',$reason,'research',$rid,$r['status'],$status,'',$actor,ucfirst($actor));
    });
}
function approved_contributions(string $rid): array {
    research($rid);
    return all("SELECT c.*, a.provider,a.model,a.harness,a.harness_version,a.reasoning,a.input_tokens,a.output_tokens,a.total_tokens,a.measurement,a.duration,a.tools,a.prompt_version,a.missing_fields FROM contributions c JOIN agent_metadata a ON a.contribution_id=c.id WHERE c.research_id=? AND c.moderation='Approved' AND (c.target_id IS NULL OR EXISTS(SELECT 1 FROM contributions t WHERE t.id=c.target_id AND t.research_id=c.research_id AND t.moderation='Approved')) ORDER BY c.created_at,c.id",[$rid]);
}
function public_feedback(string $rid): array {
    research($rid);
    return all("SELECT id,research_id,target_id,finding_id,kind,body,display_name,created_at FROM feedback f WHERE research_id=? AND moderation='Approved' AND (target_id IS NULL OR EXISTS(SELECT 1 FROM contributions c WHERE c.id=f.target_id AND c.moderation='Approved')) ORDER BY created_at,id",[$rid]);
}
function public_findings(string $rid): array {
    research($rid);
    $rows=all('SELECT * FROM findings WHERE research_id=? ORDER BY created_at,id',[$rid]);
    foreach($rows as &$row) $row['evidence']=all("SELECT c.id,c.kind,c.summary FROM finding_evidence fe JOIN contributions c ON c.id=fe.contribution_id WHERE fe.finding_id=? AND c.moderation='Approved' AND (c.target_id IS NULL OR EXISTS(SELECT 1 FROM contributions t WHERE t.id=c.target_id AND t.moderation='Approved')) ORDER BY c.id",[$row['id']]);
    return $rows;
}
function discovery_filters(array $input): array {
    $out=[];
    foreach(['q','project_type','external_tools','category','tag','status','sort','no_contributions','page'] as $key) $out[$key]=is_scalar($input[$key]??null)?trim((string)$input[$key]):'';
    if(!in_array($out['project_type'],['research','research_tool'],true))$out['project_type']='';
    if(!isset(EXTERNAL_ACCESS[$out['external_tools']]))$out['external_tools']='';
    $out['q']=substr($out['q'],0,1000); $out['tag']=substr($out['tag'],0,60);
    $out['category']=ctype_digit($out['category'])?(string)(int)$out['category']:'';
    if(!isset(STATUSES[$out['status']]) && !in_array($out['status'],['needs-research','needs-review'],true)) $out['status']='';
    if(!in_array($out['sort'],['new','active','updated','fewest','most','title'],true)) $out['sort']='new';
    $out['no_contributions']=$out['no_contributions']==='1'?'1':'';
    $out['page']=(string)max(1,min(1000000,(int)$out['page']));
    return $out;
}
function discovery_categories(): array {
    return all("SELECT c.id,c.name,COUNT(r.id) request_count FROM categories c LEFT JOIN research r ON r.category_id=c.id AND r.moderation='Approved' AND r.status<>'Withdrawn' GROUP BY c.id,c.name ORDER BY c.name");
}
function discover(array $input): array {
    $filters=discovery_filters($input);
    $where=["r.moderation='Approved'","r.status<>'Withdrawn'"]; $params=[];
    if($filters['project_type']!==''){$where[]='r.project_type=?';$params[]=$filters['project_type'];}
    if($filters['external_tools']!==''){$where[]="COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.external_requirements,'$.external_tools')),'unspecified')=?";$params[]=$filters['external_tools'];}
    $q=$filters['q'];
    if($q!=='') { $where[]='(MATCH(r.title,r.question,r.context) AGAINST (? IN NATURAL LANGUAGE MODE) OR r.title LIKE ?)'; $params[]=$q; $params[]='%'.str_replace(['%','_'],['\\%','\\_'],$q).'%'; }
    if($filters['category']!=='') { $where[]='r.category_id=?'; $params[]=(int)$filters['category']; }
    if($filters['tag']!=='') { $where[]='EXISTS (SELECT 1 FROM research_tags rt JOIN tags t ON t.id=rt.tag_id WHERE rt.research_id=r.id AND t.name=?)'; $params[]=$filters['tag']; }
    $status=$filters['status'];
    if($status==='needs-review') $where[]="r.status IN ('Potential Answer — Needs Verification','Under Independent Review','Independent Verification')";
    elseif($status==='needs-research') $where[]="r.status IN ('Awaiting First Research','Still Unsolved','Mixed Results — More Research Needed','Needs Research Plan','Open for Research','Synthesis')";
    elseif(isset(STATUSES[$status])) { $where[]='r.status=?'; $params[]=$status; }
    // Keep counts, ordering, and zero-contribution filtering on the same public child boundary.
    $visible="x.research_id=r.id AND x.moderation='Approved' AND (x.target_id IS NULL OR EXISTS(SELECT 1 FROM contributions t WHERE t.id=x.target_id AND t.research_id=x.research_id AND t.moderation='Approved'))";
    $count="((SELECT COUNT(*) FROM contributions x WHERE ".$visible." AND x.kind<>'Independent review')+(SELECT COUNT(*) FROM workflow_submissions ws JOIN workflow_tasks wt ON wt.id=ws.task_id WHERE ws.research_id=r.id AND ws.visibility='public' AND wt.visibility='public' AND ws.admission='accepted' AND wt.kind<>'review'))";
    if($filters['no_contributions']==='1') $where[]=$count.'=0';
    $w=implode(' AND ',$where);
    $total=(int)one('SELECT COUNT(*) n FROM research r WHERE '.$w,$params)['n'];
    $page=min((int)$filters['page'],max(1,(int)ceil($total/12))); $filters['page']=(string)$page; $offset=($page-1)*12;
    $orders=['new'=>'r.created_at DESC,r.id','active'=>'r.activity_at DESC,r.id','updated'=>'r.updated_at DESC,r.id','fewest'=>'contribution_count ASC,r.created_at DESC,r.id','most'=>'contribution_count DESC,r.created_at DESC,r.id','title'=>'r.title ASC,r.id'];
    $rows=all("SELECT r.*,c.name category,".$count." contribution_count,((SELECT COUNT(*) FROM contributions x WHERE ".$visible." AND x.kind='Independent review')+(SELECT COUNT(*) FROM workflow_submissions ws JOIN workflow_tasks wt ON wt.id=ws.task_id WHERE ws.research_id=r.id AND ws.visibility='public' AND wt.visibility='public' AND ws.admission='accepted' AND wt.kind='review')) review_count FROM research r JOIN categories c ON c.id=r.category_id WHERE ".$w." ORDER BY ".$orders[$filters['sort']]." LIMIT 12 OFFSET ".$offset,$params);
    foreach($rows as &$r) $r['tags']=all('SELECT t.name FROM tags t JOIN research_tags rt ON rt.tag_id=t.id WHERE rt.research_id=? ORDER BY t.name',[$r['id']]);
    return compact('rows','total','page','filters');
}
function moderate(string $type,string $id,string $state,string $reason,string $exception=''): void {
    require_admin();
    $tables=['request'=>'research','contribution'=>'contributions','feedback'=>'feedback','revision'=>'revisions','attachment'=>'attachments'];
    if(!isset($tables[$type]) || !in_array($state,MODERATION,true)) fail(422,'Invalid moderation action.');
    if(trim($reason)==='') fail(422,'A moderation reason is required.');
    $table=$tables[$type];
    tx(function() use($table,$type,$id,$state,$reason,$exception) {
        $row=one("SELECT * FROM $table WHERE id=? FOR UPDATE",[$id]) ?? fail(404,'Item not found.');
        $rid=$type==='request'?$id:$row['research_id'];
        if($state==='Approved' && $type==='contribution') {
            $meta=one('SELECT missing_fields FROM agent_metadata WHERE contribution_id=?',[$id]);
            if($meta && $meta['missing_fields']!=='[]' && trim($exception)==='') fail(422,'Explain the metadata exception before approval.');
            if($row['target_id']) {
                if(!one("SELECT id FROM contributions WHERE id=? AND research_id=? AND moderation='Approved'",[$row['target_id'],$rid])) fail(422,'Approve the review target first.');
            }
            query('UPDATE contributions SET metadata_exception=?,published_at=COALESCE(published_at,?) WHERE id=?',[$exception,now(),$id]);
        }
        if($state==='Approved' && $type==='attachment' && str_starts_with($row['scan_status'],'infected')) fail(422,'An infected attachment cannot be approved.');
        if($type==='revision' && $state==='Approved') {
            if($row['moderation']==='Approved') fail(422,'This revision has already been applied.');
            $r=research($rid,false);
            insert('research_versions',['research_id'=>$rid,'version'=>$r['context_version'],'title'=>$r['title'],'question'=>$r['question'],'context'=>$r['context'],'created_at'=>now()]);
            query('UPDATE research SET title=?,question=?,context=? WHERE id=?',[$row['title'],$row['question'],$row['context'],$rid]);
            if(wf_project($rid))wf_restart($rid,'The research brief changed. Earlier work is preserved for a new plan.');
        }
        query("UPDATE $table SET moderation=?,moderation_reason=? WHERE id=?",[$state,$reason,$id]);
        touch_context($rid,$state==='Approved');
        audit_event($rid,'Moderation decision',$reason,$type,$id,$row['moderation'],$state,'','administrator','Administrator',[],'private');
        if($state!=='Approved')query('UPDATE workflow_snapshots SET public_allowed=0 WHERE research_id=?',[$rid]);
        if($type==='request' && $state==='Approved')wf_start($rid);
        if($type==='contribution' && $state==='Approved' && $row['kind']!=='Independent review') {
            $r=research($rid,false);
            if($r['status']==='Awaiting First Research') {
                query("UPDATE research SET status='Still Unsolved' WHERE id=?",[$rid]);
                insert('status_history',['research_id'=>$rid,'old_status'=>$r['status'],'new_status'=>'Still Unsolved','actor'=>'administrator','reason'=>'First research contribution approved; resolution remains open.','created_at'=>now()]);
            }
        }
    });
}
function add_finding(string $rid,array $d): void {
    require_admin(); research($rid,false);
    $kind=text_field($d,'kind'); $classification=text_field($d,'classification');
    if(!in_array($kind,['Finding','Remaining question','Unsuccessful approach','Recommended next step','Resolution rationale','Disproven claim','Superseded claim'],true) || !in_array($classification,['Provisional','Contested','Verified'],true)) fail(422,'Invalid finding classification.');
    $statement=text_field($d,'statement',10); $rationale=text_field($d,'rationale',10);
    $ids=array_values(array_unique(array_filter(array_map('trim',explode(',',text_field($d,'evidence',0,4000))))));
    if(!$ids) fail(422,'Link at least one approved contribution as evidence.');
    $hasReview=false;
    foreach($ids as $id) {
        $c=one("SELECT kind FROM contributions WHERE id=? AND research_id=? AND moderation='Approved'",[$id,$rid])??fail(422,'Evidence must belong to this request and be approved.');
        $hasReview=$hasReview||$c['kind']==='Independent review';
    }
    if($classification==='Verified' && !$hasReview) fail(422,'Verified findings require approved independent review evidence and documented criteria.');
    $supersedes=text_field($d,'supersedes_id',0,32)?:null;
    if($supersedes && !one('SELECT id FROM findings WHERE id=? AND research_id=?',[$supersedes,$rid])) fail(422,'Invalid superseded finding.');
    tx(function() use($rid,$kind,$classification,$statement,$rationale,$ids,$supersedes) {
        $id=uid(); insert('findings',['id'=>$id,'research_id'=>$rid,'kind'=>$kind,'classification'=>$classification,'statement'=>$statement,'rationale'=>$rationale,'supersedes_id'=>$supersedes,'created_at'=>now()]);
        foreach($ids as $cid) insert('finding_evidence',['finding_id'=>$id,'research_id'=>$rid,'contribution_id'=>$cid]);
        touch_context($rid);
    });
}
function purge_request(string $rid,string $confirmation): void {
    require_admin(); if($confirmation!==$rid) fail(422,'Type the complete immutable research ID to confirm permanent deletion.');
    $job=tx(function() use($rid) {
        research($rid,false);
        $files=array_column(all('SELECT stored_name FROM attachments WHERE research_id=?',[$rid]),'stored_name');
        foreach(all('SELECT id FROM emails WHERE research_id=?',[$rid]) as $m) $files[]='mail:'.$m['id'].'.json';
        $job=uid(); insert('purge_jobs',['id'=>$job,'files'=>json_encode($files),'state'=>'pending','created_at'=>now()]);
        query('UPDATE contributions SET target_id=NULL,superseded_by=NULL WHERE research_id=?',[$rid]);
        query('UPDATE findings SET supersedes_id=NULL WHERE research_id=?',[$rid]);
        query('DELETE FROM feedback WHERE research_id=?',[$rid]);
        audit_event(null,'Research purged','An administrator explicitly purged the request and its local records.','research',$rid,'Retained','Purged','','administrator','Administrator',[],'private');
        query('DELETE FROM workflow_handoffs WHERE research_id=?',[$rid]);
        query('DELETE FROM workflow_submissions WHERE research_id=?',[$rid]);
        query('DELETE FROM research WHERE id=?',[$rid]);
        return $job;
    });
    cleanup_purge($job);
}
function cleanup_purge(string $id): void {
    $job=one('SELECT * FROM purge_jobs WHERE id=?',[$id]); if(!$job) return;
    $remaining=[];
    foreach(json_decode($job['files'],true) as $name) {
        $path=str_starts_with($name,'mail:')?storage('mail/'.basename(substr($name,5))):storage('uploads/'.basename($name));
        if(is_file($path) && !unlink($path)) $remaining[]=$name;
    }
    query('UPDATE purge_jobs SET files=?,state=? WHERE id=?',[json_encode($remaining),$remaining?'retry required':'complete',$id]);
}
