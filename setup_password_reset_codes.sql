-- Password Reset Codes Table Setup
-- Run this SQL to ensure the password_reset_codes table exists with proper structure

CREATE TABLE IF NOT EXISTS password_reset_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    code VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME NULL,
    INDEX idx_email (email),
    INDEX idx_code (code),
    INDEX idx_expires (expires_at),
    INDEX idx_email_expires (email, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clean up old expired codes (optional - can be run periodically)
DELETE FROM password_reset_codes WHERE expires_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);










