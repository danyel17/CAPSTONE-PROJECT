<?php
require_once 'config.php';
require_once 'auth_functions.php';
require_once __DIR__ . '/includes/receipt_resubmit_helpers.php';
require_once __DIR__ . '/includes/renewal_payment_reminder.php';


requireLogin();

$success_message = '';
$error_message = '';


$user_id = $_SESSION['user_id'];

$unread_notifications_count = getUnreadApprovalNotificationCount($user_id);


$conn = getDBConnection();
$stmt = $conn->prepare("SELECT email, full_name, phone_number, user_type, status, email_verified, created_at, last_login FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    
    logoutUser();
    header("Location: userlogin.php");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

$receipt_submit_check = dpmReceiptCanSubmitNew($conn, (int)$user_id);
$receipt_submit_blocked = !($receipt_submit_check['allowed'] ?? true);
$receipt_block_message = trim((string)($receipt_submit_check['reason'] ?? ''));
$receipt_block_status = strtolower(trim((string)($receipt_submit_check['status'] ?? '')));


$profile_stmt = $conn->prepare("SELECT profile_image FROM user_profiles WHERE user_id = ? AND profile_type = 'vendor'");
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$profile_res = $profile_stmt->get_result();
$profile = $profile_res->fetch_assoc();
$profile_stmt->close();

if (!$profile) {
    
    $create_stmt = $conn->prepare("INSERT INTO user_profiles (user_id, profile_type, profile_status) VALUES (?, 'vendor', 'active')");
    $create_stmt->bind_param("i", $user_id);
    $create_stmt->execute();
    $create_stmt->close();
    $profile = ['profile_image' => null];
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    
   
    if (empty($full_name)) {
        $error_message = "Full name is required.";
    } else {
       
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("si", $full_name, $user_id);
        
        if ($stmt->execute()) {
           
            $_SESSION['full_name'] = $full_name;
            
            
            $user['full_name'] = $full_name;

            // Keep application cards / vendor records in sync with the user's display name
            try {
                $sync_vendors = $conn->prepare("UPDATE vendors SET vendor_name = ? WHERE user_id = ?");
                if ($sync_vendors) {
                    $sync_vendors->bind_param("si", $full_name, $user_id);
                    $sync_vendors->execute();
                    $sync_vendors->close();
                }
            } catch (Exception $e) {
                error_log("Failed to sync vendor_name after profile update: " . $e->getMessage());
            }
            
            $success_message = "Profile updated successfully!";
            
            
            try {
                $log_stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity_type, details) VALUES (?, 'profile_update', ?)");
                $details = "Profile updated: " . $full_name;
                $log_stmt->bind_param("is", $user_id, $details);
                $log_stmt->execute();
                $log_stmt->close();
            } catch (Exception $e) {
                error_log("Failed to log profile update: " . $e->getMessage());
            }
        } else {
            $error_message = "Failed to update profile. Please try again.";
        }
        $stmt->close();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password)) {
        $error_message = "Current password is required.";
    } elseif (empty($new_password)) {
        $error_message = "New password is required.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error_message = "New password must be at least 8 characters long.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $new_password)) {
        $error_message = "New password must contain at least one lowercase letter, one uppercase letter, and one number.";
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user_data || !password_verify($current_password, $user_data['password_hash'])) {
            $error_message = "Current password is incorrect.";
        } else {
            // Update password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?");
            $stmt->bind_param("si", $new_password_hash, $user_id);
            
            if ($stmt->execute()) {
                $success_message = "Password changed successfully!";
                try {
                    $log_stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity_type, details) VALUES (?, 'password_change', 'Password changed')");
                    $log_stmt->bind_param("i", $user_id);
                    $log_stmt->execute();
                    $log_stmt->close();
                } catch (Exception $e) {
                    error_log("Failed to log password change: " . $e->getMessage());
                }
            } else {
                $error_message = "Failed to change password. Please try again.";
            }
            $stmt->close();
        }
    }
}




$name_parts = explode(' ', $user['full_name'], 2);
$first_name = $name_parts[0] ?? '';
$last_name = $name_parts[1] ?? '';


$vendor_id = 'DPMO' . str_pad($user_id, 4, '0', STR_PAD_LEFT);

// Get total applications count for this user
$total_applications = 0;
$count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM vendors WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_res = $count_stmt->get_result();
if ($count_res && $row = $count_res->fetch_assoc()) {
    $total_applications = (int) $row['cnt'];
}
$count_stmt->close();

// Get user's applications (vendors) for account cards
$user_applications = [];
$active_stalls = 0;
$pending_review = 0;
$renewals_due = 0;
$apps_stmt = $conn->prepare("SELECT vendor_id, vendor_name, status, application_type, submitted_date, reviewed_date, section, block, stall_number FROM vendors WHERE user_id = ? ORDER BY COALESCE(submitted_date, NOW()) DESC");
if ($apps_stmt) {
    $apps_stmt->bind_param("i", $user_id);
    $apps_stmt->execute();
    $apps_res = $apps_stmt->get_result();
    while ($row = $apps_res->fetch_assoc()) {
        $app_status = strtolower(trim((string)($row['status'] ?? '')));
        $app_type = strtolower(trim((string)($row['application_type'] ?? 'new')));
        $ref = !empty($row['reviewed_date']) ? (string)$row['reviewed_date'] : (string)($row['submitted_date'] ?? '');
        $days_since = 0;
        if ($ref !== '') {
            $ts = strtotime($ref);
            if ($ts !== false) {
                $days_since = (int) floor((time() - $ts) / 86400);
            }
        }
        $is_renewal_due = ($app_status === 'approved' && $days_since >= 20);
        $row['days_since'] = $days_since;
        $row['is_renewal_due'] = $is_renewal_due;
        $row['due_date_label'] = '';
        if ($ref !== '' && function_exists('dpm_renewal_due_day')) {
            try {
                $due = dpm_renewal_due_day($ref);
                // e.g. "due date: december 17, 2026"
                $row['due_date_label'] = 'due date: ' . strtolower($due->format('F')) . ' ' . $due->format('j') . ', ' . $due->format('Y');
            } catch (Throwable $e) {
                $row['due_date_label'] = '';
            }
        }
        $user_applications[] = $row;
        if ($app_status === 'approved') {
            $active_stalls++;
        } elseif ($app_status === 'pending') {
            $pending_review++;
        }
        if ($is_renewal_due || ($app_type === 'renewal' && in_array($app_status, ['pending', 'incomplete'], true))) {
            $renewals_due++;
        }
    }
    $apps_stmt->close();
}
$first_app = $user_applications[0] ?? null;
$rest_apps = array_slice($user_applications, 1);

