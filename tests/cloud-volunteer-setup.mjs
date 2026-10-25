/** Local package validation only: never launches Dot, a browser, a server or research. */
import assert from 'node:assert/strict';
import './launch-preflight.mjs';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..');
const packagePath = path.join(root, 'volunteer/codex-cloud');
const expectedFiles = ['README.md', 'initialization.txt', 'launch-preflight.mjs', 'setup.json'];
const checks = [];
function check(name, action) { action(); checks.push(name); }

function validateSetup(setup) {
  assert.equal(setup.schema_version, 2);
  assert.equal(setup.setup_version, '2.1.0');
  assert.equal(setup.product, 'ChatGPT Dot');
  assert.equal(setup.status, 'controller_worker_single_assignment_verified_phone_pending');
  assert.equal(setup.documentation_checked_on, '2026-10-24');
  assert.deepEqual(setup.profile, {session_profile: 'restricted_web_v1', label: 'Restricted web research', setup_path: '/volunteer?session_profile=restricted_web_v1'});
  assert.deepEqual(setup.environment, {runtime:'dot_native_cloud_browser',personal_computer_required:false,repository_required:false,published_codex_environment_required:false,model:null,reasoning_effort:null,install_script:'',start_services:[],environment_variables:{},network_secrets:[],initialization_file:'initialization.txt'});
  assert.equal(setup.network.mode, 'Instruction scope; not provider configuration');
  assert.deepEqual(setup.network.required_https_hosts, ['llm.giving']);
  assert.equal(setup.network.https_port, 443);
  assert.equal(setup.network.private_network, false);
  assert.match(setup.network.source_hosts, /does not configure a domain allowlist/);
  assert.deepEqual(setup.capabilities, {
    cloud_source_search:'not_established_by_submission_proof',cloud_source_reading:'verified_single_synthetic_task',cloud_interactive_browser:'verified_single_synthetic_task',actual_model_settings:'controller_recorded_worker_launch_only',phone_launch:'not_tested',multi_task_controller:'not_tested',model_derived_arithmetic:'not_tested',dot_to_codex_workers:'verified_one_accepted_synthetic_assignment',production_accepted_result:'one_accepted_synthetic_task'
  });
  assert.deepEqual(setup.configured_worker, {mode:'dot_controller_codex_cloud_worker',model:'gpt-6.1-sol',thinking:'high',basis:'controller_recorded_native_launch',provider_effective_readback:'unavailable',provider_verified:false,published_private_configuration_required:true,configuration_id:null,one_active_worker:true,browser_owner:'Dot controller',packet_author:'research worker',repair_author:'same research worker'});
  assert.deepEqual(setup.verification, {checked_on:'2026-10-24',public_result_url:'https://llm.giving/research/qa-dot-sol-high-cloud-worker-packet-and-browser-receipt-2026-10-24-dotsol20261024b01-3c1bb7ce8f/results/bc0c24cfcf533ac4804135d6b2c637e2',result_id:'bc0c24cfcf533ac4804135d6b2c637e2',admission:'accepted',outcome:'completed',submission_method:'ai_browser',task_revision:1,stored_results_after_check:0,stored_results_after_receipt_reopen:1,scope:'One actual Dot to configured gpt-6.1-sol/high cloud research worker to Dot browser assignment; no phone, multi-task or arithmetic acceptance.',owner_amended_preflight:true,human_influence:'influenced',admission_path:'quarantined_then_explicit_admin_approval',submitted_payload_matched_worker_packet:true,provider_verified:false});
  assert.equal(setup.prior_native_browser_proof.result_id,'cec5f4649f0446fc6b7622ddad933f97');
  assert.equal(setup.prior_native_browser_proof.admission,'accepted');
  assert.notEqual(setup.prior_native_browser_proof.result_id,setup.verification.result_id);
  assert.match(setup.restrictions.enforcement, /no provider sandbox attestation/i);
  assert.deepEqual(setup.restrictions.allowed, ['web_search', 'accessible_source_reading', 'synthesis_and_reasoning', 'model_derived_arithmetic', 'llm_giving_browser_forms', 'native_tool_orchestration_javascript']);
  for (const capability of ['research_code_execution', 'shell_execution', 'calculators', 'spreadsheets', 'notebooks', 'simulations', 'scientific_software', 'research_tool_installation', 'tool_building', 'computational_reproduction', 'desktop_access', 'personal_files', 'api_funded_substitute']) assert.ok(setup.restrictions.excluded.includes(capability), 'Missing exclusion ' + capability);
  assert.match(setup.credential_delivery, /Private complete per-run handoff after human authorization and actual capability checks/);
  assert.deepEqual(setup.documentation, ['https://learn.chatgpt.com/docs/dots/getting-started','https://learn.chatgpt.com/docs/dots/computers-and-apps','https://learn.chatgpt.com/docs/dots/controls','https://learn.chatgpt.com/docs/environments/cloud-environments']);
}

