-- Update Admin Email from admin@gmail.com to daetpublicmarket@gmail.com
-- Run this SQL script to update the existing admin account email

START TRANSACTION;

-- Update the admin user email
UPDATE users 
SET email = 'daetpublicmarket@gmail.com' 
WHERE email = 'admin@gmail.com' AND user_type = 'admin';

-- Verify the update
SELECT user_id, email, full_name, user_type, status 
FROM users 
WHERE email = 'daetpublicmarket@gmail.com' AND user_type = 'admin';

COMMIT;

-- After running this script:
--   - Admin login email is now: daetpublicmarket@gmail.com
--   - Password remains the same (password)
--   - Forgot password will send emails to daetpublicmarket@gmail.com






