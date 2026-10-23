# LLM.Giving
Read README.md and docs/architecture.md before changing the application.
Use PHP 8.2+, PDO/MariaDB, semantic server-rendered HTML and local assets. No runtime AI calls.
Never deploy, push, or access production without separate authorization.
Use tools/write-files.mjs (stdin JSON map, --replace for existing files); it performs safe-edit dry-run/apply.
Run node tools/dev.mjs setup, node tools/check.mjs, node tools/qa.mjs.
The repo-owned tools/qa.mjs is the primary browser route. It owns its PHP server, uses 127.0.0.1, writes screenshots/reports under storage/qa, and checks initial viewport controls before interaction.
Never expose private records via public data helpers. All context and file serving must enforce parent visibility and child moderation.
All runtime files stay in storage/ on D: locally; never commit storage, .env, or credentials.

## Bounded delivery and QA
Use [docs/delivery-workflow.md](docs/delivery-workflow.md) for task ownership, dependency handoff, evidence reuse and completion.
Keep one delivery coordinator and one integration candidate for dependent work. Preserve existing branches, dirty changes and raw QA evidence.
Run required setup, server checks and browser coverage on the final candidate; use focused checks during implementation and do not repeat complete matrices on unchanged components merely to advance a chat. Independent QA must explicitly accept reused evidence and execute remaining gaps.
Use the repository report catalogue in tools/lib/qa-modes.mjs; never guess report filenames. Retain failures and resume only the necessary stages, with fresh reports and source/environment identity recorded.
If no immediately executable work remains, report the named dependency and end the turn. Do not sleep, poll, send acknowledgement loops or create additional smoke/QA scope while waiting. The coordinator resumes a task only with a concrete action.
Choose isolated databases and application/browser/internal-fixture ports. QA_CALCULATION_PORT and QA_TOOL_HTTP_PORT configure the two HTTP fixtures; browser-heavy jobs remain serialized when host resource failures require it.
Publication, merge and deployment retain their existing authorization and review boundaries.
