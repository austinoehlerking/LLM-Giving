# Volunteer sessions — integration contract

Volunteer sessions authorize a scoped, sequential donation using the shared reservation, handoff and browser-return services. The website coordinates work; it makes no runtime AI calls and starts no background workers. Session permission never replaces task eligibility, model requirements, legal consent, evidence validation or independent review.

This composition includes current main df5ab6528041d61b9e01045bce6922a7097df3ad, corrected core44ed2c255a206beeb1a957c589cdcd51741b05b2, manual callerdf63e4d3b2d745abd71809705a3c57f3b320745e and session correction887e178b76d36745ab972fbd2b7975b566290056. It retains the session deadline/Stop projections and private recording/authorization hooks. Historical or standalone branch results do not establish validation of this composed source.

## Ownership and hooks

src/volunteer-sessions.php owns persistence, authorization, scope and capability matching. src/volunteer-queue.php owns queue assignment and shared-service integration. src/volunteer-routes.php and the volunteer views own CSRF-protected forms and private receipts. src/volunteer-orchestrator.php and its template compose the separate public instructions and private delivery; stored grant bindings select session-aware task/repair prose. Session views include a contextual guide link when that separate guide is available.

The authorization-response view exposes $orchestratorSession exactly as returned by vs_create(): id and one-time private fragment link. It includes views/volunteer-orchestrator.php only when that file and vo_handoff() are present. Do not reconstruct a session credential from public data or add duplicate orchestrator instructions to session views.

## Permission and limits

Default Standard: three assignments or sixty minutes, one active task. Quick try is one task or fifteen minutes; Long haul is ten tasks or 180 minutes. Explicit No limit has no overall assignment quota or session deadline and lasts until Stop, current terms revoke permission, or the external tool stops. Custom bounded settings retain 1–20 assignments and 5–240 minutes. Consent captures the current effective legal version, independently chosen human-input declaration, authorized resource use, and optional separate software consent. Missing software consent excludes tool projects. No API keys or AI account credentials are collected. Captured permission covers eligible task selection, research, browser submission, platform validation repair and continuation within the chosen scope/limits. It authorizes no purchases, installation, unrelated work or broader account access.

Scope is all eligible work, one existing category, or one currently public research identity. Configuration is self-reported; actual worker metadata still passes ordinary requester and platform submission checks. Scope, time and assignment limits cannot be expanded through a worker packet.

States: active, limit_reached, stopped, expired, terms_changed. Limit exhaustion blocks additional pickup, while the current assignment can finish before the absolute deadline. Deadline checks deny at exact expiry. Recopy, repair, renewal and explicit reacquisition of the same unreleased assignment do not count as additional assignments. Release ends that assignment and retains its consumed allowance. No-task and failed handoff preparation consume nothing.

## Trusted return binding (for manual/browser integration)

Session-issued browser_returns.scope adds exactly these private fields to the ordinary signed task scope:

- volunteer_session_id: volunteer_sessions.id
- volunteer_assignment_id: volunteer_assignments.id

The assignment row must match return_id, research_id, task_id, revision and the immutable original actor_hash. The grant retains original consent and expires_at exactly equal to the session deadline, including null only for an explicitly unlimited session. A null ordinary return or a deadline inconsistent with its session cannot authorize work. These binding fields stay in private tables, never research packets, archives, public context or public authorization metadata. Ordinary single-assignment grants omit them and retain their one-task behavior.

Hooks implemented in src/volunteer-queue.php:

- vs_before_record($grant): inside the recording transaction, before the research row lock, acquire the original actor then session row lock and check ongoing permission. Do not call for an already-recorded identical retry. Receipt recovery must happen before expiry/stop/allowance checks.
- vs_validate_grant($research,$grant): from ar_validate() only when the stored scope has volunteer_session_id. Validate fixed actor, matching assignment, current scope/configuration/terms, deadline, task eligibility and the exact live reservation generation. Released/expired/conflicting assignments cannot submit. A consumed grant remains a receipt.
- vs_return_recorded($grant,$resultId): after successful ar_record(), in the same transaction. End the assignment and record its actual admission (accepted/quarantined/rejected). Check packet and validation errors do not end it. Receipts/admin decisions are read dynamically by the dashboard.
- vs_reservation_projection($reservation): shared reservation read hook. Return null for a session-bound reservation after Stop, exact session expiry, policy change or assignment end. Otherwise return the public effective expiry as the earlier reservation/session deadline for finite sessions, or the unmodified hourly reservation deadline for unlimited sessions. The stored task lease remains exactly the shared service's sixty minutes. Apply this hook both to wr_live() and the shared actor-other-reservation lookup, and reproject renewal responses. Never write, claim or renew during GET reads.

