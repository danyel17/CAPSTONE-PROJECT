<?php
/**
 * Helpers for the Client Satisfaction Rating feature.
 * Provides a single source of truth for "has this user already rated?"
 * so the success popup is shown only once per user (ever).
 */

if (!function_exists('dpmEnsureClientRatingsTable')) {
    function dpmEnsureClientRatingsTable($conn) {
        if (!$conn) return;
        $conn->query("CREATE TABLE IF NOT EXISTS client_ratings (
            rating_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            source VARCHAR(20) DEFAULT 'application',
            client_name VARCHAR(255) NULL,
            sex VARCHAR(10) NULL,
            age_group VARCHAR(30) NULL,
            address VARCHAR(255) NULL,
            email VARCHAR(255) NULL,
            contact_no VARCHAR(50) NULL,
            client_class VARCHAR(60) NULL,
            service_availed VARCHAR(255) NULL,
            transaction_date VARCHAR(50) NULL,
            q1 TINYINT NULL, q2 TINYINT NULL, q3 TINYINT NULL, q4 TINYINT NULL, q5 TINYINT NULL,
            q6 TINYINT NULL, q7 TINYINT NULL, q8 TINYINT NULL, q9 TINYINT NULL, q10 TINYINT NULL,
            overall_avg DECIMAL(3,2) NULL,
            comments TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created_at (created_at),
            INDEX idx_source (source),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }
}

if (!function_exists('dpmUserHasRated')) {
    /**
     * Returns true if the given user has already submitted at least one rating.
     */
    function dpmUserHasRated($user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return false;
        }
        $conn = getDBConnection();
        if (!$conn) {
            return false;
        }
        dpmEnsureClientRatingsTable($conn);
        $has = false;
        $stmt = $conn->prepare("SELECT 1 FROM client_ratings WHERE user_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->store_result();
            $has = $stmt->num_rows > 0;
            $stmt->close();
        }
        $conn->close();
        return $has;
    }
}
