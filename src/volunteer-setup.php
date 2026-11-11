<?php
/** Presentation defaults are requested settings; existing sessions and legacy POSTs stay unchanged. */
function vs_setup_tool(string $model): string {
    foreach(VS_WORKERS as $worker)if($worker['model']===$model)return $worker['tool'];
    return '';
}
function vs_setup_defaults(array $draft): array {
    $custom=isset($draft['minutes'])||isset($draft['assignment_limit']);
    $draft+=['model'=>'gpt-6-astra','reasoning'=>'max','session_preset'=>$custom?'custom':'standard','minutes'=>'60','assignment_limit'=>'3','scope_kind'=>'all','session_profile'=>'general','concurrent_limit'=>'1','workflow_mode'=>''];
    $draft['tool_source']=$draft['tool_source']??(array_key_exists('tool',$draft)?'explicit':'default');
    if(!array_key_exists('tool',$draft)||$draft['tool_source']==='default')$draft['tool']=vs_setup_tool($draft['model']);
    // The current form uses one model/effort pair. Historical presets normalize before rendering.
    $draft['setup_version']='2';$draft['worker_choice']='custom';
    return $draft;
}
function vs_setup_ai(array $input): array {
    $model=text_field($input,'model',1,100);$reasoning=text_field($input,'reasoning',1,100);$registry=wf_model_registry();
    if(!isset($registry[$model]))fail(422,'Choose a supported AI model.');
    if(!in_array($reasoning,$registry[$model]['efforts'],true))fail(422,'Choose a reasoning level supported by this AI model.');
    return ['model'=>$model,'reasoning'=>$reasoning];
}
function vs_setup_effort_label(string $effort): string {return $effort==='xhigh'?'Extra High':ucfirst($effort);}
