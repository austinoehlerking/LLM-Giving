ALTER TABLE workflow_submissions ADD submission_method VARCHAR(24) NULL;
ALTER TABLE contributions ADD submission_method VARCHAR(24) NULL, ADD human_influence JSON NULL;
CREATE TABLE browser_returns (
 id CHAR(32) PRIMARY KEY, research_id CHAR(32) NOT NULL, task_id CHAR(32) NULL,
 secret_hash CHAR(64) NOT NULL, actor_hash CHAR(64) NOT NULL, scope JSON NOT NULL, consent JSON NOT NULL,
 created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, result_id CHAR(32) NULL, payload_hash CHAR(64) NULL,
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE,
 FOREIGN KEY(task_id,research_id) REFERENCES workflow_tasks(id,research_id), INDEX(research_id,expires_at)
) ENGINE=InnoDB;
