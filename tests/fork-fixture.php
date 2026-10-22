<?php
function rf_test_request(string $name): array {
    return create_research(['title'=>'[QA] Fork '.$name.' '.uid(),'question'=>'Which safe synthetic methods could compare marine observations and distinguish uncertainty?','category_id'=>1,'terms'=>1,'context'=>'Synthetic public background for fork provenance.','email'=>'private-fork@example.invalid','display_name'=>'Original synthetic requester','website'=>'https://example.org/attribution']);
}
function rf_test_parent(): array {
    $parent=rf_test_request('parent');$rid=$parent['id'];moderate('request',$rid,'Approved','Synthetic fork fixture.');
    $t=wf_tasks($rid)[0];$d=wf_test_payload($rid,$t);$d['display_name']='Synthetic planning contributor';
    wf_submit($rid,$t['id'],$d,str_repeat('a',64));
    $t=array_values(array_filter(wf_tasks($rid),fn($t)=>$t['kind']==='research'&&$t['state']==='Ready'))[0];
    $d=wf_test_payload($rid,$t,'progress');$d['display_name']='Synthetic research contributor';
    $d['body']='Synthetic inherited finding with explicit uncertainty. No real scientific conclusion is established.';
    foreach($d['evidence']['claims'] as &$claim){$claim['statement']=$d['body'];$claim['links'][0]['excerpt']=$d['body'];}unset($claim);
    foreach($d['acceptance_checks'] as &$check)if($check['result_id']==='self')$check['evidence_quote']=$d['body'];unset($check);
    $d=rs_test_materials($d,$t);
    $result=wf_submit($rid,$t['id'],$d,str_repeat('b',64));
    return $parent+['result_id'=>$result,'task_id'=>$t['id'],'r'=>research($rid)];
}
function rf_test_child_data(array $draft): array {
    return ['fork_nonce'=>$draft['nonce'],'title'=>'[QA] Related marine question '.uid(),'question'=>'What related synthetic question could volunteers investigate independently with inherited background?','category_id'=>1,'terms'=>1,'calculation_help'=>'maybe'];
}
