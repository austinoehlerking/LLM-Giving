# Local setup and testing

## Local setup (Windows, existing PHP/MariaDB)

Prerequisites: PHP 8.2+ with PDO MySQL, fileinfo and GD; MariaDB; Node 22.16+ only for local tooling. The Windows launcher defaults to the current user's Scoop MariaDB installation; set MARIADB_BIN for another location. See the portable setup below for other platforms.

```powershell
cd D:\Codex\LLM-Giving
node tools/dev.mjs setup
node tools/dev.mjs start
```

The app listens on http://127.0.0.1:8086. MariaDB uses loopback port 3318. All local database files, logs, email, uploads, sessions, browser profiles and screenshots are under storage/. The launcher generates random local database credentials in ignored .env and storage/local-runtime.json. No default administrator or production credentials exist. Keep the start process running while using the app; Ctrl+C stops its PHP web server. In sandboxed tool environments, keep that command in an active terminal session so its child database process survives.

In a second terminal, initialize an administrator with a password supplied on stdin. This command also resets a forgotten admin password:

```powershell
$securePassword = Read-Host 'Administrator password (14+ characters)' -AsSecureString
$plainPassword = [System.Net.NetworkCredential]::new('', $securePassword).Password
$plainPassword | php bin/console.php admin you@example.test
Remove-Variable plainPassword, securePassword
```

Open /admin and sign in. The source does not contain a default password.

Optional, explicitly labeled synthetic examples:

```powershell
php bin/console.php seed
```

## Portable native PHP setup

Create a dedicated MariaDB database and user. Copy .env.example to .env; set APP_URL and DB_HOST, DB_PORT, DB_NAME, DB_USER and DB_PASSWORD. Enable pdo_mysql, fileinfo and gd in PHP. Use utf8mb4 and a 32 MB database packet limit. Run:

```sh
php bin/console.php migrate
php bin/console.php admin admin@example.test < /private/path/password-input
php -d upload_max_filesize=10M -d post_max_size=32M -S 127.0.0.1:8086 -t public public/router.php
```

Do not store the password-input file in the project or public directory. The stdin example is optional; interactive shell piping can avoid a file. Set upload_tmp_dir and session storage to private writable directories. The provided Windows launcher explicitly places them on D:.

## Docker Compose option

Docker is not installed on the development machine used for this build; the native setup is the verified route. The Compose definition is supplied for reproducibility but requires a separate Docker test.

Create .env from .env.example and choose nonempty DB_PASSWORD and DB_ROOT_PASSWORD. Then:

```sh
docker compose up -d --build
docker compose exec app php bin/console.php migrate
docker compose exec -T app php bin/console.php admin you@example.test
```

The last command reads the administrator password from stdin. The app is exposed only on 127.0.0.1:8086. Database and application storage are bind-mounted under storage/docker-*; set their ownership appropriately for the container's www-data user. Node is not required in production.

## Local checks

Keep the native local database running first:

```powershell
node tools/check.mjs
node tools/qa.mjs viewport
node tools/qa.mjs
node tools/qa.mjs library
node tools/qa.mjs blog
node tools/qa.mjs submission
node tools/qa.mjs workflow
node tools/qa.mjs artifacts
node tools/qa.mjs quarantine
node tools/qa.mjs handoffs
node tools/qa.mjs launch
node tools/qa.mjs legal
node tools/qa.mjs citations
node tools/qa.mjs requirements
node tools/qa.mjs setup
node tools/qa.mjs tools
node tools/qa.mjs calculations
```

For the complete set in one command, run `node tools/qa-all.mjs` while the local development server/database is running. It saves timestamped logs and fresh report snapshots under `storage/qa/runs/`. `node tools/source-check.mjs` runs database-independent source hygiene and syntax checks. See [the development guide](development.md) for the module map and tooling contracts.

The browser runner starts its own PHP server on 127.0.0.1:8087, uses installed Microsoft Edge, writes evidence under storage/qa, and stops only its own PHP process. PLAYWRIGHT_MODULE and QA_BROWSER may point to existing installations on another machine. The current machine uses the bundled Playwright library without installing dependencies. If absent elsewhere, the package.json declares the optional development dependency; install it only with permission.

Browser tests create random QA administrator credentials and labeled synthetic requests, and exercise real form interactions. Some suites clean up their fixtures; others retain clearly labeled examples for inspection. Use a dedicated local development database. Failure evidence remains private under storage/qa. The runner and database-backed fixtures refuse production/nonlocal configuration and remote database hosts. It does not deploy, send real emails, or contact SiteGround. A full run includes the local upload and purge lifecycle; do not point the runner at a production database.

See [QA evidence](qa-report.md) for executed results and limitations.

Local quarantine QA: `node tools/qa.mjs quarantine` retains its clearly labeled synthetic topic for inspection and writes screenshots to `storage/qa/quarantine/index.html`. It tests unchanged quarantined plans, rejection without advancement, fresh submission, approval exactly once, retained disclosures, UTC decisions, and first-viewport actions at 1366×768, 390×844 and 375×667, plus 200% text reflow. `node tools/check.mjs` includes outcome, authorization, duplicate-decision, stale-round and evidence-isolation checks in `tests/quarantine.php`. These fixtures test software behavior, not scientific findings or real model provenance. `node tools/qa.mjs handoffs` tests exact archive bytes, clipboard copying, initial prompt edits, removed instructions, added sources, preferred conclusions, uncertainty and unchanged/repair controls. It retains labeled fixtures and writes screenshots to `storage/qa/v1-safeguards/index.html`.


