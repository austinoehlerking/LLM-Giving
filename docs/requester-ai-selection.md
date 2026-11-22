# Historical requester AI selection

Earlier requests could store a model allowlist, exact or minimum reasoning levels, and a workflow mode. The original values in `research.ai_requirements` and issued snapshots are retained for audit. Nested object key order remains immaterial when comparing historical records. List ordering and value types remain significant.

Request authors specify research capabilities and concrete dependencies through the normal request form. Setup JSON v4 omits the former model fields. The importer accepts v2 and v3 drafts and discards their retired model, effort and mode fields. New requests store the migration's empty default.

The legacy requester management action still authorizes the session and checks CSRF. It returns to the request without changing the request, its snapshots or its audit trail. The local planner's operator configuration and actual model or reasoning provenance on submitted work are separate from requester eligibility.
