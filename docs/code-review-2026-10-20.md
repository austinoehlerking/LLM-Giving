# Code review and cleanup — 2026-10-20

Repository-wide source hygiene and syntax checks, manual review of critical request/data/workflow boundaries, targeted fixes, and complete local regression testing are complete.

## Resolved findings

### Hosting asset parity

Apache omitted the request-setup script and artifact CSS/JS even though the local router served them. Added the missing assets and a check that compares both allowlists with every checked-in static asset.

### Complete, verified backup writes

The fixed table list omitted rate_limits, including archive-budget state, and some writes were unchecked. Exports discover all application tables, use a consistent snapshot and explicit UTF-8, remove source trigger definers, and reject failed/short writes. Tests check every table.

### Database outage response

The generic error page depended on database-backed layout settings and could fail recursively during an outage. Added an escaped, database-independent error document and buffered normal rendering to prevent partial output on failure. Verified HTTP status, privacy, headers, actual retry and reflow.

### Malformed input boundaries

Empty/malformed CSRF state, invalid UTF-8 and nested upload fields lacked consistent early rejection. Reject malformed CSRF tokens, invalid UTF-8, password arrays and malformed upload shapes with controlled validation errors.

### Cross-platform files

Calculation filenames could use Windows device names or trailing-dot aliases; local pilot utilities required a D: drive on every Windows machine. Reject nonportable names in server validation and extraction; let consumers select their output directory. This audit kept every generated artifact on D:.

### Portable contributor tooling

Editing and browser helpers contained absolute paths to a personal Windows profile. Added a repository-owned deterministic editor, shared browser discovery, environment overrides and current-user fallback paths. No personal skill installation is needed.

### Fail-closed local QA

Test guards were inconsistent, some production skips exited successfully, and unknown browser modes fell through to the lifecycle suite. Centralized local CLI/database guards, reject unsupported modes and add sequential complete-suite execution with fresh per-run reports.

### Code organization and release exclusions

Workflow context, policy and the submission engine were combined in a large module; source tooling conventions were implicit. Split those responsibilities into named modules without rewriting their extracted function bodies. Added the developer map, editor conventions, package scripts, environment-variant exclusions and explicit Docker runtime copies.

## Validation

- All 16 browser modes passed in the final run: 1638 assertions. The preceding complete run also passed; the final run followed the shared-rendering fixes.
- 104 PHP files passed syntax validation; server/HTTP, evidence, workflow, tools, calculations, quarantine, handoffs, requirements and citation regressions passed.
- Source tooling covers JavaScript syntax, JSON, asset parity, common credential markers and release exclusions. Deterministic editing has 10 regressions; input/backup boundaries have 10; the separate outage browser test has 13.
- Required first viewports: 1366×768, 390×844 and 375×667; the general runner also covers 768×1024. Fresh representative screenshots were inspected. Enlarged/reduced-viewport reflow uses a documented simulation, not a browser-toolbar zoom certification.
- Final run evidence is local and ignored: storage/qa/runs/2026-10-20T16-01-39.584Z/summary.json. The readable local report is storage/qa/code-review-2026-10-20.html.

## Scope and remaining launch gates

This review did not publish a repository, initialize Git history, install dependencies, access production or deploy. This checkout has no Git metadata; review the actual staged source list and diff before first publication. Runtime storage and credentials must remain excluded.

Local testing used the installed PHP/MariaDB and Edge environment. Docker, Linux/macOS, the actual hosting PHP/database stack, production mail, malware scanning and production backup restoration still need their documented environment-specific validation. The backup tests establish export coverage and failure handling, not a production restore. Source-marker checks are not a comprehensive secret scanner, and this is not an independent penetration test or a guarantee of zero defects.

See [the development guide](development.md), [architecture](architecture.md) and [launch checklist](siteground.md).
