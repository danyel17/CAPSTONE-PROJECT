<?php
/**
 * Debug Code Expiration
 * This script helps debug why codes are expiring immediately
 */

require_once 'config.php';

$email = isset($_GET['email']) ? filter_var($_GET['email'], FILTER_SANITIZE_EMAIL) : '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Code Expiration</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #667eea;
            color: white;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        form {
            margin: 20px 0;
        }
        input[type="email"] {
            padding: 10px;
            width: 300px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        input[type="submit"] {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Debug Code Expiration</h1>
        
        <?php
        try {
            $conn = getDBConnection();
            
            // Check timezone settings
            echo '<div class="info"><h2>1. Timezone Information</h2>';
            $tz_result = $conn->query("SELECT NOW() as db_now, UTC_TIMESTAMP() as db_utc, @@session.time_zone as timezone, @@global.time_zone as global_timezone");
            if ($tz_result) {
                $tz_data = $tz_result->fetch_assoc();
                echo '<p><strong>Database NOW():</strong> ' . htmlspecialchars($tz_data['db_now']) . '</p>';
                echo '<p><strong>Database UTC_TIMESTAMP():</strong> ' . htmlspecialchars($tz_data['db_utc']) . '</p>';
                echo '<p><strong>Session Timezone:</strong> ' . htmlspecialchars($tz_data['timezone']) . '</p>';
                echo '<p><strong>Global Timezone:</strong> ' . htmlspecialchars($tz_data['global_timezone']) . '</p>';
                echo '<p><strong>PHP Time:</strong> ' . date('Y-m-d H:i:s') . '</p>';
                echo '<p><strong>PHP Timezone:</strong> ' . date_default_timezone_get() . '</p>';
            }
            echo '</div>';
            
            // Show recent codes for email
            if (!empty($email)) {
                echo '<div class="info"><h2>2. Recent Codes for: ' . htmlspecialchars($email) . '</h2>';
                
                $codes_stmt = $conn->prepare("
                    SELECT 
                        id,
                        code,
                        created_at,
                        expires_at,
                        used_at,
                        attempts,
                        TIMESTAMPDIFF(SECOND, NOW(), expires_at) as seconds_remaining,
                        CASE 
                            WHEN used_at IS NOT NULL THEN 'Used'
                            WHEN TIMESTAMPDIFF(SECOND, NOW(), expires_at) <= 0 THEN 'Expired'
                            ELSE 'Valid'
                        END as status
                    FROM password_reset_codes 
                    WHERE email = ?
                    ORDER BY created_at DESC
                    LIMIT 10
                ");
                $codes_stmt->bind_param("s", $email);
                $codes_stmt->execute();
                $codes_result = $codes_stmt->get_result();
                
                if ($codes_result->num_rows > 0) {
                    echo '<table>';
                    echo '<tr>';
                    echo '<th>ID</th>';
                    echo '<th>Code</th>';
                    echo '<th>Created At</th>';
                    echo '<th>Expires At</th>';
                    echo '<th>Used At</th>';
                    echo '<th>Seconds Remaining</th>';
                    echo '<th>Status</th>';
                    echo '</tr>';
                    
                    while ($row = $codes_result->fetch_assoc()) {
                        $status_color = '';
                        switch($row['status']) {
                            case 'Valid':
                                $status_color = 'color: green; font-weight: bold;';
                                break;
                            case 'Expired':
                                $status_color = 'color: red; font-weight: bold;';
                                break;
                            case 'Used':
                                $status_color = 'color: orange; font-weight: bold;';
                                break;
                        }
                        
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['code']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['created_at']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['expires_at']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['used_at'] ?? 'Not used') . '</td>';
                        echo '<td>' . htmlspecialchars($row['seconds_remaining']) . '</td>';
                        echo '<td style="' . $status_color . '">' . htmlspecialchars($row['status']) . '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</table>';
                } else {
                    echo '<p>No codes found for this email.</p>';
                }
                
                $codes_stmt->close();
                echo '</div>';
            }
            
            // Form to check email
            echo '<div class="info"><h2>3. Check Codes for Email</h2>';
            echo '<form method="GET">';
            echo '<label>Email Address: </label>';
            echo '<input type="email" name="email" value="' . htmlspecialchars($email) . '" required>';
            echo '<input type="submit" value="Check Codes">';
            echo '</form>';
            echo '</div>';
            
            $conn->close();
            
        } catch (Exception $e) {
            echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>
        
        <div style="margin-top: 30px;">
            <h2>Quick Actions</h2>
            <a href="forgot_password.php" style="display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 5px;">Forgot Password</a>
            <a href="verify_code.php" style="display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 5px;">Verify Code</a>
        </div>
    </div>
</body>
</html>






