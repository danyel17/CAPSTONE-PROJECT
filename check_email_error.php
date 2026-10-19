<?php
/**
 * Check Email Error Details
 * This script shows the actual error from email_logs table
 */

require_once 'config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Email Error Details</title>
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
        .info {
            background: #d1ecf1;
            color: #0c5460;
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
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Email Error Details</h1>
        
        <?php
        try {
            $conn = getDBConnection();
            
            // Check if email_logs table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'email_logs'");
            if (!$table_check || $table_check->num_rows == 0) {
                echo '<div class="error">Email logs table does not exist yet. It will be created when you try to send an email.</div>';
            } else {
                // Get recent email logs
                $result = $conn->query("
                    SELECT 
                        id,
                        recipient_email,
                        recipient_name,
                        email_type,
                        subject,
                        status,
                        error_message,
                        error_details,
                        smtp_host,
                        smtp_username,
                        attempts,
                        sent_at,
                        created_at
                    FROM email_logs 
                    ORDER BY created_at DESC 
                    LIMIT 10
                ");
                
                if ($result && $result->num_rows > 0) {
                    echo '<div class="info">Found ' . $result->num_rows . ' recent email log entries</div>';
                    echo '<table>';
                    echo '<tr>';
                    echo '<th>ID</th>';
                    echo '<th>Recipient</th>';
                    echo '<th>Status</th>';
                    echo '<th>Error Message</th>';
                    echo '<th>Error Details</th>';
                    echo '<th>Created At</th>';
                    echo '</tr>';
                    
                    while ($row = $result->fetch_assoc()) {
                        $status_color = '';
                        switch($row['status']) {
                            case 'sent':
                                $status_color = 'color: green; font-weight: bold;';
                                break;
                            case 'failed':
                            case 'error':
                                $status_color = 'color: red; font-weight: bold;';
                                break;
                            case 'pending':
                                $status_color = 'color: orange; font-weight: bold;';
                                break;
                        }
                        
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['recipient_email']) . '</td>';
                        echo '<td style="' . $status_color . '">' . htmlspecialchars($row['status']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['error_message'] ?? 'N/A') . '</td>';
                        echo '<td>';
                        if (!empty($row['error_details'])) {
                            $details = json_decode($row['error_details'], true);
                            if ($details) {
                                echo '<pre>' . htmlspecialchars(print_r($details, true)) . '</pre>';
                            } else {
                                echo '<pre>' . htmlspecialchars($row['error_details']) . '</pre>';
                            }
                        } else {
                            echo 'N/A';
                        }
                        echo '</td>';
                        echo '<td>' . htmlspecialchars($row['created_at']) . '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</table>';
                } else {
                    echo '<div class="info">No email logs found. Try sending a test email first.</div>';
                }
            }
            
            $conn->close();
            
        } catch (Exception $e) {
            echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>
        
        <div style="margin-top: 30px;">
            <h2>Quick Actions</h2>
            <a href="test_email_sending.php" style="display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 5px;">Test Email Sending</a>
            <a href="forgot_password.php" style="display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 5px;">Forgot Password</a>
        </div>
    </div>
</body>
</html>






