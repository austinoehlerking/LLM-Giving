-- Discovery is an explicit administrator choice, independent of publication and workflow state.
-- Existing and new requests stay listed until an administrator records a choice.
ALTER TABLE research ADD COLUMN discovery_listed TINYINT NOT NULL DEFAULT 1;
