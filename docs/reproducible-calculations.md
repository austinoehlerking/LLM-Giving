# Reproducible-calculation pilot

Implemented locally for the existing rice AWD climate research. This is an arithmetic/workflow pilot, not a new field experiment, a general climate simulation or a scientific certification.

## Request and planning

The submit form uses one **Calculation help needed** choice. **Maybe — if useful** is the default: it permits useful calculations, modeling and supporting analysis without requiring a computational task or deliverable. **Run calculations and share reproducible results** requires at least one research task with computational: true. **Prepare inputs or review results only** explicitly prohibits calculation execution. **None specified** records no execution permission. A Maybe request can complete through ordinary evidence-backed research, synthesis and independent review without calculations when its deliverables do not require them.

Complete context exports calculation_policy with separate allowed, required and preparation_only fields. The historical computational_models boolean remains for API/storage compatibility and means permission, not a requirement. The redundant form checkbox is removed. Old checkbox-only callers and existing records retain their explicit restrictions and required-execution behavior; contradictory submitted choices are rejected. No existing JSON or archived context is rewritten. Permission never authorizes installation, resource use, purchases, deployment, physical experiments or access outside a contributor's own permissions. The PHP server performs no model execution, inference or external source retrieval.

## Calculation records

`evidence.calculations` contains up to four records on a permitted research task, including optional supporting calculations when computational: false. Optional calculation records receive the same file, checksum, unit, checkpoint and independent reproduction checks as required calculations. Ordinary results without calculations leave the list empty and checkpoint null. Each records status (`proposed`, `executed`, `failed`), method, environment/software versions, command, actual execution note, limitations, source IDs and claim IDs. Each terminal calculation must have executed; unexecuted work uses progress/blocker/clarification instead. Failed work can retain a failure log but cannot claim successful output.

Executed calculations require input, code and output files, numerical quantities and unit checks. Proposed/failed progress retains only work actually available, an actionable checkpoint and explicit missing-material disclosures; never invent absent code or execution. Failed work requires a retained failure log. Every input identifies its inspected source, precise extraction location, extracted value/units, original/derived provenance and derivation when derived. Abstract-only inputs retain limited source support. A software list records actual runtime/dependency name and version; missing versions block completion. Files are exact UTF-8 strings with tool-computed SHA-256 hashes, plain unique filenames and input/code/output/environment/log roles. Quantities identify an input/output file of the correct role and a locator. Files remain inert text inside the existing evidence metadata and immutable packages. No executable upload type or arbitrary server execution was added.

Limits: eight files per calculation, 30,000 bytes per file, 100,000 file bytes per packet, 30 inputs/outputs/unit checks per calculation, and a 200,000-byte compact-JSON packet limit plus the browser raw-payload limit. Required oversized content is reported in progress, never silently truncated. Avoid putting private data or secrets in any retained file. Public content remains subject to source rights and the contribution's recorded license; hashes do not establish copyright ownership or scientific accuracy.

Automatic consistency checks cover identical declared units, mg/g/kg scaling with matching suffixes, CH4-C to CH4 (16/12) and N2O-N to N2O (44/28) for the pilot's annual per-hectare units. They reject incompatible time boundaries and inconsistent declared values. Comparison tolerance is absolute 1e-9 plus relative 1e-6. Other conversions explicitly use `review_required` with their derivation; they are not labeled automatically validated. Source correctness, omitted quantities, whether a run actually happened and scientific appropriateness still require review.

## Interruption and resumption

A computational progress/completed/inconclusive packet includes `calculation_checkpoint` with `resume_from_result_id`, completed work, remaining work, exact next action and required tools. Progress is accepted without completing the task. The task shows **Resume saved work** in its first viewport, and fresh prompts name the progress result and retain all input/code/output/checkpoint files in complete context.

