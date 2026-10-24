<?php
/** Scholarly resources and supporting-material completeness. Never fetch or execute sources. */
const RS_CATEGORIES=['sources','inputs','units','formulas','code','software','methods','assumptions','outputs','tables','intermediate_results','failed_approaches'];
function rs_packet_budget(array $packet): void {
    if(strlen(json_encode($packet,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))>200000)fail(422,'The complete packet exceeds 200,000 bytes. Report oversized required material in progress; never silently truncate it or claim unsupported completion.');
}
function rs_attribution(array $source): array {
    $out=[];
    foreach(['authors'=>1000,'publication_date'=>80,'publisher'=>500,'doi'=>200,'edition'=>200,'metadata_note'=>1500] as $key=>$max){
        if(!array_key_exists($key,$source))fail(422,'Source attribution needs '.$key.'. Use an empty string and explain unavailable metadata; never guess.');
        $out[$key]=text_field($source,$key,0,$max);
    }
    if($out['doi']!==''&&!preg_match('~^10\.[0-9]{4,9}/[^\s<>]+$~uD',$out['doi']))fail(422,'Use a DOI identifier such as 10.1234/example, or leave it empty with an honest metadata note.');
    if((!$out['authors']||!$out['publication_date']||!$out['publisher']||(($source['type']??'')==='dataset'&&!$out['edition']))&&strlen($out['metadata_note'])<10)fail(422,'Explain missing scholarly attribution or dataset edition in metadata_note. Do not fabricate it.');
    $out['use']=wf_evidence_choice($source,'use',['used','background','rejected']);
    return $out;
}
function rs_software(array $data,bool $terminal): array {
    $out=[];$seen=[];
    foreach(ra_list($data,'software',12) as $software){
        ra_keys($software,['name','version'],'software version');
        $name=text_field($software,'name',1,100);$version=text_field($software,'version',0,100);
        if(isset($seen[strtolower($name)]))fail(422,'Record each software package/version once.');
        if($terminal&&in_array(strtolower($version),['','unknown','unavailable','not recorded','n/a'],true))fail(422,'Completion needs actual software versions for executed or built work. Report missing versions in progress.');
        $out[]=['name'=>$name,'version'=>$version];$seen[strtolower($name)]=true;
    }
    if($terminal&&!$out)fail(422,'Completed computational/tool work needs the actual software versions.');
    return $out;
}
function rs_material_template(): array {
    $items=[];
    foreach(RS_CATEGORIES as $category)$items[]=['category'=>$category,'status'=>'','details'=>'','refs'=>[],'access_instructions'=>''];
    return ['version'=>1,'work_performed'=>'','items'=>$items,'files'=>[]];
}
function rs_review_template(array $task): array {
    $out=[];
    if(!in_array($task['kind'],['review','contribution_review'],true))return $out;
    foreach(wf_evidence_results($task) as $target)$out[]=['task_id'=>$target['task']['id'],'result_id'=>$target['result']['id'],'verdict'=>'','reason'=>'','checked_refs'=>[]];
    return $out;
}
function rs_file_records(array $data): array {
    $out=[];$bytes=0;$names=[];
    foreach(ra_list($data,'files',12) as $file){
        ra_keys($file,['id','name','category','content','sha256','rights'],'supporting file');
        $id=wf_evidence_id($file);$name=text_field($file,'name',1,80);
        if(isset($out[$id])||isset($names[strtolower($name)])||!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,79}$/D',$name)||str_contains($name,'..')||str_ends_with($name,'.')||preg_match('/^(con|prn|aux|nul|com[0-9]|lpt[0-9])(\.|$)/i',$name))fail(422,'Supporting files need unique IDs and safe plain filenames.');
        $content=$file['content']??null;
        if(!is_string($content)||strlen($content)<1||strlen($content)>30000||str_contains($content,"\0")||!preg_match('//u',$content))fail(422,'Supporting files need 1–30,000 UTF-8 bytes. Report oversized material in progress; do not truncate required content.');
        $bytes+=strlen($content);if($bytes>80000)fail(422,'Supporting files exceed the 80,000-byte budget. Report oversized material; do not truncate it.');
        $hash=text_field($file,'sha256',64,64);
        if(!hash_equals(hash('sha256',$content),$hash))fail(422,'Supporting file checksum mismatch. Preserve exact bytes.');
        $out[$id]=['id'=>$id,'name'=>$name,'category'=>wf_evidence_choice($file,'category',RS_CATEGORIES),'content'=>$content,'sha256'=>$hash,'rights'=>text_field($file,'rights',20,1500)];$names[strtolower($name)]=true;
    }
    return array_values($out);
}
function rs_reference_map(array $row,array $evidence,array $files=[]): array {
    $map=[];
    foreach(['body','methodology','sources','limitations','next_action'] as $key)if(!empty($row[$key]))$map[$key]=$row[$key];
    $map['evidence.sources']=$evidence['sources']??[];
    foreach(['sources','claims','datasets','artifacts','calculations','derivations','tool_builds'] as $kind)foreach($evidence[$kind]??[] as $item){
        $prefix='evidence.'.$kind.'/'.$item['id'];$map[$prefix]=$item;
        foreach($item['files']??[] as $file)$map[$prefix.'/files/'.$file['id']]=$file;
        foreach(['inputs','outputs'] as $quantities)foreach($item[$quantities]??[] as $quantity)$map[$prefix.'/'.$quantities.'/'.$quantity['id']]=$quantity;
    }
    foreach($files as $file)$map['supporting.files/'.$file['id']]=$file;
    return $map;
}
function rs_required_materials(array $evidence): array {
    $required=['methods'=>['methodology']];
    if($evidence['sources']??[])$required['sources']=['evidence.sources'];
    foreach($evidence['datasets']??[] as $dataset)$required['tables'][]='evidence.datasets/'.$dataset['id'];
    foreach(['calculations','tool_builds'] as $kind)foreach($evidence[$kind]??[] as $item){
        $prefix='evidence.'.$kind.'/'.$item['id'];$required['software'][]=$prefix;$required['assumptions'][]=$prefix;
        foreach($item['files'] as $file){
            $category=match($file['role']){'input'=>'inputs','code','source','test'=>'code','output'=>'outputs','environment'=>'software','log'=>'intermediate_results',default=>'methods'};
            $required[$category][]=$prefix.'/files/'.$file['id'];
        }
        if($kind==='calculations'){$required['units'][]=$prefix;$required['formulas'][]=$prefix;}
    }
    foreach($evidence['derivations']??[] as $d)foreach(['inputs','units','formulas','methods','assumptions','outputs','intermediate_results'] as $category)$required[$category][]='evidence.derivations/'.$d['id'];
    return $required;
}
function rs_validate_materials(mixed $raw,array $row,array $evidence,bool $terminal): array {
    if(!is_array($raw)||array_is_list($raw)||($raw['version']??null)!==1)fail(422,'Supporting materials version 1 is required for the work actually performed. Missing or oversized work must use progress.');
    ra_keys($raw,['version','work_performed','items','files'],'supporting materials');
    $work=wf_evidence_choice($raw,'work_performed',['narrative','preparation','executed_analysis','tool_build']);
    if($work==='executed_analysis'&&!($evidence['calculations']??[]))fail(422,'Executed analysis needs retained calculation records, inputs, code and outputs; unexecuted work must use preparation or narrative.');
    if($work==='tool_build'&&!($evidence['tool_builds']??[]))fail(422,'Tool-building work needs a retained tool build.');
    if($work==='preparation'&&(array_filter($evidence['calculations']??[],fn($c)=>$c['status']==='executed')||array_filter($evidence['tool_builds']??[],fn($b)=>$b['status']==='tested')))fail(422,'Preparation-only work cannot claim executed calculations or tested builds.');
    $files=rs_file_records($raw);$map=rs_reference_map($row,$evidence,$files);$required=rs_required_materials($evidence);
    foreach($files as $file)$required[$file['category']][]='supporting.files/'.$file['id'];
    $items=[];
    foreach(wf_evidence_list($raw,'items',count(RS_CATEGORIES)) as $item){
        ra_keys($item,['category','status','details','refs','access_instructions'],'supporting material');
        $category=wf_evidence_choice($item,'category',RS_CATEGORIES);
        if(isset($items[$category]))fail(422,'Duplicate supporting-material category.');
        $status=wf_evidence_choice($item,'status',['supplied','not_applicable','restricted','missing','oversized']);
        $refs=ra_refs($item['refs']??null,$map,$status==='supplied'?1:0,100,'supporting material');
        $access=text_field($item,'access_instructions',$status==='restricted'?20:0,3000);
        if($terminal&&in_array($status,['missing','oversized'],true))fail(422,'Required supporting material is '.$status.': '.$category.'. Preserve useful partial work with progress; do not claim completion.');
        if($terminal&&isset($required[$category])&&(!in_array($status,['supplied','restricted'],true)||array_diff($required[$category],$refs)))fail(422,'Supporting materials must retain and reference all applicable '.$category.' records before completion.');
        if($status==='restricted'&&(!in_array('methodology',$refs,true)||!in_array('sources',$refs,true)))fail(422,'Restricted material needs attribution in sources, access instructions and a reproducible methodology instead of an unauthorized copy.');
        if($status==='not_applicable'&&$refs)fail(422,'Not-applicable material must not claim supplied references.');
        $items[$category]=['category'=>$category,'status'=>$status,'details'=>text_field($item,'details',20,3000),'refs'=>$refs,'access_instructions'=>$access];
    }
    if(count($items)!==count(RS_CATEGORIES))fail(422,'Assess every supporting-material category. State why inapplicable items are unnecessary; never invent code or execution.');
    return ['version'=>1,'work_performed'=>$work,'items'=>array_values($items),'files'=>$files];
}
function rs_validate(array $task,array $data,array $evidence,array $row): array {
    $terminal=in_array($row['outcome'],['completed','inconclusive','verified'],true);
    $materials=rs_validate_materials($data['supporting_materials']??null,$row,$evidence,$terminal);
    $targets=in_array($task['kind'],['review','contribution_review'],true)?wf_evidence_results($task):[];$reviews=[];$seen=[];$corrections=[];
    foreach(ra_list($data,'material_reviews',100) as $review){
        ra_keys($review,['task_id','result_id','verdict','reason','checked_refs'],'material review');
        $rid=text_field($review,'result_id',32,32);$tid=text_field($review,'task_id',32,32);
        if(!isset($targets[$rid])||$targets[$rid]['task']['id']!==$tid||isset($seen[$rid]))fail(422,'Material review needs a unique current accepted research/synthesis result.');
        $target=$targets[$rid]['result'];$e=json_decode($target['metadata'],true)['evidence']??[];$support=$e['supporting_materials']??null;
        $map=rs_reference_map($target,$e,$support['files']??[]);
        $refs=ra_refs($review['checked_refs']??null,$map,1,200,'material review');
        $verdict=wf_evidence_choice($review,'verdict',['complete','needs_correction','unverifiable']);
        $reason=text_field($review,'reason',20,1000);
        if($verdict==='complete'){
            $required=['body','methodology','sources'];
            if($support){foreach($support['items'] as $item){if(in_array($item['status'],['missing','oversized'],true))fail(422,'Incomplete materials need correction, not a complete review.');$required=array_merge($required,$item['refs']);}}
            if(array_diff($required,$refs))fail(422,'A complete material audit must inspect every supplied/access-method reference, including methods and sources.');
        }else{
            if($row['outcome']!=='needs_correction')fail(422,'Missing or unverifiable supporting materials require needs_correction before verification.');
            $corrections[]=['task_id'=>$tid,'result_id'=>$rid,'criterion_id'=>'supporting-materials','reason'=>$reason,'required_change'=>'Supply or correct the materials needed to inspect, reproduce or continue this work: '.$reason];
        }
        $reviews[]=['task_id'=>$tid,'result_id'=>$rid,'verdict'=>$verdict,'reason'=>$reason,'checked_refs'=>$refs];$seen[$rid]=true;
    }
    if($row['outcome']==='verified'&&count($seen)!==count($targets))fail(422,'Verified review must independently assess supporting-material completeness for every current research/synthesis result, including historical gaps.');
    return ['fields'=>['supporting_materials'=>$materials,'material_reviews'=>$reviews],'corrections'=>$corrections];
}
function rs_contributions(string $rid): array {
    $approved=array_column(approved_contributions($rid),null,'id');
    return array_values(array_filter($approved,fn($c)=>!$c['superseded_by']&&(!$c['target_id']||isset($approved[$c['target_id']])&&!$approved[$c['target_id']]['superseded_by'])));
}
/** Current accepted results plus resumable progress; audit-only records never become current evidence. */
function rs_records(string $rid,bool $history=false): array {
    research($rid);$project=wf_project($rid);if(!$project)return [];
    $tasks=array_column(array_filter(wf_tasks($rid),fn($t)=>$t['visibility']==='public'),null,'id');$out=[];
    foreach(all("SELECT * FROM workflow_submissions WHERE research_id=? AND visibility='public' AND admission='accepted' ORDER BY created_at,id",[$rid]) as $result){
        $task=$tasks[$result['task_id']]??null;if(!$task)continue;
        try{wf_read_snapshot($rid,$result['snapshot_id']);}catch(HttpError $error){if($error->status===404)continue;throw $error;}
        $revision=json_decode($result['metadata'],true)['task_revision']??null;
        $accepted=$task['state']==='Completed'&&$task['accepted_id']===$result['id'];
        $current=$task['round_no']==$project['round_no']&&(($accepted&&($revision===null||(int)$revision===(int)$task['revision']))||($result['outcome']==='progress'&&!$task['accepted_id']&&$revision!==null&&(int)$revision===(int)$task['revision']));
        if($history===$current)continue;
        $out[]=['result'=>$result,'task'=>$task,'current'=>$current];
    }
    return $out;
}
function rs_identities(array $source,string $scope): array {
    $doi=strtolower($source['doi']??'');$url=$source['url']??'';
    if(!$doi&&preg_match('~^https?://(?:dx\.)?doi\.org/(10\.[^?#]+)~i',$url,$m))$doi=strtolower(rawurldecode($m[1]));
    $identities=[];
    if($doi)$identities[]='doi:'.$doi;
    if($url){$u=parse_url($url);$identities[]='url:'.strtolower($u['host']).(isset($u['port'])?':'.$u['port']:'').(rtrim($u['path']??'/','/')?:'/').(isset($u['query'])?'?'.$u['query']:'');}
    if(!$identities)$identities[]='local:'.$scope.':'.$source['id'];
    return array_map(fn($identity)=>hash('sha256',$identity.'|edition:'.($source['edition']??'')),$identities);
}
function rs_catalogue(string $rid): array {
    research($rid);$groups=[];$historical=[];$aliases=[];$materials=[];$supplementHistory=[];
    $records=rs_records($rid);
    $currentContributions=array_column(rs_contributions($rid),null,'id');
    foreach(approved_contributions($rid) as $contribution){
        if(!isset($currentContributions[$contribution['id']])){$supplementHistory[]=['result_id'=>$contribution['id'],'task_title'=>$contribution['summary'],'created_at'=>$contribution['created_at'],'contribution'=>true];continue;}
        $records[]=['result'=>$contribution+['metadata'=>json_encode(['evidence'=>$contribution['evidence']?json_decode($contribution['evidence'],true):null]),'outcome'=>$contribution['kind']],'task'=>['id'=>$contribution['id']],'contribution'=>true];
    }
    foreach($records as $record){
        $result=$record['result'];$e=json_decode($result['metadata'],true)['evidence']??null;
        if(!$e){$historical[]=['result_id'=>$result['id'],'contribution'=>!empty($record['contribution']),'note'=>'Historical result: structured source attribution and inspections were not collected.'];continue;}
        if($e['supporting_materials']??null)$materials[]=['result_id'=>$result['id'],'contribution'=>!empty($record['contribution']),'title'=>$record['task']['title']??$result['summary'],'files'=>array_map(fn($file)=>array_intersect_key($file,array_flip(['id','name','category','rights'])),$e['supporting_materials']['files'])];
        foreach($e['sources']??[] as $source){
            $identities=rs_identities($source,$result['id']);$matches=[];
            foreach($identities as $identity)if(isset($aliases[$identity]))$matches[$aliases[$identity]]=true;
            $key=array_key_first($matches)??$identities[0];
            foreach(array_keys($matches) as $other)if($other!==$key){
                $groups[$key]['contributions']=array_merge($groups[$key]['contributions']??[],$groups[$other]['contributions']);unset($groups[$other]);
                foreach($aliases as $alias=>$group)if($group===$other)$aliases[$alias]=$key;
            }
            foreach($identities as $identity)$aliases[$identity]=$key;
            $uses=[];
            foreach($e['claims']??[] as $claim)foreach($claim['links'] as $link)if($link['source_id']===$source['id'])$uses[]=['kind'=>'claim','id'=>$claim['id'],'title'=>$claim['statement'],'locator'=>$link['locator'],'extraction'=>$link['excerpt'],'explanation'=>$link['explanation'],'relation'=>$link['relation']];
            foreach($e['datasets']??[] as $dataset)if(in_array($source['id'],$dataset['source_ids'],true))$uses[]=['kind'=>'dataset','id'=>$dataset['id'],'title'=>$dataset['title'],'provenance'=>$dataset['provenance'],'locations'=>$dataset['source_locations']??[]];
            foreach($e['calculations']??[] as $calculation)if(in_array($source['id'],$calculation['source_ids'],true))$uses[]=['kind'=>'calculation','id'=>$calculation['id'],'title'=>$calculation['title'],'status'=>$calculation['status'],'inputs'=>array_values(array_filter($calculation['inputs'],fn($q)=>($q['source_id']??'')===$source['id']))];
            foreach($e['derivations']??[] as $d)if(in_array($source['id'],$d['source_ids'],true))$uses[]=['kind'=>'derivation','id'=>$d['id'],'title'=>$d['title'],'type'=>'model_derived','version'=>$d['version'],'inputs'=>array_values(array_filter($d['inputs'],fn($q)=>$q['source_id']===$source['id']))];
            foreach(['artifacts','tool_builds'] as $kind)foreach($e[$kind]??[] as $item)if(in_array($source['id'],$item['source_ids'],true))$uses[]=['kind'=>$kind==='artifacts'?'artifact':'tool','id'=>$item['id'],'title'=>$item['title']];
            $groups[$key]['id']=$key;
            $groups[$key]['contributions'][]=['result_id'=>$result['id'],'task_id'=>$record['task']['id'],'created_at'=>$result['created_at'],'display_name'=>$result['display_name'],'outcome'=>$result['outcome'],'contribution'=>!empty($record['contribution']),'source'=>$source,'uses'=>$uses,'historical_metadata'=>!isset($source['authors'])];
        }
    }
    $history=$supplementHistory;foreach(rs_records($rid,true) as $record)$history[]=['result_id'=>$record['result']['id'],'task_title'=>$record['task']['title'],'created_at'=>$record['result']['created_at']];
    return ['sources'=>array_values($groups),'historical_metadata'=>$historical,'audit_history'=>$history,'materials'=>$materials,'notice'=>'Only eligible current accepted results, current-revision progress and approved current supplementary contributions supply resources. Superseded work is audit history. Attribution, access and inspections are contributor declarations, never inferred or independently authenticated.'];
}
function rs_read(string $rid,string $resultId): array {
    foreach(rs_records($rid) as $record)if($record['result']['id']===$resultId)return $record['result'];
    fail(404,'Resources are unavailable or superseded.');
}
function rs_route(array $r,string $suffix,string $method): bool {
    if($suffix==='resources'){
        if($method!=='GET')fail(405,'Resources are read-only.');
        render('research-resources',['title'=>'Resources & sources','r'=>$r,'resources'=>rs_catalogue($r['id'])]);return true;
    }
    if(!preg_match('~^(results|contributions)/([a-f0-9]{32})/resources/(datasets/([a-z][a-z0-9_-]{0,39})\.csv|files/([a-z][a-z0-9_-]{0,39}))$~D',$suffix,$m))return false;
    if($method!=='GET')fail(405,'Supporting material is read-only.');
    if($m[1]==='results'){$result=rs_read($r['id'],$m[2]);$e=json_decode($result['metadata'],true)['evidence']??[];}
    else{$eligible=array_column(rs_contributions($r['id']),null,'id');$result=$eligible[$m[2]]??fail(404,'Supporting contribution is unavailable or superseded.');$e=json_decode($result['evidence']??'null',true)??[];}
    header("Content-Security-Policy: sandbox; default-src 'none'");header('Cache-Control: no-store');
    if(!empty($m[4]))foreach($e['datasets']??[] as $dataset)if($dataset['id']===$m[4]){header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="'.$dataset['id'].'.csv"');echo ra_csv($dataset);return true;}
    if(!empty($m[5]))foreach($e['supporting_materials']['files']??[] as $file)if($file['id']===$m[5]){
        if(!hash_equals($file['sha256'],hash('sha256',$file['content'])))fail(409,'Stored supporting-file checksum mismatch.');
        header('Content-Type: text/plain; charset=utf-8');header('Content-Disposition: attachment; filename="'.$file['name'].'"');echo $file['content'];return true;
    }
    fail(404,'Supporting material not found.');
}
