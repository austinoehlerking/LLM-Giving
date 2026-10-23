# Shared task reservations

## Scope and rollout

`src/task-reservations.php` is the single lease service for manual AI handoff pickup and the volunteer orchestrator. This change owns persistence, lifecycle, HTTP integration seams and result enforcement. Public pickup/renew/release controls belong to the next task. Do not implement another lease table or let an orchestrator submit around `wf_submit`.

Migration **016_task_reservations.sql** is additive. It creates private `reservation_actors` mutex rows and `task_reservations` generations, with same-research task foreign keys and live-task/live-actor indexes. No historical handoff, consent, snapshot, task or result is rewritten. Logical backup discovers both tables automatically. Parent purge cascades lease generations; an actor mutex contains only the existing anonymous actor hash and may outlive a purged project. No account, runtime AI call or scheduled cleanup is introduced.

## Trusted identity and operations

All PHP actor arguments are **server-owned hashes**, obtained from `wf_actor()` or a session-authorized, unconsumed `browser_returns` grant's `actor_hash`. Never accept an actor hash, reservation row or ownership assertion from JSON, query strings or form fields. A reservation ID is an internal reference, not a capability.

- `wr_claim(researchId, taskId, exactRevision, actor)`: atomically pick up one currently eligible task for 3,600 seconds. A repeated claim by the same current holder returns its unchanged expiry. Another holder receives 409. An expired or superseded generation is replaced, without a cleanup job.
- `wr_renew(researchId, taskId, exactRevision, actor)`: require a live owned generation and recheck eligibility; set expiry to renewal server time plus 3,600 seconds. Expired generations cannot renew.
- `wr_release(researchId, taskId, actor)`: only that holder may release its outstanding generation, including when the task has since become unavailable. This does not grant publication access. Another actor receives 403.
- `wr_require(researchId, taskId, exactRevision, actor)`: revalidate current eligibility and ownership immediately before a new submission is inserted. Caller must hold the actor and parent locks.
- `wr_finish(reservation, resultId)`: end the lease in the same transaction as recording any result, including progress, blockers and quarantine. Never call before recording a result.
- `wr_invalidate(researchId, optionalTaskId)`: lifecycle-only cancellation under the existing parent transaction; never acquire an actor lock here.
- `wr_availability(researchId, taskId)`: public projection after current publication and eligibility checks. Returns only `status` (available/reserved), `expires_at_utc` and `task_revision`. Ineligible/hidden tasks fail rather than presenting them as available. Do not export internal reservation or actor rows.

Claim and renewal attempts have separate fixed-hour budgets: **60 per anonymous actor** and **600 per direct client IP** per operation. Counters use a separate short autocommit PDO connection, so a failed or rolled-back caller cannot erase its attempts. Resetting browser identity cannot bypass the IP budget. Release does not charge a claim/renew budget. These supplement deployment HTTP limits; they do not identify a physical person.

## Transactions and lock ordering

**Actor mutex → parent research row → reservation generations.** Use InnoDB transactions and the shared `tx` helper.

1. Claim inserts/locks the actor mutex first. Renew, release, task recording and AI recording lock the existing actor mutex first.
2. Lock the target `research` row with `FOR UPDATE`.
3. Reload current public research, workflow project/task, round/revision, dependencies and reviewer eligibility.
4. Read/change reservation generations and, if submitting, insert the result and apply its outcome in this same transaction.

Parent serialization makes different-actor claims atomic. Actor serialization also spans different research parents, preventing one holder from acquiring simultaneous tasks. At most one unexpired current Ready/public task generation can be held by an actor in this initial sequential release. Release or finish it before taking another.

Do not call claim/renew/release from a transaction that first locked a parent without its actor mutex. The orchestrator should call the shared top-level operation directly, or follow this ordering for a combined operation. Existing browser authorization and recording acquire the original volunteer mutex before their parent transaction.

Lifecycle paths that already hold a parent lock may invalidate its leases **without** acquiring actor locks. They never wait for an actor, so they do not reverse the order. Restart, clarification, corrections, pause/withdrawal, removal and quarantine cancel affected leases. Restoration does not revive cancelled leases. Dependency/result/snapshot and transitive fork-source visibility are also checked on every admission. Each lease pins a private hash of loss-of-availability history IDs across its full source ancestry. A source withdrawal followed by restoration changes this fence permanently, including when a child claim races the uncommitted withdrawal. The old child lease cannot revive; a fresh claim must pass current checks. The hash contains no public capability.

Keep transactions short; no AI work, remote calls, browser execution or waiting for volunteers inside locks. Rate-limit connections do not hold a counter lock while waiting for application locks.

