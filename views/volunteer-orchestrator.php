<?php $orchestratorHandoff = vo_handoff($orchestratorSession['id'], $orchestratorSession['link']); ?>
<section class="return-start" aria-labelledby="orchestrator-heading">
<h2 id="orchestrator-heading">Launch your AI</h2>
<p>Review and send the complete copy as <strong>your own message</strong>. Keep it private.</p>
<div class="actions primary-actions"><button type="button" data-copy-orchestrator hidden>Copy orchestrator handoff</button></div>
<p class="fine" data-orchestrator-status role="status" aria-live="polite">Copying does not start work or reserve a task.</p>
<details class="research-disclosure" data-orchestrator-text><summary>Complete private handoff for manual copying</summary>
<label for="orchestrator-handoff">Complete orchestrator handoff with private session authorization</label>
<textarea id="orchestrator-handoff" readonly rows="12" spellcheck="false" autocomplete="off"><?=e($orchestratorHandoff)?></textarea>
</details>
<p class="fine">Save the complete text privately. Never include its authorization link in research, citations, public screenshots or your final session report.</p>
</section>
<script src="/assets/orchestrator.js" defer></script>
