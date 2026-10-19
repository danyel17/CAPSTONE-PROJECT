-- para sa database set to work in the long run


USE daet_market_db;




CREATE INDEX IF NOT EXISTS idx_vendors_status_submitted_date 
ON vendors(status, submitted_date DESC);


CREATE INDEX IF NOT EXISTS idx_vendors_type_submitted_date 
ON vendors(application_type, submitted_date DESC);


CREATE INDEX IF NOT EXISTS idx_vendors_user_id 
ON vendors(user_id);


CREATE INDEX IF NOT EXISTS idx_vendors_status_type_date 
ON vendors(status, application_type, submitted_date DESC);

)
CREATE INDEX IF NOT EXISTS idx_vendors_reviewed_date 
ON vendors(reviewed_date);


CREATE INDEX IF NOT EXISTS idx_vendors_reviewed_by 
ON vendors(reviewed_by);


CREATE INDEX IF NOT EXISTS idx_users_user_id 
ON users(user_id);


CREATE INDEX IF NOT EXISTS idx_users_email 
ON users(email);


CREATE INDEX IF NOT EXISTS idx_app_docs_vendor_id_active 
ON application_documents(vendor_id, is_active);

CREATE INDEX IF NOT EXISTS idx_app_docs_upload_date 
ON application_documents(upload_date DESC);


CREATE INDEX IF NOT EXISTS idx_approval_actions_vendor_id 
ON approval_actions(vendor_id);

CREATE INDEX IF NOT EXISTS idx_approval_actions_admin_id 
ON approval_actions(admin_id);

CREATE INDEX IF NOT EXISTS idx_approval_actions_action_date 
ON approval_actions(action_date DESC);




SHOW INDEXES FROM vendors;
SHOW INDEXES FROM users;
SHOW INDEXES FROM application_documents;

