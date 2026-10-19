-- =====================================================
-- SIMPLE VERSION - ADD PASSWORD RESET FIELDS
-- If the above script doesn't work, use this simpler version
-- =====================================================

USE daet_market_db;

-- Add reset_token column
ALTER TABLE users 
ADD COLUMN reset_token VARCHAR(255) NULL;

-- Add reset_token_expires column
ALTER TABLE users 
ADD COLUMN reset_token_expires DATETIME NULL;

-- Create index for faster lookups
CREATE INDEX idx_reset_token ON users(reset_token);

-- Verify
SELECT 'Password reset fields added successfully!' AS status;













