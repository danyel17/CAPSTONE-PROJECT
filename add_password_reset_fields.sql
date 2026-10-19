-- =====================================================
-- SIMPLE SQL TO ADD PASSWORD RESET FIELDS
-- Run this in phpMyAdmin - Select 'daet_market_db' database first
-- =====================================================

USE daet_market_db;

ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN reset_token_expires DATETIME NULL;
CREATE INDEX idx_reset_token ON users(reset_token);
