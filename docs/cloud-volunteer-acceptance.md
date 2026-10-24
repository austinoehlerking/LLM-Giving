# Restricted Cloud volunteer acceptance

This is a prepared test, not a working Codex Cloud submission claim. The current capability report says native computer/browser use is unsupported and no suitable published environment has been tested. A published environment alone does not resolve that gap. See [Cloud setup](cloud-volunteer-setup.md) and the conditional phone guide at /volunteer/quick-start after integration. No live setup or research submission was executed by this development task.

The human selected https://llm.giving for isolated synthetic acceptance; there is no non-production site. That permission does not deploy a candidate, expose a database/tunnel, install tools or authorize unrelated research. The new-feature live test stays PENDING until separately authorized deployment of the reviewed candidate AND genuine native Cloud interactive-browser support in the selected account/environment. Do not substitute Work, desktop, HTTP/API automation, installed Playwright or a resized screen. Development artifacts can merge before that external test.

## Operator tooling

Run these tools privately as an authorized operator, outside the volunteer research. Nothing adds a public route. Keep credentials, manifests, per-run handoffs and private evidence under ignored storage/qa on D:. Never publish a session/return link or copy the production database locally.

    php tools/cloud-volunteer-fixtures.php
    php tools/cloud-volunteer-fixtures.php plan --scope=32_LOWERCASE_HEX

Both are DRY_RUN and access no database. They provide only a research brief and two exclusion controls, no extracted values, answer, packet, source inspection or AI provenance. The proposed primary sources are NASA's [Apollo 11](https://www.nasa.gov/mission/apollo-11/) and [Apollo 12](https://www.nasa.gov/mission/apollo-12/) pages. Source-page availability during preparation is not the Cloud volunteer's inspection evidence. The volunteer must read the actual pages and produce its own cited derivation; do not supply it an answer.

Choose a fresh random 128-bit scope. Use one study with a narrative/Maybe calculation task and computational execution/reproduction controls. Scope the session to that exact study, one assignment; never select All work for this production test. The controls must remain unassigned and unsubmitted. Fixtures explicitly construct research-stage tasks, not an AI-authored accepted plan.

Local operations require APP_ENV=local/test/testing, a loopback DB, a dedicated database named llm_giving_cloud_qa_*, and APP_URL=http://127.0.0.1:PORT. Use the portable repo setup or an isolated repo-derived launcher; suggested DB/app ports are 18458/18456. Never use shared llm_giving or production credentials for local tests.

    php tools/cloud-volunteer-fixtures.php create --scope=SCOPE --destination=local --candidate=FULL_SHA --tree=FULL_TREE --apply > storage/qa/cloud-manifest.json
    php tools/cloud-volunteer-fixtures.php inspect --scope=SCOPE --destination=local --candidate=FULL_SHA --tree=FULL_TREE --manifest=storage/qa/cloud-manifest.json

Create is transactional and scope-locked. Repeating it reuses the same study/tasks and creates no work. Ownership checks compare the exact scope, study identity and task definitions; ambiguous or modified ownership fails closed. A manifest contains public study/task IDs and operator-reported source pins, never management/session credentials. Check the real deployed source separately: providing candidate/tree arguments is not host attestation.

Future live operations use the SAME commands but require BOTH --destination=https://llm.giving and --allow-live-synthetic, an explicit scope/candidate/tree, and an actual production APP_ENV/APP_URL match. Run only on the authorized host/private operator environment using its existing private DB connection. Do not expose or copy it. These flags are deliberate operator intent, not new deployment authorization. Do not run live commands while the prerequisites above remain unmet.

## Verbatim independent walkthrough

Record the exact application commit/tree, actual host/source proof, Cloud setup version, environment ID/revision and actual worker model/reasoning/tool availability. Record phone OS/app versions, real handset actions, UTC times, Cloud task identity and tester participation. Unknown required settings are incompatible; do not guess or relabel. Instructions/configuration are not technical sandbox guarantees.

1. Stop if candidate deployment or native Cloud browser support is unverified. Record the precise gap; do not authorize/copy a live session yet. After those prerequisites are independently established, create only the manifested study and confirm zero results.
2. On a real phone follow the conditional guide: choose Restricted web research, select this exact study and one assignment, verify the actual worker settings, authorize honestly, and copy the COMPLETE private orchestrator handoff. Save it privately. Select the genuinely prepared Cloud environment and start the Cloud task. If the tester cannot operate the handset, record the human-assisted launch; a desktop resize is insufficient.
3. Let the Cloud agent pick up the compatible task using normal browser controls. Confirm the full unchanged handoff, reservation and original volunteer identity. Confirm execution/reproduction controls are excluded. If a hidden incompatible deliverable appears, release/skip honestly; do not scientifically block the study merely because the worker lacks tools.
4. Have the Cloud agent inspect the primary sources and author the narrative plus evidence.derivations record itself, including source locations, input units, formula, worked steps, outputs, assumptions and limitations. Keep all applicable supporting materials, including the full evidence.sources registry and methodology refs. Do not generate a packet or compute the answer with fixture code, calculators, spreadsheets or another worker. Record the actual tool-use evidence; a prompt restriction alone proves no sandbox.
5. While genuinely working, explicitly Renew reservation and inspect the returned expiry. Recopy does not renew. Keep the 60-minute reservation distinct from the session deadline; Stop/expiry remains authoritative.
6. Record an independent before_check DB observation. Exercise a formatting-only omission such as the required reason summary. Let the AI read the actual browser error and repair it in its own work context. Preserve conclusions, handoff_code, context_id, task_revision and submission_key. Record fault injection and human/AI disclosures truthfully; never change them to force acceptance. The actual error may differ from this example.
7. Click Check packet through the actual browser form. Record after_check independently and verify no result exists. A passing preview is not submission. No hidden API result delivery is permitted.
8. Submit the complete JSON through the browser, inspect the recorded receipt and retain its public result ID. Capture after_submit independently. Require exactly one current accepted completed result with submission_method=ai_browser and the original stored restricted-session/assignment binding. Quarantine proves recording, not acceptance; report it and do not bypass moderation.
9. Reopen the same return/receipt. Recover the existing receipt before any uncertain retry. If a retry is genuinely needed, preserve the identical packet/key. Capture after_recovery independently and verify the same sole result, with no duplicate. Reopen the SAME Cloud chat on the actual phone and review the result link there.
10. Stop the session using the website and stop external work. Capture after_stop independently. The same current result must remain available under normal parent, moderation, current-result and snapshot rules; Stop does not grant visibility to withdrawn/revoked evidence.