// Applications per day - current week Mon-Sun, highlight today
$chart_data = [0, 0, 0, 0, 0, 0, 0]; // M, T, W, Th, F, Sa, Su
$chart_labels = ['M', 'T', 'W', 'Th', 'F', 'Sa', 'Su'];
$monday_this_week = date('Y-m-d', strtotime('monday this week'));
$sunday_this_week = date('Y-m-d', strtotime('sunday this week'));
$today_dow_index = (int) date('w');
$today_index = ($today_dow_index === 0) ? 6 : ($today_dow_index - 1); // Mon=0 .. Sun=6
$chart_date_range = date('F j, Y');
$chart_stmt = $conn->prepare("
    SELECT DATE(COALESCE(submitted_date, CURDATE())) as d, COUNT(*) as cnt
    FROM vendors
    WHERE user_id = ? AND COALESCE(submitted_date, CURDATE()) >= ? AND COALESCE(submitted_date, CURDATE()) <= ?
    GROUP BY DATE(COALESCE(submitted_date, CURDATE()))
");
if ($chart_stmt) {
    $chart_stmt->bind_param("iss", $user_id, $monday_this_week, $sunday_this_week);
    $chart_stmt->execute();
    $chart_res = $chart_stmt->get_result();
    while ($row = $chart_res->fetch_assoc()) {
        $d = $row['d'];
        $cnt = (int) $row['cnt'];
        $dow = (int) date('w', strtotime($d));
        $idx = ($dow === 0) ? 6 : ($dow - 1);
        $chart_data[$idx] = $cnt;
    }
    $chart_stmt->close();
}

// Activity log: vendors (all applications/renewals) + log updates + receipts
$profile_activity_entries = [];
try {
    $v_stmt = $conn->prepare("
        SELECT vendor_id, vendor_name, section, block, application_type, status, submitted_date
        FROM vendors
        WHERE user_id = ?
        ORDER BY submitted_date DESC
        LIMIT 80
    ");
    if ($v_stmt) {
        $v_stmt->bind_param("i", $user_id);
        $v_stmt->execute();
        $v_res = $v_stmt->get_result();
        while ($row = $v_res->fetch_assoc()) {
            $is_renewal = (($row['application_type'] ?? '') === 'renewal');
            $section = trim((string)($row['section'] ?? ''));
            $block = trim((string)($row['block'] ?? ''));
            $vid = (int)($row['vendor_id'] ?? 0);
            $loc = trim(implode(' · ', array_filter([$section, $block])));
            $title_sec = $section !== '' ? $section : ('Application #' . $vid);
            $details = $is_renewal
                ? ('Renewal for ' . $title_sec . ' was filed and queued for office review.')
                : ($title_sec . ' allotment was filed and queued for office review.');
            if ($loc !== '') {
                $details = ($block !== '' ? ('Block ' . $block . ' allotment for ' . ($section !== '' ? $section : 'your stall')) : $title_sec)
                    . ' was filed and queued for office review.';
            }
            $sd = $row['submitted_date'] ?? '';
            $profile_activity_entries[] = [
                'type' => $is_renewal ? 'vendor_renewal' : 'vendor_new_app',
                'category' => $is_renewal ? 'renewals' : 'applications',
                'title' => ($is_renewal ? 'Renewal submitted' : 'Application submitted') . ' — ' . $title_sec,
                'details' => $details,
                'ref' => ($is_renewal ? 'RNW-' : 'APP-') . str_pad((string)$vid, 4, '0', STR_PAD_LEFT),
                'ts' => $sd !== '' ? strtotime($sd) : time(),
                'created_at' => $sd,
            ];
        }
        $v_stmt->close();
    }
} catch (Throwable $e) {
    error_log('profile activity (vendors): ' . $e->getMessage());
}

// Edits after submit (avoid duplicating "submitted" rows already covered by vendors)
try {
    $act_stmt = $conn->prepare("
        SELECT activity_type, details, created_at
        FROM user_activity_log
        WHERE user_id = ?
          AND activity_type IN ('application_updated','renewal_updated','password_change')
        ORDER BY created_at DESC
        LIMIT 50
    ");
    if ($act_stmt) {
        $act_stmt->bind_param("i", $user_id);
        $act_stmt->execute();
        $act_res = $act_stmt->get_result();
        while ($row = $act_res->fetch_assoc()) {
            $atype = (string)($row['activity_type'] ?? '');
            $cat = 'account';
            $title = 'Account updated';
            $ref = 'ACC-' . date('ymd', strtotime($row['created_at'] ?? 'now'));
            if ($atype === 'application_updated') {
                $cat = 'applications';
                $title = 'Application updated';
                $ref = 'APP-UPD';
            } elseif ($atype === 'renewal_updated') {
                $cat = 'renewals';
                $title = 'Renewal updated';
                $ref = 'RNW-UPD';
            } elseif ($atype === 'password_change') {
                $cat = 'account';
                $title = 'Password changed';
                $ref = '';
            }
            $details_txt = (string)($row['details'] ?? '');
            if ($atype === 'password_change') {
                $details_txt = 'Your trader login password was updated from this device.';
            }
            $profile_activity_entries[] = [
                'type' => $atype,
                'category' => $cat,
                'title' => $title,
                'details' => $details_txt,
                'ref' => $ref,
                'ts' => strtotime($row['created_at'] ?? 'now'),
                'created_at' => $row['created_at'] ?? '',
            ];
        }
        $act_stmt->close();
    }
} catch (Throwable $e) {
    error_log('profile activity (log): ' . $e->getMessage());
}

try {
    $rc_stmt = $conn->prepare("SELECT id, section, block, notes, created_at FROM receipts WHERE user_id = ? ORDER BY id DESC LIMIT 50");
    if ($rc_stmt) {
        $rc_stmt->bind_param("i", $user_id);
        $rc_stmt->execute();
        $rc_res = $rc_stmt->get_result();
        while ($row = $rc_res->fetch_assoc()) {
            $det = 'Receipt sent';
            $loc = trim(implode(' · ', array_filter([(string)($row['section'] ?? ''), (string)($row['block'] ?? '')])));
            if ($loc !== '') {
                $det .= ': ' . $loc;
            }
            $notes = trim((string)($row['notes'] ?? ''));
            if ($notes !== '') {
                $maxN = 120;
                if (function_exists('mb_strlen') && mb_strlen($notes) > $maxN) {
                    $det .= ' — ' . mb_substr($notes, 0, $maxN) . '…';
                } elseif (strlen($notes) > $maxN) {
                    $det .= ' — ' . substr($notes, 0, $maxN) . '…';
                } else {
                    $det .= ' — ' . $notes;
                }
            }
            $ca = $row['created_at'] ?? null;
            $ts = $ca ? strtotime($ca) : time();
            $rid = (int)($row['id'] ?? 0);
            $stall_bits = array_filter([(string)($row['section'] ?? ''), (string)($row['block'] ?? '')]);
            $stall_label = trim(implode(' ', $stall_bits));
            $receipt_title = $stall_label !== '' ? ('Receipt uploaded — ' . $stall_label) : 'Receipt uploaded';
            $profile_activity_entries[] = [
                'type' => 'receipt_upload',
                'category' => 'payments',
                'title' => $receipt_title,
                'details' => $det,
                'ref' => 'OR-' . str_pad((string)$rid, 6, '0', STR_PAD_LEFT),
                'ts' => $ts,
                'created_at' => $ca ?: date('Y-m-d H:i:s'),
            ];
        }
        $rc_stmt->close();
    }
} catch (Throwable $e) {
    error_log('profile activity (receipts): ' . $e->getMessage());
}

usort($profile_activity_entries, function ($a, $b) {
    return ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0);
});

$profile_activity_icons = [
    'vendor_new_app' => 'fa-file-lines',
    'vendor_renewal' => 'fa-arrows-rotate',
    'application_submitted' => 'fa-file-lines',
    'application_updated' => 'fa-file-pen',
    'renewal_submitted' => 'fa-arrows-rotate',
    'renewal_updated' => 'fa-rotate',
    'receipt_upload' => 'fa-receipt',
    'password_change' => 'fa-shield-halved',
];
$profile_activity_tone = [
    'vendor_new_app' => 'orange',
    'vendor_renewal' => 'orange',
    'application_submitted' => 'orange',
    'application_updated' => 'orange',
    'renewal_submitted' => 'orange',
    'renewal_updated' => 'orange',
    'receipt_upload' => 'green',
    'password_change' => 'slate',
];
$profile_activity_tkeys = [
    'vendor_new_app' => 'profile.activity.appSubmitted',
    'vendor_renewal' => 'profile.activity.renewalSubmitted',
    'application_submitted' => 'profile.activity.appSubmitted',
    'application_updated' => 'profile.activity.appUpdated',
    'renewal_submitted' => 'profile.activity.renewalSubmitted',
    'renewal_updated' => 'profile.activity.renewalUpdated',
    'receipt_upload' => 'profile.activity.receiptSent',
    'password_change' => 'profile.activity.passwordChanged',
];
$profile_activity_tdefault = [
    'vendor_new_app' => 'Application submitted',
    'vendor_renewal' => 'Renewal submitted',
    'application_submitted' => 'Application submitted',
    'application_updated' => 'Application updated',
    'renewal_submitted' => 'Renewal submitted',
    'renewal_updated' => 'Renewal updated',
    'receipt_upload' => 'Receipt sent',
    'password_change' => 'Password changed',
];
$profile_activity_count = count($profile_activity_entries);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title> Profile</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link rel="icon" type="image/jpeg" href="assets/imgs/logo.jpeg">
  <link rel="stylesheet" href="assets/css/profile-darkmode-btn.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      background-color: #667eea;
      min-height: 100vh;
      padding: 0;
      margin: 0;
      font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      overflow-x: hidden;
      color: #ffffff;
    }

    .main-container {
      width: 100%;
      margin: 0;
      padding: 1.5rem 1.5rem 0;
      padding-top: calc(80px + 1.5rem);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background: transparent;
      position: relative;
    }

    .navbar-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100vh;
      background: rgba(0, 0, 0, 0.5);
      z-index: 998;
      backdrop-filter: blur(2px);
    }
    .navbar-overlay.show { display: block; }

    .navbar {
      background: rgba(102, 126, 234, 0.95);
      backdrop-filter: blur(10px);
      padding: 1rem 2rem;
      margin-bottom: 0;
      border-radius: 0;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
      border-bottom: 2px solid rgba(255, 255, 255, 0.2);
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      width: 100%;
      z-index: 1000;
    }
    .navbar-content {
      display: flex;
      align-items: center;
      justify-content: space-between;
      width: 100%;
      max-width: 1400px;
      margin: 0 auto;
      position: relative;
    }
    .navbar-left { display: flex; align-items: center; gap: 1.5rem; }
    .navbar-logo { display: flex; flex-direction: row; align-items: center; gap: 0.65rem; text-decoration: none; color: inherit; }
    .navbar-logo .brand__stack { display: flex; flex-direction: column; gap: 0.1rem; }
    .navbar-logo .brand__text {
      font-family: 'DM Serif Display', Georgia, serif;
      font-weight: 400;
      font-size: clamp(0.88rem, 1.55vw, 1.08rem);
      line-height: 1.15;
      letter-spacing: 0;
      color: #fff;
      text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }
    .navbar-logo .brand__place {
      display: block;
      font-family: 'Inter', system-ui, sans-serif;
      font-size: 0.62rem;
      letter-spacing: 0.14em;
      font-weight: 700;
      color: rgba(255, 255, 255, 0.78);
      margin-top: 0.15rem;
    }
    .navbar-logo .brand__mark {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      flex-shrink: 0;
      overflow: hidden;
      border: 2px solid rgba(255, 255, 255, 0.85);
      box-sizing: border-box;
    }
    .navbar-logo .brand__mark img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }
    .navbar-toggler {
      display: none;
      border: 2px solid rgba(255, 255, 255, 0.3);
      border-radius: 10px;
      padding: 0.5rem 0.75rem;
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
      position: relative;
      overflow: hidden;
      cursor: pointer;
    }
    .navbar-toggler:hover {
      border-color: rgba(255, 255, 255, 0.5);
      background: rgba(255, 255, 255, 0.2);
    }
    .navbar-toggler-icon {
      background-image: none !important;
      width: 24px;
      height: 18px;
      position: relative;
      z-index: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .navbar-toggler-icon::before {
      content: '';
      display: block;
      height: 3px;
      width: 100%;
      background: white;
      border-radius: 3px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: absolute;
      top: 0;
      z-index: 1;
    }
    .navbar-toggler-icon::after {
      content: '';
      display: block;
      height: 3px;
      width: 100%;
      background: white;
      border-radius: 3px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: absolute;
      bottom: 0;
      z-index: 1;
    }
    .navbar-toggler-icon span {
      display: block;
      height: 3px;
      width: 100%;
      background: white;
      border-radius: 3px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      z-index: 1;
    }
    .navbar-toggler[aria-expanded="true"] .navbar-toggler-icon::before {
      transform: rotate(45deg) translate(6px, 6px);
      top: 50%;
    }
    .navbar-toggler[aria-expanded="true"] .navbar-toggler-icon span {
      opacity: 0;
      transform: translateY(-50%) scaleX(0);
    }
    .navbar-toggler[aria-expanded="true"] .navbar-toggler-icon::after {
      transform: rotate(-45deg) translate(6px, -6px);
      bottom: 50%;
    }
    .nav-pills-wrap {
      display: flex;
      align-items: center;
      justify-content: center;
      position: fixed;
      top: 0;
      left: 50%;
      transform: translateX(-50%);
      height: 80px;
      width: auto;
      z-index: 1001;
      pointer-events: none;
    }
    .nav-pills-wrap .nav-pills-container {
      pointer-events: auto;
    }
    .navbar-center {
      display: none;
    }
    .navbar-right-desktop {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-left: auto;
    }
    .navbar-right-desktop .calendar-icon {
      margin-right: 1.5rem;
    }
    .navbar-right-desktop .language-selector {
      margin-right: 1.5rem;
      margin-left: -1rem;
    }
    .navbar-right-desktop .logout-btn {
      margin-left: 1.5rem;
    }
    .nav-pills-container {
      background: rgba(102, 126, 234, 0.3);
      backdrop-filter: blur(10px);
      border-radius: 50px;
      padding: 0.3rem;
      display: flex;
      gap: 0.25rem;
      align-items: center;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .nav-pill {
      padding: 0.6rem 1.5rem;
      border-radius: 50px;
      color: rgba(255, 255, 255, 0.9);
      text-decoration: none;
      font-weight: 500;
      font-size: 0.95rem;
      transition: transform 0.25s ease, color 0.25s ease, background 0.25s ease;
      background: transparent;
      border: none;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transform: scale(1);
      transform-origin: center center;
      will-change: transform;
    }
    .nav-pill.active,
    .nav-icon-pill.active {
      background: rgba(255, 255, 255, 0.2);
      color: #ffffff;
      backdrop-filter: blur(10px);
    }
    .nav-pill:hover {
      color: #ffffff;
      background: rgba(255, 255, 255, 0.1);
      transform: scale(1.1);
    }
    .nav-pill.active:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: scale(1.1);
    }
    .nav-pill i {
      font-size: 1rem;
    }
    .nav-icon-pill {
      padding: 0.6rem 1rem;
      border-radius: 50px;
      color: rgba(255, 255, 255, 0.9);
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: transform 0.25s ease, color 0.25s ease, background 0.25s ease;
      background: transparent;
      border: none;
      font-weight: 500;
      font-size: 0.95rem;
      position: relative;
      transform: scale(1);
      transform-origin: center center;
      will-change: transform;
    }
    .nav-icon-pill:hover {
      color: #ffffff;
      background: rgba(255, 255, 255, 0.1);
      transform: scale(1.1);
    }
    .nav-icon-pill.active {
      background: rgba(255, 255, 255, 0.2);
      color: #ffffff;
      backdrop-filter: blur(10px);
    }
    .nav-icon-pill i {
      font-size: 1rem;
    }
    .nav-icon-badge {
      position: absolute;
      top: -4px;
      right: -4px;
      left: auto;
      min-width: 20px;
      height: 20px;
      padding: 0 6px;
      border-radius: 999px;
      background-color: #ef4444;
      color: #fff;
      font-size: 11px;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .logout-btn {
      padding: 0.65rem 1.5rem;
      background: #ff6b35;
      backdrop-filter: none;
      color: #ffffff;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
    }
    .logout-btn:hover {
      background: #ff8555;
      transform: translateY(-2px) scale(1.03);
      box-shadow: 0 8px 20px rgba(255, 107, 53, 0.5), 0 0 15px rgba(255, 107, 53, 0.3);
    }
    .logout-btn i {
      font-size: 1rem;
    }
    .navbar-logout-row,
    .navbar-logout-darkmode {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
      align-items: flex-start;
    }
    /* Dark mode toggle: same row as Apply Now (right side of profile header) */
    .accounts-header-right {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      flex-wrap: wrap;
      gap: 0.75rem;
      flex-shrink: 0;
    }
    .navbar-menu-controls { display: none; }
    .navbar-right { display: none; }
    /* Calendar icon (same as menu – image, transparent tile) */
    .calendar-icon {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      background: transparent;
      text-decoration: none;
      transition: all 0.3s ease;
      position: relative;
      padding: 0;
      cursor: pointer;
      border: none;
    }
    .calendar-icon-img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      display: block;
    }
    .calendar-icon:hover {
      transform: translateY(-2px);
    }
    /* Language selector (same as menu – flags fill circle, globe when unset) */
    .language-selector {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 0;
      width: 40px;
      height: 40px;
      border-radius: 30%;
      color: #ffffff;
      text-decoration: none;
      transition: all 0.3s ease;
      background: transparent;
      backdrop-filter: none;
      border: none;
      position: relative;
      cursor: pointer;
      overflow: hidden;
    }
    .language-selector:hover {
      transform: translateY(-2px);
    }
    .language-selector.ph-selected,
    .language-selector.en-selected {
      background: transparent;
      border: none;
    }
    .language-selector select {
      position: absolute;
      opacity: 0;
      width: 100%;
      height: 100%;
      cursor: pointer;
      z-index: 10;
      top: 0;
      left: 0;
    }
    .language-selector i {
      font-size: 1.1rem;
      z-index: 1;
    }
    .ph-flag-icon,
    .uk-flag-icon {
      position: absolute;
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 30%;
      top: 0;
      left: 0;
      z-index: 0;
      display: none;
    }
    .uk-flag-icon {
      border-radius: 30%;
    }
    .language-selector.ph-selected .ph-flag-icon {
      display: block;
    }
    .language-selector.ph-selected .uk-flag-icon {
      display: none;
    }
    .language-selector.en-selected .uk-flag-icon {
      display: block;
    }
    .language-selector.en-selected .ph-flag-icon {
      display: none;
    }
    .language-selector.ph-selected i,
    .language-selector.en-selected i {
      display: none;
    }
    .nav-link {
      color: #1e293b !important;
      font-weight: 600;
      padding: 0.55rem 1rem !important;
      margin: 0 0.15rem;
      border-radius: 8px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      font-size: 0.95rem;
      letter-spacing: 0.05px;
      font-family: 'Inter', sans-serif;
      position: relative;
    }
    
    .nav-link::before {
      content: '';
      position: absolute;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%) scaleX(0);
      width: 80%;
      height: 2px;
      background: linear-gradient(90deg, #4f46e5, #6366f1);
      transition: transform 0.3s ease;
    }

    .nav-link:hover,
    .nav-link.active {
      background: linear-gradient(135deg, #4f46e5, #6366f1);
      color: white !important;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
    }
    
    .nav-link.active::before {
      transform: translateX(-50%) scaleX(1);
    }

    .nav-link i {
      font-size: 1.1rem;
      margin-right: 0.4rem;
      transition: transform 0.3s ease;
    }
    
    .nav-link:hover i {
      transform: scale(1.1) rotate(5deg);
    }

    .nav-link.icon-only {
      padding: 0.5rem 0.65rem !important;
      display: flex;
      align-items: center;
      justify-content: center;
      min-width: 36px;
      position: relative;
    }
    
    .nav-link.icon-only i {
      margin-right: 0;
      font-size: 1.2rem;
    }

    .user-info {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 0.5rem 0.9rem;
      background: linear-gradient(135deg, #f8f9ff, #f0f4ff);
      border-radius: 8px;
      margin-left: 0.75rem;
      border: 1px solid rgba(79, 70, 229, 0.15);
      font-size: 0.9rem;
      font-weight: 600;
      color: #4f46e5;
      font-family: 'Inter', sans-serif;
    }

    .user-info i {
      color: #4f46e5;
      font-size: 1.1rem;
    }

    
    main {
      max-width: 1200px;
      margin: 1rem auto 2rem;
      padding: 0 1rem;
    }

    .alert {
      border-radius: 10px;
      padding: 1rem 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    /* Soft UI - Accounts/Profile page */
    .accounts-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
    }
    .accounts-header-text h1,
    .accounts-title {
      color: #ffffff;
      font-family: 'DM Serif Display', Georgia, serif;
      font-size: clamp(2.35rem, 5vw, 3.1rem);
      font-weight: 400;
      line-height: 1.05;
      letter-spacing: -0.02em;
      margin-bottom: 0.25rem;
    }
    .accounts-header-text .subtitle,
    .accounts-subtitle {
      color: #64748b;
      font-size: 1rem;
      font-weight: 500;
    }
    .accounts-header-profile {
      align-items: flex-start;
      gap: 1rem;
      margin-bottom: 1.35rem;
    }
    .accounts-header-profile .accounts-header-text {
      flex: 1;
      min-width: 0;
      text-align: left;
      padding-top: 0.15rem;
    }
    .profile-pic-circle {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 90px;
      height: 90px;
      min-width: 90px;
      min-height: 90px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.25);
      border: 3px solid rgba(255, 255, 255, 0.5);
      cursor: pointer;
      overflow: hidden;
      flex-shrink: 0;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .profile-pic-circle:hover {
      transform: scale(1.03);
      box-shadow: 0 4px 16px rgba(102, 126, 234, 0.25);
    }
    .profile-pic-inner {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #667eea;
    }
    .profile-pic-inner img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .profile-pic-overlay {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: rgba(102, 126, 234, 0.8);
      color: #fff;
      font-size: 0.9rem;
      padding: 0.2rem;
      text-align: center;
      opacity: 0;
      transition: opacity 0.2s;
    }
    .profile-pic-circle:hover .profile-pic-overlay {
      opacity: 1;
    }
    .btn-add-account {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.6rem 1.25rem;
      background: #ff6b35;
      color: #ffffff;
      font-weight: 600;
      font-size: 0.95rem;
      border: none;
      border-radius: 50px;
      text-decoration: none;
      transition: all 0.3s ease;
      flex-shrink: 0;
    }
    .btn-add-account:hover {
      background: #ff8555;
      color: #ffffff;
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(255, 107, 53, 0.5);
    }

    /* Hero banner - indigo background (transparent so body indigo shows) */
    .accounts-hero {
      background: rgba(102, 126, 234, 0.35);
      backdrop-filter: blur(10px);
      border-radius: 24px;
      padding: 1.5rem 2rem;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 1.5rem;
      position: relative;
      min-height: 140px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.25);
    }
    .accounts-hero-mascot {
      flex: 0 0 150px;
      width: 150px;
      height: 150px;
      border-radius: 20px;
      overflow: hidden;
    }
    .accounts-hero-mascot img {
      width: 150px;
      height: 150px;
      object-fit: contain;
    }
    .accounts-total-card {
      flex: 1;
      background: rgba(255, 255, 255, 0.25);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      padding: 1.25rem 1.75rem;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      position: relative;
      margin-left: -0.5rem;
      border: 1px solid rgba(255, 255, 255, 0.35);
    }
    .accounts-total-card::before {
      display: none;
    }
    .accounts-total-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      width: 100%;
    }
    .accounts-total-text {
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
      padding-right: 6rem;
    }
    .accounts-total-card .label,
    .accounts-total-card .accounts-total-label {
      font-size: 0.75rem;
      font-weight: 600;
      color: rgba(255, 255, 255, 0.9);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 0.25rem;
      display: block;
    }
    .accounts-total-card .value,
    .accounts-total-card .accounts-total-value {
      font-size: 1.75rem;
      font-weight: 800;
      color: #ffffff;
      line-height: 1.2;
    }
    .accounts-total-card .icon-wrap,
    .accounts-total-card .accounts-total-icon {
      position: absolute;
      right: 1.25rem;
      top: 50%;
      transform: translateY(-50%);
      width: auto;
      height: auto;
      background: none;
      border-radius: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: visible;
    }
    .accounts-total-card .accounts-total-icon img {
      width: 88px;
      height: 88px;
      object-fit: contain;
      display: block;
    }

    /* Middle grid - Insights + Chart */
    .accounts-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .accounts-card {
      background: rgba(255, 255, 255, 0.25);
      backdrop-filter: blur(10px);
      border-radius: 24px;
      padding: 1.5rem;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      border: 1px solid rgba(255, 255, 255, 0.35);
    }
    .accounts-card.insights p {
      color: rgba(255, 255, 255, 0.95);
      font-size: 0.95rem;
      line-height: 1.6;
      margin: 0;
    }
    .accounts-insights-card {
      background: rgba(255, 255, 255, 0.25);
      backdrop-filter: blur(10px);
      border-radius: 24px;
      padding: 1.5rem;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      border: 1px solid rgba(255, 255, 255, 0.35);
    }
    .accounts-insights-card p,
    .insights-text {
      color: rgba(255, 255, 255, 0.95);
      font-size: 0.95rem;
      line-height: 1.6;
      margin: 0;
    }
    .accounts-total-inner .accounts-total-label,
    .accounts-total-card .accounts-total-label {
      font-size: 0.75rem;
      font-weight: 600;
      color: rgba(255, 255, 255, 0.8);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      display: block;
      margin-bottom: 0.2rem;
    }
    .accounts-total-inner .accounts-total-value,
    .accounts-total-card .accounts-total-value {
      font-size: 1.75rem;
      font-weight: 800;
      color: #ffffff;
      line-height: 1.2;
    }
    .accounts-total-inner .accounts-total-icon,
    .accounts-total-card .accounts-total-icon {
      flex-shrink: 0;
      width: auto;
      height: auto;
      background: none;
      border-radius: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: visible;
    }
    .accounts-chart-card {
      background: rgba(255, 255, 255, 0.25);
      backdrop-filter: blur(10px);
      border-radius: 24px;
      padding: 1.5rem;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      border: 1px solid rgba(255, 255, 255, 0.35);
      display: flex;
      flex-direction: column;
    }
    .accounts-chart-card .chart-label {
      font-size: 0.75rem;
      font-weight: 600;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      display: block;
      margin-bottom: 0.35rem;
    }
    .chart-date-range {
      font-size: 1rem;
      font-weight: 700;
      color: rgba(255, 255, 255, 0.95);
      margin-bottom: 1rem;
      display: block;
    }
    .chart-bars {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      gap: 0.5rem;
      height: 80px;
    }
    .chart-bar-wrap {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.5rem;
    }
    .chart-bar {
      width: 100%;
      max-width: 24px;
      min-height: 4px;
      border-radius: 6px 6px 0 0;
      background: rgba(255,255,255,0.35);
      transition: height 0.3s ease;
    }
    .chart-bar.highlight {
      background: #667eea;
    }
    .chart-day {
      font-size: 0.7rem;
      font-weight: 600;
      color: rgba(255, 255, 255, 0.9);
    }
    .account-card {
      background: rgba(255, 255, 255, 0.25);
      backdrop-filter: blur(10px);
      border-radius: 24px;
      padding: 1.5rem;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      border: 1px solid rgba(255, 255, 255, 0.35);
      display: flex;
      align-items: flex-start;
      gap: 1rem;
      text-decoration: none;
      color: inherit;
      transition: all 0.3s ease;
    }
    .account-card:hover,
    .account-card-action:hover {
      box-shadow: 0 8px 30px rgba(0,0,0,0.1);
    }
    .account-card-icon {
      width: 48px;
      height: 48px;
      background: rgba(102, 126, 234, 0.12);
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #667eea;
      flex-shrink: 0;
      overflow: hidden;
    }
    .account-card-icon i {
      font-size: 1.35rem;
      line-height: 1;
      color: #667eea;
    }
    .account-card[data-vendor-id] .account-card-icon {
      width: 56px;
      height: 56px;
    }
    .account-card[data-vendor-id] .account-card-icon i {
      font-size: 1.55rem;
    }
    .account-card-body {
      flex: 1;
      min-width: 0;
    }
    .account-card-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 0.5rem;
      margin-bottom: 0.5rem;
    }
    .account-name {
      font-size: 1.1rem;
      font-weight: 700;
      color: #ffffff;
      margin-bottom: 0.2rem;
    }
    .account-type {
      font-size: 0.85rem;
      color: rgba(255, 255, 255, 0.9);
      display: block;
    }
    .account-menu-btn,
    .account-eye-btn {
      width: 36px;
      height: 36px;
      border: none;
      background: transparent;
      color: rgba(255, 255, 255, 0.9);
      font-size: 1rem;
      cursor: pointer;
      border-radius: 10px;
      padding: 0;
      transition: color 0.2s, background 0.2s;
    }
    .account-eye-btn:hover {
      color: #667eea;
      background: rgba(255, 255, 255, 0.3);
    }
    .account-roll-wrapper {
      display: none;
    }
    .account-roll-wrapper.visible {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }
    .account-roll-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      padding: 0.75rem 1.25rem;
      border: 2px solid rgba(255,255,255,0.35);
      background: rgba(255, 255, 255, 0.25);
      color: #ffffff;
      font-weight: 600;
      font-size: 0.95rem;
      border-radius: 14px;
      cursor: pointer;
      transition: background 0.2s, color 0.2s;
      margin-top: 0.5rem;
      width: 100%;
    }
    .account-roll-btn:hover {
      background: rgba(255,255,255,0.35);
      color: #1e293b;
    }
    .account-roll-btn.rolled .fa-chevron-down {
      transform: rotate(180deg);
    }
    .account-eye-btn {
      width: 36px;
      height: 36px;
      border: none;
      background: transparent;
      color: #667eea;
      font-size: 1rem;
      cursor: pointer;
      border-radius: 10px;
      padding: 0;
      transition: color 0.2s, background 0.2s;
    }
    .account-eye-btn:hover {
      color: #1e293b;
      background: rgba(255,255,255,0.35);
    }
    .account-roll-wrapper {
      display: none;
      flex-direction: column;
      gap: 1rem;
    }
    .account-roll-wrapper.expanded {
      display: flex;
    }
    .account-roll-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      padding: 0.6rem 1rem;
      background: rgba(255,255,255,0.35);
      color: #667eea;
      border: none;
      border-radius: 12px;
      font-weight: 600;
      font-size: 0.9rem;
      cursor: pointer;
      transition: background 0.2s, color 0.2s;
    }
    .account-roll-btn:hover {
      background: #667eea;
      color: #fff;
    }
    .account-roll-btn.expanded i {
      transform: rotate(180deg);
    }
    .account-eye-btn {
      width: 36px;
      height: 36px;
      border: none;
      background: transparent;
      color: rgba(255, 255, 255, 0.9);
      font-size: 1rem;
      cursor: pointer;
      border-radius: 10px;
      padding: 0;
      transition: color 0.2s;
    }
    .account-eye-btn:hover {
      color: #ffffff;
      background: rgba(255, 255, 255, 0.25);
    }
    .account-roll-wrapper {
      display: none;
    }
    .account-roll-wrapper.show {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }
    .account-roll-btn {
      align-self: flex-start;
      padding: 0.5rem 1rem;
      border: 1px solid rgba(255,255,255,0.35);
      background: rgba(255, 255, 255, 0.25);
      color: #ffffff;
      border-radius: 12px;
      font-weight: 600;
      font-size: 0.9rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.2s;
    }
    .account-roll-btn:hover {
      background: rgba(255,255,255,0.35);
    }
    .account-roll-btn.rolled .account-roll-chevron {
      transform: rotate(180deg);
    }
    .account-balance .balance-label {
      font-size: 0.8rem;
      color: rgba(255, 255, 255, 0.8);
      display: block;
      margin-bottom: 0.2rem;
    }
    .account-balance .balance-value {
      font-size: 1.25rem;
      font-weight: 800;
      color: #ffffff;
    }
    .account-balance .balance-value.status-pending {
      color: #f59e0b;
    }
    .account-balance .balance-value.status-approved {
      color: #667eea;
    }
    .account-balance .balance-value.status-incomplete {
      color: rgba(255, 255, 255, 0.7);
    }

    /* Application/Account cards list */
    .accounts-list {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }
    .account-item-card {
      background: rgba(255, 255, 255, 0.25);
      backdrop-filter: blur(10px);
      border-radius: 24px;
      padding: 1.5rem;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      border: 1px solid rgba(255, 255, 255, 0.35);
      display: flex;
      flex-wrap: wrap;
      align-items: flex-start;
      gap: 1rem;
      text-decoration: none;
      color: inherit;
      transition: all 0.3s ease;
    }
    .account-item-card:hover {
      box-shadow: 0 8px 30px rgba(0,0,0,0.1);
    }
    .account-item-icon {
      width: 48px;
      height: 48px;
      background: rgba(255,255,255,0.35);
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #667eea;
      flex-shrink: 0;
    }
    .account-item-main {
      flex: 1;
      min-width: 160px;
    }
    .account-item-card h3 {
      font-size: 1.1rem;
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 0.2rem;
    }
    .account-item-card .sublabel {
      font-size: 0.85rem;
      color: #64748b;
      margin-bottom: 0.5rem;
    }
    .account-item-card .balance-label {
      font-size: 0.8rem;
      color: #64748b;
      margin-bottom: 0.2rem;
    }
    .account-item-card .balance-value {
      font-size: 1.5rem;
      font-weight: 800;
      color: #1e293b;
    }
    .account-item-actions {
      margin-left: auto;
    }
    .account-item-card .ellipsis-btn {
      width: 36px;
      height: 36px;
      border: none;
      background: transparent;
      color: #64748b;
      font-size: 1.2rem;
      cursor: pointer;
      border-radius: 10px;
    }
    .account-item-card .action-pill {
      padding: 0.5rem 1rem;
      background: rgba(255,255,255,0.25);
      color: #667eea;
      font-weight: 600;
      font-size: 0.85rem;
      border: none;
      border-radius: 50px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      transition: all 0.3s ease;
    }
    .account-item-card .action-pill:hover {
      background: rgba(255,255,255,0.35);
    }

    /* Edit Profile / Change Password - keep tabbed forms in modal/collapse */
    .profile-actions {
      display: contents;
    }
    .profile-action-card {
      background: rgba(255, 255, 255, 0.25);
      backdrop-filter: blur(10px);
      border-radius: 24px;
      padding: 1.5rem;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      border: 1px solid rgba(255, 255, 255, 0.35);
      text-decoration: none;
      color: inherit;
      display: flex;
      align-items: center;
      gap: 1rem;
      transition: all 0.3s ease;
    }
    .profile-action-card:hover {
      box-shadow: 0 8px 30px rgba(0,0,0,0.1);
    }
    .profile-action-card h3 {
      color: #ffffff;
      font-size: 1.1rem;
      font-weight: 700;
      margin: 0 0 0.2rem 0;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .profile-action-card h3 i {
      color: rgba(255, 255, 255, 0.95);
    }
    .profile-action-card p {
      color: rgba(255, 255, 255, 0.9);
      font-size: 0.85rem;
      margin: 0;
    }

    @media (max-width: 768px) {
      .profile-hero-row {
        flex-direction: column;
      }
      .profile-gif-wrap {
        width: 160px;
        height: 160px;
        margin: 0 auto;
      }
      .accounts-grid {
        grid-template-columns: 1fr;
      }
      .accounts-hero {
        flex-direction: column;
        text-align: center;
      }
      .accounts-hero-mascot {
        margin: 0 auto;
      }
      .accounts-header {
        flex-direction: column;
        align-items: flex-start;
      }
    }

    .profile-header {
      background-color: white;
      padding: 2rem;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      margin-bottom: 2rem;
      text-align: center;
    }

    .profile-header h1 {
      color: #667eea;
      margin-bottom: 0.5rem;
      font-size: 2.5rem;
      font-weight: bold;
    }

    .profile-avatar {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      background: linear-gradient(135deg, #667eea, #764ba2);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
      position: relative;
      overflow: hidden;
    }

    .profile-avatar i {
      font-size: 4rem;
      color: white;
    }

    .avatar-overlay {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: rgba(0,0,0,0.5);
      color: #fff;
      font-size: 0.85rem;
      padding: 6px 8px;
      text-align: center;
      opacity: 0;
      transition: opacity 0.2s;
    }
    .profile-avatar:hover .avatar-overlay { opacity: 1; }

    .badge {
      display: inline-block;
      padding: 0.5rem 1rem;
      border-radius: 20px;
      font-size: 0.9rem;
      font-weight: 600;
      margin-top: 0.5rem;
    }

    .badge-success {
      background-color: #28a745;
      color: white;
    }

    .badge-warning {
      background-color: #ffc107;
      color: #333;
    }

    .profile-content {
      background: transparent;
      padding: 2rem 0;
      border-radius: 24px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.25);
      margin-top: 2rem;
    }

    .profile-section {
      margin-bottom: 2rem;
    }

    .profile-section h2.section-title {
      color: #667eea;
      font-size: 1.75rem;
      margin-bottom: 1.5rem;
      padding-bottom: 0.75rem;
      border-bottom: 3px solid rgba(255,255,255,0.35);
      font-weight: 700;
      display: flex;
      align-items: center;
    }

    /* Account Menu Tabs */
    .account-menu-tabs {
      display: flex;
      gap: 1rem;
      margin-bottom: 2rem;
      border-bottom: 2px solid rgba(255,255,255,0.25);
      padding-bottom: 0;
    }

    .account-tab-btn {
      background: none;
      border: none;
      padding: 1rem 2rem;
      font-size: 1.1rem;
      font-weight: 600;
      color: #64748b;
      cursor: pointer;
      position: relative;
      transition: all 0.3s ease;
      border-bottom: 3px solid transparent;
      margin-bottom: -2px;
      font-family: 'Inter', sans-serif;
    }

    .account-tab-btn:hover {
      color: #667eea;
      background: linear-gradient(180deg, rgba(255, 255, 255, 0.2), transparent);
    }

    .account-tab-btn.active {
      color: #667eea;
      border-bottom-color: #667eea;
      background: linear-gradient(180deg, rgba(255, 255, 255, 0.3), transparent);
    }

    .account-tab-btn.active::after {
      content: '';
      position: absolute;
      bottom: -3px;
      left: 0;
      right: 0;
      height: 3px;
      background: #667eea;
      border-radius: 3px 3px 0 0;
    }

    /* Account Content Card */
    .account-content-card {
      background: rgba(255, 255, 255, 0.25);
      backdrop-filter: blur(10px);
      border-radius: 24px;
      padding: 2.5rem;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      border: 1px solid rgba(255, 255, 255, 0.35);
      transition: all 0.3s ease;
    }

    .account-content-card:hover {
      box-shadow: 0 8px 30px rgba(102, 126, 234, 0.1);
      transform: translateY(-2px);
    }

    .activity-log-item-icon img.receipt-icon-img {
      width: 26px;
      height: 26px;
      object-fit: contain;
      display: block;
    }
    .profile-receipt-file-shell {
      position: relative;
      min-height: 3.25rem;
      border-radius: 12px;
      border: 2px solid rgba(255, 255, 255, 0.25);
      background: linear-gradient(180deg, #ffffff, #f8f9ff);
      overflow: hidden;
      transition: border-color 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease;
    }
    .profile-receipt-file-shell:hover {
      border-color: rgba(102, 126, 234, 0.45);
    }
    .profile-receipt-file-shell:focus-within {
      border-color: #667eea;
      box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15), 0 4px 12px rgba(102, 126, 234, 0.1);
    }
    .profile-receipt-form .info-item .profile-receipt-file-input {
      position: absolute;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      min-height: 3.25rem;
      opacity: 0;
      cursor: pointer;
      z-index: 2;
      padding: 0 !important;
      margin: 0 !important;
      border: none !important;
      border-radius: 12px !important;
      background: transparent !important;
      box-shadow: none !important;
      transform: none !important;
      font-size: 0;
    }
    .profile-receipt-file-inner {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.65rem 1rem;
      min-height: 3.25rem;
      pointer-events: none;
    }
    .profile-receipt-file-btn {
      flex-shrink: 0;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: #fff;
      font-weight: 600;
      font-size: 0.875rem;
      box-shadow: 0 2px 10px rgba(102, 126, 234, 0.35);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .profile-receipt-file-shell:hover .profile-receipt-file-btn {
      box-shadow: 0 4px 14px rgba(102, 126, 234, 0.45);
      transform: translateY(-1px);
    }
    .profile-receipt-file-name {
      flex: 1;
      min-width: 0;
      font-size: 0.9rem;
      color: #1e293b;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .profile-receipt-hint {
      display: block;
      margin-top: 0.35rem;
      font-size: 0.82rem;
      color: rgba(30, 41, 59, 0.65);
      line-height: 1.4;
    }
    #profileReceiptFeedback.is-success {
      color: #0d9488;
      font-weight: 600;
    }
    #profileReceiptFeedback.is-error {
      color: #dc2626;
      font-weight: 600;
    }

    /* Activity log (below Change Password) */
    .activity-log-card {
      flex-direction: column;
      align-items: stretch;
      gap: 0;
    }
    .activity-log-card .activity-log-header-row {
      display: flex;
      align-items: flex-start;
      gap: 1rem;
      width: 100%;
    }
    .activity-log-card .account-card-icon {
      width: 56px;
      height: 56px;
    }
    .activity-log-card .account-card-icon i {
      font-size: 1.55rem;
    }
    .activity-log-card .activity-log-body {
      margin-top: 1.25rem;
      width: 100%;
    }
    /* Light mode: avoid harsh white stacks — tint with page purple */
    .activity-log-card .account-content-card {
      background: rgba(75, 88, 185, 0.28);
      border-color: rgba(255, 255, 255, 0.2);
      box-shadow: 0 4px 18px rgba(45, 55, 130, 0.14);
    }
    .activity-log-card .account-content-card:hover {
      transform: none;
      box-shadow: 0 6px 24px rgba(45, 55, 130, 0.18);
    }
    .activity-log-entries {
      display: flex;
      flex-direction: column;
      gap: 0.65rem;
      /* ~2 rows visible; scroll for the rest */
      max-height: 15rem;
      overflow-y: auto;
      padding-right: 0.25rem;
      scrollbar-gutter: stable;
    }
    .activity-log-entries::-webkit-scrollbar {
      width: 6px;
    }
    .activity-log-entries::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.35);
      border-radius: 6px;
    }
    .activity-log-item {
      display: flex;
      gap: 0.85rem;
      align-items: flex-start;
      padding: 0.85rem 1rem;
      background: rgba(62, 74, 165, 0.35);
      border-radius: 16px;
      border: 1px solid rgba(255, 255, 255, 0.14);
      transition: background 0.2s, border-color 0.2s;
    }
    .activity-log-item:hover {
      background: rgba(62, 74, 165, 0.48);
      border-color: rgba(255, 255, 255, 0.22);
    }
    .activity-log-item-icon {
      width: 40px;
      height: 40px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      background: rgba(45, 55, 140, 0.45);
      color: #c7d2fe;
      font-size: 1rem;
    }
    .activity-log-item-text {
      flex: 1;
      min-width: 0;
    }
    .activity-log-type {
      display: block;
      font-weight: 700;
      font-size: 0.95rem;
      color: #ffffff;
      margin-bottom: 0.25rem;
    }
    .activity-log-details {
      display: block;
      font-size: 0.88rem;
      color: rgba(255, 255, 255, 0.92);
      line-height: 1.45;
      word-break: break-word;
    }
    .activity-log-time {
      display: block;
      font-size: 0.75rem;
      color: rgba(255, 255, 255, 0.75);
      margin-top: 0.35rem;
    }
    .activity-log-empty {
      text-align: center;
      padding: 1.5rem 1rem;
      color: rgba(255, 255, 255, 0.88);
      font-size: 0.95rem;
      background: rgba(62, 74, 165, 0.3);
      border-radius: 16px;
      border: 1px dashed rgba(255, 255, 255, 0.22);
    }

    .account-tab-content {
      display: none;
      animation: fadeInUp 0.4s ease;
    }

    .account-tab-content.active {
      display: block;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .info-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 1.5rem;
      margin-bottom: 1rem;
    }
    .profile-form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
      margin-bottom: 1rem;
    }
    .profile-form-col {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }
    @media (max-width: 640px) {
      .profile-form-row {
        grid-template-columns: 1fr;
      }
    }

    .info-item {
      display: flex;
      flex-direction: column;
    }

    .info-item label {
      color: #667eea;
      font-weight: 600;
      margin-bottom: 0.75rem;
      font-size: 0.95rem;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .info-item label::before {
      content: '';
      width: 4px;
      height: 16px;
      background: linear-gradient(135deg, #667eea, #764ba2);
      border-radius: 2px;
    }

    .info-item input,
    .info-item select,
    .info-item textarea {
      padding: 0.875rem 1rem;
      border: 2px solid rgba(255,255,255,0.25);
      border-radius: 12px;
      font-size: 1rem;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      background: linear-gradient(180deg, #ffffff, #f8f9ff);
      color: #1e293b;
      font-family: 'Inter', sans-serif;
    }

    .info-item input:focus,
    .info-item select:focus,
    .info-item textarea:focus {
      outline: none;
      border-color: #667eea;
      background: white;
      box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15), 0 4px 12px rgba(102, 126, 234, 0.1);
      transform: translateY(-1px);
    }

    .info-item input:disabled {
      background-color: #f8f9fa;
      cursor: not-allowed;
    }

    .form-link {
      color: #667eea;
      text-decoration: none;
      font-weight: 500;
    }
    .form-link:hover {
      color: #1e4d3a;
      text-decoration: underline;
    }
    .btn-group-profile {
      display: flex;
      gap: 1rem;
      justify-content: center;
      margin-top: 2rem;
      flex-wrap: wrap;
    }

    .btn-profile {
      padding: 0.75rem 2rem;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .btn-primary-profile {
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: white;
      box-shadow: 0 4px 15px rgba(102, 126, 234, 0.35);
      position: relative;
      overflow: hidden;
    }

    .btn-primary-profile::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, #764ba2, #667eea);
      transition: left 0.3s ease;
      z-index: 0;
    }

    .btn-primary-profile:hover::before {
      left: 0;
    }

    .btn-primary-profile:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 20px rgba(102, 126, 234, 0.45);
    }

    .btn-primary-profile i,
    .btn-primary-profile span {
      position: relative;
      z-index: 1;
    }

    .btn-secondary-profile {
      background: linear-gradient(135deg, #6c757d, #5a6268);
      color: white;
      box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
    }

    .btn-secondary-profile:hover {
      background: linear-gradient(135deg, #5a6268, #495057);
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(108, 117, 125, 0.4);
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-top: 1.5rem;
    }

    .stat-card {
      background: linear-gradient(135deg, #667eea, #764ba2);
      padding: 1.5rem;
      border-radius: 10px;
      color: white;
      text-align: center;
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
      transition: transform 0.3s;
    }

    .stat-card:hover {
      transform: translateY(-5px);
    }

    .stat-card i {
      font-size: 2.5rem;
      margin-bottom: 0.5rem;
      opacity: 0.9;
    }

    .stat-card .stat-number {
      font-size: 2rem;
      font-weight: bold;
      margin-bottom: 0.25rem;
    }

    .stat-card .stat-label {
      font-size: 0.9rem;
      opacity: 0.9;
    }

    .applications-table {
      overflow-x: auto;
      margin-top: 1rem;
    }

    .applications-table table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .applications-table th {
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: white;
      padding: 1rem;
      text-align: left;
      font-weight: 600;
      font-size: 0.9rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .applications-table td {
      padding: 1rem;
      border-bottom: 1px solid #e9ecef;
      vertical-align: middle;
    }

    .applications-table tr:hover {
      background-color: #f8f9fa;
    }

    .applications-table tr:last-child td {
      border-bottom: none;
    }

    .badge-primary {
      background-color: #007bff;
      color: white;
    }

    .badge-secondary {
      background-color: #6c757d;
      color: white;
    }

    .badge-success {
      background-color: #28a745;
      color: white;
    }

    .badge-danger {
      background-color: #dc3545;
      color: white;
    }

    .badge-warning {
      background-color: #ffc107;
      color: #333;
    }

    .table-light {
      background-color: #f8f9fa;
    }

    .notifications-list {
      margin-top: 1rem;
    }

    .notification-item {
      display: flex;
      align-items: flex-start;
      padding: 1rem;
      margin-bottom: 1rem;
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      border-left: 4px solid #e9ecef;
      transition: all 0.3s;
    }

    .notification-item.unread {
      border-left-color: #007bff;
      background: #f8f9ff;
    }

    .notification-item:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .notification-icon {
      margin-right: 1rem;
      margin-top: 0.25rem;
    }

    .notification-icon i {
      font-size: 1.5rem;
    }

    .notification-content {
      flex: 1;
    }

    .notification-title {
      font-weight: 600;
      font-size: 1.1rem;
      margin-bottom: 0.5rem;
      color: #333;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .notification-message {
      color: #666;
      margin-bottom: 0.5rem;
      line-height: 1.5;
    }

    .notification-meta {
      color: #999;
      font-size: 0.85rem;
    }

    .notification-meta i {
      margin-right: 0.25rem;
    }

    @media (max-width: 991px) {
      .navbar {
        padding: 0.75rem 1rem;
      }
      
      .navbar-overlay {
        display: none;
      }
      .navbar-overlay.show {
        display: block;
      }
      
      .navbar-toggler {
        display: block;
        order: 2;
        margin-left: auto;
        z-index: 1000;
      }
      
      .navbar-content {
        flex-wrap: wrap;
        gap: 0.75rem;
      }
      
      .navbar-left {
        order: 1;
        flex: 1;
      }
      
      .nav-pills-wrap {
        top: auto;
        bottom: calc(1rem + env(safe-area-inset-bottom, 0px));
        height: auto;
      }
      .navbar-center {
        display: none;
        position: fixed;
        top: 0;
        right: 0;
        width: min(300px, 86vw);
        height: 100vh;
        background: rgba(102, 126, 234, 0.98);
        backdrop-filter: blur(20px);
        flex-direction: column;
        align-items: stretch;
        gap: 0;
        margin: 0;
        padding: 1.25rem;
        padding-top: 88px;
        padding-bottom: calc(1.5rem + env(safe-area-inset-bottom, 0px));
        border-left: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: -4px 0 20px rgba(0, 0, 0, 0.3);
        z-index: 999;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        overflow-y: auto;
      }
      .navbar-center.show {
        display: flex;
        transform: translateX(0);
      }
      .navbar-menu-controls {
        display: flex;
        width: 100%;
        justify-content: flex-start;
        align-items: center;
        gap: 1rem;
        margin: 0;
        padding: 1rem 0 0.35rem;
        border-top: 1px solid rgba(255, 255, 255, 0.2);
        border-bottom: none;
      }
      .navbar-right-desktop {
        display: none;
      }
      .navbar-right {
        display: flex;
        width: 100%;
        justify-content: flex-start;
        margin-top: 0;
        margin-left: 0;
        padding: 0.65rem 0 1.25rem;
        border-top: none;
        position: relative;
      }
      .logout-btn {
        width: 100%;
        justify-content: center;
      }
      .main-container {
        padding: 1rem 1rem 0;
        padding-top: calc(80px + 1rem);
        padding-bottom: calc(5.5rem + env(safe-area-inset-bottom, 0px));
      }

      .navbar-logo .brand__text {
        font-size: clamp(0.82rem, 2vw, 1rem);
      }
      .navbar-logo .brand__mark {
        width: 40px;
        height: 40px;
      }
      
      .profile-page-title {
        font-size: 2rem;
      }
      
      main {
        padding: 0 0.75rem;
        margin-top: 0.35rem;
      }
      .accounts-header {
        margin-bottom: 0.85rem;
      }
    }

    @media (max-width: 768px) {
      .navbar {
        padding: 0.5rem 0.75rem;
      }
      .logout-btn {
        padding: 0.6rem 1.25rem;
        font-size: 0.95rem;
      }
      .calendar-icon,
      .language-selector {
        width: 36px;
        height: 36px;
      }
      
      .accounts-grid {
        grid-template-columns: 1fr;
      }
      .accounts-hero {
        flex-direction: column;
        align-items: center;
        text-align: center;
      }
      .accounts-hero-mascot {
        flex: none;
      }
      .accounts-total-card::before {
        display: none;
      }
      .accounts-header {
        flex-direction: column;
        align-items: flex-start;
      }
      .accounts-header-profile .accounts-header-text {
        text-align: left;
        width: 100%;
      }
      .accounts-header-right {
        width: 100%;
        justify-content: flex-end;
      }
      
      .nav-link {
        padding: 0.5rem 0.75rem !important;
        font-size: 0.9rem;
      }
      
      .profile-header {
        padding: 1.5rem 1rem;
      }
      
      .profile-header h1 {
        font-size: 1.75rem;
      }
      
      .profile-card {
        padding: 1.5rem;
      }
      
      .info-row {
        grid-template-columns: 1fr;
        gap: 1rem;
      }

      .account-menu-tabs {
        flex-direction: column;
        gap: 0.5rem;
      }

      .account-tab-btn {
        width: 100%;
        text-align: center;
        padding: 0.875rem 1.5rem;
      }

      .account-content-card {
        padding: 1.5rem;
      }

      .accounts-grid {
        grid-template-columns: 1fr;
      }
      .accounts-hero {
        flex-direction: column;
        align-items: center;
        text-align: center;
      }
      .accounts-hero-mascot {
        flex: none;
      }
      .accounts-total-card {
        margin-left: 0;
        width: 100%;
      }
      .accounts-total-card::before {
        display: none;
      }
      .accounts-header {
        flex-direction: column;
        align-items: flex-start;
      }
    }

    @media (max-width: 576px) {
      .navbar {
        padding: 0.5rem;
      }
      .navbar-content {
        gap: 0.5rem;
      }
      .navbar-left {
        gap: 0.5rem;
      }
      .calendar-icon,
      .language-selector {
        width: 32px;
        height: 32px;
      }
      .navbar-logo .brand__text {
        font-size: 0.82rem;
      }
      .navbar-logo .brand__place {
        font-size: 0.55rem;
      }
      .navbar-logo .brand__mark {
        width: 36px;
        height: 36px;
      }
      .logout-btn {
        padding: 0.55rem 1rem;
        font-size: 0.9rem;
        width: 100%;
        justify-content: center;
      }
      .main-container {
        padding: 0.75rem 0.75rem 0;
        padding-top: calc(70px + 0.75rem);
        padding-bottom: calc(5.25rem + env(safe-area-inset-bottom, 0px));
      }
      
      .profile-header {
        padding: 1rem;
      }
      
      .profile-header h1 {
        font-size: 1.5rem;
      }
      
      .profile-card {
        padding: 1rem;
      }

      .info-row {
        grid-template-columns: 1fr;
      }

      .btn-group-profile {
        flex-direction: column;
      }

      .btn-profile {
        width: 100%;
        margin-bottom: 0.5rem;
      }
      
      .profile-image-section {
        text-align: center;
      }
      
      .profile-image {
        width: 120px;
        height: 120px;
      }
    }
    
    @media (max-width: 480px) {
      .profile-header h1 {
        font-size: 1.3rem;
      }
      
      .profile-card {
        padding: 0.75rem;
      }
      
      .info-item {
        padding: 0.75rem;
      }
      
      .info-item label {
        font-size: 0.85rem;
      }
      
      .info-item span {
        font-size: 0.9rem;
      }
    }

    /* Elevated date card */
    .chart-date-hero {
      display: flex;
      flex-direction: column;
      gap: 0.2rem;
      margin-bottom: 1rem;
      animation: chartDateIn 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
    }
    .chart-date-kicker {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      font-size: 0.68rem;
      font-weight: 800;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: rgba(255, 255, 255, 0.72);
    }
    .chart-date-live {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #ff6b35;
      box-shadow: 0 0 0 0 rgba(255, 107, 53, 0.55);
      animation: chartLivePulse 1.8s ease-out infinite;
    }
    .chart-date-range {
      display: flex;
      flex-direction: column;
      gap: 0.05rem;
      font-size: inherit;
      font-weight: inherit;
      color: inherit;
      margin-bottom: 0;
    }
    .chart-date-weekday {
      font-size: 0.78rem;
      font-weight: 600;
      color: rgba(255, 255, 255, 0.78);
      letter-spacing: 0.04em;
    }
    .chart-date-main {
      display: flex;
      align-items: baseline;
      gap: 0.45rem;
      line-height: 1;
    }
    .chart-date-month {
      font-family: 'DM Serif Display', Georgia, serif;
      font-size: 1.35rem;
      font-weight: 400;
      color: #fff;
    }
    .chart-date-daynum {
      font-family: 'DM Serif Display', Georgia, serif;
      font-size: 2.05rem;
      font-weight: 400;
      background: linear-gradient(180deg, #ffffff 20%, #ffd4c2 100%);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      animation: chartDayGlow 2.8s ease-in-out infinite;
    }
    .chart-date-year {
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.18em;
      color: rgba(255, 255, 255, 0.62);
    }
    .chart-bar-wrap .chart-bar {
      transform-origin: bottom center;
      animation: chartBarRise 0.75s cubic-bezier(0.22, 1, 0.36, 1) both;
    }
    .chart-bar-wrap:nth-child(1) .chart-bar { animation-delay: 0.05s; }
    .chart-bar-wrap:nth-child(2) .chart-bar { animation-delay: 0.12s; }
    .chart-bar-wrap:nth-child(3) .chart-bar { animation-delay: 0.19s; }
    .chart-bar-wrap:nth-child(4) .chart-bar { animation-delay: 0.26s; }
    .chart-bar-wrap:nth-child(5) .chart-bar { animation-delay: 0.33s; }
    .chart-bar-wrap:nth-child(6) .chart-bar { animation-delay: 0.4s; }
    .chart-bar-wrap:nth-child(7) .chart-bar { animation-delay: 0.47s; }
    .chart-bar-wrap.is-today .chart-bar {
      box-shadow: 0 0 14px rgba(255, 107, 53, 0.65);
    }
    .chart-bar-wrap.is-today .chart-day {
      color: #ffd4c2;
    }
    @keyframes chartDateIn {
      from { opacity: 0; transform: translateY(12px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes chartLivePulse {
      0% { box-shadow: 0 0 0 0 rgba(255, 107, 53, 0.55); }
      70% { box-shadow: 0 0 0 8px rgba(255, 107, 53, 0); }
      100% { box-shadow: 0 0 0 0 rgba(255, 107, 53, 0); }
    }
    @keyframes chartDayGlow {
      0%, 100% { filter: brightness(1); transform: translateY(0); }
      50% { filter: brightness(1.18); transform: translateY(-1px); }
    }
    @keyframes chartBarRise {
      from { transform: scaleY(0); opacity: 0.3; }
      to { transform: scaleY(1); opacity: 1; }
    }

    /* Dark mode: light dark cards */
    body.darkmode .accounts-total-card,
    body.darkmode .accounts-insights-card,
    body.darkmode .accounts-chart-card,
    body.darkmode .accounts-card,
    body.darkmode .account-card,
    body.darkmode .account-roll-btn,
    body.darkmode .account-item-card,
    body.darkmode .profile-action-card,
    body.darkmode .account-content-card,
    body.darkmode .account-card-expandable {
      background: rgba(55, 65, 81, 0.9) !important;
      border-color: rgba(255, 255, 255, 0.25) !important;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important;
    }
    /* Dark mode: icons indigo, visible on light-dark cards */
    body.darkmode .account-card-icon,
    body.darkmode .account-item-icon {
      color: #667eea !important;
      background: rgba(255, 255, 255, 0.25) !important;
    }
    body.darkmode .accounts-total-card .accounts-total-label,
    body.darkmode .accounts-total-card .accounts-total-value,
    body.darkmode .accounts-insights-card p,
    body.darkmode .accounts-chart-card .chart-label,
    body.darkmode .accounts-chart-card .chart-date-range,
    body.darkmode .accounts-chart-card .chart-day,
    body.darkmode .account-card .account-name,
    body.darkmode .account-card .account-type,
    body.darkmode .profile-action-card h3,
    body.darkmode .profile-action-card p,
    body.darkmode .account-content-card .info-item label,
    body.darkmode .account-content-card .info-item span,
    body.darkmode .account-content-card input,
    body.darkmode .account-item-card h3,
    body.darkmode .account-item-card .sublabel,
    body.darkmode .account-item-card .balance-value {
      color: rgba(255, 255, 255, 0.95) !important;
    }
    body.darkmode .account-item-card .balance-label,
    body.darkmode .account-item-card .sublabel {
      color: rgba(255, 255, 255, 0.75) !important;
    }
    body.darkmode .account-card .account-expand-icon,
    body.darkmode .account-card .account-expand-icon i {
      color: rgba(255, 255, 255, 0.95) !important;
    }
    /* Dark mode: input fields - lighter dark bg (no white), white text for visibility */
    body.darkmode .account-content-card .info-item input,
    body.darkmode .account-content-card .info-item select,
    body.darkmode .account-content-card .info-item textarea {
      background: rgba(71, 85, 105, 0.85) !important;
      background-color: rgba(71, 85, 105, 0.85) !important;
      color: rgba(255, 255, 255, 0.95) !important;
      border-color: rgba(255, 255, 255, 0.25) !important;
    }
    body.darkmode .account-content-card .info-item input:focus,
    body.darkmode .account-content-card .info-item select:focus,
    body.darkmode .account-content-card .info-item textarea:focus {
      background: rgba(71, 85, 105, 0.95) !important;
      background-color: rgba(71, 85, 105, 0.95) !important;
    }
    body.darkmode .account-content-card .info-item input::placeholder,
    body.darkmode .account-content-card .info-item textarea::placeholder {
      color: rgba(255, 255, 255, 0.65) !important;
    }
    body.darkmode .account-content-card .info-item input:disabled {
      background: rgba(55, 65, 81, 0.7) !important;
      background-color: rgba(55, 65, 81, 0.7) !important;
      color: rgba(255, 255, 255, 0.9) !important;
    }
    body.darkmode .account-content-card .info-item input:-webkit-autofill,
    body.darkmode .account-content-card .info-item input:-webkit-autofill:focus {
      -webkit-text-fill-color: rgba(255, 255, 255, 0.95) !important;
      -webkit-box-shadow: 0 0 0 1000px rgba(71, 85, 105, 0.85) inset !important;
      box-shadow: 0 0 0 1000px rgba(71, 85, 105, 0.85) inset !important;
    }
    /* Password hint + other helper text in forms (Bootstrap .text-muted is gray) */
    body.darkmode .account-content-card .info-item small.text-muted {
      color: rgba(255, 255, 255, 0.95) !important;
    }
    body.darkmode .profile-receipt-hint {
      color: rgba(255, 255, 255, 0.8) !important;
    }
    body.darkmode #profileReceiptFeedback.is-success {
      color: #5eead4 !important;
    }
    body.darkmode #profileReceiptFeedback.is-error {
      color: #fca5a5 !important;
    }
    body.darkmode .profile-receipt-file-shell {
      background: rgba(64, 75, 105, 0.92) !important;
      border-color: rgba(255, 255, 255, 0.22) !important;
    }
    body.darkmode .profile-receipt-file-shell:hover {
      border-color: rgba(167, 180, 251, 0.5) !important;
    }
    body.darkmode .profile-receipt-file-shell:focus-within {
      border-color: #a5b4fc !important;
      box-shadow: 0 0 0 4px rgba(165, 180, 252, 0.2), 0 4px 12px rgba(0, 0, 0, 0.2) !important;
    }
    body.darkmode .profile-receipt-file-name {
      color: rgba(255, 255, 255, 0.9) !important;
    }
    body.darkmode .profile-receipt-form .info-item .profile-receipt-file-input {
      background: transparent !important;
      background-color: transparent !important;
      border: none !important;
      box-shadow: none !important;
      color: transparent !important;
    }
    body.darkmode .activity-log-item {
      background: rgba(71, 85, 105, 0.55) !important;
      border-color: rgba(255, 255, 255, 0.2) !important;
    }
    body.darkmode .activity-log-item:hover {
      background: rgba(71, 85, 105, 0.75) !important;
    }
    body.darkmode .activity-log-item-icon {
      background: rgba(255, 255, 255, 0.2) !important;
      color: #a5b4fc !important;
    }
    body.darkmode .activity-log-type {
      color: rgba(255, 255, 255, 0.98) !important;
    }
    body.darkmode .activity-log-details {
      color: rgba(255, 255, 255, 0.88) !important;
    }
    body.darkmode .activity-log-time {
      color: rgba(255, 255, 255, 0.65) !important;
    }
    body.darkmode .activity-log-empty {
      background: rgba(55, 65, 81, 0.6) !important;
      border-color: rgba(255, 255, 255, 0.2) !important;
      color: rgba(255, 255, 255, 0.85) !important;
    }
    body.darkmode .activity-log-entries::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.25);
    }

    /* Elevated profile section */
    .profile-elevate { position: relative; }
    .profile-theme-row {
      display: flex;
      justify-content: flex-end;
      margin-bottom: 0.65rem;
    }
    .profile-stage,
    .profile-stat-card,
    .profile-apps-panel,
    .profile-today-card {
      position: relative;
      overflow: hidden;
    }
    .profile-manage-panel {
      position: relative;
      overflow: visible;
    }
    .profile-stage { padding: 1.55rem 1.7rem 1.4rem; margin-bottom: 1.1rem; }
    .profile-stage-body {
      position: relative;
      z-index: 1;
      display: flex;
      align-items: center;
      gap: 1.25rem;
    }
    .profile-stage .profile-pic-circle {
      width: 92px;
      height: 92px;
      min-width: 92px;
      min-height: 92px;
      border-width: 3px;
    }
    .profile-stage-copy { min-width: 0; flex: 1; }
    .profile-stage-mascot {
      flex: 0 0 auto;
      width: 150px;
      height: 150px;
      margin-left: auto;
      display: flex;
      align-items: center;
      justify-content: center;
      pointer-events: none;
    }
    .profile-stage-mascot img {
      width: 150px;
      height: 150px;
      object-fit: contain;
      display: block;
    }
    .profile-stage .accounts-title { margin: 0; }
    .profile-person-name {
      margin: 0.28rem 0 0.95rem;
      font-size: 1.28rem;
      font-weight: 700;
      letter-spacing: -0.02em;
    }
    .profile-stage-actions { display: flex; flex-wrap: wrap; gap: 0.6rem; }
    .profile-ghost-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.62rem 1.15rem;
      border-radius: 999px;
      border: 1px solid rgba(28, 28, 30, 0.16);
      background: transparent;
      font-weight: 650;
      font-size: 0.95rem;
      cursor: pointer;
      text-decoration: none;
      color: inherit;
    }
    .profile-ghost-btn:hover { transform: translateY(-1px); }
    body.darkmode .profile-ghost-btn { border-color: rgba(255, 255, 255, 0.22); }
    .profile-stats {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 0.85rem;
      margin-bottom: 1.1rem;
    }
    .profile-stat-card {
      min-height: 112px;
      padding: 1rem 1.05rem 0.9rem;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .profile-stat-top {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 0.5rem;
    }
    .profile-stat-value {
      font-size: 2.05rem;
      font-weight: 750;
      line-height: 1;
      letter-spacing: -0.04em;
    }
    .profile-stat-icon {
      width: 36px;
      height: 36px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(255, 107, 53, 0.14);
      color: #ff6b35;
      flex-shrink: 0;
    }
    .profile-stat-label {
      margin-top: 0.85rem;
      font-size: 0.68rem;
      font-weight: 750;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      opacity: 0.62;
    }
    .profile-apps-panel,
    .profile-manage-panel,
    .profile-today-card { padding: 1.2rem 1.3rem 1.1rem; margin-bottom: 1.1rem; }
    .profile-block-head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 0.35rem;
    }
    .profile-block-title { margin: 0 0 0.2rem; font-size: 1.18rem; font-weight: 750; letter-spacing: -0.02em; }
    .profile-block-sub { margin: 0; font-size: 0.92rem; opacity: 0.7; max-width: 38rem; }
    .profile-apps-panel .profile-app-row {
      background: transparent;
      box-shadow: none;
      border-radius: 16px;
      padding: 0.95rem 0.85rem;
      margin-top: 0.7rem;
    }
    /* Manage account — heading on page bg, standalone rows (matches mock) */
    .profile-manage-panel {
      padding: 0.4rem 0 0;
      margin-bottom: 1.1rem;
      overflow: visible;
    }
    body.darkmode .profile-manage-panel {
      padding: 1.2rem 1.3rem 1.1rem;
    }
    .profile-manage-head { margin: 0 0 1rem 0.15rem; }
    .profile-manage-title {
      margin: 0 0 0.3rem;
      font-size: 1.32rem;
      font-weight: 650;
      letter-spacing: -0.015em;
      color: #ffffff;
    }
    .profile-manage-sub {
      margin: 0;
      font-size: 0.92rem;
      color: rgba(255, 255, 255, 0.72);
    }
    .profile-manage-panel .account-card {
      display: flex;
      flex-direction: column;
      align-items: stretch;
      gap: 0;
      padding: 0;
      margin: 0 0 0.9rem;
      border-radius: 16px;
      box-sizing: border-box;
    }
    /* Stable shell: collapsed vs expanded must match box metrics (no width/shadow jump) */
    body.page-profile .profile-manage-panel .account-card.account-card-expandable {
      border: 1px solid rgba(255, 255, 255, 0.08) !important;
      border-radius: 16px !important;
      background: rgba(255, 255, 255, 0.04) !important;
      background-color: rgba(255, 255, 255, 0.04) !important;
      box-shadow: none !important;
    }
    body.page-profile .profile-manage-panel .account-card.account-card-expandable.expanded {
      border-color: rgba(255, 255, 255, 0.08) !important;
      background: rgba(255, 255, 255, 0.04) !important;
      background-color: rgba(255, 255, 255, 0.04) !important;
      box-shadow: none !important;
    }
    body:not(.darkmode).page-profile .profile-manage-panel .account-card.account-card-expandable,
    body:not(.darkmode).page-profile .profile-manage-panel .account-card.account-card-expandable.expanded {
      background: #ffffff !important;
      background-color: #ffffff !important;
      border: 1px solid rgba(28, 28, 30, 0.08) !important;
      box-shadow: 0 10px 28px rgba(46, 35, 108, 0.12) !important;
    }
    .profile-manage-panel .account-card-header-row {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1rem 1.25rem;
      min-height: 76px;
      cursor: pointer;
    }
    .profile-manage-panel .account-card-icon {
      width: 46px;
      height: 46px;
      min-width: 46px;
      border-radius: 11px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(255, 107, 53, 0.12);
      border: 1px solid rgba(255, 107, 53, 0.55);
      color: #ff6b35;
      font-size: 1.1rem;
    }
    .profile-manage-panel .account-card-icon i { color: #ff6b35; }
    .profile-manage-panel .account-card-body { flex: 1; min-width: 0; }
    .profile-manage-panel .account-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      margin: 0;
    }
    .profile-manage-panel .account-name {
      margin: 0 0 0.2rem;
      font-size: 1.02rem;
      font-weight: 700;
    }
    .profile-manage-panel .account-type {
      display: block;
      font-size: 0.88rem;
      opacity: 0.72;
    }
    .profile-manage-panel .account-expand-icon {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 28px;
      height: 28px;
      flex-shrink: 0;
      font-size: 0.95rem;
      opacity: 0.75;
    }
    .profile-manage-panel .account-card-expand-content {
      display: block;
      padding: 0 1.25rem 1.35rem;
      overflow: visible;
    }
    .profile-manage-panel .account-card-expand-content[hidden] {
      display: none !important;
      padding: 0 !important;
    }
    .profile-manage-panel .account-card-expand-content > .account-content-card {
      min-height: auto;
      overflow: visible;
    }
    .profile-manage-panel .account-card-expandable.expanded .account-expand-icon,
    .profile-manage-panel .account-card-expandable.expanded .account-expand-icon i {
      color: #ff6b35 !important;
      opacity: 1;
    }
    body.page-profile .profile-manage-panel .profile-manage-form-panel {
      margin: 0;
      padding: 1.35rem 1.35rem 1.25rem;
      border-radius: 18px;
      border: 1px solid rgba(255, 107, 53, 0.55) !important;
      background: rgba(0, 0, 0, 0.18) !important;
      background-color: rgba(0, 0, 0, 0.18) !important;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04) !important;
      transform: none !important;
    }
    body:not(.darkmode).page-profile .profile-manage-panel .profile-manage-form-panel {
      background: rgba(28, 28, 30, 0.04) !important;
      background-color: rgba(28, 28, 30, 0.04) !important;
      border-color: rgba(255, 107, 53, 0.45) !important;
    }
    body.darkmode.page-profile .profile-manage-panel .profile-manage-form-panel {
      background: rgba(0, 0, 0, 0.22) !important;
      background-color: rgba(0, 0, 0, 0.22) !important;
      border-color: rgba(255, 107, 53, 0.5) !important;
    }
    .profile-manage-form-panel .info-item {
      margin-bottom: 1.15rem;
    }
    .profile-manage-form-panel .info-item label {
      margin-bottom: 0.45rem;
      font-size: 0.68rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: rgba(255, 255, 255, 0.55);
      gap: 0;
    }
    .profile-manage-form-panel .info-item label::before {
      display: none;
    }
    body:not(.darkmode) .profile-manage-form-panel .info-item label {
      color: rgba(28, 28, 30, 0.55);
    }
    .profile-manage-form-panel .info-item input,
    .profile-manage-form-panel .info-item textarea {
      width: 100%;
      padding: 0.85rem 1rem;
      border-radius: 14px;
      border: 1px solid rgba(255, 255, 255, 0.12);
      background: rgba(0, 0, 0, 0.28);
      color: inherit;
      font-size: 0.95rem;
      box-shadow: none;
      transform: none;
    }
    body:not(.darkmode) .profile-manage-form-panel .info-item input,
    body:not(.darkmode) .profile-manage-form-panel .info-item textarea {
      border-color: rgba(28, 28, 30, 0.12);
      background: rgba(28, 28, 30, 0.04);
      color: #1c1c1e;
    }
    .profile-manage-form-panel .info-item input:focus,
    .profile-manage-form-panel .info-item textarea:focus {
      outline: none;
      border-color: rgba(255, 107, 53, 0.55);
      box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.12);
      transform: none;
    }
    .profile-manage-form-panel .info-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }
    @media (max-width: 640px) {
      .profile-manage-form-panel .info-row { grid-template-columns: 1fr; }
    }
    .profile-password-form .profile-pw-stack {
      display: flex;
      flex-direction: column;
      gap: 1.05rem;
    }
    .profile-password-form .profile-pw-block .info-item {
      margin-bottom: 0;
    }
    .profile-password-form .profile-pw-block--split {
      display: flex;
      flex-direction: column;
      gap: 1.05rem;
    }
    .profile-password-field {
      position: relative;
      width: 100%;
    }
    .profile-password-form .profile-password-field input {
      min-height: 3rem;
      padding: 0.8rem 2.85rem 0.8rem 1rem !important;
    }
    .profile-password-field input {
      padding-right: 2.75rem !important;
    }
    .profile-password-toggle {
      position: absolute;
      right: 0.85rem;
      top: 50%;
      transform: translateY(-50%);
      border: none;
      background: transparent;
      color: rgba(255, 255, 255, 0.45);
      cursor: pointer;
      padding: 0.25rem;
      line-height: 1;
    }
    body:not(.darkmode) .profile-password-toggle { color: rgba(28, 28, 30, 0.45); }
    .profile-pw-strength {
      margin-top: 0.75rem;
      padding: 0.9rem 1rem;
      border-radius: 14px;
      border: 1px solid rgba(255, 255, 255, 0.08);
      background: rgba(255, 255, 255, 0.03);
    }
    body:not(.darkmode) .profile-pw-strength {
      border-color: rgba(28, 28, 30, 0.08);
      background: rgba(28, 28, 30, 0.03);
    }
    .profile-pw-strength-bars {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 0.35rem;
      margin-bottom: 0.5rem;
    }
    .profile-pw-strength-bars span {
      height: 4px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.12);
    }
    body:not(.darkmode) .profile-pw-strength-bars span { background: rgba(28, 28, 30, 0.12); }
    .profile-pw-strength-bars span.is-on { background: #ff6b35; }
    .profile-pw-strength-label {
      margin: 0 0 0.7rem;
      font-size: 0.82rem;
      opacity: 0.78;
    }
    .profile-pw-strength-label strong { font-weight: 750; opacity: 1; }
    .profile-pw-rules {
      list-style: none;
      margin: 0;
      padding: 0;
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.45rem 0.85rem;
      font-size: 0.76rem;
      opacity: 0.72;
    }
    @media (min-width: 768px) {
      .profile-password-form .profile-pw-rules {
        grid-template-columns: repeat(4, minmax(0, 1fr));
      }
    }
    .profile-pw-rules li {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
    }
    .profile-pw-rules li::before {
      content: '';
      width: 7px;
      height: 7px;
      border-radius: 50%;
      border: 1px solid rgba(255, 255, 255, 0.35);
      flex-shrink: 0;
    }
    body:not(.darkmode) .profile-pw-rules li::before { border-color: rgba(28, 28, 30, 0.35); }
    .profile-pw-rules li.is-met { opacity: 1; color: #ff6b35; }
    .profile-pw-rules li.is-met::before {
      background: #ff6b35;
      border-color: #ff6b35;
    }
    .profile-manage-form-panel .profile-forgot-wrap {
      margin-top: 0.5rem;
      text-align: right;
    }
    .profile-password-form .profile-pw-footer {
      margin-top: 1.15rem;
      padding-top: 1.1rem;
      border-top: 1px solid rgba(255, 255, 255, 0.08);
    }
    body:not(.darkmode) .profile-password-form .profile-pw-footer {
      border-top-color: rgba(28, 28, 30, 0.08);
    }
    .profile-manage-form-panel .form-link {
      color: rgba(255, 255, 255, 0.55);
      font-size: 0.82rem;
      font-weight: 500;
    }
    body:not(.darkmode) .profile-manage-form-panel .form-link {
      color: rgba(28, 28, 30, 0.55);
    }
    .profile-manage-form-panel .profile-manage-submit {
      margin-top: 0.35rem;
      padding: 0.85rem 1.35rem;
      border: none;
      border-radius: 999px;
      background: #ff6b35;
      color: #0b0b0d;
      font-weight: 750;
      font-size: 0.92rem;
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      cursor: pointer;
      box-shadow: 0 8px 24px rgba(255, 107, 53, 0.28);
    }
    .profile-manage-form-panel .profile-manage-submit:hover {
      filter: brightness(1.05);
    }
    .profile-manage-form-panel .profile-manage-submit:disabled {
      opacity: 0.55;
      cursor: not-allowed;
    }
    .profile-manage-form-panel .profile-receipt-file-shell {
      min-height: 4.5rem;
      border-radius: 14px;
      border: 1px dashed rgba(255, 255, 255, 0.22);
      background: rgba(0, 0, 0, 0.22);
      box-shadow: none;
    }
    body:not(.darkmode) .profile-manage-form-panel .profile-receipt-file-shell {
      border-color: rgba(28, 28, 30, 0.18);
      background: rgba(28, 28, 30, 0.03);
    }
    .profile-manage-form-panel .profile-receipt-file-inner {
      flex-wrap: wrap;
      min-height: 4.5rem;
      padding: 0.85rem 1rem;
    }
    .profile-manage-form-panel .profile-receipt-file-btn {
      padding: 0.55rem 0.95rem;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.18);
      background: rgba(255, 255, 255, 0.06);
      color: inherit;
      font-size: 0.82rem;
      font-weight: 650;
      box-shadow: none;
      transform: none;
    }
    body:not(.darkmode) .profile-manage-form-panel .profile-receipt-file-btn {
      border-color: rgba(28, 28, 30, 0.14);
      background: rgba(28, 28, 30, 0.05);
      color: #1c1c1e;
    }
    .profile-manage-form-panel .profile-receipt-file-shell:hover .profile-receipt-file-btn {
      transform: none;
      box-shadow: none;
    }
    .profile-manage-form-panel .profile-receipt-file-name,
    .profile-manage-form-panel .profile-receipt-hint {
      font-size: 0.78rem;
      opacity: 0.65;
      color: inherit;
    }
    .profile-manage-form-panel .profile-receipt-hint {
      margin-top: 0;
      flex: 1;
      min-width: 8rem;
    }
    .profile-receipt-notes-wrap textarea {
      min-height: 7.5rem;
      resize: vertical;
    }
    .profile-receipt-char-count {
      margin-top: 0.35rem;
      font-size: 0.72rem;
      opacity: 0.55;
    }
    .profile-manage-panel .account-content-card {
      padding: 0;
      margin: 0;
      border: none;
      background: transparent;
      box-shadow: none;
      transform: none !important;
    }
    body.darkmode.page-profile .profile-manage-form-panel .info-item input,
    body.darkmode.page-profile .profile-manage-form-panel .info-item textarea {
      background: rgba(0, 0, 0, 0.28) !important;
      background-color: rgba(0, 0, 0, 0.28) !important;
      border-color: rgba(255, 255, 255, 0.12) !important;
      color: rgba(255, 255, 255, 0.95) !important;
    }
    body.darkmode.page-profile .profile-manage-form-panel .info-item label {
      color: rgba(255, 255, 255, 0.55) !important;
    }
    body.darkmode.page-profile .profile-manage-form-panel .profile-receipt-file-shell {
      background: rgba(0, 0, 0, 0.22) !important;
      border-color: rgba(255, 255, 255, 0.22) !important;
    }
    /* Edit profile modal — centered glass dialog */
    .profile-edit-modal .modal-dialog { max-width: 640px; }
    .profile-edit-modal-content {
      border-radius: 22px;
      border: 1px solid rgba(255, 255, 255, 0.65);
      background: #ffffff;
      color: #1c1c1e;
      box-shadow: 0 24px 60px rgba(20, 16, 60, 0.35);
      overflow: hidden;
    }
    .profile-edit-modal-header {
      align-items: flex-start;
      padding: 1.35rem 1.5rem 0.75rem;
      border-bottom: none;
    }
    .profile-edit-modal-header .modal-title {
      font-size: 1.3rem;
      font-weight: 750;
      letter-spacing: -0.02em;
      color: inherit;
    }
    .profile-edit-modal-sub { margin: 0.2rem 0 0; font-size: 0.9rem; opacity: 0.7; }
    .profile-edit-modal-body { padding: 0.5rem 1.5rem 1.5rem; }
    .profile-edit-modal .profile-manage-form-panel {
      margin: 0;
    }
    .profile-edit-modal .profile-form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }
    @media (max-width: 640px) {
      .profile-edit-modal .profile-form-row { grid-template-columns: 1fr; }
    }
    .profile-edit-modal .info-item input:disabled { opacity: 0.72; }
    .profile-edit-modal .btn-group-profile {
      margin-top: 0.5rem;
      justify-content: flex-start;
      gap: 0.65rem;
    }
    .profile-edit-modal .btn-profile.btn-primary-profile {
      background: #ff6b35;
      color: #0b0b0d;
      border-radius: 999px;
      box-shadow: 0 8px 24px rgba(255, 107, 53, 0.28);
    }
    .profile-edit-modal .btn-profile.btn-primary-profile::before { display: none; }
    .profile-edit-modal .btn-profile.btn-secondary-profile {
      border-radius: 999px;
      border: 1px solid rgba(255, 107, 53, 0.35);
      background: transparent;
      color: inherit;
    }
    body.darkmode .profile-edit-modal-content {
      background: #0b0b0d;
      color: #ffffff;
      border-color: rgba(255, 255, 255, 0.1);
    }
    body.darkmode .profile-edit-modal .btn-close { filter: invert(1) grayscale(1) brightness(1.6); }
    .profile-app-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.85rem;
    }
    .profile-app-copy { min-width: 0; }
    .profile-app-title { margin: 0 0 0.15rem; font-size: 1rem; font-weight: 750; }
    .profile-app-meta { margin: 0; font-size: 0.88rem; opacity: 0.68; }
    .profile-app-side { display: flex; align-items: center; gap: 0.55rem; flex-shrink: 0; }
    .profile-status-pill {
      display: inline-flex;
      align-items: center;
      padding: 0.32rem 0.7rem;
      border-radius: 999px;
      font-size: 0.68rem;
      font-weight: 800;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      border: 1px solid transparent;
    }
    .profile-status-pill.status-pending { color: #b45309; background: rgba(245, 158, 11, 0.16); border-color: rgba(217, 119, 6, 0.35); }
    .profile-status-pill.status-approved { color: #047857; background: rgba(16, 185, 129, 0.14); border-color: rgba(5, 150, 105, 0.3); }
    .profile-status-pill.status-incomplete { color: #c2410c; background: rgba(255, 107, 53, 0.14); border-color: rgba(234, 88, 12, 0.32); }
    body.darkmode .profile-status-pill.status-pending { color: #fbbf24; }
    body.darkmode .profile-status-pill.status-approved { color: #6ee7b7; }
    body.darkmode .profile-status-pill.status-incomplete { color: #fdba74; }
    .profile-apps-empty { margin: 0.85rem 0 0.2rem; opacity: 0.75; }
    .profile-today-card .chart-date-hero { margin: 0 0 1rem; }
    .profile-today-card .chart-bars {
      height: 88px;
      margin-top: 0.35rem;
    }
    .profile-elevate .activity-log-card,
    .profile-elevate .profile-activity-panel { margin-bottom: 1.5rem; }

    /* Elevated application cards — override legacy .account-card row layout */
    .profile-apps-list {
      display: flex;
      flex-direction: column;
      gap: 0.85rem;
      margin-top: 0.85rem;
      width: 100%;
    }
    .profile-apps-panel .profile-app-card.account-card,
    .profile-apps-panel .profile-app-card {
      display: flex !important;
      flex-direction: column !important;
      align-items: stretch !important;
      justify-content: flex-start !important;
      width: 100% !important;
      max-width: 100% !important;
      box-sizing: border-box;
      gap: 0.85rem !important;
      padding: 1.1rem 1.15rem 1rem !important;
      margin: 0 !important;
      border-radius: 18px !important;
    }
    .profile-apps-panel .profile-app-card-top,
    .profile-apps-panel .profile-app-card-foot {
      display: flex !important;
      flex-direction: row !important;
      align-items: flex-start !important;
      justify-content: space-between !important;
      width: 100% !important;
      max-width: 100% !important;
      gap: 1rem;
      box-sizing: border-box;
    }
    .profile-apps-panel .profile-app-card-foot {
      align-items: flex-end !important;
    }
    .profile-apps-panel .profile-app-copy,
    .profile-apps-panel .profile-app-foot-copy {
      flex: 1 1 auto !important;
      min-width: 0 !important;
      max-width: none !important;
      padding-right: 0.75rem;
    }
    .profile-apps-panel .profile-app-title { margin: 0 0 0.2rem; font-size: 1.08rem; font-weight: 750; }
    .profile-apps-panel .profile-app-meta { margin: 0; font-size: 0.88rem; opacity: 0.68; }
    .profile-apps-panel .profile-status-pill {
      flex: 0 0 auto !important;
      margin-left: auto !important;
      margin-right: 0 !important;
      align-self: flex-start !important;
    }
    .profile-status-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      white-space: nowrap;
      flex-shrink: 0;
    }
    .profile-status-pill i { font-size: 0.78rem; }
    .profile-status-pill.status-renewal_due {
      color: #d97706;
      background: rgba(245, 158, 11, 0.14);
      border-color: rgba(245, 158, 11, 0.45);
    }
    .profile-apps-panel .profile-app-progress {
      width: 100% !important;
      height: 4px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.12);
      overflow: hidden;
      flex: 0 0 auto;
    }
    body:not(.darkmode) .profile-apps-panel .profile-app-progress { background: rgba(28, 28, 30, 0.1); }
    .profile-app-progress-fill {
      display: block;
      height: 100%;
      border-radius: inherit;
      background: #ff6b35;
    }
    .profile-app-foot-meta {
      margin: 0;
      font-size: 0.82rem;
      opacity: 0.72;
      line-height: 1.4;
    }
    .profile-app-due-line {
      margin: 0.28rem 0 0;
      font-size: 0.8rem;
      font-weight: 650;
      color: #fbbf24;
      text-transform: lowercase;
    }
    body:not(.darkmode) .profile-app-due-line { color: #b45309; }
    .profile-apps-panel .profile-app-cta {
      display: inline-flex !important;
      align-items: center;
      gap: 0.4rem;
      flex: 0 0 auto !important;
      margin-left: auto !important;
      margin-right: 0 !important;
      align-self: flex-end !important;
      padding: 0.45rem 0.9rem;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.18);
      background: transparent;
      color: inherit;
      text-decoration: none;
      font-size: 0.86rem;
      font-weight: 650;
      white-space: nowrap;
    }
    body:not(.darkmode) .profile-apps-panel .profile-app-cta { border-color: rgba(28, 28, 30, 0.14); }

    /* Elevated activity log */
    .profile-activity-panel {
      padding: 1.25rem 1.3rem 1.15rem;
    }
    .profile-activity-head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 1rem;
    }
    .profile-activity-count {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.4rem 0.8rem;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.14);
      font-size: 0.72rem;
      font-weight: 750;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      white-space: nowrap;
      opacity: 0.9;
    }
    body:not(.darkmode) .profile-activity-count { border-color: rgba(28, 28, 30, 0.12); }
    .profile-activity-filters {
      display: flex;
      flex-wrap: wrap;
      gap: 0.45rem;
      margin-bottom: 1.15rem;
      padding: 0.45rem;
      border-radius: 16px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      background: rgba(0, 0, 0, 0.28);
    }
    body:not(.darkmode) .profile-activity-filters {
      border-color: rgba(28, 28, 30, 0.1);
      background: rgba(28, 28, 30, 0.05);
    }
    .profile-activity-filter {
      display: inline-flex;
      align-items: center;
      gap: 0.42rem;
      padding: 0.55rem 0.95rem;
      border-radius: 999px;
      border: 1px solid transparent;
      background: transparent;
      color: inherit;
      font-size: 0.8rem;
      font-weight: 700;
      letter-spacing: 0.01em;
      cursor: pointer;
      opacity: 0.78;
      transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, opacity 0.15s ease;
    }
    .profile-activity-filter i {
      font-size: 0.78rem;
    }
    .profile-activity-filter.is-active {
      background: #ff6b35;
      border-color: #ff6b35;
      color: #ffffff;
      opacity: 1;
    }
    .profile-activity-filter.is-active i {
      opacity: 1;
    }
    .profile-activity-expand-bar {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.45rem;
      width: 100%;
      margin-top: 0.35rem;
      padding: 0.75rem 1rem;
      border: none;
      border-top: 1px solid rgba(255, 255, 255, 0.08);
      background: transparent;
      color: inherit;
      font-size: 0.88rem;
      font-weight: 650;
      cursor: pointer;
      opacity: 0.85;
    }
    body:not(.darkmode) .profile-activity-expand-bar {
      border-top-color: rgba(28, 28, 30, 0.08);
    }
    .profile-activity-expand-bar i {
      transition: transform 0.2s ease;
    }
    .profile-activity-expand-bar[aria-expanded="true"] i {
      transform: rotate(180deg);
    }
    .profile-activity-item.is-overflow-hidden,
    .profile-activity-day.is-overflow-hidden {
      display: none !important;
    }
    .profile-activity-day {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin: 0.85rem 0 0.55rem;
    }
    .profile-activity-day-label {
      font-size: 0.72rem;
      font-weight: 750;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      opacity: 0.55;
      white-space: nowrap;
    }
    .profile-activity-day-line {
      flex: 1;
      height: 1px;
      background: rgba(255, 255, 255, 0.1);
    }
    body:not(.darkmode) .profile-activity-day-line { background: rgba(28, 28, 30, 0.1); }
    .profile-activity-item {
      display: flex;
      align-items: flex-start;
      gap: 0.85rem;
      padding: 0.85rem 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.07);
    }
    body:not(.darkmode) .profile-activity-item { border-bottom-color: rgba(28, 28, 30, 0.07); }
    .profile-activity-item:last-child { border-bottom: none; }
    .profile-activity-icon {
      width: 42px;
      height: 42px;
      min-width: 42px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.95rem;
    }
    .profile-activity-icon.tone-orange {
      background: rgba(255, 107, 53, 0.18);
      color: #ff6b35;
    }
    .profile-activity-icon.tone-green {
      background: rgba(16, 185, 129, 0.18);
      color: #34d399;
    }
    .profile-activity-icon.tone-slate {
      background: rgba(148, 163, 184, 0.12);
      border: 1px solid rgba(148, 163, 184, 0.35);
      color: #cbd5e1;
    }
    .profile-activity-body {
      flex: 1;
      min-width: 0;
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 0.85rem;
    }
    .profile-activity-title {
      margin: 0 0 0.2rem;
      font-size: 0.98rem;
      font-weight: 700;
    }
    .profile-activity-details {
      margin: 0 0 0.45rem;
      font-size: 0.86rem;
      opacity: 0.68;
      line-height: 1.45;
    }
    .profile-activity-ref {
      display: inline-flex;
      padding: 0.22rem 0.55rem;
      border-radius: 8px;
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.08);
      font-size: 0.72rem;
      font-weight: 650;
      letter-spacing: 0.02em;
    }
    body:not(.darkmode) .profile-activity-ref {
      background: rgba(28, 28, 30, 0.05);
      border-color: rgba(28, 28, 30, 0.08);
    }
    .profile-activity-time {
      flex-shrink: 0;
      font-size: 0.82rem;
      opacity: 0.6;
      white-space: nowrap;
      padding-top: 0.15rem;
    }
    .profile-activity-empty-filter {
      margin: 0.75rem 0 0.25rem;
      opacity: 0.7;
      font-size: 0.92rem;
    }
    .profile-activity-item.is-hidden,
    .profile-activity-day.is-hidden { display: none !important; }
    @media (max-width: 720px) {
      .profile-apps-panel .profile-app-card-top,
      .profile-apps-panel .profile-app-card-foot {
        flex-wrap: wrap;
      }
      .profile-apps-panel .profile-status-pill,
      .profile-apps-panel .profile-app-cta {
        margin-left: auto !important;
      }
      .profile-activity-body { flex-direction: column; gap: 0.35rem; }
      .profile-activity-filters { gap: 0.4rem; }
    }
    @media (max-width: 900px) {
      .profile-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .profile-stage-mascot {
        width: 120px;
        height: 120px;
      }
      .profile-stage-mascot img {
        width: 120px;
        height: 120px;
      }
    }
    @media (max-width: 640px) {
      .profile-stage-body { flex-direction: column; align-items: flex-start; }
      .profile-stage-mascot {
        margin-left: 0;
        align-self: center;
      }
      .profile-block-head { flex-direction: column; }
      .profile-app-row { align-items: flex-start; }
      .profile-stats { grid-template-columns: 1fr 1fr; }
    }

  </style>
  <link rel="stylesheet" href="assets/css/vendor-light-mode-text.css?v=profile-elevate-2">
  <link rel="stylesheet" href="assets/css/vendor-mobile-sidebar-logout.css">
  <link rel="stylesheet" href="assets/css/vendor-navbar-glass.css?v=nav-icon-phone-1">
  <link rel="stylesheet" href="assets/css/vendor-nav-theme-lock.css?v=1">
  <link rel="stylesheet" href="assets/css/vendor-portal-shell.css?v=1">
  <link rel="stylesheet" href="assets/css/menu-landing-nav.css?v=menu-nav-lock-4">
  <link rel="stylesheet" href="assets/css/vendor-portal-dark-nav-pills.css?v=phone-glass-4">
  <link rel="stylesheet" href="assets/css/vendor-page-glass.css?v=profile-manage-accordion-8">
