-- =====================================================
-- FIX: Display ALL Vendors, Records, and Receipts in Admin Panel
-- Run this to ensure everything displays correctly
-- =====================================================

-- Step 1: Ensure all required columns exist in vendors table
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS is_reviewed TINYINT(1) DEFAULT 0;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS review_status ENUM('pending', 'approved', 'incomplete') DEFAULT 'pending';
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS reviewed_by INT NULL;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS reviewed_date DATETIME NULL;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS admin_notes TEXT NULL;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS submitted_date DATETIME NULL;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS application_type ENUM('new', 'renewal') DEFAULT 'new';
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS status ENUM('pending', 'approved', 'incomplete', 'rejected') DEFAULT 'pending';
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS vendor_name VARCHAR(255) NULL;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS section VARCHAR(100) NULL;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS block VARCHAR(100) NULL;
ALTER TABLE vendors ADD COLUMN IF NOT EXISTS user_id INT NULL;

-- Step 2: Set default values for NULL columns
UPDATE vendors SET is_reviewed = 0 WHERE is_reviewed IS NULL;
UPDATE vendors SET review_status = 'pending' WHERE review_status IS NULL;
UPDATE vendors SET application_type = 'new' WHERE application_type IS NULL;
UPDATE vendors SET status = 'pending' WHERE status IS NULL OR status = '';
UPDATE vendors SET submitted_date = COALESCE(submitted_date, NOW()) WHERE submitted_date IS NULL;

-- Step 3: Ensure users table has required columns
ALTER TABLE users ADD COLUMN IF NOT EXISTS full_name VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone_number VARCHAR(50) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'active';
ALTER TABLE users ADD COLUMN IF NOT EXISTS created_at DATETIME NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login DATETIME NULL;

-- Step 4: Set default values for users
UPDATE users SET status = 'active' WHERE status IS NULL OR status = '';
UPDATE users SET created_at = COALESCE(created_at, NOW()) WHERE created_at IS NULL;

-- Step 5: Ensure approval_actions table exists (for activity logs)
CREATE TABLE IF NOT EXISTS approval_actions (
    action_id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    admin_id INT NULL,
    action_type ENUM('approve', 'incomplete', 'reject') NOT NULL,
    admin_notes TEXT NULL,
    rejection_reason TEXT NULL,
    status_before VARCHAR(50) NULL,
    status_after VARCHAR(50) NULL,
    action_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_admin_id (admin_id),
    INDEX idx_action_date (action_date),
    INDEX idx_action_type (action_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 6: Ensure receipts table exists
CREATE TABLE IF NOT EXISTS receipts (
    receipt_id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NULL,
    user_id INT NULL,
    receipt_number VARCHAR(100) NULL,
    amount DECIMAL(10,2) NULL,
    receipt_date DATE NULL,
    status VARCHAR(50) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_user_id (user_id),
    INDEX idx_receipt_date (receipt_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Step 7: Show ALL vendors (this is what adminpanel should display)
SELECT 
    v.vendor_id,
    v.vendor_name,
    v.section,
    v.block,
    v.status,
    v.application_type,
    v.submitted_date,
    v.user_id,
    COALESCE(u.email, '') as email,
    COALESCE(u.full_name, 'Unknown User') as user_name,
    v.review_status,
    v.is_reviewed,
    v.reviewed_by,
    v.reviewed_date
FROM vendors v
LEFT JOIN users u ON v.user_id = u.user_id
ORDER BY COALESCE(v.submitted_date, v.vendor_id) DESC, v.vendor_id DESC;

-- Step 8: Show ALL users/records (this is what adminpanel should display)
SELECT 
    u.user_id,
    COALESCE(u.email, '') as email,
    COALESCE(u.full_name, 'Unknown User') as user_name,
    COALESCE(u.phone_number, '') as phone_number,
    COALESCE(u.created_at, NOW()) as member_since,
    COALESCE(u.status, 'active') as user_status,
    u.last_login
FROM users u
WHERE u.user_id IS NOT NULL
ORDER BY COALESCE(u.created_at, u.user_id) DESC;

-- Step 9: Show ALL receipts (this is what adminpanel should display)
SELECT 
    r.receipt_id,
    r.vendor_id,
    r.user_id,
    r.receipt_number,
    r.amount,
    r.receipt_date,
    r.status,
    r.created_at,
    COALESCE(v.vendor_name, 'Unknown Vendor') as vendor_name,
    COALESCE(u.full_name, 'Unknown User') as user_name
FROM receipts r
LEFT JOIN vendors v ON r.vendor_id = v.vendor_id
LEFT JOIN users u ON r.user_id = u.user_id
ORDER BY r.created_at DESC;

-- Step 10: Show ALL activity logs (this is what adminpanel should display)
SELECT 
    aa.action_id,
    aa.vendor_id,
    aa.admin_id,
    aa.action_type,
    aa.action_date,
    COALESCE(aa.admin_notes, '') as admin_notes,
    COALESCE(v.vendor_name, 'Unknown Vendor') as vendor_name,
    COALESCE(v.application_type, 'new') as application_type,
    COALESCE(admin_user.full_name, 'Unknown Admin') as admin_name,
    COALESCE(admin_user.email, '') as admin_email
FROM approval_actions aa
LEFT JOIN vendors v ON aa.vendor_id = v.vendor_id
LEFT JOIN users admin_user ON aa.admin_id = admin_user.user_id
WHERE aa.action_type IN ('approve', 'incomplete')
ORDER BY aa.action_date DESC;

-- Step 11: Diagnostic - Count everything
SELECT 
    'Vendors' as table_name,
    COUNT(*) as total_records
FROM vendors
UNION ALL
SELECT 
    'Users' as table_name,
    COUNT(*) as total_records
FROM users
UNION ALL
SELECT 
    'Receipts' as table_name,
    COUNT(*) as total_records
FROM receipts
UNION ALL
SELECT 
    'Approval Actions' as table_name,
    COUNT(*) as total_records
FROM approval_actions;

-- Step 12: Check for vendors without users (these should still display)
SELECT 
    v.vendor_id,
    v.vendor_name,
    v.status,
    v.user_id,
    CASE 
        WHEN u.user_id IS NULL THEN 'NO USER MATCH - Will show as Unknown User'
        ELSE 'HAS USER MATCH'
    END as user_status
FROM vendors v
LEFT JOIN users u ON v.user_id = u.user_id
WHERE u.user_id IS NULL
ORDER BY v.vendor_id DESC;

-- Step 13: Verify data integrity
SELECT 
    COUNT(*) as total_vendors,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
    SUM(CASE WHEN status = 'incomplete' THEN 1 ELSE 0 END) as incomplete_count,
    SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) as vendors_without_users,
    SUM(CASE WHEN user_id IS NOT NULL THEN 1 ELSE 0 END) as vendors_with_users
FROM vendors;

-- Step 14: Show recent vendors (last 20) - Quick check
SELECT 
    vendor_id,
    vendor_name,
    section,
    status,
    application_type,
    submitted_date,
    user_id
FROM vendors
ORDER BY vendor_id DESC
LIMIT 20;
