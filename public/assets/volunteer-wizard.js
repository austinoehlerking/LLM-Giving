'use strict';
(() => {
  const wizard = document.querySelector('[data-volunteer-wizard]');
  if (!wizard) return;
  const panes = [...wizard.querySelectorAll('[data-wizard-pane]')];
  const markers = [...wizard.querySelectorAll('[data-wizard-marker]')];
  const confirm = wizard.querySelector('[data-wizard-confirm]');
  const next = wizard.querySelector('[data-wizard-next="2"]');
  const form = wizard.querySelector('[data-wizard-email]');
  const emailToggle = wizard.querySelector('[data-wizard-email-toggle]');
  const emailDisclosure = wizard.querySelector('[data-wizard-email-disclosure]');
  let methodClicked = wizard.dataset.emailInteracted === 'true';
  let skipAcknowledged = false;
  let current = 1;
  let sending = false;
  function show(step, focus = true) {
    current = step;
    panes.forEach(pane => { pane.hidden = Number(pane.dataset.wizardPane) !== step; });
    markers.forEach(marker => { if (Number(marker.dataset.wizardMarker) === step) marker.setAttribute('aria-current', 'step'); else marker.removeAttribute('aria-current'); });
    if (focus) wizard.querySelector('#wizard-title-' + step).focus({preventScroll: true});
  }
  wizard.classList.add('is-enhanced');
  wizard.querySelectorAll('[data-wizard-copy],[data-wizard-email-toggle],[data-wizard-next],[data-wizard-back]').forEach(button => { button.hidden = false; });
  if (form.hasAttribute('data-initially-collapsed')) form.hidden = true;
  emailDisclosure.open = !form.hidden;
  show(1, false);
  wizard.querySelectorAll('[data-wizard-next]').forEach(button => button.addEventListener('click', () => {
    if (current === 1 && !methodClicked && !skipAcknowledged) { confirm.showModal(); return; }
    show(Number(button.dataset.wizardNext));
  }));
  wizard.querySelectorAll('[data-wizard-back]').forEach(button => button.addEventListener('click', () => show(Number(button.dataset.wizardBack))));
  wizard.querySelectorAll('[data-wizard-dismiss]').forEach(button => button.addEventListener('click', () => confirm.close()));
  confirm.addEventListener('close', () => { if (current === 1) next.focus({preventScroll: true}); });
  wizard.querySelector('[data-wizard-continue]').addEventListener('click', () => { skipAcknowledged = true; confirm.close(); show(2); });
  emailToggle.addEventListener('click', () => {
    methodClicked = true;
    form.hidden = !form.hidden;
    emailDisclosure.open = !form.hidden;
    emailToggle.setAttribute('aria-expanded', String(!form.hidden));
    if (!form.hidden) form.querySelector('[name=email]').focus({preventScroll: true});
  });
  wizard.querySelectorAll('[data-wizard-copy]').forEach(button => button.addEventListener('click', async () => {
    const kind = button.dataset.wizardCopy;
    if (kind === 'link') methodClicked = true; // An attempt is not a successful save.
    const text = wizard.querySelector(kind === 'link' ? '#volunteer-private-link' : '#orchestrator-handoff');
    const status = wizard.querySelector('[data-wizard-copy-status="' + kind + '"]');
    button.disabled = true;
    status.textContent = 'Copying…';
    try {
      if (!text.value.trim()) throw new Error('Missing private text');
      await navigator.clipboard.writeText(text.value);
      status.textContent = kind === 'link' ? 'Session link copied. Save it somewhere private if you want to return later.' : 'Start message copied. Paste this one message into Dot and press Send.';
    } catch {
      wizard.querySelector(kind === 'link' ? '[data-wizard-link-manual]' : '[data-wizard-instructions-manual]').open = true;
      text.focus({preventScroll: true}); text.select();
      status.textContent = 'Clipboard access was denied or unavailable. Select and copy the complete text below.';
    } finally { button.disabled = false; }
  }));
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (sending || !form.reportValidity()) return;
    const send = form.querySelector('button[type=submit]');
    const status = form.querySelector('[data-wizard-email-status]');
    const submittedAttempt = form.querySelector('[name=email_attempt]').value;
    sending = true; send.disabled = true;
    status.textContent = 'Sending…';
    try {
      const response = await fetch(form.action, {method: 'POST', body: new FormData(form), headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
      const result = await response.json();
      status.textContent = result.message || "We couldn't send the email. Copy your session link instead.";
      if (!((result.state === 'accepted' && result.ok === true) || (result.state === 'failed' && result.ok === false))) {
        form.dataset.emailState = result.state === 'pending' ? 'pending' : 'unknown';
        form.querySelector('[name=email_attempt]').value = submittedAttempt;
      } else {
        form.dataset.emailState = result.ok ? 'accepted' : 'failed';
        if (typeof result.next_attempt === 'string' && /^[a-f0-9]{32}$/.test(result.next_attempt)) form.querySelector('[name=email_attempt]').value = result.next_attempt;
      }
    } catch {
      // Retain the attempt key: an explicit retry safely recovers an uncertain acceptance.
      form.dataset.emailState = 'unknown';
      form.querySelector('[name=email_attempt]').value = submittedAttempt;
      status.textContent = 'Email outcome is unknown. Copy your session link, or explicitly retry to recover the same send attempt.';
    } finally { sending = false; send.disabled = false; }
  });
})();
