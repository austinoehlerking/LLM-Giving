<?php
// Visual specifications are data, never submitted HTML, CSS, JavaScript or formulas.
function ra_keys(array $item,array $allowed,string $name): void {
    if(array_diff(array_keys($item),$allowed))fail(422,'Unsupported '.$name.' field. Use the structured visual format; executable markup and remote assets are not accepted.');
}
function ra_list(array $data,string $key,int $max): array {return wf_evidence_list([$key=>$data[$key]??[]],$key,$max);}
function ra_refs(mixed $ids,array $known,int $min,int $max,string $label): array {
    if(!is_array($ids)||!array_is_list($ids)||count($ids)<$min||count($ids)>$max)fail(422,$label.' must be a bounded list of reference IDs.');
    $out=[];foreach($ids as $id){if(!is_string($id)||!isset($known[$id])||in_array($id,$out,true))fail(422,'Unknown or duplicate '.$label.' reference.');$out[]=$id;}return $out;
}
function ra_number(mixed $value,string $label,float $min=-1.0e12,float $max=1.0e12): int|float {
    if((!is_int($value)&&!is_float($value))||!is_finite((float)$value)||$value<$min||$value>$max)fail(422,$label.' must be a finite number in the supported range.');return $value;
}
function ra_provenance(array $item): string {return wf_evidence_choice($item,'provenance',['observed','derived','illustrative','simulated']);}
function ra_observed_sources(array $ids,array $sources,string $label): void {
    if(!$ids)fail(422,$label.' needs source provenance.');foreach($ids as $id)if($sources[$id]['access']==='unavailable')fail(422,$label.' cannot use an unavailable source as inspected data provenance.');
}
function ra_review_targets(array $task): array {
    if($task['kind']!=='review')return [];$out=[];
    foreach(wf_evidence_results($task) as $target)foreach(json_decode($target['result']['metadata'],true)['evidence']['artifacts']??[] as $artifact){
        $key=$target['task']['id'].'/'.$target['result']['id'].'/'.$artifact['id'];$out[$key]=$target+['artifact'=>$artifact];
    }return $out;
}
function ra_review_template(array $task): array {
    $out=[];foreach(ra_review_targets($task) as $target)$out[]=['task_id'=>$target['task']['id'],'result_id'=>$target['result']['id'],'artifact_id'=>$target['artifact']['id'],'verdict'=>'','reason'=>''];return $out;
}
function ra_validate(array $task,array $data,array $evidence,array $row): array {
    $sources=array_column($evidence['sources'],null,'id');$claims=array_column($evidence['claims'],null,'id');$datasets=[];$artifacts=[];
    foreach(ra_list($data,'datasets',8) as $dataset){
        ra_keys($dataset,['id','title','provenance','source_ids','method','limitations','uncertainty','columns','rows'],'dataset');
        $id=wf_evidence_id($dataset);if(isset($datasets[$id]))fail(422,'Duplicate dataset ID.');
        $d=['id'=>$id,'title'=>text_field($dataset,'title',5,140),'provenance'=>ra_provenance($dataset),
            'source_ids'=>ra_refs($dataset['source_ids']??null,$sources,0,20,'dataset source'),
            'method'=>text_field($dataset,'method',20,3000),'limitations'=>text_field($dataset,'limitations',20,3000),'uncertainty'=>text_field($dataset,'uncertainty',20,2000),'columns'=>[],'rows'=>[]];
        if(in_array($d['provenance'],['observed','derived'],true))ra_observed_sources($d['source_ids'],$sources,'Observed/derived dataset');
        $keys=[];foreach(ra_list($dataset,'columns',8) as $column){
            ra_keys($column,['key','label','type','unit'],'data column');$key=wf_evidence_id(['id'=>$column['key']??'']);if(isset($keys[$key]))fail(422,'Duplicate dataset column key.');$keys[$key]=true;
            $type=wf_evidence_choice($column,'type',['number','text']);$d['columns'][]=['key'=>$key,'label'=>text_field($column,'label',1,80),'type'=>$type,'unit'=>text_field($column,'unit',$type==='number'?1:0,60)];
        }
        if(count($d['columns'])<2)fail(422,'A dataset needs 2–8 typed columns.');
        $rows=$dataset['rows']??null;if(!is_array($rows)||!array_is_list($rows)||!$rows||count($rows)>100)fail(422,'Dataset rows must be a list with 1–100 rows.');
        foreach($rows as $cells){
            if(!is_array($cells)||!array_is_list($cells)||count($cells)!==count($d['columns']))fail(422,'Every data row must match the column count.');
            $clean=[];foreach($cells as $i=>$cell){if($cell===null){$clean[]=null;continue;}if($d['columns'][$i]['type']==='number')$clean[]=ra_number($cell,'Data value');else{if(!is_string($cell))fail(422,'Text data cells must be strings or null.');$clean[]=text_field(['value'=>$cell],'value',0,160);}}$d['rows'][]=$clean;
        }$datasets[$id]=$d;
    }
    foreach(ra_list($data,'artifacts',6) as $artifact){
        ra_keys($artifact,['id','title','kind','purpose','summary','provenance','claim_ids','source_ids','dataset_ids','assumptions','limitations','method','control_label','panels','states'],'artifact');
        $id=wf_evidence_id($artifact);if(isset($artifacts[$id]))fail(422,'Duplicate visual artifact ID.');
        $a=['id'=>$id,'title'=>text_field($artifact,'title',5,100),'kind'=>wf_evidence_choice($artifact,'kind',['chart','diagram','animation','interactive']),
            'purpose'=>text_field($artifact,'purpose',20,2000),'summary'=>text_field($artifact,'summary',20,3000),'provenance'=>ra_provenance($artifact),
            'claim_ids'=>ra_refs($artifact['claim_ids']??null,$claims,1,20,'artifact claim'),'source_ids'=>ra_refs($artifact['source_ids']??null,$sources,0,40,'artifact source'),
            'dataset_ids'=>ra_refs($artifact['dataset_ids']??null,$datasets,0,8,'artifact dataset'),'assumptions'=>text_field($artifact,'assumptions',20,3000),
            'limitations'=>text_field($artifact,'limitations',20,3000),'method'=>text_field($artifact,'method',20,3000),'control_label'=>text_field($artifact,'control_label',0,100),'panels'=>[],'states'=>[]];
        foreach($a['claim_ids'] as $claimId)if(array_diff(array_column($claims[$claimId]['links'],'source_id'),$a['source_ids']))fail(422,'Artifact source_ids must include its linked claim sources, including contrary evidence.');
        if(in_array($a['provenance'],['observed','derived'],true)&&!array_filter($a['source_ids'],fn($sourceId)=>$sources[$sourceId]['access']!=='unavailable'))fail(422,'Observed/derived artifact needs inspected source provenance.');
        foreach($a['dataset_ids'] as $did){
            $dataset=$datasets[$did];if(array_diff($dataset['source_ids'],$a['source_ids']))fail(422,'Artifact source_ids must include the sources of its backing datasets.');
            if(in_array($dataset['provenance'],['illustrative','simulated'],true)&&in_array($a['provenance'],['observed','derived'],true))fail(422,'Illustrative or simulated data cannot be labeled observed/derived in a visual.');
            if($dataset['provenance']==='simulated'&&$a['provenance']!=='simulated')fail(422,'A visual containing simulated data must be labeled simulated.');
        }
        $panelIds=[];$usedDatasets=[];
        foreach(ra_list($artifact,'panels',3) as $panel){
            ra_keys($panel,['id','title','type','dataset_id','chart_type','x','y','nodes','edges'],'visual panel');$pid=wf_evidence_id($panel);if(isset($panelIds[$pid]))fail(422,'Duplicate panel ID.');
            $p=['id'=>$pid,'title'=>text_field($panel,'title',3,100),'type'=>wf_evidence_choice($panel,'type',['chart','diagram'])];
            if($p['type']==='chart'){
                ra_keys($panel,['id','title','type','dataset_id','chart_type','x','y'],'chart panel');
                $did=text_field($panel,'dataset_id',1,40);if(!in_array($did,$a['dataset_ids'],true))fail(422,'Chart must use a declared backing dataset.');
                $columns=array_column($datasets[$did]['columns'],null,'key');$x=text_field($panel,'x',1,40);if(!isset($columns[$x]))fail(422,'Unknown chart x column.');
                $p+=['dataset_id'=>$did,'chart_type'=>wf_evidence_choice($panel,'chart_type',['bar','line','scatter']),'x'=>$x,'y'=>ra_refs($panel['y']??null,$columns,1,3,'chart series')];
                if(in_array($p['chart_type'],['line','scatter'],true)&&$columns[$x]['type']!=='number')fail(422,'Line/scatter x axes require numeric data.');
                $units=[];foreach($p['y'] as $y){if($columns[$y]['type']!=='number'||$y===$x)fail(422,'Chart y series need distinct numeric columns.');$units[]=$columns[$y]['unit'];}if(count(array_unique($units))!==1)fail(422,'Series on one y axis must use the same units.');
                $usedDatasets[$did]=true;
            }else{
                ra_keys($panel,['id','title','type','nodes','edges'],'diagram panel');$nodes=[];$edges=[];
                foreach(ra_list($panel,'nodes',16) as $node){ra_keys($node,['id','label','detail','x','y'],'diagram node');$nid=wf_evidence_id($node);if(isset($nodes[$nid]))fail(422,'Duplicate diagram node.');$nodes[$nid]=['id'=>$nid,'label'=>text_field($node,'label',1,60),'detail'=>text_field($node,'detail',10,1000),'x'=>ra_number($node['x']??null,'Node x',12,88),'y'=>ra_number($node['y']??null,'Node y',14,86)];}
                if(!$nodes)fail(422,'A diagram needs nodes.');
                $placed=[];foreach($nodes as $node){foreach($placed as $other)if(abs($node['x']-$other['x'])<20&&abs($node['y']-$other['y'])<20)fail(422,'Space diagram nodes at least 20 percentage points apart on one axis.');$placed[]=$node;}
                foreach(ra_list($panel,'edges',30) as $edge){ra_keys($edge,['from','to','label'],'diagram edge');$from=text_field($edge,'from',1,40);$to=text_field($edge,'to',1,40);if(!isset($nodes[$from],$nodes[$to])||$from===$to)fail(422,'Diagram edges must reference two distinct nodes.');$edges[]=['from'=>$from,'to'=>$to,'label'=>text_field($edge,'label',0,80)];}
                $p+=['nodes'=>array_values($nodes),'edges'=>$edges];
            }$panelIds[$pid]=$p;$a['panels'][]=$p;
        }
        if(!$a['panels'])fail(422,'An artifact needs at least one visual panel.');
        if(in_array($a['kind'],['chart','diagram'],true)&&(count($a['panels'])!==1||$a['panels'][0]['type']!==$a['kind']))fail(422,'Static chart/diagram artifacts need one matching panel.');
        if(array_diff($a['dataset_ids'],array_keys($usedDatasets)))fail(422,'Every declared backing dataset must be used by a chart panel.');
        foreach(ra_list($artifact,'states',12) as $state){
            ra_keys($state,['id','label','explanation','filters','highlights'],'visual state');$sid=wf_evidence_id($state);if(isset($a['states'][$sid]))fail(422,'Duplicate visual state.');
            $s=['id'=>$sid,'label'=>text_field($state,'label',1,80),'explanation'=>text_field($state,'explanation',20,1500),'filters'=>[],'highlights'=>[]];$seen=[];
            foreach(ra_list($state,'filters',12) as $filter){ra_keys($filter,['panel_id','column','value'],'state filter');$pid=text_field($filter,'panel_id',1,40);$column=text_field($filter,'column',1,40);if(!isset($panelIds[$pid])||$panelIds[$pid]['type']!=='chart')fail(422,'State filter must target a chart panel.');$d=$datasets[$panelIds[$pid]['dataset_id']];$cols=array_column($d['columns'],null,'key');$key=$pid.'/'.$column;if(!isset($cols[$column])||isset($seen[$key]))fail(422,'Unknown or duplicate filter column.');$seen[$key]=true;$value=$filter['value']??null;if($value===null)fail(422,'State filters need an actual data value.');if($cols[$column]['type']==='number')$value=ra_number($value,'Filter value');elseif(!is_string($value))fail(422,'Text state filters require a string.');else $value=text_field(['value'=>$value],'value',1,160);$s['filters'][]=['panel_id'=>$pid,'column'=>$column,'value'=>$value];}
            $seen=[];foreach(ra_list($state,'highlights',3) as $highlight){ra_keys($highlight,['panel_id','node_ids'],'state highlight');$pid=text_field($highlight,'panel_id',1,40);if(!isset($panelIds[$pid])||$panelIds[$pid]['type']!=='diagram'||isset($seen[$pid]))fail(422,'Highlight must target a unique diagram panel.');$seen[$pid]=true;$s['highlights'][]=['panel_id'=>$pid,'node_ids'=>ra_refs($highlight['node_ids']??null,array_column($panelIds[$pid]['nodes'],null,'id'),1,16,'highlight node')];}
            $a['states'][$sid]=$s;
        }$a['states']=array_values($a['states']);
        if(in_array($a['kind'],['animation','interactive'],true)){if(count($a['states'])<2||strlen($a['control_label'])<5)fail(422,'Animations and interactive pages need 2–12 described states and a control label.');}
        elseif($a['states']||$a['control_label']!=='')fail(422,'Static artifacts have no state controls.');
        $marks=0;foreach(ra_states($a) as $state)foreach($a['panels'] as $panel)if($panel['type']==='chart'){
            $d=$datasets[$panel['dataset_id']];$rows=ra_rows($d,$panel,$state);$cols=array_column($d['columns'],'key');$xi=array_search($panel['x'],$cols,true);$plotted=0;
            foreach($panel['y'] as $y){$yi=array_search($y,$cols,true);foreach($rows as $cells)if($cells[$xi]!==null&&$cells[$yi]!==null)$plotted++;}
            if(!$plotted)fail(422,'Every visual state must contain plottable data. Missing values are gaps, never zero.');$marks+=$plotted;
        }
        if($marks>3000)fail(422,'Reduce visual states or data points to the 3,000-mark rendering budget.');
        $artifacts[$id]=$a;
    }
    $targets=ra_review_targets($task);$seen=[];$audits=[];$corrections=[];
    foreach(ra_list($data,'artifact_reviews',100) as $audit){
        ra_keys($audit,['task_id','result_id','artifact_id','verdict','reason'],'artifact review');$tid=text_field($audit,'task_id',32,32);$rid=text_field($audit,'result_id',32,32);$aid=text_field($audit,'artifact_id',1,40);$key=$tid.'/'.$rid.'/'.$aid;
        if(!isset($targets[$key])||isset($seen[$key]))fail(422,'Artifact review references an unknown, stale, foreign or duplicate artifact.');$seen[$key]=true;
        $verdict=wf_evidence_choice($audit,'verdict',['supported','needs_correction','unverifiable']);$reason=text_field($audit,'reason',20,3000);$audits[]=['task_id'=>$tid,'result_id'=>$rid,'artifact_id'=>$aid,'verdict'=>$verdict,'reason'=>$reason];
        if($verdict!=='supported'){
            if($row['outcome']!=='needs_correction')fail(422,'Misleading or unverifiable visual artifacts require outcome needs_correction.');
            $corrections[]=['task_id'=>$tid,'result_id'=>$rid,'criterion_id'=>'artifact-standard','artifact_id'=>$aid,'reason'=>substr($reason,0,1000),'required_change'=>'Correct visual artifact '.$aid.': '.substr($reason,0,2200).' Recheck data, source links, assumptions, labels and conclusions; propagate changes to dependent work.'];
        }
    }
    if($row['outcome']==='verified'&&count($seen)!==count($targets))fail(422,'Verified review must audit every current research/synthesis visual artifact, including its data, assumptions and limitations.');
    return ['fields'=>['datasets'=>array_values($datasets),'artifacts'=>array_values($artifacts),'artifact_reviews'=>$audits],'corrections'=>$corrections];
}
function ra_states(array $artifact): array {return $artifact['states']?:[['id'=>'default','label'=>'Static view','explanation'=>'','filters'=>[],'highlights'=>[]]];}
function ra_rows(array $dataset,array $panel,array $state): array {
    $columns=array_column($dataset['columns'],'key');return array_values(array_filter($dataset['rows'],function($row)use($columns,$panel,$state){foreach($state['filters'] as $filter)if($filter['panel_id']===$panel['id']){$cell=$row[array_search($filter['column'],$columns,true)];if($cell===null||(is_int($filter['value'])||is_float($filter['value'])?(float)$cell!==(float)$filter['value']:$cell!==$filter['value']))return false;}return true;}));
}
function ra_current(array $result): bool {
    research($result['research_id']);if($result['admission']!=='accepted'||($result['visibility']??'public')!=='public')return false;
    $task=wf_task($result['research_id'],$result['task_id']);$project=wf_project($result['research_id']);
    if(!$project||$task['visibility']!=='public'||$task['round_no']!=$project['round_no']||$task['state']!=='Completed'||$task['accepted_id']!==$result['id'])return false;
    try{wf_read_snapshot($result['research_id'],$result['snapshot_id']);}catch(HttpError $error){if($error->status===404)return false;throw $error;}return true;
}
function ra_read(string $rid,string $resultId,string $artifactId): array {
    research($rid);$result=one("SELECT * FROM workflow_submissions WHERE id=? AND research_id=? AND visibility='public' AND admission='accepted'",[$resultId,$rid])??fail(404,'Visual artifact unavailable.');
    if(!ra_current($result))fail(404,'This visual artifact is no longer a current accepted result.');$evidence=json_decode($result['metadata'],true)['evidence']??[];
    foreach($evidence['artifacts']??[] as $artifact)if($artifact['id']===$artifactId){$datasets=array_column($evidence['datasets'],null,'id');return ['result'=>array_intersect_key($result,array_flip(['id','research_id','task_id','snapshot_id','created_at','admission','outcome','display_name'])),'artifact'=>$artifact,'datasets'=>array_intersect_key($datasets,array_flip($artifact['dataset_ids'])),'claims'=>array_values(array_filter($evidence['claims'],fn($claim)=>in_array($claim['id'],$artifact['claim_ids'],true))),'sources'=>array_values(array_filter($evidence['sources'],fn($source)=>in_array($source['id'],$artifact['source_ids'],true)))];}
    fail(404,'Visual artifact not found.');
}
function ra_catalogue(string $rid): array {
    research($rid);$out=[];foreach(wf_tasks($rid) as $task)if($task['visibility']==='public'&&$task['state']==='Completed'){
        $result=one("SELECT * FROM workflow_submissions WHERE id=? AND research_id=? AND visibility='public' AND admission='accepted'",[$task['accepted_id'],$rid]);if(!$result||!ra_current($result))continue;
        foreach(json_decode($result['metadata'],true)['evidence']['artifacts']??[] as $artifact)$out[]=['result_id'=>$result['id'],'task_title'=>$task['title'],'created_at'=>$result['created_at'],'artifact'=>$artifact];
    }return $out;
}
function ra_csv(array $dataset): string {
    $stream=fopen('php://temp','r+');$safe=function($value){if($value===null)return '';if(is_string($value)&&preg_match('/^(?:[\t\r\n]|[\s]*[=+@-])/u',$value))return "'".$value;return $value;};
    fputcsv($stream,array_map(fn($column)=>$safe($column['label'].($column['unit']?' ('.$column['unit'].')':'')),$dataset['columns']),',','"','');foreach($dataset['rows'] as $row)fputcsv($stream,array_map($safe,$row),',','"','');rewind($stream);$csv=stream_get_contents($stream);fclose($stream);return $csv;
}
function ra_route(array $r,string $suffix,string $method): bool {
    if(!preg_match('~^results/([a-f0-9]{32})/artifacts/([a-z][a-z0-9_-]{0,39})(?:/(spec\.json|data/([a-z][a-z0-9_-]{0,39})\.csv|panels/([a-z][a-z0-9_-]{0,39})\.svg))?$~D',$suffix,$m))return false;
    if($method!=='GET')fail(405,'Visual artifacts are read-only.');$record=ra_read($r['id'],$m[1],$m[2]);$artifact=$record['artifact'];$download=$m[3]??'';
    if($download!==''){
        header("Content-Security-Policy: sandbox; default-src 'none'");
        if($download==='spec.json'){header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="'.$artifact['id'].'.json"');echo json_encode(['format'=>'llm-giving-visual-v1','research_id'=>$r['id'],'result_id'=>$m[1],'artifact'=>$artifact,'datasets'=>array_values($record['datasets']),'claims'=>$record['claims'],'sources'=>$record['sources']],JSON_PRETTY_PRINT|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES);return true;}
        if(!empty($m[4])){$dataset=$record['datasets'][$m[4]]??fail(404,'Backing dataset unavailable.');header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="'.$dataset['id'].'.csv"');echo ra_csv($dataset);return true;}
        foreach($artifact['panels'] as $panel)if($panel['id']===($m[5]??'')){
            $stateId=text_field($_GET,'state',0,40);$states=ra_states($artifact);$state=$states[0];if($stateId!==''){$matched=false;foreach($states as $s)if($s['id']===$stateId){$state=$s;$matched=true;}if(!$matched)fail(404,'Visual state unavailable.');}
            header('Content-Type: image/svg+xml; charset=utf-8');header('Content-Disposition: attachment; filename="'.$artifact['id'].'-'.$panel['id'].'.svg"');echo ra_svg($artifact,$panel,$record['datasets'],$state);return true;
        }fail(404,'Visual panel unavailable.');
    }
    render('research-artifact',['title'=>$artifact['title'],'r'=>$r]+$record);return true;
}