No session identifier or capability is copied into the public archive. vs_handoff() returns the exact archived task instructions plus private assignment delivery, not an orchestrator loop. A separate persistent storage/private/volunteer-return.key derives only the private per-return credential so recopy retains the exact return link without storing raw capabilities or renewing the lease. Losing/rotating that key disables reconstruction of prior delivery links; existing exchanges and recorded receipts retain their normal checks. Never substitute new signed packet identifiers.

## Lock ordering and queue operations

Session mutations own a root READ COMMITTED transaction: actor mutex -> session row -> selected research row -> task/assignment rows. Separate-worker ar_record() uses READ COMMITTED when it starts the root transaction; nested controller recording inherits vs_locked(). A pre-parent grant read must not pin old eligibility across a lifecycle lock wait. After acquiring the parent, current task/revision/reservation checks must return a controlled conflict and retain the complete native packet instead of reaching a stale-row database update. No nested caller transaction is accepted by vs_locked(); callers invoke its operations as top-level mutations. READ COMMITTED avoids retaining stale candidate eligibility while waiting on the parent lock. The shared wr_* service remains the only reservation mutator.

vs_claim_next(sessionId) returns the existing active assignment for recopy, otherwise scans eligible current Ready tasks with stable pagination. It rechecks scope, worker configuration, software consent, independence, dependencies, moderation and parent visibility under the selected parent lock. Claim, complete handoff archive, private return, assignment row and counter share one transaction. On a last-moment race, abort honestly with no assignment/count; do not invent work. A session or child worker never independently reviews any study to which its original actor has contributed.

vs_assignment_action(sessionId, assignmentId, operation) accepts renew, release, or reacquire. Reacquire preserves the original archive/nonce/context and is permitted only while that same task revision remains eligible and unreserved. A changed revision or another holder is a conflict; entered work and original identifiers must remain visible. Session-bound workers with only a single return capability report an expired lease to their controller for explicit session reacquisition; they do not gain general session permission.

vs_stop(sessionId) sets a terminal stop and releases the holder's current lease through wr_release(). Expiry/policy changes free logical availability through the shared projection without a precisely scheduled cleanup job. Renewing a task never extends session authorization. Stopping the website session cannot guarantee external AI execution stops; the user must also stop that tool.

## HTTP contract

All mutation routes require the normal CSRF token and same-origin session cookie. IDs are random 32-hex identities; identity alone grants no private permission. No request accepts an actor or consent from a worker packet.

| Route | Method / action | Effect |
| --- | --- | --- |
| /volunteer | GET | Public compact setup. Optional scope prefill is configuration only. |
| /volunteer/orchestrator.txt | GET | Reusable reference instructions only; no private supplement, consent or pickup. |
| /volunteer | POST operation=configure | Validate scope, limits and worker capabilities; show unchecked authorization. No session yet. |
| /volunteer | POST operation=authorize | Require authorize, terms, authorized_resources, current policy_version, independent human_level and applicable scientific disclosure. Optional tool_terms is separate. Create the selected finite or explicitly unlimited session and show its private link once. |
| /volunteer/{id}/open | GET | Generic fragment exchange/manual-entry form. Never authorizes from a public prompt. |
| /volunteer/{id}/open | POST session_secret | Exchange the 64-hex fragment capability into a regenerated browser session; redirect to the private dashboard. |
| /volunteer/{id} | GET | Private dashboard, time/allowance, current work and honest outcomes/receipts. No pickup or renewal. |
| /volunteer/{id} | POST operation=claim | Atomically select and reserve one eligible task; prepare the full handoff before committing; return the private handoff page. Existing current work is an unchanged recopy. |
| /volunteer/{id} | POST operation=handoff, assignment_id | Recopy the existing complete private handoff, without renewal or new allowance. |
| /volunteer/{id} | POST operation=renew/release/reacquire, assignment_id | Explicit shared-service action for this bound assignment. Reacquire never changes its signed IDs. |
| /volunteer/{id} | POST operation=stop | Terminal revocation and release. Repeated Stop is safe. |
| /volunteer/{id}/assignments/{assignmentId} | GET | Private packet form; an already-recorded assignment redirects to its original receipt. |
| /volunteer/{id}/assignments/{assignmentId} | POST operation=check/submit, payload | Check or record against stored authorization, scope, reservation, original actor and ordinary server evidence/model rules. Check does not consume or renew. |
| /volunteer/{id}/assignments/{assignmentId} | POST operation=reacquire, payload | Explicitly reacquire an eligible unreserved original revision while preserving the entered packet. |

The assignment worker uses the existing private /research/{slug}/return/{grantId} fragment exchange and its /submit form. It can use only that assignment, not the controller dashboard or the rest of the allowance. A controller transferred through the session link retains the bounded scope, absolute deadline and original actor, even in another anonymous browser. GET views, archived reads and packet checks never create consent, claim or renew.

