-- =====================================================
-- COMPLETE FIX: Show ALL Applications, Records, and Activity Logs
-- Run this to fix everything at once
-- =====================================================

-- Step 1: Ensure all columns exist
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS is_reviewed TINYINT(1) DEFAULT 0;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS review_status ENUM('pending', 'approved', 'incomplete') DEFAULT 'pending';
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS reviewed_by INT NULL;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS reviewed_date DATETIME NULL;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS admin_notes TEXT NULL;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS submitted_date DATETIME NULL;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS application_type ENUM('new', 'renewal') DEFAULT 'new';

-- Step 2: Set default values
UPDATE vendors SET is_reviewed = 0 WHERE is_reviewed IS NULL;
UPDATE vendors SET review_status = 'pending' WHERE review_status IS NULL;
UPDATE vendors SET application_type = 'new' WHERE application_type IS NULL;
UPDATE vendors SET submitted_date = COALESCE(submitted_date, NOW()) WHERE submitted_date IS NULL;
UPDATE vendors SET status = 'pending' WHERE status IS NULL OR status = '';

-- Step 3: Show ALL vendors (should show everything)
SELECT 
    COUNT(*) as total_vendors,
    'Total vendors in database' as description
FROM vendors;

-- Step 4: Show vendors with and without users
SELECT 
    v.vendor_id,
    v.vendor_name,
    v.status,
    v.user_id,
    CASE 
        WHEN u.user_id IS NULL THEN 'NO USER MATCH'
        ELSE 'HAS USER'
    END as user_status,
    u.email,
    u.full_name
FROM vendors v
LEFT JOIN users u ON v.user_id = u.user_id
ORDER BY v.vendor_id DESC
LIMIT 50;

-- Step 5: Count by status
SELECT 
    status,
    COUNT(*) as count
FROM vendors
GROUP BY status;

-- Step 6: Show all users (records)
SELECT 
    COUNT(*) as total_users,
    'Total users in database' as description
FROM users;

-- Step 7: Show all activity logs
SELECT 
    COUNT(*) as total_activities,
    'Total activities in approval_actions' as description
FROM approval_actions;

-- Step 8: Show recent activities
SELECT 
    aa.action_id,
    aa.action_type,
    aa.action_date,
    v.vendor_name,
    u.full_name as admin_name
FROM approval_actions aa
LEFT JOIN vendors v ON aa.vendor_id = v.vendor_id
LEFT JOIN users u ON aa.admin_id = u.user_id
ORDER BY aa.action_date DESC
LIMIT 20;

-- Step 9: Verify data integrity
SELECT 
    'Vendors' as table_name,
    COUNT(*) as record_count
FROM vendors
UNION ALL
SELECT 
    'Users' as table_name,
    COUNT(*) as record_count
FROM users
UNION ALL
SELECT 
    'Approval Actions' as table_name,
    COUNT(*) as record_count
FROM approval_actions;
