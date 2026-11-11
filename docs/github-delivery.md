# GitHub delivery enforcement

GitHub Actions is disabled for this repository. GitHub enforces PRs, conversation resolution and administrator protections for main and develop. The coordinator verifies review and QA records against live GitHub metadata and private local evidence before the protected merge. Deploy verifies the direct human command before manual production deployment.

## Development

Register work in issue #20 and include this PR-body marker:

    <!-- llm-delivery-task {"batchId":"BATCH_ID","taskId":"TASK_ID"} -->

Developers send ready PRs, questions, dependency requests and tooling/permission blockers to the coordinator, who dispatches independent Code Review and routes findings. Only the coordinator consolidates unavoidable questions for the human; developers do not dispatch peers or ask the human directly. This routing adds no review or QA gate.

Code Review publishes its current-head verdict once:

    <!-- llm-delivery-review {"schemaVersion":1,"sha":"FULL_HEAD_SHA","verdict":"pass","taskId":"TASK_ID","reviewerThread":"01a15bda-b64c-7540-9a89-261c5349b451"} -->

Before merging, the coordinator verifies the review marker, any blocking native reviews and unresolved conversations against the live PR head. Use the expected head SHA when merging. The task may still say in_progress or in_review. A pushed head invalidates its previous review. Closing a batch freezes develop in qa_ready, qa_running and qa_passed.

All roles currently use one account. Records are trusted role declarations, not separate identity authentication.

## QA

Close membership only after all assigned tasks are terminal, pin the candidate, and mirror public/private membership. QA's single start-qa invocation checks actual remote integration and local evidence through public REST. It does not wait for a status publisher or another preflight.

After risk-based checks, QA publishes one sanitized record:

    <!-- llm-delivery-qa {"schemaVersion":1,"batchId":"BATCH_ID","sha":"CANDIDATE_SHA","tree":"TREE_SHA","batchDigest":"DIGEST","verdict":"pass","reviewerThread":"01a15bea-834d-7771-a3a9-cb783ce547e1","summary":"Executed/reused coverage; no blocking findings."} -->

The local report retains private evidence and hashes. Existing evidence-array attestations remain verified for historical compatibility; new reports need no paired comment/hash publication. Missing QA, changed membership/source, a blocking verdict or incomplete work still blocks release.

## Manual deployment

Only a direct human deploy command authorizes promotion and deployment. Deploy verifies the original user message and records it once with the release PR, candidate, tree and batch digest. Run the local check-release once and use its evidence for the exact candidate.

Release PRs must be same-repository develop-to-main at the approved candidate. Recheck identities, merge normally, verify main's tree, then perform the manual host deployment. QA publishes evidence for the manual release decision.

## Metadata and recovery

The local release and QA checks validate live GitHub metadata. The coordinator records the reviewed commit and merge, and QA publishes one sanitized verdict. Preserve evidence from prior releases as historical records.

Batch completeness is checked when QA claims work and again against the approved candidate during release.

Fix a real metadata/API problem once and request one bounded refresh. Preserve evidence. Do not disable protection, forge a pass, retest the application, wake every role or build another approval chain to repair a read-only QA helper. Follow docs/delivery-workflow.md for reuse, fallback and message rules.