## Independent stored-result observations

A separate authorized operator uses the read-only snapshot action against the actual instance DB, not a volunteer packet or client-reported receipt. Capture each phase with the same source/scope/destination options as above:

    php tools/cloud-volunteer-fixtures.php snapshot --scope=SCOPE --destination=local --candidate=FULL_SHA --tree=FULL_TREE --manifest=storage/qa/cloud-manifest.json --phase=before_check > storage/qa/before_check.json

Repeat for after_check, after_submit, after_recovery, after_stop at the specified steps. Each query runs in its own read-only DB transaction. It projects only scoped public IDs/outcomes and binding booleans, omitting private hashes, credentials, payloads and research content. Canonical public resource helpers enforce parent/moderation/revision/snapshot visibility. It checks stored derivation structure and cited references; it never evaluates the formula or validates scientific truth.

Create a private JSON object mapping those five phase names, in that order, to the five local evidence-file paths. Then:

    php tools/cloud-volunteer-fixtures.php verify --scope=SCOPE --destination=local --manifest=storage/qa/cloud-manifest.json --snapshots=storage/qa/snapshot-paths.json --result=RESULT_ID

For production verification use the exact live destination. Offline comparison reads no DB. Exit 0 / stored_result_verification=PASS means only that the independent observations satisfy the stored-state contract. real_phone_cloud_acceptance ALWAYS remains NOT_ESTABLISHED_BY_THIS_TOOL. Source pins are operator-reported, not authenticated. QA must independently inspect the real phone/Cloud, source/tool, form/repair and deployed-source evidence before issuing overall acceptance. Synthetic unit observations cannot provide that evidence.

After evidence is retained and active assignments are stopped/released, remove only this manifested study:

    php tools/cloud-volunteer-fixtures.php cleanup --scope=SCOPE --destination=local --candidate=FULL_SHA --tree=FULL_TREE --manifest=storage/qa/cloud-manifest.json --confirm-scope=SCOPE --apply

Omitting --apply is a database-free dry run. Repeated scoped cleanup reports already absent. Cleanup refuses an ownership mismatch or active assignment; it never scans/deletes by a broad QA prefix. Production cleanup additionally requires the exact live flags above. Retain the evidence first; cleanup does not assert a successful acceptance run.

## Evidence report template

Copy this table into a private dated report under storage/qa. Initial verdict: PENDING. Name executed and explicitly reused evidence separately; preserve raw failures.

| Item | Actual evidence / status |
| --- | --- |
| Candidate/tree and independently verified deployed source | PENDING |
| Cloud setup/environment revision; actual browser/search capability | PENDING |
| Real phone OS/app, Cloud task identity, launch/reopen UTC and human action | PENDING |
| Actual worker model/effort/mode and any unknown required setting | PENDING |
| Restricted stored profile; compatible pickup; controls untouched | PENDING |
| Private full handoff and original volunteer identity transfer (redacted) | PENDING |
| Source inspection/location and AI-authored derivation | PENDING |
| No research execution/calculator/spreadsheet/software/install; evidence limits | PENDING |
| Explicit reservation renewal and independent expiry/session limits | PENDING |
| Real validation omission/error/AI repair; conclusions/IDs preserved | PENDING |
| before_check / after_check: no recorded result | PENDING |
| after_submit: one accepted completed AI browser result and receipt | PENDING |
| after_recovery: same sole result; real phone same-chat reopen | PENDING |
| after_stop: session stopped, eligible result preserved | PENDING |
| Redacted screenshots; actual approvals; limitations and stopping reason | PENDING |

Local developer checks are separate: source hygiene, pure guard/verifier contracts, local scoped setup/read-only observation/cleanup, and the other owners' focused suites after integration. The phone-guide browser mode is repository-owned UI QA, not proof of phone-launched Cloud execution. Required UI screenshots remain 1366x768, 390x844, 375x667 before interaction, plus 200% text reflow; Dev4 owns that module. No per-component complete application matrix is required.
