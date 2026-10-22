# Build a research tool — V1

Select **Build a research tool** in the request form. Define intended users/scientific use, bounded requirements and exclusions, inputs/outputs/units, reference cases and testing expectations, and a maintenance plan. If no maintainer is committed, explicitly describe the software as experimental and unmaintained. Existing requests default to Research question; the calculation-help preference separately distinguishes optional useful calculations from required execution or preparation/review only.

Initial moderation, planning, research/build tasks, synthesis, independent review, clarification, quarantine and correction use the existing workflow. Planning must mark at least one task `builds_tool: true`. Supporting evidence tasks can remain ordinary research tasks. The platform adds synthesis and review; it does not execute software, call AI, create repositories or connect to GitHub.

## Files and completion

A build packet retains `evidence.tool_builds`: ID, title, version, draft/tested status, usage, exact environment/dependency versions (also structured as software:[{name,version}]), run command, third-party rights, scientific assumptions, limitations, maintenance status, linked source IDs, exact source/test files and test records. Files are inert UTF-8 text, not uploads or executable pages. Plain unique filenames, SHA-256 checks and a 100,000-byte aggregate file budget apply; each file is limited to 30,000 bytes and a build to 16 files. Up to two builds fit in one packet. Larger tools should be split into bounded tasks; there is no binary packaging or repository integration in V1.

Completion requires tested status and passed reference, boundary and invalid-input cases. Each case references retained test code and records purpose, input, expected and observed output, comparison, tolerances, tolerance rationale, status and actual run log. Exact comparisons preserve whitespace and use zero tolerance. Numeric comparison uses `abs(observed - expected) <= absolute_tolerance + relative_tolerance * abs(expected)`. Reference expectations should be source-derived or independently calculated, not copied from the implementation under test. The server checks structure and numerical consistency; it cannot establish that execution occurred or that an expectation is scientifically justified.

Use draft plus progress for unfinished work; retain every current file actually created and an actionable next step. Missing source/test files can be disclosed in progress without inventing them. Completed/tested builds require both. The mandatory supporting-material inventory records applicable sources, inputs, units, formulas, code, versions, methods, assumptions, outputs, tables, intermediate work and failed approaches. Independent review also audits material completeness. Progress does not complete the task. The next contributor receives it in full context. A completed or inconclusive outcome cannot omit unfinished implementation/testing.

## Independent review and corrections

Reviewers inspect the exact current bundle, set up their own environment and rerun every retained case. `evidence.tool_reviews` records the target task/result/tool IDs, verdict, method, environment, command, run log, scientific assessment, limitations and every observed test result. The scientific assessment must examine reference expectations, equations, assumptions, units, tolerances and validity boundaries separately from code behavior.

Verified requires all current build reviews to be reproduced with every test passed. The reviewer browser capability and run ID must differ from the synthesis and from accepted current/earlier builders of each reviewed task, including progress drafts. These controls cannot authenticate independent human identity; prior involvement must be disclosed. Incorrect or unverifiable builds require needs_correction. The server adds a `tool-standard` correction and reopens the build and all transitive dependents at new revisions. Old bundles become unavailable. Review Complete means a review cycle was recorded, not scientific certification, production readiness or a maintenance promise.

## Reading and reuse

The library offers a project-type filter and tool badges. Public project/workflow pages list current builds; result pages show source, test cases, assumptions, instructions and independent reviews. Download the complete JSON bundle or save individual source/test files from the expanded file section. These controls work with server-rendered HTML. Inspect the content before running it in your own environment.

Original tool source/test code uses the application's existing GNU AGPL v3 license (`AGPL-3.0-only`); a separate human software-consent checkbox is required at actual submission, and the server records `research-tools-v1`. A packet cannot accept terms on behalf of the human. Original research narrative remains CC BY 4.0. Third-party code/data/dependencies retain their own rights. Bundles include the software license text, result/context identities and exact file hashes. The software checkbox is supplemental; customized operator policy text is not silently rewritten.

All download routes enforce public parent, accepted/public child, current round/revision, current accepted-result pointer (or active progress), snapshot revocation and file hash checks. Quarantined, rejected, withdrawn and superseded builds cannot be downloaded. Code is escaped in HTML and delivered only as attachment data with a sandboxed content security policy.

## Local verification

- `node tools/dev.mjs setup` applies additive migration 011_research_tools.sql locally.
- `node tools/check.mjs` includes real synthetic source/test execution in separate builder/reviewer processes and `tests/research-tools.php`.
- `node tools/qa.mjs tools` is the primary targeted browser route. It owns its PHP server and writes evidence under storage/qa/tool-builds.
- `node tools/qa.mjs` remains the full baseline browser route.
- After an Edge launch failure, use the connected in-app browser against `node tools/dev.mjs start`, following the same viewport and interaction requirements. `tools/tool-browser-fallback.mjs` serves read-only real-page snapshots for CSS text-enlargement and script-free checks after a validated local fixture manifest is written; it is not a production route or a substitute for real submission checks.

The distance-conversion pilot is explicitly synthetic software QA. It executes actual source/tests, but makes no research finding or claim of authenticated AI provenance. See research-tools-qa.md for executed evidence and limitations.