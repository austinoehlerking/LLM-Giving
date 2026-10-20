# Local V1 implementation and QA report

Latest source cleanup and complete regression: [October 20 code review](code-review-2026-10-20.md). The original implementation notes below are historical; the newer review records current local verification and remaining hosting gates.

Completed locally on October 18, 2026 (America/Chicago). Machine timestamps in research records use UTC.

The approved research library redesign and its latest verification are documented in [library-implementation.md](library-implementation.md).

## Outcome

The core definition-of-done workflow is implemented and verified against a real local MariaDB database and browser: anonymous request submission → private management link → administrative approval → public discovery → deterministic research handoff → structured contribution → approval → targeted independent review → findings ledger → requester resolution → withdrawal → administrative restoration → permanent purge.

No production deployment, SiteGround connection, domain modification, remote push, or paid AI integration occurred.

## What was built

- PHP application with PDO/MariaDB, migrations, native startup tooling, Docker definition, private storage and development email sink.
- Responsive public homepage, search/filter/sort/pagination, research records, solved filtering, contribution/review forms, feedback, context export and draft information/legal pages.
- Request-scoped management credentials with fragment delivery, CSRF-protected POST exchange, expiration, rotation, private email recovery and single-use recovery tokens.
- Administrative moderation queues, full record inspection, status/rationale controls, material revisions, contribution supersession, related/duplicate associations, findings and evidence links, file approval/removal, categories/tags, public-page editing, site settings, notices, private backup initiation and maintenance.
- Separate moderation, progress and factual-verification dimensions. Original content and prior published versions are retained for provenance.
- Versioned deterministic research/review prompts with complete approved context, explicit safety boundaries and structured submission schema. No inference runs on the server.
- AGPL-3.0 source license, CC0 contribution distinction, setup/architecture/schema/security/contributing/deployment documentation.

## Executed checks

| Check | Result |
| --- | --- |
| PHP syntax | 35 PHP files passed; final receipt/router edits separately rechecked |
| Service/database/security integration | 98 assertions passed |
| Browser lifecycle and security | 105 assertions passed in installed Microsoft Edge |
| Focused final viewport run | 37 assertions passed |
| Screenshots | 30 workflow captures; 19 initial-layout captures in the focused viewport run, sharing filenames where applicable |
| Session-secret inspection | 45 local session files inspected; zero raw management links found |
| Fixture cleanup | Zero [QA] requests and zero temporary QA administrators remain in the active database; three explicitly labeled [Demo] questions remain |
| Private SQL backup | Created through the admin UI; export exists outside the public root and public download is denied |

Commands: `node tools/check.mjs`, `node tools/qa.mjs`, `node tools/qa.mjs viewport`.

Machine-readable evidence: storage/qa/integration.json, storage/qa/browser-report.json, storage/qa/viewport-report.json. Screenshots remain in storage/qa as private, ignored evidence.

The browser tests used the repository-owned route. Bundled Playwright Chromium was absent; installed Edge then exited inside the Windows sandbox. After explicit user approval, the same local runner succeeded outside the sandbox. No dependencies were installed. Browser profiles, downloads and reports were directed to D:.

## Security behavior verified

Tests cover pending/rejected/removed publication isolation; parent withdrawal across HTML, JSON, prompts and file routes; independent child attachment approval; review and feedback target checks; database-enforced composite foreign keys; CSRF denial; anonymous moderation denial; invalid capabilities; expiration and rotation; email recovery and replay denial; administrator password-reset session revocation; HttpOnly/SameSite cookies; malicious markup remaining inert; unsafe URL rejection; executable upload denial; request-scope isolation; incomplete provenance and explicit exceptions; deterministic prompt safety content; safe private-field allowlists; nested transaction rollback; rate limiting; and deletion of every related table plus files and development mail.

The final credential-delivery change renders receipts directly into POST responses. Raw management links are not saved in server sessions or redirect URLs. Optional delivery email is necessarily a private message containing the link; the local sink is deliberately outside the public root.

