# Guided AI request setup

The submission form offers **Copy AI prompt to assist setup** before the public fields. The locally composed prompt starts with the exact quick/in-depth choice, uses the current public draft, and reveals **Paste setup prompt** for the returned JSON. The requester reviews the filled fields, confirms fresh terms and submits for normal approval. Filling never submits or approves a request; volunteer AI browser-return authorization is a separate workflow.

## Conversation contract

- Ask “Would you like a quick setup or an in-depth guide to help develop your research request?” first, alone. Ask “What research topic or question are you interested in getting help with?” only when it is not already supplied.
- Quick mode has no follow-up for a clear topic and at most two essential clarifications for real ambiguity. In-depth mode offers relevant help one short question at a time and stops whenever the requester is satisfied.
- Do not ask routine audience, decision, licensing, budget, computing-capacity, classification, model or optional-reproducibility questions. Infer categories/tags and straightforward field mapping; leave unknown optional facts empty. A software deliverable needs an actual request or genuine ambiguity.
- Preserve the topic and only supplied/accepted scope. The exact regression topic is **Effects of plastic pollution on the biological carbon pump in the deep ocean**. Do not turn it into a global estimate, future projection, modeling requirement or reusable-software project.
- Before JSON, repeat only the topic/accepted details and provide the separate copy/paste, review, consent and approval instructions from the template. Wait for **Ready**. Then return one complete JSON code block, without surrounding prose, using every current template key.
- With JavaScript disabled, the same depth/topic rules apply, but a clearly labeled manual-entry override explains the review/submission steps before Ready and returns a labeled brief for manual entry instead of JSON.

## Import and requirements contract

Conversation revision 4 retains the versioned **llm-giving-request-setup-v3** JSON format and compatible v2 import. The prompt includes exact enabled categories, current model registry/planning policy, field limits and public draft only. Copied prompts omit private identity/contact details, attachments, consent and fork capabilities. Pinned inherited background is data, not the new question or new results.

The importer validates the entire allowlisted object before changing any field. Malformed, incomplete, oversized or protected data leaves the draft unchanged. A single complete JSON code fence is accepted; truncated output is rejected. Private fields and selected files remain in the form, consent is cleared for fresh review, and import never submits. Values are assigned through native field properties, never executable HTML.

Maybe calculations permit useful work without requiring it; explicit preparation-only, unspecified and required-execution preferences remain distinct. Unknown setup is **None specified**. No mandatory external tools suppresses hidden software values while allowing useful volunteer-chosen tools. Preserve supplied model alternatives and the independent Ultracode/effort contract. Applicable supporting materials are required for work actually performed; do not invent code, executions, dependencies or scientific success criteria. Contributors remain responsible for permissions, purchases and installation authorization.

Raw prompt/paste textareas are unnamed and stay out of submitted FormData. Clipboard denial opens/selects the complete prompt for manual copying. Server validation, moderation, immutable evidence and existing research workflows remain authoritative. No runtime AI call, new dependency, endpoint, persistent browser storage or database migration is added.

## Verification

Run the repo-owned setup/check/browser routes. Template/import assertions are structural coverage, not conversation evidence. Conversation QA must use the exact prompt copied from the actual UI, retain real model responses to controlled user turns, inspect semantics, import the actual final output and verify fresh consent plus normal approval. Cover the exact quick plastic topic, a prefilled topic, genuine ambiguity, an explicit tool request and an in-depth finish. Archive prompt hashes, raw turns, outcomes and screenshots under ignored storage/qa on D:. Never fabricate transcripts or relabel an unexecuted case as passing.

## Historical initial-helper QA (before the walkthrough redesign)

- `node tools/dev.mjs setup`: local application/database initialized successfully; no new migration.
- `node tools/check.mjs`: the existing check suite passed. The final focused prompt/import suite passes 40 checks; final template/UI refinements also passed PHP/JavaScript syntax validation.
- `node tools/qa.mjs setup` and the required baseline `node tools/qa.mjs`: primary browser routes attempted; installed Edge exited during launch in the Windows sandbox (browserType.launch / crashpad TransactNamedPipe), before page interaction. No elevated launch or dependency installation was attempted.
- Connected in-app browser against http://127.0.0.1:8086/submit supplied real interaction and visual evidence. Copy was verified with a real Ctrl+V into the local paste field because the connector clipboard-read surface returned a stale value. The pasted text matched the generated guided prompt.
- Verified current-public-draft inclusion and private-email/attribution omission; rejected protected terms without partial changes; imported research and research-tool drafts; checked category/model/reasoning/checkbox values, required tool fields, preserved private inputs and cleared consent; opened the existing editable preview; confirmed injected source text remained inert with zero executable elements.
- Captured viewport-only screenshots and unobscured action rectangles before interaction at 1366×768, 390×844 and 375×667. Copy/submit, project/title/question and the revealed Fill request fields action fit in their relevant first viewports. Shortened helper text fixed an initial open-panel overflow.
- Read-only real-page snapshots from `tools/setup-assist-render-fallback.mjs` checked script-free manual controls (zero script elements) and 200% body-text reflow at a requested 683×384 viewport (browser reported 684×385). No document-level horizontal overflow. This is a CSS/script-removal simulation, not a native disabled-JavaScript context or browser-toolbar zoom.

Evidence lives under storage/qa/setup-assist: unit-checks.json, iab-browser-evidence.json and iab-*.png. Source changes were inspected against pre-edit copies under storage/qa/setup-assist-before because this directory has no Git working tree. No real research request was submitted by the helper QA, and no production access, deployment, push or paid AI call occurred. Standalone Edge automation remains blocked by its sandbox launch failure; connected-browser checks are the recorded fallback.