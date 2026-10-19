-- =====================================================
-- EMERGENCY FIX: Make adminpanel.php display data
-- Run this IMMEDIATELY to fix display issues
-- =====================================================

-- Step 1: Check if vendors table exists
SELECT COUNT(*) as vendor_count FROM vendors;

-- Step 2: Check if users table exists  
SELECT COUNT(*) as user_count FROM users;

-- Step 3: Show ALL vendors (this is what should display)
SELECT * FROM vendors ORDER BY vendor_id DESC;

-- Step 4: Show ALL users (this is what should display)
SELECT * FROM users ORDER BY user_id DESC;

-- Step 5: Ensure vendors table has all required columns
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS vendor_name VARCHAR(255) NULL;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS section VARCHAR(100) NULL;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS block VARCHAR(100) NULL;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS status ENUM('pending', 'approved', 'incomplete', 'rejected') DEFAULT 'pending';
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS application_type ENUM('new', 'renewal') DEFAULT 'new';
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS submitted_date DATETIME NULL;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS user_id INT NULL;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS is_reviewed TINYINT(1) DEFAULT 0;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS review_status ENUM('pending', 'approved', 'incomplete') DEFAULT 'pending';

-- Step 6: Set defaults for NULL values
UPDATE vendors SET status = 'pending' WHERE status IS NULL OR status = '';
UPDATE vendors SET application_type = 'new' WHERE application_type IS NULL;
UPDATE vendors SET submitted_date = COALESCE(submitted_date, NOW()) WHERE submitted_date IS NULL;
UPDATE vendors SET is_reviewed = 0 WHERE is_reviewed IS NULL;
UPDATE vendors SET review_status = 'pending' WHERE review_status IS NULL;

-- Step 7: Test the EXACT query adminpanel.php uses
SELECT * FROM vendors ORDER BY vendor_id DESC;

-- Step 8: If vendors exist but don't display, check for PHP errors
-- Look for errors in PHP error log or browser console

-- Step 9: Verify data integrity
SELECT 
    'Vendors' as table_name,
    COUNT(*) as count,
    CASE WHEN COUNT(*) > 0 THEN '✓ HAS DATA' ELSE '✗ EMPTY' END as status
FROM vendors
UNION ALL
SELECT 
    'Users',
    COUNT(*),
    CASE WHEN COUNT(*) > 0 THEN '✓ HAS DATA' ELSE '✗ EMPTY' END
FROM users;

-- Step 10: Show sample data
SELECT vendor_id, vendor_name, status, user_id FROM vendors LIMIT 10;
SELECT user_id, email, full_name FROM users LIMIT 10;
