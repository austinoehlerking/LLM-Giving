# LLM.Giving
Read README.md and docs/architecture.md before changing the application.
Use PHP 8.2+, PDO/MariaDB, semantic server-rendered HTML and local assets. No runtime AI calls.
Use tools/write-files.mjs (stdin JSON map, --replace for existing files); it performs safe-edit dry-run/apply.
Never expose private records via public data helpers. All context and file serving must enforce parent visibility and child moderation.
All runtime files stay in storage/ on D: locally; never commit storage, .env, or credentials.

## Development branches and release batches
Follow [docs/delivery-workflow.md](docs/delivery-workflow.md). It is the scheduling and evidence contract for every existing and new task.
Create task branches with the codex/ prefix from current develop. Development PRs target develop, not main.
Developer focused checks and independent Code Review precede Merge into develop. A merge is not a QA pass.
One coordinator owns a fixed batch and the shared storage/orchestration/batch-state.json ledger. Discover active assigned work before closing membership. A task is done only when its reviewed commit is in develop and its focused checks are recorded, or the human explicitly deferred it.
Do not start independent batch QA until every batch member is terminal and the coordinator supplies the pinned candidate. New tasks after closure belong to the next batch.
QA runs node tools/delivery-gate.mjs start-qa --owner QA from a clean checkout of that candidate before starting its approved check plan. The gate refuses incomplete batches, stale candidates and duplicate starts.
Freeze develop merges during QA except reviewed fixes coordinated into a new candidate. QA failures return through Dev, Code Review and Merge; rerun affected checks and retain valid unchanged evidence.
Only a later explicit human deploy command authorizes promotion of the QA-approved develop candidate to main and production deployment. Passing QA never starts promotion or deployment. Use the human's actual publication authorization; do not infer permission from test success or ask again for approval already given.
Before promotion, run node tools/delivery-gate.mjs check-release. Promote the pinned candidate without changing its source tree; stop on main drift, conflicts or missing evidence. Follow docs/siteground.md for deployment safeguards.

## Checks and evidence
Use node tools/dev.mjs setup when local runtime setup is needed. Developers run node tools/source-check.mjs and the affected tests; do not run the complete application matrix for every component or metadata-only handoff.
The repo-owned tools/qa.mjs is the primary browser route. It owns its PHP server, uses 127.0.0.1, writes screenshots/reports under storage/qa, and checks initial viewport controls before interaction.
The independent QA owner chooses the batch plan by risk: focused checks for tooling/docs; node tools/check.mjs and the required tools/qa.mjs modes for affected behavior; one node tools/qa-all.mjs matrix for shared application validation, routing, visibility, authorization, concurrency or workflow changes.
QA accepts explicitly identified reusable evidence and executes remaining gaps. Use tools/lib/qa-modes.mjs as the report-path catalogue. Preserve failed raw reports; do not relabel combined partial runs as one clean invocation.
UI changes require inspected initial viewports at 1366x768, 390x844 and 375x667 before interaction, visible/unobscured essential actions, a real primary-action result, and separate 200% text reflow.

## Ownership and waiting
Use one integration owner, one independent semantic reviewer and one independent QA owner. Preserve branches, worktrees, dirty files and raw evidence.
If no immediately executable work remains, report the named dependency and end the turn. Do not sleep, poll, send acknowledgement loops, create self-wakeups or invent extra smoke/QA work.
Choose isolated databases and application/browser/internal-fixture ports. QA_CALCULATION_PORT and QA_TOOL_HTTP_PORT configure the two HTTP fixtures; browser-heavy jobs remain serialized when host capacity requires it. Release owned processes/slots after checks.

## GitHub enforcement
Follow [docs/github-delivery.md](docs/github-delivery.md). GitHub issue #20 holds the sanitized active batch; the local ledger mirrors membership/candidate and keeps private evidence.
Register every assigned task before development. Development PRs target develop and include the exact llm-delivery-task batch/task marker. Independent Code Review must publish the current-head llm-delivery-review record before delivery/develop can pass.
Do not bypass required checks, unresolved reviews, protected branches or administrator enforcement. Missing checks are blockers; a prose PASS cannot substitute for the machine record.
QA starts once with start-qa, which validates the closed live batch directly; do not run duplicate check-qa or wait for delivery/batch-ready. Publish one exact-source QA summary marker and keep private evidence/hashes local. Reuse valid developer evidence and rerun only failed/affected coverage. QA owns read-only runtime helper/transport adaptations without a new Code Review chain.
Main requires delivery/release for the exact QA-approved develop candidate and an owner-dispatched Manual release authorization run after the human deploy command. There is no second environment approval. The workflow records authorization only; it never merges/deploys or accesses production. Send other tasks only actionable handoffs or resolvable blockers; no preparation/status wakeups, acknowledgements or completion broadcasts.
