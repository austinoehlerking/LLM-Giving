<?php
require __DIR__.'/bootstrap.php';require_once ROOT.'/src/view.php';require_once __DIR__.'/resources-fixture.php';$_SESSION['admin']=1;
function reader_test_create(): array {
    $f=rs_test_create();$rid=$f['id'];
    query('UPDATE research SET title=? WHERE id=?',['[Resources QA] Effects of plastic pollution on the biological carbon pump in the deep ocean — reader navigation and complete evidence inventory',$rid]);
    $result=one('SELECT metadata FROM workflow_submissions WHERE id=?',[$f['result_id']]);$meta=json_decode($result['metadata'],true);
    $aid=['version'=>1,'question_helped'=>'How can a reader inspect this synthetic mass conversion?','intended_user'=>'Readers testing a local source-to-result path.','finding'=>'In this synthetic example, 2 kg is converted to 2000 g.','why_it_matters'=>'This local example demonstrates an inspectable unit conversion, not a scientific discovery.','increment'=>'A trace from the supplied original mass row to the retained calculation is available.','established'=>'Only the arithmetic relationship in this synthetic fixture is established by its author.','uncertain'=>'No real-world measurement or scientific uncertainty was investigated.','reusable_output'=>'The retained synthetic input, PHP code and output can be inspected.','next_use'=>'Read the original row and retained method before reusing this synthetic conversion.'];$meta['usefulness']=$aid;
    for($i=1;$i<=24;$i++){$source=rs_test_source('reader-extra-'.$i);$source['title']='Synthetic index source '.str_pad((string)$i,2,'0',STR_PAD_LEFT);$source['url']='https://example.invalid/reader-source/'.$i;$source['type']='other';$source['edition']='';$source['use']='background';$meta['evidence']['sources'][]=$source;}
    query('UPDATE workflow_submissions SET metadata=?,display_name=?,created_at=? WHERE id=?',[json_encode($meta),'Synthetic reader author','2026-10-27 23:59:00',$f['result_id']]);
    touch_context($rid);return $f+['aid'=>$aid,'title'=>research($rid)['title'],'source_count'=>count(rs_catalogue($rid)['sources'])];
}
if(realpath($_SERVER['SCRIPT_FILENAME'])!==__FILE__)return;
$action=$argv[1]??'';
if($action==='create'){echo json_encode(reader_test_create());exit;}
$rid=$argv[2]??'';if(!one("SELECT id FROM research WHERE id=? AND title LIKE '[Resources QA]%'",[$rid]))exit(2);
if($action==='cleanup'){purge_request($rid,$rid);exit;}
if($action==='hold'){$id=$argv[3];query("UPDATE workflow_submissions SET admission='quarantined',body='HELD_READER_PRIVATE_MARKER' WHERE research_id=? AND id=?",[$rid,$id]);touch_context($rid);exit;}
if($action==='legacy'){$id=$argv[3];$meta=json_decode(one('SELECT metadata FROM workflow_submissions WHERE research_id=? AND id=?',[$rid,$id])['metadata'],true);unset($meta['usefulness']);query('UPDATE workflow_submissions SET metadata=? WHERE research_id=? AND id=?',[json_encode($meta),$rid,$id]);touch_context($rid);exit;}
exit(2);
