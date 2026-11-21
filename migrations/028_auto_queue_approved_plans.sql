ALTER TABLE workflow_tasks
    ADD COLUMN local_plan_requested TINYINT(1) NOT NULL DEFAULT 0,
    ADD INDEX local_plan_dispatch (local_plan_requested, kind, state, visibility);
