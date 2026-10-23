# Volunteer orchestrator handoff

Volunteer mode supplies a bounded, sequential controller handoff after the volunteer actually authorizes a session. It reuses the shared reservation, pickup, session/queue and browser-return services. The platform makes no runtime AI calls and starts no background workers.

## Authorization and private delivery

GET /volunteer/orchestrator.txt returns reusable reference instructions only. It creates no session, assignment, consent, reservation or submission permission. The source is templates/volunteer-orchestrator.txt.

The actual authorization response includes **Copy orchestrator handoff** and a native **Complete private handoff for manual copying** disclosure. src/volunteer-orchestrator.php composes those public instructions with a private supplement only after vs_read()/vs_assert_active(). It checks the exact service-issued fragment link against its retained hash and the authorized browser session. The supplement records scope, deadline, allowance, declared capabilities and the independently captured volunteer declaration. It omits actor and credential hashes. Live Stop, expiry and legal-version checks remain authoritative.

Private session/assignment supplements must never enter public archives, context, research packets, citations, downloads, analytics or public screenshots. Copying neither picks up nor renews work. Clipboard success appears only after the write succeeds; denial opens/selects the complete text and reports failure honestly. Native details/textarea work without JavaScript. Volunteer JavaScript has its own lexical scope so the main capability-exchange script cannot prevent session exchange.

## Sequential execution

The controller uses ordinary browser controls: find eligible work, explicitly reserve, obtain its complete current handoff, perform work, check/repair, submit, inspect the recorded receipt, then continue only within the current session scope and limits. One active assignment and the shared actor reservation prevent accumulating parallel claims. There is no idle renewal timer.

Before research, verify the actual worker's required model, reasoning, workflow mode, tool and concrete dependencies. Unknown required configuration is incompatible. Report the worker that performed the assignment; never substitute controller metadata or invent unavailable settings. Each worker receives the complete unchanged archived instructions and current platform context, plus its private assignment delivery. Accepted context for later tasks comes from the platform rather than an accumulated chat summary.

vs_handoff() appends vo_assignment_instructions() only after shared grant validation. It leaves the archived body byte-for-byte unchanged. The private procedure explicitly supersedes archived single-assignment continuation/stale-authorization wording for this validated session path. The worker still stops after its one assignment. Only a controller holding the live session capability may continue or reacquire through its dashboard. Ordinary single-assignment browser returns keep their original stopping boundary.

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
