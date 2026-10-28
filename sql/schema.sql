SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs, queue_tokens, appointments, staff_schedules, blackout_dates, services, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role ENUM('admin', 'staff', 'client') NOT NULL DEFAULT 'client',
    first_name VARCHAR(60) NOT NULL,
    last_name VARCHAR(60) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    phone VARCHAR(25) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_verified TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    department VARCHAR(80) NOT NULL DEFAULT 'General Dentistry',
    duration_minutes INT UNSIGNED NOT NULL DEFAULT 30,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE staff_schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id INT UNSIGNED NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    break_start TIME NULL,
    break_end TIME NULL,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE blackout_dates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    closed_date DATE NOT NULL UNIQUE,
    reason VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE appointments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_no VARCHAR(32) NOT NULL UNIQUE,
    client_id INT UNSIGNED NOT NULL,
    staff_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    appointment_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') NOT NULL DEFAULT 'approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id),
    FOREIGN KEY (staff_id) REFERENCES users(id),
    FOREIGN KEY (service_id) REFERENCES services(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE queue_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token_number VARCHAR(16) NOT NULL,
    appointment_id INT UNSIGNED NOT NULL UNIQUE,
    queue_date DATE NOT NULL,
    priority_order INT UNSIGNED NOT NULL DEFAULT 0,
    queue_status ENUM('waiting', 'calling', 'serving', 'served', 'no_show', 'cancelled') NOT NULL DEFAULT 'waiting',
    called_at DATETIME NULL,
    served_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
