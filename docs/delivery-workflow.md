# Bounded delivery and QA

One coordinator owns delivery state for related work. Record the candidate commit, base, component owners, exact requirements, next action and evidence locations in an ignored storage/orchestration ledger. Preserve existing branch histories, dirty work and failed raw reports. Do not keep multiple competing scheduling ledgers.

## Ownership and dependencies

Use one integration owner, one independent semantic reviewer and one independent QA owner. Component developers implement only their assigned delta and run focused checks. Final review and QA cover one dependency-inclusive candidate. A component is not accepted just because its consumer has copied it.

Stabilize a prerequisite contract before starting dependent implementation. A consumer may inspect requirements while blocked, then reports the named dependency and ends its turn. The coordinator dispatches a concrete action when the dependency is ready. Do not sleep, poll, repeatedly acknowledge status or invent additional work to keep a chat active.

Contributors report results in their own final answer. The coordinator reads compact completion snapshots and supplies the next handoff. Send messages only within the human's existing authorization. Do not create duplicate tasks, test queues, worktrees or approval gates just to relay status.

## Necessary checks and evidence reuse

- Small changes: source/syntax checks and the affected behavior, including required first-viewport and accessibility coverage when UI changes.
- Shared validation, routing, visibility, authorization, concurrency or workflow changes: focused regressions during development, then one complete repository matrix on the final integrated candidate.
- Independent review: inspect the final source and original acceptance requirements. Reuse unchanged component review, and review the actual integration delta. Run focused checks where they resolve a real uncertainty.
- Independent QA: audit reusable developer evidence and independently execute missing risk and acceptance checks. Repeating every developer test is not a prerequisite for independence. Record who executed and who accepted each result.

Reuse requires an identified source commit or verified relevant file hashes, the actual runtime/environment, raw exit status, fresh report paths, and stated coverage. Never relabel an earlier or developer-run test as a new independent execution. If source, runtime or relevant configuration changes, rerun affected checks and broaden only for a material unresolved concern. A source-changing integration needs new integration coverage; a metadata-only handoff does not.

Use node tools/dev.mjs setup, node tools/check.mjs and the repository tools/qa.mjs modes. The final shared-workflow matrix is node tools/qa-all.mjs. Use tools/lib/qa-modes.mjs as the report-path catalogue. A collector failure remains a failure; preserve it, repair the collector, and run the necessary continuation. Passing unaffected stages can be carried forward with explicit source/environment identity. Do not claim a clean single-invocation pass from combined partial runs.

UI acceptance requires viewport-only images at scroll zero, inspected before interactions, at 1366x768, 390x844 and 375x667; all essential action rectangles must be inside the viewport and unobscured. Exercise the primary action and inspect its real result. Check 200% text reflow separately. Preserve the actual screenshots and report visual limitations honestly.

## Isolated execution

Each concurrent worktree uses its own database/data directory, app port and QA_PORT. QA_APP_URL selects the real local blog HTTP target. QA_CALCULATION_PORT and QA_TOOL_HTTP_PORT configure the calculation pilot and tool HTTP fixture; defaults remain 8089 and 8099. Only loopback addresses are used. Stop only processes owned by the current run. Port isolation does not remove host-wide browser limits: use one heavy browser owner if capacity errors recur, while bounded read-only review can continue.

A slot records the owner, exact command/source, ports and report. Release it immediately after the run; do not hold it while coding, requesting authorization or waiting for another task. Avoid interactive inspection flags during an unattended matrix. Run a bounded inspected UI pass separately when needed, then clear those flags.

## Completion and release

Implementation is done when the acceptance delta is present and focused checks are recorded. Delivery is done when final independent review and QA accept the same candidate and the authorized publication/merge is verified. A chat awaiting a dependency or human authorization ends its turn with the exact next action. Do not schedule self-wakeups or keep running a waiting loop.

Create or update a release smoke package once for the actual final release candidate. Reuse its unchanged checks and include new behavior only where required; do not build a new cumulative package for every acknowledgement or intermediate branch. Deployment is a separate human-authorized operation. Never infer push, production access or deployment permission from successful local tests.
