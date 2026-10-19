<?php
/**
 * View SMS Logs
 * Display all received SMS messages
 */

$logFile = __DIR__ . '/sms_logs.txt';

// Check if log file exists
if (!file_exists($logFile)) {
    $logs = "No SMS messages received yet.";
} else {
    $logs = file_get_contents($logFile);
    if (empty($logs)) {
        $logs = "No SMS messages received yet.";
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>View SMS Logs</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
        }
        .logs {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            white-space: pre-wrap;
            font-family: monospace;
            font-size: 14px;
            max-height: 600px;
            overflow-y: auto;
            border: 1px solid #ddd;
        }
        .refresh-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 15px;
        }
        .refresh-btn:hover {
            background: #5568d3;
        }
        .clear-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 15px;
            margin-left: 10px;
        }
        .clear-btn:hover {
            background: #c0392b;
        }
        .info {
            background: #d1ecf1;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            border-left: 4px solid #3498db;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📱 SMS Logs Viewer</h1>
        
        <div class="info">
            <strong>Webhook URL:</strong> <?php echo (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/receive_sms.php'; ?><br>
            <strong>Copy this URL and configure it in Twilio Console</strong>
        </div>
        
        <button class="refresh-btn" onclick="location.reload()">🔄 Refresh</button>
        <button class="clear-btn" onclick="if(confirm('Clear all logs?')) { window.location.href='?clear=1'; }">🗑️ Clear Logs</button>
        
        <div class="logs"><?php 
            if (isset($_GET['clear']) && $_GET['clear'] == '1') {
                file_put_contents($logFile, '');
                echo "Logs cleared. Refreshing...";
                echo "<script>setTimeout(function(){window.location.href='view_sms_logs.php';}, 1000);</script>";
            } else {
                echo htmlspecialchars($logs);
            }
        ?></div>
    </div>
</body>
</html>


