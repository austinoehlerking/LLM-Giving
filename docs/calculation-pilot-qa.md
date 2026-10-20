# Calculation pilot: local verification

Verified 2026-10-19, America/Chicago. No production access, deployment, push, dependency installation or runtime AI/model service was used.

## Delivered behavior

- Separate requester opt-in for running computational models; false by default and preserved in approved briefs, administration and full context.
- Planned computational tasks retain exact input/code/output files, hashes, real environment/version details, units, execution status and an actionable checkpoint.
- Fresh handoffs and visible Resume saved work controls support another contributor/session continuing accepted progress.
- Current accepted/progress result downloads are visibility/revision/snapshot gated. Inert bundle extraction verifies names/hashes and cannot overwrite an existing directory or execute code.
- Independent calculation audits compare all output quantities and units. Corrections reopen the responsible task and dependents; quarantine approval revalidates normalized metadata.
- The retained pilot shows actual computed climate totals, original factor convention and explicit scientific limits. Provider/run metadata on software-test packets is labeled synthetic. Calculations actually ran locally.

Migration 009_calculations.sql was applied locally. Other in-progress project changes were preserved; a concurrent licensing schema addition initially caused an unknown-column HTTP error and was resolved by the project setup/migration command before the final HTTP pass.

## Automated results

| Command/check | Result |
| --- | --- |
| node tools/dev.mjs setup | Passed |
| node tools/check.mjs | Passed: PHP syntax, existing integration/discovery/workflow/evidence/artifacts/quarantine/handoffs/licensing/requester-requirement groups |
| Climate runner/extraction checks | 12 passed: real execution, fresh-process resume, independent fresh run, known baseline and sensitivity values, gram/kilogram error, changed input, corrupted checkpoint, paths containing spaces, path traversal, altered hashes, filename collisions and existing-directory protection |
| Calculation server/workflow checks | 33 passed: opt-in planning, strict boolean flags, required files/checkpoints, exact hashes, unit mismatch/boundaries, execution declarations, file roles, read-only validation, progress/resume references, reproduction coverage, stale/foreign results, correction propagation, quarantine revalidation, download bytes, inert rendering and withdrawal |
| node tools/calculation-pilot.mjs | 24 passed through real HTTP forms, sessions, CSRF, initial moderation, accepted progress, downloaded bundle extraction, fresh-process resumption, injected unit error, rejected check without advancement, independent correction, revision invalidation, replacement results and final reproduced review |
| Focused baseline whitespace / JS syntax | Passed; Git only emitted line-ending normalization warnings |

The HTTP runner checks the downloaded code against the known repository-owned model before executing it. A different process/session resumes using the actual downloaded input/code/checkpoint, and another fresh process recomputes the complete output independently. This verifies computational reproduction using the same implementation; it does not establish a separate human reviewer, independent implementation or field validity.

A real defect was fixed during testing: percent-encoded paths containing spaces could cause the model CLI's entrypoint check to skip execution silently. fileURLToPath now identifies the entrypoint correctly, and the test verifies output files are actually produced from a spaced directory. The pilot also rejects changed checkpoint identities, corrupted baseline values and unsafe/duplicate extraction paths.

## Browser coverage and limitations

The mandatory primary routes `node tools/qa.mjs viewport` and `node tools/qa.mjs` were attempted. Installed Edge exited at startup with `crashpad ... TransactNamedPipe: The pipe has been ended (0x6D)`. That route was stopped after two failures. The new `node tools/qa.mjs calculations` browser mode is supplied and syntax-checked, but was not claimed to pass on this machine.

Fallback used the in-app Chromium browser against the real app on http://127.0.0.1:8086. Before scrolling or auto-scrolling interactions, viewport-only screenshots were captured and inspected at exactly **1366x768, 390x844 and 375x667**. The request form, saved-checkpoint task, accepted calculation result and public pilot brief showed their primary actions fully inside the viewport and unobscured. The opt-in, title and objective were also visible before scrolling. CSS primary touch heights are at least 44px; the in-app geometry projection has a sub-0.02px rounding difference.

Real browser interactions verified the opt-in appears in preview, fresh resume prompt copying reports success, the unit-error packet is rejected, the corrected packet passes without submission, and the accepted result's primary action reaches the research workflow. Live-page console inspection reported no warnings/errors before the separate rendered-text fixture checks.

Final browser submission/terms acceptance was not exercised through the in-app browser. Actual mutations and final result receipts were covered by the HTTP walkthrough; browser checks were read-only apart from local form entry/preview/copy.

Enlarged body text was checked at 200% (32px at the tested breakpoint) using read-only copies of actual rendered submit/checkpoint/result markup. At the browser's actual 684x384 viewport they reflowed without horizontal clipping; ordinary vertical scrolling was allowed. These are labeled rendered fixtures, with scripts/forms blocked. This is enlarged-text layout evidence, not a claim that native browser zoom or live no-JavaScript submission was tested.

## Evidence and retained examples

Private local evidence stays under storage/qa/calculations on D:. Key records:

- http-report.json: current completed pilot and saved-checkpoint routes, session/form checks and scope notice.
- model-checks.json / integration-checks.json: actual executed checks.
- final-geometry.json: exact final viewport sizes, scroll position zero, action rectangles and occlusion checks.
- final-submit-*.png / final-checkpoint-*.png / final-completed-*.png: final first viewports.
- final-unit-error.png / final-packet-valid.png / browser-interactions.json: real browser validation loop, with no result submission.
- enlarged-*.png / enlarged-checks.json: read-only rendered 200% body-text fixtures.
- downloaded-bundle.json and retained/{pilot-id}/: actual HTTP bundle and verified extracted files.

Superseded disposable topics created by these pilot runs were removed only when they matched the generator's exact test brief and had no added contributions/feedback. The latest completed pilot and one ready saved-checkpoint example remain for inspection. The original climate research was not replaced. Temporary QA administrators and owned test servers were cleaned up.

Deterministic checks establish record consistency, file identity and declared arithmetic agreement. They cannot authenticate external model provenance, prove that a contributor executed a model, detect every omitted unit/assumption or establish scientific truth. Stochastic/distributional validation beyond this deterministic pilot remains future work. Deployment requires separate authorization.