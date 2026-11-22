<?php
$draft=vs_setup_defaults($draft);$categories=all('SELECT id,name FROM categories ORDER BY name');
$preset=$draft['session_preset'];$scopeKind=$draft['scope_kind'];$categoryId=$draft['category_id']??'';
if(($draft['cause_choice']??'')==='all')$scopeKind='all';elseif(preg_match('/^category:([0-9]+)$/D',$draft['cause_choice']??'',$match)){$scopeKind='category';$categoryId=$match[1];}
$arrow='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12h16m-6-6 6 6-6 6"/></svg>';
?>
<div class="volunteer-start inline-setup"><h1>Start volunteering</h1><p class="volunteer-subtitle">You’ll need an AI tool with browser control. We’ll give you one message to paste into it.</p>
<?php if($error): ?><p class="warning" role="alert" tabindex="-1" data-setup-error><?=e($error)?></p><?php endif ?>
<form method="post" action="/volunteer" data-volunteer-setup><?=csrf()?><input type="hidden" name="operation" value="configure"><input type="hidden" name="setup_version" value="3">
<div class="setup-capabilities"><label for="volunteer-capabilities">AI capabilities</label><select id="volunteer-capabilities" name="session_profile" aria-describedby="volunteer-research-advice"><?php if(!isset(VS_PROFILES[$draft['session_profile']])): ?><option value="<?=e($draft['session_profile'])?>" selected><?=e($draft['session_profile'])?></option><?php endif ?><?php foreach(VS_PROFILES as $id=>$profile): ?><option value="<?=e($id)?>" <?=$draft['session_profile']===$id?'selected':''?>><?=e($profile['label'])?></option><?php endforeach ?></select></div>
<p class="setup-research-advice" id="volunteer-research-advice"><?=e(wf_research_guidance())?></p>
<section class="setup-time-step" aria-labelledby="setup-time-heading"><h2 id="setup-time-heading">How much time do you have?</h2>
<fieldset class="setup-time-options"><legend class="sr-only">Session time</legend><div class="setup-time-cards"><?php foreach(VS_PRESETS as $key=>$option): ?><label class="setup-time-card"><span class="setup-time-name"><input type="radio" name="session_preset" value="<?=e($key)?>" <?=$preset===$key?'checked':''?>><span><?=e($option['label'])?></span></span><span class="setup-time-caption"><?=e(match($key){'quick'=>'15 min or 1 task','standard'=>'60 min or 3 tasks','long'=>'3 hrs or 10 tasks',default=>'∞ Until Stop'})?></span></label><?php endforeach ?></div></fieldset>
<details class="setup-advanced" data-volunteer-advanced <?=$error||$preset==='custom'?'open':''?>><summary>Advanced options</summary><div class="setup-advanced-fields"><?php include __DIR__.'/volunteer-options.php'; ?></div></details>
<button type="submit" class="setup-continue" data-setup-submit>Continue <?=$arrow?></button></section>
</form></div>
