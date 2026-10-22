<?php
/** Synthetic supplementary evidence only; never infer real source access from fixture text. */
function rs_test_supplement(array $d): array {
    $d['methodology']=$d['methodology']??'Inspect the supplied synthetic fixture text; no scientific investigation or execution is claimed.';
    $d['sources']=$d['sources']??'Supplied synthetic fixture only; no scientific source claim.';
    $d['limitations']=$d['limitations']??'Synthetic testing only, not scientific evidence.';
    $statement=$d['body'];
    $e=['version'=>1,'scope'=>'Validate the supplied local synthetic fixture; no scientific claim is established.','stopping_rule'=>'Stop after the synthetic record and intended local behavior have been inspected.',
        'search_log'=>[['query'=>'Inspect supplied synthetic fixture text','where'=>'Local regression fixture only','result'=>'Read the supplied synthetic fixture; no external search or scientific inspection is claimed.']],
        'sources'=>[['id'=>'fixture','title'=>'Supplied synthetic regression fixture','url'=>'','type'=>'provided','access'=>'full_text','access_note'=>'Read the supplied local synthetic regression text only. No external publication inspection is claimed.','authors'=>'','publication_date'=>'','publisher'=>'','doi'=>'','edition'=>'','metadata_note'=>'Synthetic QA fixture, without scholarly publication metadata.','use'=>'used']],
        'claims'=>[['id'=>'fixture','statement'=>$statement,'kind'=>'finding','support'=>'supported','confidence'=>'moderate','confidence_reason'=>'The supplied synthetic text establishes the local fixture scope only.','derivation'=>'','limitations'=>'This is a local software regression fixture and cannot establish scientific facts.','counterevidence'=>'No scientific finding or external source inspection is asserted by this fixture.','basis_result_ids'=>[],'links'=>[['source_id'=>'fixture','relation'=>'supports','locator'=>'Supplied fixture body','excerpt'=>$statement,'explanation'=>'The supplied text explicitly states the synthetic software regression scope.']]]],
        'review_checks'=>[],'datasets'=>[],'artifacts'=>[]];
    $required=rs_required_materials($e);$items=[];
    foreach(RS_CATEGORIES as $category){$refs=$required[$category]??[];$items[]=['category'=>$category,'status'=>$refs?'supplied':'not_applicable','details'=>$refs?'Synthetic fixture materials are retained at the indicated exact references.':'Not applicable: this synthetic narrative performs no computations, tool builds or unrecorded experiments.','refs'=>$refs,'access_instructions'=>''];}
    $e['supporting_materials']=['version'=>1,'work_performed'=>'narrative','items'=>$items,'files'=>[]];$d['evidence']=$e;
    return $d;
}
