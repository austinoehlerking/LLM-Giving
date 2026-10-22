<?php
$original=$requirements??rq_requirements([]);
$req=rq_choices($original);$registry=wf_model_registry();
$selectedLevels=array_column($req['efforts'],'level');
if($req['modes'])$selectedLevels[]='ultracode';
$higher=array_column(array_filter($req['efforts'],fn($choice)=>$choice['or_higher']),'level');
$config=['registry'=>$registry,'policy'=>wf_policy()];
if(isset($requirements)&&($original['version']??null)!==2){
 $config['legacy_values']=['required_models'=>$req['models'],'reasoning_levels'=>$selectedLevels,'reasoning_or_higher'=>$higher];
 $config['legacy_description']=rq_description($original);
}
$available=$registry;foreach($req['models'] as $id)if(!isset($available[$id]))$available[$id]=['label'=>'Historical exact model'];

?>
<fieldset data-ai-selection aria-describedby="ai-selection-help ai-selection-error">
<legend>AI model &amp; reasoning requirements</legend>
<input type="hidden" name="ai_selection_version" value="2">
<p id="ai-selection-help" class="fine">Optional. Any adds no requester restriction; platform planning still requires an approved model at Max/Ultra. Select one or more alternatives. Requirements lock after the first research submission.</p>
<template data-ai-config><?=e(json_encode($config,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT))?></template>
<details data-ai-model-menu class="ai-choice-menu">
<summary>Required AI model: <span data-ai-model-summary>Any</span></summary>
<div class="ai-choice-options" role="group" aria-label="Required AI model">
<label class="check"><input data-ai-choice type="checkbox" name="required_models[]" value="any" <?=!$req['models']?'checked':''?>><span>Any — no additional model restriction</span></label>
<?php foreach($available as $id=>$model): ?>
<label class="check"><input data-ai-choice type="checkbox" name="required_models[]" value="<?=e($id)?>" <?=in_array($id,$req['models'],true)?'checked':''?>><span><?=e($model['label'])?> <small><?=e($id)?></small></span></label>
<?php endforeach ?>
</div></details>
<details data-ai-effort-menu class="ai-choice-menu">
<summary>Reasoning: <span data-ai-effort-summary>Any</span></summary>
<div class="ai-choice-options" role="group" aria-label="Reasoning alternatives and workflow mode">
<label class="check"><input data-ai-choice type="checkbox" name="reasoning_levels[]" value="any" <?=!$selectedLevels?'checked':''?>><span>Any — no additional effort or mode restriction</span></label>
<?php foreach(RQ_EFFORTS as $level): ?>
<div class="ai-effort-row">
<label class="check"><input data-ai-choice type="checkbox" name="reasoning_levels[]" value="<?=e($level)?>" <?=in_array($level,$selectedLevels,true)?'checked':''?>><span><?=e(ucfirst($level))?></span></label>
<?php if($level!=='ultra'): ?><label class="check"><input data-ai-choice type="checkbox" name="reasoning_or_higher[]" value="<?=e($level)?>" <?=in_array($level,$higher,true)?'checked':''?>><span><?=e(ucfirst($level))?> or higher</span></label><?php endif ?>
</div>
<?php endforeach ?>
<p class="fine">Effort selections are OR alternatives. “Or higher” includes only levels supported by the actual model, with no comparison of capability across models.</p>
<label class="check"><input data-ai-choice type="checkbox" name="reasoning_levels[]" value="ultracode" <?=in_array('ultracode',$selectedLevels,true)?'checked':''?>><span>Ultracode — Claude Code workflow mode</span></label>
<p class="fine">Ultracode is a separate mode restriction AND any effort choices above. By itself it allows any supported actual effort; planning still requires Max. Claude Code 2.1.284+ supports this mode independently of effort. Always record the actual effort separately.</p>
</div></details>
<p data-ai-summary class="fine" aria-live="polite"><?=e(rq_description($original))?></p>
<p id="ai-selection-error" data-ai-error class="validation-message" role="status" hidden></p>
<noscript><p class="fine">Uncheck Any before selecting specific options. Clearing all means Any. Select an effort before its “or higher” option. The server checks all choices.</p></noscript>
</fieldset>
<script type="module" src="/assets/ai-selection.js?v=1"></script>
