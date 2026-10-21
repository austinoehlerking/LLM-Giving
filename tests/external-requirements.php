<?php
require __DIR__.'/bootstrap.php';require_once ROOT.'/src/view.php';session_start_safe();$_SESSION['admin']=1;$ids=[];$checks=[];
function ext_ok($v,$s){global $checks;if(!$v)throw new RuntimeException($s);$checks[]=$s;}
try {
 foreach([['calculation_help'=>'prepare_review','computational_models'=>1],['calculation_help'=>'maybe','computational_models'=>false],['calculation_help'=>'run','computational_models'=>0],['computational_models'=>'false'],['external_tools'=>'invalid'],['external_tools'=>'free']] as $bad){try{external_parse($bad);throw new RuntimeException('Invalid requirements accepted');}catch(HttpError $e){ext_ok($e->status===422,'Contradictory or invalid choice rejected');}}
 ext_ok(external_parse([])['calculation_help']==='maybe','No supplied preference defaults to Maybe');
 ext_ok(external_parse(['computational_models'=>false])['calculation_help']==='unspecified'&&external_parse(['computational_models'=>true])['calculation_help']==='run','Legacy explicit flags retain their meaning');
 ext_ok(external_parse(['calculation_help'=>'maybe','computational_models'=>true])['calculation_help']==='maybe','Legacy permission flag cannot turn Maybe into required execution');
 foreach(['unspecified','maybe','prepare_review','run'] as $mode){
  $created=create_research(['title'=>'[QA] External tool requirements '.$mode,'question'=>'Synthetic software access and calculation scope verification.','category_id'=>one('SELECT id FROM categories LIMIT 1')['id'],'terms'=>1,'calculation_help'=>$mode,'external_tools'=>'free','required_software'=>'PySCF test version','software_licensing'=>'REMOVED_LICENSE','computing_resources'=>'REMOVED_COMPUTE','setup_needs'=>'Download from official source']);$ids[]=$created['id'];
  try{context_data($created['id']);throw new RuntimeException('Pending request exposed');}catch(HttpError $e){ext_ok($e->status===404,'Parent visibility');}
  moderate('request',$created['id'],'Approved','Synthetic requirements check.');$r=research($created['id']);$ctx=context_data($r['id']);$policy=$ctx['research']['calculation_policy'];
  ext_ok($ctx['research']['external_requirements']['required_software']==='PySCF test version','Persistence and export');
  ext_ok((bool)$r['computational_models']===$policy['allowed']&&$policy['allowed']===in_array($mode,['maybe','run'],true)&&$policy['required']===($mode==='run')&&$policy['preparation_only']===($mode==='prepare_review'),'Distinct calculation policy: '.$mode);
  ext_ok(!str_contains($r['external_requirements'],'REMOVED_'),'Removed questions are not stored for new requests');
  $handoff=wf_handoff_text($r['id']);ext_ok(str_contains($handoff,'specific authorization')&&str_contains($handoff,'purchases'),'Setup authorization and contributor responsibilities retained');
 }
 foreach([str_repeat('STALE_SOFTWARE',500),['stale'=>'invalid hidden type']] as $stale){$parsed=external_parse(['external_tools'=>'none','required_software'=>$stale,'setup_needs'=>'']);ext_ok($parsed['required_software']===''&&$parsed['setup_needs']==='None specified','Inactive software ignored before validation; no setup invented');}
 $created=create_research(['title'=>'[QA] No mandatory dependencies','question'=>'Compare supplied evidence without imposing software or resource restrictions.','category_id'=>one('SELECT id FROM categories LIMIT 1')['id'],'terms'=>1,'external_tools'=>'none','required_software'=>'STALE_SOFTWARE']);$ids[]=$created['id'];moderate('request',$created['id'],'Approved','Synthetic no dependency check.');$r=research($created['id']);
 ext_ok(!str_contains($r['external_requirements'],'STALE_SOFTWARE'),'Hidden stale software not saved');
 ext_ok(!str_contains(json_encode(context_data($r['id'])),'STALE_SOFTWARE')&&!str_contains(prompt_text($r['id']),'STALE_SOFTWARE'),'Hidden stale software excluded from current context and prompts');
 // Historical records and issued snapshots are never migrated or rewritten.
 $legacy=json_encode(['version'=>1,'calculation_help'=>'unspecified','external_tools'=>'licensed','required_software'=>'Legacy package','software_licensing'=>'Explicit existing license restriction','computing_resources'=>'Explicit existing compute restriction','setup_needs'=>'']);
 query('UPDATE research SET external_requirements=?,computational_models=1 WHERE id=?',[$legacy,$r['id']]);$r=research($r['id']);$active=external_requirements($r);
 ext_ok(calculation_policy($r)['required']&&$active['software_licensing']==='Explicit existing license restriction'&&$active['computing_resources']==='Explicit existing compute restriction','Legacy execution and explicit restrictions retained');
 ob_start();include ROOT.'/views/external-requirements.php';$html=ob_get_clean();ext_ok(str_contains($html,'historical restriction')&&str_contains($html,'Explicit existing compute restriction'),'Legacy restrictions remain visible, read only');
 ext_ok(one('SELECT external_requirements FROM research WHERE id=?',[$r['id']])['external_requirements']===$legacy,'Reading effective requirements never rewrites historical bytes');
 echo count($checks)." external requirement checks passed.\n";
}finally{foreach($ids as $id)purge_request($id,$id);}
