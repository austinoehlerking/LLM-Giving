<?php $setupConfig=['instructions'=>file_get_contents(ROOT.'/templates/request-setup.txt'),'categories'=>all('SELECT id,name FROM categories WHERE enabled=1 ORDER BY name'),'policy'=>wf_policy(),'maxText'=>(int)env('MAX_TEXT','100000')]; ?>
<template id="request-setup-config"><?=e(json_encode($setupConfig,JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT))?></template>
<p id="request-setup-status" class="fine setup-status" role="status" aria-live="polite" aria-atomic="true"></p>
<div id="request-setup-panel" class="setup-panel" hidden>
<p class="fine">Follow your AI’s questions, then paste its final JSON here. Private details and consent stay on this form.</p>
<label for="paste-setup-prompt">Paste setup prompt</label><textarea data-setup-text id="paste-setup-prompt" rows="4" aria-describedby="request-setup-help request-setup-feedback" spellcheck="false"></textarea>
<p id="request-setup-help" class="fine">Fills the public draft for your review. Nothing is submitted.</p>
<button type="button" class="secondary" id="fill-request-fields">Fill request fields</button><p id="request-setup-feedback" class="warning" role="status" aria-live="polite"></p>
<details><summary>Read or manually copy setup prompt</summary><label for="request-setup-prompt">Setup prompt to copy</label><textarea data-setup-text id="request-setup-prompt" readonly rows="8"><?=e($setupConfig['instructions'])?></textarea></details>
</div>

<script type="module" src="/assets/request-setup.js?v=prompt-copy-validation2"></script>
