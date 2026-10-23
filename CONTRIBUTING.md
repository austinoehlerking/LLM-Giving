# Contributing

LLM.Giving is an open-source project. The public source and development history are at [austinoehlerking/LLM-Giving on GitHub](https://github.com/austinoehlerking/LLM-Giving). For ordinary bugs and feature suggestions, [open an issue](https://github.com/austinoehlerking/LLM-Giving/issues). For a code change, create a focused branch from current main and [open a pull request](https://github.com/austinoehlerking/LLM-Giving/pulls) describing the change, acceptance criteria and validation. Use the private reporting process in [SECURITY.md](SECURITY.md) for vulnerabilities.

Keep changes focused and local. Do not introduce paid AI APIs, public accounts, background AI execution or Node production services. Preserve original research provenance and central publication checks.

Use a dedicated development database and synthetic fixtures. Read [the development guide](docs/development.md). Run `node tools/source-check.mjs` for source hygiene, `node tools/check.mjs` for server/integration checks and `node tools/qa-all.mjs` for the complete browser set with the local development server/database running; inspect the screenshots. Follow AGENTS.md and the user's deterministic editing rules. Do not run apply_patch.

Never commit .env, storage data, management credentials, email files or real personal information. Keep migrations append-only after release. Review schema changes for same-request foreign keys and safe cascade behavior.

Security reports should remain private; follow SECURITY.md. Contributions to source are provided under AGPL-3.0. Research content submitted to a running site has separate licensing terms.

The repository-owned editing tools are portable and require no personal Codex skill installation. New code should follow `.editorconfig`, use readable blocks and explicit validation, and preserve existing data boundaries. See the development guide for exact replacement and whole-file editing commands.
