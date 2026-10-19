-- Seed a default admin account and profile (safe to run multiple times)
-- Default credentials:
--   Email: daetpublicmarket@gmail.com
--   Password: password
-- The password is stored as a bcrypt hash (do NOT change the hash directly unless you know what you're doing)

START TRANSACTION;

-- Ensure the users table exists with a unique email index
-- This script assumes your schema already created `users` and `admin_profiles`.

-- Insert or update the default admin user
INSERT INTO users (email, password_hash, full_name, user_type, email_verified, status)
VALUES (
  'daetpublicmarket@gmail.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- bcrypt hash for: password
  'System Administrator',
  'admin',
  TRUE,
  'active'
)
ON DUPLICATE KEY UPDATE
  password_hash = VALUES(password_hash),
  user_type = 'admin',
  email_verified = TRUE,
  status = 'active',
  full_name = VALUES(full_name);

-- Ensure an admin profile exists for this admin (admin_id resolved dynamically)
INSERT INTO admin_profiles (admin_id, site_name)
SELECT u.user_id, 'Daet Public Market Clearance System'
FROM users u
WHERE u.email = 'daetpublicmarket@gmail.com'
ON DUPLICATE KEY UPDATE
  site_name = VALUES(site_name);

COMMIT;

-- After running, login using:
--   Email: daetpublicmarket@gmail.com
--   Password: password
-- Then change the credentials in the Admin Profile settings.



