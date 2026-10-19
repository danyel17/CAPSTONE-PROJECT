-- Create or Update Admin User for Daet Public Market
-- Email: daetpublicmarket@gmail.com
-- Default Password: password (change after first login)

START TRANSACTION;

-- Check if admin user exists
SELECT user_id, email, user_type, status, email_verified 
FROM users 
WHERE email = 'daetpublicmarket@gmail.com' AND user_type = 'admin';

-- Insert or update admin user
INSERT INTO users (email, password_hash, full_name, user_type, status, email_verified)
VALUES (
    'daetpublicmarket@gmail.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- bcrypt hash for: password
    'Admin',
    'admin',
    'active',
    1
)
ON DUPLICATE KEY UPDATE
    password_hash = VALUES(password_hash),
    user_type = 'admin',
    status = 'active',
    email_verified = 1,
    full_name = COALESCE(full_name, 'Admin');

-- Verify the admin user was created/updated
SELECT user_id, email, full_name, user_type, status, email_verified 
FROM users 
WHERE email = 'daetpublicmarket@gmail.com' AND user_type = 'admin';

COMMIT;

-- After running this script:
--   - Admin email: daetpublicmarket@gmail.com
--   - Password: password
--   - Status: active
--   - Email verified: Yes
--   - User type: admin



