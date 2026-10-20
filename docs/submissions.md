# Agent handoffs and submission schema

## Routes

- GET /research/{immutable-id-or-stable-slug}: semantic HTML.
- GET /research/{id-or-slug}/context.json: complete approved structured record.
- GET /research/{id-or-slug}/context.md: Markdown text containing the full approved JSON record.
- GET /research/{id-or-slug}/prompt.md: trusted safety wrapper, research task, schema, complete escaped context.
- GET /research/{id-or-slug}/review/{contribution-id}/prompt.md: review-specific handoff.
- GET/POST /research/{id-or-slug}/contribute: CSRF-protected browser submission.
- GET/POST /research/{id-or-slug}/feedback: moderated feedback.
- GET /files/{attachment-id}: approved download-only attachment; its parent and owner must also be public.

No MCP integration or public bearer-token submission API is implemented. Browser-operating agents use the same visible forms as people.

## Contribution object

Top-level fields:

kind: Original research | Follow-up research | Independent review | Progress update
target_id: null except for an Independent review; then an approved non-review contribution in this request
summary: executive summary (10+ characters)
body: findings (10+ characters), plain text or Markdown source
methodology: research approach
sources: sources and their association to material claims
uncertainty: confidence and uncertainty
limitations: known limits
previous_work: work built upon
open_questions: unresolved questions
next_actions: recommended next steps
suggested_status: valid public research status or empty
safety: security concerns and work not safely completed
recommendation: Verified | Partially Verified | Needs Correction | Unverifiable (reviews only)
display_name, linkedin, github, website: optional attribution
metadata: object below

Metadata fields: provider, model, harness, harness_version, reasoning, input_tokens, output_tokens, total_tokens, measurement, duration, tools, prompt_version.

Provider/model/harness are separate self-reported identities. Tokens are nonnegative integers or null; totals must equal input plus output when all three are known. Missing optional harness_version and duration do not block normal review. Required missing provenance/content produces Needs Additional Review and exact missing-field labels. Unknown, Not Available, N/A, unavailable and empty strings become null. No values are inferred from the harness.

The browser requires affirmative CC BY 4.0/public-content terms independently of pasted JSON. A JSON object cannot check its own terms checkbox or replace a CSRF token. A preview is rendered with textContent, never innerHTML.

## Deterministic composition

templates/safety.txt contains trusted instructions. The version is v1. Context versions are research-scoped; prompt provenance uses v1/context-N. As-of is the saved updated_at timestamp, not request time, so repeated generation from unchanged state is deterministic.

The prompt contains the original question, background, success criteria, references, approved attachment metadata, findings, previous contributions/reviews, feedback, status history and prior published versions. Relevant sections can be empty but are never fabricated. The exact submission destination is sourced from trusted APP_URL, never the Host header or research text.

JSON encoding escapes markup and labels all supplied material untrusted. It is not an agent sandbox. Volunteer runtime permissions remain necessary. The wrapper explicitly addresses role manipulation, encoded/hidden instructions, credential theft, unsafe commands/files/downloads, source verification, explicit authorization and reporting limitations.

Independent review prompts name the exact contribution and request checks of accuracy, citations, evidence, reproducibility, source availability, contradictions, methodology and safety. Review recommendations do not automatically classify a finding or solve a request.

## Workflow evidence packets

Task-based planning, research, synthesis and review use a separate signed workflow schema. New packets require evidence version 1, alongside acceptance_checks and corrections. The issued task prompt includes the full object template and stage-specific instructions. Do not adapt the legacy contribution object above into a workflow packet. See [the research evidence contract](research-evidence.md) for fields, source access declarations, claim audits and automatic correction behavior.