No critical failure was observed in these exercised cases. This is not an independent penetration-test certification or a claim that all possible vulnerabilities are absent.

## Visual and usability verification

Initial viewport-only screenshots were captured and action rectangles checked before interaction at:

- 1366 × 768 desktop
- 390 × 844 mobile
- 375 × 667 short mobile
- 768 × 1024 tablet

All expected homepage actions, research actions, initial submission action and discovery search action were fully inside the tested viewports, at least 44 CSS px high, and unobscured. The actual primary workflow was then exercised. A maximum-length 180-character title and extensive context also passed desktop and mobile action checks.

Inspected screenshots include home, discovery, submission, research, populated/solved research, long-content research, administration and keyboard focus. Compared typography, palette/contrast, spacing, component consistency, action placement, wrapping and horizontal overflow. Fixed mobile mission-text spacing and moved research actions before excerpts on narrow screens. Search filters use an expandable secondary area so the search control remains immediately usable.

Keyboard skip-link focus and activation passed. Reflow was tested at a 683 × 384 CSS viewport (the layout width of a 1366 × 768 browser at 200% zoom), with additional body-text enlargement and no horizontal overflow. This is an automated reflow simulation, not manual browser-toolbar zoom or a full screen-reader/WCAG audit.

Design uses code-native HTML/CSS and a small SVG mark. No image-generation concept was created because the available generator's default C: storage conflicted with the user's D:-only rule. There is therefore no claim of image-concept fidelity approval. Native design tokens and actual rendered views are the implementation reference.

## Startup and administrator access

From D:\Codex\LLM-Giving:

```powershell
node tools/dev.mjs setup
node tools/dev.mjs start
```

Open http://127.0.0.1:8086. The setup has already created this machine's private local database and applied migrations.

Initialize your own administrator using `php bin/console.php admin your-email`, supplying a password of at least 14 characters on stdin. The README contains a PowerShell prompt that avoids storing the password in a file. Sign in at /admin. No default or retained QA administrator exists.

## Remaining production prerequisites and practical limits

- Docker Compose and PHP 8.2 were not run here. The tested runtime is PHP 8.5.7 with MariaDB 12.3.2; validate the exact SiteGround PHP/database stack separately.
- Outbound mail is not live-tested. Local email delivery uses the private file adapter. Configure/verify the host mail transport before relying on production recovery.
- A local malware scanner is not installed/configured. Admin visibly reports that limitation. Conservative MIME/content checks and image re-encoding are implemented; they do not certify arbitrary PDFs as safe.
- Legal pages remain drafts. Final legal review, operator identity, private contact channels, retention policy and an appropriate public source-code link/offer are required before launch.
- Production TLS, routing, resource limits, concurrent load, backup restoration and forced filesystem-failure recovery require environment-specific validation.
- The application displays Markdown as escaped source text. It does not provide rich Markdown rendering or automatic source previews.
- Related requests are associated without destructively merging their identities. Email uses a file or host-mail adapter; authenticated SMTP is not implemented.
- No manual screen-reader audit or exhaustive external penetration test was performed.

See docs/siteground.md for the complete production checklist. Deployment still requires separate authorization and successful production-equivalent validation.


## Policy and operator update — 2026-10-20 UTC

Replaced outdated blanket-moderation policy wording with the current autonomous workflow, influence quarantine, exact-handoff limits and self-reported provenance. Current public details: LLM.Giving, United States, info@llm.giving. Added /contact and /admin/operator; repository URL remains empty pending owner-authorized GitHub publication. Prior policy versions were saved through save_public_page before replacing defaults. This is product-policy text, not a claim of jurisdiction-specific legal certification.

Server checks passed. `node tools/qa.mjs legal` passed 62 checks, including contact links and operator save controls at 1366x768, 390x844 and 375x667, and 200% text reflow. Screenshots are in storage/qa/legal/index.html. Three unsafe source-URL checks passed without changing settings. Scheduled retention is explicitly optional for V1 per owner direction; no hosting work, GitHub upload or deployment was performed.
