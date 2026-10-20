# Research-tool project verification

Implemented locally on 19 October 2026 (America/Chicago). No deployment, remote push, production access, dependency installation or GitHub integration.

## Server and real execution evidence

The local setup applied additive migration 011_research_tools.sql. Existing requests default to research; existing task flags default to false. `node tools/check.mjs` passed with 95 PHP files syntax-checked and all existing integration suites green. The focused research-tool suite passes 62 checks, including required briefs/plans, file identity/filenames, complete test coverage, numerical comparisons and whitespace, human software consent, progress, builder/reviewer separation (including prior drafts), correction invalidation, quarantine revalidation, escaping, individual file downloads, parent/child visibility and snapshot revocation.

The source-controlled metric-distance fixture actually ran its reference, zero-boundary and invalid-input tests in separate builder/reviewer Node processes. This demonstrates software execution and record handling, not scientific research quality or independent human identity. Execution logs: storage/qa/tool-builds/execution/{builder,reviewer}/run.json.

## Browser route and fallback

Both `node tools/qa.mjs tools` and the required baseline `node tools/qa.mjs` failed before page interaction because installed Edge exited inside the Windows sandbox with browserType.launch / crashpad TransactNamedPipe. No dependency was installed and no elevated launch was attempted. The connected in-app browser then exercised the real PHP application at http://127.0.0.1:8086; a separate Chrome browser session submitted the independent review. Synthesis was supplied through the local CLI fixture helper. Browser fixture content and model/run declarations are explicitly synthetic.

Observed interaction: select tool project → fill all required brief fields → review complete preview → submit request → initial approval through fixture helper → check/submit planning packet in browser → build task → copy full prompt → reject a factor-of-1000 incorrect output without advancement → check/submit corrected build → separate-browser check/submit reproduction review → Review Complete → current build displayed in project/catalogue. The tool bundle and individual file routes passed 16 real HTTP checks for exact bytes, licenses, MIME/attachment headers, current identity and revocation. No app error/warning logs were observed in either connected-browser session. Disposable project fixtures were purged and the two QA-owned servers were stopped after evidence capture.

## Viewport and rendering evidence

Viewport-only screenshots were captured before interaction at 1366×768, 390×844 and 375×667 for the request, build task, independent-review task, build result and completed project. Primary action rectangles were fully inside the viewport and unobscured. Project type, computational-model option, title and question were also checked before scrolling. An initial 375×667 defect clipped the bottom of the question input; shorter introductory/help text corrected it and all three sizes were recaptured.

Screenshots and DOM measurements: storage/qa/tool-builds/iab-*.png and iab-browser-evidence.json. Screenshots were inspected for readable text, wrapping, clipping, action placement and consistency. Source code remains escaped and collapsed into a readable file section.

Text enlargement uses read-only snapshots fetched from the actual PHP pages with body font-size:200% and a requested 683×384 CSS viewport (the in-app browser reported 684×385). Request/task/result pages have no document-level horizontal overflow. This is a CSS enlargement/reflow simulation, not manual browser-toolbar zoom. Script-free snapshots contain zero script elements; the native tool selector, expandable requirements, source/test record and download links remain usable. Native POST routes are exercised by the real browser workflow and server integration tests; a JavaScript-disabled browser context was not available through the fallback API.

Remaining limit: the standalone Edge browser suite remains blocked by the sandbox launch failure; the recorded connected-browser route supplies the rendered and interaction evidence. No scientific validity, independent penetration test or production readiness is claimed.