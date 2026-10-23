# Development, batch QA and release

The flow is: task branch -> Dev and Code Review -> merge to develop -> closed-batch QA -> human deploy command -> promote the approved candidate to main -> deploy -> live smoke.

develop is the integration branch. main is the release branch. Both began this workflow at the already reviewed and QA-passed source from PR 18. This transition does not authorize a new deployment.

## One coordinator and one batch

The coordinator owns one canonical, ignored ledger at storage/orchestration/batch-state.json in the primary repository, not a separate copy inside each linked worktree. The gate resolves this location through the Git common directory. Keep previous delivery/recovery ledgers as history; do not use them as competing queues.

Before closing a batch, enumerate currently assigned dev tasks and their dependencies, with acceptance criteria, owner, branch/PR, reviewed commit and focused evidence. Existing completed tasks do not become active just because their chats are loaded. New assigned work joins the open batch or is explicitly queued for the next one. Close membership once its intended scope is recorded. A late unrelated task must not silently extend a closed batch.

A task is terminal only when:
- Its exact reviewed and focused-tested commit is merged into develop, with no unresolved blocking review findings; or
- The human has explicitly deferred it, with the reason and decision recorded.

An idle chat, blocked task, copied component, open PR, or developer's statement of completion is not a substitute for that evidence. Do not silently defer slow work to open the gate.

One integration owner merges; one independent reviewer accepts code; one independent QA owner accepts the integrated batch. Use existing chats. Each participant ends its turn when its concrete work is done or a dependency is outstanding. The coordinator resumes it once with the next executable action. No polling loops, repeated acknowledgements, duplicate task queues or automatic wakeups.

## Dev and Code Review

Start codex/ task branches from current develop, using isolated worktrees where appropriate. Preserve unfinished work before updating a checkout. Do not reset, clean, stash or switch a shared dirty checkout as routine setup.

Developers implement their assigned delta and run focused source/behavior checks. A changed UI still needs the required initial viewport and accessibility checks. A docs/tooling-only change does not justify a full application browser matrix.

Open development PRs against develop. Code Review checks acceptance, correctness and the integration delta. Reuse unchanged component review explicitly. Fixes return to the same developer and are reviewed at the new commit. Merge conflict resolutions are code changes and need review/focused validation before merge.

Use normal merge commits so reviewed task commits remain ancestors of develop. Record the reviewed head and evidence in the ledger. Merge into develop after Code Review; final batch QA is deliberately after integration. Use the human's task/workflow publication authorization, and do not request approval again when already supplied. No development role promotes to main.

## Closing the QA gate

The coordinator records a full candidate commit SHA and tree SHA from develop, the current main baseline, closed membership, task evidence and an empty blocker list. Once every member is terminal, set phase to qa_ready and hand the exact candidate and check plan to QA.

From a clean worktree at that candidate:

```sh
node tools/delivery-gate.mjs check-qa
node tools/delivery-gate.mjs start-qa --owner QA
```

check-qa is a read-only preflight. start-qa validates again and atomically records qa_running; it refuses a second start. Missing evidence, an active task, stale review, a dirty source checkout, or a remote develop mismatch closes the gate. A tool/network failure is a blocker, never an assumed pass. The optional --state argument is for isolated tests only; operational tasks use the canonical ledger.

Freeze ordinary develop merges while QA runs and after acceptance until promotion or explicit replacement of the candidate. Developers can continue next-batch work on their branches. A new commit cannot inherit the old candidate's QA verdict.

QA selects the necessary checks from the batch risk and records the plan:
- Small isolated changes: source/syntax checks and the affected behavior.
- Shared application validation, routing, visibility, authorization, concurrency or research workflow changes: one full integrated matrix with node tools/qa-all.mjs, plus any acceptance coverage it lacks.
- Workflow tooling/docs only: gate regression tests, source checks and independent evidence/contract review. Reuse unchanged application evidence rather than rerunning browsers.

