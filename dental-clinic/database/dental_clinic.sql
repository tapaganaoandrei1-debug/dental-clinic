-- =====================================================================
-- Dental Clinic Appointment and Queue Management System
-- School project database. Import in phpMyAdmin (Import tab).
-- WARNING: running this file again DROPS and recreates all tables.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS dental_clinic
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE dental_clinic;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS email_verification_tokens;
DROP TABLE IF EXISTS password_reset_tokens;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS queue_entries;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS holidays;
DROP TABLE IF EXISTS schedule_breaks;
DROP TABLE IF EXISTS schedules;
DROP TABLE IF EXISTS dentist_services;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS dentists;
DROP TABLE IF EXISTS staff_profiles;
DROP TABLE IF EXISTS patient_profiles;
DROP TABLE IF EXISTS clinic_settings;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- users: every person who can log in (patient, staff or admin).
-- Passwords are stored ONLY as password_hash() values.
-- failed_login_attempts + locked_until are used for the login cooldown.
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    role                  ENUM('patient','staff','admin') NOT NULL DEFAULT 'patient',
    full_name             VARCHAR(100) NOT NULL,
    email                 VARCHAR(150) NOT NULL,
    phone                 VARCHAR(20) DEFAULT NULL,
    password_hash         VARCHAR(255) NOT NULL,
    is_active             TINYINT(1) NOT NULL DEFAULT 1,
    email_verified_at     DATETIME DEFAULT NULL,
    failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until          DATETIME DEFAULT NULL,
    last_login_at         DATETIME DEFAULT NULL,
    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='All login accounts';