## Time and eligibility

Production time is the database server's Unix timestamp, stored as UTC seconds. A lease is live exactly while `expires_at > server_now`; equality is expired. A new or renewed lease ends exactly 3,600 seconds after the respective operation. Request headers, packet timestamps, environment variables and browser clocks cannot set it.

The CLI-only local/test clock callback enables exact boundary and race tests. It is unreachable from HTTP and disabled outside local/test/testing CLI.

Eligibility requires Approved/non-withdrawn research, currently available project state, no quarantine hold, public Ready task in the current round and exact revision, and completed/current/public dependency results with valid public snapshots. Review pickup rejects the synthesis author and current/earlier tool builders. Recording still independently validates run separation, evidence, all required model/effort/mode policies, legal/software consent and human-input declarations. A reservation alone satisfies none of those permissions.

Copies, archived reads, open pages, context downloads, packet checks and receipt reads never claim or renew. Recopying an already authorized handoff returns the original lease expiry; it does not extend it.

## HTTP integration seams

All changes use normal session cookies and the common router's CSRF-protected POST. The routes do not accept an actor from the request:

- Manual holder: `POST /research/{slug}/tasks/{taskId}/reservation`.
- Authorized AI: `POST /research/{slug}/return/{grantId}/reservation`, after private fragment exchange into that AI browser's session.

Form fields: `csrf`, `operation=claim|renew|release`, `task_revision` as the exact positive revision. Claim and renewal succeed with the allowlisted public availability JSON; release returns `status=released` and null expiry. Common HTTP errors retain their usual 403/404/409/422/429 behavior. The subsequent controls must show these errors inline and handle lost/stale/expired ownership explicitly. GET never changes a lease.

The AI endpoint reloads the private scoped grant, original actor, task revision, current consent and requester requirements. A public grant ID alone is insufficient. Do not expose its secret, actor hash, consent record or lease row in public context or availability. The volunteer retains the same ownership; an authorized AI delegates its use, rather than replacing the volunteer with a second actor. Both can release/renew using the same lease.

Current successful `ar_issue` task authorization claims before issuing its private handoff. Supplementary contributions do not reserve workflow tasks. Public/reference prompt issuance does not claim.

## Outstanding handoffs and compatibility

Historical exact handoff archives and consent bytes are immutable. Existing recorded task retries resolve their original actor/payload/nonce receipt **before** new reservation, readiness and revision checks; changed duplicates still fail. Consumed AI links recover their recorded receipt before checking lease expiry. Receipt recovery never consumes another holder's lease.

An outstanding unrecorded public/manual handoff must explicitly claim its exact current revision through the manual operation before new recording. An outstanding authorized AI handoff uses its existing private return session and the AI operation, preserving the volunteer actor and captured consent. No retroactive reservation or consent is fabricated. If its revision, context visibility, requester requirements, grant expiry or legal authorization is stale, obtain fresh appropriate authorization/context and reassess. A valid legacy signature does not bypass current eligibility or lease ownership.

Expired holders must explicitly reacquire. If another holder won or work completed, they stop; they cannot overwrite results or displace the winner. Invalid/recoverable packet errors and Check packet retain the lease without renewing it. Any successfully recorded packet ends it. Quarantine's existing project hold and independent administrative decision still determine whether more work can become available.

Until the next task supplies the public pickup controls, manual-return integration uses the explicit POST seam above. Do not silently claim on packet check, page load or final submission to hide the missing controls.

## Verification

- `tests/task-reservations.php`: controllable clock; ownership, exact boundaries, renew/release, stale revision/supersession/withdrawal, current dependencies/reviewer eligibility, progress/quarantine, recoverable errors, model/consent separation, original actor handover, immutable scope/consent, receipt recovery and durable rate budgets.
- `tests/reservation-concurrency.mjs`: independent PHP/PDO workers released through a filesystem barrier; competing holders, one actor across two parents, exact-expiry claim versus stale submission, and renewal versus recording.
- `node tools/qa.mjs reservations`: real anonymous browser sessions, CSRF and private-grant checks, shared operations, expired-packet repair, recorded receipt recovery, original handoff archive, required first viewports and enlarged text.
- Existing server fixtures explicitly pick up before recording; check-only fixture helpers release their temporary pickup afterward. New admission tests bypass that helper to test the actual missing/expired reservation rejection.
- `node tools/check.mjs` includes focused reservation and concurrency checks. `node tools/qa-all.mjs` includes the reservation suite and existing browser regressions.

All fixtures, local databases, screenshots and reports stay under ignored D: storage. No production migration, push, merge or deployment is part of this change.
