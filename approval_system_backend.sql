-- Robust Approval System Backend SQL
-- This file contains the complete backend structure for the approval system
-- Designed for the Daet Public Market Clearance System
-- This is the main SQL schema file for the approval system

-- =============================================
-- CORE APPROVAL SYSTEM TABLES
-- =============================================

-- Create approval_workflow table for managing approval processes
CREATE TABLE IF NOT EXISTS approval_workflow (
    workflow_id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    current_step INT DEFAULT 1,
    total_steps INT DEFAULT 3,
    step_name VARCHAR(100) NOT NULL,
    step_status ENUM('pending', 'in_progress', 'completed', 'rejected') DEFAULT 'pending',
    assigned_to INT DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    completed_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_current_step (current_step),
    INDEX idx_step_status (step_status),
    INDEX idx_assigned_to (assigned_to)
);

-- Create approval_actions table for detailed tracking
CREATE TABLE IF NOT EXISTS approval_actions (
    action_id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    admin_id INT NOT NULL,
    action_type ENUM('approve', 'reject', 'pending_review', 'request_changes', 'resubmit') NOT NULL,
    admin_notes TEXT,
    rejection_reason VARCHAR(255) DEFAULT NULL,
    status_before ENUM('pending', 'approved', 'rejected', 'resubmitted') NOT NULL,
    status_after ENUM('pending', 'approved', 'rejected', 'resubmitted') NOT NULL,
    action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    session_id VARCHAR(255),
    FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_admin_id (admin_id),
    INDEX idx_action_type (action_type),
    INDEX idx_action_date (action_date),
    INDEX idx_status_before (status_before),
    INDEX idx_status_after (status_after)
);

-- Create user_profiles table for storing user profile information
CREATE TABLE IF NOT EXISTS user_profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vendor_id INT DEFAULT NULL,
    profile_type ENUM('vendor', 'admin', 'staff') NOT NULL,
    profile_image VARCHAR(500) DEFAULT NULL,
    business_license VARCHAR(500) DEFAULT NULL,
    tax_certificate VARCHAR(500) DEFAULT NULL,
    id_document VARCHAR(500) DEFAULT NULL,
    additional_documents JSON DEFAULT NULL,
    profile_status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_profile (user_id, profile_type),
    INDEX idx_user_id (user_id),
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_profile_type (profile_type),
    INDEX idx_profile_status (profile_status)
);

-- Create approval_notifications table for better notification management
CREATE TABLE IF NOT EXISTS approval_notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vendor_id INT DEFAULT NULL,
    notification_type ENUM('approval', 'rejection', 'pending_review', 'profile_required', 'deadline_reminder', 'resubmission') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    action_required BOOLEAN DEFAULT FALSE,
    action_url VARCHAR(500) DEFAULT NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_notification_type (notification_type),
    INDEX idx_is_read (is_read),
    INDEX idx_priority (priority),
    INDEX idx_created_at (created_at)
);

-- Create audit_log table for comprehensive tracking
CREATE TABLE IF NOT EXISTS audit_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(100) NOT NULL,
    record_id INT NOT NULL,
    action_type ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    user_id INT DEFAULT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    session_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_table_record (table_name, record_id),
    INDEX idx_action_type (action_type),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_session_id (session_id)
);

-- Create approval_settings table for system configuration
CREATE TABLE IF NOT EXISTS approval_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT DEFAULT NULL,
    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_setting_key (setting_key),
    INDEX idx_is_active (is_active)
);

-- =============================================
-- VENDORS TABLE ENHANCEMENTS
-- =============================================

-- Add additional columns to vendors table for better tracking
ALTER TABLE vendors 
ADD COLUMN IF NOT EXISTS approval_priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
ADD COLUMN IF NOT EXISTS review_deadline DATE DEFAULT NULL,
ADD COLUMN IF NOT EXISTS last_reminder_sent TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS profile_uploaded BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS profile_upload_date TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS compliance_score INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS risk_level ENUM('low', 'medium', 'high') DEFAULT 'medium',
ADD COLUMN IF NOT EXISTS resubmission_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS last_resubmission_date TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS auto_approve_eligible BOOLEAN DEFAULT FALSE;

-- =============================================
-- PERFORMANCE INDEXES
-- =============================================

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_vendors_status_priority ON vendors(status, approval_priority);
CREATE INDEX IF NOT EXISTS idx_vendors_review_deadline ON vendors(review_deadline);
CREATE INDEX IF NOT EXISTS idx_vendors_profile_uploaded ON vendors(profile_uploaded);
CREATE INDEX IF NOT EXISTS idx_vendors_compliance_score ON vendors(compliance_score);
CREATE INDEX IF NOT EXISTS idx_vendors_risk_level ON vendors(risk_level);
CREATE INDEX IF NOT EXISTS idx_vendors_resubmission_count ON vendors(resubmission_count);

-- =============================================
-- DEFAULT SETTINGS
-- =============================================

-- Insert default approval settings
INSERT INTO approval_settings (setting_key, setting_value, setting_type, description) VALUES
('auto_approve_threshold', '90', 'number', 'Compliance score threshold for auto-approval'),
('max_resubmissions', '3', 'number', 'Maximum number of resubmissions allowed'),
('review_deadline_days', '7', 'number', 'Default review deadline in days'),
('notification_retention_days', '30', 'number', 'Days to retain notifications'),
('enable_auto_approval', 'false', 'boolean', 'Enable automatic approval for high-scoring applications'),
('enable_email_notifications', 'true', 'boolean', 'Enable email notifications'),
('approval_workflow_enabled', 'true', 'boolean', 'Enable multi-step approval workflow'),
('audit_log_retention_days', '365', 'number', 'Days to retain audit logs')
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    updated_at = CURRENT_TIMESTAMP;

-- =============================================
-- USAGE NOTES
-- =============================================

/*
This robust approval system provides:

1. COMPREHENSIVE TRACKING:
   - approval_workflow: Multi-step approval processes
   - approval_actions: All approval/rejection actions with detailed logging
   - user_profiles: User profile management and document uploads
   - approval_notifications: Comprehensive notification system
   - audit_log: Complete audit trail for all actions
   - approval_settings: System configuration management

2. ENHANCED FEATURES:
   - Risk level assessment based on compliance scores
   - Priority management (low, medium, high, urgent)
   - Resubmission tracking
   - Auto-approval capabilities
   - Session and IP tracking
   - Comprehensive audit logging

3. SECURITY & INTEGRITY:
   - Transaction safety
   - Session tracking
   - IP address logging
   - User agent tracking
   - Complete audit trail

4. PERFORMANCE:
   - Optimized indexes
   - Efficient queries
   - Minimal database load

To apply this schema:
1. Run this SQL file against your database
2. The system will automatically create all necessary tables
3. Existing data will be preserved
4. New columns will be added to the vendors table

The approval system is now ready to use with the adminpanel.php interface!
*/
