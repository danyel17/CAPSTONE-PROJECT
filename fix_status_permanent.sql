-- =====================================================
-- PERMANENT STATUS FIX - Run this in MySQL/MariaDB
-- This will fix the status persistence issue at database level
-- =====================================================

-- Step 1: Drop existing trigger if it exists
DROP TRIGGER IF EXISTS prevent_status_reset_to_pending;

-- Step 2: Create ABSOLUTE trigger that prevents ANY reset to pending
DELIMITER $$

CREATE TRIGGER prevent_status_reset_to_pending 
BEFORE UPDATE ON vendors 
FOR EACH ROW 
BEGIN
    -- Layer 1: If vendor was reviewed, NEVER allow status to become pending
    IF (OLD.status IN ('approved', 'incomplete') 
        OR OLD.is_reviewed = 1 
        OR OLD.review_status IN ('approved', 'incomplete') 
        OR OLD.reviewed_by IS NOT NULL 
        OR EXISTS(SELECT 1 FROM approval_actions WHERE vendor_id = OLD.vendor_id AND action_type IN ('approve', 'incomplete')))
        AND NEW.status = 'pending' 
    THEN 
        -- Force status to match review_status or keep old status
        SET NEW.status = COALESCE(OLD.review_status, OLD.status, 
            CASE WHEN OLD.admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END),
        NEW.review_status = COALESCE(OLD.review_status, OLD.status,
            CASE WHEN OLD.admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END),
        NEW.is_reviewed = 1,
        NEW.reviewed_date = COALESCE(OLD.reviewed_date, NOW()),
        NEW.reviewed_by = COALESCE(OLD.reviewed_by, NEW.reviewed_by),
        NEW.admin_notes = COALESCE(OLD.admin_notes, NEW.admin_notes);
    END IF;
    
    -- Layer 2: ALWAYS ensure status matches review_status if review_status is set
    IF NEW.review_status IS NOT NULL AND NEW.review_status != 'pending' AND NEW.status != NEW.review_status THEN
        SET NEW.status = NEW.review_status;
    END IF;
    
    -- Layer 3: ALWAYS ensure is_reviewed=1 if reviewed_by is set
    IF NEW.reviewed_by IS NOT NULL AND NEW.reviewed_date IS NOT NULL THEN
        SET NEW.is_reviewed = 1;
        IF NEW.review_status IS NULL OR NEW.review_status = 'pending' THEN
            SET NEW.review_status = CASE WHEN NEW.admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END;
            SET NEW.status = NEW.review_status;
        END IF;
    END IF;
    
    -- Layer 4: If is_reviewed=1, status CANNOT be pending
    IF NEW.is_reviewed = 1 AND NEW.status = 'pending' THEN
        SET NEW.status = COALESCE(NEW.review_status, OLD.review_status, 
            CASE WHEN NEW.admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' 
                 WHEN OLD.admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' 
                 ELSE 'approved' END);
        SET NEW.review_status = NEW.status;
    END IF;
END$$

DELIMITER ;

-- Step 3: Create stored procedure to fix ALL statuses
DELIMITER $$

CREATE PROCEDURE fix_all_vendor_statuses()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1
            @errno = MYSQL_ERRNO, @errmsg = MESSAGE_TEXT;
        SELECT CONCAT('Error: ', @errno, ' - ', @errmsg) AS error;
    END;
    
    -- Fix 1: Use review_status as source of truth
    UPDATE vendors 
    SET status = review_status, 
        is_reviewed = 1
    WHERE review_status IS NOT NULL 
    AND review_status != 'pending' 
    AND status != review_status;
    
    -- Fix 2: Use reviewed_by/reviewed_date to set status
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
    
    -- Fix 3: Use approval_actions as absolute source
    UPDATE vendors v
    INNER JOIN (
        SELECT vendor_id, 
               MAX(CASE WHEN action_type = 'approve' THEN 'approved' 
                        WHEN action_type = 'incomplete' THEN 'incomplete' 
                        ELSE NULL END) as correct_status,
               MAX(action_date) as last_action_date
        FROM approval_actions
        WHERE action_type IN ('approve', 'incomplete')
        GROUP BY vendor_id
    ) aa ON v.vendor_id = aa.vendor_id
    SET v.status = aa.correct_status,
        v.review_status = aa.correct_status,
        v.is_reviewed = 1,
        v.reviewed_date = COALESCE(v.reviewed_date, aa.last_action_date)
    WHERE aa.correct_status IS NOT NULL
    AND (v.status != aa.correct_status OR v.review_status != aa.correct_status);
    
    -- Fix 4: Force ALL vendors with is_reviewed=1
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
    
    SELECT 'All vendor statuses have been fixed!' AS result;
END$$

DELIMITER ;

-- Step 4: Create event scheduler to run fix procedure every minute (optional but recommended)
-- First, enable event scheduler if not already enabled
SET GLOBAL event_scheduler = ON;

-- Drop event if exists
DROP EVENT IF EXISTS auto_fix_vendor_statuses;

-- Create event that runs every minute
DELIMITER $$

CREATE EVENT auto_fix_vendor_statuses
ON SCHEDULE EVERY 1 MINUTE
DO
BEGIN
    CALL fix_all_vendor_statuses();
END$$

DELIMITER ;

-- Step 5: Run the fix procedure immediately
CALL fix_all_vendor_statuses();

-- Step 6: Verify the fix worked
SELECT 
    vendor_id,
    vendor_name,
    status,
    review_status,
    is_reviewed,
    reviewed_by,
    reviewed_date,
    CASE 
        WHEN review_status IS NOT NULL AND review_status != 'pending' THEN review_status
        WHEN is_reviewed = 1 AND reviewed_by IS NOT NULL THEN 
            CASE WHEN admin_notes LIKE '%Incomplete Reason%' THEN 'incomplete' ELSE 'approved' END
        ELSE status
    END as correct_status
FROM vendors
WHERE reviewed_by IS NOT NULL
ORDER BY vendor_id DESC
LIMIT 20;

-- Step 7: Show count of fixed vs pending
SELECT 
    COUNT(*) as total_vendors,
    SUM(CASE WHEN status = 'pending' AND reviewed_by IS NOT NULL THEN 1 ELSE 0 END) as incorrectly_pending,
    SUM(CASE WHEN status IN ('approved', 'incomplete') AND reviewed_by IS NOT NULL THEN 1 ELSE 0 END) as correctly_reviewed
FROM vendors;
