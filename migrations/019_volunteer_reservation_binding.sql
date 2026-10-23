ALTER TABLE volunteer_assignments ADD reservation_id CHAR(32) NULL,
 ADD FOREIGN KEY(reservation_id) REFERENCES task_reservations(id) ON DELETE SET NULL,
 ADD INDEX(reservation_id);
