<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';require __DIR__.'/fork-fixture.php';
session_start_safe();$_SESSION['admin']=1;$_SERVER['REMOTE_ADDR']='fork-discovery-'.uid();
$checks=[];function fd_ok(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
db()->beginTransaction();
try{
    $category=(int)insert('categories',['name'=>'Fork pagination '.uid(),'enabled'=>1]);
    $onlyFork=(int)insert('categories',['name'=>'Fork-only category '.uid(),'enabled'=>1]);
    $tag='fork-discovery-'.uid();$base=['tag'=>$tag,'category'=>(string)$category];$normal=[];
    for($i=0;$i<14;$i++){
        $r=create_research(['title'=>'[QA] Listing sibling '.sprintf('%02d',$i).' '.uid(),'question'=>'Synthetic public discovery sibling for pagination and category regression.','category_id'=>$category,'tags'=>$tag,'terms'=>1]);
        moderate('request',$r['id'],'Approved','Synthetic discovery sibling.');$normal[]=$r['id'];
        query('UPDATE research SET created_at=? WHERE id=?',[sprintf('2026-01-%02d 12:00:00',$i+1),$r['id']]);
    }
    $parent=rf_test_parent();$draft=rf_prepare($parent['id']);$needle=uid();
    $data=rf_test_child_data($draft);$data['title']='[QA] Related question '.$needle;$data['category_id']=$category;$data['tags']=$tag;
    $child=rf_create($parent['id'],$data);moderate('request',$child['id'],'Approved','Synthetic discovery fork.');
    query("UPDATE research SET created_at='2030-01-01 00:00:00' WHERE id=?",[$child['id']]);
    $chain=rf_prepare($child['id']);$gData=rf_test_child_data($chain);$gData['category_id']=$category;$gData['tags']=$tag;
    $grand=rf_create($child['id'],$gData);moderate('request',$grand['id'],'Approved','Synthetic discovery descendant.');
    query("UPDATE research SET created_at='2030-01-02 00:00:00' WHERE id=?",[$grand['id']]);
    $otherDraft=rf_prepare($parent['id']);$otherData=rf_test_child_data($otherDraft);$otherData['category_id']=$onlyFork;$otherData['tags']=$tag;
    $other=rf_create($parent['id'],$otherData);moderate('request',$other['id'],'Approved','Synthetic isolated category fork.');
    foreach([$parent['id'],$child['id'],$grand['id'],$other['id']] as $target)insert('research_links',['research_id'=>$normal[0],'related_id'=>$target,'reason'=>'Synthetic related-research visibility test.']);
    $categoryCount=fn($id)=>(int)array_column(discovery_categories(),null,'id')[$id]['request_count'];
    $assertVisible=function(string $stage)use($base,$tag,$needle,$category,$onlyFork,$categoryCount,$normal,$parent,$child,$grand){
        fd_ok(research($child['id'])['id']===$child['id'],$stage.': direct access allowed');
        $list=discover($base);
        fd_ok($list['total']===16&&count($list['rows'])===12,$stage.': complete total and first page');
        fd_ok($list['rows'][0]['id']===$grand['id']&&$list['rows'][1]['id']===$child['id'],$stage.': newest fork ordering');
        fd_ok(count(discover($base+['page'=>'2'])['rows'])===4,$stage.': complete second page');
        fd_ok(discover($base+['q'=>$needle])['total']===1,$stage.': searchable fork included');
        fd_ok(discover(['tag'=>$tag])['total']===17,$stage.': totals across categories');
        fd_ok($categoryCount($category)===16&&$categoryCount($onlyFork)===1,$stage.': category counts include eligible forks');
        fd_ok(count(public_related_research($normal[0]))===4,$stage.': direct related-research links included');
        fd_ok(count(context_data($normal[0])['related_research'])===4,$stage.': context related-research links included');
        fd_ok(count(rf_children($parent['id']))===2,$stage.': public parent fork links included');
    };
    $assertBlocked=function(string $stage,bool $parentPublic=true)use($base,$tag,$needle,$category,$onlyFork,$categoryCount,$normal,$parent,$child){
        try{research($child['id']);throw new RuntimeException('Direct fork access unexpectedly allowed');}catch(HttpError $e){fd_ok($e->status===404,$stage.': direct access blocked');}
        $list=discover($base);
        fd_ok($list['total']===14&&count($list['rows'])===12,$stage.': eligibility applied before total and LIMIT');
        fd_ok($list['rows'][0]['id']===$normal[13],$stage.': newest visible sibling fills first page');
        $second=discover($base+['page'=>'2']);fd_ok(count($second['rows'])===2,$stage.': second page is accurate');
        fd_ok(discover($base+['page'=>'999'])['page']===2,$stage.': page clamps using eligible total');
        fd_ok(discover($base+['q'=>$needle])['total']===0,$stage.': search excludes unavailable fork');
        fd_ok(discover(['tag'=>$tag])['total']===14,$stage.': total excludes unavailable forks across categories');
        fd_ok(discover($base+['no_contributions'=>'1'])['total']===14,$stage.': contribution filter retains eligibility');
        fd_ok($categoryCount($category)===14&&$categoryCount($onlyFork)===0,$stage.': category counts omit descendants and retain empty category');
        $related=public_related_research($normal[0]);
        fd_ok(count($related)===($parentPublic?1:0),$stage.': direct related-research listing excludes blocked targets');
        fd_ok(count(context_data($normal[0])['related_research'])===($parentPublic?1:0),$stage.': exported related-research listing excludes blocked targets');
        if($parentPublic)fd_ok(!rf_children($parent['id']),$stage.': parent fork listing excludes blocked children');
    };
    $assertVisible('Initially public');
    change_status($parent['id'],'Withdrawn','Synthetic discovery withdrawal.','requester');$assertBlocked('Ancestor withdrawn',false);
    change_status($parent['id'],'Open for Research','Synthetic source restoration.','administrator');$assertVisible('Restored after withdrawal');
    query('UPDATE workflow_snapshots SET public_allowed=0 WHERE id=?',[$draft['fork']['snapshot_id']]);$assertBlocked('Source snapshot revoked');
    query('UPDATE workflow_snapshots SET public_allowed=1 WHERE id=?',[$draft['fork']['snapshot_id']]);$assertVisible('Restored after revocation');
    query("UPDATE workflow_submissions SET visibility='private' WHERE id=?",[$parent['result_id']]);$assertBlocked('Source result hidden');
    query("UPDATE workflow_submissions SET visibility='public' WHERE id=?",[$parent['result_id']]);$assertVisible('Restored after hidden result');
    echo count($checks)." fork discovery/count/pagination/related visibility checks passed.
";
}finally{db()->rollBack();}
