<?php
require __DIR__.'/bootstrap.php';
require ROOT.'/src/view.php';
$checks=[]; function expect_discovery(bool $ok,string $name): void {global $checks;if(!$ok)throw new RuntimeException($name);$checks[]=$name;}
db()->beginTransaction();
try {
 $category=insert('categories',['name'=>'Discovery QA '.uid(),'enabled'=>1]);
 $tag='discovery-'.uid();$ids=[];
 for($i=0;$i<16;$i++){
  $r=create_research(['title'=>sprintf('Discovery fixture %02d',$i),'question'=>'Synthetic discovery regression content with enough context.','category_id'=>$category,'tags'=>$tag,'terms'=>1]);$ids[]=$r['id'];
  query("UPDATE research SET moderation=?,status=?,created_at=?,updated_at=?,activity_at=? WHERE id=?",[$i===14?'Pending Approval':'Approved',$i===15?'Withdrawn':($i===2?'Under Independent Review':'Awaiting First Research'),sprintf('2026-01-%02d 12:00:00',$i+1),sprintf('2026-02-%02d 12:00:00',16-$i),sprintf('2026-03-%02d 12:00:00',16-$i),$r['id']]);
 }
 foreach($ids as $i=>$id)query('UPDATE research SET external_requirements=? WHERE id=?',[json_encode(['external_tools'=>$i===13?'licensed':'free']),$id]);
 $child=function(string $rid,string $state,?string $target=null,string $kind='Original research'): string {
  $row=['id'=>uid(),'research_id'=>$rid,'kind'=>$kind,'target_id'=>$target,'summary'=>'Synthetic contribution','body'=>'Fixture content','profiles'=>'{}','moderation'=>$state,'terms_version'=>terms_version(),'created_at'=>now()];
  foreach(['methodology','sources','uncertainty','limitations','previous_work','open_questions','next_actions','suggested_status','safety','recommendation','display_name','moderation_reason','flags','metadata_exception'] as $key)$row[$key]='';
  insert('contributions',$row);return $row['id'];
 };
 $first=$child($ids[0],'Approved');$child($ids[0],'Approved');$child($ids[0],'Pending Approval');
 $child($ids[1],'Pending Approval');$child($ids[1],'Rejected');
 $removed=$child($ids[1],'Removed');$child($ids[1],'Approved',$removed,'Follow-up research');$child($ids[1],'Approved',$removed,'Independent review');
 $child($ids[2],'Approved');$child($ids[0],'Approved',$first,'Independent review');
 $base=['tag'=>$tag,'category'=>(string)$category];
 $r=discover($base);expect_discovery($r['total']===14,'Discovery hides pending and withdrawn parents');expect_discovery(count($r['rows'])===12,'Pagination limits first page');expect_discovery($r['rows'][0]['id']===$ids[13],'Newest ordering');
 $page2=discover($base+['page'=>'2']);expect_discovery(count($page2['rows'])===2 && $page2['rows'][1]['id']===$ids[0],'Pagination retains filter and order');
 expect_discovery(discover($base+['page'=>'999'])['page']===2,'Out-of-range page clamps safely');
 expect_discovery(discover($base+['sort'=>'most'])['rows'][0]['id']===$ids[0],'Most-contribution ordering');
 $few=discover($base+['sort'=>'fewest']);expect_discovery((int)$few['rows'][0]['contribution_count']===0,'Fewest-contribution ordering');
 foreach(['updated','active','title'] as $sort)expect_discovery(discover($base+['sort'=>$sort])['rows'][0]['id']===$ids[0],$sort.' ordering');
 $zero=discover($base+['no_contributions'=>'1']);expect_discovery($zero['total']===12,'Unpublished and target-hidden children do not affect zero-contribution filter');
 expect_discovery(in_array($ids[1],array_column($zero['rows'],'id'),true),'Removed-target children stay hidden from counts');
 $most=discover($base+['sort'=>'most']);expect_discovery((int)$most['rows'][0]['contribution_count']===2 && (int)$most['rows'][0]['review_count']===1,'Research and review counts are separate');
 expect_discovery(discover($base+['status'=>'needs-review'])['total']===1,'Combined category tag status filters');
 expect_discovery(discover($base+['status'=>'needs-review','no_contributions'=>'1'])['total']===0,'Combined filters produce accurate empty state');
 expect_discovery(discover($base+['q'=>'fixture 01'])['total']===1,'Text search composes with category and tag');
 expect_discovery(discover($base+['sort'=>"id; DROP TABLE research"])['filters']['sort']==='new','Sort uses a strict allowlist');
 expect_discovery(discover(['q'=>[],'sort'=>[],'status'=>[]])['filters']['q']==='','Array query parameters safely normalized');
 $free=discover($base+['external_tools'=>'free']);expect_discovery($free['total']===13,'Free filter excludes licensed, pending and withdrawn requests');
 $freePage=discover($base+['external_tools'=>'free','page'=>'2']);expect_discovery(count($freePage['rows'])===1&&$freePage['filters']['external_tools']==='free','Free filter persists through pagination');
 expect_discovery(discover($base+['external_tools'=>'licensed'])['total']===1,'Licensed software is separately discoverable');
 expect_discovery(discover($base+['external_tools'=>'free','q'=>'fixture 01','no_contributions'=>'1'])['total']===1,'Tool access composes with search and contribution filters');
 foreach(['mixed','unknown','none','unspecified'] as $access){query('UPDATE research SET external_requirements=? WHERE id=?',[$access==='unspecified'?'{}':json_encode(['external_tools'=>$access]),$ids[13]]);expect_discovery(discover($base+['external_tools'=>$access])['total']===1,'Separate access filter: '.$access);}
 expect_discovery(discovery_filters(['external_tools'=>['free']])['external_tools']===''&&discovery_filters(['external_tools'=>"free' OR 1=1"])['external_tools']==='','Invalid tool filters normalize safely');
 $cats=array_column(discovery_categories(),null,'id');expect_discovery((int)$cats[$category]['request_count']===14,'Category counts exclude private parents');
 expect_discovery(research_excerpt("Résumé\nβeta",7)==='Résumé …','Excerpt preserves Unicode and normalizes whitespace');
} finally {db()->rollBack();}
file_put_contents(storage('qa/discovery.json'),json_encode(['passed'=>count($checks),'checks'=>$checks],JSON_PRETTY_PRINT));
echo count($checks)." discovery regression checks passed.\n";
