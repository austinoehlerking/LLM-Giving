# Fork this question
Approved public requests with a research workflow offer **Fork this question** alongside the primary actions. The form starts a new editable question with blank title/question, own attribution/contact and the usual calculation/model choices. Category is suggested from the source. A separate disclosure shows exactly the original question, public current research/progress, findings, sources, methods, limitations and contributors being inherited. Preview and setup assistance carry the same read-only background. Setup import cannot change its binding.

Each child has a new research identity, pending moderation, a separate management credential and a new planning workflow after approval. No tasks, results, verification decisions, contact details or capabilities move from the parent. Inherited records are historical background, not child results or a verification claim. Original question/result/contribution links and CC BY attribution remain attached; third-party material keeps its own rights.

## Persistence and retries
Migration 015 adds immutable research_forks and private research_fork_intents. Provenance pins parent ID, context version, immutable workflow snapshot ID/hash, the explicit background projection/hash and UTC capture time. The projection excludes arbitrary workflow metadata and embedded file bytes. Updates to provenance are rejected by a database trigger. Backups discover both tables and their trigger automatically.

A 256-bit form nonce binds the snapshot and browser session. Only its hash is stored. Creation, uploads, provenance and completion of the intent share one transaction and row lock. Identical retries return the same child; changed content fails with a clear message. A separate persistent 256-bit storage/private/forks.key derives the new child's credential from its nonce and identity, so only a credential hash is stored. Retrying after credential rotation or child purge cannot replace access or create a duplicate. Preserve this private key with the other application secrets; losing it prevents old receipt retries but does not invalidate independently stored current management credentials.

Drafts expire after 24 hours; unused expired drafts are removed on preparation. Preparation has per-IP and shared hourly limits, 16 ancestor and 4 MiB background limits. Oversized/unsupported sources fail explicitly without truncation.

## Visibility and corrections
Every public research helper enforces the live transitive fork boundary before returning a child. Discovery resolves that same live predicate for public fork candidates and excludes unavailable IDs in SQL before totals, sorting and pagination. Category counts, related-question exports and related-fork links use the same predicate; no persistent eligibility cache delays withdrawal or restoration. That covers pages, contexts, snapshots, old exact issued handoffs, packets/results, original attachments, and artifact/tool/calculation downloads. Publication approval rechecks the same boundary. A nonpublic parent, missing/purged record, removed source contribution/result, hidden task, revoked source snapshot or integrity failure blocks public access to dependent forks. Private management/admin remain available, with a source-unavailable notice instead of inherited bodies. This deliberately withholds the entire dependent public request while its provenance is unavailable; it never silently publishes a different background.

Parent/child purge cannot delete the other identity. Parent references deliberately have no cascading FK; missing sources deny reuse. Child purge deletes its own provenance and completed intents. Public related-fork links exclude pending, removed, withdrawn and source-blocked children.

Later public source changes/reopened or replaced records show live warnings while pinned bytes remain unchanged. Task and repair prompts explicitly require checking current sources and prohibit treating inherited work as new or verified work. Exact archived bodies remain immutable and contain the historical-background notice; source visibility/revocation still gates every archived read. Fork chains carry the exact earlier bindings as well as live public links.

## Resources dependency
This implementation is stacked on the independently reviewed resources/supporting-materials PR #11. Its source/material catalogue and visibility contracts supply the pinned projection. No prerequisite PR or main branch was merged by this task.

## Acceptance and QA
- A public question starts an editable related request with exact attributed background.
- Creation/retry/import/preview preserve the source binding and independent review, management, planning and results.
- Parent and descendant links appear only for public eligible records.
- Correction warnings do not rewrite history; withdrawal, removal, purge and snapshot revocation cannot expose stale copied material.
- Keyboard, touch targets, no-JavaScript forms, enlarged text and initial controls at 1366x768, 390x844 and 375x667 are covered by the repo-owned fork browser suite.
- Run node tools/dev.mjs setup, node tools/check.mjs, node tools/qa.mjs forks and node tools/qa-all.mjs. Inspect captured screenshots under storage/qa/forks. These tests use synthetic local data; they do not establish scientific accuracy or authenticate contributor identity.
