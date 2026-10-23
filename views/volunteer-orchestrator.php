<?php $orchestratorHandoff = vo_handoff($orchestratorSession['id'], $orchestratorSession['link']); ?>
<section class="return-start" aria-labelledby="orchestrator-heading">
<h2 id="orchestrator-heading">Give the session to your AI</h2>
<p>Use an eligible AI with browser control. Keep this handoff private: it carries your bounded session authorization.</p>
<div class="actions primary-actions"><button type="button" data-copy-orchestrator hidden>Copy orchestrator handoff</button></div>
<p class="fine" data-orchestrator-status role="status" aria-live="polite">Copying does not pick up a task, renew a reservation or extend your session. Your AI uses the live queue after opening the private link.</p>
<details class="research-disclosure" data-orchestrator-text><summary>Complete private handoff for manual copying</summary>
<label for="orchestrator-handoff">Complete orchestrator handoff with private session authorization</label>
<textarea id="orchestrator-handoff" readonly rows="12" spellcheck="false" autocomplete="off"><?=e($orchestratorHandoff)?></textarea>
</details>
<p class="fine">Save the complete text privately. Never include its authorization link in research, citations, public screenshots or your final session report.</p>
</section>
<script src="/assets/orchestrator.js" defer></script>
