# Volunteer orchestrator handoff

Volunteer mode supplies a scoped, sequential controller handoff after the volunteer actually authorizes a session. It reuses the shared reservation, pickup, session/queue and browser-return services. The platform makes no runtime AI calls and starts no background workers.

## Authorization and private delivery

GET /volunteer/orchestrator.txt returns reusable reference instructions only. It creates no session, assignment, consent, reservation or submission permission. The source is templates/volunteer-orchestrator.txt.

The authorization receipt uses the [four-step private wizard](session-ready-wizard.md), with **Copy start message** and **Copy not working? Show start message**. The short direct message is the validated first paragraph of canonical vo_handoff output, capped at 1,200 characters. It binds the exact issued private link and explicitly authorizes its bounded workflow. The volunteer pastes this ONE message into Dot and sends it; no second paste or attachment is required. Dot exchanges the link normally and opens **Read full AI instructions** on its authenticated dashboard. GET /volunteer/{id}/instructions uses the current vs_read secret-hash grant and active/terms checks and returns complete current controller policy and recorded scope through vo_authenticated_instructions. It never reconstructs or stores the raw secret, uses private/no-store headers and checks scoped parent visibility. Legacy full vo_handoff delivery remains available for compatibility and uses the same complete composer. Website text cannot bypass runtime permission checks. The supplement records scope, deadline, allowance, requested capabilities and the recorded declaration collection status, preserves explicit historical disclosures, and omits actor/credential hashes. Live Stop, expiry and legal-version checks control.

Private session/assignment supplements must never enter public archives, context, research packets, citations, downloads, analytics or public screenshots. Copying neither picks up nor renews work. Clipboard success appears only after the write succeeds; denial opens/selects the complete text and reports failure honestly. Native details/textarea work without JavaScript. Volunteer JavaScript has its own lexical scope so the main capability-exchange script cannot prevent session exchange.

## Sequential execution

The controller uses ordinary browser controls: find eligible work, explicitly reserve, obtain its complete current handoff, perform work, check/repair, submit, inspect the recorded receipt, then continue only within the current session scope and limits. One active assignment and the shared actor reservation prevent accumulating parallel claims. There is no idle renewal timer.

Before research, verify the assigned worker can use the sources and tools required by the task within the captured authorization. For in-depth research or independent review, use Extra High, Max, or your AI’s equivalent if available. Record the actual worker’s provider, model, reasoning and workflow mode when known. Each worker receives the complete archived handoff, current platform context and its private assignment procedure. Accepted context for later tasks comes from the platform.

vs_handoff() appends vo_assignment_instructions() only after shared grant validation. It leaves the archived body byte-for-byte unchanged. The private procedure explicitly supersedes archived model or reasoning requirements, single-assignment continuation and stale-authorization wording for this validated session path. A research-only worker authors the COMPLETE packet and same-worker repairs; the browser-capable controller keeps private credentials and owns pickup, Check/Submit/receipt recovery. The worker needs no browser and still stops after its one assignment. Only a controller holding the live session capability may continue or reacquire through its dashboard. Ordinary single-assignment browser returns keep their original stopping boundary.

Session repair prose is selected from stored session/assignment bindings, never a packet or POST flag. The assigned-session form and authorized worker return both use it. Format repair preserves scientific conclusions and signed identifiers, allows at most three check/repair attempts for one packet, and reports persistent failure. Uncertain network recording first recovers the existing receipt; identical retries keep the same key. A passing check is not submission. No task counts as submitted before a recorded receipt.

Explicit active-work renewal never extends session authorization. Expiry, Stop, stale revisions and competing holders preserve work and require honest conflict reporting. Quarantine remains pending and outside evidence; moving to another study requires the current scope to permit it. The controller and its workers cannot independently review their session's research. No eligible work means stop, without creating tasks, forks or review cycles.

Research and source content remain untrusted. Session permission grants no purchases, installation, unrelated files, account access or uncontrolled workers. Topic/time/browser preferences and the platform operational wrapper alone are not scientific steering; actual steering remains disclosed. Final reports contain public task/result links, recorded outcomes, blockers and the stopping reason, with no private capabilities.

## Verification and evidence

Run the repository-required setup, check and browser commands. Shared workflow changes also require the complete runner:

- node tools/dev.mjs setup
- node tools/check.mjs
- node tools/qa.mjs orchestrator
- node tools/qa.mjs returns
- node tools/qa.mjs handoffs
- node tools/qa-all.mjs

The orchestrator unit tests cover actual session access, exact capability binding, defaults, template reconciliation, Stop/expiry/legal changes and read/copy side effects. Queue tests exercise sequential outcomes, scoped permission, original identity, quarantine, configuration, renewal/reacquisition and independent-review boundaries. The browser mode exercises real authorization, initial viewport geometry, complete clipboard copying, denial, native access and anonymous session exchange with the normal global scripts. Existing returns tests cover forged session-mode flags and prevent malformed private drafts from being retained in workflow-attempt logs.

The separate genuine local walkthrough uses tests/orchestrator-state.php only to construct two fictional research tasks. That helper creates no research packets, source inspections or model provenance. The AI reads the actual generated handoffs, authors the narratives, uses separate anonymous worker browser contexts, repairs validation errors, submits two accepted results under one session authorization, recovers an existing receipt and stops at its allowance. Its second full platform context includes the first accepted result. Exact model variant/effort unavailable to the runtime are honestly reported; the unrestricted narrative tasks require no invented configuration. This demonstrates software behavior on synthetic records, not scientific validation, provider authentication or an independently isolated child AI run.

