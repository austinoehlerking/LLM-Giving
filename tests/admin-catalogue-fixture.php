<?php
require_once __DIR__.'/workflow-fixture.php';
require_once ROOT.'/src/admin-catalogue.php';
/** Deliberately synthetic local records for count and discovery boundaries only. */
function ac_fixture_seed(): array {
    require_admin();$category=insert('categories',['name'=>'Catalogue fixture '.uid(),'enabled'=>1]);$ids=[];
    foreach(['usage','queue','private'] as $key){
        $c=create_research(['title'=>'[QA] Catalogue '.$key.' '.uid(),'question'=>'Synthetic local administration and discovery checks only; this record establishes no scientific result.','category_id'=>$category,'terms'=>1]);
        $ids[$key]=$c['id'];if($key!=='private')moderate('request',$c['id'],'Approved','Local synthetic catalogue and usage regression fixture.');
    }
    $task=wf_tasks($ids['usage'])[0];$result=wf_test_submit($ids['usage'],$task['id'],wf_test_payload($ids['usage'],$task),hash('sha256',uid()));
    $original=one('SELECT * FROM workflow_submissions WHERE id=?',[$result]);
    // Explicit fixture states exercise two independent count dimensions, without pretending these are research.
    foreach([['accepted','removed'],['rejected','public'],['quarantined','public']] as [$admission,$visibility]){
        $row=$original;$row['id']=uid();$row['nonce']=uid();$row['payload_hash']=hash('sha256',uid());$row['admission']=$admission;$row['visibility']=$visibility;
        insert('workflow_submissions',$row);
    }
    foreach(['Approved','Pending Approval','Rejected','Removed'] as $moderation){
        $cid=create_contribution($ids['usage'],rs_test_supplement(['kind'=>'Progress update','summary'=>'Synthetic supplementary '.$moderation,'body'=>'This local synthetic record tests operational counting only and establishes no scientific result.','terms'=>1,'metadata'=>['provider'=>'Synthetic QA','model'=>'gpt-6-astra','reasoning'=>'max','harness'=>'Local fixture']]));
        query('UPDATE contributions SET moderation=? WHERE id=?',[$moderation,$cid]);
    }
    return ['ids'=>$ids,'category'=>$category,'slug'=>research($ids['usage'])['slug'],'result'=>$result,'queue_task'=>wf_tasks($ids['queue'])[0]['id']];
}
function ac_fixture_cleanup(array $f): void {
    foreach($f['ids'] as $id)if(one('SELECT id FROM research WHERE id=?',[$id]))purge_request($id,$id);
    query('DELETE FROM categories WHERE id=?',[$f['category']]);
}
