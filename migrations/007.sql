CREATE TABLE workflow_handoffs (
 id CHAR(32) PRIMARY KEY, research_id CHAR(32) NOT NULL, task_id CHAR(32) NULL, snapshot_id CHAR(32) NOT NULL,
 nonce CHAR(32) NULL, body LONGTEXT NOT NULL, sha256 CHAR(64) NOT NULL, created_at DATETIME NOT NULL,
 UNIQUE KEY handoff_nonce(research_id,nonce),
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE,
 FOREIGN KEY(task_id,research_id) REFERENCES workflow_tasks(id,research_id),
 FOREIGN KEY(snapshot_id,research_id) REFERENCES workflow_snapshots(id,research_id)
) ENGINE=InnoDB;
CREATE TRIGGER workflow_handoffs_no_update BEFORE UPDATE ON workflow_handoffs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Issued handoffs are immutable';
