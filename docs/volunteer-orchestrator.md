# Volunteer orchestrator handoff — integration in progress

This branch is not ready for release. Reservation and Volunteer session/queue dependencies are being implemented separately; manual pickup integration remains pending. No orchestrator route is enabled on this branch yet. Do not interpret public instructions as session authorization.

## Ownership and private boundary

- templates/volunteer-orchestrator.txt is reusable public guidance with no credentials or consent.
- src/volunteer-orchestrator.php composes the private supplement using vs_read and vs_assert_active. It validates the exact service-issued link against the retained session hash and requires the browser's session access. It neither creates a session nor reserves, renews, submits or archives anything.
- views/volunteer-orchestrator.php is a private partial for the actual authorization response. The caller passes the result of vs_create as orchestratorSession, after loading the orchestrator helper. It revalidates permission before rendering. It must never be rendered through a public archive or context route.
- public/assets/orchestrator.js enhances copying. Failed clipboard writes expose the complete selected text and report failure honestly. Native details/textarea remain available without JavaScript.

Only the current authorized Volunteer session permits the orchestrator to return to the queue. Each assigned worker receives an unchanged complete task handoff in its own work context and stops after that assignment. Ordinary single-assignment grants retain their one-task boundary. Task/repair mode integration must be bound to a server-validated session grant, never a packet flag or public reference prompt.

The private supplement has the service-issued fragment link and an explicit snapshot of scope, deadline, allowance, declared capabilities and the independent volunteer declaration. Actor hashes and retained credential hashes are omitted. The live server remains authoritative. Never archive the supplement or put it in public evidence, research packets, logs, analytics or shared screenshots.

## Acceptance evidence to collect after dependencies are integrated

| Case | Required evidence |
| --- | --- |
| Real authorization | Initially unchecked real consent precedes Copy orchestrator handoff; missing consent denied |
| Sequential continuation | At least two assignments, one session authorization, fresh complete handoff/context for each |
| Configuration | Incompatible/unknown required settings skipped; actual worker provenance retained |
| Repair | Recoverable validation error repaired with the same identifiers; bounded persistent failure |
| Receipt recovery | Uncertain response opens the existing receipt; no new submission key or duplicate |
| Reservation | Explicit active-work renewal changes expiry; copy/view does not |
| Limits | Session deadline/Stop block new operations; existing receipts remain accessible |
| Quarantine | Recorded pending outcome is not evidence; continuation only to eligible in-scope work |
| Reviewer lineage | Session and child workers cannot independently review their own research |
| Privacy | Public context, archives, packets and exports omit session/reservation credentials |
| Clipboard and native path | Denial reveals complete text honestly; native manual copying works without JavaScript |
| Viewports | Inspect 1366x768, 390x844, 375x667 viewport-only screenshots before interaction, plus 200% text |

## Evidence status

The synthetic study constructor tests/orchestrator-state.php creates two dependent fictional narrative tasks. It generates no completed research packets, sources, inspection records or model provenance. The genuine browser walkthrough must use actual generated handoffs and AI-authored results. Deterministic fixture packets, if used for boundary regression tests, must be reported separately and do not establish agent behavior or scientific correctness.

The required setup command initially failed because this worktree did not own port 3318. An ignored copy of the repository launcher under storage/qa/orchestrator/dev-runtime.mjs uses dedicated DB port 3346 and HTTP port 8356. Its first detached DB process exited after setup; one restart under a persistent parent passed an actual application query. The original database was not touched. Source/syntax checks pass for the draft. The existing complete repo checks passed after the documented Windows junction EPERM was rerun unchanged outside the sandbox and the explicitly named walkthrough fixture was removed before the full-text search test. The pinned session foundation (original commit 4155c07, cherry-picked as 29f570e) passes 49 authorization checks; the orchestrator supplement passes 15 focused checks against that real foundation. The queue/reservation/manual-pickup dependencies are not complete. The copy partial is not connected to a public route; no browser or genuine multi-task walkthrough is claimed.
