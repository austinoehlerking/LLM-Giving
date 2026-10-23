CREATE TABLE volunteer_sessions (
 id CHAR(32) PRIMARY KEY,
 secret_hash CHAR(64) NOT NULL,
 actor_hash CHAR(64) NOT NULL,
 scope_kind VARCHAR(16) NOT NULL,
 scope_id VARCHAR(180) NOT NULL DEFAULT '',
 assignment_limit SMALLINT UNSIGNED NOT NULL,
 assignments_used SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 capabilities JSON NOT NULL,
 consent JSON NOT NULL,
 created_at DATETIME NOT NULL,
 expires_at DATETIME NOT NULL,
 stopped_at DATETIME NULL,
 stop_reason VARCHAR(120) NOT NULL DEFAULT '',
 INDEX(actor_hash, expires_at)
) ENGINE=InnoDB;

CREATE TABLE volunteer_assignments (
 id CHAR(32) PRIMARY KEY,
 session_id CHAR(32) NOT NULL,
 research_id CHAR(32) NULL,
 task_id CHAR(32) NULL,
 return_id CHAR(32) NULL UNIQUE,
 task_revision INT NOT NULL,
 created_at DATETIME NOT NULL,
 ended_at DATETIME NULL,
 outcome VARCHAR(24) NOT NULL DEFAULT 'not_submitted',
 FOREIGN KEY(session_id) REFERENCES volunteer_sessions(id) ON DELETE CASCADE,
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE SET NULL,
 FOREIGN KEY(task_id) REFERENCES workflow_tasks(id) ON DELETE SET NULL,
 FOREIGN KEY(return_id) REFERENCES browser_returns(id) ON DELETE SET NULL,
 INDEX(session_id, created_at)
) ENGINE=InnoDB;
