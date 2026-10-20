# Development guide

The production application uses PHP and MariaDB/MySQL. Node 22.16+ is used only for local development, editorial compilation, deterministic editing and QA. Start with the setup routes in [README](../README.md). Use a dedicated local database: tests exercise moderation, recovery, deletion and backups, and some suites retain labeled fixtures.

## Repository map

| Location | Responsibility |
| --- | --- |
| `public/` | The only web document root: entry point, local router, fixed static assets |
| `src/` | Validation, database access, public visibility, workflow and rendering helpers |
| `views/` | Escaped server-rendered templates and progressive enhancement hooks |
| `migrations/` | Ordered schema changes; never rename an applied migration |
| `templates/` | Trusted instructions included around untrusted research data |
| `content/` | Default policy text and compiled editorial content |
| `content/blog-drafts/` | Editorial sources and publication catalogue; never served directly |
| `tests/` | Synthetic fixtures, integration tests and browser suites |
| `tools/` | Local development, build and QA entry points |
| `tools/lib/` | Shared deterministic editing and QA suite catalogue |
| `docs/` | Architecture, contracts, developer guidance and recorded limitations |
| `storage/` | Ignored databases, uploads, email, signing key, logs and QA evidence |

## Workflow module boundaries

`src/workflow.php` loads the workflow modules and owns project lifecycle, restart, clarification and audit events. Existing callers continue to load it through `src/bootstrap.php`.

- `workflow-context.php`: public context, snapshots, signing and task prompts.
- `workflow-policy.php`: configured model policy and contributor instructions.
- `workflow-engine.php`: planning validation, submission transactions and state transitions.
- `workflow-routes.php`: HTTP form handling and response formats.
- `workflow-handoffs.php`: exact issued archives, quotas and retention.
- `workflow-packets.php`: influence disclosures, quarantine and admin decisions.
- `workflow-acceptance.php` and `workflow-evidence.php`: acceptance criteria, evidence checks and corrections.
- `research-artifacts.php`, `research-calculations.php`, `research-tools.php`: specialized evidence contracts and guarded downloads.

Preserve parent-row locking during state changes. Public context and downloads must enforce current parent visibility, child admission/moderation and snapshot revocation. Never expose a database row wholesale merely because its parent is public. Contributor declarations are not provider-authenticated provenance.

## Repeatable checks

With the local development server/database running:

```sh
node tools/source-check.mjs
node tools/check.mjs
node tools/qa-all.mjs
```

`source-check` is database-independent. It validates every JavaScript module and JSON file, checks Apache/local asset parity, detects common private-key/token markers and verifies source exclusions. Its file hashes and results are saved in `storage/qa/source-check.json`. This is a lightweight hygiene check, not a comprehensive secret scanner.

`check` covers source hygiene, PHP syntax, input boundaries, deterministic editing, calculation/tool execution and server/HTTP integration. Database-backed fixtures refuse non-CLI execution, nonlocal environments and remote database hosts. Configure `APP_ENV=local`, `test` or `testing` and use a dedicated local database. The Docker service hostname `db` is also supported.

`qa-all` runs the server checks, blog HTTP checks and all 16 browser modes sequentially. Keep `node tools/dev.mjs start` running so the database and the blog HTTP target remain available. Each run has its own timestamped directory under `storage/qa/runs/`, containing exit status, commands, logs and fresh report copies. Screenshots remain in the suite directories. A missing or stale browser report cannot count as a pass. Inspect the screenshots before claiming visual QA.

Single modes are listed in `tools/lib/qa-modes.mjs`: viewport, full, library, blog, submission, workflow, artifacts, quarantine, handoffs, launch, legal, citations, requirements, setup, tools and calculations. An unknown mode fails before starting a server. `full` is the original general lifecycle suite; `qa-all` is the complete set.

Browser discovery uses `QA_BROWSER` when set, otherwise installed Edge on Windows or Playwright's Chromium on other platforms. `PLAYWRIGHT_MODULE` may name an existing package/path. The optional Codex-bundled fallback is resolved from the current user's home directory. Nothing is downloaded automatically. `PHP_BINARY` overrides the PHP executable; `MARIADB_BIN` overrides the Windows database binary directory. Other platforms use the portable PHP or Docker instructions.

## Windows QA execution boundary

In a restricted Codex execution sandbox, the linked-directory regression may fail with EPERM even though it uses a Windows junction, and Edge may exit during launch with a TransactNamedPipe error (0x6D). On this machine, both checks pass unchanged when the project-owned commands run outside that sandbox with user authorization. This is an execution-boundary issue, not a reason to skip the junction assertion, weaken link protections, install another browser, or change Windows security settings.

Keep node tools/dev.mjs start active for the local database, then run node tools/qa-all.mjs in the authorized execution context. The runner includes node tools/check.mjs and all browser modes, retains logs and fresh reports under storage/qa/runs, and keeps profiles/downloads on D:. Inspect captured viewport screenshots before claiming visual QA. Ask for authorization if the session does not already permit leaving the sandbox; never bypass a denied escalation.

## Editing and review

Use the repository-owned editors; they need no personal Codex skill installation:

```sh
# stdin: {"relative/file.txt":"complete content"}
node tools/write-files.mjs
node tools/write-files.mjs --replace

# stdin: [{"file":"relative/file.txt","search":"old","replace":"new","expected":1}]
node tools/edit-files.mjs
```

Both validate the entire batch before writing, print dry-run/apply metadata, reject path traversal and symbolic links, and check the original file again before replacement. `--dry-run` validates without applying. Use one entry per file per batch. Whole-file creation requires an absent target; `--replace` requires an existing target. Do not use `apply_patch` in this repository.

New code should use readable blocks, explicit validation and small functions with a single responsibility. Follow `.editorconfig`; preserve meaningful content bytes and migration history. Avoid unrelated formatting churn in the existing compact validators. After a change, run the focused tests and the complete suite when shared validation, routing or workflow code changes.

## Historical local experiments

The `*-live-run.mjs`, `*-research-run.mjs` and `research-submission.mjs` scripts replay specific local research sessions. They depend on private input/state under ignored `storage/` and are not setup steps or general regression suites. Do not run them against other people's records. The `*-fallback.mjs` scripts provide documented read-only rendered snapshots when primary browser QA is unavailable. Standard contributor entry points are `dev`, `check`, `qa`, `qa-all`, `build-blog` and `unpack-calculation`.

## Preparing a public repository

Publish source and synthetic test fixtures only. Never add `.env` variants, `storage/`, browser profiles, private research inputs, email, database exports or signing keys. `.gitignore` excludes runtime data; it does not remove files already tracked in an existing repository. Review the actual staged file list and staged diff before first publication. Keep the research-content and source-code licenses distinct.

Production deployment uses only `public/` as the document root. The Dockerfile copies explicit runtime directories instead of the entire checkout. Validate the actual hosting runtime and restore procedure using [the launch checklist](siteground.md). Local QA does not authorize deployment or publication.