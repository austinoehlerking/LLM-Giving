<?php
require __DIR__.'/bootstrap.php';
$_SESSION['admin']=1;
$categories=all('SELECT id FROM categories WHERE enabled=1 ORDER BY id LIMIT 2');
$tag='library-browser-'.substr(uid(),0,8);$ids=[];
for($i=0;$i<14;$i++){
 $created=create_research(['title'=>sprintf('[QA] Library research %02d',$i),'question'=>'Synthetic browser fixture for research discovery and filtering.','category_id'=>$categories[$i===13?1:0]['id'],'tags'=>$tag,'terms'=>1,'external_tools'=>$i===13?'licensed':'free','required_software'=>$i===13?'Licensed QA software':'Free QA software']);
 $ids[]=$created['id'];moderate('request',$created['id'],'Approved','Synthetic local browser fixture.');
 query("UPDATE research SET created_at=?,updated_at=?,activity_at=? WHERE id=?",[sprintf('2026-01-%02d 12:00:00',$i+1),sprintf('2026-02-%02d 12:00:00',14-$i),sprintf('2026-03-%02d 12:00:00',14-$i),$created['id']]);
}
foreach([0,0,1] as $i){
 $c=create_contribution($ids[$i],rs_test_supplement(['kind'=>'Original research','summary'=>'Synthetic library browser contribution','body'=>'Synthetic fixture content with no scientific claims.','terms'=>1,'metadata'=>[]]));
 moderate('contribution',$c,'Approved','Synthetic fixture.','Local synthetic test explicitly allows missing model metadata.');
}
query("UPDATE research SET status='Under Independent Review' WHERE id=?",[$ids[0]]);
echo json_encode(['ids'=>$ids,'tag'=>$tag,'category'=>(string)$categories[0]['id']]);
