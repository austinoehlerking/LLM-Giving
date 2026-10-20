ALTER TABLE workflow_tasks ADD COLUMN visibility VARCHAR(20) NOT NULL DEFAULT 'public';
DROP TRIGGER IF EXISTS audit_events_no_update;
CREATE TRIGGER audit_events_no_update BEFORE UPDATE ON audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit entries are append-only';
DROP TRIGGER IF EXISTS audit_events_no_delete;
CREATE TRIGGER audit_events_no_delete BEFORE DELETE ON audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit entries are append-only. Explicit parent purge is handled by foreign-key cascade';
