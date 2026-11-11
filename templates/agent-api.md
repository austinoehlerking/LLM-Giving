# Agent access and research continuity

Human path: configure an ordinary Volunteer session, confirm existing authorization and resource/publication consent, choose **connect an agent via API**, enable access, and copy one private start message. There is no agent signup, provider-key collection or installed client. Browser participation remains available. A valid session owner may replace/revoke API access from the same page. Replacement invalidates the previous token; the original session, consent, deadline, assignment count and actor/account lineage do not change.

The API delegates existing authority. It cannot create sessions, accept terms, attest reviewer-account separation, change worker settings, expand scope/limits, or promote moderation decisions. Human review attestations still happen through the normal exact-target form. Research only and Research + tools preserve their existing capability restrictions. API transport is not a paid-model substitute or permission to install research software.

## Protocol v1

Use HTTPS in production. The private credential is sent only as `Authorization: Bearer llmg_SESSION_SECRET` to the configured site origin. Never put it in URLs, logs, research packets, notes or public context. No cookies, Origin, CORS or browser sessions authenticate API requests. JSON errors use HTTP status plus `ok:false,error:{code,message}`. Success is `ok:true,data`.

Base: `/api/agent/v1/sessions/{session_id}`.

| Method/path | Request and result |
| --- | --- |
| GET base | Live state/limits, requested worker, active/recent assignments (20 maximum), saved work, receipts and a fresh claim_key. Older known assignment IDs remain directly retrievable. |
| GET /instructions | Complete controller guidance, transport override, exact UTF-8 length and SHA-256. |
| POST /claim | `{"claim_key":"32 lowercase hex characters"}`; use the supplied key and preserve it on retries. Shared lease/capacity service atomically claims compatible work. |
| GET /assignments/{id} | Exact assignment status, research notes, queue reason and recorded receipt if any. |
| GET /assignments/{id}/handoff | Complete unchanged archived body with digest/length, bound worker procedure and required-context directory. Fetch and inspect all required resources before research. Public dependency/part URLs require no Authorization header. |
| GET /assignments/{id}/notes | Private versioned unfinished research. |
| POST /assignments/{id}/notes | `{"version":0,"notes":{"hypothesis":"","findings":"","attempted_approaches":"","open_questions":"","next_step":""}}`. Use latest version; stale writes reject without overwriting. |
| POST /assignments/{id}/check | `{"packet":{...}}`; shared full validation, no scientific result recorded. Validation errors return the shared repair prompt to the same worker. |
| POST /assignments/{id}/submit | Same complete packet; shared recording/idempotency/consent/evidence/independence/moderation path. Read the actual receipt. |
| GET /assignments/{id}/receipt | Recorded status/outcome if present, without submitting. Receipt URLs may require the owner's private browser access. |
| POST /assignments/{id}/renew, /release, /reacquire | Empty JSON object; shared explicit lease operations. Renewal cannot extend authorization. |
| POST /stop | Empty JSON object; shared Stop revokes work and releases assignments. |

Keep at most the captured concurrent capacity, use exact worker configuration/preflight and original signed identifiers, and distinguish accepted, quarantined and unsubmitted work. Before retrying an uncertain Submit, GET its receipt. Identical packets recover the original result; changed duplicates reject. Check is not a receipt. On no eligible work, stop instead of polling. No GET claims or renews work. Exhausted eligible work returns HTTP 409 with error code `no_work`; locked candidates may temporarily be unavailable. Do not poll automatically.

An API request has a 4,300,000-byte envelope limit; the original snapshot-specific packet validator retains its own byte limits. This initial API accepts complete JSON requests; existing browser multipart transport remains available for tools with smaller request limits. No submitted code runs on the website.

## Private saved work

Five fields retain hypotheses, findings, attempts, questions and a next step (4,000 UTF-8 bytes each). One current note record per assignment uses optimistic versions, credential binding, and a fixed seven-day window from assignment pickup. Pruning cannot reopen that window. Saving/retrying never renews the lease, publishes research, records scientific progress or extends retention. Reads remain available after Stop/expiry for recovery, subject to parent/task/snapshot visibility and non-revoked credentials. New saves need a live owned assignment and current permission. Rotation of a session or underlying grant invalidates predecessor notes. API credential replacement alone retains the same owner/session notes.

Existing recovery continues to provide explicit same-task human reauthorization; this API does not transfer notes automatically into a new grant or turn a new session into an independent reviewer. Notes may be copied by the owner after reassessing the current task. They are untrusted data and never replace the exact task context or evidence inventory.

## Queue policy v1

Eligible candidates receive age in days since task creation, plus four days for review, two for work following an accepted assignment in this session, and up to three for directly dependent Waiting tasks. Tie-break by creation time and stable task ID. Unbounded age and bounded bonuses prevent newer tasks permanently outranking old ones. This is scheduling priority, not scientific quality or model ranking. Busy candidate parents are skipped without waiting to prevent lock-order cycles between personalized queues. Eligibility, scope, current listing, profile/model/software consent, independence, reservations, limits and all shared locks are rechecked at pickup. Each assignment retains a plain-language explanation.