-- patient_profiles: extra details for users whose role is patient.
CREATE TABLE patient_profiles (
    user_id                 INT UNSIGNED NOT NULL,
    birth_date              DATE DEFAULT NULL,
    gender                  ENUM('female','male','other','prefer_not_to_say') DEFAULT NULL,
    address                 VARCHAR(255) DEFAULT NULL,
    emergency_contact_name  VARCHAR(100) DEFAULT NULL,
    emergency_contact_phone VARCHAR(20) DEFAULT NULL,
    medical_notes           TEXT DEFAULT NULL,
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_patient_profiles_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Patient details (one row per patient user)';

-- staff_profiles: job title for staff and admin users.
CREATE TABLE staff_profiles (
    user_id    INT UNSIGNED NOT NULL,
    position   VARCHAR(80) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_staff_profiles_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Staff and admin job titles';

-- dentists: the service providers. Each dentist is a bookable "resource".
CREATE TABLE dentists (
DROP TABLE IF EXISTS email_verification_tokens;
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name  VARCHAR(100) NOT NULL,
    specialty  VARCHAR(100) DEFAULT NULL,
    email      VARCHAR(150) DEFAULT NULL,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Dentists / service providers';

-- services: what patients can book. Duration decides the appointment length.
CREATE TABLE services (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name             VARCHAR(100) NOT NULL,
    category         VARCHAR(60) DEFAULT NULL,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_services_name (name),
    KEY idx_services_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Dental services with duration and price';

-- dentist_services: which dentist can perform which service.
    email_verified_at     DATETIME DEFAULT NULL,
CREATE TABLE dentist_services (
    dentist_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (dentist_id, service_id),
    CONSTRAINT fk_ds_dentist FOREIGN KEY (dentist_id) REFERENCES dentists (id) ON DELETE CASCADE,
    CONSTRAINT fk_ds_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Which dentist offers which service';

-- schedules: weekly working hours. One row per dentist per weekday.
-- day_of_week: 0 = Sunday ... 6 = Saturday (same as PHP date("w")).
    day_of_week TINYINT UNSIGNED NOT NULL,
    start_time  TIME NOT NULL,
    end_time    TIME NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schedule_dentist_day (dentist_id, day_of_week),
    CONSTRAINT fk_schedules_dentist FOREIGN KEY (dentist_id) REFERENCES dentists (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Weekly working hours per dentist';

-- schedule_breaks: time ranges inside a working day that cannot be booked (lunch).
CREATE TABLE schedule_breaks (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    schedule_id INT UNSIGNED NOT NULL,
    break_start TIME NOT NULL,
    break_end   TIME NOT NULL,
    label       VARCHAR(60) DEFAULT 'Break',
    PRIMARY KEY (id),
    KEY idx_breaks_schedule (schedule_id),
    CONSTRAINT fk_breaks_schedule FOREIGN KEY (schedule_id) REFERENCES schedules (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Breaks inside a working day';

-- holidays: blocked dates. dentist_id NULL = whole clinic closed.
-- A dentist_id value blocks only that dentist (example: leave).
CREATE TABLE holidays (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    holiday_date DATE NOT NULL,
    description  VARCHAR(150) NOT NULL,
    dentist_id   INT UNSIGNED DEFAULT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_holidays_date (holiday_date),

-- status values: pending, confirmed, completed, cancelled, no_show
-- A slot is blocked by appointments whose status is pending, confirmed
-- or completed. Cancelled and no_show appointments do NOT block a slot.
-- Double-booking is prevented in PHP using a transaction (Phase 3).
-- ---------------------------------------------------------------------
CREATE TABLE appointments (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    patient_id          INT UNSIGNED NOT NULL,
    service_id          INT UNSIGNED NOT NULL,

UPDATE users SET email_verified_at = NOW() WHERE id BETWEEN 1 AND 8;
    dentist_id          INT UNSIGNED NOT NULL,
    appointment_date    DATE NOT NULL,
    start_time          TIME NOT NULL,
    end_time            TIME NOT NULL,
    status              ENUM('pending','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
    patient_notes       TEXT DEFAULT NULL,
    staff_notes         TEXT DEFAULT NULL,
    cancellation_reason VARCHAR(255) DEFAULT NULL,
    arrived_at          DATETIME DEFAULT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_appt_dentist_slot (dentist_id, appointment_date, start_time),
    KEY idx_appt_patient_date (patient_id, appointment_date),
    KEY idx_appt_date_status (appointment_date, status),
    CONSTRAINT fk_appt_patient FOREIGN KEY (patient_id) REFERENCES users (id),
    CONSTRAINT fk_appt_service FOREIGN KEY (service_id) REFERENCES services (id),
    CONSTRAINT fk_appt_dentist FOREIGN KEY (dentist_id) REFERENCES dentists (id)
  COMMENT='Appointments. patient_id points to users.id';

-- ---------------------------------------------------------------------
-- queue_entries: one row per checked-in appointment.
-- status values: waiting -> called -> serving -> completed
--                also: skipped, no_show, cancelled
-- Queue numbers restart every day (see UNIQUE key on queue_date + queue_number).
-- estimated_wait_minutes is only an ESTIMATE, not an exact prediction.
-- ---------------------------------------------------------------------
CREATE TABLE queue_entries (
    id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    appointment_id         INT UNSIGNED NOT NULL,
    queue_number           SMALLINT UNSIGNED NOT NULL,
    status                 ENUM('waiting','called','serving','completed','skipped','no_show','cancelled') NOT NULL DEFAULT 'waiting',
    checked_in_at          DATETIME NOT NULL,
    called_at              DATETIME DEFAULT NULL,
    service_started_at     DATETIME DEFAULT NULL,
    completed_at           DATETIME DEFAULT NULL,
    estimated_wait_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_queue_appointment (appointment_id),
    UNIQUE KEY uq_queue_date_number (queue_date, queue_number),
    KEY idx_queue_date_status (queue_date, status),
    CONSTRAINT fk_queue_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci

-- ---------------------------------------------------------------------
-- notifications: reminder log / outbox. THIS IS A SIMULATION.
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED NOT NULL,
    appointment_id INT UNSIGNED DEFAULT NULL,
    channel        ENUM('email','sms') NOT NULL,
    type           ENUM('confirmation','reminder','reschedule','cancellation','password_reset','email_verification') NOT NULL,
    recipient      VARCHAR(150) NOT NULL,
    subject        VARCHAR(150) DEFAULT NULL,
    scheduled_for  DATETIME NOT NULL,
    status         ENUM('queued','simulated_sent','sent','failed') NOT NULL DEFAULT 'queued',
    sent_at        DATETIME DEFAULT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_status_time (status, scheduled_for),
    KEY idx_notif_user (user_id),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_appt FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Email/SMS reminder log (simulation)';

CREATE TABLE password_reset_tokens (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    used_at    DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reset_token_hash (token_hash),
    KEY idx_reset_user_expiry (user_id, used_at, expires_at),
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Expiring, single-use password reset tokens';

CREATE TABLE email_verification_tokens (
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_verification_token_hash (token_hash),
    KEY idx_verification_user_expiry (user_id, used_at, expires_at),
    CONSTRAINT fk_verification_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Expiring, single-use email verification tokens';

-- activity_logs: simple audit trail of important actions.
CREATE TABLE activity_logs (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED DEFAULT NULL,
    action     VARCHAR(60) NOT NULL,
    details    VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_logs_user (user_id),
    KEY idx_logs_created (created_at),
    CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Audit / activity log';

-- clinic_settings: simple key/value settings editable by the admin.
CREATE TABLE clinic_settings (
    setting_key   VARCHAR(60) NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    description   VARCHAR(150) DEFAULT NULL,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Clinic settings (key/value)';

-- =====================================================================
-- SAMPLE DATA
-- =====================================================================

-- Dates below are relative to the day you import this file.
SET @today = CURDATE();
SET @d7    = DATE_SUB(CURDATE(), INTERVAL 7 DAY);
SET @d14   = DATE_SUB(CURDATE(), INTERVAL 14 DAY);

-- Users. The password_hash "RUN_SETUP_SCRIPT" is NOT a valid hash, so nobody can
-- log in until you run scripts/setup-accounts.php once. It writes real hashes.
INSERT INTO users (id, role, full_name, email, phone, password_hash) VALUES
(1, 'admin',   'Clinic Administrator', 'admin@dentalclinic.test',   '09170000001', 'RUN_SETUP_SCRIPT'),
(2, 'staff',   'Front Desk Staff',     'staff@dentalclinic.test',   '09170000002', 'RUN_SETUP_SCRIPT'),
(3, 'patient', 'Maria Cruz',           'patient@dentalclinic.test', '09171234567', 'RUN_SETUP_SCRIPT'),
(4, 'patient', 'Ana Reyes',            'ana.reyes@example.com',     '09171110004', 'RUN_SETUP_SCRIPT'),
(5, 'patient', 'Joel Santos',          'joel.santos@example.com',   '09171110005', 'RUN_SETUP_SCRIPT'),
(6, 'patient', 'Liza Moreno',          'liza.moreno@example.com',   '09171110006', 'RUN_SETUP_SCRIPT'),
(7, 'patient', 'Ben Torres',           'ben.torres@example.com',    '09171110007', 'RUN_SETUP_SCRIPT'),
(8, 'patient', 'Carla Dizon',          'carla.dizon@example.com',   '09171110008', 'RUN_SETUP_SCRIPT');

INSERT INTO staff_profiles (user_id, position) VALUES
(1, 'Clinic Manager'),
(2, 'Front Desk');

INSERT INTO patient_profiles (user_id, birth_date, gender, address, emergency_contact_name, emergency_contact_phone, medical_notes) VALUES
(3, '1994-03-12', 'female', 'Taguig City',        'Pedro Cruz',    '09175550001', NULL),
(4, '1988-07-25', 'female', 'Makati City',        'Luis Reyes',    '09175550002', NULL),
(5, '1979-11-02', 'male',   'Pasig City',         'Rosa Santos',   '09175550003', 'Allergic to penicillin'),
(6, '1999-01-18', 'female', 'Quezon City',        'Ramon Moreno',  '09175550004', NULL),
(7, '2001-09-30', 'male',   'Mandaluyong City',   'Elena Torres',  '09175550005', NULL),
(8, '1992-05-09', 'female', 'Paranaque City',     'Mario Dizon',   '09175550006', NULL);

INSERT INTO dentists (id, full_name, specialty, email) VALUES
(1, 'Dr. Elena Ramos',      'General Dentistry',          'ramos@dentalclinic.test'),
(2, 'Dr. Marco Villanueva', 'Oral Surgery',               'villanueva@dentalclinic.test'),
(3, 'Dr. Sofia Lim',        'Orthodontics and Cosmetic',  'lim@dentalclinic.test');

-- Durations are multiples of 30 minutes to match the default slot interval.
INSERT INTO services (id, name, category, description, duration_minutes, price, is_active) VALUES
(1, 'Check-up & Cleaning',   'General Dentistry', 'Oral exam and professional teeth cleaning.',          30,  800.00, 1),
(2, 'Tooth Extraction',      'Oral Surgery',      'Removal of a damaged or problem tooth.',              60, 1500.00, 1),
(3, 'Braces Consultation',   'Orthodontics',      'Evaluation and treatment plan for braces.',           30,  500.00, 1),
(4, 'Teeth Whitening',       'Cosmetic',          'In-clinic professional teeth whitening.',             60, 4500.00, 1),
(5, 'Dental Filling',        'General Dentistry', 'Filling for a cavity.',                               60, 1200.00, 1),
(6, 'Root Canal Treatment',  'Oral Surgery',      'Currently not offered (inactive example service).',   90, 6000.00, 0);

INSERT INTO dentist_services (dentist_id, service_id) VALUES
(1, 1), (1, 5),
(2, 1), (2, 2), (2, 6),
(3, 3), (3, 4);

-- Weekly schedules (0 = Sunday ... 6 = Saturday). Sunday is closed for everyone.
INSERT INTO schedules (dentist_id, day_of_week, start_time, end_time) VALUES
(1, 1, '09:00:00', '17:00:00'),
(1, 2, '09:00:00', '17:00:00'),
(1, 3, '09:00:00', '17:00:00'),
(1, 4, '09:00:00', '17:00:00'),
(1, 5, '09:00:00', '17:00:00'),
(1, 6, '09:00:00', '17:00:00'),
(2, 1, '09:00:00', '17:00:00'),
(2, 3, '09:00:00', '17:00:00'),
(2, 5, '09:00:00', '17:00:00'),
(3, 2, '09:00:00', '15:00:00'),
(3, 4, '09:00:00', '15:00:00'),
(3, 6, '09:00:00', '15:00:00');

-- Lunch break for every schedule row.
INSERT INTO schedule_breaks (schedule_id, break_start, break_end, label)
SELECT id, '12:00:00', '13:00:00', 'Lunch break' FROM schedules;

-- Holidays (clinic-wide) and one dentist-only block.
INSERT INTO holidays (holiday_date, description, dentist_id) VALUES
('2026-11-01', 'All Saints'' Day',               NULL),
('2026-11-30', 'Bonifacio Day',                  NULL),
('2026-12-08', 'Feast of the Immaculate Conception', NULL),
('2026-12-25', 'Christmas Day',                  NULL),
('2026-12-30', 'Rizal Day',                      NULL),
('2027-01-01', 'New Year''s Day',                NULL),
('2026-11-20', 'Dr. Villanueva - dental seminar', 2);

-- Appointments. Ids 1-6 are TODAY, 7-10 are past, 11-13 are upcoming.
INSERT INTO appointments
(id, patient_id, service_id, dentist_id, appointment_date, start_time, end_time, status, patient_notes, staff_notes, cancellation_reason, arrived_at) VALUES
(1, 4, 1, 1, @today, '09:00:00', '09:30:00', 'confirmed', NULL, NULL, NULL, TIMESTAMP(@today, '08:50:00')),
(2, 5, 2, 2, @today, '09:30:00', '10:30:00', 'confirmed', 'Toothache on the lower left side.', NULL, NULL, TIMESTAMP(@today, '09:20:00')),
(3, 3, 1, 1, @today, '10:00:00', '10:30:00', 'pending',   NULL, NULL, NULL, NULL),
(4, 6, 3, 3, @today, '10:30:00', '11:00:00', 'confirmed', NULL, NULL, NULL, TIMESTAMP(@today, '10:15:00')),
(5, 7, 4, 3, @today, '11:00:00', '12:00:00', 'confirmed', NULL, NULL, NULL, TIMESTAMP(@today, '10:40:00')),
(6, 8, 1, 1, @today, '10:30:00', '11:00:00', 'confirmed', NULL, NULL, NULL, TIMESTAMP(@today, '10:20:00')),
(7, 3, 1, 1, @d7,  '10:00:00', '10:30:00', 'completed', NULL, 'Routine cleaning done.', NULL, TIMESTAMP(@d7, '09:50:00')),
(8, 4, 2, 2, @d14, '09:00:00', '10:00:00', 'completed', NULL, 'Lower molar removed.', NULL, TIMESTAMP(@d14, '08:55:00')),
(9, 5, 1, 1, DATE_SUB(@today, INTERVAL 3 DAY), '14:00:00', '14:30:00', 'cancelled', NULL, NULL, 'Schedule conflict at work.', NULL),
(10, 7, 4, 3, DATE_SUB(@today, INTERVAL 5 DAY), '10:00:00', '11:00:00', 'no_show', NULL, 'Patient did not arrive.', NULL, NULL),
(11, 3, 1, 1, DATE_ADD(@today, INTERVAL 3 DAY),  '10:00:00', '10:30:00', 'confirmed', NULL, NULL, NULL, NULL),
(12, 6, 3, 3, DATE_ADD(@today, INTERVAL 5 DAY),  '14:00:00', '14:30:00', 'pending',   NULL, NULL, NULL, NULL),
(13, 8, 5, 1, DATE_ADD(@today, INTERVAL 10 DAY), '15:00:00', '16:00:00', 'confirmed', NULL, NULL, NULL, NULL);

-- Queue entries for today (numbers restart every day) plus two old completed ones.
INSERT INTO queue_entries
(appointment_id, queue_number, queue_date, status, checked_in_at, called_at, service_started_at, completed_at, estimated_wait_minutes) VALUES
(1, 1, @today, 'serving', TIMESTAMP(@today, '08:50:00'), TIMESTAMP(@today, '09:00:00'), TIMESTAMP(@today, '09:05:00'), NULL, 0),
(2, 2, @today, 'called',  TIMESTAMP(@today, '09:20:00'), TIMESTAMP(@today, '09:35:00'), NULL, NULL, 0),
(4, 3, @today, 'waiting', TIMESTAMP(@today, '10:15:00'), NULL, NULL, NULL, 90),
(6, 4, @today, 'waiting', TIMESTAMP(@today, '10:20:00'), NULL, NULL, NULL, 120),
(5, 5, @today, 'waiting', TIMESTAMP(@today, '10:40:00'), NULL, NULL, NULL, 150),
(7, 1, @d7,  'completed', TIMESTAMP(@d7, '09:50:00'),  TIMESTAMP(@d7, '09:58:00'),  TIMESTAMP(@d7, '10:00:00'),  TIMESTAMP(@d7, '10:28:00'), 0),
(8, 1, @d14, 'completed', TIMESTAMP(@d14, '08:55:00'), TIMESTAMP(@d14, '09:00:00'), TIMESTAMP(@d14, '09:02:00'), TIMESTAMP(@d14, '09:58:00'), 0);

-- Reminder log (simulation).
INSERT INTO notifications (user_id, appointment_id, channel, type, recipient, subject, message, scheduled_for, status, sent_at) VALUES
(3, 11, 'email', 'confirmation', 'patient@dentalclinic.test', 'Appointment confirmed',
 'Hi Maria, your Check-up & Cleaning appointment is confirmed for 10:00 AM.', NOW(), 'simulated_sent', NOW()),
(3, 11, 'email', 'reminder', 'patient@dentalclinic.test', 'Appointment reminder',
 'Reminder: you have a Check-up & Cleaning appointment tomorrow at 10:00 AM.', TIMESTAMP(DATE_ADD(@today, INTERVAL 2 DAY), '10:00:00'), 'queued', NULL),
(3, 11, 'sms', 'reminder', '09171234567', NULL,
 'Dental Clinic: reminder of your appointment tomorrow at 10:00 AM.', TIMESTAMP(DATE_ADD(@today, INTERVAL 2 DAY), '10:00:00'), 'queued', NULL);

INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES
(2, 'check_in', 'Checked in appointment #1 as queue number 1', '127.0.0.1'),
(2, 'queue_call', 'Called queue number 2', '127.0.0.1');

INSERT INTO clinic_settings (setting_key, setting_value, description) VALUES
('clinic_name',             'Dental Clinic Online',  'Name shown in the header'),
('clinic_address',          'Taguig City, Metro Manila', 'Clinic address'),
('clinic_phone',            '(02) 8123 4567',        'Clinic phone number'),
('clinic_email',            'info@dentalclinic.test','Clinic email'),
('slot_interval_minutes',   '30',                    'Gap between possible start times'),
('booking_days_ahead',      '60',                    'How many days ahead patients can book'),
('min_hours_before_change', '2',                     'Patients cannot cancel or reschedule closer than this many hours'),
('reminder_hours_before',   '24',                    'Hours before the appointment to queue a reminder'),
('currency_symbol',         '₱',                     'Currency symbol shown with prices');
