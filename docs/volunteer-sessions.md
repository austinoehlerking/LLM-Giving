# Volunteer sessions — implementation contract (in progress)

This feature depends on the shared reservation service and manual pickup/browser-return integration. It is not available through the public application until those integrations and their checks pass.

The session is private, anonymous bounded permission, distinct from a task reservation. Default allowance: three assignments and sixty minutes, always one active task. Editable bounds: 1–20 assignments and 5–240 minutes. Consent is initially unchecked and captures the exact current legal version, an independent human-input declaration, authorized resource use and optional separate software consent. Missing software consent excludes tool projects. No API keys or AI account credentials are requested.

Scope is all eligible work, one existing category, or one currently public research identity. A session never authorizes purchases, installation, unrelated work or account access. The platform coordinates work without invoking AI.

The private exchange link uses a fragment-only random 256-bit credential. Only its SHA-256 is retained. Session read access is granted by explicit CSRF-protected exchange. The original volunteer actor remains fixed in the database across anonymous browser changes. Session identifiers and credentials must stay out of public context, handoff archives, activity exports, analytics, packets and research metadata. Parent/child visibility must be rechecked before private dashboard links expose research or results.

Derived states: active, limit_reached, stopped, expired, terms_changed. Limit exhaustion prevents pickup, while an already assigned task may finish before the absolute deadline. Exact expiry is denied at the deadline, not one second afterward. A stop or policy change prevents new claims, renewal and submission. Existing receipts remain recoverable through the private session after expiry/stop. Starting a new session always requires fresh human authorization.

Pending dependency integration: atomic queue/reservation/assignment counting, reservation release at stop or session expiry, same-actor reviewer separation, authorized return binding, dashboard, explicit session actions and browser QA. This document will be finalized with exact routes and lock ordering after the shared contract is available; it does not invent a replacement reservation API.
