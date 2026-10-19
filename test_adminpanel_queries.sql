-- =====================================================
-- TEST QUERIES: Exact queries used in adminpanel.php
-- Run these to verify data will display correctly
-- =====================================================

-- Test 1: Recent Applications Query (exact copy from adminpanel.php)
SELECT v.*, 
       COALESCE(u.email, '') as email, 
       COALESCE(u.full_name, 'Unknown User') as user_name,
       COALESCE(v.status, 'pending') as display_status,
       COALESCE(v.status, 'pending') as original_status
FROM vendors v
LEFT JOIN users u ON v.user_id = u.user_id
ORDER BY v.vendor_id DESC;

-- Test 2: Vendors Query (exact copy from adminpanel.php)
SELECT v.*, 
       COALESCE(u.email, '') as email, 
       COALESCE(u.full_name, 'Unknown User') as user_name,
       CASE 
           WHEN v.review_status IS NOT NULL AND v.review_status != 'pending' THEN v.review_status
           WHEN v.is_reviewed = 1 AND v.reviewed_by IS NOT NULL THEN 
               CASE WHEN v.admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END
           ELSE COALESCE(v.status, 'pending')
       END as display_status,
       COALESCE(v.is_reviewed, 0) as is_reviewed,
       COALESCE(v.review_status, 'pending') as review_status,
       COALESCE(v.status, 'pending') as original_status
FROM vendors v
LEFT JOIN users u ON v.user_id = u.user_id
WHERE v.vendor_id IS NOT NULL
ORDER BY COALESCE(v.submitted_date, v.vendor_id) DESC, v.vendor_id DESC;

-- Test 3: Records Query (exact copy from adminpanel.php)
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

-- Test 4: Receipts Query (exact copy from adminpanel.php)
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

-- Test 5: Activity Log Query (exact copy from adminpanel.php)
SELECT 
    aa.action_id,
    aa.vendor_id,
    aa.admin_id,
    aa.action_type,
    aa.admin_notes,
    aa.rejection_reason,
    aa.status_before,
    aa.status_after,
    aa.action_date,
    aa.ip_address,
    COALESCE(admin_user.full_name, 'Unknown Admin') as admin_name,
    COALESCE(admin_user.email, '') as admin_email,
    COALESCE(v.vendor_name, 'Unknown Vendor') as vendor_name,
    COALESCE(v.application_type, 'new') as application_type,
    COALESCE(v.section, '') as section,
    COALESCE(v.block, '') as block,
    COALESCE(u.full_name, 'Unknown User') as user_name,
    COALESCE(u.email, '') as user_email
FROM approval_actions aa
LEFT JOIN users admin_user ON aa.admin_id = admin_user.user_id
LEFT JOIN vendors v ON aa.vendor_id = v.vendor_id
LEFT JOIN users u ON v.user_id = u.user_id
WHERE aa.action_id IS NOT NULL
ORDER BY COALESCE(aa.action_date, aa.action_id) DESC;

-- Test 6: Count queries (for statistics)
SELECT COUNT(*) as total_vendors FROM vendors;
SELECT COUNT(*) as total_users FROM users;
SELECT COUNT(*) as total_receipts FROM receipts;
SELECT COUNT(*) as total_activities FROM approval_actions;

-- Test 7: Check if data exists
SELECT 
    'Vendors' as section,
    COUNT(*) as count,
    CASE WHEN COUNT(*) > 0 THEN '✓ DATA EXISTS' ELSE '✗ NO DATA' END as status
FROM vendors
UNION ALL
SELECT 
    'Users' as section,
    COUNT(*) as count,
    CASE WHEN COUNT(*) > 0 THEN '✓ DATA EXISTS' ELSE '✗ NO DATA' END as status
FROM users
UNION ALL
SELECT 
    'Receipts' as section,
    COUNT(*) as count,
    CASE WHEN COUNT(*) > 0 THEN '✓ DATA EXISTS' ELSE '✗ NO DATA' END as status
FROM receipts
UNION ALL
SELECT 
    'Activities' as section,
    COUNT(*) as count,
    CASE WHEN COUNT(*) > 0 THEN '✓ DATA EXISTS' ELSE '✗ NO DATA' END as status
FROM approval_actions;
