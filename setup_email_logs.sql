-- Email Logs Table for Tracking Email Sending Attempts
-- This table helps debug email sending issues and track email delivery

CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255) NULL,
    email_type VARCHAR(50) NOT NULL DEFAULT 'password_reset',
    subject VARCHAR(255) NOT NULL,
    status ENUM('pending', 'sent', 'failed', 'error') NOT NULL DEFAULT 'pending',
    error_message TEXT NULL,
    error_details TEXT NULL,
    smtp_host VARCHAR(255) NULL,
    smtp_username VARCHAR(255) NULL,
    attempts INT DEFAULT 1,
    sent_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_recipient (recipient_email),
    INDEX idx_status (status),
    INDEX idx_email_type (email_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clean up old logs (older than 30 days) - run periodically
-- DELETE FROM email_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);






