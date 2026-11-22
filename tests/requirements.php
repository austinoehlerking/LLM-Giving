<?php
require __DIR__.'/bootstrap.php';
session_start_safe();$_SESSION['admin']=1;$ids=[];$checks=[];
function req_ok(bool $v,string $s):void{global $checks;if(!$v)throw new RuntimeException($s);$checks[]=$s;}
try{
 $legacy=['model'=>'','mode'=>'none','effort'=>''];$reordered=['mode'=>'none','model'=>'','effort'=>''];
 req_ok(rq_same_requirements($legacy,$reordered),'Requirement object key order is not a semantic change');
 foreach(['model'=>'gpt-6-astra','mode'=>'exact','effort'=>'max'] as $key=>$value){$changed=$legacy;$changed[$key]=$value;req_ok(!rq_same_requirements($legacy,$changed),'Changed '.$key.' remains a real requirement change');}
 foreach([null,'{}',[],['none'],['model'=>'','mode'=>'none'],['model'=>'','mode'=>'none','effort'=>'','extra'=>true],['model'=>'','mode'=>'none','effort'=>false]] as $malformed)req_ok(!rq_same_requirements($malformed,$legacy),'Missing, extra, malformed or differently typed values never compare equal');
 $v2=['version'=>2,'models'=>['gpt-6-astra','claude-fable-5-1'],'efforts'=>[['level'=>'max','or_higher'=>false]],'modes'=>[],'registry'=>['gpt-6-astra'=>['efforts'=>['low','max','ultra'],'modes'=>[]]]];
 $v2Reordered=['registry'=>['gpt-6-astra'=>['modes'=>[],'efforts'=>['low','max','ultra']]],'modes'=>[],'efforts'=>[['or_higher'=>false,'level'=>'max']],'models'=>['gpt-6-astra','claude-fable-5-1'],'version'=>2];
 $before=json_encode($v2Reordered);req_ok(rq_same_requirements($v2,$v2Reordered),'Nested version2 registry and effort-object key order is ignored');req_ok(json_encode($v2Reordered)===$before,'Comparison does not mutate captured requirements');
 $changed=$v2;$changed['efforts'][0]['or_higher']=0;req_ok(!rq_same_requirements($v2,$changed),'Boolean effort policy is not coerced to an integer');
 $changed=$v2;$changed['registry']['gpt-6-astra']['efforts']=['max','low','ultra'];req_ok(!rq_same_requirements($v2,$changed),'Effort scale list ordering remains significant');
 $changed=$v2;$changed['version']='2';req_ok(!rq_same_requirements($v2,$changed),'Requirement version type remains significant');
 $brief=['question'=>'Compare the limitations of reproducible synthetic dataset conversion methods.','category_id'=>1,'terms'=>1,'external_tools'=>'free','required_software'=>'Fixture software','calculation_help'=>'run'];
 $current=create_research($brief+['title'=>'[QA] Ordinary capability request']);$ids[]=$current['id'];
 $oldScalar=create_research($brief+['title'=>'[QA] Legacy scalar model fields','required_model'=>'invalid-retired-model','reasoning_rule'=>'exact','required_reasoning'=>'invalid']);$ids[]=$oldScalar['id'];
 $oldList=create_research($brief+['title'=>'[QA] Legacy model and mode fields','ai_selection_version'=>2,'required_models'=>['invalid-retired-model'],'reasoning_levels'=>['ultracode','invalid'],'reasoning_or_higher'=>['invalid']]);$ids[]=$oldList['id'];
 foreach($ids as $rid){
  $r=research($rid,false);$external=external_requirements($r);
  req_ok($r['ai_requirements']==='{}','No model or reasoning constraints are stored by current or legacy posts');
  req_ok($external['required_software']==='Fixture software'&&$external['external_tools']==='free'&&calculation_policy($r)['required'],'Legitimate capability and dependency requirements are preserved');
 }
 $rid=$oldList['id'];$original=json_encode($v2,JSON_THROW_ON_ERROR);
 query('UPDATE research SET ai_requirements=? WHERE id=?',[$original,$rid]);
 req_ok(rq_requirements(research($rid,false))===$v2,'Stored historical model and reasoning data remains readable');
 req_ok(research($rid,false)['ai_requirements']===$original,'Historical bytes are preserved');
 echo count($checks)." requester capability and historical record checks passed.\n";
}finally{foreach($ids as $rid)purge_request($rid,$rid);}
