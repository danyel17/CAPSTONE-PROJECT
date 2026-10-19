-- =====================================================
-- IMMEDIATE STATUS FIX - Run this RIGHT NOW to fix all statuses
-- Run this multiple times if needed
-- =====================================================

-- Fix ALL vendors immediately - Run this 10 times
UPDATE vendors 
SET status = review_status, 
    is_reviewed = 1
WHERE review_status IS NOT NULL 
AND review_status != 'pending' 
AND status != review_status;

UPDATE vendors 
SET status = CASE 
        WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete'
        ELSE 'approved'
    END,
    review_status = CASE 
        WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete'
        ELSE 'approved'
    END,
    is_reviewed = 1
WHERE reviewed_by IS NOT NULL 
AND reviewed_date IS NOT NULL
AND (status = 'pending' OR review_status IS NULL OR review_status = 'pending');

UPDATE vendors v
INNER JOIN (
    SELECT vendor_id, 
           MAX(CASE WHEN action_type = 'approve' THEN 'approved' 
                    WHEN action_type = 'incomplete' THEN 'incomplete' 
                    ELSE NULL END) as correct_status
    FROM approval_actions
    WHERE action_type IN ('approve', 'incomplete')
    GROUP BY vendor_id
) aa ON v.vendor_id = aa.vendor_id
SET v.status = aa.correct_status,
    v.review_status = aa.correct_status,
    v.is_reviewed = 1
WHERE aa.correct_status IS NOT NULL
AND (v.status != aa.correct_status OR v.review_status != aa.correct_status OR v.status = 'pending');

UPDATE vendors 
SET status = COALESCE(review_status, 
    CASE 
        WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete'
        ELSE 'approved'
    END),
review_status = COALESCE(review_status,
    CASE 
        WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete'
        ELSE 'approved'
    END)
WHERE is_reviewed = 1 
AND (status = 'pending' OR review_status IS NULL OR review_status = 'pending');

-- Check results
SELECT 
    vendor_id,
    vendor_name,
    status as current_status,
    review_status,
    is_reviewed,
    reviewed_by,
    CASE 
        WHEN review_status IS NOT NULL AND review_status != 'pending' THEN review_status
        WHEN is_reviewed = 1 AND reviewed_by IS NOT NULL THEN 'should_be_reviewed'
        ELSE status
    END as should_be_status
FROM vendors
WHERE reviewed_by IS NOT NULL
ORDER BY vendor_id DESC;
