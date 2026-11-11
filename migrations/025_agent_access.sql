-- Optional transport authority remains subordinate to the original human session.
CREATE TABLE volunteer_agent_credentials (
 session_id CHAR(32) PRIMARY KEY,
 token_hash CHAR(64) NOT NULL,
 session_hash CHAR(64) NOT NULL,
 enabled_at DATETIME NOT NULL,
 FOREIGN KEY(session_id) REFERENCES volunteer_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB;
ALTER TABLE volunteer_assignments ADD queue_reason VARCHAR(300) NOT NULL DEFAULT '';
CREATE TABLE volunteer_research_notes (
 assignment_id CHAR(32) PRIMARY KEY,
 research_id CHAR(32) NOT NULL,
 grant_hash CHAR(64) NOT NULL,
 session_hash CHAR(64) NOT NULL,
 version INT UNSIGNED NOT NULL,
 notes TEXT NOT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 expires_at DATETIME NOT NULL,
 FOREIGN KEY(assignment_id) REFERENCES volunteer_assignments(id) ON DELETE CASCADE,
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE
) ENGINE=InnoDB;
