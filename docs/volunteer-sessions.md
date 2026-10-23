# Volunteer sessions — integration contract

Volunteer sessions authorize a bounded, sequential donation using the shared reservation, handoff and browser-return services. The website coordinates work; it makes no runtime AI calls and starts no background workers. Session permission never replaces task eligibility, model requirements, legal consent, evidence validation or independent review.

## Ownership and hooks

src/volunteer-sessions.php owns persistence, authorization, scope and capability matching. src/volunteer-queue.php owns queue assignment and shared-service integration. src/volunteer-routes.php and the volunteer views own CSRF-protected forms and private receipts. src/volunteer-orchestrator.php and its template compose the separate public instructions and private delivery; stored grant bindings select session-aware task/repair prose. Session views include a contextual guide link when that separate guide is available.

The authorization-response view exposes $orchestratorSession exactly as returned by vs_create(): id and one-time private fragment link. It includes views/volunteer-orchestrator.php only when that file and vo_handoff() are present. Do not reconstruct a session credential from public data or add duplicate orchestrator instructions to session views.

## Permission and limits

Default: three assignments, sixty minutes, one active task. Editable bounds: 1–20 assignments and 5–240 minutes. Consent captures the current effective legal version, independently chosen human-input declaration, authorized resource use, and optional separate software consent. Missing software consent excludes tool projects. No API keys or AI account credentials are collected. Captured permission covers eligible task selection, research, browser submission, platform validation repair and continuation within the chosen scope/limits. It authorizes no purchases, installation, unrelated work or broader account access.

Scope is all eligible work, one existing category, or one currently public research identity. Configuration is self-reported; actual worker metadata still passes ordinary requester and platform submission checks. Scope, time and assignment limits cannot be expanded through a worker packet.

States: active, limit_reached, stopped, expired, terms_changed. Limit exhaustion blocks additional pickup, while the current assignment can finish before the absolute deadline. Deadline checks deny at exact expiry. Recopy, repair, renewal and explicit reacquisition of the same unreleased assignment do not count as additional assignments. Release ends that assignment and retains its consumed allowance. No-task and failed handoff preparation consume nothing.

## Trusted return binding (for manual/browser integration)

Session-issued browser_returns.scope adds exactly these private fields to the ordinary signed task scope:

- volunteer_session_id: volunteer_sessions.id
- volunteer_assignment_id: volunteer_assignments.id

The assignment row must match return_id, research_id, task_id, revision and the immutable original actor_hash. The grant retains the original session consent and expires_at equal to the session deadline. These binding fields stay in private tables, never research packets, archives, public context or public authorization metadata. Ordinary single-assignment grants omit them and retain their one-task behavior.

Hooks implemented in src/volunteer-queue.php:

- vs_before_record($grant): inside the recording transaction, before the research row lock, acquire the original actor then session row lock and check ongoing permission. Do not call for an already-recorded identical retry. Receipt recovery must happen before expiry/stop/allowance checks.
- vs_validate_grant($research,$grant): from ar_validate() only when the stored scope has volunteer_session_id. Validate fixed actor, matching assignment, current scope/configuration/terms, deadline, task eligibility and the exact live reservation generation. Released/expired/conflicting assignments cannot submit. A consumed grant remains a receipt.
- vs_return_recorded($grant,$resultId): after successful ar_record(), in the same transaction. End the assignment and record its actual admission (accepted/quarantined/rejected). Check packet and validation errors do not end it. Receipts/admin decisions are read dynamically by the dashboard.
- vs_reservation_projection($reservation): shared reservation read hook. Return null for a session-bound reservation after Stop, exact session expiry, policy change or assignment end. Otherwise return the public effective expiry as the earlier reservation/session deadline. The stored task lease remains exactly the shared service's sixty minutes. Apply this hook both to wr_live() and the shared actor-other-reservation lookup, and reproject renewal responses. Never write, claim or renew during GET reads.

No session identifier or capability is copied into the public archive. vs_handoff() returns the exact archived task instructions plus private assignment delivery, not an orchestrator loop. A separate persistent storage/private/volunteer-return.key derives only the private per-return credential so recopy retains the exact return link without storing raw capabilities or renewing the lease. Losing/rotating that key disables reconstruction of prior delivery links; existing exchanges and recorded receipts retain their normal checks. Never substitute new signed packet identifiers.

## Lock ordering and queue operations

Session mutations own a root READ COMMITTED transaction: actor mutex -> session row -> selected research row -> task/assignment rows. No nested caller transaction is accepted by vs_locked(); callers invoke its operations as top-level mutations. READ COMMITTED avoids retaining stale candidate eligibility while waiting on the parent lock. The shared wr_* service remains the only reservation mutator.

vs_claim_next(sessionId) returns the existing active assignment for recopy, otherwise scans eligible current Ready tasks with stable pagination. It rechecks scope, worker configuration, software consent, independence, dependencies, moderation and parent visibility under the selected parent lock. Claim, complete handoff archive, private return, assignment row and counter share one transaction. On a last-moment race, abort honestly with no assignment/count; do not invent work. A session or child worker never independently reviews any study to which its original actor has contributed.

vs_assignment_action(sessionId, assignmentId, operation) accepts renew, release, or reacquire. Reacquire preserves the original archive/nonce/context and is permitted only while that same task revision remains eligible and unreserved. A changed revision or another holder is a conflict; entered work and original identifiers must remain visible. Session-bound workers with only a single return capability report an expired lease to their controller for explicit session reacquisition; they do not gain general session permission.

vs_stop(sessionId) sets a terminal stop and releases the holder's current lease through wr_release(). Expiry/policy changes free logical availability through the shared projection without a precisely scheduled cleanup job. Renewing a task never extends session authorization. Stopping the website session cannot guarantee external AI execution stops; the user must also stop that tool.

## Routes and UI

Public entry /volunteer has a compact setup followed by actual authorization. /volunteer/orchestrator.txt is reference-only guidance. The private dashboard is /volunteer/{id}; /volunteer/{id}/open exchanges its fragment through an explicit CSRF-protected POST. Dashboard actions Pick up next task, Copy task handoff, Renew reservation, Release task, Reacquire this assignment and Stop session all use explicit POST operations. Ordinary views never pick up or renew. The dashboard shows remaining assignments/time, its deadline, current reservation and actual recorded outcomes with receipt links.

/volunteer/{id}/assignments/{assignmentId} supplies the controller result form. Each separate worker uses its scoped /research/{slug}/return/{grantId}/submit form instead of receiving general session authority. Check packet records nothing. Native and JavaScript forms preserve entered work and signed identifiers on validation, expiry or conflict; the controller can explicitly reacquire an eligible unreserved revision without replacing its packet. AJAX checks use current server state to reveal that action.

Pickup prepares the complete private handoff inside the assignment transaction and returns prepared_handoff for private delivery. Never serialize that field into public data, archives or scientific metadata. Copy enhancement reports success only after clipboard completion; native manual copying remains available when JavaScript is disabled.

Run node tools/check.mjs and node tools/qa.mjs volunteers. Server concurrency tests cover competing pickup and Stop versus pickup/recording. The browser suite uses deterministic synthetic packets and is not scientific or genuine AI execution evidence. Its receipt counter is session-scoped; the native context uses reduced motion after a documented Edge smooth-scroll stabilization failure. The separate AI-authored synthetic walkthrough and orchestrator checks are described in the [orchestrator contract](volunteer-orchestrator.md). Inspect all three first viewports and enlarged text before accepting visual QA.
