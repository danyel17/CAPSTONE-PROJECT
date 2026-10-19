-- =====================================================
-- FIX: Show ALL Applications in Admin Panel
-- Run this to ensure all applications are visible
-- =====================================================

-- Step 1: Ensure all required columns exist
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS is_reviewed TINYINT(1) DEFAULT 0;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS review_status ENUM('pending', 'approved', 'incomplete') DEFAULT 'pending';
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS reviewed_by INT NULL;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS reviewed_date DATETIME NULL;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS admin_notes TEXT NULL;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS submitted_date DATETIME NULL;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS application_type ENUM('new', 'renewal') DEFAULT 'new';

-- Step 2: Set default values for NULL columns to ensure queries work
UPDATE vendors SET is_reviewed = 0 WHERE is_reviewed IS NULL;
UPDATE vendors SET review_status = 'pending' WHERE review_status IS NULL;
UPDATE vendors SET application_type = 'new' WHERE application_type IS NULL;
UPDATE vendors SET submitted_date = NOW() WHERE submitted_date IS NULL;

-- Step 3: Ensure all vendors have valid user_id (check for orphaned records)
-- This query shows vendors without matching users (these won't show in admin panel)
SELECT v.vendor_id, v.vendor_name, v.user_id, u.user_id as user_exists
FROM vendors v
LEFT JOIN users u ON v.user_id = u.user_id
WHERE u.user_id IS NULL;

-- Step 4: Fix status for all vendors - ensure pending applications show up
UPDATE vendors 
SET status = 'pending',
    review_status = 'pending',
    is_reviewed = 0
WHERE status IS NULL OR status = '';

-- Step 5: Show all vendors with their user information
SELECT 
    v.vendor_id,
    v.vendor_name,
    v.section,
    v.block,
    v.status,
    v.application_type,
    v.submitted_date,
    v.user_id,
    u.email,
    u.full_name as user_name,
    CASE 
        WHEN u.user_id IS NULL THEN 'NO USER MATCH'
        ELSE 'OK'
    END as user_status
FROM vendors v
LEFT JOIN users u ON v.user_id = u.user_id
ORDER BY v.submitted_date DESC, v.vendor_id DESC;

-- Step 6: Count applications by status
SELECT 
    status,
    COUNT(*) as count
FROM vendors
GROUP BY status;

-- Step 7: Count applications with and without matching users
SELECT 
    COUNT(*) as total_vendors,
    SUM(CASE WHEN u.user_id IS NOT NULL THEN 1 ELSE 0 END) as with_users,
    SUM(CASE WHEN u.user_id IS NULL THEN 1 ELSE 0 END) as without_users
FROM vendors v
LEFT JOIN users u ON v.user_id = u.user_id;

-- Step 8: Show recent applications (last 20)
SELECT 
    v.vendor_id,
    v.vendor_name,
    v.section,
    v.status,
    v.submitted_date,
    u.email,
    u.full_name
FROM vendors v
LEFT JOIN users u ON v.user_id = u.user_id
ORDER BY v.submitted_date DESC
LIMIT 20;
