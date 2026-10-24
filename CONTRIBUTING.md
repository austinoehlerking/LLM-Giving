# Contributing

LLM.Giving is an open-source project. The public source and development history are at [austinoehlerking/LLM-Giving on GitHub](https://github.com/austinoehlerking/LLM-Giving). For ordinary bugs and feature suggestions, [open an issue](https://github.com/austinoehlerking/LLM-Giving/issues). For a code change, create a focused codex/ branch from current develop and [open a pull request](https://github.com/austinoehlerking/LLM-Giving/pulls) targeting develop and describing the change, acceptance criteria and validation. Reviewed changes merge into develop before final batch QA; main promotion is reserved for a human-triggered release. Use the private reporting process in [SECURITY.md](SECURITY.md) for vulnerabilities.

Register the task and batch with a maintainer before integration, and include the task marker in the PR template. Required GitHub checks bind independent review and QA to the actual candidate. See [the enforced GitHub workflow](docs/github-delivery.md).

Keep changes focused and local. Do not introduce paid AI APIs, public accounts, background AI execution or Node production services. Preserve original research provenance and central publication checks.

Use a dedicated development database and synthetic fixtures. Read [the development guide](docs/development.md). Developers run source hygiene and focused checks for their changes. Independent QA claims the completed batch with node tools/delivery-gate.mjs start-qa --owner QA, then executes the agreed checks. Use node tools/check.mjs for server coverage and node tools/qa-all.mjs for the complete browser set when the batch requires it; inspect screenshots. Follow [the batch workflow](docs/delivery-workflow.md); do not repeat the full matrix for each task. Follow AGENTS.md and the user's deterministic editing rules. Do not run apply_patch.

Never commit .env, storage data, management credentials, email files or real personal information. Keep migrations append-only after release. Review schema changes for same-request foreign keys and safe cascade behavior.

Security reports should remain private; follow SECURITY.md. Contributions to source are provided under AGPL-3.0. Research content submitted to a running site has separate licensing terms.

The repository-owned editing tools are portable and require no personal Codex skill installation. New code should follow `.editorconfig`, use readable blocks and explicit validation, and preserve existing data boundaries. See the development guide for exact replacement and whole-file editing commands.
