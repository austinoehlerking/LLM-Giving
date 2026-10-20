# Research library implementation — October 18, 2026

Implemented the approved charcoal/lime design in the PHP application, not just the concept files.

- Homepage explains directing unused AI subscription usage toward useful deep research requests, compares this to Folding@home and spare CPU/GPU cycles, and states that volunteers run their own investigations.
- Homepage and /research share compact real-data cards, category selection with public counts, status and tag filters, no-published-contributions filtering, sorting, result counts, reset links, and pagination.
- Sort choices: newest, recently active, recently updated, fewest or most approved research contributions, title A–Z.
- Filters compose and survive category changes and pagination. Changing a filter resets pagination. GET forms remain usable without JavaScript; JavaScript enhances select/checkbox changes.
- Counts and sorting exclude unpublished children and approved children whose required target is no longer approved. Pending and withdrawn parents remain excluded.
- Cards retain actual research status, contribution/review counts, dates, tags, and direct detail links. Unicode-safe excerpts keep cards compact without breaking non-ASCII text.
- LLM.Giving branding updated across navigation, footer, page titles, platform email subjects, default content, and prompt branding. Database identifiers and technical paths remain unchanged.
- Dark styling extends to research details, submissions, administration, and public content.
- Migration 003 refreshes only the original saved homepage defaults, preserving custom administrator copy.

## Verification

- node tools/dev.mjs setup: passed; local migration 003 applied.
- node tools/check.mjs: 38 PHP syntax checks, 98 integration/security checks, 20 discovery regression checks passed.
- node tools/qa.mjs: full browser lifecycle passed, 105 checks and 30 screenshots.
- node tools/qa.mjs library: 52 checks passed, including shared viewport checks and targeted combined filtering, sorting, pagination, search, mobile categories, empty/reset state, no-JavaScript fallback, branding and dark stylesheet checks.
- JavaScript syntax checks passed for application, browser test module, and runner.
- Required initial screenshots captured before interactions at 1366×768, 390×844, 375×667, and 768×1024. Home start actions, research contribution/copy actions, submission action, and directory search were inside the viewport, unobscured, and at least 44 CSS px high.
- Screenshots inspected for homepage, directory, research detail, submission, and mobile administration. Long-title controls and enlarged-text reflow were also covered by the repository runner.
- Disposable browser fixtures and temporary QA administrator cleaned up. Existing labeled demonstration research remains.
- Local application at http://127.0.0.1:8086 returned HTTP 200 and rendered the new library, explanation, and brand.

Evidence: storage/qa/browser-report.json, library-report.json, integration.json, discovery.json, and library-home-full.png.

No deployment or push occurred. This workspace is not a Git repository, so no Git diff or commit was available. Existing hosting, email-delivery, and production launch gates in docs/siteground.md still apply.
