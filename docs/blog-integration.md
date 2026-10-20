# Blog integration

The Blog link in the main navigation and footer opens `/blog`. The four reviewed guides are available at `/blog/unused-ai-tokens`, `/blog/folding-at-home-for-ai`, `/blog/ai-for-good-projects`, and `/blog/citizen-science-ai`.

## Content workflow

Edit the reviewed Markdown and metadata under `content/blog-drafts/`. Only catalogue entries explicitly marked `published` are compiled. Run `node tools/build-blog.mjs`; it writes `content/blog/articles.json` through the project safe writer. `node tools/check.mjs` rejects stale output. PHP serves structured, escaped blocks without Node or runtime AI. Private metadata and review files are never served.

The index and article pages include titles, descriptions, canonical URLs, text social previews, related links and Blog or BlogPosting structured data. No invented dates or named author are added. `/sitemap.xml` includes fixed public pages and the four guides.

## Verification

Local setup and the application checks passed: 61 PHP syntax checks; 98 integration/security, 20 discovery, 30 blog and 82 workflow checks; plus compiler checks. `node tests/blog-http.mjs` passed 96 checks against the local server, including complete server-rendered bodies, destination links, canonical redirects and draft isolation.

Both `node tools/qa.mjs blog` and `node tools/qa.mjs` were attempted. Edge crashed at browser startup in this Windows sandbox; an existing Firefox probe also exited with access denied. No dependency or privileged permission was added.

The in-app Chromium fallback passed 120 checks across the changed surfaces. Captured and inspected all five pages at 1366x768, 390x844, 375x667 and 768x1024; primary actions were fully visible and unobscured. Inspected 200% desktop zoom-equivalent reflow at 683x384 CSS pixels and mobile table scrolling. Real card, read, research, related-guide, contents, Submit and keyboard-skip actions worked. Existing home/research/submit first-viewport controls were checked at the three required sizes. No console errors were recorded.

Browser evidence is in `storage/qa/blog/`; exact rendered-copy editorial receipts are in `storage/editorial/2026-10-19/blog-integration/`. The full automated lifecycle browser run remains unverified because Edge could not launch.

## Release status

Integrated and verified locally. No production access, push or deployment was performed. Production deployment requires separate authorization and the established hosting checks.
