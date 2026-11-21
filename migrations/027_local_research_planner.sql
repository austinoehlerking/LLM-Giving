CREATE TABLE local_planner_jobs (
    research_id CHAR(32) PRIMARY KEY,
    task_id CHAR(32) NOT NULL,
    context_version INT UNSIGNED NOT NULL,
    state ENUM('running','failed','complete') NOT NULL,
    lease_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL,
    model VARCHAR(80) NOT NULL,
    failure VARCHAR(50) NOT NULL DEFAULT '',
    result_hash CHAR(64) NULL,
    run_id VARCHAR(80) NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (research_id) REFERENCES research(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES workflow_tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO settings(name,value) VALUES ('local_planner_enabled','0'),('local_planner_mode_lock','');
