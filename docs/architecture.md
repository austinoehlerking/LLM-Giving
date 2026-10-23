# Architecture

## Request path

public/index.php bootstraps configuration, headers, sessions, CSRF and routing. public/ is the only web document root. src/admin.php handles admin routes after server-side authorization; src/view.php renders PHP templates with systematic output escaping. Content is stored and displayed as plain text/Markdown source rather than interpreted HTML.

The workflow is organized into lifecycle/audit (`workflow.php`), context/signing (`workflow-context.php`), policy (`workflow-policy.php`) and submission/state transitions (`workflow-engine.php`), with specialized evidence modules. See [the development guide](development.md) for the full module map and contributor commands.

src/domain.php owns research retrieval, discovery, state changes, moderation, canonical findings and purge. src/submissions.php validates anonymous contributions and feedback. src/files.php handles validated, private attachments and download-only serving. src/prompt.php composes approved context through a source-controlled trusted safety wrapper. src/security.php contains credentials, CSRF, throttling, safe URLs and limited email. src/db.php uses PDO prepared statements and transactional helpers.

There are no paid dependencies or production Node services. Local Node tools manage processes, existing binaries and browser QA only.

The platform is [open source on GitHub](https://github.com/austinoehlerking/LLM-Giving) under AGPL-3.0. The homepage, footer and Licensing page expose the source link. The rendering helper defaults to this published repository and preserves an operator-configured source_url for forks or modified deployments. Public source availability supports inspection; it does not establish scientific correctness or replace the publication and permission boundaries below.

## Editorial blog

The fixed content/blog/articles.json collection contains only explicitly published editorial entries compiled from reviewed Markdown by tools/build-blog.mjs. src/blog.php serves that collection through /blog, /blog/{slug} and a fixed-public-page sitemap. Request parameters never select filesystem paths. Draft metadata, keyword research and private review records are excluded from the compiled field allowlist.

Editorial blocks support headings, paragraphs, lists, quotes and tables. Their text and attributes are escaped by the dedicated renderer, and links use an explicit internal-route or HTTPS rule. This renderer never processes submitted research, attachments or administrator-edited public text; their existing plain-text rendering and visibility controls remain in force. Canonical and social metadata use the configured APP_URL. No publication date or named author is invented.

## Data relationships

Research has an immutable random 128-bit ID and a stable readable slug. Slugs do not change when titles are revised.

- categories → research; tags ↔ research_tags ↔ research.
- credentials and recovery_tokens are request-scoped and store only SHA-256 hashes of random 256-bit secrets.
- contributions hold original research, follow-ups, progress and independent reviews. Independent reviews have a target_id. The composite FK (target_id, research_id) enforces the same research parent.
- agent_metadata is separate from content and has a composite contribution/request FK.
- findings and finding_evidence link canonical entries to approved contributions/reviews. Reclassification appends a new entry referencing supersedes_id; original entries remain.
- feedback can target a contribution or finding within the same request.
- attachments identify the research parent and their actual contribution/feedback owner. These ownership relationships use composite foreign keys.
- revisions are proposed changes; research_versions preserves prior published title/question/context on approval.
- status_history records the old and new research statuses, actor, rationale and timestamp. This is research provenance, not a general admin audit log.
- research_links associates related or duplicate requests without merging identities or destructively moving contributions.
- emails tracks delivery acceptance/failure without exposing bodies in admin listings.
- settings stores safe site settings and editable public text. legal_versions preserves each legal text bundle when an administrator edits a policy, and new submissions record the current acceptance version. Secrets live only in the environment.
- purge_jobs stores only paths awaiting cleanup after transactional database deletion.
- rate_limits and admins support application security.

## Forked research

Immutable attributed background lives separately from the editable child brief. New identities retain independent moderation, capabilities and workflows. Public helpers recheck transitive source visibility, source snapshot integrity and record moderation before serving a descendant, including archived contexts and file routes. Later corrections warn without rewriting pinned history. See [the fork contract](research-forks.md).

## Three separate dimensions

Moderation controls initial topic visibility and exceptional removals. Research status describes autonomous workflow progress. Topic approval creates a planning task; accepted structured results unlock research, synthesis and independent verification. Review Complete records a contributor recommendation, never a guarantee of scientific truth. Blockers and clarification pause advancement until an authorized response starts a new planning round. Finding classification remains a separate optional administrator judgment.

Requester Solved/Paused/Withdrawn actions have explicit attribution. The administrator can restore or reopen with a rationale. The inactivity threshold defaults to 60 days and uses meaningful approved activity. Inactivity archiving is invoked by CLI/cron or the admin maintenance control.

## Publication boundary

research(..., true) is the central public parent gate. Every public research page, prompt, JSON/Markdown record and attachment route uses it. Approved children are selected explicitly; pending/rejected/removed items are omitted. Reviews and targeted feedback disappear if their target contribution is removed. Parent withdrawal hides all linked public content. Private emails, capability hashes, flags, exception rationales and moderation reasons are not exported.

Public context uses explicit field allowlists. Context contains complete approved material in stable section and record order and increments its version on context-affecting changes. No AI summaries or silent truncation occur. Long records are pageless within a request; server resource limits still apply.

## Capabilities

The requester secret is rendered directly in the submission/rotation/recovery POST response and optionally delivered by private email. It is never placed in a redirect URL or persisted as a receipt in server sessions; sessions store only request-scoped credential hashes. The browser exchanges the URL fragment via a CSRF-protected POST, removes the fragment from browser history, regenerates the session ID, and grants access to only that request for 30 minutes. Every management operation compares the current credential hash with the session grant, so rotation revokes old sessions as well as old links.

The private-link receipt uses an in-memory browser guard: successful clipboard writes are distinguished from attempted copies, and a separate checkbox confirms the link was saved. In-page navigation opens an accessible confirmation dialog until that acknowledgment. The associated acknowledgment label has a minimum 44 CSS px clickable height; dialog controls remain usable with enlarged text and keyboard navigation. Tab close, reload and browser navigation use the browser-controlled beforeunload warning where supported. No link, secret or acknowledgment is added to Web Storage or server sessions. Each replacement receipt starts a fresh guard. This is a usability safeguard, not an authorization boundary.

Recovery requires a matching stored email and research ID but always returns the same public response. A short-lived single-use recovery credential is emailed; consuming it under a row lock rotates the management credential. Email possession is required; entering an address does not authorize access.

## Transactions and filesystem effects

Submission creation and attachment records share a transaction. Known newly written upload files are tracked and removed if the transaction fails. Purge records paths in a durable job and deletes DB records atomically; filesystem cleanup happens afterward and failures are retained for retry. There is no cross-request cascade through a review target because target references enforce the same request ID.

MariaDB migration DDL commits implicitly; take backups before production changes. Migration filenames are recorded and are never re-run once successful. A failed partially applied initial migration needs diagnosis before retry; do not blindly reset an existing database.


## Signed AI handoffs

Task schemas include a server-issued HMAC-SHA256 handoff_code bound to research ID, task ID, task revision, immutable context ID and submission_key. New task submissions must preserve all identifiers. The signing key is generated once under storage/private/handoff.key with an exclusive file lock; keep it private and persistent across application workers and deployments. Rotating or losing it invalidates outstanding handoffs. Existing recorded results remain readable. Exact accepted retries retain their existing actor/payload idempotency checks.

The code detects identifier mismatches, not authorship or research accuracy: public visitors can obtain signed prompts. It grants no moderation or management permission. CSRF, terms, ready-state, visibility, revision, evidence, model-policy and independent-review checks still apply. Prompts specify the browser return URL and require a recorded outcome before claiming submission.

## Exact issued handoff archives

Every HTTP handoff and task-prompt issuance stores the complete UTF-8 response body in workflow_handoffs, including the wrapper, task instructions, output schema and context. A server-generated archive ID, SHA-256, UTC issuance time and submission key link the copy to its eventual packet. Background-only handoffs are archived too. The database rejects updates; explicit parent purge removes its archives. Logical backups include the table and immutable-update trigger.

New issuance is capped at 60 copies per direct client IP per hour and 1,000 site-wide per hour, before context generation. A shared transactional budget also caps new archive text at 100 MiB per UTC day. Copies over 4 MiB fail without truncation or archival. Fixed-hour counters are shared by all workers. Archived reads are not charged. Run `php bin/console.php handoffs-prune` daily, or use the admin System maintenance control: unused copies older than 30 days are removed; all archives matching any submitted nonce are retained, including rejected/quarantined/private results. Cleanup and submissions share the research row lock. Each cleanup handles up to 100 eligible projects; repeat for a backlog. These application limits supplement hosting-level bandwidth/storage monitoring.

Archive reads enforce the public parent, task visibility and snapshot revocation gates, and check the stored hash. Issuing a handoff does not change research context or create recursive archive content. The issued text is returned unchanged; HTTP headers carry its archive ID and checksum. Windows clipboard CRLF conversion is not treated as scientific editing; the archived HTTP bytes remain exact.

New packet metadata records successful task-signature validation and any matching issued archive. The UI says “Task signature valid. Conversation integrity is self-reported.” Historical packets without this metadata are not retroactively labeled verified. A schema-reference or older packet without an archive is explicitly labeled as unlinked. No AI-computed checksum or provider-authenticated execution is claimed.

## Packet checks and influence disclosures

Check packet runs the same validation transaction as submission and exits before writes, task changes or audit attempts. Final submission always revalidates current task state. Manual returns require current human terms confirmation on actual submission; AI browser returns use independently captured, scoped volunteer consent. Client checks are advisory; they confer no acceptance capability.

The AI packet declares human_interaction, explicitly covering initial prompt edits before the first message, removed instructions/context, added sources, preferred conclusions and later input. Uncertainty also triggers quarantine; repair_only applies only when the original handoff was unchanged and the additional input was a platform formatting repair. The contributor must independently choose human_disclosure through separate manual form controls or the private authorization form before AI browser return; the route overwrites any packet-supplied contributor declaration. Substantive influence or uncertainty from either source adds a public review-needed flag and UTC event. Platform-only formatting repair is recorded separately. These declarations are not detection of undisclosed external edits.

New influenced or uncertain packets pass structural validation into admission=quarantined, without applying any outcome. The project rejects other submissions and handoffs advertise an admin hold. Quarantined/rejected bodies are excluded from agent context; receipts and UTC decisions remain accessible. Under the parent lock, an administrator explicitly approves or rejects with a reason. Approval rechecks current round, revision, project availability and evidence targets, then applies the original outcome once. Rejection leaves research unchanged and restores the current task for fresh work. Original disclosures remain intact. Historical already-applied flags support assessment only; they are never replayed. Historical results without disclosure are labeled as not collected, never silently labeled uninfluenced. Review decisions update context and append an audit event. No runtime AI calls are added.

## Evidence-linked workflow acceptance

Each nonempty acceptance line defines a stable criterion-N within a task revision (1–20 criteria per planned task). Terminal completed, inconclusive and verified submissions must assess every required criterion as met, give a reason and quote 20–3000 characters exactly from the referenced result. Independent reviews assess the current accepted research and synthesis results as well as their own review deliverable. These deterministic checks establish evidence presence, not scientific support or provider-authenticated provenance. A documented null result can satisfy a deliverable; missing work cannot.

Acceptance assessments and targeted corrections are retained in submission metadata and immutable context snapshots. Corrected task revisions also require an explicit correction_response in their self assessments. Reviewers can select a task and criterion in the browser correction composer. The server requires a current, public, same-project accepted research/synthesis result; it reopens that task and all transitive dependents, clears accepted pointers and increments revisions atomically. Unaffected tasks and all historical submissions remain intact. Reopened reviews assess the replacement result IDs. Each invalidation is recorded in the UTC ledger with its initiating review and previous result ID.

The research brief and workflow show the current accepted synthesis, limitations, next steps and completed public reviews. Invalidated syntheses are excluded. Historical results without structured checks are explicitly labeled rather than backfilled. No database migration or runtime AI call is required.

Local workflow browser QA temporarily raises the local submission limit to 30 for the longer correction cycle and restores the original setting in finally. It does not change production. Screenshots and reports remain under storage/qa/workflow.

## Evidence-led research standard

All new workflow stages carry structured evidence version 1: search/access records, source metadata, claim-to-passage links, scoped confidence, contrary evidence and current upstream result IDs. Planning tasks require evidence needs and stopping rules. Independent review enumerates current claims and their cited sources; unsupported, unverifiable or overstated conclusions require a correction outcome and generate claim-specific evidence-standard corrections. Existing invalidation reopens the responsible task and transitive dependents. Source support remains a reviewer judgment; PHP validates structure and references without AI or external fetching.

Evidence lives in submission metadata and complete context snapshots. Migration 008 widens submission/audit metadata to MEDIUMTEXT. Historical records are explicitly labeled and receive whole-result legacy audits, with no invented source inspections. Result pages expose escaped structured evidence and source audits. See [the evidence contract](research-evidence.md) for stage rules, limits, historical behavior and optional visual recommendations.


## Resources and supporting materials

src/research-resources.php composes the study-level /resources catalogue from public, accepted workflow records in the current round/revision (including useful progress) and approved, unsuperseded supplementary contributions. Parent visibility, task visibility, child admission and non-revoked snapshots are checked before aggregation, direct result/source reads and every specialized download. Historical accepted work remains linked as audit history only while its parent, child/task and snapshot remain eligible; current downloads are withdrawn after correction or supersession. New context excludes workflow bodies whose task or snapshot is no longer public. No source URL is fetched by PHP.

Source identities consolidate matching DOI identifiers/DOI URLs or normalized source URLs within the same explicitly recorded dataset edition. Each contribution retains its own attribution, access declaration, extraction, usage and result/claim links. Authors/organization, publication date, journal/publisher, DOI and dataset edition may be honestly absent with a metadata note; historical records are never backfilled. Background, abstract-only, unavailable and rejected references are distinguished from inspected-and-used sources. Structured calculation inputs and observed/derived datasets locate extracted values and units in original sources; derived outputs remain separate.

Every new task and supplementary submission carries a bounded supporting_materials inventory. Applicable methods, sources, specialized files, formulas, units and actual software versions are enforced structurally; missing/oversized required material blocks completion while progress preserves useful work. Inapplicable code/execution is not invented for narrative research. Restricted third-party material uses attribution, access instructions and reproducible methods instead of unauthorized copies. Independent review must audit all current research/synthesis material references; gaps produce supporting-materials corrections through the existing invalidation transaction. These checks establish presence and reference integrity; scientific adequacy, actual access and honest applicability remain independent reviewer judgments.

Migration 014 adds a nullable supplementary evidence column without rewriting historical records. Existing accepted retries and stored records remain readable. Only administrator revalidation of already stored historical quarantined packets can use the former structural contract; new packets cannot select that mode. Existing handoff bytes and snapshots remain unchanged. New contracts appear in task, repair, supplementary and agent instructions. All content budgets fail explicitly; retained source/code/file bytes are not truncated. No production migration is performed by development QA.

## Requester AI requirements

Current multi-model/effort controls, versioned persistence and separate workflow modes are described in [requester AI selection](requester-ai-selection.md). Historical matching and immutable handoff/snapshot bytes remain intact.


Migration 010 adds optional requester-selected exact model and exact/minimum reasoning requirements. The model list comes from the configured planning policy; reasoning order is low, medium, high, xhigh, max, ultra. No cross-model ranking is implied. Creation rejects combinations that cannot also satisfy mandatory Max/Ultra planning. Requirements appear in request previews, management, admin review, task pages, prompts, handoffs and full context. Task, handoff, supplementary and repair prompts require a configuration preflight before any work. Unknown/unconfirmed required settings and mismatches stop the run with a plain-language BLOCKED — AI CONFIGURATION message, required/actual settings, a concrete fix and instructions to copy a fresh handoff into a new qualifying run. This explicitly overrides JSON-only output; configuration failures are not submitted as scientific blockers or claimed to change project status. Both task packets and supplementary contributions enforce them server-side against declared metadata; this is not provider authentication.

The original requester can change requirements before the first research submission. Parent-row locking serializes updates with submissions; later changes are refused, including after quarantined submissions. Changes create a UTC audit event and new context version. Old handoffs whose requirements differ from the current request are rejected with instructions to copy a fresh handoff. Existing requests default to no additional restriction. Verification: tests/requirements.php and node tools/qa.mjs requirements; screenshot gallery storage/qa/ai-requirements/index.html.

## Research attribution and citations

All existing local research contributions were offered under CC BY 4.0 with explicit owner authorization. Migration 009_research_license.sql adds the server-owned license to supplementary contributions and workflow submissions. New workflow packets also record the human-accepted terms version and license in metadata; packet-supplied license values cannot override these. Current public context exposes the research license and per-result license. Software remains AGPL-3.0; third-party sources retain their rights. Historical issued handoffs and policy snapshots remain byte-preserved audit evidence, not the current licensing notice.

Cite this research copies the named contributor (or LLM.Giving contributors), research title, UTC result date, exact result ID, result URL, site name and CC BY license link. The top-page action cites the current accepted synthesis; result pages cite that particular result. The manual textarea works without clipboard access. Full citation guidance sits below the summary and takeaways. Research-request citations are marked as requests rather than completed results.

Local verification: node tools/check.mjs; node tools/citation-qa.mjs. The standalone runner is a fallback for a shared QA runner that temporarily imported an unavailable calculation-browser module. Browser QA passed 14 checks, including an actual synthetic packet submission, CC BY consent, context export, clipboard fallback, three first-viewports and enlarged text. Screenshots: storage/qa/citations/index.html. No deployment or GitHub publication was performed.

## Visual research artifacts

Optional typed datasets and visual specifications live inside accepted workflow evidence. src/research-artifacts.php validates bounded native chart/diagram/state formats, builds artifact review targets and enforces parent, result, task, current-round/accepted-pointer and snapshot gates for every preview/download. src/artifact-render.php produces escaped native SVG and complete data tables. Local artifacts.js handles only supplied-state selection and explicit playback; no submitted code, remote assets, model execution or runtime AI is used. Result pages and research/workflow catalogues link current artifacts. JSON/CSV/SVG exports contain allowlisted public content. Failing visual audits generate artifact-standard corrections through the existing transactional invalidation path. No artifact-specific database migration or new upload surface is required. See [the artifact contract](research-artifacts.md) and [verification evidence](research-artifacts-qa.md).

## Guided request setup

views/request-setup.php embeds a public, escaped category/model policy and local interview instructions. The local request-setup.js module copies a prompt tailored to the public form draft and validates a versioned JSON setup result before any field changes. Import only fills the public request allowlist, preserves private identity/contact/files, clears consent for fresh human review and never submits. Raw prompt/paste textareas are unnamed. No endpoint, AI call, database record or new permission is involved; existing server validation and moderation remain authoritative. Conversation revision 4 starts with quick/in-depth selection, preserves supplied scope, bounds essential quick clarifications, and separates copy/import instructions and a Ready gate from the complete fenced JSON response. The JSON contract remains v3. A native manual guide follows the same conversation with an explicit plain-text/manual-entry override when JavaScript is disabled. See request-setup.md for the contract and executed QA.

## Research tool projects

Additive migration 011_research_tools.sql adds research.project_type/tool_requirements and workflow_tasks.builds_tool. Existing records remain ordinary research. src/research-tools.php validates the required tool brief, bounded inert source/test files, exact checksums, reference/boundary/invalid-input tests and independent reproduction/scientific assessments. Original source/tests use the existing AGPL v3 software license with a separately recorded human software consent; narrative keeps its current research license. No submitted code is executed and no external repository service is contacted.

Tool plans use existing research tasks with builds_tool=true; synthesis/review and correction propagation retain the existing transaction and revision model. Reviewer browser/run separation covers accepted current and earlier tool builders as well as synthesis. Tool-standard corrections invalidate the affected task and transitive dependents. Public bundles and individual file attachments use the existing parent, accepted child, current revision and snapshot gates, with hash verification. Current builds are listed only when those download gates pass. See research-tools.md and research-tools-qa.md.

## External software and computing requirements

Migration 012 stores a bounded external_requirements JSON brief. New briefs use version 2: calculation_help (unspecified, maybe, prepare_review, run), external_tools (unspecified, none, free, licensed, mixed, unknown), required_software and setup_needs. Maybe is the new form/interview default: useful calculations are permitted but not required. None specified grants no execution permission; preparation/review only explicitly excludes execution; run requires a computational task. Public context includes calculation_policy with separate allowed/required/preparation_only decisions. The compatibility computational_models column records permission; the selected calculation preference is authoritative. Contradictory legacy API flags are rejected. Old checkbox-only callers retain their explicit behavior, and old opted-in records without a calculation preference retain required execution.

License eligibility and computing-budget questions are absent from new forms and guided setup. Existing JSON and immutable snapshots remain unchanged; explicit historical license/resource restrictions are still displayed read only and exported. Tools=none means no mandatory dependency and suppresses stale required_software before validation, saving, previews and current prompts, while allowing volunteers to choose useful tools they can legally use. Unknown setup is displayed as None specified. Volunteers remain responsible for permissions, resource use and purchases; requests grant no installation or spending authorization. Agent instructions ask only about concrete dependencies and still require specific authorization for installation or purchases. These public fields retain the existing parent moderation gates. No database migration, runtime AI, installation or execution service is added. Tests: tests/external-requirements.php, tests/request-setup.mjs, tests/calculations.php, and the calculations/setup/submission browser suites.

## Reproducible-calculation records

The explicit research computational_models flag and per-task computational flag are introduced by migration 009_calculations.sql. src/research-calculations.php validates inert exact-text calculation files, checksums, declared quantities/conversions, same-revision checkpoints and independent output audits. It generates calculation-standard corrections through the existing dependent-task invalidation path. Complete context and handoff archives retain the records. Downloads enforce public parent/accepted child/current task revision/non-revoked snapshot gates. PHP never runs submitted code. Local Node tooling supplies the climate arithmetic pilot and safe extraction; it is not a production service. See [the calculation contract and QA route](reproducible-calculations.md).

## Google services

src/google.php emits a static Search Console ownership tag on the home page and a local Analytics loader only on successful allowlisted public GET pages. When enabled, the loader starts Analytics automatically without visitor opt-in controls, respects browser privacy signals and previously saved opt-outs, strips URL queries/fragments and referrers, and disables advertising features. Private sessions, forms, receipts, file/context routes and errors omit it. CSP adds specific Google script/collection origins on eligible requests. Local environments default to no collection. Public identifiers have validated environment overrides; no Google credential or API secret is stored. The rendered Analytics notice is also part of the canonical legal bundle. Effective acceptance versions fingerprint its full content; actual acceptance and admin policy saves persist complete snapshots without rewriting historical records. Rendering and packet checks do not write policy snapshots. See [Google service setup](google-services.md).

## Anonymous AI browser return

Migration 013 adds nullable submission_method columns to workflow submissions and supplementary contributions, supplementary human_influence, and the private browser_returns table. Existing rows remain method not recorded. Method labels are declared transport, independent of research influence and authorship.

The volunteer authorizes one assignment through a CSRF-protected form before copying. Consent captures the displayed effective legal version, separate AGPL software terms when applicable, a separately sourced public human-input declaration through authorization, UTC time and purpose. The 256-bit secret is returned once in a private supplement to the exact archived research instructions. Only its hash is stored; never archive or export that supplement, grant rows, actor hashes or session capabilities. JavaScript removes the fragment from browser history before the normal CSRF-protected exchange POST. Without JavaScript, an explicit form action keeps the secret fragment out of subsequent redirect URLs; browser-history removal requires JavaScript. No sign-in, runtime AI or external execution service is added.

Task scope binds task/revision, immutable context and nonce; supplementary scope binds kind and approved review target. Current parent visibility, task/snapshot gates, requester requirements and accepted terms are rechecked. Browser handover preserves the original volunteer actor for independent-review checks. Server-injected consent/declaration cannot be replaced by packet fields; the AI independently reports later scientific steering. Operational browser permission, unchanged copying and platform formatting repair alone are not influence.

Check packet is read-only. Recording and consuming a grant share the research row lock and transaction. Accepted and quarantined results consume it once; recoverable validation errors do not. Identical retries find the same result, changed duplicates fail, and reopening a consumed link goes to its existing receipt before expiry/readiness checks. Quarantine remains excluded from accepted evidence and does not apply an outcome. Pending supplementary receipts require the submitting session or scoped grant, and still enforce the public parent and review-target moderation. Public results and admin/audit surfaces show the method; public context explicitly allowlists it without credentials. Grant records are removed before task/parent purge.

The required consent checkbox explicitly confirms authorized resource use, provider compliance and public-publication rights. Its exact statement is captured in new private browser-return consent and its supplement. The shared contributor-resources notice is appended to the displayed Terms and canonical legal bundle even when an operator has saved custom Terms, so effective policy fingerprints change and unconsumed old authorizations require fresh consent. Historical policy snapshots and issued handoff bytes remain unchanged. Participation guidance, task/supplementary prompts and agent instructions repeat the rule. No external account inspection, approval-verification or policing service is added; contributors retain responsibility for permissions, resource use and costs.

These are anonymous self-attestations. A capability transfers permission; it does not authenticate a physical person, model, untouched conversation or scientific truth. Native GET/POST forms support the consent/return path without JavaScript; clipboard and fragment exchange are progressive enhancements. Guided request drafting remains import-and-human-review, with no automatic request publication.
