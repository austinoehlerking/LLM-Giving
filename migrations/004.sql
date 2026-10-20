CREATE TABLE workflow_projects (
 research_id CHAR(32) PRIMARY KEY, round_no INT NOT NULL DEFAULT 1, blocker_id CHAR(32) NULL,
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE workflow_tasks (
 id CHAR(32) PRIMARY KEY, research_id CHAR(32) NOT NULL, round_no INT NOT NULL, task_key VARCHAR(40) NOT NULL,
 kind VARCHAR(30) NOT NULL, title VARCHAR(180) NOT NULL, objective TEXT NOT NULL, deliverable TEXT NOT NULL, acceptance TEXT NOT NULL,
 dependencies TEXT NOT NULL, state VARCHAR(30) NOT NULL, revision INT NOT NULL DEFAULT 1, accepted_id CHAR(32) NULL, created_at DATETIME NOT NULL,
 UNIQUE KEY task_parent(id,research_id), UNIQUE KEY round_key(research_id,round_no,task_key),
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE workflow_snapshots (
 id CHAR(32) PRIMARY KEY, research_id CHAR(32) NOT NULL, round_no INT NOT NULL, context_version INT NOT NULL,
 package_json LONGTEXT NOT NULL, sha256 CHAR(64) NOT NULL, public_allowed TINYINT NOT NULL DEFAULT 1, created_at DATETIME NOT NULL,
 UNIQUE KEY snapshot_parent(id,research_id),
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE workflow_submissions (
 id CHAR(32) PRIMARY KEY, research_id CHAR(32) NOT NULL, task_id CHAR(32) NOT NULL, snapshot_id CHAR(32) NOT NULL,
 nonce CHAR(32) NOT NULL, payload_hash CHAR(64) NOT NULL, actor_hash CHAR(64) NOT NULL, display_name VARCHAR(120) NOT NULL,
 outcome VARCHAR(40) NOT NULL, reason VARCHAR(500) NOT NULL, body MEDIUMTEXT NOT NULL, methodology MEDIUMTEXT NOT NULL,
 sources MEDIUMTEXT NOT NULL, limitations TEXT NOT NULL, next_action TEXT NOT NULL, metadata TEXT NOT NULL, plan_json MEDIUMTEXT NOT NULL,
 visibility VARCHAR(20) NOT NULL DEFAULT 'public', created_at DATETIME NOT NULL,
 UNIQUE KEY submission_nonce(research_id,nonce), UNIQUE KEY submission_parent(id,research_id),
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE,
 FOREIGN KEY(task_id,research_id) REFERENCES workflow_tasks(id,research_id),
 FOREIGN KEY(snapshot_id,research_id) REFERENCES workflow_snapshots(id,research_id)
) ENGINE=InnoDB;
CREATE TABLE workflow_clarifications (
 id CHAR(32) PRIMARY KEY, research_id CHAR(32) NOT NULL, blocker_id CHAR(32) NULL, actor VARCHAR(40) NOT NULL,
 reason VARCHAR(500) NOT NULL, body MEDIUMTEXT NOT NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE audit_events (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, research_id CHAR(32) NULL, actor_type VARCHAR(40) NOT NULL, actor_name VARCHAR(120) NOT NULL,
 entity_type VARCHAR(40) NOT NULL, entity_id VARCHAR(100) NOT NULL, action VARCHAR(100) NOT NULL,
 old_state VARCHAR(100) NOT NULL, new_state VARCHAR(100) NOT NULL, reason VARCHAR(500) NOT NULL, next_action TEXT NOT NULL,
 metadata TEXT NOT NULL, visibility VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE, INDEX(research_id,id)
) ENGINE=InnoDB;
CREATE TABLE workflow_attempts (
 id CHAR(32) PRIMARY KEY, research_id CHAR(32) NOT NULL, task_id CHAR(32) NOT NULL,
 payload MEDIUMTEXT NOT NULL, reason VARCHAR(500) NOT NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE
) ENGINE=InnoDB;
