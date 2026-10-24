# Delivery workflow

Task branch → independent Code Review → merge to develop → one closed-batch QA pass → human DEPLOY command → promote the approved source to main → deploy and live smoke.

## Ownership and messages

One coordinator integrates work, one reviewer accepts code, and one QA owner accepts the batch. Issue #20 is the public batch; storage/orchestration/batch-state.json is its local mirror and private evidence index.

Message another task only with a concrete executable handoff or a blocker it can resolve. No preparation notices, success broadcasts, acknowledgements, polling loops or self-wakeups. Idle chats are not active development tasks. Record routine progress in the ledger/PR. A worker ends its turn when finished or waiting on a named dependency.

## Development and review

Register assigned work before coding. Branch with codex/ from current develop. Incorporate released main before feature edits; preserve changes and use isolated worktrees where appropriate.

Developers run source checks and affected tests. Do not run the full application matrix for every task. Code Review publishes one verdict for the current PR head. That is the review gate; a coordinator does not publish a second reviewed flag. Resolve blocking findings, review changed code, and merge the exact accepted head into develop.

Tracked application and delivery-tooling changes require code review. Runtime QA helpers, screenshots, transport diagnostics and report formatting are QA-owned work, not new development/review cycles. QA may adapt these within its read-only plan without starting another review chain. Actual application defects return through Dev → Review → Merge. Never hide a failed assertion or claim coverage a workaround does not provide.

## One batch QA pass

The coordinator discovers every currently assigned task before closing membership. Every member must be merged at its reviewed head or explicitly human-deferred. New unrelated work belongs to the next batch.

Close membership, pin develop's full commit/tree and main baseline, record focused evidence, set qa_ready, and freeze develop merges. Give QA one executable handoff with a risk-based plan.

From the clean candidate, run only:

    node tools/delivery-gate.mjs start-qa --owner QA

This performs the live membership/candidate/integration checks and atomically claims QA once. Do not first run check-qa or wait for delivery/batch-ready. Missing, stale or active work still blocks QA.

Choose coverage by risk:
- Isolated behavior: affected server/browser checks.
- Shared routing, authorization, visibility, concurrency or workflow behavior: one integrated application matrix.
- Tooling/docs only: focused tooling regressions and source checks; reuse unchanged application evidence.

Developer evidence satisfies checks when source, environment and coverage still apply. QA independently accepts it rather than rerunning it for a different role's signature. After a fix, rerun failed/affected checks and retain the valid remainder. Report-only changes do not restart QA. Preserve failed raw reports and distinguish partial/composite evidence from a clean invocation.

Use the repo-owned QA route. UI changes require inspected initial screenshots at 1366x768, 390x844 and 375x667, visible/unobscured essential actions, a real primary-action result, and separate 200% text reflow.

A tool path gets at most two failed attempts. Switch to the proven fallback; do not rebuild guard frameworks or probe small variations indefinitely. For SiteGround, use normal installed Chrome to complete the hosting challenge. Keep external HTTP challenge responses separate from hosting-origin redirect proof. QA chooses the smallest honest plan; each read-only transport adaptation does not need another Code Review.

QA writes one local report with executed/reused coverage and findings. Publish one sanitized QA marker with candidate/tree/batch digest and a concise summary. Paired evidence comments and body-hash chains are optional legacy compatibility, not a second gate.

## Manual release

QA never promotes or deploys automatically. A human DEPLOY command is the only human approval step. Record it once; do not ask again or ask the human to approve a second GitHub environment.

Deploy runs check-release once, opens the approved develop-to-main release PR, and dispatches Manual release authorization to record the command's exact PR/candidate/batch binding. That metadata-only workflow has no approval environment or production credentials. It does not merge or deploy.

Merge the exact QA-approved source, verify main's tree, then follow docs/siteground.md for backups, migrations, data/key preservation and deployment. Review and batch QA are already complete; Deploy does not restart them. Governance-only publication does not deploy the site.

Live smoke checks deployment-specific risks: actual source/schema, public navigation/assets, essential controls and remaining live acceptance gaps. Reuse accepted application tests. A local report cannot replace host proof, and a hosting challenge is not an application failure. Retries use the same source unless a real code change is needed.

Record promotion and deployment separately. Never reverse a database migration by restoring source alone. Archive a completed batch, open the next batch, and expire its deploy approval. New features cannot inherit an old deploy command.