A resumed packet must reference accepted progress from the same task revision in its supplied immutable context. Foreign/stale references are rejected. Another contributor/session can resume; the original records are retained. A fresh packet uses a new signed handoff/submission key; identical retries use the existing idempotency contract.

Accepted result pages expose **Calculation files** in their first viewport and a detailed calculation section. `/research/{slug}/results/{result}/calculations/{calculation}/bundle.json` downloads an inert JSON bundle. Downloads enforce parent publication, accepted child status, public task visibility, current round/revision and non-revoked context. Quarantined, foreign, removed and superseded work cannot bypass those gates. Current-revision accepted progress remains downloadable for resumption until the task has a current completed result. Superseded progress remains readable as eligible audit history, with downloads withdrawn. Source tracing and material inventories are available in each study’s Resources & sources section; independent material-completeness audits supplement calculation reproduction.

## Independent reproduction and corrections

Review templates enumerate every current accepted calculation. Each audit reports the actual method/environment/run log and observed output values/units. `reproduced` requires every output quantity to match; missing, stale or inconsistent records cannot receive `verified`. `needs_correction`/`unverifiable` generate a calculation-specific correction and reopen the responsible task plus transitive dependents. Changed results require a new synthesis and review. Quarantine approval revalidates normalized calculation records.

A fresh process using the same code is computational reproduction, not an independent implementation, field replication or proof that assumptions are correct. Review identity, actual execution and model provenance remain self-reported. Existing source/claim, visual artifact and human-influence checks remain in force.

## Climate pilot and local reproduction

Inputs come from the existing climate calculation: LaHue et al. 2016 Table 3 annual 2013 WS-C/WS-AWD gas means, paired Table 2 yields, and section 2.7 source-paper GWP100 factors 34/298. Source: https://linquist.ucdavis.edu/sites/g/files/dgvnsk6581/files/inline-files/LaHue-et-al.-2016-AEE.pdf

The pilot preserves this historical factor convention to reproduce the paper; it does not present it as the latest climate-accounting factor set. Rounded means reproduce annual totals within 0.5%. Annual gas budgets allocated to one harvest are explicitly separated from the paper's growing-season yield-scaled metric. Sensitivity inputs are illustrative; variance/covariance, confidence intervals, broader lifecycle terms, adoption and carbon-credit eligibility are not inferred.

```powershell
node tools/climate-model.mjs --input tests/fixtures/climate-input.json --out D:/Codex/LLM-Giving/storage/research/manual-baseline --stop-after baseline
node tools/climate-model.mjs --input tests/fixtures/climate-input.json --out D:/Codex/LLM-Giving/storage/research/manual-complete --resume D:/Codex/LLM-Giving/storage/research/manual-baseline/checkpoint.json
```

A downloaded bundle can be extracted without executing its contents:

```powershell
node tools/unpack-calculation.mjs D:/path/climate-bundle.json D:/path/new-calculation-directory
```

The extractor validates all filenames, sizes and checksums before writing, refuses an existing output directory, and never runs submitted code. Inspect extracted code and the recorded command/environment before local execution. Use D: output paths. The archived model checks both input/code hashes and recomputes the saved baseline before resuming. It works from paths containing spaces.

## Checks and evidence

```powershell
node tools/dev.mjs setup
node tools/check.mjs
node tools/qa.mjs calculations
```

`tools/check.mjs` runs actual model execution/resumption, input/checkpoint tampering, a gram/kilogram error, sign-changing sensitivity, path-with-spaces and safe extraction checks, followed by server calculation workflow tests. The browser calculation mode exercises required initial viewports, preview, resume/copy, invalid/valid packet checks, real result views, no-JavaScript form availability and enlarged text.

If the primary installed Edge route cannot start, the project-owned HTTP fallback is:

```powershell
node tools/calculation-pilot.mjs
```

