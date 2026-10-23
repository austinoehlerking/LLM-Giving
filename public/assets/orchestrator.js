'use strict';
const orchestratorButton = document.querySelector('[data-copy-orchestrator]');
if (orchestratorButton) {
  const text = document.getElementById('orchestrator-handoff');
  const details = document.querySelector('[data-orchestrator-text]');
  const status = document.querySelector('[data-orchestrator-status]');
  orchestratorButton.hidden = false;
  orchestratorButton.addEventListener('click', async () => {
    orchestratorButton.disabled = true;
    status.textContent = 'Copying the complete private handoff…';
    try {
      if (!text.value.trim()) throw new Error('Missing handoff');
      await navigator.clipboard.writeText(text.value);
      status.textContent = 'Copied the complete orchestrator handoff. Paste it privately into your AI.';
    } catch {
      details.open = true;
      text.focus();
      text.select();
      status.textContent = 'Clipboard access was denied or unavailable. Select and copy the complete private handoff below.';
    } finally {
      orchestratorButton.disabled = false;
    }
  });
}
