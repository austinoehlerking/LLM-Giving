# Evidence-led research workflow

Every new planning, research, synthesis and independent-review packet carries evidence version 1. The source-controlled standard lives in templates/evidence.txt; src/workflow-evidence.php composes stage instructions, builds source-audit targets and validates packets. Runtime PHP performs no AI inference, network searches or external source retrieval.

## Requirements by stage

| Stage | Required behavior |
| --- | --- |
| Planning | Inspect the brief and previous work; record actual searches/access limits; give each task evidence_needs and stopping_rule. Sources and claims can be empty when no research conclusion is asserted. |
| Research | Map every central conclusion and numerical claim to inspected source passages; record scope, methods, limits, contrary evidence and confidence. Documented unresolved evidence can complete a deliverable. |
| Synthesis | Reconcile disagreements and null findings; cite original sources and current accepted upstream result IDs. Supported/limited findings and inferences require basis_result_ids. |
| Review | Independently inspect underlying sources for every current research/synthesis claim, including contradictory links. Unsupported or unverifiable conclusions require needs_correction and automatically generate targeted corrections. |

## Evidence object

The evidence member is a structured object alongside the existing body, methodology, sources, limitations, acceptance_checks and corrections fields. It is stored in submission metadata and retained in complete immutable context packages. It contains:

- version: integer 1.
- scope and stopping_rule: bounded investigation scope and a concrete stopping condition.
- search_log: up to 30 objects with query, where, result; at least one actual search/access record for completed, inconclusive or verified. Missing tools and failed access must be stated honestly.
- sources: up to 40 records with unique id, title, url, type, access and access_note, plus authors (authors or organization), publication_date, publisher (journal/publisher), doi (identifier or empty), edition, metadata_note and use (used, background, rejected). Unknown attribution remains empty with an honest metadata note; no source inspection is inferred. Types: primary, dataset, review, official, provided, other. Access: full_text, abstract_only, unavailable. External records need an HTTP(S) URL without credentials; provided material may use an empty URL with a precise access_note.
- claims: up to 20 central claims with unique id (legacy is reserved for historical audits) and a statement appearing verbatim in body. Each has kind (finding, inference, proposal), support (supported, limited, unresolved, unsupported), confidence (low, moderate, high), confidence_reason, derivation, limitations, counterevidence, basis_result_ids and links. Inferences/proposals must explain their derivation.
- links: up to 12 relationships per claim, each with source_id, relation (supports, contradicts, context), locator, short exact excerpt and explanation. Unavailable sources have no quotation and cannot support a claim. Source quotations are declarations, not independently fetched text.
- review_checks: server-enumerated target task_id, result_id and claim_id, plus verdict, reason and source_checks. Each source check records source_id, access, locator, excerpt and assessment. Only review tasks may supply these audits.

Supported and limited claims need inspected supporting links. Supported claims require at least one full-text source; abstract-only findings are limited. High confidence requires supported evidence; unresolved findings require low confidence. Terminal research, synthesis and review require central claims. An unsupported claim cannot complete a task; it needs further work or an explicitly revised unresolved conclusion. Progress/blocker/clarification packets can contain empty lists while describing what remains undone.

The ordinary browser packet limit remains 200,000 characters. Per-list and text limits prevent unbounded metadata. Migration 008 widens submission/audit metadata to MEDIUMTEXT so valid evidence packets do not overflow the former TEXT columns. Local setup applies the migration; production migration requires separate authorization.

## Correction behavior

A reviewer cannot submit verified while any current claim audit is missing, any linked source is unaudited, or a conclusion is assessed unsupported/unverifiable. Downgrading an originally supported claim to limited also requires correction. A supported verdict requires full-text inspection of a supporting source; reading a contradictory or context source in full cannot satisfy that requirement. Reviewers may mark a properly bounded unresolved finding limited without inventing positive evidence.

For needs_correction, the server turns failing claim audits into correction entries with criterion_id=evidence-standard, claim_id, reason and required_change. The existing transaction reopens the responsible research/synthesis task and every transitive dependent, increments revisions and clears accepted result pointers. Unaffected research remains accepted. Revised acceptance checks must explain the correction response. Reopened reviews reference replacement result IDs; stale, foreign, duplicate and unknown references fail validation.

Ordinary criterion-based corrections remain supported. Evidence-standard corrections must name an actual central claim in the current accepted result. Check packet uses the same validation as submission and writes no result, task change or audit attempt. Admin approval revalidates new quarantined evidence packets against current targets before applying an outcome.

## Historical records and publication

