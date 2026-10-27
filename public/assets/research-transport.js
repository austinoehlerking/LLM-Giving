'use strict';
/** Local browser transport. No model calls, persistent browser storage or implicit Submit. */
(() => {
  const root = document.querySelector('[data-research-transport]');
  if (!root) return;
  const base = root.dataset.base;
  const encoder = new TextEncoder();
  const decoder = new TextDecoder('utf-8', { fatal: true });
  const status = root.querySelector('[data-transport-status]');
  const digest = async bytes => [...new Uint8Array(await crypto.subtle.digest('SHA-256', bytes))].map(b => b.toString(16).padStart(2, '0')).join('');
  const max = 4194304;
  const sameOrigin = url => {
    const resolved = new URL(url, location.origin);
    if (resolved.origin !== location.origin || resolved.hash || resolved.username || resolved.password) throw new Error('Transport destination must remain on the trusted website.');
    return resolved;
  };
  async function request(url, values) {
    const response = await fetch(sameOrigin(url), values ? { method: 'POST', headers: { Accept: 'application/json' }, body: new URLSearchParams({ csrf: root.querySelector('[data-transport-csrf]').value, ...values }) } : { headers: { Accept: 'application/json' } });
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || `Transport HTTP ${response.status}. Resume the existing checkpoint.`);
    return result;
  }
  async function read(manifest, partUrl, ack) {
    if (manifest.version !== 'llm-transport-v1' || manifest.encoding !== 'UTF-8' || manifest.length_unit !== 'bytes' || !Number.isInteger(manifest.byte_length) || manifest.byte_length < 1 || manifest.byte_length > max || manifest.part_count !== manifest.parts.length || manifest.end_marker !== `END LLM TRANSPORT ${manifest.sha256}`) throw new Error('Incomplete or unsupported manifest. Do not start work.');
    const bytes = new Uint8Array(manifest.byte_length);
    let offset = 0;
    for (let index = 0; index < manifest.parts.length; index++) {
      const part = manifest.parts[index];
      if (part.index !== index || !Number.isInteger(part.byte_length) || part.byte_length < 1 || part.byte_length > 16384 || offset + part.byte_length > bytes.length) throw new Error('Missing/reordered or invalid part descriptor.');
      const response = await fetch(sameOrigin(part.url || partUrl(index)), { cache: 'no-store' });
      if (!response.ok) throw new Error(`Part ${index} unavailable (HTTP ${response.status}). Resume; do not reconstruct it.`);
      const data = new Uint8Array(await response.arrayBuffer());
      decoder.decode(data);
      if (data.byteLength !== part.byte_length || await digest(data) !== part.sha256) throw new Error(`Part ${index} was truncated or changed. Retry the original bytes.`);
      bytes.set(data, offset); offset += data.byteLength;
      if (ack) await ack(index, part.sha256);
      status.textContent = `Verified ${index + 1}/${manifest.part_count} parts (${offset} UTF-8 bytes). Nothing submitted.`;
    }
    if (offset !== bytes.length || await digest(bytes) !== manifest.sha256) throw new Error('Whole-stream byte length/digest failed. Nothing finalized.');
    return decoder.decode(bytes);
  }
  const show = text => { root.querySelector('#transport-received').value = text; root.querySelector('[data-transport-result]').open = true; };
  async function receiveInput(stream) {
    const text = await read(stream.manifest, i => `${base}/${stream.id}/parts/${i}`, (index, sha256) => request(`${base}/${stream.id}`, { operation: 'ack', index, sha256 }));
    // Each required dependency is a separate immutable verified resource, never a silent summary.
    for (const resource of stream.manifest.required_resources || []) {
      const manifest = await request(resource.manifest_url);
      const body = await read(manifest);
      if (manifest.sha256 !== resource.digest || encoder.encode(body).length !== resource.byte_length) throw new Error('Required context differs from the pinned directory.');
      const details = document.createElement('details'); const summary = document.createElement('summary'); const textarea = document.createElement('textarea');
      summary.textContent = `Verified required resource: ${resource.name}`; textarea.value = body; textarea.readOnly = true; textarea.rows = 8; textarea.setAttribute('aria-label', summary.textContent);
      details.append(summary, textarea); root.querySelector('[data-transport-resources]').append(details);
      await request(`${base}/${stream.id}`, { operation: 'ack-resource', sha256: resource.digest });
    }
    await request(`${base}/${stream.id}`, { operation: 'finalize' });
    show(text);
    status.textContent = 'Task handoff and all required dependency bytes verified and acknowledged. Read them completely before research. Finalize did not submit or extend authority.';
  }
  async function saveOutput() {
    const file = root.querySelector('#transport-file').files[0];
    if (file && file.size > max) throw new Error('Packet exceeds the 4 MiB private stream limit. Nothing truncated.');
    const bytes = file ? new Uint8Array(await file.arrayBuffer()) : encoder.encode(root.querySelector('#transport-packet').value);
    const text = decoder.decode(bytes);
    if (!bytes.length || bytes.length > max || text.includes('\0')) throw new Error('Supply complete UTF-8 output between 1 byte and 4 MiB.');
    const packet = JSON.parse(text); const binding = JSON.parse(root.querySelector('#transport-binding').value);
    for (const key of ['task_revision', 'context_id', 'submission_key']) if (String(packet[key]) !== String(binding[key])) throw new Error(`Preserve original ${key}. Nothing submitted.`);
    const parts = []; const descriptors = []; let offset = 0;
    while (offset < bytes.length) {
      let end = Math.min(offset + 16384, bytes.length);
      while (end < bytes.length && (bytes[end] & 0xc0) === 0x80) end--;
      const part = bytes.slice(offset, end); const index = parts.length;
      descriptors.push({ index, byte_length: part.length, sha256: await digest(part) }); parts.push(part); offset = end;
    }
    const hash = await digest(bytes);
    const manifest = { version: 'llm-transport-v1', encoding: 'UTF-8', length_unit: 'bytes', binding, part_count: parts.length, byte_length: bytes.length, sha256: hash, parts: descriptors, end_marker: `END LLM TRANSPORT ${hash}` };
    const stream = await request(base, { operation: 'begin', direction: 'output', manifest: JSON.stringify(manifest) });
    for (let index = 0; index < parts.length; index++) {
      if (stream.received.includes(index)) continue;
      let binary = ''; for (const byte of parts[index]) binary += String.fromCharCode(byte);
      const ack = await request(`${base}/${stream.id}`, { operation: 'put', index, body_base64: btoa(binary) });
      if (!ack.acknowledged.includes(index)) throw new Error(`Server did not acknowledge part ${index}. Resume this checkpoint.`);
      status.textContent = `Saved ${index + 1}/${parts.length} original output parts privately. Nothing submitted.`;
    }
    const finalized = await request(`${base}/${stream.id}`, { operation: 'finalize' });
    readyOutput(finalized);
  }
  function readyOutput(stream) {
    if (stream.state !== 'finalized') throw new Error('Output is incomplete. Supply the same original packet to resume its missing parts.');
    const form = root.querySelector('[data-transport-submit]'); form.querySelector('[name=transport_id]').value = stream.id; form.hidden = false;
    root.querySelector('[data-transport-final]').hidden = false;
    status.textContent = 'Complete output finalized privately. No Check or Submit performed. Use the separate actions below; current authorization still applies.';
  }
  const action = fn => async event => {
    const button = event.currentTarget; button.disabled = true;
    try { if (!crypto.subtle) throw new Error('Integrity verification requires a secure browser context. Use the manifest with a capable trusted receiver.'); await fn(); }
    catch (error) { status.textContent = `${error.message} No partial result was submitted. Keep the existing checkpoint and original packet.`; }
    finally { button.disabled = false; }
  };
  root.querySelector('[data-transport-read]')?.addEventListener('click', action(async () => { const manifest = await request(`${base}/manifest`); show(await read(manifest)); status.textContent = 'All original bytes verified. Read the complete instructions and required dependencies before work. Nothing submitted.'; }));
  root.querySelector('[data-transport-input]')?.addEventListener('click', action(async () => receiveInput(await request(base, { operation: 'begin', direction: 'input' }))));
  root.querySelector('[data-transport-output]')?.addEventListener('click', action(saveOutput));
  for (const button of root.querySelectorAll('[data-delete-stream]')) button.addEventListener('click', action(async () => {
    if (!confirm('Delete this private transport checkpoint? Keep the original packet if it is still needed. Research results and receipts remain unchanged.')) return;
    await request(`${base}/${button.dataset.deleteStream}`, { operation: 'delete' }); button.closest('li').remove(); status.textContent = 'Private checkpoint deleted. Research results and receipts unchanged.';
  }));
  for (const button of root.querySelectorAll('[data-resume-stream]')) button.addEventListener('click', action(async () => {
    const stream = await request(`${base}/${button.dataset.resumeStream}`);
    if (stream.direction === 'input') return receiveInput(stream);
    if (stream.state === 'finalized') { readyOutput(stream); show(await read(stream.manifest, i => `${base}/${stream.id}/parts/${i}`)); }
    else status.textContent = `Recovered ${stream.received.length}/${stream.manifest.part_count} parts. Supply the same original complete packet and choose Save complete packet to resume. Nothing submitted.`;
  }));
})();