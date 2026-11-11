<?php
$draft=vs_setup_defaults($draft);$categories=all('SELECT id,name FROM categories ORDER BY name');$registry=wf_model_registry();
$modelId=$draft['model'];$reasoning=$draft['reasoning'];$modelLabel=$registry[$modelId]['label']??$modelId;$effortOptions=$registry[$modelId]['efforts']??RQ_EFFORTS;
$preset=$draft['session_preset'];$scopeKind=$draft['scope_kind'];$categoryId=$draft['category_id']??'';
if(($draft['cause_choice']??'')==='all')$scopeKind='all';elseif(preg_match('/^category:([0-9]+)$/D',$draft['cause_choice']??'',$match)){$scopeKind='category';$categoryId=$match[1];}
$aiValid=true;try{vs_setup_ai($draft);}catch(HttpError){$aiValid=false;}
$setupStep=$aiValid&&($error||($draft['setup_step']??'')==='time')?'time':'ai';
$arrow='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12h16m-6-6 6 6-6 6"/></svg>';
?>
<div class="volunteer-start inline-setup"><h1>Start volunteering</h1><p class="volunteer-subtitle">Use your AI to help with research.</p>
<?php if($error): ?><p class="warning" role="alert" tabindex="-1" data-setup-error><?=e($error)?></p><?php endif ?>
<form method="post" action="/volunteer" data-volunteer-setup data-step="<?=e($setupStep)?>"><?=csrf()?><input type="hidden" name="operation" value="configure"><input type="hidden" name="setup_version" value="2"><input type="hidden" name="setup_step" value="time"><input type="hidden" name="tool_source" value="<?=e($draft['tool_source'])?>">
<section class="setup-ai-step" aria-label="Choose your AI"><div data-ai-open><h2 tabindex="-1" id="setup-ai-heading">1. Choose your AI</h2><div class="setup-ai-fields">
<div><label for="volunteer-model">AI model</label><select id="volunteer-model" name="model" required><?php if(!isset($registry[$modelId])): ?><option value="<?=e($modelId)?>" selected><?=e($modelId?:'Choose an AI model')?></option><?php endif ?><?php foreach($registry as $id=>$model): ?><option value="<?=e($id)?>" <?=$id===$modelId?'selected':''?>><?=e($model['label'])?></option><?php endforeach ?></select></div>
<div><label for="volunteer-effort">Reasoning level</label><select id="volunteer-effort" name="reasoning" required><?php if(!in_array($reasoning,$effortOptions,true)): ?><option value="<?=e($reasoning)?>" selected><?=e(vs_setup_effort_label($reasoning)?:'Choose a reasoning level')?></option><?php endif ?><?php foreach($effortOptions as $effort): ?><option value="<?=e($effort)?>" <?=$effort===$reasoning?'selected':''?>><?=e(vs_setup_effort_label($effort))?></option><?php endforeach ?></select></div>
</div><button class="setup-continue" type="button" data-ai-continue hidden>Continue <?=$arrow?></button></div>
<div class="setup-ai-summary" data-ai-summary hidden><h2 data-ai-summary-text>1. <?=e($modelLabel)?> · <?=e(vs_setup_effort_label($reasoning))?> reasoning</h2><button type="button" class="setup-change" data-ai-change>Change</button></div></section>
<section class="setup-time-step"><h2 id="setup-time-heading" tabindex="-1" data-time-heading>2. How much time do you have?</h2><div data-time-body>
<fieldset class="setup-time-options"><legend class="sr-only">Session time</legend><div class="setup-time-cards"><?php foreach(VS_PRESETS as $key=>$option): ?><label class="setup-time-card"><span class="setup-time-name"><input type="radio" name="session_preset" value="<?=e($key)?>" <?=$preset===$key?'checked':''?>><span><?=e($option['label'])?></span></span><span class="setup-time-caption"><?=e(match($key){'quick'=>'15 min or 1 task','standard'=>'60 min or 3 tasks','long'=>'3 hrs or 10 tasks',default=>'∞ Until Stop'})?></span></label><?php endforeach ?></div></fieldset>
<details class="setup-advanced" data-volunteer-advanced <?=$error&&$aiValid?'open':''?>><summary>Advanced options</summary><div class="setup-advanced-fields"><?php include __DIR__.'/volunteer-options.php'; ?></div></details>
<button type="submit" class="setup-continue" data-setup-submit>Continue <?=$arrow?></button></div></section>
</form><template id="volunteer-ai-options"><?=e(json_encode(array_map(fn($id,$model)=>['id'=>$id,'label'=>$model['label'],'efforts'=>$model['efforts'],'tool'=>vs_setup_tool($id)],array_keys($registry),array_values($registry)),JSON_THROW_ON_ERROR))?></template></div>
