# Restricted Codex Cloud volunteer setup

**Blocked as of 2026-10-24.** Codex Cloud currently documents computer/browser use as unsupported. LLM.Giving requires interactive browser controls to exchange private authorization, reserve work, check and submit packets, and inspect receipts. Search or an HTTP fetch does not establish that capability. [Official Cloud environments](https://learn.chatgpt.com/docs/environments/cloud-environments#current-limitations)

The [version 1.0.0 package](../volunteer/codex-cloud/README.md) supplies reviewable settings and audit instructions. No LLM.Giving Cloud environment has been configured, published or tested by this package. Actual environment/task identity, provider revision, model/effort, source access, phone launch and accepted browser submission are unverified. No working Cloud route is advertised. Do not authorize a live volunteer session or copy its private handoff to Cloud while the browser prerequisite remains unresolved.

## One-time preparation

An environment owner needs an owner-designated private published Codex Cloud environment accessible to the tester. The smallest proposed capability change is supported native interactive browser control in that same Cloud task. Publishing alone cannot resolve the gap. Browser-tool installation, Work/connected-desktop substitution and an HTTP/API submission path are outside this setup.

Official preparation happens on web/desktop under **Settings → Codex Cloud → Environments → Create environment**; mobile selects a published environment. Review before **Publish**; later changes need **Republish** and a fresh task. Keep **Who can use: Only me**. Never publish individual session credentials. These are provider UI instructions, not evidence that an environment was created. [Official environment guide](https://learn.chatgpt.com/docs/environments/cloud-environments)

For a future supported environment, use the [configuration checklist](../volunteer/codex-cloud/setup.json) and [initialization instructions](../volunteer/codex-cloud/initialization.txt). The JSON is an LLM.Giving specification, not an OpenAI import/configuration API. No installation/start commands, services or keys are needed. If a repository is required, select an owner-approved minimal instructions repository containing this package; do not create one silently or run the application repository's development setup. Review any automatically proposed installation before continuing.

Use Custom domains only with llm.giving over HTTPS443 plus individually approved source/search hosts and necessary redirects. Record the final host list. Verify actual search and browser traffic separately; the VM policy may not govern every provider tool. Domain access supplies neither site permission nor a browser. Do not add production/admin access, personal files, VPN or unrestricted access as a workaround.

The restrictions govern volunteer research: accessible source search/reading, synthesis and model reasoning/arithmetic are allowed. Code execution, calculators, spreadsheets, scientific software, research-tool installation/building and computational reproduction are excluded. Instructions and LLM.Giving server eligibility do not prove a provider sandbox. Contributors retain responsibility for permissions, resource use and costs; no purchase/installation authority is granted.

## Initialization and session contract

Run only an owner-authorized capability audit using the credential-free initialization text first. Record observed native source search, full-source reading and interactive forms independently. If browser control is absent, stop without claiming work or requesting a live handoff. Never infer actual model/effort from a task's requested settings, the package, a controller or the desktop agent.

When a future package revision has verified native browser support, the existing volunteer setup can preselect **Restricted web research** at /volunteer?session_profile=restricted_web_v1. The validated field is session_profile=restricted_web_v1; a GET only configures. Review scope/settings and give fresh consent before copying **Copy orchestrator handoff** privately into the prepared Cloud task. Never bake session/return links into setup files, images, variables/vault, shared logs, screenshots or public evidence. General sessions keep their existing behavior. See the [server session contract](volunteer-sessions.md) and [orchestrator contract](volunteer-orchestrator.md).

Use the actual complete generated handoff and packet schema. Read all deliverables before work. Release/skip hidden incompatible software/execution dependencies without declaring the scientific study blocked. Optional Maybe calculations do not force execution. Use metadata.evidence.derivations with version=1 and type=model_derived, separate from executed calculations. Preserve source inputs/locations, units, formula, worked steps, result, assumptions and limitations. Independent derivation_reviews assess the reasoning; they do not claim computational reproduction. Do not invent code/files/software/checksums or use derivations to satisfy required execution. Preparation/review-only excludes external execution; explanatory non-executed derivations remain permitted unless an explicit brief restriction forbids them. See the [calculation contract](reproducible-calculations.md).

The existing session controls, 60-minute task reservations, explicit renew/release, original contributor identity, independent-review rules and Stop remain authoritative. Session duration does not extend a reservation; recopying does not renew. Check packet records nothing. Only inspectable recorded receipts prove submission, and quarantined/awaiting-admin-review is not accepted. Preserve signed identifiers and recover an existing receipt after a network interruption before retrying.

## Separate capability and acceptance evidence

| Check in an actual fresh Cloud task | Required evidence; current status |
| --- | --- |
| Identity/configuration | Private environment ID, published revision/setup version, Cloud task identity, observed model/effort or unavailable; NOT TESTED |
| Source search | Native search with actual source results; NOT TESTED |
| Source inspection | Accessible primary page, observed passage and precise location; NOT TESTED |
| Browser operation | Native controls for cookies/CSRF, private fragment exchange, full JSON entry and explicit reservation actions; DOCUMENTED UNSUPPORTED / NOT TESTED |
| Actual phone start and return | Phone OS/app version, published environment selection, same Cloud task reopened; NOT TESTED |
| Validation and recording | AI-authored synthetic research, recoverable omission repaired in browser, Check packet with no stored result, Submit, accepted stored result independently confirmed, receipt recovery without duplicate; PENDING |

The acceptance destination is https://llm.giving with isolated labelled synthetic records. New-feature live acceptance waits for a separately human-authorized deployment of the exact candidate and genuine Cloud browser capability. This package authorizes no deployment or research submission. Record exact application commit/tree, package version, environment revision, real timestamps, human actions and sources. The AI must author research itself using allowed capabilities; fixture-generated research and a precomputed packet cannot prove acceptance. A resized desktop, Work or connected computer is not an actual phone-started Cloud run. [Official mobile instructions](https://learn.chatgpt.com/docs/mobile)

Keep per-run secrets private, outside all public examples. Redact screenshots without claiming unobserved controls or concealing failures. Record blocked/unavailable source access honestly and do not fabricate metadata or inspections. Preserve partial work and explain expired/conflicting reservations; do not replace signed identifiers. No receipt means no proven submission. Stop the website session and external AI separately.

## Developer verification

    node tests/cloud-volunteer-setup.mjs
    node tools/source-check.mjs

The first command validates the reusable package, local links, configuration boundaries and credential exclusions, including rejection cases. Neither command runs Cloud, visits production, installs tools or proves a live submission. This documentation/package changes no application UI, so application viewport QA belongs to the integrated guide/profile change. Independent integrated QA occurs once after the complete reviewed batch is merged and closed.
