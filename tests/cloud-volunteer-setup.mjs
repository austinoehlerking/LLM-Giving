/** Local package validation only: never launches Cloud, a browser, a server or research. */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..');
const packagePath = path.join(root, 'volunteer/codex-cloud');
const expectedFiles = ['README.md', 'initialization.txt', 'setup.json'];
const checks = [];
function check(name, action) { action(); checks.push(name); }

function validateSetup(setup) {
  assert.equal(setup.schema_version, 1);
  assert.equal(setup.setup_version, '1.0.0');
  assert.equal(setup.product, 'Codex Cloud');
  assert.equal(setup.status, 'blocked_browser_capability');
  assert.match(setup.documentation_checked_on, /^\d{4}-\d{2}-\d{2}$/);
  assert.deepEqual(setup.profile, {session_profile: 'restricted_web_v1', label: 'Restricted web research', setup_path: '/volunteer?session_profile=restricted_web_v1'});
  const environment = setup.environment;
  for (const field of ['published_environment_id', 'published_revision', 'cloud_task_id', 'model', 'reasoning_effort']) assert.equal(environment[field], null, 'Unobserved ' + field);
  assert.equal(environment.sharing, 'Only me');
  assert.equal(environment.install_script, '');
  assert.deepEqual(environment.start_services, []);
  assert.deepEqual(environment.environment_variables, {});
  assert.deepEqual(environment.network_secrets, []);
  assert.equal(environment.initialization_file, 'initialization.txt');
  assert.equal(setup.network.mode, 'Custom domains only');
  assert.deepEqual(setup.network.required_https_hosts, ['llm.giving']);
  assert.equal(setup.network.https_port, 443);
  assert.equal(setup.network.private_network, false);
  assert.ok(setup.network.source_hosts.trim());
  assert.deepEqual(setup.capabilities, {
    cloud_source_search: 'not_tested', cloud_source_reading: 'not_tested',
    cloud_interactive_browser: 'documented_unsupported', actual_model_settings: 'unavailable',
    phone_launch: 'not_tested', production_accepted_result: 'pending_deployment_and_cloud_browser',
  });
  assert.match(setup.restrictions.enforcement, /no provider sandbox attestation/i);
  assert.deepEqual(setup.restrictions.allowed, ['web_search', 'accessible_source_reading', 'synthesis_and_reasoning', 'model_derived_arithmetic', 'llm_giving_browser_forms']);
  for (const capability of ['research_code_execution', 'shell_execution', 'calculators', 'spreadsheets', 'notebooks', 'simulations', 'scientific_software', 'research_tool_installation', 'tool_building', 'computational_reproduction', 'desktop_access', 'personal_files', 'api_funded_substitute']) assert.ok(setup.restrictions.excluded.includes(capability), 'Missing exclusion ' + capability);
  assert.match(setup.credential_delivery, /Private complete per-run handoff only after capability verification/);
  assert.deepEqual(setup.documentation, ['https://learn.chatgpt.com/docs/environments/cloud-environments', 'https://learn.chatgpt.com/docs/mobile']);
}

// These are narrow leakage checks, not a complete secret scanner.
function assertCredentialFree(text) {
  assert.ok(!/\b[a-f0-9]{64}\b/i.test(text), 'Credential/hash-shaped value in reusable setup');
  assert.ok(!/\b(?:ghp_|github_pat_|sk-)[a-z0-9_-]{20,}/i.test(text), 'API/access token in reusable setup');
  assert.ok(!/-----BEGIN (?:[A-Z ]+ )?PRIVATE KEY-----/.test(text), 'Private key in reusable setup');
  assert.ok(!/https?:\/\/[^\s)]+[#?](?:[^\s)]*=)?[a-z0-9_-]{32,}/i.test(text), 'Private link in reusable setup');
  assert.ok(!/https?:\/\/[^\s/@]+:[^\s/@]+@/.test(text), 'URL credentials in reusable setup');
}

check('Only the three reviewed inert package files exist; no hidden credentials or installers', () => {
  assert.deepEqual(fs.readdirSync(packagePath).sort(), expectedFiles);
  for (const name of expectedFiles) assert.ok(fs.lstatSync(path.join(packagePath, name)).isFile(), 'Not a regular file: ' + name);
});
const setup = JSON.parse(fs.readFileSync(path.join(packagePath, 'setup.json'), 'utf8'));
check('Versioned configuration preserves blocked/unverified status and minimal permissions', () => validateSetup(setup));
for (const file of [...expectedFiles.map(name => 'volunteer/codex-cloud/' + name), 'docs/cloud-volunteer-setup.md']) {
  const text = fs.readFileSync(path.join(root, file), 'utf8');
  check('Credential-free public file: ' + file, () => assertCredentialFree(text));
  check('Complete UTF-8 text: ' + file, () => { assert.ok(text.endsWith('\n')); assert.ok(!text.includes('\ufffd')); });
  if (file.endsWith('.md')) check('Local documentation links resolve: ' + file, () => {
    for (const match of text.matchAll(/\]\(([^)]+)\)/g)) {
      const target = match[1];
      if (/^https:/.test(target)) { const url = new URL(target); assert.equal(url.username + url.password, ''); continue; }
      assert.ok(!/^[a-z]+:/i.test(target), 'Unexpected URI');
      const resolved = path.resolve(root, path.dirname(file), target.split('#')[0]);
      const relative = path.relative(root, resolved);
      assert.ok(relative && !relative.startsWith('..') && !path.isAbsolute(relative), 'Link escapes repository');
      assert.ok(fs.statSync(resolved).isFile(), 'Missing link: ' + target);
    }
  });
}

const mutations = [
  ['false readiness', candidate => { candidate.status = 'ready'; }],
  ['unverified browser success', candidate => { candidate.capabilities.cloud_interactive_browser = 'pass'; }],
  ['invented phone acceptance', candidate => { candidate.capabilities.phone_launch = 'pass'; }],
  ['invented model identity', candidate => { candidate.environment.model = 'invented'; }],
  ['baked-in per-session secret', candidate => { candidate.environment.network_secrets.push('not-a-real-secret'); }],
  ['environment variable handoff', candidate => { candidate.environment.environment_variables.HANDOFF = 'private'; }],
  ['installation workaround', candidate => { candidate.environment.install_script = 'install-browser'; }],
  ['background service', candidate => { candidate.environment.start_services.push('server'); }],
  ['broader profile', candidate => { candidate.profile.session_profile = 'general'; }],
  ['unrestricted network', candidate => { candidate.network.mode = 'All (unrestricted)'; }],
  ['private network workaround', candidate => { candidate.network.private_network = true; }],
  ['missing calculator restriction', candidate => { candidate.restrictions.excluded = candidate.restrictions.excluded.filter(value => value !== 'calculators'); }],
];
for (const [name, mutate] of mutations) check('Reject ' + name, () => {
  const candidate = structuredClone(setup); mutate(candidate);
  assert.throws(() => validateSetup(candidate));
});
const leakedExamples = ['d'.repeat(64), 'sk-' + 'x'.repeat(24), 'https://example.invalid/#' + 'x'.repeat(43), 'https://user:password@example.invalid/'];
for (let index = 0; index < leakedExamples.length; index++) check('Reject synthetic credential example ' + (index + 1), () => assert.throws(() => assertCredentialFree(leakedExamples[index])));

console.log(JSON.stringify({status: 'PASS', checks: checks.length, assertions: checks, limits: 'Local inert-package/configuration/link and narrow credential checks only. No Cloud/phone/source/browser/production acceptance and no provider sandbox proof.'}, null, 2));
