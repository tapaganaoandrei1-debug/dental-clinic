INSERT INTO users (id, role, first_name, last_name, email, phone, password_hash, is_verified) VALUES
(1, 'admin', 'System', 'Admin', 'admin@dentalclinic.com', '+12025550101', '$2y$10$wT0E0vsm0cQjFz3PjE6Vre7J3056M3eX5YvL5m8R2kG0T5P2L92qO', 1),
(2, 'staff', 'Dr. Sarah', 'Jenkins', 'sjenkins@dentalclinic.com', '+12025550102', '$2y$10$wT0E0vsm0cQjFz3PjE6Vre7J3056M3eX5YvL5m8R2kG0T5P2L92qO', 1),
(3, 'staff', 'Dr. Marcus', 'Vance', 'mvance@dentalclinic.com', '+12025550103', '$2y$10$wT0E0vsm0cQjFz3PjE6Vre7J3056M3eX5YvL5m8R2kG0T5P2L92qO', 1);

INSERT INTO services (id, name, department, duration_minutes, price) VALUES
(1, 'Routine Cleaning', 'Hygiene', 45, 85.00),
(2, 'Tooth Filling', 'Restorative', 60, 150.00),
(3, 'Tooth Extraction', 'Surgery', 60, 250.00);

INSERT INTO staff_schedules (staff_id, day_of_week, start_time, end_time, break_start, break_end) VALUES
(2, 1, '08:00:00', '17:00:00', '12:00:00', '13:00:00'),
(2, 2, '08:00:00', '17:00:00', '12:00:00', '13:00:00'),
(2, 3, '08:00:00', '17:00:00', '12:00:00', '13:00:00'),
(2, 4, '08:00:00', '17:00:00', '12:00:00', '13:00:00'),
(2, 5, '08:00:00', '17:00:00', '12:00:00', '13:00:00');