Existing records are unchanged and explicitly labeled when they lack structured evidence. New reviews receive a legacy whole-result audit target for those records and must document newly inspected sources in their own registry. No historic source access or claim assessment is inferred. Previously quarantined packets without evidence remain historical; their original approval policy is preserved and their missing evidence stays visible.

Public parent visibility, accepted-child selection and context revocation remain mandatory. Evidence helpers enforce the public parent gate and use only current, public, accepted research/synthesis results. No source URL is fetched or executed by the server. All new evidence UI text and links are escaped; executable HTML/JavaScript is not rendered from packets.

## Visual research artifacts

Optional evidence.datasets, evidence.artifacts and evidence.artifact_reviews now support native charts, diagrams, step-based animations and interactive scenario pages. Every artifact links to central claims and source records, typed backing data, assumptions and limitations. Readers can inspect text alternatives and export JSON/CSV/SVG. Failing visual audits generate targeted corrections and reopen affected work. No submitted executable code or remote embedded assets are hosted. See [the visual artifact contract](research-artifacts.md).

## Resources and mandatory supporting material

Every study exposes /research/{slug}/resources from its main heading and section navigation. DOI/source URL identities consolidate within a dataset edition while retaining each contribution's access, attribution, claim passages, original table links, input locations, derived calculation bundles and artifact links. Accepted visual progress links to the retained specification and backing data on its eligible result, while interactive previews and visual exports retain their completed-result gates. Eligible superseded records are linked separately; removed/quarantined/rejected evidence and revoked snapshots never supply the catalogue or current downloads. Historical missing metadata is labeled, never fabricated. Supplementary source registries use the same validation and current moderation/supersession gates.

Every new evidence object also requires supporting_materials={version:1,work_performed,items,files}. work_performed is narrative, preparation, executed_analysis or tool_build. Assess all twelve categories: sources, inputs, units, formulas, code, software, methods, assumptions, outputs, tables, intermediate_results, failed_approaches. Each item has category, status (supplied, not_applicable, restricted, missing, oversized), details, refs and access_instructions. refs must resolve to retained narrative fields, evidence records/files or supporting.files/ID. A literature review can explain why code is inapplicable. Computational/tool records enforce their applicable retained references and actual software versions; they cannot complete with an omitted source/input/code/output requirement. Missing/oversized statuses require progress, not completed/inconclusive/verified. Restricted material requires sources and methodology references plus access instructions, preserving legal reproduction without an unauthorized copy. Useful partial work and documented failed approaches must remain available.

General supporting files are inert UTF-8 text with exact SHA-256, safe plain filename, category and redistribution rights: at most 12 files, 30,000 bytes each, 80,000 bytes total. Specialized file budgets still apply. All new packets have a 200,000-byte compact-JSON limit as well as the browser's raw payload limit. Oversized required content is reported, never silently truncated. Large/binary input can use a legally accessible original repository with precise attribution, version/location and reproduction instructions; missing necessary material cannot be disguised as not_applicable. Contributor permissions, installations and purchases remain their own responsibility.

material_reviews enumerate every current accepted research/synthesis result, including historical results without an inventory. Each audit identifies task_id, result_id, verdict (complete, needs_correction, unverifiable), reason and checked_refs. A complete audit must inspect all supplied/restricted-access references and body/methodology/sources. Missing/unverifiable materials require needs_correction and generate a supporting-materials correction, reopening the responsible task and dependents. Applicability and scientific sufficiency require independent judgment; automated checks do not prove truth or actual external access.

Calculation inputs now identify source_id, source_locator, extraction, provenance (original/derived) and derivation when derived, alongside the retained file locator/value/unit. Observed/derived datasets require source_locations for each source. Actual software versions are retained in calculations/tool_builds as software:[{name,version}], with unknown versions disallowed on completion. Existing stored records retain their original schema and honest missing-metadata labels.

## Verification

node tools/qa.mjs resources verifies the synthetic trace original table row mass=2 kg → inputs.json mass_kg → retained 2*1000 formula → outputs.json mass_g=2000 g → claim c1, along with current/partial downloads, child moderation, withdrawal and snapshot revocation. It captures initial viewport screenshots at 1366x768, 390x844 and 375x667, enlarged text and no-JavaScript navigation.

node tools/check.mjs runs tests/resources.php and tests/evidence.php along with the existing integration, workflow, quarantine and immutable-handoff checks. node tools/qa.mjs workflow exercises missing source support, expanded evidence records, source navigation, escaped source text and automatic claim corrections through the real browser forms. It checks the initial viewport before interactions at 1366x768, 390x844 and 375x667, plus enlarged-text reflow. All evidence stays under storage/qa on D:.