It uses its own loopback PHP server, real forms/CSRF, separate contributor/reviewer sessions, moderation, actual downloaded/verified files, fresh-process resumption, independent reproduction and targeted corrections. It stops its own server, removes its temporary QA administrator and retains clearly labeled local pilot topics for inspection. Original climate research is not replaced. Workflow-test AI metadata is explicitly synthetic; the calculation processes actually execute.

Evidence is under `storage/qa/calculations/` on D:. `http-report.json` records pilot URLs; `model-checks.json` and `integration-checks.json` record checks. These are private local development artifacts, excluded from source publication. See the local handoff report for executed browser coverage and limitations. No production access, push, deployment or dependency installation is part of this pilot.

## Model-derived arithmetic without tool execution

A model can retain a simple source-based derivation without pretending to have run software. New evidence may include derivations and derivation_reviews alongside the original calculation fields. Each derivation uses integer version 1 and type model_derived. It is explicitly not executed or computationally reproduced. Research and synthesis may include it even when the request disallows computational execution; explicit brief restrictions still apply. It cannot complete a task requiring executed calculations, retained code, software tests or computational reproduction.

A derivation has exactly version, type, id, title, claim_ids, source_ids, inputs, formula, steps, outputs, assumptions and limitations. Retain inspected-and-used original sources; each numeric input records id, value, unit, source_id, source_locator and extraction. Outputs record id, value and unit. Formula is inert text; steps retain the actual model-worked reasoning. Assumptions and limitations explain scope and uncertainty. Missing source extraction, units, formula, steps or limitations is invalid. Records reject command, files, software, hashes, execution status and unknown fields rather than fabricating an execution environment. The server validates structure/references and reported output consistency, never evaluates the formula or authenticates arithmetic/source truth.

Up to four records are allowed. Each has 1–30 input/output quantities and 1–20 worked steps. Quantities use finite JSON numbers within existing evidence bounds and explicit units. Formula is 3–3000 characters, worked steps 10–2000 each, title 5–150, assumptions/limitations 20–3000, extraction 10–2000 and source location 3–500. Current claim IDs and cited source IDs must resolve; the linked central numerical claims remain verbatim in the result body.

All twelve supporting-material categories still apply. work_performed=narrative is appropriate when no computational execution occurred. sources refs include evidence.sources and methods refs include methodology. Each derivation adds evidence.derivations/ID to inputs, units, formulas, methods, assumptions, outputs and intermediate_results. These are retained references, including /inputs/ID and /outputs/ID. Truthfully inapplicable code/software use refs=[]; no extra files or checksums are required merely for model reasoning.

Independent derivation reviews use version, task_id, result_id, derivation_id, verdict, method, worked_steps, observed_outputs and limitations. Verdict is assessed, needs_correction or unverifiable, never reproduced. An assessed result describes the actual independent checks and worked reasoning and reports every output ID/value/unit consistently. Verified requires every current accepted derivation assessed, plus the original source/claim/material audits. An incorrect/unverifiable derivation produces derivation-standard corrections and reopens responsible work and its dependents. Executed calculations still require their original calculation_reviews and genuine fresh-process reproduction. Agreement between outputs or AI answers is not proof of scientific correctness or execution.

Normalized versioned records persist in result metadata and current context; old missing fields mean no structured derivation was collected. Existing snapshots, handoff archives and historical execution records are not rewritten. Task and repair prompts explain both paths and give current review targets. Public result/resource views label the distinction and link a read-only /results/RESULT/derivations/ID/bundle.json record with format llm-giving-derivation-v1. Existing accepted-result, parent, task revision, snapshot and transitive source visibility checks apply to every download. No migration, AI call or submitted-code execution is introduced.

Focused coverage: php tests/derivations.php and php tests/calculations.php; model-derived browser coverage is included in node tools/qa.mjs calculations. These are local synthetic developer fixtures, not the genuine phone/Cloud acceptance run. Cloud browser compatibility and live new-feature acceptance require their separately verified external prerequisites and authorized deployment.
