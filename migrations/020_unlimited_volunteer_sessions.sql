ALTER TABLE volunteer_sessions
 ADD COLUMN limit_mode ENUM('bounded','unlimited') NOT NULL DEFAULT 'bounded',
 MODIFY assignment_limit SMALLINT UNSIGNED NULL,
 MODIFY assignments_used BIGINT UNSIGNED NOT NULL DEFAULT 0,
 MODIFY expires_at DATETIME NULL,
 ADD CONSTRAINT volunteer_session_limits CHECK (
  (limit_mode='bounded' AND assignment_limit IS NOT NULL AND assignment_limit>=1 AND expires_at IS NOT NULL)
  OR (limit_mode='unlimited' AND assignment_limit IS NULL AND expires_at IS NULL)
 );
ALTER TABLE browser_returns MODIFY expires_at DATETIME NULL;
