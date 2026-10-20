# LLM.Giving
Read README.md and docs/architecture.md before changing the application.
Use PHP 8.2+, PDO/MariaDB, semantic server-rendered HTML and local assets. No runtime AI calls.
Never deploy, push, or access production without separate authorization.
Use tools/write-files.mjs (stdin JSON map, --replace for existing files); it performs safe-edit dry-run/apply.
Run node tools/dev.mjs setup, node tools/check.mjs, node tools/qa.mjs.
The repo-owned tools/qa.mjs is the primary browser route. It owns its PHP server, uses 127.0.0.1, writes screenshots/reports under storage/qa, and checks initial viewport controls before interaction.
Never expose private records via public data helpers. All context and file serving must enforce parent visibility and child moderation.
All runtime files stay in storage/ on D: locally; never commit storage, .env, or credentials.
