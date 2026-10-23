# Volunteer sessions — integration contract (in progress)

The authorization foundation is local commit 4155c0759f03584224519df64219baffc7016931. The queue implementation is being added on codex/volunteer-sessions. Do not advertise the feature until shared reservations and manual pickup integration are included and its acceptance checks pass. Current main does not yet include these dependencies.

## Ownership and hooks

Dev 4 owns session persistence/authorization, queue, setup, private dashboard, stop and session validation. Dev 5 owns orchestrator instructions, its private supplement, optional session-mode prose in ar_instructions()/wf_repair_prompt(), and Copy orchestrator handoff. Preserve its src/volunteer-orchestrator.php, templates/volunteer-orchestrator.txt, views/volunteer-orchestrator.php, public/assets/orchestrator.js and associated tests. Dev 1 owns /volunteer/quick-start and the guide text. The session views will provide a small contextual link slot for that guide.

The authorization-response view will expose $orchestratorSession exactly as returned by vs_create(): id and one-time private fragment link. It will include views/volunteer-orchestrator.php only when that file and vo_handoff() are present. Do not reconstruct a session credential from public data or add duplicate orchestrator instructions to session views.

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

## Routes and UI still being finalized

Planned public entry /volunteer; private session /volunteer/{id}; explicit fragment exchange /volunteer/{id}/open. Creation, pickup, renewal, release, reacquisition, worker configuration and Stop are CSRF-protected POST actions. Ordinary views never pick up or renew. The setup will use a compact configuration step followed by real authorization. The private dashboard will link current work, remaining allowance/time, recorded outcomes and receipts. Exact final action names, return form routes, screenshot evidence and full browser results will be documented after dependency integration; these planned routes are not advertised as available yet.