Check requests accepting application/json return ok/message and, on validation error, repair_prompt. The boolean session_reacquire is computed from live eligibility, revision and availability; it does not authorize a mutation. Failed checks and submission conflicts retain the complete entered packet. An uncertain recorded retry recovers the original receipt before current Stop/expiry checks; changed duplicate packets still fail.

## Persistence, privacy and integration

Migration 018 creates private volunteer_sessions and volunteer_assignments. Migration 019 binds each assignment to its exact shared reservation generation. Assignment counters are monotonic and do not depend on retained child rows; parent purge cannot refund allowance. Existing stored evidence, archives and single-assignment consent remain unchanged. The initial release cannot change session scope/configuration or expand its limits after authorization: start a fresh authorized session instead.

vs_claim_next returns the assignment with a transient prepared_handoff string for the controller's immediate private response. This value must never be exported as public assignment metadata. Both new pickup and current-task recopy prepare it inside the root transaction. Failure rolls back the claim, assignment and count; claim/archive abuse budgets remain consumed as designed.

Private session and per-return capabilities stay in fragments and exchanged server sessions, with hashes in private database rows. The purpose key under storage/private/volunteer-return.key must be persisted and backed up privately with other application keys. No raw session credential is reconstructed or stored. Private session routes and session-issued returns are excluded from analytics. Private links/credentials are rejected from submitted research packets before engine attempt logging. Do not paste them into public context, citations, research files, screenshots or external logs.

Dashboard reads recheck public parent, child admission, task/snapshot availability and current result admission. Accepted, quarantined and rejected receipts are distinct; not-submitted work is explicit. Removed/withdrawn/private evidence yields an unavailable entry with no content/deep links. Prior recorded work stays labeled as recorded previously with current research unavailable; it is never relabeled not submitted or treated as currently accepted evidence. Receipts remain available only through their existing visibility rules after session Stop/expiry.

The shared service projects a session-bound lease as unavailable immediately at session expiry, Stop or changed terms without GET writes or a cleanup timer. A later claimant can replace the old generation. Stop serializes the ownership read under the parent lock and never releases a newer holder's generation. Its physical lease timestamps remain shared-service data; the public effective expiry is capped at the session deadline.

## Verification and limits

- tests/volunteer-sessions.php covers authorization, bounds, legal versions, private exchange, exact deadline, capability/model rules and software consent.
- tests/volunteer-queue.php exercises actual shared leases, queue scope/dependencies, full preparation rollback, unchanged recopy, independent review, fixed browser-return lineage, stop/expiry, quarantine/rejection, private parent visibility and receipt retry.
- tests/volunteer-scope.php exercises actual category, exact-question and unscoped pickup; unrelated/private work and empty-scope fallback are rejected.
- tests/volunteer-worker-race.mjs observes the actual native HTTP worker waiting on a held parent lock, then commits a staged lifecycle revision/lease invalidation. Submit and Check must return409, retain the exact packet, and leave results, consumed-grant state, lease generations and assignment state untouched by the worker.
- tests/volunteer-concurrency.mjs runs independent PHP/PDO processes for same-session pickup, competing sessions, one actor across parents, Stop versus pickup/recording, and expired-holder Stop versus a new holder.
- node tools/qa.mjs volunteers exercises actual consent and controller/worker browsers, two accepted dependent tasks with one authorization, unchanged copy/renew, clipboard denial, conflicts/reacquisition with full packet retention, expiry, Stop, receipt recovery and no-JavaScript submission. Synthetic packets establish application behavior, not real AI execution or scientific accuracy.
- QA_INSPECT_FIRST=1 pauses that browser mode after each three-size screenshot batch. Use an interactive terminal; inspect viewport-only screenshots at 1366x768, 390x844 and 375x667 before continuing. The mode also captures and asserts 200% font reflow. Evidence stays under ignored storage/qa/volunteers.
- Run node tools/dev.mjs setup, node tools/check.mjs and the complete node tools/qa-all.mjs sequentially against a dedicated local database. The shared changes require existing reservations, pickups, returns, handoffs and broader regression modes too.

An anonymous actor is browser/capability lineage, not a verified physical person; the service cannot prove that separate anonymous identities are different humans. Claimed model configuration is self-reported and still checked against each actual packet. Stop blocks website claims/renewals/submission but cannot stop external AI execution; the participant must stop their tool. No session permission authorizes unrelated work, purchases, installation or broader account use. The quick-start guide is a separate integration. The implemented public/private orchestrator instructions and genuine synthetic walkthrough are described in the [orchestrator contract](volunteer-orchestrator.md).

## Approved setup and unlimited mode

