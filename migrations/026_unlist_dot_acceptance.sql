-- These two stable research IDs were created for the October 24 Dot acceptance test.
-- Keep their publication and workflow state separate from discovery.
UPDATE research SET discovery_listed=0 WHERE id IN ('0242a1b38a0ec3688a243660e9a4641d','3c1bb7ce8f63d6fcc31ab948ff34b1d0');

-- Earlier local seeds carry both markers from the seed command.
UPDATE research SET discovery_listed=0 WHERE display_name='Development example' AND moderation_reason='Clearly labelled synthetic development example.' AND context='DEVELOPMENT EXAMPLE. This is synthetic demonstration content, not completed research or a verified factual claim.';
