<?php
require __DIR__.'/happy-path-reader-state.php';
$checks=[];function reader_check(bool $ok,string $message): void {global $checks;if(!$ok)throw new RuntimeException($message);$checks[]=$message;}
function reader_denied(callable $read,string $message): void {try{$read();}catch(HttpError $e){reader_check($e->status===404,$message);return;}throw new RuntimeException($message);}
$f=reader_test_create();$rid=$f['id'];
try{
    $r=research($rid);$items=reader_findings_data($rid);$found=array_column($items,null,'id');
    reader_check($found[$f['result_id']]['aid']===$f['aid']&&$found[$f['result_id']]['finding']===$f['aid']['finding'],'Reader preserves every authored reading-aid field and attribution without reinterpretation');
    reader_check(!isset($found[$f['progress_id']])&&isset($found[$f['contribution_id']]),'Only current completed research and approved supplementary work enter the findings reader; progress is not a finding');
    $catalogue=rs_catalogue($rid);$index=rs_source_index($catalogue,[]);$next=rs_source_index($catalogue,['page'=>'2']);
    reader_check($index['source_count']===count($catalogue['sources'])&&$index['pages']===3&&count($index['sources'])===12&&count($next['sources'])===12,'Compact source index pages all distinct sources without dropping retained evidence');
    $all=[];for($p=1;$p<=$index['pages'];$p++)$all=array_merge($all,array_column(rs_source_index($catalogue,['page'=>(string)$p])['sources'],'id'));
    reader_check($all===array_column($catalogue['sources'],'id'),'Every original source appears once across source pages');
    $searched=rs_source_index($catalogue,['q'=>'Original synthetic mass table, row mass','use'=>'used']);
    reader_check(count($searched['sources'])===1&&count($searched['sources'][0]['contributions'])===3,'Search reaches extracted claims and keeps all differing attributions for the matching identity');
    reader_check($index['contribution_count']===4&&$index['source_record_count']> $index['source_count'],'Counts distinguish source identities, contributing results and repeated attribution records');
    foreach(['Paused','Blocked','Needs Clarification','Review Complete'] as $status){query('UPDATE research SET status=? WHERE id=?',[$status,$rid]);$state=reader_workflow_state($r);reader_check($state['task']===null&&$state['unblocker']!=='','Unavailable workflow '.$status.' supplies an honest next action and responsible role');}
    query("UPDATE research SET status='Needs Research' WHERE id=?",[$rid]);query("UPDATE workflow_submissions SET admission='quarantined',body='HELD_READER_PRIVATE_MARKER' WHERE id=?",[$f['progress_id']]);
    $state=reader_workflow_state($r);reader_check($state['label']==='Awaiting admin decision'&&$state['task']===null&&str_contains($state['unblocker'],'administrator'),'Effective hold overrides stored stage and exposes only generic administrator guidance');
    reader_check(!str_contains(json_encode([reader_findings_data($rid),rs_catalogue($rid),$state]),'HELD_READER_PRIVATE_MARKER'),'Held content is absent from findings, resources and availability');
    $meta=json_decode(one('SELECT metadata FROM workflow_submissions WHERE id=?',[$f['result_id']])['metadata'],true);$meta['usefulness']['version']=2;query('UPDATE workflow_submissions SET metadata=? WHERE id=?',[json_encode($meta),$f['result_id']]);$unsupported=array_column(reader_findings_data($rid),null,'id')[$f['result_id']];reader_check($unsupported['aid']===null&&$unsupported['finding']===one('SELECT reason FROM workflow_submissions WHERE id=?',[$f['result_id']])['reason'],'Unknown reading-aid versions fall back to original authored text without interpreting a new contract');unset($meta['usefulness']);query('UPDATE workflow_submissions SET metadata=? WHERE id=?',[json_encode($meta),$f['result_id']]);$legacy=array_column(reader_findings_data($rid),null,'id')[$f['result_id']];$original=one('SELECT reason,limitations FROM workflow_submissions WHERE id=?',[$f['result_id']]);
    reader_check($legacy['aid']===null&&$legacy['established']===null&&$legacy['finding']===$original['reason']&&$legacy['uncertain']===$original['limitations'],'Historical reading gaps stay explicit and original conclusion/limitations remain verbatim');
    foreach(['admission','visibility','snapshot','task'] as $boundary){
        if($boundary==='admission')query("UPDATE workflow_submissions SET admission='rejected' WHERE id=?",[$f['result_id']]);
        if($boundary==='visibility')query("UPDATE workflow_submissions SET visibility='removed' WHERE id=?",[$f['result_id']]);
        if($boundary==='snapshot')query('UPDATE workflow_snapshots SET public_allowed=0 WHERE id=?',[$f['snapshot_id']]);
        if($boundary==='task')query("UPDATE workflow_tasks SET visibility='removed' WHERE id=?",[$f['task_id']]);
        reader_check(!in_array($f['result_id'],array_column(reader_findings_data($rid),'id'),true),'Reader respects '.$boundary.' revocation');
        query("UPDATE workflow_submissions SET admission='accepted',visibility='public' WHERE id=?",[$f['result_id']]);query('UPDATE workflow_snapshots SET public_allowed=1 WHERE id=?',[$f['snapshot_id']]);query("UPDATE workflow_tasks SET visibility='public' WHERE id=?",[$f['task_id']]);
    }
    query("UPDATE research SET status='Withdrawn' WHERE id=?",[$rid]);reader_denied(fn()=>reader_findings_data($rid),'Withdrawn parent denies findings');reader_denied(fn()=>reader_workflow_state($r),'Withdrawn parent denies availability');reader_denied(fn()=>rs_catalogue($rid),'Withdrawn parent denies source catalogue');
    file_put_contents(storage('qa/happy-path-reader-integration.json'),json_encode(['status'=>'PASS','checks'=>$checks],JSON_PRETTY_PRINT));echo count($checks)." reader findings, availability and source-index checks passed.\n";
}finally{if(one('SELECT id FROM research WHERE id=?',[$rid]))purge_request($rid,$rid);}
