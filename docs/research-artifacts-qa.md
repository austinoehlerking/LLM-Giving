# Visual research artifact verification

Local verification completed on 2026-10-19. No deployment, push or production access was performed. All fixtures and reports are under storage/qa/artifacts on D: and are excluded from source control.

## Executed automated checks

- `node tools/dev.mjs setup` passed. Artifacts require no new database migration. Existing development migrations from concurrent work were applied before the final regression run.
- `node tools/check.mjs` passed: 88 PHP syntax checks; 98 integration/security, 20 discovery, 30 blog, 82 workflow, 48 evidence, 33 calculation, 60 artifact, 59 quarantine, 36 exact handoff, 17 citation/licensing and 18 requester AI requirement checks. Blog compilation, 12 real calculation execution/resumption checks and the artifact control suite also passed.
- The artifact-specific coverage totals 102 checks: 60 PHP integration checks, 31 HTTP/export checks and 11 playback/reduced-motion checks. Their detailed assertions are saved in integration.json, http.json and controls.json in storage/qa/artifacts.

Coverage includes typed/finite data, units, missing observations, zero-height bars, backing-value precision, diagram bounds and overlap, references, provenance, contrary sources, escaping, private-field exclusion, CSV formula protection, current-result visibility, moderation, all exports, mandatory reviewer audits, generated visual corrections and transitive task reopening. The HTTP suite owns its temporary server and fixture and tears both down.

## Browser route and fallback

Both `node tools/qa.mjs artifacts` and the required general `node tools/qa.mjs` were attempted through the project-owned Edge route. Edge exited at startup with `browserType.launch: Target page, context or browser has been closed` and a crashpad `TransactNamedPipe` pipe-ended error. No primary browser suite passed. No browser install or security escalation was attempted.

After that failure, the in-app Chromium browser exercised real accepted local artifact pages using the explicitly synthetic `[Artifact QA] Visual artifact evidence and controls` fixture. Its report is storage/qa/artifacts/iab-report.json. Viewport PNGs were captured before scrolling or interaction and inspected. All four artifact types were checked at these exact sizes:

| Viewport | Initial primary actions and essential controls |
| --- | --- |
| 1366 x 768 desktop | Fully inside viewport, readable and unobscured |
| 390 x 844 mobile | Fully inside viewport, readable and unobscured |
| 375 x 667 short mobile | Fully inside viewport, readable and unobscured |
| 768 x 1024 tablet | Fully inside viewport, readable and unobscured |

Controls retain computed minimum heights of 44 CSS px (46 px where applicable). Rect checks allow a 0.02 px browser projection tolerance. No whole-page horizontal overflow was observed; wide SVG figures have a separate keyboard-focusable horizontal scroll region. The final diagram renderer was also inspected with its compact fitted canvas.

Verified real interactions: scenario selection changes the visible chart/diagram, Reset restores the initial state, animation has no autoplay, Play/Pause retains the paused frame beyond an interval, Previous/Next and keyboard timeline navigation change scenes, timed playback reaches the next state and stops at the last one, Data & assumptions reaches the backing data/methods, Evidence reaches the result's claim/data registry, and the research catalogue opens the interactive artifact. The mobile plot region supports horizontal keyboard scrolling. No warning/error console entries were captured for the tested artifact views.

## Accessibility fallback limits

The in-app browser does not expose native reduced-motion or JavaScript-disabled contexts. The read-only local `tools/artifact-browser-fallback.mjs` therefore served copies of the actual rendered artifact HTML for these bounded checks:

- A clearly simulated reduced-motion media preference disabled Play, showed the preference notice and retained working manual Next. The separate real-controller harness covers preference changes during playback and hidden-page pause.
- A snapshot with all script tags removed retained the initial native figure, all six supplied data rows and the native disclosure containing every state's explanation.
- A 200% body-text snapshot at 683 x 384 reflowed without whole-page horizontal overflow. This is enlarged text; native browser zoom itself was unavailable.

The project-owned native browser suite includes actual media emulation and disabled-JavaScript contexts, but it could not execute because Edge failed to launch. Full general browser lifecycle/admin/submission coverage remains unverified in this session. Automated integration/security checks and the targeted in-app artifact interactions passed.

## Evidence and reproducibility limits

All displayed examples are explicitly simulated test data, not scientific findings. Structural checks enforce provenance fields, data links and reviewer coverage; they cannot prove source accuracy or scientific validity. Independent review must still inspect the linked underlying evidence and whether a visual overstates it. Hosted interactive pages select supplied scenarios; they do not execute arbitrary submitted code or compute new scientific results.

Specifications are attached to immutable result history. Current preview/export routes close when the parent, task, result or snapshot is unavailable or when correction supersedes the accepted result. JSON exports include allowlisted public data only. CSV and selected-state SVG exports were checked over HTTP.

The temporary in-app fixture and fallback server are removed/stopped after verification. The pre-existing local application server is left running. Source changes from concurrent work were preserved. No production action was authorized or performed.