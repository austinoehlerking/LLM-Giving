# Research evidence verification

Implemented and checked locally on 2026-10-19 (America/Chicago). No production access, push, deployment, dependency installation or runtime AI call occurred.

## Changes verified

All four workflow stages receive evidence-first instructions and an evidence version 1 schema. Plans specify task evidence needs/stopping rules. New packets record inspected sources, central claims, exact passages, confidence, contrary evidence and current accepted basis results. Reviewer source audits generate claim-specific corrections and invalidate the affected task and dependent results. Result pages expose readable, escaped evidence and correction records. Historical evidence gaps remain explicit.

Migration 008 widens submission/audit metadata to MEDIUMTEXT and was applied only to the local MariaDB database. No data backfill or scientific reclassification was performed.

## Server checks

node tools/dev.mjs setup passed. The final node tools/check.mjs run passed:

| Check group | Passed |
| --- | ---: |
| PHP syntax | 68 files |
| Integration/security | 98 |
| Discovery | 20 |
| Blog/publication boundaries | 30 |
| Workflow | 82 |
| New research evidence cases | 48 |
| Quarantine | 59 |
| Exact handoffs | 36 |

Evidence cases cover missing and malformed records, exact body references, source access, abstract-only support, confidence, inference derivation, null findings, current upstream IDs, complete claim/source audits, contradictory-source coverage, correction propagation, historical audits and parent visibility. A full-text contradictory source cannot replace inspecting the supporting source. Unsupported/unverifiable conclusions cannot receive verified. Auto-generated corrections reopen affected work, preserve unrelated results and require replacement result IDs in later reviews. New quarantined packets retain the same checks on admin approval.

node --check passed for the changed browser test and check runner. Focused baseline diffs were inspected and passed whitespace checks. The workspace has no Git repository metadata, so no commit or normal git status was available. One intermediate full-suite run encountered a missing disposable foreign fixture; the focused rerun and final complete suite passed. One new counterevidence test exposed and led to fixing the supporting-source/full-text distinction before the final pass.

## Browser route and environment

The mandatory primary commands node tools/qa.mjs workflow and node tools/qa.mjs were attempted. Installed Edge exited during startup with a crashpad named-pipe error. The documented in-app Chromium fallback was used against the existing local app at http://127.0.0.1:8086 and a temporary read-only fixture server at http://127.0.0.1:8087. No browser dependency or privileged execution was added.

Automatic approval review rejected accepting the local public-content/CC0 test terms and submitting the synthetic packet. That action was not retried or bypassed. Browser coverage therefore used read-only Check packet validation and clearly labeled static HTML rendered through the real PHP result template. No result was submitted through the browser. Correction transactions were verified by server integration tests.

| Browser check | Result |
| --- | --- |
| Page identity / nonblank content | Live planning task and labeled rendered result/correction fixtures inspected |
| Initial viewport actions | Visible and unobscured at 1366x768, 390x844 and 375x667, before interaction; CSS minimum touch height 44px |
| Read-only packet interaction | Missing evidence rejected with repair prompt; valid packet preview accepted without advancing |
| Evidence interaction | Expanded claims show confidence, limits and counterevidence; source links reach access records |
| Correction display | Unsupported source audit and claim-specific required change readable on desktop and mobile |
| Escaping | Submitted HTML displayed literally; no injected image element |
| Enlarged text | 200% body text at 683x384 reflowed without horizontal clipping |
| Primary result action | Reached the real local research workflow |
| Browser logs | No warnings/errors in final rendered-fixture checks |
| Screenshots | Captured and inspected required first viewports, claim/source records, source audits, correction text and enlarged text |

The browser viewport capability required a one-pixel height adjustment to achieve the exact requested innerHeight at some sizes. CSS min-height was 44px; rendered geometry had a fractional projection tolerance of 0.02px. Each first-viewport test confirmed scroll position zero. Evidence JSON, measurements and PNGs remain under storage/qa/evidence-iab on D:.

## Limits and release

The full automated Edge lifecycle, live browser submission/correction cycle, and exact clipboard byte readback remain unverified. The in-app copy button reported success, but the clipboard read API returned empty. The browser result/correction images are explicitly labeled rendered fixtures, not submission receipts.

Automated checks validate structure, references, declared access and outcome consistency. They cannot detect omitted central claims, invented source access or whether a passage scientifically supports a conclusion. Independent reviewers must make and disclose that assessment. The new standard does not authenticate model provenance or establish scientific truth.

Optional visual recommendations are included in the handoff standard. This change does not implement chart generation, animation playback, uploads or interactive artifact hosting. Future artifacts need their own evidence and visibility controls, data provenance, accessible text equivalents and clear simulation labels.

Deployment did not occur. Production migration and deployment require separate authorization.