// These are narrow leakage checks, not a complete secret scanner.
function assertCredentialFree(text) {
  assert.ok(!/\b[a-f0-9]{64}\b/i.test(text), 'Credential/hash-shaped value in reusable setup');
  assert.ok(!/\b(?:ghp_|github_pat_|sk-)[a-z0-9_-]{20,}/i.test(text), 'API/access token in reusable setup');
  assert.ok(!/-----BEGIN (?:[A-Z ]+ )?PRIVATE KEY-----/.test(text), 'Private key in reusable setup');
  assert.ok(!/https?:\/\/[^\s)]+[#?](?:[^\s)]*=)?[a-z0-9_-]{32,}/i.test(text), 'Private link in reusable setup');
  assert.ok(!/https?:\/\/[^\s/@]+:[^\s/@]+@/.test(text), 'URL credentials in reusable setup');
}

check('Only the reviewed instructions and pure controller diagnostic exist; no credentials or installers', () => {
  assert.deepEqual(fs.readdirSync(packagePath).sort(), expectedFiles);
  for (const name of expectedFiles) assert.ok(fs.lstatSync(path.join(packagePath, name)).isFile(), 'Not a regular file: ' + name);
});
const setup = JSON.parse(fs.readFileSync(path.join(packagePath, 'setup.json'), 'utf8'));
check('Versioned Dot checklist binds limited real proof and preserves untested claims/minimal permissions', () => validateSetup(setup));
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
  ['unbounded browser success', candidate => { candidate.capabilities.cloud_interactive_browser = 'pass'; }],
  ['invented multi-task acceptance', candidate => { candidate.capabilities.multi_task_controller = 'pass'; }],
  ['invented arithmetic acceptance', candidate => { candidate.capabilities.model_derived_arithmetic = 'pass'; }],
  ['unbounded worker acceptance', candidate => { candidate.capabilities.dot_to_codex_workers = 'pass'; }],
  ['provider-effective worker claim', candidate => { candidate.configured_worker.provider_verified = true; }],
  ['invented effective readback', candidate => { candidate.configured_worker.provider_effective_readback = 'verified'; }],
  ['wrong configured model', candidate => { candidate.configured_worker.model = 'gpt-6-sol'; }],
  ['wrong configured effort', candidate => { candidate.configured_worker.thinking = 'max'; }],
  ['private configuration baked in', candidate => { candidate.configured_worker.configuration_id = 'private-config'; }],
  ['hidden original influence', candidate => { candidate.verification.human_influence = 'none'; }],
  ['automatic quarantine approval claim', candidate => { candidate.verification.admission_path = 'automatic'; }],
  ['invented source search proof', candidate => { candidate.capabilities.cloud_source_search = 'verified'; }],
  ['Check mistaken for submission', candidate => { candidate.verification.stored_results_after_check = 1; }],
  ['duplicate receipt recovery', candidate => { candidate.verification.stored_results_after_receipt_reopen = 2; }],
  ['quarantine mistaken for acceptance', candidate => { candidate.verification.admission = 'quarantined'; }],
  ['published Codex environment requirement', candidate => { candidate.environment.published_codex_environment_required = true; }],
  ['personal-computer permission', candidate => { candidate.environment.personal_computer_required = true; }],
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

console.log(JSON.stringify({status: 'PASS', checks: checks.length, assertions: checks, limits: 'Local inert-package/configuration/link and narrow credential checks only. Local checks do not execute Dot, establish phone/multi-task/arithmetic acceptance, attest provider settings or prove sandboxing. The separately recorded single-task browser proof is explicitly bounded.'}, null, 2));
