CREATE TABLE IF NOT EXISTS categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL UNIQUE, enabled TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS research (
 id CHAR(32) PRIMARY KEY, slug VARCHAR(220) NOT NULL UNIQUE, title VARCHAR(180) NOT NULL, question MEDIUMTEXT NOT NULL,
 context MEDIUMTEXT NOT NULL, success MEDIUMTEXT NOT NULL, previous MEDIUMTEXT NOT NULL, constraints_text MEDIUMTEXT NOT NULL,
 questions MEDIUMTEXT NOT NULL, references_text MEDIUMTEXT NOT NULL,
 category_id INT NOT NULL, display_name VARCHAR(120) NOT NULL DEFAULT '', profiles TEXT NOT NULL, email VARCHAR(254) NOT NULL DEFAULT '',
 moderation VARCHAR(40) NOT NULL DEFAULT 'Pending Approval', moderation_reason TEXT NOT NULL,
 status VARCHAR(80) NOT NULL DEFAULT 'Awaiting First Research', flags TEXT NOT NULL,
 terms_version VARCHAR(40) NOT NULL, context_version INT NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, activity_at DATETIME NOT NULL,
 FOREIGN KEY(category_id) REFERENCES categories(id), INDEX(moderation,status,activity_at),
 FULLTEXT KEY research_search(title,question,context)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS credentials (research_id CHAR(32) PRIMARY KEY, token_hash CHAR(64) NOT NULL, FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS recovery_tokens (research_id CHAR(32) PRIMARY KEY, token_hash CHAR(64) NOT NULL, expires BIGINT NOT NULL, FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tags (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(60) NOT NULL UNIQUE) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS research_tags (research_id CHAR(32) NOT NULL, tag_id INT NOT NULL, PRIMARY KEY(research_id,tag_id), FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE, FOREIGN KEY(tag_id) REFERENCES tags(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS contributions (
 id CHAR(32) PRIMARY KEY, research_id CHAR(32) NOT NULL, kind VARCHAR(40) NOT NULL,
 target_id CHAR(32) NULL, summary TEXT NOT NULL, body MEDIUMTEXT NOT NULL, methodology MEDIUMTEXT NOT NULL,
 sources MEDIUMTEXT NOT NULL, uncertainty TEXT NOT NULL, limitations TEXT NOT NULL, previous_work TEXT NOT NULL,
 open_questions TEXT NOT NULL, next_actions TEXT NOT NULL, suggested_status VARCHAR(80) NOT NULL,
 safety TEXT NOT NULL, recommendation VARCHAR(40) NOT NULL DEFAULT '', display_name VARCHAR(120) NOT NULL, profiles TEXT NOT NULL,
 moderation VARCHAR(40) NOT NULL, moderation_reason TEXT NOT NULL, flags TEXT NOT NULL, metadata_exception TEXT NOT NULL,
 superseded_by CHAR(32) NULL, terms_version VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL, published_at DATETIME NULL,
 UNIQUE KEY contribution_parent(id,research_id), INDEX(research_id,moderation),
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE
) ENGINE=InnoDB;
ALTER TABLE contributions ADD CONSTRAINT contribution_target FOREIGN KEY(target_id,research_id) REFERENCES contributions(id,research_id);
ALTER TABLE contributions ADD CONSTRAINT contribution_superseded FOREIGN KEY(superseded_by,research_id) REFERENCES contributions(id,research_id);
CREATE TABLE IF NOT EXISTS agent_metadata (
 contribution_id CHAR(32) PRIMARY KEY, research_id CHAR(32) NOT NULL,
 provider VARCHAR(150) NULL, model VARCHAR(200) NULL, harness VARCHAR(150) NULL, harness_version VARCHAR(100) NULL,
 reasoning VARCHAR(100) NULL, input_tokens BIGINT NULL, output_tokens BIGINT NULL, total_tokens BIGINT NULL,
 measurement VARCHAR(200) NULL, duration VARCHAR(100) NULL, tools TEXT NULL, prompt_version VARCHAR(80) NULL,
 missing_fields TEXT NOT NULL,
 FOREIGN KEY(contribution_id,research_id) REFERENCES contributions(id,research_id) ON DELETE CASCADE,
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS findings (
 id CHAR(32) PRIMARY KEY, research_id CHAR(32) NOT NULL, kind VARCHAR(50) NOT NULL, classification VARCHAR(30) NOT NULL,
 statement MEDIUMTEXT NOT NULL, rationale TEXT NOT NULL, supersedes_id CHAR(32) NULL, created_at DATETIME NOT NULL,
 UNIQUE KEY finding_parent(id,research_id), FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE
) ENGINE=InnoDB;
ALTER TABLE findings ADD CONSTRAINT finding_supersedes FOREIGN KEY(supersedes_id,research_id) REFERENCES findings(id,research_id);
CREATE TABLE IF NOT EXISTS finding_evidence (
 finding_id CHAR(32) NOT NULL, research_id CHAR(32) NOT NULL, contribution_id CHAR(32) NOT NULL,
 PRIMARY KEY(finding_id,contribution_id),
 FOREIGN KEY(finding_id,research_id) REFERENCES findings(id,research_id) ON DELETE CASCADE,
 FOREIGN KEY(contribution_id,research_id) REFERENCES contributions(id,research_id) ON DELETE CASCADE,
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS feedback (
 id CHAR(32) PRIMARY KEY, research_id CHAR(32) NOT NULL, target_id CHAR(32) NULL, finding_id CHAR(32) NULL,
 kind VARCHAR(40) NOT NULL, body MEDIUMTEXT NOT NULL, display_name VARCHAR(120) NOT NULL,
 moderation VARCHAR(40) NOT NULL, moderation_reason TEXT NOT NULL, flags TEXT NOT NULL,
 terms_version VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL,
 UNIQUE KEY feedback_parent(id,research_id),
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE,
 FOREIGN KEY(target_id,research_id) REFERENCES contributions(id,research_id),
 FOREIGN KEY(finding_id,research_id) REFERENCES findings(id,research_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS revisions (
 id CHAR(32) PRIMARY KEY, research_id CHAR(32) NOT NULL, title VARCHAR(180) NOT NULL, question MEDIUMTEXT NOT NULL, context MEDIUMTEXT NOT NULL,
 moderation VARCHAR(40) NOT NULL, moderation_reason TEXT NOT NULL, flags TEXT NOT NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS research_versions (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, research_id CHAR(32) NOT NULL, version INT NOT NULL,
 title VARCHAR(180) NOT NULL, question MEDIUMTEXT NOT NULL, context MEDIUMTEXT NOT NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS attachments (
 id CHAR(32) PRIMARY KEY, research_id CHAR(32) NOT NULL, contribution_id CHAR(32) NULL, feedback_id CHAR(32) NULL,
 original_name VARCHAR(200) NOT NULL, stored_name VARCHAR(80) NOT NULL, mime VARCHAR(100) NOT NULL, bytes BIGINT NOT NULL,
 sha256 CHAR(64) NOT NULL, moderation VARCHAR(40) NOT NULL DEFAULT 'Pending Approval', scan_status VARCHAR(80) NOT NULL,
 moderation_reason TEXT NOT NULL, flags TEXT NOT NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE,
 FOREIGN KEY(contribution_id,research_id) REFERENCES contributions(id,research_id) ON DELETE CASCADE,
 FOREIGN KEY(feedback_id,research_id) REFERENCES feedback(id,research_id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS status_history (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, research_id CHAR(32) NOT NULL, old_status VARCHAR(80) NOT NULL,
 new_status VARCHAR(80) NOT NULL, actor VARCHAR(30) NOT NULL, reason TEXT NOT NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS research_links (
 research_id CHAR(32) NOT NULL, related_id CHAR(32) NOT NULL, reason TEXT NOT NULL,
 PRIMARY KEY(research_id,related_id), FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE,
 FOREIGN KEY(related_id) REFERENCES research(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS admins (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(254) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS settings (name VARCHAR(100) PRIMARY KEY, value MEDIUMTEXT NOT NULL) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS rate_limits (bucket CHAR(64) PRIMARY KEY, hits INT NOT NULL, expires BIGINT NOT NULL) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS emails (id BIGINT AUTO_INCREMENT PRIMARY KEY, research_id CHAR(32) NOT NULL, subject VARCHAR(180) NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, FOREIGN KEY(research_id) REFERENCES research(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS purge_jobs (id CHAR(32) PRIMARY KEY, files MEDIUMTEXT NOT NULL, state VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL) ENGINE=InnoDB;