Use node tools/dev.mjs setup when needed, node tools/check.mjs for server coverage and tools/qa.mjs modes for browser coverage. tools/lib/qa-modes.mjs is the report catalogue. The full matrix already contains server checks; do not run the same complete server set again merely to hand off.

Reusable evidence identifies source commit/relevant hashes, actual environment, raw exit status, author and coverage. QA independently accepts it; it is never relabeled as a new execution. Source/config/runtime changes require affected checks. Preserve failed reports and distinguish a resumed run from a clean single invocation.

UI acceptance requires viewport-only screenshots at scroll zero, inspected before interactions, at 1366x768, 390x844 and 375x667. Essential actions must be completely visible and unobscured. Exercise the primary action and inspect its result; separately check 200% text reflow.

## QA findings and acceptance

A failed or interrupted run remains a failure with its evidence retained. The coordinator records qa_failed, opens concrete fix tasks in the same batch, and returns them through Dev -> Review -> Merge into develop. No direct QA source fixes bypass review. The replacement candidate receives a new gate handoff only after all fix tasks are terminal.

QA reruns failed/affected checks, adding broader coverage only for a material risk. It explicitly carries forward unaffected evidence with the applicable hashes/environment. Do not restart every unrelated task or repeat the whole matrix after a report-only change.

QA writes its acceptance report under ignored storage/ and gives the coordinator the exact candidate commit/tree, checks, reused evidence, raw failures, unresolved findings and verdict. The coordinator records qa_passed only after QA reports PASS and blockers are empty. qa contains status pass, owner, candidateSha, candidateTree and evidence entries with a shared-root-relative path and SHA-256 hash. The ledger is a local record of trusted role decisions, not a substitute for independent review or an authentication system.

After any interrupted claim, preserve the failed state and report. Explicitly reconcile it before returning to qa_ready; never auto-delete a lock or self-retry a claim.

## Human-triggered promotion and deployment

QA completion stops work. Only a later human deploy command authorizes release.

The deploy owner:
1. Reads the closed batch and QA report, then runs node tools/delivery-gate.mjs check-release from the clean pinned candidate. The gate checks live develop identity, evidence hashes, task ancestry and that current main can be promoted without introducing untested changes.
2. Opens a release PR from the approved candidate into main, with batch ID and QA evidence. Recheck both branch heads immediately before the head-guarded normal merge. Do not merge an arbitrary newer develop.
3. Verify the resulting main source tree equals the QA-approved tree. A merge conflict, unexpected main change or different tree returns to review/QA; no automatic conflict repair during deployment.
4. Package/deploy that verified main commit using docs/siteground.md, including backups, migrations and persistent private storage/key protection.
5. Run the required live smoke checks and inspect the screenshots. Record the actual deployed identity and outcome.

Create/update a release smoke package once for the final candidate and reuse unchanged checks. Do not rebuild it for acknowledgements. A failed deployment is recorded separately from a successful main promotion; do not falsely mark it deployed or reverse database migrations by restoring source alone. A retry uses the same verified candidate unless a new reviewed batch is required.

An emergency main hotfix requires explicit human authorization and must be brought back into develop before its next candidate. The gate's main ancestry rule prevents releasing a candidate that omits that hotfix.

## Ledger contract

Schema version 1 contains batchId, membershipClosed, phase, baseMain, candidate {sha, tree}, tasks and blockers. Task statuses are merged, deferred or nonterminal development/review states. A merged task records commit, review {status: pass, sha}, and checks {status: pass, sha, evidence: [shared-root-relative paths]}. Deferred tasks record reason and humanDecision. The gate validates this contract rather than inferring state from chat activity.

Only the coordinator updates membership and verdicts, with the gate owning the atomic start-qa transition. Keep old evidence and closed ledgers when starting the next batch. Every handoff names the batch and candidate so old approvals cannot be applied accidentally.
