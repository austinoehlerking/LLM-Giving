<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/workflow-fixture.php';
$_SESSION['admin']=1;$rid=null;$checks=[];
function cite_check(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
try{
 foreach(['contributions','workflow_submissions'] as $table)cite_check((int)one("SELECT COUNT(*) n FROM ".$table." WHERE license<>'CC-BY-4.0'")['n']===0,'All existing '.$table.' use CC BY 4.0');
 $made=create_research(['title'=>'[QA] Citation and licensing','question'=>'Synthetic license and citation workflow; no scientific finding.','category_id'=>1,'terms'=>1]);$rid=$made['id'];moderate('request',$rid,'Approved','Local citation test.');
 $task=wf_tasks($rid)[0];$d=wf_test_payload($rid,$task);$d['license']='CC0';$d['display_name']='Test contributor';
 $bad=$d;$bad['terms']=0;try{wf_submit($rid,$task['id'],$bad,str_repeat('f',64));throw new RuntimeException('Missing agreement accepted');}catch(HttpError $e){cite_check($e->status===422,'New license still requires human terms acceptance');}
 $id=wf_submit($rid,$task['id'],$d,str_repeat('f',64));$row=one('SELECT * FROM workflow_submissions WHERE id=?',[$id]);$meta=json_decode($row['metadata'],true);
 cite_check($row['license']===RESEARCH_LICENSE&&$meta['contribution_license']===RESEARCH_LICENSE&&$meta['terms_version']===terms_version(),'New task stores server-owned license and actual policy version');
 $cid=create_contribution($rid,['kind'=>'Original research','summary'=>'Synthetic license test','body'=>'Synthetic license test only, not scientific findings.','terms'=>1,'license'=>'CC0']);
 cite_check(one('SELECT license FROM contributions WHERE id=?',[$cid])['license']===RESEARCH_LICENSE,'Supplementary contributions cannot override CC BY 4.0');
 $ctx=context_data($rid);cite_check($ctx['research_license']['id']===RESEARCH_LICENSE&&$ctx['workflow']['submissions'][0]['license']===RESEARCH_LICENSE,'Current context exports license for contributions');
 $citation=research_citation(research($rid),$row);
 cite_check(str_contains($citation,'Test contributor')&&str_contains($citation,$row['created_at'].' UTC')&&str_contains($citation,'/results/'.$id)&&str_contains($citation,RESEARCH_LICENSE_URL),'Citation contains contributor, UTC date, exact result and license');
 cite_check(str_contains(wf_prompt($rid,$task['id']),'credit the named contributors and LLM.Giving'),'Task prompt has explicit attribution requirement');
 cite_check(str_contains(wf_agent_guide(research($rid)),'CC BY 4.0'),'AI guide includes license');
 foreach(all('SELECT body,sha256 FROM workflow_handoffs') as $a)cite_check(hash_equals($a['sha256'],hash('sha256',$a['body'])),'Issued archive bytes remain intact');
 echo count($checks)." citation and licensing checks passed.\n";
}finally{if($rid)purge_request($rid,$rid);}
