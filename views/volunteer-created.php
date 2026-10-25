<?php
$wizardHandoff=vo_handoff($created['id'],$created['link']);
$wizardStartMessage=vo_start_message($wizardHandoff);
$wizardSecret=substr($created['link'],strrpos($created['link'],'#')+1);
$wizardTitles=['Save your private link','Copy start message','Paste into Dot and send','Open your dashboard'];
?>
<div class="volunteer-wizard" data-volunteer-wizard data-email-interacted="<?=!empty($emailOpen)?'true':'false'?>">
<h1>Your session is ready</h1>
<p class="wizard-subtitle">Your AI starts after you send the instructions. Follow these 4 steps.</p>
<div class="wizard-surface">
<ol class="wizard-overview" aria-label="Your four steps">
<?php foreach($wizardTitles as $index=>$wizardTitle): ?><li data-wizard-marker="<?=$index+1?>" <?=$index===0?'aria-current="step"':''?>><span class="wizard-number" aria-hidden="true"><?=$index+1?></span><span class="wizard-step-label"><?=e($wizardTitle)?></span></li><?php endforeach ?>
</ol>
<div class="wizard-panes">
<section id="wizard-step-1" data-wizard-pane="1" aria-labelledby="wizard-title-1">
<p class="wizard-step-count">Step 1 of 4</p><h2 id="wizard-title-1" tabindex="-1">Save your private link</h2>
<p>Save a copy so you can return later. You can also continue without saving.</p>
<div class="wizard-save-methods"><button type="button" class="secondary" data-wizard-copy="link" hidden>Copy session link</button><noscript><a class="button secondary" href="#wizard-link-manual">Copy session link manually</a></noscript><span class="wizard-or">OR</span><button type="button" class="secondary" data-wizard-email-toggle hidden aria-controls="wizard-email" aria-expanded="<?=!empty($emailOpen)?'true':'false'?>">Email it to me <span>(optional)</span></button><noscript><a class="button secondary" href="#wizard-email">Email it to me (optional)</a></noscript></div>
<p class="wizard-feedback" data-wizard-copy-status="link" role="status" aria-live="polite"></p>
<details class="wizard-manual" id="wizard-link-manual" data-wizard-link-manual><summary>Copy link manually</summary><label for="volunteer-private-link">Private session link</label><textarea id="volunteer-private-link" rows="3" readonly autocomplete="off" spellcheck="false"><?=e($created['link'])?></textarea></details>
<details data-wizard-email-disclosure <?=!empty($emailOpen)?'open':''?>><summary class="wizard-email-native-summary">Email it to me (optional)</summary>
<form id="wizard-email" class="wizard-email" method="post" action="/volunteer/<?=e($created['id'])?>/email-link" data-wizard-email data-email-state="<?=e($emailState??'idle')?>" <?=empty($emailOpen)?'data-initially-collapsed':''?>>
<?=csrf()?><input type="hidden" name="session_secret" value="<?=e($wizardSecret)?>"><input type="hidden" name="email_attempt" value="<?=e($emailAttempt??uid())?>">
<label for="wizard-email-address">Your email address</label><div class="wizard-email-row"><input type="email" id="wizard-email-address" name="email" value="<?=e($emailValue??'')?>" placeholder="you@example.com" autocomplete="email" maxlength="254" required><button type="submit">Send my link</button></div>
<p class="fine">We'll email your private session link to this address.</p><p class="wizard-feedback" data-wizard-email-status role="status" aria-live="polite"><?=e($emailFeedback??'')?></p>
</form></details>
<div class="wizard-actions"><button type="button" data-wizard-next="2" hidden>I've saved it — Next <span aria-hidden="true">→</span></button><noscript><a class="button" href="#wizard-step-2">I've saved it — Next →</a></noscript></div>
</section>
<section id="wizard-step-2" data-wizard-pane="2" aria-labelledby="wizard-title-2">
<p class="wizard-step-count">Step 2 of 4</p><h2 id="wizard-title-2" tabindex="-1">Copy start message</h2>
<p>Copy one short start message. Dot opens your private session and reads the full AI instructions there.</p>
<button type="button" data-wizard-copy="instructions" hidden>Copy start message</button>
<p class="wizard-feedback" data-wizard-copy-status="instructions" role="status" aria-live="polite"></p>
<details class="wizard-manual" data-wizard-instructions-manual><summary>Copy not working? Show start message</summary><label for="orchestrator-handoff">Short start message with private session authorization</label><textarea id="orchestrator-handoff" readonly rows="10" spellcheck="false" autocomplete="off"><?=e($wizardStartMessage)?></textarea></details>
<div class="wizard-actions"><button type="button" class="secondary" data-wizard-back="1" hidden>Back</button><button type="button" class="secondary" data-wizard-next="3" hidden>Next →</button><noscript><a class="button secondary" href="#wizard-step-3">Next →</a></noscript></div>
</section>
<section id="wizard-step-3" data-wizard-pane="3" aria-labelledby="wizard-title-3">
<p class="wizard-step-count">Step 3 of 4</p><h2 id="wizard-title-3" tabindex="-1">Paste into Dot and send</h2>
<p>Open a new chat in <strong>Dot</strong> with browser control enabled. Paste the <strong>one copied start message</strong> into the message box and press <strong>Send</strong>.</p>
<p>No second paste or attachment is needed. Dot reads the complete instructions from your private session.</p>
<p>Your AI starts after you send the instructions. Copying here does not launch AI or connect an account.</p>
<div class="wizard-actions"><button type="button" class="secondary" data-wizard-back="2" hidden>Back</button><button type="button" data-wizard-next="4" hidden>Next →</button><noscript><a class="button" href="#wizard-step-4">Next →</a></noscript></div>
</section>
<section id="wizard-step-4" data-wizard-pane="4" aria-labelledby="wizard-title-4">
<p class="wizard-step-count">Step 4 of 4</p><h2 id="wizard-title-4" tabindex="-1">Open your dashboard</h2>
<p>Return here and click Open dashboard to see recorded progress. To finish, select <strong>Stop session</strong> there and stop your AI too.</p>
<p>The website cannot terminate your external AI.</p>
<div class="wizard-actions"><button type="button" class="secondary" data-wizard-back="3" hidden>Back</button><a class="button" href="/volunteer/<?=e($created['id'])?>" data-wizard-dashboard>Open dashboard</a></div>
</section>
</div></div>
<p class="wizard-privacy">Keep your session link and AI instructions private.</p>
<dialog data-wizard-confirm aria-labelledby="wizard-confirm-title" aria-describedby="wizard-confirm-body wizard-confirm-help"><button type="button" class="wizard-close secondary" data-wizard-dismiss aria-label="Close">×</button><h2 id="wizard-confirm-title">Continue without saving?</h2><p id="wizard-confirm-body">Are you sure you don't want to save the session link?</p><p id="wizard-confirm-help">Saving it helps you return to this session later.</p><div class="wizard-dialog-actions"><button type="button" class="secondary" data-wizard-dismiss>Go back and save</button><button type="button" data-wizard-continue>Continue anyway</button></div></dialog>
</div>
