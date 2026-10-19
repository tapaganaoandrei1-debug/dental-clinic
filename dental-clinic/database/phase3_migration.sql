USE dental_clinic;

ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified_at DATETIME DEFAULT NULL AFTER is_active;

ALTER TABLE notifications
    MODIFY type ENUM('confirmation','reminder','reschedule','cancellation','password_reset','email_verification') NOT NULL;

UPDATE users
SET email_verified_at = created_at
WHERE email_verified_at IS NULL AND password_hash <> 'RUN_SETUP_SCRIPT';

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
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
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
    CONSTRAINT fk_verification_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dental_sessions (
    session_id    VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    session_data  MEDIUMBLOB NOT NULL,
    last_activity INT UNSIGNED NOT NULL,
    PRIMARY KEY (session_id),
    KEY idx_dental_sessions_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;