# Manual pickup reservation integration

This UI layer reuses src/task-reservations.php and its [shared contract](task-reservations.md). The foundation is reconciled to corrected dependency 44ed2c255a206beeb1a957c589cdcd51741b05b2 (fix b308fde6f0ad029df1057bc7ee3efee3c35494dc) and includes current main df5ab6528041d61b9e01045bce6922a7097df3ad, preserving consent and source links. It adds no second reservation service.

## Updated pickup surfaces

| Surface | Deliberate action and behavior |
| --- | --- |
| Study Copy AI handoff | GET /authorize/pickup displays consent without claiming. CSRF POST selects an eligible unreserved task (or resumes the holder's eligible task), claims and prepares the complete authorized handoff under actor-first and parent locks. No available task produces an explanation, never an invented assignment. |
| Task Authorize AI browser submission | Existing CSRF POST /authorize/{taskId} claims and prepares its private handoff atomically through ar_issue. |
| Task Copy task prompt | CSRF POST /tasks/{taskId}/pickup includes the exact task_revision. Claim and full archive preparation share a transaction. Preparation failure rolls back a new claim; failed recopy leaves an existing explained lease unchanged. |
| Task Reserve task / Reacquire reservation | Explicit CSRF operation reservation-claim prepares complete research instructions before reporting success, preserving entered work and signed identifiers. |
| Study manual pickup seam | CSRF POST /research/{slug}/pickup uses the same atomic eligible-task selection and archive preparation. |
| Authorized AI browser return | Existing private fragment exchange preserves the original volunteer actor. Exchange, reads and checks never claim or renew; deliberate controls and submission use that trusted actor. |

The study next-step, For AI agents, workflow list and task views reflect reservations. Supplementary contributions have no selected workflow task and do not reserve one. Public/reference prompts remain readable without claiming or authorizing submission. GET handoff.md, tasks/{id}/prompt.md, issued/{id}, context, packages and ordinary views never claim or renew. Shared submission enforcement is unchanged.

## Status and controls

The holder sees **Reserved for you until [UTC date/time]**. Others see **In progress — reserved until [UTC date/time]**. **Renew reservation** sets expiry to 60 minutes after server time; **Release task** releases it. **Check availability** explicitly refreshes availability when needed; there is no polling or idle renewal timer. Expired renewal fails and offers **Reacquire reservation** only if that exact current task remains eligible and unreserved. Another holder's task offers a status refresh instead.

Controls appear on task, manual handoff and private authorized-handoff views. Task/AI controls post to the existing result form; manual handoff controls post to /tasks/{id}/pickup; private handoff controls post to /pickup/return/{grantId}. Common CSRF applies before dispatch. The private wrapper requires the original actor or the exchanged scoped grant session. The shared low-level /reservation endpoints retain their contract.

Fields are exact task_revision and operation=reservation-claim|reservation-renew|reservation-release|reservation-status. Native forms retain packet text and disclosure. JavaScript replaces only the escaped server-rendered reservation panel; it never edits packet fields or signed identifiers. A conflicting or stale claim explains the problem and retains the work. Grant/revision/consent changes require fresh appropriate authorization and reassessment, not replacement identifiers.

## Privacy and copying

Complete readonly handoff text is available without JavaScript. Clipboard controls are enhancements and only report copy success after writeText succeeds. Denial reports the displayed reservation state and offers complete manual copying. Recopying never renews. Manual copying is operational activity; actual manual result delivery remains **Manually submitted**, while the authorized transport remains **AI browser submission**.

Private supplements never enter public archives, research packets, context, citations or activity exports. Native private handoff controls return the submitted private text only to their authorized private POST response, with bounded validation and escaped rendering; they do not persist it. Public availability contains no reservation generation or actor credentials.

mp_control treats a preloaded grant only as a holder/lookup hint. Inside one actor-before-parent transaction it calls the shared wr_current_grant, compares the requested revision to the returned scope and uses the returned actor for lease operations. The guard current-reads and authenticates the unused grant for the original holder or an established delegated session, including current consent, expiry and requester requirements. Claim and complete handoff preparation remain in the same transaction. Consumed handoffs show Open recorded receipt rather than reacquisition. No packet/POST ownership or session-mode flag is trusted. Dev 5 owns mode-aware ar_instructions and wf_repair_prompt; those helpers remain unchanged by this layer. The existing guidance paragraphs expose data-pickup-help for the separate guide task once its route exists. No dead guide link is published.

## Finalized foundation reconciliation

The earlier 69d7d69 implementation used the shared core replayed as 44d3711. Its 25-suite pass remains historical evidence for that head. This follow-up imports the final core's bounded semantic deltas instead of merging duplicate replay histories:

- Source ancestry fences record Withdrawn events. Pausing or completing a public source's own task workflow does not revoke independent fork work; withdrawal and restoration still invalidate the old lease.
- Migration 016 omits the actor-to-mutex foreign key, avoiding a different holder's actor lock after the research lock during expiry or lifecycle invalidation. Research/task ownership foreign keys and the actor index remain.
- The finalized source, migration, shared contract, core unit tests and two concurrency workers are byte-identical to dependency 44ed2c2. The concurrency tests explicitly hold competing locks for expiry and withdrawal, with 14 assertions. Core tests include source pause/resume versus withdrawal.

The CR-RES-1 follow-up adds the owner’s locked current-grant reader and its 44 barrier-race assertions, with the same guard used by the pickup controller. Its ar_read/record changes are integrated narrowly, retaining the pickup/authorization routes. Pickup regressions exercise actual consumed progress, stale claim/renew/release against a legitimate later lease, original-holder controls without exchange and scoped delegated controls. Initial pickup UI, CSRF routes, private authorization, current main consent and source links are preserved. Closed private handoffs now offer their recorded receipt. Overlapping manual QA fixture fixes already have equivalent explicit claims/releases and the prepared-copy flow in this branch, so the dependency's older fixture versions are not copied over them. Reconciliation QA uses a new owned synthetic schema, preserving the earlier database and evidence rather than rerunning migration 016 in place. No production repair is involved.

## QA

Run node tools/dev.mjs setup, node tools/check.mjs, node tools/qa.mjs pickups, node tools/qa.mjs returns and node tools/qa.mjs handoffs. Shared workflow changes also require the complete regression from docs/development.md.

The new focused PHP and browser suites cover transactional preparation failure, competing anonymous holders, exact expiry, recopy, explicit renew/release, clipboard denial, native forms, preserved drafts and signed IDs, separate-browser AI recording and transport labels. They use synthetic local records and do not assert scientific AI performance.

QA_VIEWPORT_REVIEW=1 pauses after screenshots at 1366x768, 390x844 and 375x667 before target interaction. Inspect them and send a line on standard input to continue. Evidence is under ignored storage/qa/manual-pickup; all-font 200% reflow is checked separately.
