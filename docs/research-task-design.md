# Implemented autonomous research workflow

Local implementation completed 19 October 2026. This replaces the earlier proposal.

## Lifecycle

Initial topic publication is the sole routine administrator gate. Approval creates one planning task idempotently. New and migrated awaiting topics use Needs Research Plan.

A completed planning submission creates 1–12 bounded research tasks plus platform-defined synthesis and independent-review tasks. Tasks have objectives, deliverables, acceptance criteria and acyclic, same-project dependencies. Tasks are Ready, Waiting, Completed or Superseded.

Completed and inconclusive research results satisfy dependencies. Progress results are retained without claiming completion. Synthesis waits for all research tasks. Independent verification waits for synthesis and rejects the synthesis contributor's browser capability or run ID. Correction outcomes reopen synthesis at a new revision while retaining the old outputs. Verified outcomes set Review Complete, which means the submitted review cycle finished, not that scientific truth was established. Anyone may request an additional independent review with a concise reason after completion.

Needs clarification and blocked outcomes stop advancement across the project. The result includes the obstacle, methodology, evidence/constraints and a concrete next action. The requester uses their existing private management capability to respond; an admin may also help. Responses are published as context and start a new planning round. Earlier tasks/results remain available unless explicitly removed. If a private requester email exists, the existing mail adapter sends a response-needed notice; local delivery remains the private file adapter.

No AI inference or subscription integration runs on the server. Contributors copy prompts into their own AI. Plans and results are structurally validated, not scientifically certified by the site.

## Planning model policy

Default exact IDs: gpt-6-astra and gpt-6.1-sol at max, or ultra via Codex; claude-opus-5-5 at max. The admin UI at /admin/workflow-policy maintains the latest eligible release IDs and explicit tool mappings. Policies are not automatically fetched from vendors. Every context snapshot captures the policy used for that run, so subsequent model releases do not retroactively invalidate an in-flight run.

Only Astra, Sol and Opus family IDs can be configured. Metadata requires provider, model, effort, tool, run ID and ISO UTC execution timestamp. Optional run evidence is public and must exclude secrets. All provenance remains self-reported, including screenshots or logs. Neither text output nor a contributor-entered model name proves provider execution. Max subscription tiers are not reasoning settings.

## Audit and context

audit_events records UTC timestamps, actor type/name, affected entity and ID, action, prior/result states, concise reason, next action and structured provenance. Public workflow entries link to full results, tasks and input packages. Private HTTP operation receipts record successful and rejected POSTs without request bodies, CSRF tokens or management credentials. Invalid workflow payloads are retained privately in workflow_attempts with a reason and no advancement.

Database triggers reject direct UPDATE and DELETE on ledger entries. Corrections append events. Explicit authorized research purge remains an exception: the parent cascade removes its local children and a separate private site event records the purge. This is application/database immutability, not a cryptographically signed external audit service.

workflow_snapshots stores the full JSON context, version, planning round, UTC creation time and SHA-256 hash. Full packages contain the brief, published legacy data, workflow tasks, entire public result bodies, submitted plans, clarifications, history and public ledger. They do not silently summarize or truncate the input record. Prompt generation links the snapshot ID and hash; every submitted result references its exact input package.

Public package access checks current parent publication and withdrawal state. Removing content revokes earlier public snapshots. Admins retain the exact historical packages privately. Removing a planning result also hides tasks and outputs derived from that plan. Current public context excludes removed results and their result-ledger entries.

## Concurrency and safe retries

Each mutation locks the research parent inside a transaction. Task revision, round, readiness and snapshot membership are validated server-side. Submission keys are unique per project; identical retries return the original result, while changed payloads or different actors cannot overwrite it. Unrelated task activity does not invalidate a still-ready task revision.

A random session capability prevents accidental review of one's own synthesis within that browser session, and run IDs must differ. This does not establish independent human identity; another browser/session can be a different or the same person. The UI and packages disclose that limitation.

Only authorized request managers and admins can resolve blockers. Public content is escaped as data, including JSON previews and run evidence. Existing CSRF, rate limits, parent/child visibility and private-file boundaries remain in force.

## Screens and operations

- /research/{slug}/work: ready tasks, dependency states, latest updates and additional-review request.
- /research/{slug}/tasks/{id}: objective, acceptance criteria, prompt copy, JSON template, preview, result submission and prior results.
- /research/{slug}/results/{id}: full result, submitted plan, concise reason, next action, model/run provenance and exact context link.
- /research/{slug}/ledger: chronological catch-up in UTC, newest first.
- /research/{slug}/package.json and /packages/{id}: current and historical full packages.
- /manage/{id}: capability-authorized clarification/blocker response.
- /admin/research/{id}: assistance, exceptional restart/removal and private audit link.
- /admin/research/{id}/ledger: private operational events and rejected attempts.
- /admin/audit: paginated private site-wide audit.
- /admin/workflow-policy: maintained planning model allowlist.

Legacy supplementary contributions publish automatically when their provenance and safety checks pass. They do not bypass or complete a planned task. Exceptional incomplete/suspicious legacy material and attachments retain their existing controls. Routine workflow JSON submissions need no second administrator approval.

## Storage and compatibility

Migrations 004/005 add workflow projects, tasks, submissions, snapshots, clarifications, private attempts and the audit ledger with immutability triggers. Backups include these tables and the triggers. The native setup runs workflow-upgrade after migrations; existing pending topics are not automatically approved. Paused, solved, archived and withdrawn topics are not automatically restarted.

Migrations were applied locally after a private database backup. No dependencies were installed and no production access, push or deployment occurred.

## Verification

The repository-owned tools/qa.mjs workflow mode exercises initial approval through planning validation, requester clarification, admin blocker help, autonomous dependencies, progress/inconclusive outcomes, synthesis, correction and independent verification. It saves screenshots under storage/qa/workflow and removes its disposable [QA] topic. Required first-viewport actions are checked at 1366x768, 390x844 and 375x667, plus enlarged-text reflow.

tests/workflow.php covers model/effort restrictions, invalid/cyclic plans, cross-project context isolation, unauthorized responses, terms, idempotent retries/collisions, stale task revisions, dependency gating, review independence checks, correction loops, automatic re-review, public/private exports, immutable ledger enforcement, historical hashes, source removal, blocker handling, backups and parent visibility. The main check runner includes it.

Browser testing uses installed Edge on the local PHP server. No real AI run or scientific result was generated by the tests. Cross-browser behavior and scientifically evaluating model-generated plans remain outside these local fixture tests.
