<?php
require __DIR__.'/bootstrap.php';session_start_safe();$_SESSION['admin']=1;$ids=[];
function ext_ok($v,$s){if(!$v)throw new RuntimeException($s);}
try {
 foreach([['calculation_help'=>'prepare_review','computational_models'=>1],['external_tools'=>'invalid'],['external_tools'=>'free']] as $bad){try{external_parse($bad);throw new RuntimeException('Invalid requirements accepted');}catch(HttpError $e){ext_ok($e->status===422,'Validation status');}}
 foreach(['prepare_review','run'] as $mode){$created=create_research(['title'=>'[QA] External tool requirements','question'=>'Synthetic software access and calculation scope verification.','category_id'=>one('SELECT id FROM categories LIMIT 1')['id'],'terms'=>1,'calculation_help'=>$mode,'external_tools'=>'free','required_software'=>'PySCF test version','software_licensing'=>'Apache 2.0; check dependencies','computing_resources'=>'Local CPU, 8 GB RAM; no paid compute','setup_needs'=>'Download from official source']);$ids[]=$created['id'];
 try{context_data($created['id']);throw new RuntimeException('Pending request exposed');}catch(HttpError $e){ext_ok($e->status===404,'Parent visibility');}
 moderate('request',$created['id'],'Approved','Synthetic requirements check.');$r=research($created['id']);$ctx=context_data($r['id']);ext_ok($ctx['research']['external_requirements']['required_software']==='PySCF test version','Persistence and export');ext_ok((bool)$r['computational_models']===($mode==='run'),'Execution scope');ext_ok(str_contains(wf_handoff_text($r['id']),'May I use computer use'),'Setup permission in handoff');ext_ok(str_contains(prompt_text($r['id']),'prepare') ,'Supplementary prompt');
 }
 echo "External requirements: validation, persistence, scope, privacy and handoff checks passed.\n";
}finally{foreach($ids as $id)purge_request($id,$id);}
