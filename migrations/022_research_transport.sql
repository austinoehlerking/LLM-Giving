-- Private short-lived transport checkpoints. Public archives/snapshots stay immutable.
CREATE TABLE research_transports (
 id CHAR(32) PRIMARY KEY,
 research_id CHAR(32) NOT NULL,
 task_id CHAR(32) NOT NULL,
 grant_id CHAR(32) NOT NULL,
 owner_actor_hash CHAR(64) NOT NULL,
 credential_hash CHAR(64) NOT NULL,
 session_id CHAR(32) NULL,
 session_credential_hash CHAR(64) NULL,
 direction VARCHAR(8) NOT NULL,
 binding JSON NOT NULL,
 manifest JSON NOT NULL,
 dependency_ack JSON NOT NULL,
 byte_length INT UNSIGNED NOT NULL,
 state VARCHAR(16) NOT NULL DEFAULT 'receiving',
 created_at DATETIME NOT NULL,
 expires_at DATETIME NOT NULL,
 finalized_at DATETIME NULL,
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE,
 FOREIGN KEY(task_id) REFERENCES workflow_tasks(id) ON DELETE CASCADE,
 FOREIGN KEY(grant_id) REFERENCES browser_returns(id) ON DELETE CASCADE,
 INDEX(owner_actor_hash,expires_at), INDEX(expires_at)
) ENGINE=InnoDB;
CREATE TABLE research_transport_parts (
 transport_id CHAR(32) NOT NULL,
 part_no SMALLINT UNSIGNED NOT NULL,
 byte_length INT UNSIGNED NOT NULL,
 sha256 CHAR(64) NOT NULL,
 body MEDIUMBLOB NOT NULL,
 acknowledged_at DATETIME NULL,
 PRIMARY KEY(transport_id,part_no),
 FOREIGN KEY(transport_id) REFERENCES research_transports(id) ON DELETE CASCADE
) ENGINE=InnoDB;