ALTER TABLE volunteer_sessions
 ADD COLUMN session_profile ENUM('general','restricted_web_v1') NOT NULL DEFAULT 'general' AFTER scope_id;