</head>
<body class="page-profile">
  <script>try{if(localStorage.getItem('darkmode')==='active')document.body.classList.add('darkmode');}catch(e){}</script>
  <div class="main-container">
    <?php $vendor_nav_active = 'profile'; include __DIR__ . '/includes/vendor_portal_navbar.php'; ?>

  <main>
    <!-- Success/Error Messages -->
    <?php if (!empty($success_message)): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><span class="profile-server-msg" data-msg-en="<?php echo htmlspecialchars($success_message); ?>"><?php echo htmlspecialchars($success_message); ?></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" data-translate-aria="modal.close" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><span class="profile-server-msg" data-msg-en="<?php echo htmlspecialchars($error_message); ?>"><?php echo htmlspecialchars($error_message); ?></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" data-translate-aria="modal.close" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <div class="profile-elevate">
    <div class="profile-theme-row">
      <button type="button" class="profile-darkmode-btn" id="theme-switch" data-translate-title="profile.darkModeTitle" data-translate-aria="profile.darkModeAria" title="Dark mode" aria-label="Toggle dark mode">
        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" class="theme-icon-moon"><path d="M480-120q-150 0-255-105T120-480q0-150 105-255t255-105q14 0 27.5 1t26.5 3q-41 29-65.5 75.5T444-660q0 90 63 153t153 63q55 0 101-24.5t75-65.5q2 13 3 26.5t1 27.5q0 150-105 255T480-120Z"/></svg>
        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" class="theme-icon-sun"><path d="M480-280q-83 0-141.5-58.5T280-480q0-83 58.5-141.5T480-680q83 0 141.5 58.5T680-480q0 83-58.5 141.5T480-280ZM200-440H40v-80h160v80Zm720 0H760v-80h160v80ZM440-760v-160h80v160h-80Zm0 720v-160h80v160h-80ZM256-650l-101-97 57-59 96 100-52 56Zm492 496-97-101 53-55 101 97-57 59Zm-98-550 97-101 59 57-100 96-56-52ZM154-212l101-97 55 53-97 101-59-57Z"/></svg>
      </button>
    </div>

    <section class="profile-stage" aria-label="Trader profile">
      <div class="profile-stage-body">
        <label class="profile-pic-circle" for="userPhotoInput" id="profilePicLabel" data-translate-title="profile.uploadPhotoTitle" title="Click to upload profile photo">
          <div class="profile-pic-inner" id="userAvatar">
            <?php if (!empty($profile['profile_image']) && file_exists($profile['profile_image'])): ?>
              <img src="<?php echo htmlspecialchars($profile['profile_image']); ?>" alt="Profile" data-translate-alt="profile.avatarAlt">
            <?php else: ?>
              <i class="fas fa-user fa-2x"></i>
            <?php endif; ?>
          </div>
          <span class="profile-pic-overlay"><i class="fas fa-camera"></i></span>
        </label>
        <input type="file" id="userPhotoInput" accept="image/*" style="display:none;">
        <div class="profile-stage-copy">
          <h1 class="accounts-title">
            <span data-translate="profile.titleLead">My </span><span class="app-title-accent" data-translate="profile.titleAccent">Profile</span>
          </h1>
          <p class="profile-person-name"><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></p>
          <div class="profile-stage-actions">
            <a href="application.php" class="btn-add-account" id="applyNowBtn"><i class="fas fa-plus"></i> <span data-translate="profile.applyNow">Apply now</span></a>
            <button type="button" class="profile-ghost-btn" id="heroEditProfileBtn">
              <i class="fas fa-pen"></i> <span data-translate="profile.heroEdit">Edit profile</span>
            </button>
          </div>
        </div>
        <div class="profile-stage-mascot" aria-hidden="true">
          <img src="assets/imgs/profile.gif" alt="" data-translate-alt="profile.mascotAlt" width="150" height="150">
        </div>
      </div>
    </section>

    <div class="profile-stats">
      <article class="profile-stat-card">
        <div class="profile-stat-top">
          <span class="profile-stat-value"><?php echo number_format((int) $total_applications); ?></span>
          <span class="profile-stat-icon" aria-hidden="true"><i class="fas fa-file-lines"></i></span>
        </div>
        <span class="profile-stat-label" data-translate="profile.totalApplicationsLabel">Total applications</span>
      </article>
      <article class="profile-stat-card">
        <div class="profile-stat-top">
          <span class="profile-stat-value"><?php echo number_format((int) $active_stalls); ?></span>
          <span class="profile-stat-icon" aria-hidden="true"><i class="fas fa-store"></i></span>
        </div>
        <span class="profile-stat-label" data-translate="profile.stat.activeStalls">Active stalls</span>
      </article>
      <article class="profile-stat-card">
        <div class="profile-stat-top">
          <span class="profile-stat-value"><?php echo number_format((int) $pending_review); ?></span>
          <span class="profile-stat-icon" aria-hidden="true"><i class="fas fa-clock"></i></span>
        </div>
        <span class="profile-stat-label" data-translate="profile.stat.pendingReview">Pending review</span>
      </article>
      <article class="profile-stat-card">
        <div class="profile-stat-top">
          <span class="profile-stat-value"><?php echo number_format((int) $renewals_due); ?></span>
          <span class="profile-stat-icon" aria-hidden="true"><i class="fas fa-rotate"></i></span>
        </div>
        <span class="profile-stat-label" data-translate="profile.stat.renewalsDue">Renewals due</span>
      </article>
    </div>

    <section class="profile-today-card" aria-label="Today">
      <div class="chart-date-hero">
        <span class="chart-date-kicker"><span class="chart-date-live" aria-hidden="true"></span><span data-translate="profile.chart.today">Today</span></span>
        <time class="chart-date-range" id="chartDateRange" datetime="<?php echo htmlspecialchars(date('Y-m-d')); ?>" data-iso-date="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
          <span class="chart-date-weekday"><?php echo htmlspecialchars(date('l')); ?></span>
          <span class="chart-date-main">
            <span class="chart-date-month"><?php echo htmlspecialchars(date('F')); ?></span>
            <span class="chart-date-daynum"><?php echo htmlspecialchars(date('j')); ?></span>
          </span>
          <span class="chart-date-year"><?php echo htmlspecialchars(date('Y')); ?></span>
        </time>
      </div>
      <div class="chart-bars">
        <?php
        $max_chart = max(1, max($chart_data ?? [0, 0, 0, 0, 0, 0, 0]));
        $today_idx = $today_index ?? 0;
        foreach (($chart_labels ?? ['M', 'T', 'W', 'Th', 'F', 'Sa', 'Su']) as $i => $l):
          $val = $chart_data[$i] ?? 0;
          $pct = round(($val / $max_chart) * 100);
          $isToday = ($i === $today_idx);
        ?>
        <div class="chart-bar-wrap<?php echo $isToday ? ' is-today' : ''; ?>">
          <div class="chart-bar" style="height: <?php echo max(8, $pct); ?>%; background: <?php echo $isToday ? '#ff6b35' : 'rgba(255,255,255,0.35)'; ?>"></div>
          <span class="chart-day" data-translate="profile.chart.day<?php echo (int) $i; ?>"><?php echo htmlspecialchars($l); ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="profile-apps-panel">
      <div class="profile-block-head">
        <div>
          <h2 class="profile-block-title" data-translate="profile.yourApps">Your applications</h2>
          <p class="profile-block-sub" data-translate="profile.insights.track">Track your market clearance applications and renewal status here.</p>
        </div>
        <a href="notification.php" class="profile-ghost-btn">
          <i class="fas fa-bell"></i> <span data-translate="profile.viewNotices">View notices</span>
        </a>
      </div>
      <?php
      $first_app = !empty($user_applications) ? $user_applications[0] : null;
      $rest_apps = array_slice($user_applications, 1);
      $has_more = count($rest_apps) > 0;
      $profile_owner_name = trim((string)($user['full_name'] ?? ''));
      ?>
      <div id="applicationCardsList" class="profile-apps-list">
      <?php
      function render_app_card($app, $owner_name) {
        $status_raw = strtolower(trim((string)($app['status'] ?? 'incomplete')));
        if (!in_array($status_raw, ['approved', 'pending', 'incomplete'], true)) {
          $status_raw = 'incomplete';
        }
        $is_renewal_due = !empty($app['is_renewal_due']);
        $badge_key = $is_renewal_due ? 'renewal_due' : $status_raw;
        $status_label = $is_renewal_due
          ? 'Renewal Due'
          : ($status_raw === 'approved' ? 'Approved' : ($status_raw === 'pending' ? 'Pending' : 'Incomplete'));
        $badge_icon = $is_renewal_due
          ? 'fa-arrows-rotate'
          : ($status_raw === 'approved' ? 'fa-circle-check' : ($status_raw === 'pending' ? 'fa-clock' : 'fa-ban'));
        $section = trim((string)($app['section'] ?? ''));
        $block = trim((string)($app['block'] ?? ''));
        $vendor_name = trim((string)($app['vendor_name'] ?? ''));
        $vid = (int) ($app['vendor_id'] ?? 0);
        $title = $section !== '' ? $section : ($vendor_name !== '' ? $vendor_name : ('Application #' . $vid));
        $meta_bits = [];
        if ($owner_name !== '') {
          $meta_bits[] = $owner_name;
        }
        if ($block !== '') {
          $meta_bits[] = (stripos($block, 'block') === 0 ? $block : ('Block ' . $block));
        }
        $meta = implode(' · ', $meta_bits);
        $title_is_number = ($section === '' && $vendor_name === '');
        $submitted = trim((string)($app['submitted_date'] ?? ''));
        $submitted_disp = $submitted !== '' ? date('M d, Y', strtotime($submitted)) : '';
        // Progress: 0% until admin reviews; 100% once reviewed (approved, incomplete, or renewal due on approved stall).
        $admin_reviewed = ($status_raw === 'approved' || $status_raw === 'incomplete' || $is_renewal_due);
        $progress = $admin_reviewed ? 100 : 0;

        if ($is_renewal_due) {
          $progress_note = 'Renew before ' . ($app['due_date_label'] !== '' ? preg_replace('/^due date:\s*/i', '', (string)$app['due_date_label']) : 'due date');
          $cta_label = 'Renew';
          $cta_href = 'renewal.php';
          $cta_icon = 'fa-arrow-right';
        } elseif ($status_raw === 'approved') {
          $progress_note = 'Reviewed and approved';
          $cta_label = 'View stall';
          $cta_href = 'renewal.php';
          $cta_icon = 'fa-arrow-right';
        } elseif ($status_raw === 'pending') {
          $progress_note = 'Awaiting admin review';
          $cta_label = 'Continue';
          $cta_href = 'notification.php';
          $cta_icon = 'fa-arrow-right';
        } else {
          $progress_note = 'Reviewed — documents need attention';
          $cta_label = 'Fix documents';
          $cta_href = 'notification.php';
          $cta_icon = 'fa-arrow-right';
        }
        $foot = $progress . '% · ' . $progress_note;
        if ($submitted_disp !== '') {
          $foot .= ' · submitted ' . $submitted_disp;
        }
        $due_line = $is_renewal_due ? trim((string)($app['due_date_label'] ?? '')) : '';
        ?>
        <article class="profile-app-card account-card" data-vendor-id="<?php echo $vid; ?>">
          <div class="profile-app-card-top">
            <div class="profile-app-copy">
              <?php if ($title_is_number): ?>
              <h3 class="profile-app-title" data-i18n-app-number="<?php echo $vid; ?>"><?php echo htmlspecialchars($title); ?></h3>
              <?php else: ?>
              <h3 class="profile-app-title"><?php echo htmlspecialchars($title); ?></h3>
              <?php endif; ?>
              <?php if ($meta !== ''): ?>
              <p class="profile-app-meta"><?php echo htmlspecialchars($meta); ?></p>
              <?php endif; ?>
            </div>
            <span class="profile-status-pill status-<?php echo htmlspecialchars($badge_key); ?>" data-i18n-status="<?php echo htmlspecialchars($badge_key); ?>">
              <i class="fas <?php echo htmlspecialchars($badge_icon); ?>" aria-hidden="true"></i>
              <?php echo htmlspecialchars($status_label); ?>
            </span>
          </div>
          <div class="profile-app-progress" aria-hidden="true">
            <span class="profile-app-progress-fill" style="width: <?php echo (int)$progress; ?>%;"></span>
          </div>
          <div class="profile-app-card-foot">
            <div class="profile-app-foot-copy">
              <p class="profile-app-foot-meta"><?php echo htmlspecialchars($foot); ?></p>
              <?php if ($due_line !== ''): ?>
              <p class="profile-app-due-line"><?php echo htmlspecialchars($due_line); ?></p>
              <?php endif; ?>
            </div>
            <a class="profile-app-cta" href="<?php echo htmlspecialchars($cta_href); ?>">
              <span data-translate="profile.app.cta.<?php echo htmlspecialchars($badge_key === 'renewal_due' ? 'renew' : ($status_raw === 'approved' ? 'viewStall' : ($status_raw === 'pending' ? 'continue' : 'fixDocs'))); ?>"><?php echo htmlspecialchars($cta_label); ?></span>
              <i class="fas <?php echo htmlspecialchars($cta_icon); ?>" aria-hidden="true"></i>
            </a>
          </div>
        </article>
        <?php
      }
      if ($first_app) {
        render_app_card($first_app, $profile_owner_name);
      } else { ?>
        <p class="profile-apps-empty" data-translate="profile.insights.start">Submit your first application to get started.</p>
      <?php }
      if ($has_more): ?>
      <div class="account-roll-wrapper" id="restAppsWrapper">
        <?php foreach ($rest_apps as $app) render_app_card($app, $profile_owner_name); ?>
      </div>
      <button type="button" class="account-roll-btn" id="rollUnrollBtn" data-roll-expanded="0"><i class="fas fa-chevron-down"></i> <span data-translate="profile.roll.viewAll">View all applications</span></button>
      <?php endif; ?>
      </div>
    </section>

    <section class="profile-manage-panel" id="manageAccount">
      <div class="profile-manage-head">
        <h2 class="profile-manage-title" data-translate="profile.manageTitle">Manage account</h2>
        <p class="profile-manage-sub" data-translate="profile.manageSub">Send payment receipts and keep your trader login secure.</p>
      </div>

      <!-- Send receipt (expandable) -->
      <div class="account-card account-card-expandable">
        <div class="account-card-header-row" data-toggle="profileReceiptPanel">
          <div class="account-card-icon" aria-hidden="true"><i class="fas fa-receipt"></i></div>
          <div class="account-card-body">
            <div class="account-card-header">
              <div>
                <h3 class="account-name" data-translate="profile.receipt.title">Send receipt</h3>
                <span class="account-type" data-translate="profile.receipt.subtitle">Upload a payment receipt for the market office to review</span>
              </div>
              <span class="account-expand-icon"><i class="fas fa-chevron-down"></i></span>
            </div>
          </div>
        </div>
        <div class="account-card-expand-content" id="profileReceiptPanel" hidden>
          <div class="account-content-card profile-manage-form-panel">
            <?php if ($receipt_submit_blocked): ?>
            <div class="profile-receipt-block-notice" style="margin-bottom:1rem;padding:0.85rem 1rem;border-radius:10px;background:#fef3c7;border:1px solid #fcd34d;color:#92400e;line-height:1.55;">
              <strong><i class="fas fa-circle-info"></i> Receipt already on file</strong>
              <p style="margin:0.45rem 0 0;"><?php echo htmlspecialchars($receipt_block_message, ENT_QUOTES, 'UTF-8'); ?></p>
              <?php if ($receipt_block_status === 'rejected'): ?>
              <p style="margin:0.65rem 0 0;"><a href="notification.php" style="color:#b45309;font-weight:600;text-decoration:underline;">Go to Notifications to edit and resubmit</a></p>
              <?php endif; ?>
            </div>
            <?php endif; ?>
            <form id="profileReceiptForm" class="profile-receipt-form profile-manage-form" enctype="multipart/form-data" novalidate<?php echo $receipt_submit_blocked ? ' data-receipt-blocked="1"' : ''; ?>>
              <div class="info-item">
                <label id="profileReceiptFileLabel" for="profileReceiptFile" data-translate="profile.receipt.fileLabel">Receipt file</label>
                <div class="profile-receipt-file-shell">
                  <input type="file" id="profileReceiptFile" name="receipt_file" class="profile-receipt-file-input" accept="image/jpeg,image/png,image/gif,.jpg,.jpeg,.png,.gif" <?php echo $receipt_submit_blocked ? '' : 'required'; ?> aria-labelledby="profileReceiptFileLabel"<?php echo $receipt_submit_blocked ? ' disabled' : ''; ?>>
                  <div class="profile-receipt-file-inner">
                    <span class="profile-receipt-file-btn"><i class="fas fa-arrow-up-from-bracket" aria-hidden="true"></i> <span data-translate="profile.receipt.chooseFile">Choose file</span></span>
                    <span class="profile-receipt-hint" data-translate="profile.receipt.fileHint">JPG, PNG or GIF · up to 5 MB</span>
                    <span class="profile-receipt-file-name" id="profileReceiptFileName">No file chosen</span>
                  </div>
                </div>
              </div>
              <div class="info-item profile-receipt-notes-wrap">
                <label for="profileReceiptNotes" data-translate="profile.receipt.notesLabel">Note to the office</label>
                <textarea id="profileReceiptNotes" name="notes" rows="4" maxlength="500" data-translate-placeholder="profile.receipt.notesPlaceholder" placeholder="Anything the allotment office should know about this payment"<?php echo $receipt_submit_blocked ? ' disabled' : ''; ?>></textarea>
                <div class="profile-receipt-char-count" id="profileReceiptCharCount" aria-live="polite">0/500</div>
              </div>
              <button type="submit" class="profile-manage-submit" id="profileReceiptSubmit"<?php echo $receipt_submit_blocked ? ' disabled' : ''; ?>>
                <i class="fas fa-paper-plane" aria-hidden="true"></i> <span data-translate="profile.receipt.submit">Send to market office</span>
              </button>
              <p id="profileReceiptFeedback" class="mt-2 mb-0" style="font-size: 0.9rem;" role="status" aria-live="polite"></p>
            </form>
          </div>
        </div>
      </div>

      <!-- Change Password card -->
      <div class="account-card account-card-expandable">
        <div class="account-card-header-row" data-toggle="passwordFormPanel">
          <div class="account-card-icon" aria-hidden="true"><i class="fas fa-lock"></i></div>
          <div class="account-card-body">
            <div class="account-card-header">
              <div>
                <h3 class="account-name" data-translate="profile.changePasswordTitle">Change password</h3>
                <span class="account-type" data-translate="profile.changePasswordSubtitle">Update your password</span>
              </div>
              <span class="account-expand-icon"><i class="fas fa-chevron-down"></i></span>
            </div>
          </div>
        </div>
        <div class="account-card-expand-content" id="passwordFormPanel" hidden>
          <div class="account-content-card profile-manage-form-panel profile-manage-form-panel--password">
            <form id="passwordForm" class="profile-manage-form profile-password-form" method="POST" action="profile.php">
              <div class="profile-pw-stack">
                <div class="profile-pw-block">
                  <div class="info-item">
                    <label for="current_password" data-translate="profile.currentPassword">Current password</label>
                    <div class="profile-password-field">
                      <input type="password" id="current_password" name="current_password"
                             required autocomplete="current-password" data-translate-placeholder="profile.placeholder.currentPassword" placeholder="Enter your current password">
                      <button type="button" class="profile-password-toggle" data-toggle-password="current_password" aria-label="Show password"><i class="far fa-eye" aria-hidden="true"></i></button>
                    </div>
                    <div class="profile-forgot-wrap">
                      <a href="forgot_password.php" class="form-link" data-translate="profile.forgotPassword">Forgot password?</a>
                    </div>
                  </div>
                </div>
                <div class="profile-pw-block profile-pw-block--split">
                  <div class="info-item">
                    <label for="new_password" data-translate="profile.newPassword">New password</label>
                    <div class="profile-password-field">
                      <input type="password" id="new_password" name="new_password"
                             required autocomplete="new-password" data-translate-placeholder="profile.placeholder.newPassword" placeholder="Enter new password">
                      <button type="button" class="profile-password-toggle" data-toggle-password="new_password" aria-label="Show password"><i class="far fa-eye" aria-hidden="true"></i></button>
                    </div>
                    <div class="profile-pw-strength" id="profilePwStrength" aria-live="polite">
                      <div class="profile-pw-strength-bars" aria-hidden="true">
                        <span></span><span></span><span></span><span></span>
                      </div>
                      <p class="profile-pw-strength-label"><span data-translate="profile.passwordStrengthLabel">Strength:</span> <strong id="profilePwStrengthText">Too weak</strong></p>
                      <ul class="profile-pw-rules" id="profilePwRules">
                        <li data-rule="len" data-translate="profile.passwordRule.len">8+ characters</li>
                        <li data-rule="mixed" data-translate="profile.passwordRule.mixed">Letters and numbers</li>
                        <li data-rule="case" data-translate="profile.passwordRule.case">Upper and lower case</li>
                        <li data-rule="digit" data-translate="profile.passwordRule.digit">A number</li>
                      </ul>
                    </div>
                  </div>
                  <div class="info-item">
                    <label for="confirm_password" data-translate="profile.confirmPassword">Confirm new password</label>
                    <div class="profile-password-field">
                      <input type="password" id="confirm_password" name="confirm_password"
                             required autocomplete="new-password" data-translate-placeholder="profile.placeholder.confirmPassword" placeholder="Confirm new password">
                      <button type="button" class="profile-password-toggle" data-toggle-password="confirm_password" aria-label="Show password"><i class="far fa-eye" aria-hidden="true"></i></button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="profile-pw-footer">
                <button type="submit" name="change_password" class="profile-manage-submit" id="changePasswordSubmitBtn">
                  <i class="fas fa-shield-halved" aria-hidden="true"></i> <span data-translate="profile.btn.changePasswordSubmit">Update password</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </section>

    <section class="profile-activity-panel activity-log-card" id="profileActivityPanel">
      <div class="profile-activity-head">
        <div>
          <h2 class="profile-block-title" data-translate="profile.activityLogTitle">Activity log</h2>
          <p class="profile-block-sub" data-translate="profile.activityLogSubtitleElevated">A dated record of every clearance, payment and account change on your trader profile.</p>
        </div>
        <span class="profile-activity-count">
          <i class="fas fa-clock" aria-hidden="true"></i>
          <span><?php echo (int)$profile_activity_count; ?> <span data-translate="profile.activity.events">events</span></span>
        </span>
      </div>

      <div class="profile-activity-filters" role="tablist" aria-label="Filter activity by category">
        <button type="button" class="profile-activity-filter is-active" role="tab" data-activity-filter="all" aria-selected="true">
          <i class="fas fa-bars" aria-hidden="true"></i>
          <span data-translate="profile.activity.filter.all">All</span>
        </button>
        <button type="button" class="profile-activity-filter" role="tab" data-activity-filter="applications" aria-selected="false">
          <i class="fas fa-file-lines" aria-hidden="true"></i>
          <span data-translate="profile.activity.filter.applications">Applications</span>
        </button>
        <button type="button" class="profile-activity-filter" role="tab" data-activity-filter="renewals" aria-selected="false">
          <i class="fas fa-arrows-rotate" aria-hidden="true"></i>
          <span data-translate="profile.activity.filter.renewals">Renewals</span>
        </button>
        <button type="button" class="profile-activity-filter" role="tab" data-activity-filter="payments" aria-selected="false">
          <i class="fas fa-receipt" aria-hidden="true"></i>
          <span data-translate="profile.activity.filter.payments">Payments</span>
        </button>
        <button type="button" class="profile-activity-filter" role="tab" data-activity-filter="account" aria-selected="false">
          <i class="fas fa-shield-halved" aria-hidden="true"></i>
          <span data-translate="profile.activity.filter.account">Account</span>
        </button>
      </div>

      <?php if (empty($profile_activity_entries)): ?>
        <div class="activity-log-empty" data-translate="profile.activity.empty">No activity yet. Submit an application, renewal, or receipt to see it here.</div>
      <?php else:
        $last_day_key = null;
        $today_ymd = date('Y-m-d');
        $yesterday_ymd = date('Y-m-d', strtotime('-1 day'));
      ?>
      <div class="profile-activity-feed is-limited" id="profileActivityFeed" role="list" data-activity-limit="3">
        <?php
        $activity_ordinal = 0;
        foreach ($profile_activity_entries as $entry):
          $atype = $entry['type'] ?? '';
          $cat = $entry['category'] ?? 'account';
          $icon = $profile_activity_icons[$atype] ?? 'fa-circle-dot';
          $tone = $profile_activity_tone[$atype] ?? 'slate';
          $title = trim((string)($entry['title'] ?? ''));
          if ($title === '') {
            $title = $profile_activity_tdefault[$atype] ?? 'Activity';
          }
          $details = trim((string)($entry['details'] ?? ''));
          $ref = trim((string)($entry['ref'] ?? ''));
          if ($atype === 'password_change' || strcasecmp($ref, 'ACC-PWD') === 0) {
            $ref = '';
          }
          if ($atype === 'password_change' && ($details === '' || strcasecmp($details, 'Password changed') === 0)) {
            $details = 'Your trader login password was updated from this device.';
          }
          $when = $entry['created_at'] ?? '';
          $ts = (int)($entry['ts'] ?? ($when !== '' ? strtotime($when) : time()));
          $day_key = date('Y-m-d', $ts);
          if ($day_key === $today_ymd) {
            $day_label = 'Today';
            $day_tkey = 'profile.activity.group.today';
          } elseif ($day_key === $yesterday_ymd) {
            $day_label = 'Yesterday';
            $day_tkey = 'profile.activity.group.yesterday';
          } else {
            $day_label = strtoupper(date('M d, Y', $ts));
            $day_tkey = '';
          }
          $time_disp = date('g:i A', $ts);
          if ($last_day_key !== $day_key):
            $last_day_key = $day_key;
        ?>
        <div class="profile-activity-day" data-activity-day="<?php echo htmlspecialchars($day_key); ?>">
          <?php if ($day_tkey !== ''): ?>
          <span class="profile-activity-day-label" data-translate="<?php echo htmlspecialchars($day_tkey); ?>"><?php echo htmlspecialchars($day_label); ?></span>
          <?php else: ?>
          <span class="profile-activity-day-label"><?php echo htmlspecialchars($day_label); ?></span>
          <?php endif; ?>
          <span class="profile-activity-day-line" aria-hidden="true"></span>
        </div>
        <?php endif; ?>
        <article class="profile-activity-item" role="listitem" data-activity-cat="<?php echo htmlspecialchars($cat); ?>" data-activity-ordinal="<?php echo (int)$activity_ordinal; ?>">
          <?php $activity_ordinal++; ?>
          <div class="profile-activity-icon tone-<?php echo htmlspecialchars($tone); ?>" aria-hidden="true">
            <i class="fas <?php echo htmlspecialchars($icon); ?>"></i>
          </div>
          <div class="profile-activity-body">
            <div class="profile-activity-main">
              <h3 class="profile-activity-title"><?php echo htmlspecialchars($title); ?></h3>
              <?php if ($details !== ''): ?>
              <p class="profile-activity-details"><?php echo htmlspecialchars($details); ?></p>
              <?php endif; ?>
              <?php if ($ref !== ''): ?>
              <span class="profile-activity-ref"><?php echo htmlspecialchars($ref); ?></span>
              <?php endif; ?>
            </div>
            <time class="profile-activity-time" datetime="<?php echo htmlspecialchars($when !== '' ? $when : date('c', $ts)); ?>"><?php echo htmlspecialchars($time_disp); ?></time>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <?php if ($profile_activity_count > 3): ?>
      <button type="button" class="profile-activity-expand-bar" id="profileActivityExpandBtn" aria-expanded="false">
        <span data-translate="profile.activity.showAll">Show all activity</span>
        <i class="fas fa-chevron-down" aria-hidden="true"></i>
      </button>
      <?php endif; ?>
      <p class="profile-activity-empty-filter" id="profileActivityEmptyFilter" hidden data-translate="profile.activity.emptyFilter">No events in this category yet.</p>
      <?php endif; ?>
    </section>
    </div>
  </main>

  <!-- Edit Profile Modal (opened from hero "Edit profile") -->
  <div class="modal fade profile-edit-modal" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content profile-edit-modal-content">
        <div class="modal-header profile-edit-modal-header">
          <div>
            <h5 class="modal-title" id="editProfileModalTitle" data-translate="profile.editProfileTitle">Edit Profile</h5>
            <p class="profile-edit-modal-sub" data-translate="profile.editProfileSubtitle">Update your name and details</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" data-translate-aria="modal.close" aria-label="Close"></button>
        </div>
        <div class="modal-body profile-edit-modal-body">
          <div class="profile-manage-form-panel">
          <form id="profileForm" class="profile-manage-form" method="POST" action="profile.php">
            <div class="profile-form-row">
              <div class="profile-form-col">
                <div class="info-item">
                  <label for="full_name" data-translate="profile.fullName">Full Name *</label>
                  <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required disabled>
                </div>
                <div class="info-item">
                  <label for="email" data-translate="profile.email">Email Address</label>
                  <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                </div>
              </div>
              <div class="profile-form-col">
                <div class="info-item">
                  <label for="phone_number" data-translate="profile.phone">Phone Number</label>
                  <input type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" data-translate-placeholder="profile.placeholder.phone" placeholder="+63 912 345 6789" disabled>
                </div>
                <div class="info-item">
                  <label for="created_at" data-translate="profile.memberSince">Member Since</label>
                  <input type="text" id="created_at" data-member-since="<?php echo htmlspecialchars(date('Y-m-d', strtotime($user['created_at']))); ?>" value="<?php echo date('F d, Y', strtotime($user['created_at'])); ?>" disabled>
                </div>
              </div>
            </div>

            <div class="btn-group-profile">
              <button type="button" class="btn-profile btn-primary-profile" id="editBtn">
                <i class="fas fa-pen"></i> <span data-translate="profile.btn.edit">Edit profile</span>
              </button>
              <button type="submit" name="update_profile" class="btn-profile btn-primary-profile profile-manage-submit" id="saveBtn" style="display: none;">
                <i class="fas fa-check"></i> <span data-translate="profile.btn.save">Save changes</span>
              </button>
              <button type="button" class="btn-profile btn-secondary-profile" id="cancelBtn" style="display: none;">
                <i class="fas fa-times"></i> <span data-translate="profile.btn.cancel">Cancel</span>
              </button>
            </div>
          </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Application Details Modal (eye icon) -->
  <div class="modal fade" id="applicationDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content" style="border-radius: 20px; border: 1px solid rgba(102, 126, 234, 0.3);">
        <div class="modal-header" style="background: rgba(255,255,255,0.35); border-radius: 20px 20px 0 0;">
          <h5 class="modal-title fw-bold" id="applicationDetailsModalTitle"><i class="fas fa-file-alt me-2"></i><span data-translate="modal.appDetails.title">Application Details</span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" data-translate-aria="modal.close" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="text-center py-4" id="applicationDetailsLoading">
            <div class="spinner-border text-success" role="status"><span class="visually-hidden" data-translate="modal.appDetails.loading">Loading details...</span></div>
            <p class="mt-2 mb-0" data-translate="modal.appDetails.loading">Loading details...</p>
          </div>
          <div id="applicationDetailsContent" style="display: none;">
            <div id="appDetailsInfo"></div>
            <h6 class="mt-4 mb-2" data-translate="modal.appDetails.uploadedDocs">Uploaded documents / images</h6>
            <div class="row g-2" id="appDetailsDocList"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Calendar Modal (same markup + behavior as menu.php) -->
  <div class="modal fade" id="calendarModal" tabindex="-1" aria-labelledby="calendarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 15px;">
        <div class="modal-header" style="border-bottom: 1px solid rgba(99, 102, 241, 0.1);">
          <h5 class="modal-title" id="calendarModalLabel" style="color: #6366f1;" data-translate="modal.calendar">Calendar</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" data-translate-aria="modal.close" aria-label="Close"></button>
        </div>
        <div class="modal-body" style="padding: 1.5rem;">
          <div id="calendarContainer" style="color: #475569;">
            <!-- Calendar will be rendered here -->
            <p style="text-align: center; color: rgba(71, 85, 105, 0.7);" data-translate="modal.calendar.coming">Calendar functionality coming soon</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  </div><!-- /.main-container -->

  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  <script>
    // Language switching: navbar + full profile page (EN / PH Tagalog)
    const translations = {
      en: {
        'nav.home':'Home','nav.application':'Application','nav.renewal':'Renewal','nav.profile':'Profile','nav.notifications':'Notifications','nav.logout':'Logout',
        'confirm.logout':'Are you sure you want to logout?',
        'modal.calendar':'Calendar','modal.calendar.coming':'Calendar functionality coming soon',
        'modal.close':'Close',
        'modal.appDetails.title':'Application Details','modal.appDetails.loading':'Loading details...','modal.appDetails.uploadedDocs':'Uploaded documents / images',
        'modal.appDetails.name':'Name','modal.appDetails.type':'Type','modal.appDetails.status':'Status','modal.appDetails.submitted':'Submitted',
        'logo.alt':'Daet Public Market','logo.title':'Daet Public Market',
        'aria.toggleNav':'Toggle navigation',
        'profile.pageTitle':'My Profile','profile.titleLead':'My ','profile.titleAccent':'Profile','profile.heroEdit':'Edit profile','profile.applyNow':'Apply now','profile.darkModeTitle':'Dark mode','profile.darkModeAria':'Toggle dark mode',
        'profile.stat.activeStalls':'Active stalls','profile.stat.pendingReview':'Pending review','profile.stat.renewalsDue':'Renewals due',
        'profile.yourApps':'Your applications','profile.viewNotices':'View notices',
        'profile.manageTitle':'Manage account','profile.manageSub':'Send payment receipts and keep your trader login secure.',
        'profile.uploadPhotoTitle':'Click to upload profile photo','profile.avatarAlt':'Profile','profile.mascotAlt':'Profile mascot',
        'profile.totalApplicationsLabel':'TOTAL APPLICATIONS',
        'profile.insights.many':'Past 20 applications. One month+ for many vendors.',
        'profile.insights.track':'Track your market clearance applications and renewal status here.',
        'profile.insights.start':'Submit your first application to get started.',
        'profile.chart.today':'Today',
        'profile.chart.day0':'M','profile.chart.day1':'T','profile.chart.day2':'W','profile.chart.day3':'Th','profile.chart.day4':'F','profile.chart.day5':'Sa','profile.chart.day6':'Su',
        'profile.roll.viewAll':'View all applications','profile.roll.showLess':'Show less',
        'profile.receipt.title':'Send receipt','profile.receipt.subtitle':'Upload a payment receipt for the market office to review',
        'profile.receipt.fileLabel':'Receipt file','profile.receipt.fileHint':'JPG, PNG or GIF · up to 5 MB',
        'profile.receipt.chooseFile':'Choose file','profile.receipt.noFileChosen':'No file chosen',
        'profile.receipt.notesLabel':'Note to the office','profile.receipt.notesPlaceholder':'Anything the allotment office should know about this payment',
        'profile.receipt.submit':'Send to market office','profile.receipt.errNoFile':'Please choose a receipt image.','profile.receipt.emailNotSent':'Receipt saved, but the confirmation email could not be sent. Check spam, or ask the office if SMTP is configured.',
        'profile.receipt.activityLine':'Receipt #%s submitted',
        'profile.status.approved':'Approved','profile.status.pending':'Pending','profile.status.incomplete':'Incomplete','profile.status.renewal_due':'Renewal Due',
        'profile.app.cta.continue':'Continue','profile.app.cta.fixDocs':'Fix documents','profile.app.cta.viewStall':'View stall','profile.app.cta.renew':'Renew',
        'profile.apptype.renewal':'Renewal','profile.apptype.new':'New application',
        'profile.marketIconAlt':'Market','profile.label.status':'Status','profile.viewDetails':'View details',
        'profile.editProfileTitle':'Edit Profile','profile.editProfileSubtitle':'Update your name and details',
        'profile.fullName':'Full Name *','profile.email':'Email Address','profile.phone':'Phone Number','profile.memberSince':'Member Since',
        'profile.placeholder.phone':'+63 912 345 6789',
        'profile.btn.edit':'Edit profile','profile.btn.save':'Save changes','profile.btn.cancel':'Cancel',
        'profile.changePasswordTitle':'Change password','profile.changePasswordSubtitle':'Update your password',
        'profile.currentPassword':'Current password','profile.forgotPassword':'Forgot password?','profile.newPassword':'New password',
        'profile.passwordHint':'Must be at least 8 characters with uppercase, lowercase, and number','profile.confirmPassword':'Confirm new password',
        'profile.passwordStrengthLabel':'Strength:','profile.passwordStrength.tooWeak':'Too weak','profile.passwordStrength.weak':'Weak','profile.passwordStrength.fair':'Fair','profile.passwordStrength.strong':'Strong',
        'profile.passwordRule.len':'8+ characters','profile.passwordRule.mixed':'Letters and numbers','profile.passwordRule.case':'Upper and lower case','profile.passwordRule.digit':'A number',
        'profile.placeholder.currentPassword':'Enter your current password','profile.placeholder.newPassword':'Enter new password',
        'profile.placeholder.confirmPassword':'Confirm new password','profile.btn.changePasswordSubmit':'Update password',
        'profile.activityLogTitle':'Activity log','profile.activityLogSubtitle':'Applications, renewals, and receipts you submitted',
        'profile.activityLogSubtitleElevated':'A dated record of every clearance, payment and account change on your trader profile.',
        'profile.activity.empty':'No activity yet. Submit an application, renewal, or receipt to see it here.',
        'profile.activity.emptyFilter':'No events in this category yet.',
        'profile.activity.events':'events',
        'profile.activity.filterLabel':'Filter activity',
        'profile.activity.filter.all':'All','profile.activity.filter.applications':'Applications','profile.activity.filter.renewals':'Renewals','profile.activity.filter.payments':'Payments','profile.activity.filter.account':'Account',
        'profile.activity.showAll':'Show all activity','profile.activity.showLess':'Show less','profile.activity.showMore':'Show {{count}} more',
        'profile.activity.group.today':'Today','profile.activity.group.yesterday':'Yesterday',
        'profile.activity.appSubmitted':'Application submitted','profile.activity.appUpdated':'Application updated',
        'profile.activity.renewalSubmitted':'Renewal submitted','profile.activity.renewalUpdated':'Renewal updated',
        'profile.activity.receiptSent':'Receipt sent','profile.activity.passwordChanged':'Password changed','profile.activity.unknown':'Activity',
        'app.err.failedLoad':'Failed to load details.','app.err.noDocs':'No documents uploaded.','app.err.failedLoadDetails':'Failed to load application details.',
        'app.documentFallback':'Document',
        'alert.imageInvalid':'Please select a valid image.','alert.imageSize':'Image must be less than 2MB','alert.photoUpdated':'Profile photo updated!',
        'alert.photoFailed':'Failed to update photo','alert.uploadFailed':'Upload failed',
        'lang.alt.uk':'UK flag','lang.alt.ph':'Philippines flag','lang.option.en':'English','lang.option.ph':'Tagalog',
        'profile.applicationFallback':'Application #%s',
        'profile.msg.fullNameRequired':'Full name is required.',
        'profile.msg.profileUpdated':'Profile updated successfully!',
        'profile.msg.profileUpdateFailed':'Failed to update profile. Please try again.',
        'profile.msg.currentPasswordRequired':'Current password is required.',
        'profile.msg.newPasswordRequired':'New password is required.',
        'profile.msg.passwordsMismatch':'New passwords do not match.',
        'profile.msg.passwordMinLength':'New password must be at least 8 characters long.',
        'profile.msg.passwordComplexity':'New password must contain at least one lowercase letter, one uppercase letter, and one number.',
        'profile.msg.currentPasswordWrong':'Current password is incorrect.',
        'profile.msg.passwordChanged':'Password changed successfully!',
        'profile.msg.passwordChangeFailed':'Failed to change password. Please try again.'
      },
      ph: {
        'nav.home':'Tahanan','nav.application':'Aplikasyon','nav.renewal':'Pag-renew','nav.profile':'Profile','nav.notifications':'Mga Abiso','nav.logout':'Mag-logout',
        'confirm.logout':'Sigurado ka bang gusto mong mag-logout?',
        'modal.calendar':'Kalendaryo','modal.calendar.coming':'Ang functionality ng kalendaryo ay darating sa lalong madaling panahon',
        'modal.close':'Isara',
        'modal.appDetails.title':'Detalye ng Aplikasyon','modal.appDetails.loading':'Naglo-load ng detalye...','modal.appDetails.uploadedDocs':'Mga na-upload na dokumento / larawan',
        'modal.appDetails.name':'Pangalan','modal.appDetails.type':'Uri','modal.appDetails.status':'Katayuan','modal.appDetails.submitted':'Isinumite',
        'logo.alt':'Daet Public Market','logo.title':'Daet Public Market',
        'aria.toggleNav':'Buksara / isara ang nabigasyon',
        'profile.pageTitle':'Aking Profile','profile.titleLead':'Aking ','profile.titleAccent':'Profile','profile.heroEdit':'I-edit ang profile','profile.applyNow':'Mag-apply ngayon','profile.darkModeTitle':'Madilim na mode','profile.darkModeAria':'Lumipat sa madilim / maliwanag na mode',
        'profile.stat.activeStalls':'Aktibong puwesto','profile.stat.pendingReview':'Nakabinbing pagsusuri','profile.stat.renewalsDue':'Renewal na dapat',
        'profile.yourApps':'Inyong mga aplikasyon','profile.viewNotices':'Tingnan ang mga abiso',
        'profile.manageTitle':'Pamahalaan ang account','profile.manageSub':'Magpadala ng resibo ng bayad at panatilihing ligtas ang login.',
        'profile.uploadPhotoTitle':'I-click para mag-upload ng larawan sa profile','profile.avatarAlt':'Profile','profile.mascotAlt':'Mascot ng profile',
        'profile.totalApplicationsLabel':'KABUUANG MGA APLIKASYON',
        'profile.insights.many':'Higit sa 20 aplikasyon. Isang buwan pataas para sa maraming vendor.',
        'profile.insights.track':'Subaybayan ang inyong market clearance at renewal status dito.',
        'profile.insights.start':'Magsumite ng inyong unang aplikasyon upang magsimula.',
        'profile.chart.today':'Ngayon',
        'profile.chart.day0':'Lu','profile.chart.day1':'Ma','profile.chart.day2':'Mi','profile.chart.day3':'Hu','profile.chart.day4':'Bi','profile.chart.day5':'Sa','profile.chart.day6':'Li',
        'profile.roll.viewAll':'Tingnan ang lahat ng aplikasyon','profile.roll.showLess':'Ipakita ang mas kaunti',
        'profile.receipt.title':'Magpadala ng resibo','profile.receipt.subtitle':'Mag-upload ng resibo ng bayad upang suriin ng tanggapan ng pamilihan',
        'profile.receipt.fileLabel':'File ng resibo','profile.receipt.fileHint':'JPG, PNG o GIF · hanggang 5 MB',
        'profile.receipt.chooseFile':'Pumili ng file','profile.receipt.noFileChosen':'Walang napiling file',
        'profile.receipt.notesLabel':'Tala para sa tanggapan','profile.receipt.notesPlaceholder':'Anumang dapat malaman ng allotment office tungkol sa bayad na ito',
        'profile.receipt.submit':'Ipadala sa tanggapan ng pamilihan','profile.receipt.errNoFile':'Mangyaring pumili ng larawan ng resibo.','profile.receipt.emailNotSent':'Nai-save ang resibo, ngunit hindi naipadala ang email na kumpirmasyon. Tingnan ang spam, o tanungin ang tanggapan kung naka-set up ang SMTP.',
        'profile.receipt.activityLine':'Naipadala ang resibo #%s',
        'profile.status.approved':'Aprubado','profile.status.pending':'Nakabinbin','profile.status.incomplete':'Hindi Kumpleto','profile.status.renewal_due':'Dapat Mag-renew',
        'profile.app.cta.continue':'Magpatuloy','profile.app.cta.fixDocs':'Ayusin ang dokumento','profile.app.cta.viewStall':'Tingnan ang puwesto','profile.app.cta.renew':'Mag-renew',
        'profile.apptype.renewal':'Pag-renew','profile.apptype.new':'Bagong aplikasyon',
        'profile.marketIconAlt':'Pamilihan','profile.label.status':'Katayuan','profile.viewDetails':'Tingnan ang detalye',
        'profile.editProfileTitle':'I-edit ang Profile','profile.editProfileSubtitle':'I-update ang inyong pangalan at detalye',
        'profile.fullName':'Buong Pangalan *','profile.email':'Email na Address','profile.phone':'Numero ng Telepono','profile.memberSince':'Miyembro Mula',
        'profile.placeholder.phone':'+63 912 345 6789',
        'profile.btn.edit':'I-edit ang Profile','profile.btn.save':'I-save ang mga Pagbabago','profile.btn.cancel':'Kanselahin',
        'profile.changePasswordTitle':'Palitan ang password','profile.changePasswordSubtitle':'I-update ang inyong password',
        'profile.currentPassword':'Kasalukuyang password','profile.forgotPassword':'Nakalimutan ang password?','profile.newPassword':'Bagong password',
        'profile.passwordHint':'Dapat hindi bababa sa 8 character na may malaking titik, maliit na titik, at numero','profile.confirmPassword':'Kumpirmahin ang bagong password',
        'profile.passwordStrengthLabel':'Lakas:','profile.passwordStrength.tooWeak':'Napakahina','profile.passwordStrength.weak':'Mahina','profile.passwordStrength.fair':'Katamtaman','profile.passwordStrength.strong':'Malakas',
        'profile.passwordRule.len':'8+ character','profile.passwordRule.mixed':'Mga titik at numero','profile.passwordRule.case':'Malaki at maliit na titik','profile.passwordRule.digit':'Isang numero',
        'profile.placeholder.currentPassword':'Ilagay ang kasalukuyang password','profile.placeholder.newPassword':'Ilagay ang bagong password',
        'profile.placeholder.confirmPassword':'Kumpirmahin ang bagong password','profile.btn.changePasswordSubmit':'I-update ang password',
        'profile.activityLogTitle':'Tala ng aktibidad','profile.activityLogSubtitle':'Subaybayan ang aplikasyon, renewal, at resibong isinumite ninyo',
        'profile.activityLogSubtitleElevated':'Isang petsang talaan ng bawat clearance, bayad, at pagbabago sa account sa inyong profile.',
        'profile.activity.empty':'Walang aktibidad pa. Magsumite ng aplikasyon, renewal, o resibo upang makita dito.',
        'profile.activity.emptyFilter':'Walang kaganapan sa kategoryang ito.',
        'profile.activity.events':'mga kaganapan',
        'profile.activity.filterLabel':'I-filter ang aktibidad',
        'profile.activity.filter.all':'Lahat','profile.activity.filter.applications':'Aplikasyon','profile.activity.filter.renewals':'Pag-renew','profile.activity.filter.payments':'Bayad','profile.activity.filter.account':'Account',
        'profile.activity.showAll':'Ipakita ang lahat ng aktibidad','profile.activity.showLess':'Ipakita ang mas kaunti','profile.activity.showMore':'Ipakita ang {{count}} pa',
        'profile.activity.group.today':'Ngayon','profile.activity.group.yesterday':'Kahapon',
        'profile.activity.appSubmitted':'Naisumite ang aplikasyon','profile.activity.appUpdated':'Na-update ang aplikasyon',
        'profile.activity.renewalSubmitted':'Naisumite ang renewal','profile.activity.renewalUpdated':'Na-update ang renewal',
        'profile.activity.receiptSent':'Naipadala ang resibo','profile.activity.passwordChanged':'Napalitan ang password','profile.activity.unknown':'Aktibidad',
        'app.err.failedLoad':'Hindi na-load ang detalye.','app.err.noDocs':'Walang na-upload na dokumento.','app.err.failedLoadDetails':'Hindi na-load ang detalye ng aplikasyon.',
        'app.documentFallback':'Dokumento',
        'alert.imageInvalid':'Pumili ng wastong larawan.','alert.imageSize':'Ang larawan ay dapat mas mababa sa 2MB','alert.photoUpdated':'Na-update ang larawan sa profile!',
        'alert.photoFailed':'Hindi na-update ang larawan','alert.uploadFailed':'Nabigo ang pag-upload',
        'lang.alt.uk':'Bandila ng UK','lang.alt.ph':'Bandila ng Pilipinas','lang.option.en':'EN','lang.option.ph':'PH',
        'profile.applicationFallback':'Aplikasyon #%s',
        'profile.msg.fullNameRequired':'Kailangan ang buong pangalan.',
        'profile.msg.profileUpdated':'Matagumpay na na-update ang profile!',
        'profile.msg.profileUpdateFailed':'Hindi na-update ang profile. Subukan muli.',
        'profile.msg.currentPasswordRequired':'Kailangan ang kasalukuyang password.',
        'profile.msg.newPasswordRequired':'Kailangan ang bagong password.',
        'profile.msg.passwordsMismatch':'Hindi tugma ang mga bagong password.',
        'profile.msg.passwordMinLength':'Ang bagong password ay dapat hindi bababa sa 8 character.',
        'profile.msg.passwordComplexity':'Ang bagong password ay dapat may maliit na titik, malaking titik, at numero.',
        'profile.msg.currentPasswordWrong':'Mali ang kasalukuyang password.',
        'profile.msg.passwordChanged':'Matagumpay na napalitan ang password!',
        'profile.msg.passwordChangeFailed':'Hindi napalitan ang password. Subukan muli.'
      }
    };
    function getPreferredLang() { return localStorage.getItem('preferredLanguage') || 'en'; }
    function tDict(lang) { return translations[lang] || translations.en; }
    function tKey(lang, key) { const d = tDict(lang); return d[key] !== undefined ? d[key] : (translations.en[key] || key); }
    var SERVER_MSG_KEYS = {
      'Full name is required.': 'profile.msg.fullNameRequired',
      'Profile updated successfully!': 'profile.msg.profileUpdated',
      'Failed to update profile. Please try again.': 'profile.msg.profileUpdateFailed',
      'Current password is required.': 'profile.msg.currentPasswordRequired',
      'New password is required.': 'profile.msg.newPasswordRequired',
      'New passwords do not match.': 'profile.msg.passwordsMismatch',
      'New password must be at least 8 characters long.': 'profile.msg.passwordMinLength',
      'New password must contain at least one lowercase letter, one uppercase letter, and one number.': 'profile.msg.passwordComplexity',
      'Current password is incorrect.': 'profile.msg.currentPasswordWrong',
      'Password changed successfully!': 'profile.msg.passwordChanged',
      'Failed to change password. Please try again.': 'profile.msg.passwordChangeFailed'
    };
    function translateServerAlerts(lang) {
      var t = tDict(lang);
      document.querySelectorAll('.profile-server-msg').forEach(function(el) {
        var en = el.getAttribute('data-msg-en');
        if (!en) return;
        var key = SERVER_MSG_KEYS[en];
        if (key && t[key] !== undefined) el.textContent = t[key];
        else el.textContent = en;
      });
    }
    function updateMemberSince(lang) {
      var el = document.getElementById('created_at');
      if (!el || !el.dataset.memberSince) return;
      var d = new Date(el.dataset.memberSince + 'T12:00:00');
      if (lang === 'ph') {
        var monthsPh = ['Enero', 'Pebrero', 'Marso', 'Abril', 'Mayo', 'Hunyo', 'Hulyo', 'Agosto', 'Setyembre', 'Oktubre', 'Nobyembre', 'Disyembre'];
        el.value = monthsPh[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
      } else {
        el.value = d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
      }
    }
    function updateLanguageIcon(lang) {
      document.querySelectorAll('.language-selector').forEach(function(s) {
        s.classList.remove('ph-selected', 'en-selected');
        s.classList.add(lang === 'ph' ? 'ph-selected' : 'en-selected');
      });
    }
    function updateChartDateRange(lang) {
      var el = document.getElementById('chartDateRange');
      if (!el || !el.dataset.isoDate) return;
      var d = new Date(el.dataset.isoDate + 'T12:00:00');
      var weekdayEl = el.querySelector('.chart-date-weekday');
      var monthEl = el.querySelector('.chart-date-month');
      var dayEl = el.querySelector('.chart-date-daynum');
      var yearEl = el.querySelector('.chart-date-year');
      var monthsPh = ['Enero', 'Pebrero', 'Marso', 'Abril', 'Mayo', 'Hunyo', 'Hulyo', 'Agosto', 'Setyembre', 'Oktubre', 'Nobyembre', 'Disyembre'];
      var weekdaysPh = ['Linggo', 'Lunes', 'Martes', 'Miyerkules', 'Huwebes', 'Biyernes', 'Sabado'];
      if (lang === 'ph') {
        if (weekdayEl) weekdayEl.textContent = weekdaysPh[d.getDay()];
        if (monthEl) monthEl.textContent = monthsPh[d.getMonth()];
      } else {
        if (weekdayEl) weekdayEl.textContent = d.toLocaleDateString('en-US', { weekday: 'long' });
        if (monthEl) monthEl.textContent = d.toLocaleDateString('en-US', { month: 'long' });
      }
      if (dayEl) dayEl.textContent = String(d.getDate());
      if (yearEl) yearEl.textContent = String(d.getFullYear());
    }
    function updateRollButtonText(lang) {
      var btn = document.getElementById('rollUnrollBtn');
      if (!btn) return;
      var expanded = btn.getAttribute('data-roll-expanded') === '1';
      var t = tDict(lang);
      var key = expanded ? 'profile.roll.showLess' : 'profile.roll.viewAll';
      var iconClass = expanded ? 'fa-chevron-up' : 'fa-chevron-down';
      btn.innerHTML = '<i class="fas ' + iconClass + '"></i> <span data-translate="' + key + '">' + (t[key] || '') + '</span>';
    }
    function changeLanguage(lang) {
      localStorage.setItem('preferredLanguage', lang);
      updateLanguageIcon(lang);
      document.querySelectorAll('#languageSelect, #languageSelectDesktop').forEach(function(el) { if (el) el.value = lang; });
      var t = tDict(lang);
      document.querySelectorAll('[data-translate]').forEach(function(el) {
        var key = el.getAttribute('data-translate');
        if (!key || key.indexOf('nav.') === 0) return;
        if (t[key] !== undefined) el.textContent = t[key];
      });
      document.querySelectorAll('[data-translate-placeholder]').forEach(function(el) {
        var key = el.getAttribute('data-translate-placeholder');
        if (key && t[key] !== undefined) el.placeholder = t[key];
      });
      document.querySelectorAll('[data-translate-title]').forEach(function(el) {
        var key = el.getAttribute('data-translate-title');
        if (!key || key.indexOf('nav.') === 0) return;
        if (t[key] !== undefined) el.title = t[key];
      });
      document.querySelectorAll('[data-translate-aria]').forEach(function(el) {
        var key = el.getAttribute('data-translate-aria');
        if (key && t[key] !== undefined) el.setAttribute('aria-label', t[key]);
      });
      document.querySelectorAll('[data-translate-alt]').forEach(function(el) {
        var key = el.getAttribute('data-translate-alt');
        if (key && t[key] !== undefined) el.alt = t[key];
      });
      document.querySelectorAll('[data-i18n-status]').forEach(function(el) {
        var s = el.getAttribute('data-i18n-status');
        var k = 'profile.status.' + s;
        if (t[k] !== undefined) el.textContent = t[k];
      });
      document.querySelectorAll('[data-i18n-apptype]').forEach(function(el) {
        var a = el.getAttribute('data-i18n-apptype');
        var k = 'profile.apptype.' + a;
        if (t[k] !== undefined) el.textContent = t[k];
      });
      document.querySelectorAll('[data-chart-day-index]').forEach(function(el) {
        var idx = el.getAttribute('data-chart-day-index');
        var k = 'profile.chart.day' + idx;
        if (t[k] !== undefined) el.textContent = t[k];
      });
      document.querySelectorAll('[data-i18n-app-number]').forEach(function(el) {
        var num = el.getAttribute('data-i18n-app-number');
        var tpl = t['profile.applicationFallback'];
        if (tpl && num) el.textContent = tpl.replace('%s', num);
      });
      updateChartDateRange(lang);
      updateMemberSince(lang);
      translateServerAlerts(lang);
      updateRollButtonText(lang);
      (function syncProfileReceiptFileName() {
        var inp = document.getElementById('profileReceiptFile');
        var nm = document.getElementById('profileReceiptFileName');
        if (!inp || !nm) return;
        if (inp.files && inp.files.length) {
          nm.textContent = inp.files[0].name;
        } else {
          nm.textContent = tKey(lang, 'profile.receipt.noFileChosen');
        }
      })();
      var calModal = document.getElementById('calendarModal');
      if (calModal && calModal.classList.contains('show')) renderCalendar();
      var fnClear = document.getElementById('full_name');
      if (fnClear) fnClear.setCustomValidity('');
      var newPw = document.getElementById('new_password');
      if (newPw) newPw.dispatchEvent(new Event('input', { bubbles: true }));
    }
    function confirmLogout() {
      var lang = getPreferredLang();
      return confirm(tKey(lang, 'confirm.logout'));
    }
    (function initLang() {
      var lang = localStorage.getItem('preferredLanguage') || 'en';
      document.querySelectorAll('#languageSelect, #languageSelectDesktop').forEach(function(el) { if (el) el.value = lang; });
      changeLanguage(lang);
      var langSelect = document.getElementById('languageSelect');
      var langSelectDesktop = document.getElementById('languageSelectDesktop');
      if (langSelect) {
        langSelect.addEventListener('change', function() {
          if (langSelectDesktop) langSelectDesktop.value = this.value;
          changeLanguage(this.value);
        });
      }
      if (langSelectDesktop) {
        langSelectDesktop.addEventListener('change', function() {
          if (langSelect) langSelect.value = this.value;
          changeLanguage(this.value);
        });
      }
    })();

    // Calendar modal — same logic as menu.php (month grid, today highlighted orange)
    function updateCalendarDate() {
      var dateDisplay = document.getElementById('calendarDateDisplay');
      if (dateDisplay) {
        var today = new Date();
        dateDisplay.textContent = today.getDate();
      }
    }
    function renderCalendar() {
      var calendarContainer = document.getElementById('calendarContainer');
      if (!calendarContainer) return;
      var lang = getPreferredLang();
      var today = new Date();
      var currentYear = today.getFullYear();
      var currentMonth = today.getMonth();
      var currentDate = today.getDate();
      var firstDay = new Date(currentYear, currentMonth, 1).getDay();
      var daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
      var monthNamesEn = ['January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'];
      var monthNamesPh = ['Enero', 'Pebrero', 'Marso', 'Abril', 'Mayo', 'Hunyo', 'Hulyo', 'Agosto', 'Setyembre', 'Oktubre', 'Nobyembre', 'Disyembre'];
      var monthNames = lang === 'ph' ? monthNamesPh : monthNamesEn;
      var dayNamesEn = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
      var dayNamesPh = ['Li', 'Lu', 'Ma', 'Mi', 'Hu', 'Bi', 'Sa'];
      var dayNames = lang === 'ph' ? dayNamesPh : dayNamesEn;
      var calendarHTML = '<div style="text-align: center; margin-bottom: 1.5rem;">' +
        '<h4 style="color: #667eea; font-weight: 600; margin: 0;">' +
        monthNames[currentMonth] + ' ' + currentYear +
        '</h4></div>' +
        '<table style="width: 100%; border-collapse: collapse;"><thead><tr>';
      for (var d = 0; d < 7; d++) {
        calendarHTML += '<th style="padding: 0.5rem; color: #667eea; font-weight: 600; font-size: 0.85rem; text-align: center;">' + dayNames[d] + '</th>';
      }
      calendarHTML += '</tr></thead><tbody>';
      var date = 1;
      for (var i = 0; i < 6; i++) {
        calendarHTML += '<tr>';
        for (var j = 0; j < 7; j++) {
          if (i === 0 && j < firstDay) {
            calendarHTML += '<td style="padding: 0.5rem;"></td>';
          } else if (date > daysInMonth) {
            calendarHTML += '<td style="padding: 0.5rem;"></td>';
          } else {
            var isToday = date === currentDate;
            var cellStyle = isToday
              ? 'background: #ff6b35; color: #ffffff; border-radius: 50%; font-weight: 700;'
              : 'color: #475569;';
            calendarHTML += '<td style="padding: 0.5rem; text-align: center;">' +
              '<div style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; margin: 0 auto; ' + cellStyle + '">' +
              date + '</div></td>';
            date++;
          }
        }
        calendarHTML += '</tr>';
        if (date > daysInMonth) break;
      }
      calendarHTML += '</tbody></table>';
      calendarContainer.innerHTML = calendarHTML;
    }
    (function initCalendarModal() {
      var calendarModal = document.getElementById('calendarModal');
      var calendarContainer = document.getElementById('calendarContainer');
      if (calendarModal && calendarContainer) {
        calendarModal.addEventListener('show.bs.modal', function() {
          renderCalendar();
        });
      }
      document.addEventListener('DOMContentLoaded', function() {
        updateCalendarDate();
      });
    })();

    // Profile dark mode (apply user darkmode logic to both mobile + desktop buttons)
    (function() {
      let darkmode = localStorage.getItem('darkmode');
      const enableDarkmode = () => {
        document.body.classList.add('darkmode');
        localStorage.setItem('darkmode', 'active');
      };
      const disableDarkmode = () => {
        document.body.classList.remove('darkmode');
        try {
          localStorage.removeItem('darkmode');
        } catch (e) {}
      };
      if (darkmode === 'active') enableDarkmode();
      document.querySelectorAll('.profile-darkmode-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
          darkmode = localStorage.getItem('darkmode');
          darkmode !== 'active' ? enableDarkmode() : disableDarkmode();
        });
      });
    })();

    // Edit profile functionality (labels/buttons follow language via changeLanguage; validation messages localized)
    const editBtn = document.getElementById('editBtn');
    const saveBtn = document.getElementById('saveBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const fullNameInput = document.getElementById('full_name');

    if (fullNameInput) {
      fullNameInput.addEventListener('invalid', function() {
        this.setCustomValidity(tKey(getPreferredLang(), 'profile.msg.fullNameRequired'));
      });
      fullNameInput.addEventListener('input', function() {
        this.setCustomValidity('');
      });
    }

    let originalFullName = fullNameInput ? fullNameInput.value : '';

    if (editBtn && saveBtn && cancelBtn && fullNameInput) {
      editBtn.addEventListener('click', () => {
        originalFullName = fullNameInput.value;
        fullNameInput.setCustomValidity('');
        fullNameInput.disabled = false;
        editBtn.style.display = 'none';
        saveBtn.style.display = 'flex';
        cancelBtn.style.display = 'flex';
        fullNameInput.focus();
        changeLanguage(getPreferredLang());
      });

      cancelBtn.addEventListener('click', () => {
        fullNameInput.value = originalFullName;
        fullNameInput.setCustomValidity('');
        fullNameInput.disabled = true;
        editBtn.style.display = 'flex';
        saveBtn.style.display = 'none';
        cancelBtn.style.display = 'none';
        changeLanguage(getPreferredLang());
      });
    }

    // Manage account accordions — full content visible, stable card shell
    var manageAccordionPanelIds = ['profileReceiptPanel', 'passwordFormPanel'];

    function closeManageAccordionPanel(panelId) {
      var otherPanel = document.getElementById(panelId);
      var otherRow = document.querySelector('.account-card-header-row[data-toggle="' + panelId + '"]');
      var otherCard = otherRow ? otherRow.closest('.account-card-expandable') : null;
      if (!otherPanel || !otherCard) return;
      otherPanel.setAttribute('hidden', '');
      otherCard.classList.remove('expanded');
      otherCard.setAttribute('aria-expanded', 'false');
      if (otherRow) otherRow.setAttribute('aria-expanded', 'false');
      var otherIcon = otherCard.querySelector('.account-expand-icon i');
      if (otherIcon) otherIcon.className = 'fas fa-chevron-down';
    }

    function setManageAccordionOpen(panel, card, open) {
      if (!panel || !card) return;
      if (open) {
        manageAccordionPanelIds.forEach(function(pid) {
          if (pid !== panel.id) closeManageAccordionPanel(pid);
        });
        panel.removeAttribute('hidden');
        card.classList.add('expanded');
        card.setAttribute('aria-expanded', 'true');
      } else {
        panel.setAttribute('hidden', '');
        card.classList.remove('expanded');
        card.setAttribute('aria-expanded', 'false');
      }
      var icon = card.querySelector('.account-expand-icon i');
      if (icon) icon.className = open ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
    }

    document.querySelectorAll('.account-card-header-row[data-toggle]').forEach(function(row) {
      row.style.cursor = 'pointer';
      var targetId = row.getAttribute('data-toggle');
      var panel = document.getElementById(targetId);
      var card = row.closest('.account-card-expandable');
      if (card && panel) {
        card.setAttribute('aria-expanded', panel.hasAttribute('hidden') ? 'false' : 'true');
        row.setAttribute('aria-expanded', panel.hasAttribute('hidden') ? 'false' : 'true');
        row.setAttribute('aria-controls', targetId);
      }
      row.addEventListener('click', function(e) {
        if (e.target.closest('a, button, input, textarea, select, label')) return;
        var pid = this.getAttribute('data-toggle');
        var p = document.getElementById(pid);
        var c = this.closest('.account-card-expandable');
        if (!p || !c) return;
        var willOpen = p.hasAttribute('hidden');
        setManageAccordionOpen(p, c, willOpen);
        this.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      });
    });

    document.querySelectorAll('.profile-password-toggle').forEach(function(btn) {
      btn.addEventListener('click', function() {
        const id = this.getAttribute('data-toggle-password');
        const input = id ? document.getElementById(id) : null;
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        const icon = this.querySelector('i');
        if (icon) icon.className = show ? 'far fa-eye-slash' : 'far fa-eye';
      });
    });

    (function setupPasswordStrength() {
      const input = document.getElementById('new_password');
      const bars = document.querySelectorAll('.profile-pw-strength-bars span');
      const label = document.getElementById('profilePwStrengthText');
      const rules = document.querySelectorAll('#profilePwRules li[data-rule]');
      if (!input || !bars.length || !label) return;

      function strengthKey(score) {
        if (score <= 1) return 'profile.passwordStrength.tooWeak';
        if (score === 2) return 'profile.passwordStrength.weak';
        if (score === 3) return 'profile.passwordStrength.fair';
        return 'profile.passwordStrength.strong';
      }

      function refresh() {
        const val = input.value || '';
        const checks = {
          len: val.length >= 8,
          mixed: /[a-zA-Z]/.test(val) && /\d/.test(val),
          case: /[a-z]/.test(val) && /[A-Z]/.test(val),
          digit: /\d/.test(val)
        };
        let score = 0;
        rules.forEach(function(li) {
          const key = li.getAttribute('data-rule');
          const met = !!checks[key];
          li.classList.toggle('is-met', met);
          if (met) score += 1;
        });
        bars.forEach(function(bar, idx) {
          bar.classList.toggle('is-on', idx < score);
        });
        label.textContent = tKey(getPreferredLang(), strengthKey(score));
      }
      input.addEventListener('input', refresh);
      refresh();
    })();

    (function setupReceiptNotesCounter() {
      const notes = document.getElementById('profileReceiptNotes');
      const counter = document.getElementById('profileReceiptCharCount');
      if (!notes || !counter) return;
      function refresh() {
        const max = notes.maxLength > 0 ? notes.maxLength : 500;
        counter.textContent = String(notes.value.length) + '/' + String(max);
      }
      notes.addEventListener('input', refresh);
      refresh();
    })();

    const heroEditProfileBtn = document.getElementById('heroEditProfileBtn');
    const editProfileModalEl = document.getElementById('editProfileModal');
    if (heroEditProfileBtn && editProfileModalEl && window.bootstrap) {
      const editProfileModal = bootstrap.Modal.getOrCreateInstance(editProfileModalEl);
      heroEditProfileBtn.addEventListener('click', function() {
        editProfileModal.show();
      });
      editProfileModalEl.addEventListener('hidden.bs.modal', function() {
        if (cancelBtn && cancelBtn.style.display !== 'none') cancelBtn.click();
      });
    }

    // Cancel password form
    const cancelPasswordBtn = document.getElementById('cancelPasswordBtn');
    if (cancelPasswordBtn) {
      cancelPasswordBtn.addEventListener('click', function() {
        const form = document.getElementById('passwordForm');
        if (form) form.reset();
        const panel = document.getElementById('passwordFormPanel');
        const card = cancelPasswordBtn.closest('.account-card-expandable');
        if (typeof setManageAccordionOpen === 'function') {
          setManageAccordionOpen(panel, card, false);
        } else if (panel && card) {
          panel.setAttribute('hidden', '');
          card.classList.remove('expanded');
        }
      });
    }

    // Hamburger menu / mobile sidebar (same behavior as menu.php)
    (function() {
      const navbarToggler = document.getElementById('navbarToggler');
      const navbarCenter = document.getElementById('navbarCenter');
      const navbarRight = document.querySelector('.navbar-right');
      const navbarOverlay = document.getElementById('navbarOverlay');

      function closeSidebar() {
        if (window.innerWidth <= 991) {
          if (navbarToggler) navbarToggler.setAttribute('aria-expanded', 'false');
          if (navbarCenter) navbarCenter.classList.remove('show');
          if (navbarOverlay) navbarOverlay.classList.remove('show');
          document.body.style.overflow = '';
        }
      }

      if (navbarToggler && navbarCenter) {
        navbarToggler.addEventListener('click', function(e) {
          e.stopPropagation();
          const isExpanded = this.getAttribute('aria-expanded') === 'true';
          this.setAttribute('aria-expanded', String(!isExpanded));
          navbarCenter.classList.toggle('show');
          if (window.innerWidth <= 991) {
            if (navbarOverlay) navbarOverlay.classList.toggle('show');
            document.body.style.overflow = isExpanded ? '' : 'hidden';
          }
        });

        if (navbarOverlay) {
          navbarOverlay.addEventListener('click', closeSidebar);
        }

        document.addEventListener('click', function(event) {
          if (window.innerWidth <= 991 && navbarCenter.classList.contains('show')) {
            const inside = navbarToggler.contains(event.target) || navbarCenter.contains(event.target);
            if (!inside) closeSidebar();
          }
        });

        if (navbarRight) {
          const logoutBtn = navbarRight.querySelector('#logoutBtn');
          if (logoutBtn) {
            logoutBtn.addEventListener('click', function() {
              if (window.innerWidth <= 991) closeSidebar();
            });
          }
        }

        window.addEventListener('resize', function() {
          if (window.innerWidth > 991) {
            navbarCenter.classList.remove('show');
            navbarToggler.setAttribute('aria-expanded', 'false');
            if (navbarOverlay) navbarOverlay.classList.remove('show');
            document.body.style.overflow = '';
          }
        });

        document.querySelectorAll('#calendarIconLink, #calendarIconLinkDesktop').forEach(function(calLink) {
          calLink.addEventListener('click', function() {
            if (window.innerWidth <= 991) closeSidebar();
          });
        });
      }
    })();

    // Roll/unroll applications
    (function setupActivityLog() {
      const feed = document.getElementById('profileActivityFeed');
      const filters = document.querySelectorAll('.profile-activity-filter');
      const expandBtn = document.getElementById('profileActivityExpandBtn');
      const items = document.querySelectorAll('.profile-activity-item');
      const days = document.querySelectorAll('.profile-activity-day');
      const emptyEl = document.getElementById('profileActivityEmptyFilter');
      if (!feed || !items.length) return;

      const limit = parseInt(feed.getAttribute('data-activity-limit') || '3', 10);
      let expanded = false;
      let activeCat = 'all';

      function syncDayHeaders() {
        days.forEach(function(day) {
          let node = day.nextElementSibling;
          let hasVisible = false;
          while (node && !node.classList.contains('profile-activity-day')) {
            if (node.classList.contains('profile-activity-item') &&
                !node.classList.contains('is-hidden') &&
                !node.classList.contains('is-overflow-hidden')) {
              hasVisible = true;
              break;
            }
            node = node.nextElementSibling;
          }
          day.classList.toggle('is-hidden', !hasVisible);
          day.classList.toggle('is-overflow-hidden', !hasVisible);
        });
      }

      function applyView() {
        const cat = activeCat || 'all';
        let visibleMatching = [];
        items.forEach(function(item) {
          const match = cat === 'all' || item.getAttribute('data-activity-cat') === cat;
          item.classList.toggle('is-hidden', !match);
          if (match) visibleMatching.push(item);
        });
        visibleMatching.forEach(function(item, idx) {
          const hideOverflow = !expanded && idx >= limit;
          item.classList.toggle('is-overflow-hidden', hideOverflow);
        });
        if (emptyEl) emptyEl.hidden = visibleMatching.length > 0;
        syncDayHeaders();
        if (expandBtn) {
          const hiddenCount = expanded ? 0 : Math.max(0, visibleMatching.length - limit);
          expandBtn.style.display = visibleMatching.length > limit ? '' : 'none';
          const span = expandBtn.querySelector('span');
          if (span) {
            const L = getPreferredLang();
            if (expanded) {
              span.textContent = tKey(L, 'profile.activity.showLess');
            } else if (hiddenCount > 0) {
              span.textContent = tKey(L, 'profile.activity.showMore').replace('{{count}}', String(hiddenCount));
            } else {
              span.textContent = tKey(L, 'profile.activity.showAll');
            }
          }
        }
      }

      filters.forEach(function(btn) {
        btn.addEventListener('click', function() {
          activeCat = this.getAttribute('data-activity-filter') || 'all';
          filters.forEach(function(b) {
            const on = b === btn;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
          });
          expanded = false;
          feed.classList.remove('is-expanded');
          if (expandBtn) expandBtn.setAttribute('aria-expanded', 'false');
          applyView();
        });
      });
      if (expandBtn) {
        expandBtn.addEventListener('click', function() {
          expanded = !expanded;
          feed.classList.toggle('is-expanded', expanded);
          expandBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
          applyView();
        });
      }
      applyView();
    })();

    const rollUnrollBtn = document.getElementById('rollUnrollBtn');
    const restAppsWrapper = document.getElementById('restAppsWrapper');
    if (rollUnrollBtn && restAppsWrapper) {
      rollUnrollBtn.addEventListener('click', function() {
        const expanded = restAppsWrapper.classList.toggle('visible');
        rollUnrollBtn.classList.toggle('expanded', expanded);
        rollUnrollBtn.setAttribute('data-roll-expanded', expanded ? '1' : '0');
        updateRollButtonText(getPreferredLang());
      });
    }

    function prependReceiptActivityLogRow(receiptId, detailsText) {
      var cardInner = document.querySelector('.activity-log-body .account-content-card');
      if (!cardInner) return;
      var empty = cardInner.querySelector('.activity-log-empty');
      var list = cardInner.querySelector('.activity-log-entries');
      if (empty) {
        empty.remove();
      }
      if (!list) {
        list = document.createElement('div');
        list.className = 'activity-log-entries';
        list.setAttribute('role', 'list');
        cardInner.appendChild(list);
      }
      var lang = getPreferredLang();
      var now = new Date();
      var isoAttr = now.toISOString();
      var disp = lang === 'ph'
        ? (function () {
            var mph = ['Ene', 'Peb', 'Mar', 'Abr', 'May', 'Hun', 'Hul', 'Ago', 'Set', 'Okt', 'Nob', 'Dis'];
            var h = now.getHours();
            var m = now.getMinutes();
            var ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12;
            if (h === 0) h = 12;
            return mph[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear() + ' ' + h + ':' + (m < 10 ? '0' : '') + m + ' ' + ampm;
          })()
        : now.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
      var line = tKey(lang, 'profile.receipt.activityLine').replace('%s', String(receiptId || ''));
      var item = document.createElement('div');
      item.className = 'activity-log-item';
      item.setAttribute('role', 'listitem');
      item.innerHTML =
        '<div class="activity-log-item-icon" aria-hidden="true"></div>' +
        '<div class="activity-log-item-text">' +
        '<span class="activity-log-type" data-translate="profile.activity.receiptSent"></span>' +
        '<span class="activity-log-details"></span>' +
        '<time class="activity-log-time" datetime="' + isoAttr.replace(/"/g, '&quot;') + '"></time>' +
        '</div>';
      var iconWrap = item.querySelector('.activity-log-item-icon');
      if (iconWrap) {
        var rimg = document.createElement('img');
        rimg.src = 'assets/imgs/receipt_icon.jpg';
        rimg.alt = '';
        rimg.className = 'receipt-icon-img';
        rimg.onerror = function () {
          iconWrap.removeChild(rimg);
          var fi = document.createElement('i');
          fi.className = 'fas fa-receipt';
          iconWrap.appendChild(fi);
        };
        iconWrap.appendChild(rimg);
      }
      var det = item.querySelector('.activity-log-details');
      if (det) det.textContent = line;
      var tm = item.querySelector('.activity-log-time');
      if (tm) tm.textContent = disp;
      list.insertBefore(item, list.firstChild);
      changeLanguage(lang);
    }

    // Send receipt (submit_menu_receipt.php)
    (function () {
      var form = document.getElementById('profileReceiptForm');
      var feedback = document.getElementById('profileReceiptFeedback');
      var submitBtn = document.getElementById('profileReceiptSubmit');
      var fileInput = document.getElementById('profileReceiptFile');
      var fileNameEl = document.getElementById('profileReceiptFileName');
      function updateReceiptFileLabel() {
        if (!fileInput || !fileNameEl) return;
        var lang = getPreferredLang();
        if (fileInput.files && fileInput.files.length) {
          fileNameEl.textContent = fileInput.files[0].name;
        } else {
          fileNameEl.textContent = tKey(lang, 'profile.receipt.noFileChosen');
        }
      }
      if (fileInput) {
        fileInput.addEventListener('change', updateReceiptFileLabel);
        updateReceiptFileLabel();
      }
      if (!form || !submitBtn) return;
      if (form.getAttribute('data-receipt-blocked') === '1') return;
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var lang = getPreferredLang();
        var noFile = tKey(lang, 'profile.receipt.errNoFile');
        if (!fileInput || !fileInput.files || !fileInput.files.length) {
          if (feedback) {
            feedback.textContent = noFile;
            feedback.className = 'mt-2 mb-0 is-error';
          }
          return;
        }
        if (feedback) {
          feedback.textContent = '';
          feedback.className = 'mt-2 mb-0';
        }
        submitBtn.disabled = true;
        var fd = new FormData(form);
        fetch('submit_menu_receipt.php', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function (r) {
            return r.json().catch(function () {
              return { success: false, message: 'Something went wrong.' };
            });
          })
          .then(function (data) {
            submitBtn.disabled = false;
            if (!feedback) return;
            if (data && data.success) {
              var msg = data.message || 'Submitted.';
              if (data.email_attempted === true && data.email_sent === false) {
                msg += ' ' + tKey(lang, 'profile.receipt.emailNotSent');
              }
              feedback.textContent = msg;
              feedback.className = 'mt-2 mb-0 is-success';
              form.reset();
              updateReceiptFileLabel();
              if (data.receipt_id) prependReceiptActivityLogRow(data.receipt_id, data.activity_details);
            } else {
              feedback.textContent = (data && data.message) || 'Could not submit.';
              feedback.className = 'mt-2 mb-0 is-error';
            }
          })
          .catch(function () {
            submitBtn.disabled = false;
            if (feedback) {
              feedback.textContent = 'Could not submit. Please try again.';
              feedback.className = 'mt-2 mb-0 is-error';
            }
          });
      });
    })();

    // Eye icon: view application details modal
    document.querySelectorAll('.account-eye-btn').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        const card = this.closest('.account-card');
        const vendorId = card && card.getAttribute('data-vendor-id');
        if (!vendorId) return;
        const modal = new bootstrap.Modal(document.getElementById('applicationDetailsModal'));
        const loading = document.getElementById('applicationDetailsLoading');
        const content = document.getElementById('applicationDetailsContent');
        const infoEl = document.getElementById('appDetailsInfo');
        const docList = document.getElementById('appDetailsDocList');
        loading.style.display = 'block';
        if (content) content.style.display = 'none';
        modal.show();
        fetch('get_application_details.php?vendor_id=' + encodeURIComponent(vendorId))
          .then(r => r.json())
          .then(data => {
            loading.style.display = 'none';
            const L = getPreferredLang();
            const esc = function(s) {
              if (s == null) return '';
              return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            };
            if (!data.success) {
              if (infoEl) infoEl.innerHTML = '<p class="text-danger">' + esc(data.message || tKey(L, 'app.err.failedLoad')) + '</p>';
              if (content) content.style.display = 'block';
              return;
            }
            const v = data.vendor || {};
            const docs = data.documents || [];
            const st = (v.status || '').toLowerCase();
            const statusLabel = st === 'approved' ? tKey(L, 'profile.status.approved') : (st === 'pending' ? tKey(L, 'profile.status.pending') : tKey(L, 'profile.status.incomplete'));
            const typeLabel = (v.application_type || 'new') === 'renewal' ? tKey(L, 'profile.apptype.renewal') : tKey(L, 'profile.apptype.new');
            var submitted = '—';
            if (v.submitted_date) {
              var sd = new Date(v.submitted_date);
              if (L === 'ph') {
                var mph = ['Enero', 'Pebrero', 'Marso', 'Abril', 'Mayo', 'Hunyo', 'Hulyo', 'Agosto', 'Setyembre', 'Oktubre', 'Nobyembre', 'Disyembre'];
                submitted = mph[sd.getMonth()] + ' ' + sd.getDate() + ', ' + sd.getFullYear();
              } else {
                submitted = sd.toLocaleDateString();
              }
            }
            if (infoEl) infoEl.innerHTML = '<table class="table table-sm table-borderless"><tbody>' +
              '<tr><td class="text-muted" style="width:140px;">' + esc(tKey(L, 'modal.appDetails.name')) + '</td><td><strong>' + esc(v.vendor_name || '—') + '</strong></td></tr>' +
              '<tr><td class="text-muted">' + esc(tKey(L, 'modal.appDetails.type')) + '</td><td>' + esc(typeLabel) + '</td></tr>' +
              '<tr><td class="text-muted">' + esc(tKey(L, 'modal.appDetails.status')) + '</td><td><span class="badge bg-' + (st === 'approved' ? 'success' : (st === 'pending' ? 'warning' : 'secondary')) + '">' + esc(statusLabel) + '</span></td></tr>' +
              '<tr><td class="text-muted">' + esc(tKey(L, 'modal.appDetails.submitted')) + '</td><td>' + esc(submitted) + '</td></tr>' +
              '</tbody></table>';
            docList.innerHTML = '';
            if (docs.length === 0) {
              docList.innerHTML = '<p class="text-muted small">' + esc(tKey(L, 'app.err.noDocs')) + '</p>';
            } else {
              docs.forEach(function(d) {
                const path = (d.document_path || '').trim();
                const name = d.document_name || d.original_name || tKey(L, 'app.documentFallback');
                const isImg = /\.(jpg|jpeg|png|gif|webp|bmp)$/i.test(path) || (d.mime_type || '').startsWith('image/');
                const url = path ? (path.startsWith('/') || path.startsWith('http') ? path : path) : '';
                const col = document.createElement('div');
                col.className = 'col-md-6 col-lg-4';
                if (isImg && url) {
                  col.innerHTML = '<div class="border rounded p-2 bg-light"><a href="' + encodeURI(url) + '" target="_blank" rel="noopener"><img src="' + encodeURI(url) + '" alt="' + esc(name) + '" class="img-fluid rounded" style="max-height:160px;object-fit:cover;"></a><p class="small mt-1 mb-0 text-break">' + esc(name) + '</p></div>';
                } else {
                  col.innerHTML = '<div class="border rounded p-2 bg-light"><a href="' + encodeURI(url || '#') + '" target="_blank" rel="noopener" class="text-decoration-none"><i class="fas fa-file me-1"></i>' + esc(name) + '</a></div>';
                }
                if (docList) docList.appendChild(col);
              });
            }
            if (content) content.style.display = 'block';
          })
          .catch(function() {
            loading.style.display = 'none';
            var Lc = getPreferredLang();
            if (infoEl) infoEl.innerHTML = '<p class="text-danger">' + tKey(Lc, 'app.err.failedLoadDetails') + '</p>';
            if (content) content.style.display = 'block';
          });
      });
    });

    // Profile photo instant upload for users
    const userPhotoInput = document.getElementById('userPhotoInput');
    if (userPhotoInput) {
      userPhotoInput.addEventListener('change', function() {
        const file = this.files && this.files[0];
        if (!file) return;
        var Lp = getPreferredLang();
        if (!file.type.startsWith('image/')) { alert(tKey(Lp, 'alert.imageInvalid')); return; }
        if (file.size > 2 * 1024 * 1024) { alert(tKey(Lp, 'alert.imageSize')); return; }

        const formData = new FormData();
        formData.append('profile_pic', file);

        fetch('update_user_photo.php', { method: 'POST', body: formData })
          .then(r => r.json())
          .then(data => {
            if (data.success && data.file_url) {
              const avatar = document.getElementById('userAvatar');
              var Lp2 = getPreferredLang();
              var altTxt = tKey(Lp2, 'profile.avatarAlt');
              if (avatar) {
                avatar.innerHTML = '<img src="' + String(data.file_url).replace(/"/g, '&quot;') + '" alt="' + altTxt.replace(/"/g, '&quot;') + '" style="width:100%;height:100%;object-fit:cover;">';
              }
              alert(tKey(Lp2, 'alert.photoUpdated'));
            } else {
              alert(data.message || tKey(getPreferredLang(), 'alert.photoFailed'));
            }
          })
          .catch(() => alert(tKey(getPreferredLang(), 'alert.uploadFailed')));
      });
    }

  </script>
  <?php
  $cs_contact_href = 'index.php#contact';
  $cs_announcement_href = 'index.php#announcement';
  $cs_show_menu_tools = true;
  include __DIR__ . '/includes/mobile_enhancements.php';
  include __DIR__ . '/includes/customer_support_widget.php';
  ?>
</body>
</html>
