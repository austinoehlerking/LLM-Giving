ALTER TABLE workflow_submissions ADD COLUMN admission VARCHAR(20) NOT NULL DEFAULT 'accepted', ADD INDEX admission_queue(research_id,admission);
