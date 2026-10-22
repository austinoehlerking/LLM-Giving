-- Parent references deliberately survive source purge without retaining a live FK.
CREATE TABLE research_fork_intents (
 nonce_hash CHAR(64) PRIMARY KEY, owner_hash CHAR(64) NOT NULL,
 parent_id CHAR(32) NOT NULL, snapshot_id CHAR(32) NOT NULL,
 context_version INT NOT NULL, snapshot_sha256 CHAR(64) NOT NULL,
 background_json LONGTEXT NOT NULL, background_sha256 CHAR(64) NOT NULL,
 child_id CHAR(32) NULL, payload_hash CHAR(64) NULL, expires BIGINT NOT NULL,
 created_at DATETIME NOT NULL, INDEX fork_intent_child(child_id)
) ENGINE=InnoDB;
CREATE TABLE research_forks (
 research_id CHAR(32) PRIMARY KEY, parent_id CHAR(32) NOT NULL,
 snapshot_id CHAR(32) NOT NULL, context_version INT NOT NULL,
 snapshot_sha256 CHAR(64) NOT NULL, background_json LONGTEXT NOT NULL,
 background_sha256 CHAR(64) NOT NULL, created_at DATETIME NOT NULL,
 INDEX fork_parent(parent_id),
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TRIGGER research_forks_no_update BEFORE UPDATE ON research_forks FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fork provenance is immutable';
