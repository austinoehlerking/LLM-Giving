# Session ownership, recovery and review-account separation

Private continuation transfers an existing task's captured authority; a public authorization URL does not. Original owner actor, task/revision, context, nonce, archive and consent remain bound. The original browser or a current exact private session/return grant can issue Continue this reserved task with my AI. Session access additionally proves the current secret hash and matching assignment/grant/research/task/actor; identifiers alone never authorize adoption. Exchange grants only that return capability and never replaces the receiving browser's global actor.

GET /continue/{id}/open is the fragment-exchange form. Its CSRF-protected POST grants scoped access, strips fragments with existing progressive enhancement and redirects without the secret. Native forms work without JavaScript. Reads and copying never claim or renew. /continue/{id} shows permission, task/revision, lease, recorded outcome, draft availability and controller-reported worker state separately. /instructions supplies complete unchanged archived task context and the private procedure only while ongoing permission/lease permit work. Credentials and account references never enter public manifests/context.

A valid capability may recover its receipt/draft after permission expiry or Stop, subject to parent/task/snapshot visibility. Mutation still requires current consent, scope, requirements, model/profile and lease. Another holder cannot be displaced. A revoked continuation also invalidates browser grants obtained through that continuation; direct original authority remains distinct. Revoking the underlying browser-return grant denies recovery through every path.

An explicit human same-task recovery authorization creates a separate single-assignment grant with a new bounded deadline, preserving the original signed scientific identifiers, worker/profile and original consent row. It never retroactively submits old work or extends the old session. Current terms/resources and relevant software rights require fresh confirmation; the original disclosure collection status stays intact. Recovery requests are idempotent. Changed revision/requirements/context availability requires a fresh complete task rather than relabeling old work. Saved-draft transfer to the new grant must be explicit and byte-preserving; scope.recovery_source_grant records its private lineage.

## Concurrency

New sessions separately capture concurrent_limit 1–3, default 1. Historical sessions migrate to 1 without rewriting their captured consent. Total assignments remain a separate consumed allowance. Actor, session and parent locks serialize claims. A request key makes new claims idempotent; each assignment has its own lease, grant and identifiers. One assignment's release/renewal never affects siblings; Stop releases all. Dependencies, visibility, quarantine, profile and model eligibility remain mandatory. Declaring another identity cannot free occupied actor capacity. The actor mutex uses an exclusive duplicate-key UPDATE to avoid INSERT IGNORE shared-lock upgrade deadlocks under simultaneous claims.

The site records worker_state only as an explicit controller report; it does not infer research progress from a reservation or terminate external workers.

## AI-account independence

Separate browser/thread/model/subagent is insufficient. Human account declarations are private self-reports, not provider authentication. Raw account references/passwords/API keys are not stored; the reference is HMAC-bound to a normalized provider and case-normalized private account label. Result attribution lives in private tables, never consent metadata or public packet fields.

/volunteer/review/{researchId}/{taskId} first records a human account declaration, then an explicit KNOWINGLY different-account attestation for the current task revision and exact current target-result/author/builder set. Missing/uncertain separation is ineligible. Different aliases alone do not qualify. Known same-account, same-owner and existing builder conflicts reject even with an attestation. Revisions or changed target/identity sets invalidate prior attestation. Legacy identities remain unknown; a human may explicitly attest actual known separation, never infer it from missing data. Author repairs are not independent reviews.

## Integration APIs

- sr_authority(research, grantId, mutation=false) authenticates current private proof and returns internal binding, decoded grant and status. mutation=true additionally checks ongoing permission and lease.
- sr_binding(grant) returns research_id/task_id/task_revision/context_id/submission_key/archive_id/owner_actor_hash/session_id/grant_id/schema_version. Owner/grant/account fields are INTERNAL, never public manifest fields.
- sr_reauthorize(research, oldGrantId, humanPost) creates only explicit same-task recovery permission, with original private lineage and idempotency.
- ri_review_guard(research, task, ownerActor, identityId) and ri_record_result(resultId, ownerActor, identityId) are the final-admission and private-attribution hooks. Direct/manual workflow submission needs the same guarded hooks under its result transaction. Historical recorded retries must return before new attribution, never backfill identity.
- sr_return_access(grant) validates both the return hash and any originating continuation's current hash/revocation.
- Optional integration: rtx_session_route before session dispatch; rtx_packet_input for unchanged finalized transport; rtx_draft_available(binding), rtx_recovery_url(binding); wf_packet_exception_details for structured errors; canonical vo_formatting_guidance(trustedConsent).

Fresh session, standalone and recovery consent captures influence_policy_version=formatting-only-v1 on the server. Packet flags cannot select it. Historical grants/disclosures and the quarantined synthesis are unchanged. Original configured-launch admission, exact settings, same-worker continuation and active-turn steering remain required; unavailable provider-effective readback is disclosed, not invented verification.

Migration023 is additive. Preserve storage/private/research-identity.key, task-continuation.key and the existing return/signing keys in deployment backups. This developer branch performs no production migration, moderation or deployment.

## Supplementary reviews

The same account rule applies to supplementary Independent review contributions. New contribution authors are recorded in private identity/owner tables in the insertion transaction; historical contributions remain unknown. The exact published target content and author binding determine the separation digest. Known same owner/account rejects even after attestation; missing/uncertain separation rejects. The human account declaration and exact-target checkbox come from form fields outside the AI JSON. Packet-selected identity or attestation fields are stripped and cannot authorize review. The browser return uses its original captured owner and identity, never the receiving controller’s account declaration.

/volunteer/contribution-review/{researchId}/{targetId} records a human account declaration and knowing exact-target separation before opening fresh unchecked browser authorization. The manual contribution form can capture the same human attestation. parse_contribution(post, serverResearchId) receives its parent binding from public/index.php. create_contribution has trailing trusted ownerActor/identityId arguments; direct manual calls default to the current private human state, while private grants supply their original captured pair. Check and recorded retries never insert/backfill author identity. Fresh formatting-only declarations carry category=formatting and nonempty truthful detail under the server-selected formatting-only-v1 policy; stored legacy grants stay unchanged.