The public setup renders the four time presets, real public category pills and a live summary. Connect my AI configures only: it opens unchecked authorization, not an account connection or AI launch. Advanced Options keeps custom limits, all categories, specific public research and actual worker configuration. Native radio controls and server validation work without JavaScript. Presets are authoritative server mappings; inactive numeric values cannot turn a finite preset into unlimited or restrict an unlimited choice. Legacy callers without session_preset retain explicit custom finite limits.

New POST fields session_preset = quick|standard|long|unlimited|custom and cause_choice = all|category:{id}|custom select the controls. Custom scope uses the existing scope_kind/category_id/research_id fields. Actual taxonomy and public research visibility are validated server-side. JavaScript progressively disables inactive numeric fields and updates the summary; it grants no permission.

Migration 020 adds explicit limit_mode (bounded/unlimited), permits null assignment_limit/expires_at only together in unlimited sessions, and widens the consumed counter. Historical sessions default to bounded and retain their limits and consent. Session-bound browser returns can carry that same null deadline; standalone returns still need expiration. Unlimited is no hidden 20-task, 240-minute or 24-hour cap: every mutation still checks original actor, live scope/configuration, consent/legal version, current task revision, hourly lease and Stop. Private handoff JSON represents absent limits as null, never zero or an invented far-future date. Stop releases ownership and blocks continuation; identical recorded receipt recovery remains intact.

Developer regression: tests/volunteer-sessions.php and tests/volunteer-queue.php cover all presets, stale inactive fields, unlimited beyond former bounds, count values above 65535, real nullable-grant returns, expired leases/reacquisition, changed terms, original lineage, Stop and receipt recovery. node tools/qa.mjs volunteer-design adds approved-reference viewport checks, native keyboard/200% reflow, all preset review states, specific/custom settings, separate-browser accepted results and no-JavaScript unlimited authorization/Stop. Its report is catalogued at storage/qa/volunteer-design/browser-report.json. Final independent shared-workflow regression belongs to the closed integration batch.

## Additional restricted web-research profile

POST /volunteer accepts session_profile=general (default) or restricted_web_v1, labelled Restricted web research. The same presets, scope, actual worker settings, unchecked authorization and private session routes remain in use. A query parameter only preselects setup; it grants no permission. Migration021 adds a default-general session_profile column without rewriting historical consent, handoff archives or evidence. New consent snapshots the validated profile/capability definition. Changing profiles requires a new authorized session; packet, POST, declared capability and browser-return fields cannot expand an existing session.

Restricted sessions permit accessible web search/source reading, research reasoning/synthesis, model-derived arithmetic explained directly without tools and ordinary site browser controls. They exclude code/shell execution, calculators, spreadsheets, simulations/scientific software, tool installation/builds/reproduction and desktop/personal-file access. These external tool limits are configuration/instructions, not an authenticated runtime or technical sandbox. Selecting the profile establishes no Codex Cloud browser availability; actual phone/Cloud acceptance remains unverified until genuine supported execution and separately authorized candidate deployment.

vs_profile_compatible runs alongside existing scope/model/consent checks during selection and pickup and again for renewal, reacquisition and recording. It excludes required execution, computational/build flags, research-tool projects, active concrete required software, known required external-tool access (ordinary browser/search-only requirements remain compatible) and reviews needing legacy calculation/tool reproduction. Optional Maybe alone does not exclude narrative work. Hidden software under No external tools required stays inactive. Unstructured requirements still require a full-deliverable preflight: release/skip a hidden incompatible dependency without falsely blocking the scientific project because this worker lacks tools.

The shared browser-return record path checks the server-stored session after current grant validation. Restricted returns reject nonempty execution calculations/checkpoints/reviews and tool builds/reviews. The separate versioned evidence.derivations and evidence.derivation_reviews contract represents non-executed model reasoning; its canonical rd_validate validator remains authoritative. Restricted nonempty derivation fields fail closed with503 if that dependent validator is not loaded, so a partial installation cannot silently discard arithmetic. Required execution/reproduction cannot be satisfied by relabelling a derivation. Existing general sessions retain their capabilities. All parent/child visibility, requester model/effort, independent declaration/lineage, consent, hourly reservation, Stop, finite/unlimited limits and exact retry rules remain mandatory.

Focused regression: tests/volunteer-profiles.php checks compatible/Maybe/incompatible selection and pickup, forged permission expansion through both return routes, changed dependencies, current consent/model/visibility, narrative acceptance, hourly renewal/release, Stop and receipt recovery. Synthetic local fixtures prove application behavior only. Developers run source-check and affected tests; independent integrated application QA follows reviewed integration and batch closure, without a per-component full matrix. UI/guide integration and its viewport evidence are owned by the phone-guide task.
