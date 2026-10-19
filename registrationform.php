<?php
require_once 'config.php';
require_once 'auth_functions.php';
require_once 'includes/submission_notify_email.php';

// Redirect if already logged in (admin Gmail → admin panel, same as index.php)
if (isset($_SESSION['user_id'])) {
    $is_admin = (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin')
        || (isset($_SESSION['email']) && strtolower((string) $_SESSION['email']) === 'daetpublicmarket@gmail.com');
    header('Location: ' . ($is_admin ? 'adminpanel.php' : 'menu.php'));
    exit();
}

// Make sure the users table has the profile columns used by this form
function ensureUserProfileColumns($conn) {
    $cols = [
        'gender'              => "ALTER TABLE users ADD COLUMN gender VARCHAR(20) NULL AFTER phone_number",
        'age'                 => "ALTER TABLE users ADD COLUMN age INT NULL AFTER gender",
        'residential_address' => "ALTER TABLE users ADD COLUMN residential_address VARCHAR(255) NULL AFTER age",
    ];
    foreach ($cols as $name => $sql) {
        $check = $conn->query("SHOW COLUMNS FROM users LIKE '" . $name . "'");
        if ($check && $check->num_rows === 0) {
            $conn->query($sql);
        }
    }
}

$error_message = '';
$login_error = '';
$success_message = '';
$show_login = false;
$reg_start_step = 1;
$highlight_email = false;

// ——— AJAX: check if email is already registered (step 1 Next) ———
if (isset($_GET['action']) && $_GET['action'] === 'check_email') {
    header('Content-Type: application/json; charset=utf-8');
    $checkEmail = filter_input(INPUT_GET, 'email', FILTER_SANITIZE_EMAIL);
    $exists = false;
    if ($checkEmail && filter_var($checkEmail, FILTER_VALIDATE_EMAIL)) {
        $exists = emailAlreadyRegistered($checkEmail);
    }
    echo json_encode(['exists' => (bool)$exists]);
    exit();
}

// ——— Login ———
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auth_login'])) {
    $show_login = true;
    $email = filter_input(INPUT_POST, 'login_email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['login_password'] ?? '';
    $remember_me = isset($_POST['remember_me']);

    if (empty($email) || empty($password)) {
        $login_error = 'Please enter both email and password.';
    } else {
        $result = authenticateUser($email, $password, $remember_me);
        if (!empty($result['success'])) {
            $is_admin = (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin')
                || strtolower((string) $email) === 'daetpublicmarket@gmail.com';
            header('Location: ' . ($is_admin ? 'adminpanel.php' : 'menu.php'));
            exit();
        }
        $login_error = $result['message'] ?? 'Invalid email or password.';
    }
}

// ——— Register ———
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auth_register'])) {
    $email = strtolower(trim((string)(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '')));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['fullname'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $age = trim($_POST['age'] ?? '');
    $residential_address = trim($_POST['residential_address'] ?? '');
    $terms_accepted = isset($_POST['terms_accepted']);

    $phone_digits = preg_replace('/\D+/', '', $phone_number);

    if (empty($email) || empty($password) || empty($full_name) || empty($phone_number)) {
        $error_message = "Please fill in all required fields, including phone number.";
        $reg_start_step = 1;
    } elseif (!preg_match('/^\d{11}$/', $phone_digits)) {
        $error_message = "Phone number must be exactly 11 digits.";
        $reg_start_step = 1;
        $_POST['phone_number'] = $phone_digits;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
        $reg_start_step = 1;
        $highlight_email = true;
    } elseif (emailAlreadyRegistered($email)) {
        $error_message = "This email is already registered. Please use a different email or login.";
        $reg_start_step = 1;
        $highlight_email = true;
    } elseif (empty($gender) || empty($age) || empty($residential_address)) {
        $error_message = "Please provide your gender, age, and residential address.";
        $reg_start_step = 2;
    } elseif (!is_numeric($age) || (int)$age < 15 || (int)$age > 120) {
        $error_message = "Please enter a valid age.";
        $reg_start_step = 2;
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
        $reg_start_step = 3;
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
        $reg_start_step = 3;
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $password)) {
        $error_message = "Password must contain at least one uppercase letter, one lowercase letter, and one number.";
        $reg_start_step = 3;
    } elseif (!$terms_accepted) {
        $error_message = "You must accept the Terms & Conditions.";
        $reg_start_step = 3;
    } else {
        $result = registerUser($email, $password, $full_name, $phone_digits);
        if ($result['success']) {
            if (!empty($result['user_id'])) {
                $conn = getDBConnection();
                if ($conn) {
                    ensureUserProfileColumns($conn);
                    $age_int = (int)$age;
                    $stmt = $conn->prepare("UPDATE users SET gender = ?, age = ?, residential_address = ? WHERE user_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("sisi", $gender, $age_int, $residential_address, $result['user_id']);
                        $stmt->execute();
                        $stmt->close();
                    }
                    $conn->close();
                }
            }
            $success_message = $result['message'];
            $_POST = [];
            $_SESSION['registration_success'] = true;
            try {
                dpm_notify_registration_email($email, $full_name);
            } catch (Throwable $e) {
                error_log('registration welcome email: ' . $e->getMessage());
            }
        } else {
            $error_message = $result['message'];
            if (!empty($result['code']) && $result['code'] === 'email_exists') {
                $reg_start_step = 1;
                $highlight_email = true;
            } elseif (preg_match('/email/i', $error_message)) {
                $reg_start_step = 1;
                $highlight_email = true;
            } elseif (preg_match('/password|terms/i', $error_message)) {
                $reg_start_step = 3;
            } elseif (preg_match('/gender|age|address/i', $error_message)) {
                $reg_start_step = 2;
            } else {
                $reg_start_step = 1;
            }
        }
    }
}

$pv = function ($key) {
    return isset($_POST[$key]) ? htmlspecialchars((string)$_POST[$key], ENT_QUOTES, 'UTF-8') : '';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Form - Daet Public Market</title>
    <link rel="icon" type="image/jpeg" href="assets/imgs/logo.jpeg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @font-face {
            font-family: 'Transcity';
            src:
                local('Transcity'),
                local('Transcity Regular'),
                url('assets/fonts/Transcity.woff2') format('woff2'),
                url('assets/fonts/Transcity.woff') format('woff'),
                url('assets/fonts/Transcity.ttf') format('truetype'),
                url('assets/fonts/Transcity.otf') format('opentype');
            font-weight: 400 800;
            font-style: normal;
            font-display: swap;
        }
        :root {
            --indigo: #667eea;
            --indigo-deep: #2e236c;
            --indigo-soft: #eef1ff;
            --orange: #ff6b1a;
            --text: #0f172a;
            --muted: #64748b;
            --field: #f4f6fb;
            --field-border: #e6eaf2;
            --white: #ffffff;
            --error: #dc2626;
            --success: #16a34a;
            --shadow-card: 0 28px 80px rgba(15, 23, 42, 0.38), 0 0 0 1px rgba(255,255,255,0.08);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: 'Manrope', system-ui, sans-serif;
            color: var(--text);
            overflow: hidden;
            background:
                linear-gradient(115deg, rgba(46, 35, 108, 0.93) 0%, rgba(46, 35, 108, 0.8) 42%, rgba(102, 126, 234, 0.65) 100%),
                url('assets/imgs/palengke.jpg') center / cover no-repeat fixed;
        }
        .page {
            height: 100vh;
            height: 100dvh;
            display: grid;
            place-items: center;
            padding: 1rem;
        }

        /* ——— Double-slider card ——— */
        .auth-card {
            position: relative;
            width: min(940px, 100%);
            height: min(540px, calc(100dvh - 2rem));
            max-height: 540px;
            background: var(--white);
            border-radius: 30px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            animation: cardIn 0.45s ease-out;
        }
        @keyframes cardIn {
            from { opacity: 0; transform: translateY(14px) scale(0.985); }
            to { opacity: 1; transform: none; }
        }

        .form-container {
            position: absolute;
            top: 0;
            height: 100%;
            width: 50%;
            transition: transform 0.6s ease-in-out;
            background: linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
            display: flex;
            flex-direction: column;
            padding: clamp(1.2rem, 2.5vw, 2.1rem) clamp(1.25rem, 3vw, 2.4rem);
            overflow: hidden;
            z-index: 2;
        }
        .sign-up-container {
            left: 0;
            opacity: 1;
            z-index: 2;
        }
        .sign-in-container {
            left: 0;
            opacity: 0;
            z-index: 1;
            transform: translateX(0);
            pointer-events: none;
        }
        .auth-card.right-panel-active .sign-in-container {
            transform: translateX(100%);
            opacity: 1;
            z-index: 5;
            pointer-events: auto;
        }
        .auth-card.right-panel-active .sign-up-container {
            transform: translateX(100%);
            opacity: 0;
            z-index: 1;
            pointer-events: none;
        }

        /* Overlay slides left/right */
        .overlay-container {
            position: absolute;
            top: 0;
            left: 50%;
            width: 50%;
            height: 100%;
            overflow: hidden;
            transition: transform 0.6s ease-in-out;
            z-index: 100;
        }
        .auth-card.right-panel-active .overlay-container {
            transform: translateX(-100%);
        }
        .overlay {
            position: relative;
            left: -100%;
            height: 100%;
            width: 200%;
            transform: translateX(0);
            transition: transform 0.6s ease-in-out;
        }
        .overlay__bg {
            position: absolute;
            inset: 0;
            background: url('assets/imgs/marketplace.gif') center / cover no-repeat;
            /* Light orange cast — gif stays clearly visible */
            filter: sepia(0.35) hue-rotate(-10deg) saturate(1.25) brightness(0.98);
        }
        .overlay__shade {
            position: absolute;
            inset: 0;
            background: linear-gradient(160deg, rgba(255, 107, 26, 0.4) 0%, rgba(255, 133, 85, 0.32) 55%, rgba(255, 107, 26, 0.28) 100%);
        }
        .auth-card.right-panel-active .overlay {
            transform: translateX(50%);
        }
        .overlay-panel {
            position: absolute;
            top: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            width: 50%;
            height: 100%;
            padding: 0 2rem;
            color: #fff;
            transition: transform 0.6s ease-in-out;
            z-index: 1;
        }
        .overlay-panel h2 {
            font-size: clamp(1.6rem, 2.9vw, 2.2rem);
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 0.7rem;
            letter-spacing: -0.02em;
            text-shadow: 0 2px 18px rgba(0,0,0,0.28);
        }
        .overlay-panel p {
            font-size: 0.9rem;
            line-height: 1.55;
            opacity: 0.95;
            margin-bottom: 1.45rem;
            max-width: 280px;
        }
        .overlay-left { transform: translateX(-20%); }
        .overlay-right { right: 0; transform: translateX(0); }
        .auth-card.right-panel-active .overlay-left { transform: translateX(0); }
        .auth-card.right-panel-active .overlay-right { transform: translateX(20%); }

        .btn-ghost {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.78rem 1.7rem;
            border: 1.5px solid rgba(255,255,255,0.85);
            border-radius: 999px;
            color: #fff; font-weight: 700; font-size: 0.9rem;
            text-decoration: none;
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(8px);
            cursor: pointer;
            transition: background 0.2s, color 0.2s, transform 0.15s, box-shadow 0.2s;
            font-family: inherit;
        }
        .btn-ghost:hover {
            background: #fff; color: var(--indigo-deep);
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(0,0,0,0.18);
        }

        /* Form chrome */
        .form-side-head { margin-bottom: 0.15rem; }
        .form-side-head__title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.2rem;
        }
        .form-side-head__logo-link {
            display: inline-flex;
            flex-shrink: 0;
            border-radius: 12px;
            line-height: 0;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .form-side-head__logo-link:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 20px rgba(46, 35, 108, 0.2);
        }
        .form-side-head__logo {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            object-fit: cover;
            display: block;
            box-shadow: 0 4px 14px rgba(46, 35, 108, 0.16);
        }
        .form-side-head h1 {
            font-family: 'Transcity', Georgia, 'Times New Roman', serif;
            font-size: clamp(1.65rem, 2.8vw, 2.15rem);
            font-weight: 400;
            letter-spacing: 0.04em;
            margin: 0;
            color: var(--muted);
            text-transform: uppercase;
        }
        .step-hint { font-size: 0.8rem; color: var(--muted); margin-bottom: 0.7rem; font-weight: 500; }
        .step-dots { display: flex; gap: 0.4rem; margin-bottom: 0.85rem; }
        .step-dots span {
            width: 8px; height: 8px; border-radius: 999px; background: #d8deea;
            transition: width 0.25s, background 0.25s, box-shadow 0.25s;
        }
        .step-dots span.is-active {
            width: 24px;
            background: linear-gradient(90deg, var(--indigo), #7c8ff0);
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.35);
        }

        .alert {
            display: none; font-size: 0.78rem; padding: 0.6rem 0.75rem;
            border-radius: 12px; margin-bottom: 0.65rem; line-height: 1.35;
        }
        .alert.show { display: block; }
        .alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .field-control.is-error input,
        .field-control.is-error select {
            border-color: #fca5a5;
            background: #fff7f7;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.12);
        }
        .field-control.is-error > i:first-child { color: #dc2626; }
        .field-error-msg {
            display: none;
            font-size: 0.75rem;
            color: #b91c1c;
            margin-top: 0.35rem;
            font-weight: 600;
        }
        .field-error-msg.show { display: block; }
        .alert-success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }

        .reg-form, .login-form {
            flex: 1; display: flex; flex-direction: column; min-height: 0;
        }
        .login-form { justify-content: flex-start; gap: 0.15rem; padding-top: 0.35rem; }
        .step-panel {
            display: none; flex-direction: column; gap: 0.7rem; flex: 1; min-height: 0;
            animation: stepFade 0.28s ease;
        }
        .step-panel.is-active { display: flex; }
        @keyframes stepFade {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: none; }
        }

        .field label {
            display: block; font-size: 0.7rem; font-weight: 700;
            color: #475569; margin-bottom: 0.32rem; letter-spacing: 0.03em; text-transform: uppercase;
        }
        .field-control {
            position: relative;
            display: flex;
            align-items: center;
        }
        .field-control > i.fa-solid,
        .field-control > i.fas {
            position: absolute;
            left: 0.9rem;
            color: #94a3b8;
            font-size: 0.85rem;
            pointer-events: none;
            z-index: 1;
            transition: color 0.15s;
        }
        .field input, .field select {
            width: 100%; height: 46px; border: 1px solid var(--field-border); outline: none;
            border-radius: 14px; background: var(--field);
            padding: 0 0.95rem 0 2.45rem; font: inherit; font-size: 0.92rem; color: var(--text);
            transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
        }
        .field input::placeholder { color: #94a3b8; }
        .field input:hover, .field select:hover { border-color: #c9d2e5; background: #f8f9fd; }
        .field input:focus, .field select:focus {
            border-color: var(--indigo);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.14);
        }
        .field-control:focus-within > i { color: var(--indigo); }
        .field select { appearance: none; cursor: pointer; }
        .field-control .select-chevron {
            position: absolute; right: 0.9rem; left: auto; pointer-events: none; font-size: 0.7rem;
        }
        .field-wrap { position: relative; width: 100%; }
        .field-wrap .eye-btn {
            position: absolute; right: 0.55rem; top: 50%; transform: translateY(-50%);
            width: 34px; height: 34px; border-radius: 10px;
            border: none; background: transparent; color: #94a3b8; cursor: pointer;
            display: grid; place-items: center;
            transition: color 0.15s, background 0.15s;
        }
        .field-wrap .eye-btn:hover { color: var(--indigo); background: var(--indigo-soft); }
        .field-wrap input { padding-left: 2.45rem; padding-right: 2.7rem; }

        /* Password strength meter */
        .pw-strength {
            margin-top: 0.45rem;
        }
        .pw-strength__row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.72rem;
            margin-bottom: 0.3rem;
        }
        .pw-strength__label { color: var(--muted); font-weight: 600; }
        .pw-strength__level {
            font-weight: 800;
            color: #94a3b8;
            transition: color 0.2s;
        }
        .pw-strength__level.is-weak { color: #ef4444; }
        .pw-strength__level.is-medium { color: #eab308; }
        .pw-strength__level.is-strong { color: #22c55e; }
        .pw-strength__bar {
            height: 6px;
            border-radius: 999px;
            background: #e8edf5;
            overflow: hidden;
            margin-bottom: 0.55rem;
        }
        .pw-strength__fill {
            height: 100%;
            width: 0%;
            border-radius: 999px;
            background: #cbd5e1;
            transition: width 0.25s ease, background 0.25s ease;
        }
        .pw-strength__fill.is-weak { background: #ef4444; }
        .pw-strength__fill.is-medium { background: #eab308; }
        .pw-strength__fill.is-strong { background: #22c55e; }
        .pw-rules {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem 0.65rem;
            justify-content: space-between;
        }
        .pw-rule {
            display: inline-flex;
            align-items: center;
            gap: 0.28rem;
            font-size: 0.68rem;
            font-weight: 700;
            color: #ef4444;
            transition: color 0.2s;
        }
        .pw-rule i {
            width: 14px; height: 14px; border-radius: 50%;
            border: 1.5px solid #ef4444;
            display: inline-grid; place-items: center;
            font-size: 0.48rem; color: transparent;
            transition: background 0.2s, border-color 0.2s, color 0.2s;
        }
        .pw-rule.is-met { color: #16a34a; }
        .pw-rule.is-met i {
            background: #22c55e;
            border-color: #22c55e;
            color: #fff;
        }

        .terms-row, .login-options {
            display: flex; align-items: center; gap: 0.5rem;
            font-size: 0.78rem; color: var(--muted); line-height: 1.4;
        }
        .terms-row { align-items: flex-start; margin-top: 0.15rem; }
        .terms-row input, .login-options input { accent-color: var(--indigo); width: 15px; height: 15px; }
        .terms-row a, .login-options a { color: var(--indigo); font-weight: 700; text-decoration: none; }
        .terms-row a:hover, .login-options a:hover { text-decoration: underline; }
        .login-options { justify-content: space-between; margin: 0.35rem 0 0.5rem; }
        .login-options label { display: inline-flex; align-items: center; gap: 0.45rem; cursor: pointer; font-weight: 600; }

        .form-nav {
            display: flex; align-items: center; gap: 0.7rem;
            margin-top: auto; padding-top: 0.85rem;
        }
        .btn-back {
            width: 46px; height: 46px; border-radius: 14px;
            border: 1px solid var(--field-border); background: var(--white);
            color: var(--text); cursor: pointer; display: grid; place-items: center;
            font-size: 1rem; flex-shrink: 0;
            transition: border-color 0.15s, color 0.15s, background 0.15s, transform 0.15s;
        }
        .btn-back:hover {
            border-color: var(--indigo); color: var(--indigo);
            background: var(--indigo-soft); transform: translateY(-1px);
        }
        .btn-primary, .btn-login-submit {
            border: none;
            background: linear-gradient(135deg, #7388f0 0%, var(--indigo) 55%, #5a6fd8 100%);
            color: #fff; font-weight: 800; font-size: 0.95rem;
            cursor: pointer; font-family: inherit;
            box-shadow: 0 10px 22px rgba(102, 126, 234, 0.32);
            transition: transform 0.15s, filter 0.15s, box-shadow 0.15s;
        }
        .btn-primary {
            flex: 1; height: 46px; border-radius: 14px;
        }
        .btn-login-submit {
            width: 100%; height: 48px; border-radius: 14px; margin-top: auto;
            letter-spacing: 0.01em;
        }
        .btn-primary:hover, .btn-login-submit:hover {
            filter: brightness(1.04);
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(102, 126, 234, 0.4);
        }
        .btn-primary:active, .btn-login-submit:active { transform: translateY(0); }

        .login-form .field { margin-bottom: 0.85rem; }
        .login-form .form-side-head { margin-bottom: 1.15rem; }
        .login-spacer { flex: 1; min-height: 0.75rem; }

        /* Modals */
        .terms-modal, .success-popup {
            display: none; position: fixed; inset: 0; z-index: 2000;
            background: rgba(15, 23, 42, 0.55);
            align-items: center; justify-content: center; padding: 1rem;
        }
        .terms-modal.show, .success-popup.show { display: flex; }
        .terms-modal-content {
            background: #fff; border-radius: 16px; width: min(640px, 100%);
            max-height: 85vh; display: flex; flex-direction: column; overflow: hidden;
        }
        .terms-modal-header {
            background: linear-gradient(90deg, var(--indigo), #5b5ce2);
            color: #fff; padding: 1rem 1.25rem;
            display: flex; justify-content: space-between; align-items: center;
        }
        .terms-modal-header h2 { font-size: 1.1rem; }
        .terms-modal-close {
            width: 34px; height: 34px; border: none; border-radius: 50%;
            background: rgba(255,255,255,0.2); color: #fff; font-size: 1.3rem; cursor: pointer;
        }
        .terms-modal-body { padding: 1.25rem; overflow-y: auto; font-size: 0.88rem; color: #475569; line-height: 1.65; }
        .terms-modal-body h3 { color: var(--indigo-deep); margin: 1rem 0 0.4rem; font-size: 0.95rem; }
        .terms-modal-body h3:first-child { margin-top: 0; }
        .terms-modal-body ul { padding-left: 1.1rem; margin-bottom: 0.75rem; }
        .terms-modal-footer {
            padding: 0.9rem 1.25rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end;
        }
        .terms-modal-btn {
            border: none; border-radius: 10px; padding: 0.65rem 1.2rem;
            background: var(--indigo); color: #fff; font-weight: 700; cursor: pointer;
        }
        .success-popup-content {
            background: #fff; border-radius: 18px; padding: 2rem 1.75rem; text-align: center;
            width: min(380px, 100%); box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }
        .success-popup-img {
            width: 110px;
            height: auto;
            max-height: 110px;
            object-fit: contain;
            display: block;
            margin: 0 auto 0.85rem;
        }
        .success-popup-content h2 { color: var(--indigo); font-size: 1.2rem; margin-bottom: 0.5rem; }
        .success-popup-content p { color: var(--muted); font-size: 0.92rem; }
        .loading-icon {
            width: 34px; height: 34px; margin: 1rem auto 0; border-radius: 50%;
            border: 3px solid rgba(102,126,234,0.2); border-top-color: var(--indigo);
            animation: spin 0.9s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 820px) {
            body { overflow: auto; }
            .page { height: auto; min-height: 100dvh; }
            .auth-card {
                height: auto;
                max-height: none;
                min-height: min(700px, calc(100dvh - 1.5rem));
            }
            .form-container {
                width: 100%;
                position: relative;
                height: auto;
                min-height: 420px;
                opacity: 1 !important;
                pointer-events: auto !important;
                transform: none !important;
            }
            .sign-in-container { display: none; }
            .auth-card.right-panel-active .sign-up-container { display: none; }
            .auth-card.right-panel-active .sign-in-container { display: flex; }
            .overlay-container {
                position: relative;
                left: 0; width: 100%; height: 170px;
                order: -1;
                transform: none !important;
            }
            .auth-card { display: flex; flex-direction: column; }
            .overlay { left: 0; width: 100%; transform: none !important; }
            .overlay-panel {
                width: 100%; padding: 1.25rem;
                transform: none !important;
            }
            .overlay-left { display: none; }
            .auth-card.right-panel-active .overlay-left { display: flex; }
            .auth-card.right-panel-active .overlay-right { display: none; }
            .overlay-right { display: flex; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="auth-card<?php echo $show_login ? ' right-panel-active' : ''; ?>" id="authCard">

            <!-- Register (left / default) -->
            <div class="form-container sign-up-container">
                <div class="form-side-head">
                    <div class="form-side-head__title">
                        <a href="index.php" class="form-side-head__logo-link" title="Go to Home" aria-label="Go to Home">
                            <img src="assets/imgs/logo.jpeg" alt="Daet Public Market" class="form-side-head__logo">
                        </a>
                        <h1 id="stepTitle">Register here</h1>
                    </div>
                    <p class="step-hint" id="stepHint">Step 1 of 3 — name, email &amp; phone</p>
                    <div class="step-dots" aria-hidden="true">
                        <span class="is-active" data-dot="1"></span>
                        <span data-dot="2"></span>
                        <span data-dot="3"></span>
                    </div>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error show"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <form class="reg-form" method="POST" action="registrationform.php" id="registrationForm" novalidate>
                    <input type="hidden" name="auth_register" value="1">

                    <div class="step-panel is-active" data-step="1">
                        <div class="field">
                            <label for="fullname">Full Name</label>
                            <div class="field-control">
                                <i class="fas fa-user"></i>
                                <input type="text" id="fullname" name="fullname" placeholder="Your full name" value="<?php echo $pv('fullname'); ?>" required autocomplete="name">
                            </div>
                        </div>
                        <div class="field">
                            <label for="email">Email</label>
                            <div class="field-control<?php echo $highlight_email ? ' is-error' : ''; ?>" id="emailFieldControl">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="email" name="email" placeholder="example@gmail.com" value="<?php echo $pv('email'); ?>" required autocomplete="email" <?php echo $highlight_email ? 'aria-invalid="true"' : ''; ?>>
                            </div>
                            <div class="field-error-msg<?php echo $highlight_email ? ' show' : ''; ?>" id="emailErrorMsg"><?php echo $highlight_email ? htmlspecialchars($error_message) : ''; ?></div>
                        </div>
                        <div class="field">
                            <label for="phone_number">Phone Number</label>
                            <div class="field-control">
                                <i class="fas fa-phone"></i>
                                <input type="tel" id="phone_number" name="phone_number" placeholder="09*********" value="<?php echo $pv('phone_number'); ?>" required inputmode="numeric" autocomplete="tel" maxlength="11" pattern="[0-9]{11}" title="Enter exactly 11 digits (e.g. 09123456789)">
                            </div>
                        </div>
                    </div>

                    <div class="step-panel" data-step="2">
                        <div class="field">
                            <label for="age">Age</label>
                            <div class="field-control">
                                <i class="fas fa-calendar"></i>
                                <input type="number" id="age" name="age" placeholder="e.g. 25" min="15" max="120" value="<?php echo $pv('age'); ?>" required>
                            </div>
                        </div>
                        <div class="field">
                            <label for="gender">Gender</label>
                            <div class="field-control">
                                <i class="fas fa-venus-mars"></i>
                                <select id="gender" name="gender" required>
                                    <option value="" disabled <?php echo empty($_POST['gender']) ? 'selected' : ''; ?>>Select gender</option>
                                    <?php
                                    $gender_options = ['Male', 'Female', 'Prefer not to say'];
                                    $posted_gender = $_POST['gender'] ?? '';
                                    foreach ($gender_options as $g) {
                                        $sel = ($posted_gender === $g) ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($g) . '" ' . $sel . '>' . htmlspecialchars($g) . '</option>';
                                    }
                                    ?>
                                </select>
                                <i class="fas fa-chevron-down select-chevron"></i>
                            </div>
                        </div>
                        <div class="field">
                            <label for="residential_address">Residential Address</label>
                            <div class="field-control">
                                <i class="fas fa-location-dot"></i>
                                <input type="text" id="residential_address" name="residential_address" placeholder="Your full residential address" value="<?php echo $pv('residential_address'); ?>" required autocomplete="street-address">
                            </div>
                        </div>
                    </div>

                    <div class="step-panel" data-step="3">
                        <div class="field">
                            <label for="password">Password</label>
                            <div class="field-control">
                                <i class="fas fa-lock"></i>
                                <div class="field-wrap">
                                    <input type="password" id="password" name="password" placeholder="Min. 8 characters" required autocomplete="new-password">
                                    <button type="button" class="eye-btn" data-eye="password" aria-label="Show password"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                            <div class="pw-strength" id="pwStrength" aria-live="polite">
                                <div class="pw-strength__row">
                                    <span class="pw-strength__label">Password Strength</span>
                                    <span class="pw-strength__level" id="pwStrengthLevel">—</span>
                                </div>
                                <div class="pw-strength__bar">
                                    <div class="pw-strength__fill" id="pwStrengthFill"></div>
                                </div>
                                <div class="pw-rules">
                                    <span class="pw-rule" data-rule="len"><i class="fas fa-check"></i> 8 Chars</span>
                                    <span class="pw-rule" data-rule="upper"><i class="fas fa-check"></i> A-Z</span>
                                    <span class="pw-rule" data-rule="lower"><i class="fas fa-check"></i> a-z</span>
                                    <span class="pw-rule" data-rule="num"><i class="fas fa-check"></i> 123</span>
                                </div>
                            </div>
                        </div>
                        <div class="field">
                            <label for="confirm_password">Confirm Password</label>
                            <div class="field-control">
                                <i class="fas fa-lock"></i>
                                <div class="field-wrap">
                                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required autocomplete="new-password">
                                    <button type="button" class="eye-btn" data-eye="confirm_password" aria-label="Show password"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="terms-row">
                            <input type="checkbox" id="terms_accepted" name="terms_accepted" required>
                            <label for="terms_accepted">I agree to the <a href="#" id="openTermsLink">Terms &amp; Conditions</a></label>
                        </div>
                    </div>

                    <div class="form-nav">
                        <button type="button" class="btn-back" id="btnBack" aria-label="Back" title="Back"><i class="fas fa-chevron-left"></i></button>
                        <button type="button" class="btn-primary" id="btnNext">Next</button>
                        <button type="submit" class="btn-primary" id="btnRegister" style="display:none;">Register</button>
                    </div>
                </form>
            </div>

            <!-- Login (slides in on the right) -->
            <div class="form-container sign-in-container">
                <div class="form-side-head">
                    <div class="form-side-head__title">
                        <a href="index.php" class="form-side-head__logo-link" title="Go to Home" aria-label="Go to Home">
                            <img src="assets/imgs/logo.jpeg" alt="Daet Public Market" class="form-side-head__logo">
                        </a>
                        <h1>Login here</h1>
                    </div>
                    <p class="step-hint">Welcome back — sign in to continue</p>
                </div>

                <?php if (!empty($login_error)): ?>
                    <div class="alert alert-error show"><?php echo htmlspecialchars($login_error); ?></div>
                <?php endif; ?>

                <form class="login-form" method="POST" action="registrationform.php" id="loginForm" autocomplete="on">
                    <input type="hidden" name="auth_login" value="1">
                    <div class="field">
                        <label for="login_email">Email</label>
                        <div class="field-control">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="login_email" name="login_email" placeholder="Enter your email" required autocomplete="email"
                                   value="<?php echo isset($_POST['login_email']) ? htmlspecialchars((string)$_POST['login_email'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                        </div>
                    </div>
                    <div class="field">
                        <label for="login_password">Password</label>
                        <div class="field-control">
                            <i class="fas fa-lock"></i>
                            <div class="field-wrap">
                                <input type="password" id="login_password" name="login_password" placeholder="Enter your password" required autocomplete="current-password">
                                <button type="button" class="eye-btn" data-eye="login_password" aria-label="Show password"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="login-options">
                        <label><input type="checkbox" name="remember_me" value="1"> Remember me</label>
                        <a href="forgot_password.php">Forgot password?</a>
                    </div>
                    <div class="login-spacer" aria-hidden="true"></div>
                    <button type="submit" class="btn-login-submit">Login</button>
                </form>
            </div>

            <!-- Sliding overlay with marketplace.gif -->
            <div class="overlay-container">
                <div class="overlay">
                    <div class="overlay__bg" aria-hidden="true"></div>
                    <div class="overlay__shade" aria-hidden="true"></div>

                    <!-- Shown when login form is active (panel on LEFT) -->
                    <div class="overlay-panel overlay-left">
                        <h2>Start your journey now</h2>
                        <p>If you don't have an account yet, join us and start your journey.</p>
                        <button type="button" class="btn-ghost" id="showRegisterBtn">Register <i class="fas fa-arrow-right"></i></button>
                    </div>

                    <!-- Shown when register form is active (panel on RIGHT) -->
                    <div class="overlay-panel overlay-right">
                        <h2>Welcome to MCS</h2>
                        <p>If you already have an account, login here and continue your market clearance journey.</p>
                        <button type="button" class="btn-ghost" id="showLoginBtn">Login <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms modal -->
    <div id="termsModal" class="terms-modal" onclick="if(event.target===this)closeTermsModal()">
        <div class="terms-modal-content">
            <div class="terms-modal-header">
                <h2>Terms &amp; Conditions</h2>
                <button type="button" class="terms-modal-close" onclick="closeTermsModal()" aria-label="Close">&times;</button>
            </div>
            <div class="terms-modal-body">
                <h3>1. Acceptance of Terms</h3>
                <p>By accessing and using the Daet Public Market Office - Market Clearance System(MCS), you accept and agree to be bound by the terms and provision of this agreement.</p>
                <h3>2. Account Registration</h3>
                <p>You agree to provide accurate information, maintain password security, and accept responsibility for activity under your account.</p>
                <h3>3. Vendor Responsibilities</h3>
                <ul>
                    <li>Submit accurate information and authentic documents</li>
                    <li>Comply with local market regulations</li>
                    <li>Keep account credentials confidential</li>
                </ul>
                <h3>4. Application Process</h3>
                <p>All applications are subject to DPMO review. False information may result in rejection or account suspension.</p>
                <h3>5. Data Privacy</h3>
                <p>Personal information is used for market clearance purposes and stored securely in accordance with data protection laws.</p>
                <h3>6. Contact</h3>
                <p>For questions, contact the Daet Public Market Office through official channels.</p>
            </div>
            <div class="terms-modal-footer">
                <button type="button" class="terms-modal-btn" onclick="closeTermsModal()">I Understand</button>
            </div>
        </div>
    </div>

    <div id="successPopup" class="success-popup">
        <div class="success-popup-content">
            <img src="assets/imgs/registerthumbsup.jpg" alt="Registration successful" class="success-popup-img">
            <h2>Successfully Created an Account</h2>
            <p>Redirecting to home page...</p>
            <div class="loading-icon"></div>
        </div>
    </div>

    <script>
    (function () {
        var card = document.getElementById('authCard');
        var showLoginBtn = document.getElementById('showLoginBtn');
        var showRegisterBtn = document.getElementById('showRegisterBtn');

        function showLogin() { if (card) card.classList.add('right-panel-active'); }
        function showRegister() { if (card) card.classList.remove('right-panel-active'); }

        if (showLoginBtn) showLoginBtn.addEventListener('click', showLogin);
        if (showRegisterBtn) showRegisterBtn.addEventListener('click', showRegister);

        // ——— Multi-step register ———
        var step = 1;
        var total = 3;
        var form = document.getElementById('registrationForm');
        var panels = Array.prototype.slice.call(document.querySelectorAll('.step-panel'));
        var dots = Array.prototype.slice.call(document.querySelectorAll('.step-dots span'));
        var title = document.getElementById('stepTitle');
        var hint = document.getElementById('stepHint');
        var btnBack = document.getElementById('btnBack');
        var btnNext = document.getElementById('btnNext');
        var btnRegister = document.getElementById('btnRegister');

        var titles = { 1: 'Register here', 2: 'Almost there', 3: 'Secure your account' };
        var hints = {
            1: 'Step 1 of 3 — name, email & phone',
            2: 'Step 2 of 3 — age, gender & address',
            3: 'Step 3 of 3 — create your password'
        };

        function showStep(n) {
            step = n;
            window.__regCurrentStep = step;
            panels.forEach(function (p) {
                p.classList.toggle('is-active', parseInt(p.getAttribute('data-step'), 10) === step);
            });
            dots.forEach(function (d) {
                d.classList.toggle('is-active', parseInt(d.getAttribute('data-dot'), 10) === step);
            });
            if (typeof window.regApplyStepCopy === 'function') {
                window.regApplyStepCopy(step);
            } else {
                if (title) title.textContent = titles[step] || 'Register here';
                if (hint) hint.textContent = hints[step] || '';
            }
            if (step < total) {
                btnNext.style.display = '';
                btnRegister.style.display = 'none';
            } else {
                btnNext.style.display = 'none';
                btnRegister.style.display = '';
            }
        }

        function stepFields(n) {
            var panel = document.querySelector('.step-panel[data-step="' + n + '"]');
            if (!panel) return [];
            return Array.prototype.slice.call(panel.querySelectorAll('input, select, textarea'));
        }

        function validateStep(n) {
            var fields = stepFields(n);
            for (var i = 0; i < fields.length; i++) {
                var el = fields[i];
                if (el.type === 'checkbox') {
                    if (!el.checked) { el.reportValidity(); return false; }
                    continue;
                }
                if (!el.checkValidity()) { el.reportValidity(); return false; }
            }
            if (n === 1) {
                var phone = document.getElementById('phone_number');
                if (phone) {
                    var digits = String(phone.value || '').replace(/\D+/g, '');
                    phone.value = digits;
                    if (!/^\d{11}$/.test(digits)) {
                        phone.setCustomValidity('Phone number must be exactly 11 digits.');
                        phone.reportValidity();
                        phone.setCustomValidity('');
                        phone.focus();
                        return false;
                    }
                    phone.setCustomValidity('');
                }
            }
            if (n === 3) {
                var pw = document.getElementById('password');
                var cp = document.getElementById('confirm_password');
                if (pw && cp && pw.value !== cp.value) {
                    cp.setCustomValidity('Passwords do not match.');
                    cp.reportValidity();
                    cp.setCustomValidity('');
                    return false;
                }
            }
            return true;
        }

        function setEmailError(msg) {
            var control = document.getElementById('emailFieldControl');
            var err = document.getElementById('emailErrorMsg');
            var emailInput = document.getElementById('email');
            if (control) control.classList.add('is-error');
            if (err) {
                err.textContent = msg || '';
                err.classList.add('show');
            }
            if (emailInput) {
                emailInput.setAttribute('aria-invalid', 'true');
                emailInput.focus();
                try { emailInput.select(); } catch (e) {}
            }
        }

        function clearEmailError() {
            var control = document.getElementById('emailFieldControl');
            var err = document.getElementById('emailErrorMsg');
            var emailInput = document.getElementById('email');
            if (control) control.classList.remove('is-error');
            if (err) {
                err.textContent = '';
                err.classList.remove('show');
            }
            if (emailInput) emailInput.removeAttribute('aria-invalid');
        }

        function checkEmailAvailable(email) {
            return fetch('registrationform.php?action=check_email&email=' + encodeURIComponent(email), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            }).then(function (res) { return res.json(); });
        }

        if (btnNext) {
            btnNext.addEventListener('click', function () {
                if (!validateStep(step)) return;
                if (step === 1) {
                    var emailInput = document.getElementById('email');
                    var emailVal = emailInput ? String(emailInput.value || '').trim() : '';
                    btnNext.disabled = true;
                    checkEmailAvailable(emailVal).then(function (data) {
                        btnNext.disabled = false;
                        if (data && data.exists) {
                            setEmailError('This email is already registered. Please use a different email or login.');
                            showStep(1);
                            return;
                        }
                        clearEmailError();
                        if (step < total) showStep(step + 1);
                    }).catch(function () {
                        btnNext.disabled = false;
                        // If check fails, still allow next — server will re-check on submit
                        if (step < total) showStep(step + 1);
                    });
                    return;
                }
                if (step < total) showStep(step + 1);
            });
        }

        var emailInputLive = document.getElementById('email');
        if (emailInputLive) {
            emailInputLive.addEventListener('input', clearEmailError);
        }

        var phoneInputLive = document.getElementById('phone_number');
        if (phoneInputLive) {
            phoneInputLive.addEventListener('input', function () {
                var digits = String(phoneInputLive.value || '').replace(/\D+/g, '').slice(0, 11);
                if (phoneInputLive.value !== digits) phoneInputLive.value = digits;
                phoneInputLive.setCustomValidity('');
            });
        }
        if (btnBack) {
            btnBack.addEventListener('click', function () {
                if (step > 1) showStep(step - 1);
                else showLogin();
            });
        }
        if (form) {
            form.addEventListener('submit', function (e) {
                for (var s = 1; s <= total; s++) {
                    if (!validateStep(s)) {
                        e.preventDefault();
                        showStep(s);
                        return;
                    }
                }
            });
        }

        document.querySelectorAll('.eye-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-eye');
                var input = document.getElementById(id);
                var icon = btn.querySelector('i');
                if (!input) return;
                if (input.type === 'password') {
                    input.type = 'text';
                    if (icon) icon.className = 'fas fa-eye-slash';
                } else {
                    input.type = 'password';
                    if (icon) icon.className = 'fas fa-eye';
                }
            });
        });

        // Password strength meter (live)
        (function () {
            var pw = document.getElementById('password');
            var levelEl = document.getElementById('pwStrengthLevel');
            var fillEl = document.getElementById('pwStrengthFill');
            if (!pw || !levelEl || !fillEl) return;

            var rules = {
                len: function (v) { return v.length >= 8; },
                upper: function (v) { return /[A-Z]/.test(v); },
                lower: function (v) { return /[a-z]/.test(v); },
                num: function (v) { return /\d/.test(v); }
            };

            function update() {
                var v = pw.value || '';
                var met = 0;
                Object.keys(rules).forEach(function (key) {
                    var ok = rules[key](v);
                    var el = document.querySelector('.pw-rule[data-rule="' + key + '"]');
                    if (el) el.classList.toggle('is-met', ok);
                    if (ok) met++;
                });

                fillEl.classList.remove('is-weak', 'is-medium', 'is-strong');
                levelEl.classList.remove('is-weak', 'is-medium', 'is-strong');

                if (!v) {
                    levelEl.textContent = (typeof window.regPwStrengthLabel === 'function')
                        ? window.regPwStrengthLabel(0, true) : '—';
                    fillEl.style.width = '0%';
                    return;
                }

                var label = (typeof window.regPwStrengthLabel === 'function')
                    ? window.regPwStrengthLabel(met, false)
                    : (met >= 4 ? 'Strong' : (met >= 2 ? 'Medium' : 'Weak'));
                var cls = 'is-weak';
                var width = '33%';
                if (met >= 4) {
                    cls = 'is-strong';
                    width = '100%';
                } else if (met >= 2) {
                    cls = 'is-medium';
                    width = '66%';
                }

                levelEl.textContent = label;
                levelEl.classList.add(cls);
                fillEl.classList.add(cls);
                fillEl.style.width = width;
            }

            pw.addEventListener('input', update);
            update();
        })();

        document.addEventListener('click', function (e) {
            var t = e.target;
            if (t && t.id === 'openTermsLink') {
                e.preventDefault();
                openTermsModal();
            }
        });

        showStep(<?php echo (int)$reg_start_step; ?>);
        <?php if ($highlight_email): ?>
        setEmailError(<?php echo json_encode($error_message); ?>);
        <?php endif; ?>
    })();

    function openTermsModal() {
        var modal = document.getElementById('termsModal');
        if (modal) { modal.classList.add('show'); document.body.style.overflow = 'hidden'; }
    }
    function closeTermsModal() {
        var modal = document.getElementById('termsModal');
        if (modal) { modal.classList.remove('show'); document.body.style.overflow = ''; }
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeTermsModal();
    });

    <?php if (!empty($success_message) || (isset($_SESSION['registration_success']) && $_SESSION['registration_success'])): ?>
        <?php
          if (isset($_SESSION['registration_success'])) {
            unset($_SESSION['registration_success']);
          }
        ?>
        document.addEventListener('DOMContentLoaded', function () {
            var successPopup = document.getElementById('successPopup');
            if (successPopup) {
                successPopup.classList.add('show');
                setTimeout(function () {
                    window.location.href = 'index.php#user-portal';
                }, 3000);
            }
        });
    <?php endif; ?>
    </script>
    <?php
    $cs_contact_href = 'index.php#contact';
    $cs_announcement_href = 'index.php#announcement';
    include __DIR__ . '/includes/customer_support_widget.php';
    ?>
</body>
</html>
