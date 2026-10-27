-- Additive private authority/identity records. No historical identity or consent backfill.
ALTER TABLE volunteer_sessions ADD concurrent_limit TINYINT UNSIGNED NOT NULL DEFAULT 1, ADD identity_id CHAR(32) NULL;
ALTER TABLE volunteer_assignments ADD claim_key CHAR(32) NULL, ADD worker_state VARCHAR(24) NOT NULL DEFAULT 'not_reported', ADD worker_reported_at DATETIME NULL, ADD UNIQUE KEY session_claim_key(session_id,claim_key);
ALTER TABLE browser_returns ADD revoked_at DATETIME NULL, ADD identity_id CHAR(32) NULL;
CREATE TABLE task_continuations (
 id CHAR(32) PRIMARY KEY, grant_id CHAR(32) NOT NULL, secret_hash CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL, revoked_at DATETIME NULL,
 FOREIGN KEY(grant_id) REFERENCES browser_returns(id) ON DELETE CASCADE, UNIQUE KEY(grant_id)
) ENGINE=InnoDB;
CREATE TABLE research_account_identities (
 id CHAR(32) PRIMARY KEY, provider VARCHAR(80) NOT NULL, account_hash CHAR(64) NOT NULL,
 basis VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, UNIQUE KEY account_identity(provider,account_hash)
) ENGINE=InnoDB;
CREATE TABLE research_actor_identities (
 actor_hash CHAR(64) PRIMARY KEY, identity_id CHAR(32) NOT NULL,
 FOREIGN KEY(identity_id) REFERENCES research_account_identities(id)
) ENGINE=InnoDB;
CREATE TABLE research_result_identities (
 result_id CHAR(32) PRIMARY KEY, identity_id CHAR(32) NULL, owner_actor_hash CHAR(64) NOT NULL,
 FOREIGN KEY(result_id) REFERENCES workflow_submissions(id) ON DELETE CASCADE,
 FOREIGN KEY(identity_id) REFERENCES research_account_identities(id)
) ENGINE=InnoDB;
CREATE TABLE research_review_attestations (
 id CHAR(32) PRIMARY KEY, research_id CHAR(32) NOT NULL, task_id CHAR(32) NOT NULL, task_revision INT NOT NULL,
 owner_actor_hash CHAR(64) NOT NULL, identity_id CHAR(32) NOT NULL, target_digest CHAR(64) NOT NULL,
 policy_version VARCHAR(40) NOT NULL, captured_at DATETIME NOT NULL,
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE,
 FOREIGN KEY(task_id,research_id) REFERENCES workflow_tasks(id,research_id) ON DELETE CASCADE,
 FOREIGN KEY(identity_id) REFERENCES research_account_identities(id),
 UNIQUE KEY review_attestation(task_id,task_revision,owner_actor_hash,identity_id,target_digest)
) ENGINE=InnoDB;
CREATE TABLE task_reauthorizations (
 old_grant_id CHAR(32) NOT NULL, request_key CHAR(32) NOT NULL, new_grant_id CHAR(32) NOT NULL,
 PRIMARY KEY(old_grant_id,request_key), UNIQUE KEY(new_grant_id),
 FOREIGN KEY(old_grant_id) REFERENCES browser_returns(id) ON DELETE CASCADE,
 FOREIGN KEY(new_grant_id) REFERENCES browser_returns(id) ON DELETE CASCADE
) ENGINE=InnoDB;
