<?php
function ra_test_visuals(array $packet): array {
    $base=['provenance'=>'simulated','source_ids'=>['s1'],'method'=>'Literal synthetic QA values supplied in tests/artifact-fixture.php; no measured observations or fitted model are claimed.',
        'limitations'=>'Synthetic local workflow data cannot establish any scientific mechanism or effect.','uncertainty'=>'Low/high values are illustrative ranges, not statistical confidence intervals.'];
    $packet['evidence']['datasets']=[
        $base+['id'=>'summary-data','title'=>'Synthetic scenario totals','columns'=>[['key'=>'scenario','label'=>'Scenario','type'=>'text','unit'=>''],['key'=>'value','label'=>'Illustrative export','type'=>'number','unit'=>'mg C per square metre per day']], 'rows'=>[['Baseline',20],['Hypothetical change',15]]],
        $base+['id'=>'series-data','title'=>'Synthetic scenario time series','columns'=>[['key'=>'scenario','label'=>'Scenario','type'=>'text','unit'=>''],['key'=>'time','label'=>'Time','type'=>'number','unit'=>'days'],['key'=>'value','label'=>'Illustrative export','type'=>'number','unit'=>'mg C per square metre per day'],['key'=>'low','label'=>'Illustrative lower bound','type'=>'number','unit'=>'mg C per square metre per day'],['key'=>'high','label'=>'Illustrative upper bound','type'=>'number','unit'=>'mg C per square metre per day']],
            'rows'=>[['baseline',0,10,8,12],['baseline',1,null,null,null],['baseline',2,20,16,24],['change',0,8,6,10],['change',1,12,9,15],['change',2,15,11,19]]]
    ];
    $common=['purpose'=>'Explain how a research visual connects supplied data and a conceptual pathway, using clearly synthetic QA material.',
        'summary'=>'The hypothetical change has a lower illustrative total than the baseline. The diagram is conceptual, and no scientific finding is established.',
        'provenance'=>'simulated','claim_ids'=>['c1'],'source_ids'=>['s1'],'assumptions'=>'All numerical values and pathways are deliberately synthetic. States select literal supplied scenarios without interpolation.',
        'limitations'=>'This local QA illustration cannot establish causation, a real exposure level or a measured carbon-export effect.',
        'method'=>'Read the supplied rows, select the exact scenario value, plot the typed columns and highlight the described diagram node.'];
    $diagram=['id'=>'pathway','title'=>'Conceptual pathway','type'=>'diagram','nodes'=>[['id'=>'particles','label'=>'Particles in water','detail'=>'A conceptual input node in a synthetic research explanation.','x'=>22,'y'=>50],['id'=>'export','label'=>'Carbon export endpoint','detail'=>'A conceptual endpoint; this synthetic diagram does not establish a causal link.','x'=>78,'y'=>50]],'edges'=>[['from'=>'particles','to'=>'export','label'=>'hypothetical pathway']]];
    $plot=['id'=>'plot','title'=>'Scenario export over time','type'=>'chart','dataset_id'=>'series-data','chart_type'=>'line','x'=>'time','y'=>['value','low','high']];
    $states=[['id'=>'baseline','label'=>'Baseline illustration','explanation'=>'Select the baseline rows. The gap at day one is missing data, not zero. Highlight the conceptual input node.','filters'=>[['panel_id'=>'plot','column'=>'scenario','value'=>'baseline']],'highlights'=>[['panel_id'=>'pathway','node_ids'=>['particles']]]],
        ['id'=>'change','label'=>'Hypothetical change','explanation'=>'Select the hypothetical change rows and highlight the endpoint. These are simulated values, not a newly measured effect.','filters'=>[['panel_id'=>'plot','column'=>'scenario','value'=>'change']],'highlights'=>[['panel_id'=>'pathway','node_ids'=>['export']]]]];
    $packet['evidence']['artifacts']=[
        $common+['id'=>'scenario-chart','title'=>'Illustrative scenario comparison','kind'=>'chart','dataset_ids'=>['summary-data'],'control_label'=>'','panels'=>[['id'=>'comparison','title'=>'Illustrative export by scenario','type'=>'chart','dataset_id'=>'summary-data','chart_type'=>'bar','x'=>'scenario','y'=>['value']]],'states'=>[]],
        $common+['id'=>'pathway-diagram','title'=>'Conceptual carbon export pathway','kind'=>'diagram','dataset_ids'=>[],'control_label'=>'','panels'=>[$diagram],'states'=>[]],
        $common+['id'=>'pathway-animation','title'=>'Step through the research illustration','kind'=>'animation','dataset_ids'=>['series-data'],'control_label'=>'Research illustration stage','panels'=>[$diagram,$plot],'states'=>$states],
        $common+['id'=>'scenario-page','title'=>'Explore hypothetical export scenarios','kind'=>'interactive','dataset_ids'=>['series-data'],'control_label'=>'Compare a hypothetical scenario','panels'=>[$plot,$diagram],'states'=>$states]
    ];return $packet;
}
function ra_test_create(): array {
    $_SESSION['admin']=1;$category=(int)one('SELECT id FROM categories LIMIT 1')['id'];$r=create_research(['title'=>'[Artifact QA] Visual artifact evidence and controls','question'=>'Exercise native visual research artifacts with synthetic data, source references, limitations and corrections.','category_id'=>$category,'terms'=>1]);
    moderate('request',$r['id'],'Approved','Disposable local visual research artifact QA fixture.');$plan=wf_tasks($r['id'])[0];wf_submit($r['id'],$plan['id'],wf_test_payload($r['id'],$plan),str_repeat('a',64));
    $task=array_values(array_filter(wf_tasks($r['id']),fn($task)=>$task['kind']==='research'&&$task['state']==='Ready'))[0];$packet=ra_test_visuals(wf_test_payload($r['id'],$task));$resultId=wf_submit($r['id'],$task['id'],$packet,str_repeat('a',64));
    return ['id'=>$r['id'],'slug'=>research($r['id'])['slug'],'task_id'=>$task['id'],'result_id'=>$resultId,'artifact_ids'=>array_column($packet['evidence']['artifacts'],'id')];
}
