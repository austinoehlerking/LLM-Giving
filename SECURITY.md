# Security policy

Do not treat public approval as a security or factual-verification guarantee. See the deployment and QA documentation for the scope of validation.

## Security properties

Unpublished research must never be returned to anonymous visitors. Parent withdrawal hides every linked public page, context endpoint and file. Public context must never contain requester email, credentials, admin notes or private exception rationales.

Administrative writes require authenticated sessions and CSRF. Requester management is scoped to one immutable research ID. Recovery requires possession of an emailed secret; management tokens are high-entropy hashes at rest, fragment-delivered and POST-exchanged. Rotation invalidates existing scoped sessions.

All content is untrusted. User HTML and Markdown are displayed escaped. Strict CSP, download-only file serving, validated file types, image re-encoding, prepared SQL, rate limits and safe error responses are baseline controls. No user URL is fetched server-side; no submitted content is executed.

## Reporting

Report potential vulnerabilities privately to **austin@oehlerk.ing**, including the affected route, expected behavior, minimal benign reproduction, and impact. Do not put secrets, private research, or exploit details into public feedback or a GitHub issue. Do not test against production without authorization.

## Validation

tests/integration.php covers core service and data boundaries with benign attack strings. tools/qa.mjs exercises HTTP/browser flows and hostile uploads. See docs/qa-report.md for actual evidence. Runtime isolation belongs to the volunteer; a prompt wrapper alone cannot prevent prompt injection.

## Limits

Manual moderation is not antivirus. PDF signature screening is conservative and incomplete; configure CLAMSCAN_PATH where available and validate the scanner before production. File mail is for local development. No independent penetration test, concurrency/load certification or production hosting security audit is claimed.
