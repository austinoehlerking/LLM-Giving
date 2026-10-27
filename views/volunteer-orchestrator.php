<?php $orchestratorHandoff = vo_handoff($orchestratorSession['id'], $orchestratorSession['link']); $orchestratorStart=vo_start_message($orchestratorHandoff); ?>
<section class="return-start" aria-labelledby="orchestrator-heading">
<h2 id="orchestrator-heading">Launch your AI</h2>
<p>Send this short startup as <strong>your own message</strong>. Your AI retrieves the full versioned guidance from your private session. Keep it private.</p>
<div class="actions primary-actions"><button type="button" data-copy-orchestrator hidden>Copy orchestrator handoff</button></div>
<p class="fine" data-orchestrator-status role="status" aria-live="polite">Copying does not start work or reserve a task.</p>
<details class="research-disclosure" data-orchestrator-text><summary>Short startup message for manual copying</summary>
<label for="orchestrator-handoff">Short versioned startup with private session authorization</label>
<textarea id="orchestrator-handoff" readonly rows="12" spellcheck="false" autocomplete="off"><?=e($orchestratorStart)?></textarea>
</details>
<details><summary>Optional complete private handoff</summary><textarea readonly rows="12" spellcheck="false" autocomplete="off" aria-label="Complete private handoff"><?=e($orchestratorHandoff)?></textarea></details><p class="fine">Save the startup privately. Never include its authorization link in research, citations, public screenshots or your final session report.</p>
</section>
<script src="/assets/orchestrator.js" defer></script>
