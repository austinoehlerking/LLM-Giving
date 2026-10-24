# GitHub delivery enforcement

Reviewed development work goes to develop. Independent application QA runs once for a closed batch. Only a later human release command permits promotion to main; deployment remains a separate manual operation.

## Authoritative state and protected branches

The sanitized public enforcement state is [delivery control issue 20](https://github.com/austinoehlerking/LLM-Giving/issues/20). The coordinator owns its single marked JSON block. The ignored storage/orchestration/batch-state.json record keeps private evidence references and the local execution claim; its active membership/candidate must mirror the public state. Do not maintain a second independently scheduled queue.

Both main and develop require pull requests, resolved conversations, no force pushes/deletions and compliance by administrators. develop additionally requires delivery/develop from GitHub Actions. main additionally requires delivery/release from GitHub Actions. Never use an admin override or manufacture a PASS to move a task forward.

The trusted publisher runs code from default main only. The pull_request workflow is an unprivileged signal: it checks out no code, receives no permissions or secrets, and performs no application testing. A default-branch workflow handles its completion and reads current GitHub metadata. It never executes PR source or consumes executable PR artifacts. Source changes, edited/deleted evidence and state changes cause fresh evaluation.

## Development PRs

Register assigned work in the current open batch before coding. Include every currently assigned development task and its dependencies. Give each member a stable task ID, owner and current head. A task without a PR can have null pr/head, but remains nonterminal and blocks batch readiness.

Start codex/ task branches from current develop and target PRs to develop. The PR body contains exactly one task marker:

```html
<!-- llm-delivery-task {"batchId":"BATCH_ID","taskId":"TASK_ID"} -->
```

The independent Code Review task publishes an exact-head verdict in a PR review or comment:

```html
<!-- llm-delivery-review {"schemaVersion":1,"sha":"FULL_HEAD_SHA","verdict":"pass","taskId":"TASK_ID","reviewerThread":"01a15bda-b64c-7540-9a89-261c5349b451"} -->
```

A newer changes_requested verdict, native blocking review or unresolved review conversation blocks the PR. A pushed commit makes the old review stale. The registered task/head, marker, trusted author and reviewer role must all match. Review cannot be replaced by a developer self-test.

The gate treats role records as trusted reports, not identity authentication: all current agents publish through one GitHub account. The required GitHub checks enforce the process against ordinary task pushes/merges; repository administrators still control repository settings. Future separate identities can strengthen role authentication without changing the batching rule.

## Closed-batch QA

The public control state uses schemaVersion 1 and activeBatch with id, membershipClosed, phase, baseMain, candidate, tasks and blockers. The candidate is the exact develop commit/tree. Tasks carry id, pr, head and status. Deferred tasks require a concrete humanDecision and reason.

Close membership only after enumerating active assigned work. Every member must actually be merged into develop or explicitly human-deferred. GitHub verifies PR target, repository, reviewed head, merge state and ancestry. Merely writing status merged does not satisfy the gate. Every open develop PR also blocks readiness unless its exact PR/head is explicitly registered in queuedNextBatch with a reason. This includes open PRs whose work was deferred.

When these conditions hold, delivery/batch-ready passes on the pinned develop commit. Local node tools/delivery-gate.mjs start-qa --owner QA must validate the live GitHub state/readiness before claiming the single local QA run. It refuses stale local membership, incomplete work, source drift or a duplicate claim.

No application matrix runs on every PR or push. Developers keep focused checks. QA audits reusable unchanged evidence and executes missing/affected coverage; shared runtime contracts receive one complete integrated matrix. Failures go back through Dev, Review and develop integration. Preserve failed reports and invalidate the prior QA candidate.

QA publishes a sanitized evidence summary in a GitHub comment, without private research, capabilities, runtime files or credentials. Its attestation binds the batch digest, candidate commit/tree and exact evidence-comment body hashes:

```html
<!-- llm-delivery-qa {"schemaVersion":1,"batchId":"BATCH_ID","sha":"CANDIDATE_SHA","tree":"TREE_SHA","batchDigest":"DIGEST","verdict":"pass","reviewerThread":"01a15bea-834d-7771-a3a9-cb783ce547e1","evidence":[{"url":"GITHUB_COMMENT_URL","sha256":"EXACT_BODY_SHA256"}]} -->
```

Use the repository policy helpers to compute canonical digests and exact UTF-8 body hashes. Changed membership, heads, candidate, evidence or blocking findings invalidate the approval. Never change or redact an already-hashed comment without replacing its attestation.

## Manual release authorization

QA success stops work. It does not start promotion or deployment.

After a human explicitly asks to release/deploy, the deploy owner opens a release PR from the exact QA-approved develop candidate into main and requests Manual release authorization on main. The workflow uses environment manual-release, which requires austinoehlerking approval, forbids administrator bypass, permits main only, and contains no production secrets.

The approval job records authorization only. It does not merge source, contact production or deploy. The release check verifies the successful workflow_dispatch run, main ref, workflow path, bound PR/candidate/batch digest and environment approval history; a copied authorization comment is insufficient. A completion event re-evaluates the release check after the authorizing run has actually succeeded.

Only the QA-approved same-repository develop head can pass delivery/release. A feature PR aimed directly at main, stale QA report, incomplete batch or missing manual approval remains blocked. Recheck live branch heads, merge using the exact approved head, verify that the resulting main tree matches QA, then follow the manual SiteGround deployment and smoke process. Record promotion and deployment separately.

## Installation and recovery

The initial installation requires reviewed governance-only changes on default main and develop so the trusted checks exist before their contexts become required. Record this finite bootstrap and its source/evidence; there is no permanent bootstrap switch or bypass in the policy. The installation does not deploy the application.

A missing check, API error, invalid state or failed policy is a blocker. Repair its cause, then request one bounded re-evaluation. Do not disable protection, edit a verdict into PASS, repeat unrelated QA or leave polling loops running. Event-driven metadata checks perform no application tests.
