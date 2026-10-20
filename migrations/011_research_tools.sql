ALTER TABLE research ADD COLUMN project_type VARCHAR(24) NOT NULL DEFAULT 'research', ADD COLUMN tool_requirements TEXT NOT NULL DEFAULT ('{}');
ALTER TABLE workflow_tasks ADD COLUMN builds_tool TINYINT NOT NULL DEFAULT 0;