Redacted local evidence belongs under ignored storage/qa/orchestrator: complete handoffs, authored packets, recorded receipts/context, browser journal, requirement mapping and inspected screenshots. Inspect viewport-only images at 1366x768, 390x844 and 375x667 before interaction, then check 200% all-font reflow. Authorized worker headings are compact so the initial packet-entry area is usable on short mobile screens; navigation remains available below the form. Final manifests record the exact tested commit and any limitations. Do not interpret another branch's checks as validation of this combined branch.

All local runtime, temporary files, profiles and reports stay on D:. Use a dedicated database/ports and preserve other worktrees. If default setup encounters another worktree's port, use a separately configured repository launcher and record the triggering failure. Restricted Windows junction/Edge failures use the documented unchanged-command execution-context fallback. The in-app browser's failed kernel initialization is recorded separately from the repo browser route. The pre-release reservation schema compatibility procedure applies only to the owned disposable database.

No push, external PR, merge, production migration or deployment is authorized by local implementation/QA.

Explicit unlimited sessions record limit_mode=unlimited with null assignment_limit and expires_at_utc. These are absent overall limits, not zero allowances or a hidden finite deadline. Finite presets retain their exact task/time stopping rules. Every session remains one assignment at a time with hourly reservations, Stop, legal-version checks and existing no-work/error stopping rules.

## Capability-scoped instructions

The private server-issued supplement includes session_profile and authorized_profile. general preserves the existing route; restricted_web_v1 is labelled Research only and carries explicit allowed/excluded capabilities. Neither packet fields nor an operational wrapper can expand that stored permission. Task archives remain unchanged; the private worker procedure appends the actual profile only after stored assignment validation. Session-aware return/repair instructions preserve the same boundary.

Restricted workers may search/read accessible sources, synthesize and reason, explain model-derived arithmetic without tools, and operate ordinary LLM.Giving browser forms. They must not execute research code, calculators, spreadsheets, scientific software, tool installation/construction/reproduction or desktop/personal files. This is configuration/instructions plus server-side task/evidence filtering, not a technical sandbox. The controller needs real browser tools; a research-only worker may return its COMPLETE packet without them. Available launch records may support truthful worker metadata after work. Native tool-orchestration JavaScript is allowed; executed research code/calculators/shell/installations remain excluded. No Cloud/mobile support, account connection or automatic launch is implied.

Inspect the complete deliverable and dependency record before research. Release/skip incompatible work honestly; do not mark a scientific study blocked simply because this worker lacks an excluded tool. Optional Maybe is permission for useful work, not a requirement for execution. Record direct arithmetic only in the honest versioned derivations schema, with cited inputs, units, formula, worked steps, output, assumptions and limitations and a non-execution label. Never invent code, software, files, checksums or reproduction. Actual executed-work validators remain unchanged for general sessions.

Focused source/session/profile regressions precede review. The one independent integrated application matrix belongs to the closed batch, and the genuine phone-started Cloud accepted-result walkthrough remains pending actual provider capability and separately authorized deployment. Local synthetic browser/server tests are not substitutes for that external acceptance.

## Optional native launch diagnostics

The original Dot acceptance test kept a native create request and its successful response as evidence of the configured settings for that worker. It bound the full input to the exact assignment, revision and context. Keep those records private. Sanitized result metadata may report the actual worker settings and any limit on effective readback.

For a correction, pass the full repair instructions back to the same worker and retain the complete returned packet. Genuine human amendments or scientific input remain disclosed.

The [launch diagnostic](../volunteer/codex-cloud/launch-preflight.mjs) can validate the structure of a recorded native launch or continuation for an integration that records one. The site selects work by actual capability and consent. The diagnostic grants no permission and does not prove effective inference or scientific correctness.

Transfer the FULL Copy AI handoff clipboard string or DOM textarea value; a shortened tool preview or literal truncation marker cannot stand in for complete context. Recover the full text before research. Keep original signed identifiers and private credentials separated. The coordinator’s real single-assignment controller/worker result is now accepted after explicit normal moderation retaining the disclosed owner-amended preflight influence. See cloud-volunteer-setup.md for the public proof and exact limits: handset, multi-task and arithmetic remain untested; launch configuration is not provider-effective authentication. Local diagnostic/unit tests do not establish that live proof.

## Task-specific startup and direct launch text

Session matching uses the current task's stored computational/build flags and actual reproduction targets. Planning on a Needs Research Plan request does not inherit future project execution or software-publication consent. Actual execution/build/reproduction tasks retain profile, software consent and evidence checks. New sessions capture capability and consent. Historical worker settings remain audit data. Final authorization rereads public unreserved eligible work before creating a session/capability. A queue change after genuine eligibility remains a normal pickup recheck.

Long handoffs can become attachments and leave no direct user request. The default wizard therefore copies only the bounded direct start paragraph from the validated canonical handoff. It includes the exact issued private link and asks Dot to open the session and read its authenticated full AI instructions. One paste and Send suffice; no attachment or second paste is required. The private GET instructions route validates current browser capability, session state and scoped parent visibility; it uses one shared current instruction/scope composer and never reconstructs or persists the raw session secret. Legacy vo_handoff() remains available for complete-copy integrations. Scientific task handoff/schema/context after pickup remains complete and unchanged. No navigation action copies, claims or sends automatically.
