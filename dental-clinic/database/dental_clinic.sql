-- Dental Clinic schema and safe starter data.
-- Safe to import more than once: existing tables and rows are preserved.
SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS dental_clinic
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE dental_clinic;

CREATE TABLE IF NOT EXISTS users (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS patient_profiles (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_profiles (
    user_id    INT UNSIGNED NOT NULL,
    position   VARCHAR(80) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_staff_profiles_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dentists (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name  VARCHAR(100) NOT NULL,
    specialty  VARCHAR(100) DEFAULT NULL,
    email      VARCHAR(150) DEFAULT NULL,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name             VARCHAR(100) NOT NULL,
    category         VARCHAR(60) DEFAULT NULL,
    description      TEXT DEFAULT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    price            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active        TINYINT(1) NOT NULL DEFAULT 1,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_services_name (name),
    KEY idx_services_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dentist_services (
    dentist_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (dentist_id, service_id),
    CONSTRAINT fk_ds_dentist FOREIGN KEY (dentist_id)
        REFERENCES dentists (id) ON DELETE CASCADE,
    CONSTRAINT fk_ds_service FOREIGN KEY (service_id)
        REFERENCES services (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedules (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    dentist_id  INT UNSIGNED NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL,
    start_time  TIME NOT NULL,
    end_time    TIME NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schedule_dentist_day (dentist_id, day_of_week),
    CONSTRAINT fk_schedules_dentist FOREIGN KEY (dentist_id)
        REFERENCES dentists (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedule_breaks (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    schedule_id INT UNSIGNED NOT NULL,
    break_start TIME NOT NULL,
    break_end   TIME NOT NULL,
    label       VARCHAR(60) DEFAULT 'Break',
    PRIMARY KEY (id),
    KEY idx_breaks_schedule (schedule_id),
    CONSTRAINT fk_breaks_schedule FOREIGN KEY (schedule_id)
        REFERENCES schedules (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS holidays (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    holiday_date DATE NOT NULL,
    description  VARCHAR(150) NOT NULL,
    dentist_id   INT UNSIGNED DEFAULT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_holidays_date (holiday_date),
    CONSTRAINT fk_holidays_dentist FOREIGN KEY (dentist_id)
        REFERENCES dentists (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointments (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    patient_id          INT UNSIGNED NOT NULL,
    service_id          INT UNSIGNED NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS queue_entries (
    id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    appointment_id         INT UNSIGNED NOT NULL,
    queue_number           SMALLINT UNSIGNED NOT NULL,
    queue_date             DATE NOT NULL,
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
    CONSTRAINT fk_queue_appointment FOREIGN KEY (appointment_id)
        REFERENCES appointments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED NOT NULL,
    appointment_id INT UNSIGNED DEFAULT NULL,
    channel        ENUM('email','sms') NOT NULL,
    type           ENUM('confirmation','reminder','reschedule','cancellation','password_reset','email_verification') NOT NULL,
    recipient      VARCHAR(150) NOT NULL,
    subject        VARCHAR(150) DEFAULT NULL,
    message        TEXT DEFAULT NULL,
    scheduled_for  DATETIME NOT NULL,
    status         ENUM('queued','simulated_sent','sent','failed') NOT NULL DEFAULT 'queued',
    sent_at        DATETIME DEFAULT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_status_time (status, scheduled_for),
    KEY idx_notif_user (user_id),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_appt FOREIGN KEY (appointment_id)
        REFERENCES appointments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reset_token_hash (token_hash),
    KEY idx_reset_user_expiry (user_id, used_at, expires_at),
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_verification_token_hash (token_hash),
    KEY idx_verification_user_expiry (user_id, used_at, expires_at),
    CONSTRAINT fk_verification_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_logs (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED DEFAULT NULL,
    action     VARCHAR(60) NOT NULL,
    details    VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_logs_user (user_id),
    KEY idx_logs_created (created_at),
    CONSTRAINT fk_logs_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clinic_settings (
    setting_key   VARCHAR(60) NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    description   VARCHAR(150) DEFAULT NULL,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dental_sessions (
    session_id    VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    session_data  MEDIUMBLOB NOT NULL,
    last_activity INT UNSIGNED NOT NULL,
    PRIMARY KEY (session_id),
    KEY idx_dental_sessions_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO users (id, role, full_name, email, phone, password_hash) VALUES
(1, 'admin', 'Clinic Administrator', 'admin@dentalclinic.test', '09170000001', 'RUN_SETUP_SCRIPT'),
(2, 'staff', 'Front Desk Staff', 'staff@dentalclinic.test', '09170000002', 'RUN_SETUP_SCRIPT'),
(3, 'patient', 'Maria Cruz', 'patient@dentalclinic.test', '09171234567', 'RUN_SETUP_SCRIPT'),
(4, 'patient', 'Ana Reyes', 'ana.reyes@example.com', '09171110004', 'RUN_SETUP_SCRIPT'),
(5, 'patient', 'Joel Santos', 'joel.santos@example.com', '09171110005', 'RUN_SETUP_SCRIPT'),
(6, 'patient', 'Liza Moreno', 'liza.moreno@example.com', '09171110006', 'RUN_SETUP_SCRIPT'),
(7, 'patient', 'Ben Torres', 'ben.torres@example.com', '09171110007', 'RUN_SETUP_SCRIPT'),
(8, 'patient', 'Carla Dizon', 'carla.dizon@example.com', '09171110008', 'RUN_SETUP_SCRIPT');

INSERT IGNORE INTO patient_profiles (user_id, birth_date, gender, address, emergency_contact_name, emergency_contact_phone, medical_notes) VALUES
(3, '1994-03-12', 'female', 'Taguig City', 'Pedro Cruz', '09175550001', NULL),
(4, '1988-07-25', 'female', 'Makati City', 'Luis Reyes', '09175550002', NULL),
(5, '1979-11-02', 'male', 'Pasig City', 'Rosa Santos', '09175550003', 'Allergic to penicillin'),
(6, '1999-01-18', 'female', 'Quezon City', 'Ramon Moreno', '09175550004', NULL),
(7, '2001-09-30', 'male', 'Mandaluyong City', 'Elena Torres', '09175550005', NULL),
(8, '1992-05-09', 'female', 'Paranaque City', 'Mario Dizon', '09175550006', NULL);

INSERT IGNORE INTO staff_profiles (user_id, position) VALUES
(1, 'Clinic Manager'),
(2, 'Front Desk');

INSERT IGNORE INTO dentists (id, full_name, specialty, email) VALUES
(1, 'Dr. Elena Ramos', 'General Dentistry', 'ramos@dentalclinic.test'),
(2, 'Dr. Marco Villanueva', 'Oral Surgery', 'villanueva@dentalclinic.test'),
(3, 'Dr. Sofia Lim', 'Orthodontics and Cosmetic', 'lim@dentalclinic.test');

INSERT IGNORE INTO services (id, name, category, description, duration_minutes, price, is_active) VALUES
(1, 'Check-up & Cleaning', 'General Dentistry', 'Oral exam and professional teeth cleaning.', 30, 800.00, 1),
(2, 'Tooth Extraction', 'Oral Surgery', 'Removal of a damaged or problem tooth.', 60, 1500.00, 1),
(3, 'Braces Consultation', 'Orthodontics', 'Evaluation and treatment plan for braces.', 30, 500.00, 1),
(4, 'Teeth Whitening', 'Cosmetic', 'In-clinic professional teeth whitening.', 60, 4500.00, 1),
(5, 'Dental Filling', 'General Dentistry', 'Filling for a cavity.', 60, 1200.00, 1),
(6, 'Root Canal Treatment', 'Oral Surgery', 'Currently not offered.', 90, 6000.00, 0);

INSERT IGNORE INTO dentist_services (dentist_id, service_id) VALUES
(1, 1), (1, 5), (2, 1), (2, 2), (2, 6), (3, 3), (3, 4);

INSERT IGNORE INTO schedules (dentist_id, day_of_week, start_time, end_time) VALUES
(1, 1, '09:00:00', '17:00:00'), (1, 2, '09:00:00', '17:00:00'),
(1, 3, '09:00:00', '17:00:00'), (1, 4, '09:00:00', '17:00:00'),
(1, 5, '09:00:00', '17:00:00'), (1, 6, '09:00:00', '17:00:00'),
(2, 1, '09:00:00', '17:00:00'), (2, 3, '09:00:00', '17:00:00'),
(2, 5, '09:00:00', '17:00:00'),
(3, 2, '09:00:00', '15:00:00'), (3, 4, '09:00:00', '15:00:00'),
(3, 6, '09:00:00', '15:00:00');

INSERT INTO schedule_breaks (schedule_id, break_start, break_end, label)
SELECT sc.id, '12:00:00', '13:00:00', 'Lunch break'
FROM schedules sc
WHERE NOT EXISTS (
    SELECT 1 FROM schedule_breaks sb
    WHERE sb.schedule_id = sc.id AND sb.label = 'Lunch break'
);

INSERT IGNORE INTO clinic_settings (setting_key, setting_value, description) VALUES
('clinic_name', 'Dental Clinic Online', 'Name shown in the header'),
('clinic_address', 'Taguig City, Metro Manila', 'Clinic address'),
('clinic_phone', '(02) 8123 4567', 'Clinic phone number'),
('clinic_email', 'info@dentalclinic.test', 'Clinic email'),
('slot_interval_minutes', '30', 'Gap between possible start times'),
('booking_days_ahead', '60', 'How many days ahead patients can book'),
('min_hours_before_change', '2', 'Minimum hours before a patient can change an appointment'),
('reminder_hours_before', '24', 'Hours before an appointment to queue a reminder'),
('currency_symbol', '₱', 'Currency symbol shown with prices');
