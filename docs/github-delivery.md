# GitHub delivery enforcement

One required check per destination: delivery/develop enforces independent current-head review; delivery/release enforces the closed QA-approved candidate and owner-dispatched release authorization. PR/conversation protections and administrator enforcement remain enabled. No workflow deploys production.

## Development

Register work in issue #20 and include this PR-body marker:

    <!-- llm-delivery-task {"batchId":"BATCH_ID","taskId":"TASK_ID"} -->

Developers send ready PRs, questions, dependency requests and tooling/permission blockers to the coordinator, who dispatches independent Code Review and routes findings. Only the coordinator consolidates unavoidable questions for the human; developers do not dispatch peers or ask the human directly. This routing adds no review or QA gate.

Code Review publishes its current-head verdict once:

    <!-- llm-delivery-review {"schemaVersion":1,"sha":"FULL_HEAD_SHA","verdict":"pass","taskId":"TASK_ID","reviewerThread":"01a15bda-b64c-7540-9a89-261c5349b451"} -->

The exact review, blocking native reviews and unresolved conversations determine the gate. The task may still say in_progress/in_review; a duplicate coordinator review flag is unnecessary. A pushed head invalidates its previous review. Closing a batch freezes develop in qa_ready, qa_running and qa_passed.

All roles currently use one account. Records are trusted role declarations, not separate identity authentication.

## QA

Close membership only after all assigned tasks are terminal, pin the candidate, and mirror public/private membership. QA's single start-qa invocation checks actual remote integration and local evidence through public REST. It does not wait for a status publisher or another preflight.

After risk-based checks, QA publishes one sanitized record:

    <!-- llm-delivery-qa {"schemaVersion":1,"batchId":"BATCH_ID","sha":"CANDIDATE_SHA","tree":"TREE_SHA","batchDigest":"DIGEST","verdict":"pass","reviewerThread":"01a15bea-834d-7771-a3a9-cb783ce547e1","summary":"Executed/reused coverage; no blocking findings."} -->

The local report retains private evidence and hashes. Existing evidence-array attestations remain verified for historical compatibility; new reports need no paired comment/hash publication. Missing QA, changed membership/source, a blocking verdict or incomplete work still blocks release.

## Manual deployment

Only a human deploy command authorizes promotion/deployment. Deploy records it once and invokes Manual release authorization on main for the exact PR, candidate and digest. There is no additional manual-release environment approval. The owner-only workflow records a bot marker; the release check validates the successful actual dispatch, source and inputs. Copied prose authorization does not pass.

Release PRs must be same-repository develop-to-main at the approved candidate. Recheck identities, merge normally, verify main's tree, then perform the manual host deployment. Neither QA nor a metadata job starts deployment.

## Metadata and recovery

Delivery signal never checks out PR code. The publisher executes trusted main only and performs no application tests. It handles relevant PR/review/batch events; unrelated issue/comment chatter is ignored. New metadata cancels superseded metadata jobs. Runs are bounded to two minutes.

delivery/batch-ready is retired as a required/start dependency. Batch completeness is checked when QA claims work and remains part of the release check.

Fix a real metadata/API problem once and request one bounded refresh. Preserve evidence. Do not disable protection, forge a pass, retest the application, wake every role or build another approval chain to repair a read-only QA helper. Follow docs/delivery-workflow.md for reuse, fallback and message rules.
