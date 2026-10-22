-- Additive provenance only. Existing request JSON, snapshots and issued handoffs stay unchanged.
ALTER TABLE agent_metadata ADD COLUMN IF NOT EXISTS workflow_mode VARCHAR(20) NULL DEFAULT NULL;
-- Administrator choices stay intact. Only the built-in default planning policy changes.
