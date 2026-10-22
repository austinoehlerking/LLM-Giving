# Requester AI selection

Requester controls use native details/summary dropdowns with labeled checkboxes. Space toggles a checkbox, Enter opens a summary, Tab follows native focus order, and Escape closes the current menu and returns focus. No ARIA menu roles override native checkbox semantics. Each label is at least 44 CSS pixels tall. A no-JavaScript form remains available; users must uncheck Any before choosing specifics.

## Meaning and persistence

New form posts carry ai_selection_version=2, required_models[], reasoning_levels[], and reasoning_or_higher[]. Empty lists mean Any. Any cannot coexist with specific choices. Multiple models are alternatives: any one exact version qualifies. Numeric efforts are also alternatives. Each is exact unless its own or higher checkbox is selected. Ultra has no higher option.

Ultracode is a separate workflow mode restriction, combined with the effort alternatives using AND. Selecting only Ultracode allows any supported actual effort while still requiring Claude Code and a compatible exact model. It does not satisfy an effort minimum, substitute for Ultra or Max, or relax platform planning policy. The summary explicitly separates these dimensions and resolves accepted efforts per selected model. A configuration must leave at least one platform-approved planning model/effort/tool combination.

The server stores version-2 requirements in research.ai_requirements as models, effort choices (level/or_higher), modes and a captured exact-model capability registry. The captured registry prevents future support edits from reinterpreting a saved request. Minimum expansion uses the actual model's supported effort order and requires that the starting effort itself be supported. No cross-model capability ranking is implied.

Migration 014_ai_selection.sql adds nullable agent_metadata.workflow_mode for supplementary provenance. Workflow results keep workflow_mode separately in existing JSON metadata. reasoning always records the actual numeric effort. Old provenance remains null/uncollected rather than inventing a past mode.

Historical single model/mode/effort JSON retains its original exact/minimum matcher. It is not backfilled or rewritten. Saving unchanged legacy controls is a no-op. An explicit change adopts the new schema. Existing snapshot and issued-handoff bytes are untouched. Requirements still lock under the parent row lock after the first workflow packet (including quarantined packets) or supplementary submission. Management shows the locked requirement instead of editable controls. Changed requirements invalidate stale handoffs and browser-return authorizations through existing equality checks.

## Registry and planning policy

The registry in src/workflow-policy.php declares exact IDs and supported numeric efforts/modes. The built-in planning default adds claude-fable-5-1 at Max. Existing explicitly configured administrator policies remain intact; they can add Fable through validated policy administration. Unsupported effort settings, duplicate IDs, unregistered models and a non-Codex Ultra policy are rejected. Existing immutable planning-policy snapshots keep their captured meaning.

Official references refreshed 2026-10-21:

- https://platform.claude.com/docs/en/models/fable-5-1/overview — exact claude-fable-5-1 ID, active availability.
- https://platform.claude.com/docs/en/build-with-claude/effort — Fable 5.1 supports low, medium, high, xhigh and max, with high as its default. It does not support Ultra.
- https://code.claude.com/docs/en/model-config#adjust-effort-level — Ultracode is a Claude Code workflow setting, independent of numeric effort in v2.1.284+. Its CLI shortcut can set xhigh, so contributors must verify actual effort independently. Models must support xhigh for this workflow mode.

The site performs no provider calls or runtime inference. Account availability, installed Claude Code version, organization caps and session configuration remain contributor declarations; preflight must stop on unknown settings or silent model/effort fallback.

## Surfaces and tests

The same rules appear in creation, editable previews, management, admin review, public context, task/handoff preflight and repair prompts. Setup v3 includes arrays for model/effort alternatives and independent mode; valid historical v2 exact/minimum drafts translate to equivalent controls. All import validation finishes before any field mutation, preserves private inputs and clears consent. Mixing old/new selection fields or invalid combinations is rejected.

Focused checks: tests/ai-selection.mjs, tests/ai-selection.php, tests/requirements.php, tests/request-setup.mjs. Integration checks exercise accepted/rejected planning, changed requirements, immutable snapshot bytes, supplementary persistence and locking. Repo-owned browser modes requirements, setup and submission cover keyboard, real touch, accessible names/roles, import/preview/editing, real packet checking/submission, native no-JavaScript creation, three initial viewports and enlarged text. Evidence stays under ignored storage/qa/ on D:.
