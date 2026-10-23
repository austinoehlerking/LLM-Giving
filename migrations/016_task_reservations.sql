CREATE TABLE reservation_actors (
 actor_hash CHAR(64) PRIMARY KEY
) ENGINE=InnoDB;
CREATE TABLE task_reservations (
 id CHAR(32) PRIMARY KEY, research_id CHAR(32) NOT NULL, task_id CHAR(32) NOT NULL,
 task_revision INT NOT NULL, actor_hash CHAR(64) NOT NULL, eligibility_fence CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL,
 ended_at DATETIME NULL, end_reason VARCHAR(20) NULL, result_id CHAR(32) NULL,
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE,
 FOREIGN KEY(task_id,research_id) REFERENCES workflow_tasks(id,research_id) ON DELETE CASCADE,
 FOREIGN KEY(actor_hash) REFERENCES reservation_actors(actor_hash),
 INDEX task_live(task_id,ended_at,expires_at), INDEX actor_live(actor_hash,ended_at,expires_at)
) ENGINE=InnoDB;
