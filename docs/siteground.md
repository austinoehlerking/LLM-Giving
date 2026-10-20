# SiteGround compatibility and production gates

Production was deployed with owner authorization on October 20, 2026. See [the launch verification record](launch-2026-10-20.md) for tested hosting behavior, backup restoration, and remaining limitations. The checklist below also applies to future deployments.

## Hosting layout

Use PHP 8.2+ and MariaDB/MySQL with PDO MySQL, fileinfo, GD, sessions and JSON enabled. The implementation is verified locally with PHP 8.5 and MariaDB 12.3. The initial deployment also verified PHP 8.2.34 and MySQL 8.4.11-11 on SiteGround. MariaDB is the tested SQL engine; run migrations on the exact production MySQL/MariaDB version before launch.

Keep application source, .env, storage, migrations and backups outside public_html. Publish only the contents of public/ and adjust its bootstrap include path if the hosting layout differs. Never upload the whole repository into a public document root. Confirm Apache/LiteSpeed rewrite behavior and test every isolation route. Disable directory indexes.

Set APP_ENV=production and a canonical HTTPS APP_URL. Configure HTTPS before using production cookies, HSTS and capability links. Configure PHP display_errors=Off, file_uploads=On, upload_max_filesize=10M, post_max_size=32M, max_file_uploads=10, and private temporary/session storage. Ensure DB max_allowed_packet supports the allowed submissions.

Use a dedicated least-privilege application DB account. Use a separate migration-capable account when possible; the local convenience setup grants broad rights only on its private database. Do not expose the database port publicly.

## Deployment sequence

1. Back up the database and private uploaded files, config and sources. Verify restoration in an isolated environment.
2. Validate the PHP version/extensions, schema compatibility, document root, rewrite rules, TLS, permissions and resource limits.
3. Copy application files and local static assets. Exclude .env, local database data, development email, session files, QA reports and browser profiles.
4. Configure production environment values privately and run migrations. Bootstrap an administrator via the CLI.
5. Configure and verify the email sender/transport; test delivery, single-use recovery and failure reporting.
6. Review legal drafts, publish operator identity and private contact channels, choose retention policy and add a source-code offer/link satisfying AGPL obligations.
7. Configure available malware scanning or explicitly retain conservative manual-review restrictions. Never describe unscanned files as safe.
8. Run the full lifecycle, adversarial routes, responsiveness and 200% reflow checks on an isolated production-equivalent staging instance.
9. Optional V1 maintenance: `php bin/console.php handoffs-prune` removes unused handoffs older than 30 days and expired quota counters, retaining submitted evidence. The owner has deferred scheduled cleanup for V1; the admin control remains available. Do not describe 30 days as an automatic deletion guarantee.
10. Separately authorize deployment; this repository does not auto-deploy.

## Outstanding production validation

Docker Compose is supplied but untested here because Docker is unavailable. Recipient-side mail delivery, ClamAV integration, and concurrent load remain unverified. Backup restoration, production PHP 8.2, database migrations, and SiteGround rewrite/TLS behavior were checked during the initial launch. The local test suite is evidence for exercised behavior, not a guarantee that all vulnerabilities are absent.

The web admin can initiate private backups and configure the server-mail/file adapter and sender; it cannot edit secrets or download database backups. Private operator access is required to retrieve the export. SMTP authentication is not implemented in V1; use the host's tested mail transport or add a vetted adapter if required.

## Building a source-only SiteGround bundle

Run `node tools/package-siteground.mjs` to create a timestamped release under ignored `storage/deploy/`. The tool copies an explicit runtime allowlist into private `app/` and public `public_html/`, adjusts the public entry point for that layout, and records file hashes. It excludes local configuration, storage, QA evidence, editorial drafts, development tools, and tests. It does not connect to hosting or deploy.

Extract only into the intended website directory after checking the existing hosting layout and backing up anything already present. Create `app/.env` privately on the server, keep `app/storage/` outside the document root, and run migrations from `app/`. Never replace persistent storage or configuration with a release bundle. Verify PHP extensions, database compatibility, TLS, rewrite behavior, mail delivery, and private-path denial on the actual host.
