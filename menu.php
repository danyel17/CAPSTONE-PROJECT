<?php
require_once 'config.php';
require_once 'auth_functions.php';
require_once 'application_fees.php';

// Protect this page - require login
requireLogin();

// Get user information from session
$user_name = $_SESSION['full_name'] ?? 'User';
$user_email = $_SESSION['email'] ?? '';
$user_type = $_SESSION['user_type'] ?? 'vendor';

// First name only for hero / display; first letter uppercase, rest lowercase
$first_name = explode(' ', trim($user_name))[0] ?? 'User';
if ($first_name === '') {
    $first_name = 'User';
}
if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
    $first_name = mb_strtoupper(mb_substr($first_name, 0, 1, 'UTF-8'), 'UTF-8')
        . mb_strtolower(mb_substr($first_name, 1, null, 'UTF-8'), 'UTF-8');
} else {
    $first_name = ucfirst(strtolower($first_name));
}

// Get unread notifications count from database
$unread_notifications_count = getUnreadApprovalNotificationCount($_SESSION['user_id'] ?? null);

// Get video files from database
$conn = getDBConnection();
$promotional_video = null;
$price_video = null;
$announcement_video = null;

if ($conn) {
    // Get promotional permit video (overview_video)
    $stmt = $conn->prepare("SELECT * FROM media_files WHERE file_type = 'video' AND section = 'overview_video' AND is_active = TRUE ORDER BY display_order ASC, uploaded_at DESC LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $promotional_video = $result->fetch_assoc();
        $stmt->close();
    }
    
    // Get price video (price_video)
    $stmt = $conn->prepare("SELECT * FROM media_files WHERE file_type = 'video' AND section = 'price_video' AND is_active = TRUE ORDER BY display_order ASC, uploaded_at DESC LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $price_video = $result->fetch_assoc();
        $stmt->close();
    }
    
    // Get announcement video (third_video)
    $stmt = $conn->prepare("SELECT * FROM media_files WHERE file_type = 'video' AND section = 'third_video' AND is_active = TRUE ORDER BY display_order ASC, uploaded_at DESC LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $announcement_video = $result->fetch_assoc();
        $stmt->close();
    }
}

function getSlotStatus($conn, $blockName, $slotNumber) {
    if (!$conn) {
        return 'available';
    }
    $stmt = $conn->prepare("SELECT status FROM market_slots WHERE block_name = ? AND slot_number = ?");
    if (!$stmt) {
        return 'available';
    }
    $stmt->bind_param("si", $blockName, $slotNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['status'] : 'available';
}

$fee_logo_path = file_exists(__DIR__ . '/assets/imgs/logo.jpg')
    ? 'assets/imgs/logo.jpg'
    : 'assets/imgs/logo.jpeg';

$application_fees = application_fees_get_all($conn);
$fee_new = application_fees_get_by_type($conn, 'new') ?? application_fees_defaults()['new'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Daet Public Market | Home</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Galada&family=Inter:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,700;0,900;1,700;1,900&family=Press+Start+2P&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/menu-landing-hero.css?v=mobile-hero-9">
  <link rel="icon" type="image/jpeg" href="assets/imgs/logo.jpeg">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
  <script type="module" src="https://unpkg.com/@google/model-viewer/dist/model-viewer.min.js"></script>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      background-color: #3f4191;
      min-height: 100vh;
      padding: 0;
      margin: 0;
      font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      overflow-x: hidden;
      position: relative;
      color: #ffffff;
    }

    /* Main Container - Full Width */
    .main-container {
      width: 100%;
      margin: 0;
      padding: 0;
      padding-top: 0;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background: transparent;
      backdrop-filter: none;
      border-radius: 0;
      border: none;
      box-shadow: none;
      position: relative;
      z-index: 1;
      box-sizing: border-box;
    }

    /* Top Navigation Bar */
    .navbar {
      background: #6b63c9;
      backdrop-filter: none;
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
    
    /* Hamburger Menu Button */
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
    
    /* Left: Logo */
    .navbar-left {
      display: flex;
      align-items: center;
      gap: 1.5rem;
    }
    
    .navbar-logo {
      display: flex;
      flex-direction: row;
      align-items: center;
      gap: 0.65rem;
    }
    .navbar-logo .brand__stack {
      display: flex;
      flex-direction: column;
      gap: 0.1rem;
    }
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
      color: #ffffff;
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
    
    /* Main nav links — outside navbar so position:fixed is relative to the viewport */
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

    /* Mobile sidebar shell (hidden on desktop; holds calendar / language / logout) */
    .navbar-center {
      display: none;
    }
    
    /* Right: Calendar, Language, Logout (Desktop) */
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
    
    .nav-pill.active {
      background: rgba(255, 255, 255, 0.38);
      color: #1c1c1e;
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
    
    .nav-icon-pill i {
      font-size: 1rem;
    }
    
    /* Menu Controls: Calendar and Language Selector (Mobile - Inside Sidebar) */
    .navbar-menu-controls {
      display: none;
    }
    
    /* Right: Logout Button (Mobile - Inside Sidebar) */
    .navbar-right {
      display: none;
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
    
    /* Calendar Icon */
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
    
    /* Language Selector */
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
    
    /* Notification Badge */
    .nav-icon-badge {
      position: absolute;
      top: -5px;
      right: -2px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 20px;
      height: 20px;
      padding: 0 6px;
      border-radius: 999px;
      background-color: #ef4444;
      color: #fff;
      font-size: 11px;
      box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.3);
      font-weight: 700;
    }

    /* ============================================
       HERO SECTION - NEW DESIGN
       ============================================ */

    /* Entrance Animations */
    @keyframes fadeInDown {
      from { opacity: 0; transform: translateY(-20px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(30px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeInRight {
      from { opacity: 0; transform: translateX(50px); }
      to   { opacity: 1; transform: translateX(0); }
    }
    @keyframes fadeInLeft {
      from { opacity: 0; transform: translateX(-50px); }
      to   { opacity: 1; transform: translateX(0); }
    }
    @keyframes fadeInScale {
      from { opacity: 0; transform: scale(0.8); }
      to   { opacity: 1; transform: scale(1); }
    }
    @keyframes fadeInRotate {
      from { opacity: 0; transform: translateY(30px) rotate(-5deg); }
      to   { opacity: 1; transform: translateY(0) rotate(0deg); }
    }
    @keyframes scrollDot {
      0%, 100% { transform: translateY(0); opacity: 1; }
      60%       { transform: translateY(10px); opacity: 0.2; }
    }

    /* Hero Section */
    .hero-section {
      position: relative;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 4rem;
      align-items: center;
      flex: 1;
      padding: 2rem 2rem 5rem;
      margin-bottom: 0;
      max-width: 1400px;
      width: 100%;
      margin-left: auto;
      margin-right: auto;
    }

    /* Left Text Block */
    .hero-text {
      display: flex;
      flex-direction: column;
      gap: 0;
    }

    /* Welcome Badge */
    .hero-welcome-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: rgba(255, 255, 255, 0.15);
      border: 1px solid rgba(255, 255, 255, 0.3);
      border-radius: 50px;
      padding: 0.4rem 1rem;
      font-size: 0.9rem;
      font-weight: 500;
      color: #ffffff;
      width: fit-content;
      margin-bottom: 1.8rem;
      backdrop-filter: blur(8px);
      opacity: 0;
      animation: fadeInDown 0.7s ease-out 0.1s forwards;
    }

    .hero-welcome-badge .badge-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #ff6b35;
      flex-shrink: 0;
      box-shadow: 0 0 8px #ff6b35;
    }

    /* Hero Title */
    .hero-title {
      font-family: 'Playfair Display', Georgia, 'Times New Roman', serif;
      color: #ffffff;
      font-size: clamp(3rem, 5.5vw, 5.5rem);
      font-weight: 900;
      line-height: 1.05;
      letter-spacing: -2px;
      margin-bottom: 1.5rem;
      opacity: 0;
      animation: fadeInUp 0.9s ease-out 0.25s forwards;
    }

    .hero-title .accent {
      color: #ff8555;
      font-style: italic;
    }

    /* Hero Description */
    .hero-description {
      color: rgba(255, 255, 255, 0.85);
      font-size: 1.1rem;
      line-height: 1.7;
      max-width: 480px;
      font-weight: 400;
      margin-bottom: 2.5rem;
      opacity: 0;
      animation: fadeInUp 0.9s ease-out 0.4s forwards;
    }

    /* Hero Buttons */
    .hero-buttons {
      display: flex;
      gap: 1rem;
      align-items: center;
      flex-wrap: wrap;
      margin-top: 0;
      opacity: 0;
      animation: fadeInUp 0.9s ease-out 0.55s forwards;
    }

    /* Primary Button - Apply Now */
    .btn-hero-primary {
      display: inline-flex;
      align-items: center;
      gap: 0.6rem;
      padding: 0.95rem 2.2rem;
      background: #ff6b35;
      color: #ffffff;
      border: none;
      border-radius: 50px;
      font-weight: 700;
      font-size: 1rem;
      cursor: pointer;
      text-decoration: none;
      box-shadow: 0 8px 24px rgba(255, 107, 53, 0.4);
      transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
    }
    .btn-hero-primary::before {
      content: '';
      position: absolute;
      top: 50%; left: 50%;
      width: 0; height: 0;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.25);
      transform: translate(-50%, -50%);
      transition: width 0.6s, height 0.6s;
    }
    .btn-hero-primary:hover::before {
      width: 400px;
      height: 400px;
    }
    .btn-hero-primary:hover {
      background: #ff8555;
      transform: translateY(-3px) scale(1.04);
      box-shadow: 0 16px 40px rgba(255, 107, 53, 0.55);
      color: #ffffff;
    }
    .btn-hero-primary .btn-arrow {
      font-size: 1.1rem;
      transition: transform 0.3s ease;
      display: inline-block;
    }
    .btn-hero-primary:hover .btn-arrow {
      transform: translateX(4px);
    }

    /* Secondary Button - Learn More */
    .btn-hero-secondary {
      display: inline-flex;
      align-items: center;
      gap: 0.6rem;
      padding: 0.95rem 2.2rem;
      background: transparent;
      color: #ffffff;
      border: 2px solid rgba(255, 255, 255, 0.5);
      border-radius: 50px;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.35s ease;
      backdrop-filter: blur(4px);
    }
    .btn-hero-secondary:hover {
      background: rgba(255, 255, 255, 0.15);
      border-color: rgba(255, 255, 255, 0.8);
      transform: translateY(-3px);
      color: #ffffff;
    }

    /* Scroll Indicator — centered at bottom of hero */
    .scroll-indicator {
      position: absolute;
      left: 0;
      right: 0;
      bottom: 1.25rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.4rem;
      margin-top: 0;
      opacity: 0;
      animation: fadeInUp 1s ease-out 1s forwards;
      color: rgba(255, 255, 255, 0.6);
      font-size: 0.7rem;
      letter-spacing: 3px;
      text-transform: uppercase;
      cursor: pointer;
      z-index: 3;
    }
    .scroll-mouse {
      width: 26px;
      height: 40px;
      border: 2px solid rgba(255, 255, 255, 0.4);
      border-radius: 13px;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding-top: 6px;
    }
    .scroll-mouse-dot {
      width: 4px;
      height: 8px;
      background: rgba(255, 255, 255, 0.7);
      border-radius: 2px;
      animation: scrollDot 1.8s ease-in-out infinite;
    }

    /* Right Visual — overlapping photo cluster + oval lines */
    .hero-visual {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 520px;
      opacity: 0;
      animation: fadeInRight 1.2s ease-out 0.3s forwards;
    }

    /* Decorative oval outlines — concentric rings, centered */
    .hero-rings {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      pointer-events: none;
    }
    .hero-oval {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      border-radius: 50%;
      border: 1px solid rgba(255, 255, 255, 0.18);
      pointer-events: none;
    }
    .hero-oval-1 { width: 520px; height: 520px; }
    .hero-oval-2 { width: 430px; height: 430px; border-color: rgba(255, 255, 255, 0.14); }
    .hero-oval-3 { width: 340px; height: 340px; border-color: rgba(255, 255, 255, 0.1); }

    /* Three overlapping circular photos */
    .hero-photo-cluster {
      position: relative;
      z-index: 2;
      width: 390px;
      height: 390px;
      filter: drop-shadow(0 24px 48px rgba(0, 0, 0, 0.22));
    }
    .hero-photo {
      position: absolute;
      border-radius: 50%;
      overflow: hidden;
      border: 3px solid rgba(255, 255, 255, 0.38);
      box-shadow: 0 10px 28px rgba(0, 0, 0, 0.2);
      background: #fff;
      cursor: pointer;
      transition:
        transform 0.45s cubic-bezier(0.4, 0, 0.2, 1),
        box-shadow 0.45s ease,
        border-color 0.45s ease;
    }
    .hero-photo img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center;
      display: block;
      transform: scale(1);
      transition: transform 0.45s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .hero-photo:hover {
      box-shadow: 0 18px 40px rgba(0, 0, 0, 0.28);
      border-color: rgba(255, 255, 255, 0.62);
      z-index: 5;
    }
    .hero-photo:hover img {
      transform: scale(1.08);
    }
    .hero-photo--one {
      width: 158px;
      height: 158px;
      left: 6px;
      top: 118px;
      z-index: 1;
    }
    .hero-photo--one:hover {
      transform: scale(1.06);
    }
    .hero-photo--two {
      width: 150px;
      height: 150px;
      right: 18px;
      top: 14px;
      z-index: 2;
    }
    .hero-photo--two:hover {
      transform: scale(1.06);
    }
    .hero-photo--three {
      width: 214px;
      height: 214px;
      left: 50%;
      top: 50%;
      transform: translate(-40%, -36%);
      z-index: 3;
    }
    .hero-photo--three:hover {
      transform: translate(-40%, -36%) scale(1.06);
    }

    /* ============================================
       FEATURES SECTION
       ============================================ */
    .features-section-wrapper {
      background: radial-gradient(circle at center, #6b63c9 0%, #3f4191 50%, #2e236c 100%);
      margin-left: 0;
      margin-right: 0;
      width: 100%;
      padding: 4rem 2rem 3rem;
      position: relative;
      overflow: hidden;
    }

    .features-leaves-container {
      position: absolute;
      inset: 0;
      pointer-events: none;
      z-index: 0;
    }

    .features-leaves-container .leaf {
      position: absolute;
      width: 70px;
      height: 70px;
      filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.2));
      pointer-events: none;
    }

    .features-leaves-container .fl1 { top: 8%; left: 6%; width: 100px; height: 100px; opacity: 0.4; }
    .features-leaves-container .fl2 { top: 12%; right: 8%; left: auto; width: 130px; height: 130px; opacity: 0.32; }
    .features-leaves-container .fl3 { bottom: 10%; left: 12%; top: auto; width: 90px; height: 90px; opacity: 0.38; }
    .features-leaves-container .fl4 { bottom: 14%; right: 10%; left: auto; top: auto; width: 115px; height: 115px; opacity: 0.3; }
    .features-leaves-container .fl5 { top: 45%; left: 3%; width: 80px; height: 80px; opacity: 0.35; }
    .features-leaves-container .fl6 { top: 40%; right: 4%; left: auto; width: 105px; height: 105px; opacity: 0.36; }

    .features-section-wrapper .features-section {
      position: relative;
      z-index: 1;
    }

    .features-section {
      max-width: 1400px;
      width: 100%;
      margin: 0 auto;
    }

    .features-heading {
      margin-bottom: 2.5rem;
    }

    .features-heading h2 {
      font-family: 'Playfair Display', Georgia, serif;
      font-size: clamp(2rem, 4vw, 3rem);
      font-weight: 900;
      color: #ffffff;
      line-height: 1.15;
      margin: 0;
    }

    .features-detail {
      margin: 0.65rem 0 0;
      font-family: 'Inter', 'Segoe UI', sans-serif;
      font-size: 0.88rem;
      font-weight: 400;
      font-style: normal;
      color: rgba(255, 255, 255, 0.82);
      line-height: 1.55;
      max-width: 32rem;
    }

    /* The accordion-style card strip */
    .features-cards {
      display: flex;
      gap: 12px;
      height: 420px;
      align-items: stretch;
    }

    .feature-card {
      position: relative;
      border-radius: 20px;
      overflow: hidden;
      cursor: pointer;
      flex: 1;
      min-width: 0;
      flex-shrink: 0;
      transition: flex 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Expanded card — smaller than before so the other 3 stay visible */
    .feature-card.is-expanded {
      flex: 2.2;
    }

    /* Background image */
    .feature-card-img {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center;
      transform: scale(1);
      transition: transform 0.45s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .feature-card:hover .feature-card-img {
      transform: scale(1.06);
    }
    .feature-card.is-expanded .feature-card-img {
      transform: scale(1.06);
    }
    .feature-card.is-expanded:hover .feature-card-img {
      transform: scale(1.08);
    }

    /* Dark gradient overlay */
    .feature-card-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(
        to top,
        rgba(0, 0, 0, 0.72) 0%,
        rgba(0, 0, 0, 0.25) 45%,
        rgba(0, 0, 0, 0.05) 100%
      );
      transition: background 0.4s ease;
    }
    .feature-card.is-expanded .feature-card-overlay {
      background: linear-gradient(
        to top,
        rgba(0, 0, 0, 0.78) 0%,
        rgba(0, 0, 0, 0.3) 50%,
        rgba(0, 0, 0, 0.08) 100%
      );
    }

    /* Card number badge */
    .feature-card-num {
      position: absolute;
      top: 16px;
      left: 16px;
      width: 30px;
      height: 30px;
      border-radius: 50%;
      border: 1.5px solid rgba(255,255,255,0.55);
      background: rgba(255,255,255,0.1);
      backdrop-filter: blur(6px);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.72rem;
      font-weight: 600;
      color: rgba(255,255,255,0.9);
      z-index: 2;
      letter-spacing: 0.5px;
    }

    /* Label at the bottom */
    .feature-card-label {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      padding: 1.4rem 1.2rem 1.2rem;
      z-index: 2;
    }

    .feature-card-title {
      font-size: 1.15rem;
      font-weight: 700;
      color: #ffffff;
      margin: 0 0 0.3rem 0;
      line-height: 1.3;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .feature-card-desc {
      font-size: 0.85rem;
      color: rgba(255,255,255,0.8);
      margin: 0;
      line-height: 1.45;
      max-height: 0;
      overflow: hidden;
      opacity: 0;
      transition: max-height 0.4s ease, opacity 0.4s ease;
    }

    /* Show description only on the expanded card */
    .feature-card.is-expanded .feature-card-desc {
      max-height: 60px;
      opacity: 1;
    }

    /* Responsive */
    @media (max-width: 991px) {
      .features-section-wrapper { margin-left: 0; margin-right: 0; width: 100%; padding: 3rem 1rem 2rem; }
      .features-cards { height: 340px; gap: 8px; }
      .feature-card-title { font-size: 1rem; }
    }
    @media (max-width: 768px) {
      .features-cards { height: 280px; gap: 6px; }
      .feature-card-num { width: 24px; height: 24px; font-size: 0.65rem; top: 10px; left: 10px; }
    }
    @media (max-width: 576px) {
      .features-section-wrapper { margin-left: 0; margin-right: 0; width: 100%; padding: 2rem 0.75rem 1.5rem; }
      .features-cards {
        flex-direction: column;
        height: auto;
        gap: 10px;
      }
      .feature-card {
        height: 180px;
        flex: unset !important;
        width: 100%;
      }
      .feature-card.is-expanded { flex: unset !important; }
      .feature-card-desc { max-height: 60px !important; opacity: 1 !important; }
    }

    /* ============================================
       STALL MAP SECTION
       ============================================ */
    .stall-map-section-wrapper {
      background: #ffffff;
      margin-left: calc(-1.5rem);
      margin-right: calc(-1.5rem);
      width: calc(100% + 3rem);
      padding: 4rem 2rem 4.5rem;
    }
    .stall-map-section {
      max-width: 1100px;
      margin: 0 auto;
    }
    .stall-map-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      background: #ffe8dc;
      color: #c2410c;
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      padding: 0.4rem 0.9rem;
      border-radius: 50px;
      margin-bottom: 1rem;
    }
    .stall-map-heading {
      font-family: 'Playfair Display', Georgia, serif;
      font-size: clamp(2rem, 4vw, 3rem);
      font-weight: 900;
      color: #1a1a2e;
      line-height: 1.15;
      margin: 0 0 1rem;
      text-align: center;
    }
    .stall-map-heading em {
      font-style: italic;
      color: #6366f1;
    }
    .stall-map-desc {
      text-align: center;
      color: #64748b;
      font-size: 0.98rem;
      line-height: 1.65;
      max-width: 640px;
      margin: 0 auto 2.25rem;
    }
    .stall-map-desc .legend-green { color: #16a34a; font-weight: 700; }
    .stall-map-desc .legend-red { color: #dc2626; font-weight: 700; }
    .stall-map-desc .legend-yellow { color: #ca8a04; font-weight: 700; }
    .stall-map-hero {
      position: relative;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 16px 48px rgba(15, 23, 42, 0.12);
      margin-bottom: 1.75rem;
    }
    .stall-map-hero img {
      width: 100%;
      height: clamp(220px, 38vw, 420px);
      object-fit: cover;
      display: block;
    }
    .stall-map-hero-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(to top, rgba(15, 23, 42, 0.72) 0%, rgba(15, 23, 42, 0.08) 55%, transparent 100%);
      pointer-events: none;
    }
    .stall-map-hero-caption {
      position: absolute;
      left: 1.5rem;
      bottom: 1.35rem;
      color: #ffffff;
      z-index: 1;
    }
    .stall-map-hero-caption small {
      display: block;
      font-size: 0.82rem;
      opacity: 0.9;
      margin-bottom: 0.25rem;
    }
    .stall-map-hero-caption strong {
      display: block;
      font-family: 'Playfair Display', Georgia, serif;
      font-size: clamp(1.35rem, 2.5vw, 2rem);
      font-weight: 800;
      line-height: 1.15;
    }
    .stall-map-toggle-wrap {
      display: flex;
      justify-content: center;
      margin-bottom: 1.25rem;
    }
    .stall-map-toggle {
      display: inline-flex;
      align-items: center;
      gap: 0.65rem;
      padding: 0.85rem 1.6rem;
      border: none;
      border-radius: 50px;
      background: #6366f1;
      color: #ffffff;
      font-size: 0.95rem;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 8px 24px rgba(99, 102, 241, 0.35);
      transition: background 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease;
    }
    .stall-map-toggle:hover {
      background: #4f46e5;
      transform: translateY(-2px);
      box-shadow: 0 12px 28px rgba(99, 102, 241, 0.42);
    }
    .stall-map-toggle .stall-map-toggle-chevron {
      font-size: 0.8rem;
      transition: transform 0.3s ease;
    }
    .stall-map-toggle.is-open .stall-map-toggle-chevron {
      transform: rotate(180deg);
    }
    .stall-section-legend-row {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.85rem;
      margin: 0 auto 1.35rem;
      max-width: 760px;
      padding: 0 1rem;
    }
    .stall-section-info {
      position: relative;
      flex: 0 0 auto;
      width: 52px;
      height: 52px;
      border: none;
      border-radius: 50%;
      background: #4f46e5;
      color: #fff;
      font-size: 1.35rem;
      cursor: pointer;
      box-shadow: 0 8px 22px rgba(79, 70, 229, 0.42);
      transition: background 0.22s ease, transform 0.22s ease, box-shadow 0.22s ease;
    }
    .stall-section-info i {
      position: relative;
      z-index: 1;
      display: block;
      animation: stallInfoBob 1.8s ease-in-out infinite;
    }
    .stall-section-info-ring {
      position: absolute;
      inset: -4px;
      border-radius: 50%;
      border: 2px solid #6366f1;
      animation: stallInfoPulse 1.7s ease-out infinite;
      pointer-events: none;
    }
    .stall-section-info:hover,
    .stall-section-info:focus-visible {
      background: #4338ca;
      transform: scale(1.08);
      box-shadow: 0 12px 28px rgba(67, 56, 202, 0.5);
      outline: none;
    }
    .stall-section-info:hover i,
    .stall-section-info:focus-visible i {
      animation: none;
      transform: rotate(-8deg) scale(1.05);
    }
    .stall-section-info-hint {
      margin: 0;
      color: #4338ca;
      font-size: 0.95rem;
      font-weight: 650;
      line-height: 1.35;
      text-align: left;
    }
    @keyframes stallInfoPulse {
      0% { transform: scale(0.92); opacity: 0.75; }
      100% { transform: scale(1.45); opacity: 0; }
    }
    @keyframes stallInfoBob {
      0%, 100% { transform: translateY(0) scale(1); }
      50% { transform: translateY(-3px) scale(1.1); }
    }
    .stall-section-legend {
      position: fixed;
      inset: 0;
      z-index: 14000;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.25rem;
    }
    .stall-section-legend[hidden] {
      display: none;
    }
    .stall-section-legend-backdrop {
      position: absolute;
      inset: 0;
      background: rgba(30, 27, 75, 0.55);
      animation: stallLegendFade 0.22s ease;
    }
    .stall-section-legend-card {
      position: relative;
      width: min(440px, 100%);
      max-height: min(82vh, 640px);
      overflow: auto;
      background: #fff;
      border: 1px solid #e0e7ff;
      border-radius: 18px;
      padding: 1.15rem 1.25rem 1.3rem;
      box-shadow: 0 24px 60px rgba(49, 46, 129, 0.32);
      animation: stallLegendPop 0.32s cubic-bezier(0.34, 1.4, 0.64, 1);
    }
    .stall-section-legend-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      margin-bottom: 0.85rem;
    }
    .stall-section-legend-head h3 {
      margin: 0;
      color: #312e81;
      font-size: 1.15rem;
      font-weight: 750;
    }
    .stall-section-legend-close {
      width: 34px;
      height: 34px;
      border: none;
      border-radius: 50%;
      background: #eef2ff;
      color: #4338ca;
      font-size: 1.15rem;
      line-height: 1;
      cursor: pointer;
    }
    .stall-section-legend-close:hover,
    .stall-section-legend-close:focus-visible {
      background: #4f46e5;
      color: #fff;
      outline: none;
    }
    .stall-section-legend-list {
      list-style: none;
      margin: 0;
      padding: 0;
      display: flex;
      flex-direction: column;
      gap: 0.55rem;
    }
    .stall-section-legend-list li {
      display: flex;
      align-items: center;
      gap: 0.7rem;
      padding: 0.45rem 0.55rem;
      border-radius: 10px;
      background: #f8fafc;
    }
    .stall-section-swatch {
      flex: 0 0 auto;
      width: 28px;
      height: 28px;
      border-radius: 7px;
      border: 1.5px solid rgba(17, 17, 17, 0.28);
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.35);
    }
    .stall-section-eq {
      color: #6366f1;
      font-weight: 800;
    }
    .stall-section-name {
      color: #1e1b4b;
      font-weight: 650;
      font-size: 0.92rem;
    }
    .stall-section-swatch--special {
      background: linear-gradient(90deg, #f6c445 0%, #ffe98a 18%, #f0b429 36%, #fff3b0 52%, #e8a317 70%, #ffe07a 86%, #f6c445 100%);
    }
    .stall-section-swatch--meat { background: #a32020; }
    .stall-section-swatch--fish { background: #7277ff; }
    .stall-section-swatch--produce { background: #66cd00; }
    .stall-section-swatch--grocery { background: #f97316; }
    .stall-section-swatch--service { background: #8b5cf6; }
    .stall-section-swatch--drygoods { background: #bbb2a2; }
    .stall-section-swatch--driedfish { background: #7c5c3e; }
    .stall-section-swatch--eateries { background: #f5c518; }
    @keyframes stallLegendFade {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    @keyframes stallLegendPop {
      from { opacity: 0; transform: scale(0.86) translateY(10px); }
      to { opacity: 1; transform: scale(1) translateY(0); }
    }
    .stall-map-panel {
      display: none;
      margin-top: 0.5rem;
      animation: stallMapReveal 0.45s ease;
    }
    .stall-map-panel.is-open {
      display: block;
    }
    @keyframes stallMapReveal {
      from { opacity: 0; transform: translateY(-12px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* Ground floor plan (pixel-style, inside stall map panel) */
    .stall-map-panel .gf-floor-plan {
      --gf-pixel: 'Press Start 2P', 'Courier New', monospace;
    }
    .stall-map-panel .gf-floor-legend {
      display: flex;
      justify-content: center;
      gap: 1.25rem;
      flex-wrap: wrap;
      margin-bottom: 1rem;
      font-size: 0.78rem;
      font-weight: 600;
      color: #475569;
    }
    .stall-map-panel .gf-legend-item {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
    }
    .stall-map-panel .gf-legend-swatch {
      width: 16px;
      height: 16px;
      border-radius: 3px;
      border: 2px solid #cbd5e1;
      background: #f8fafc;
    }
    .stall-map-panel .gf-legend-swatch.available { border-color: #22c55e; background: #f0fdf4; }
    .stall-map-panel .gf-legend-swatch.occupied { border-color: #ef4444; background: #fee2e2; }
    .stall-map-panel .gf-legend-swatch.second-floor { border-color: #eab308; background: #fef9c3; }
    .stall-map-panel .gf-floor-shell {
      display: flex;
      align-items: stretch;
      gap: 0.5rem;
    }
    .stall-map-panel .gf-side-nav {
      width: 28px;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #6366f1;
      font-size: 0.85rem;
      opacity: 0.55;
    }
    .stall-map-panel .gf-floor-canvas {
      flex: 1;
      min-width: 0;
      background: #e8edf5;
      background-image:
        linear-gradient(rgba(99, 102, 241, 0.06) 1px, transparent 1px),
        linear-gradient(90deg, rgba(99, 102, 241, 0.06) 1px, transparent 1px);
      background-size: 24px 24px;
      border: 2px solid #c7d2e5;
      border-radius: 14px;
      padding: 1.25rem 1rem 1rem;
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.65);
    }
    .stall-map-panel .gf-top-entrance {
      display: flex;
      justify-content: center;
      margin-bottom: 0.35rem;
    }
    .stall-map-panel .gf-entrance-tab {
      display: inline-block;
      background: #6366f1;
      color: #fff;
      font-family: var(--gf-pixel);
      font-size: 0.42rem;
      line-height: 1.5;
      letter-spacing: 0.04em;
      padding: 0.35rem 0.7rem;
      border-radius: 4px 4px 0 0;
      border: 2px solid #4f46e5;
      border-bottom: none;
      transform: translateY(2px);
    }
    .stall-map-panel .gf-floor-title {
      text-align: center;
      font-family: var(--gf-pixel);
      font-size: clamp(0.42rem, 1.2vw, 0.55rem);
      line-height: 1.7;
      letter-spacing: 0.03em;
      color: #334155;
      background: rgba(255, 255, 255, 0.88);
      border: 2px solid #94a3b8;
      border-radius: 8px;
      padding: 0.65rem 0.5rem;
      margin-bottom: 1rem;
      box-shadow: 0 2px 0 rgba(148, 163, 184, 0.35);
    }
    .stall-map-panel .fp-building-layout {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-start;
      justify-content: center;
      gap: 0.55rem;
      margin-bottom: 0.75rem;
      min-width: 0;
    }
    .stall-map-panel .fp-block {
      position: relative;
      border: 2px solid #94a3b8;
      border-radius: 12px;
      background: #f8fafc;
      padding: 0.5rem 0.45rem 0.4rem;
      box-shadow: 0 3px 0 rgba(15, 23, 42, 0.06);
    }
    /* Groceries Sari-Sari sits on top, full width */
    .stall-map-panel .fp-block--groceries-sari {
      flex: 1 1 100%;
      border-color: #c084fc;
      background: #f5e8ff;
    }
    /* Special Area Section below it, full width */
    .stall-map-panel .fp-block--special-wide {
      flex: 1 1 100%;
      border-color: #c084fc;
      background: #f5e8ff;
    }
    /* Force the remaining section blocks onto a new row */
    .stall-map-panel .fp-block--fruits-veg {
      flex: 0 0 auto;
      border-color: #6db87a;
      background: #e8f7ec;
    }
    .stall-map-panel .fp-block--block-a {
      flex: 0 0 auto;
      border-color: #5b9bd5;
      background: #e8f4fc;
    }
    .stall-map-panel .fp-block--block-b {
      flex: 0 0 auto;
      border-color: #9b7bb8;
      background: #f0e8f8;
    }
    .stall-map-panel .fp-block--block-c {
      flex: 0 0 auto;
      border-color: #5ba8b0;
      background: #e5f4f6;
    }
    .stall-map-panel .fp-block--block-behind-c {
      flex: 0 0 auto;
      border-color: #a8a29e;
      background: #f5f5f4;
    }
    .stall-map-panel .fp-block--block-e {
      flex: 0 0 auto;
      border-color: #d97070;
      background: #fde8e8;
    }
    .stall-map-panel .fp-block--dry-goods {
      flex: 0 0 auto;
      border-color: #d4b84a;
      background: #fef8df;
    }
    .stall-map-panel .fp-block--service-section {
      flex: 0 0 auto;
      border-color: #7c8fd4;
      background: #eceffd;
    }
    .stall-map-panel .fp-block--block-behind-b {
      flex: 0 0 auto;
      border-color: #a8a29e;
      background: #f5f5f4;
    }
    .stall-map-panel .fp-block--groceries-sari .fp-block-icons--lead { color: #9333ea; }
    .stall-map-panel .fp-block--fruits-veg .fp-block-icons--lead { color: #16a34a; }
    .stall-map-panel .fp-block--block-a .fp-block-icons--lead { color: #2563eb; }
    .stall-map-panel .fp-block--block-b .fp-block-icons--lead { color: #7c3aed; }
    .stall-map-panel .fp-block--block-c .fp-block-icons--lead { color: #0d9488; }
    .stall-map-panel .fp-block--block-behind-c .fp-block-icons--lead,
    .stall-map-panel .fp-block--block-behind-b .fp-block-icons--lead { color: #78716c; }
    .stall-map-panel .fp-block--block-e .fp-block-icons--lead { color: #dc2626; }
    .stall-map-panel .fp-block--dry-goods .fp-block-icons--lead { color: #ca8a04; }
    .stall-map-panel .fp-block--service-section .fp-block-icons--lead { color: #4f46e5; }
    .stall-map-panel .fp-block--special-wide .fp-block-icons--lead { color: #9333ea; }
    .stall-map-panel .fp-ent-marker {
      position: absolute;
      top: -9px;
      right: 8px;
      font-size: 0.5rem;
      font-weight: 700;
      color: #2563eb;
      border: 1.5px dashed #5b9bd5;
      border-radius: 4px;
      padding: 2px 5px;
      background: #e8f4fc;
      line-height: 1.2;
    }
    .stall-map-panel .fp-block-head {
      display: flex;
      align-items: flex-start;
      gap: 0.35rem;
      margin-bottom: 0.4rem;
      padding: 0 0.1rem;
      background: transparent;
    }
    .stall-map-panel .fp-block-labels {
      display: flex;
      flex-direction: column;
      gap: 0.12rem;
      min-width: 0;
      flex: 1;
    }
    .stall-map-panel .fp-block-section {
      font-family: 'Inter', 'Segoe UI', sans-serif;
      font-size: 0.62rem;
      font-weight: 800;
      line-height: 1.25;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }
    .stall-map-panel .fp-block-name {
      font-family: 'Inter', 'Segoe UI', sans-serif;
      font-size: 0.54rem;
      font-weight: 700;
      line-height: 1.25;
      color: #475569;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .stall-map-panel .fp-block-title {
      font-family: 'Inter', 'Segoe UI', sans-serif;
      font-size: 0.65rem;
      font-weight: 700;
      line-height: 1.3;
      color: #1e293b;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      flex: 1;
      min-width: 0;
    }
    .stall-map-panel .fp-block-icons--lead {
      display: inline-flex;
      align-items: center;
      gap: 0.18rem;
      flex-shrink: 0;
      font-size: 0.8rem;
    }
    /* ── Shared stall grid (used by both ground and elevated) ── */
    .stall-map-panel .fp-stalls,
    .stall-map-panel .ev-section .fp-stalls {
      --fp-box: 34px;
      display: grid;
      grid-template-columns: repeat(var(--fp-cols, 4), var(--fp-box));
      gap: 2px;
      justify-content: start;
    }
    .stall-map-panel .fp-stalls--wide {
      grid-template-columns: repeat(var(--fp-cols, 12), var(--fp-box));
    }
    .stall-map-panel .fp-stall {
      width: var(--fp-box);
      height: 26px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f1f5f9;
      border: 1.5px solid #cbd5e1;
      border-radius: 4px;
      font-family: 'Inter', 'Segoe UI', sans-serif;
      font-size: 0.52rem;
      font-weight: 800;
      letter-spacing: 0.01em;
      line-height: 1;
      color: #64748b;
      flex-shrink: 0;
      box-sizing: border-box;
      cursor: pointer;
      transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
    }
    .stall-map-panel .fp-stall-num {
      display: block;
      line-height: 1;
    }
    .stall-map-panel .fp-stall:hover {
      transform: translateY(-2px) scale(1.14);
      box-shadow: 0 5px 12px rgba(15, 23, 42, 0.25);
      filter: brightness(1.04);
      z-index: 3;
    }
    /* Available slots take the parent section's color (set via --sec vars).
       Box stays near-white so it pops against the tinted section panel. */
    .stall-map-panel .fp-stall.available {
      border-color: var(--sec, #22c55e);
      background: #ffffff;
      color: var(--sec-text, #15803d);
    }
    .stall-map-panel .fp-stall.occupied {
      border-color: #ef4444;
      background: #fee2e2;
      color: #b91c1c;
    }
    .stall-map-panel .fp-stall.second-floor {
      border-color: #eab308;
      background: #fef9c3;
      color: #a16207;
    }
    .stall-map-panel .fp-block-caption {
      margin: 0.3rem 0 0;
      font-size: 0.6rem;
      line-height: 1.35;
      color: #64748b;
      font-style: italic;
      text-align: left;
    }
    /* ── Elevated plaza section grid ── */
    .stall-map-panel .ev-sections-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.65rem;
      margin-bottom: 0.75rem;
    }
    .stall-map-panel .ev-section {
      border: 2px solid #94a3b8;
      border-radius: 12px;
      padding: 0.55rem 0.5rem 0.45rem;
      box-shadow: 0 3px 0 rgba(15, 23, 42, 0.06);
    }
    .stall-map-panel .ev-section-head {
      display: flex;
      align-items: flex-start;
      gap: 0.35rem;
      margin-bottom: 0.4rem;
    }
    .stall-map-panel .ev-section-labels {
      display: flex;
      flex-direction: column;
      gap: 0.12rem;
      min-width: 0;
      flex: 1;
    }
    .stall-map-panel .ev-section-name {
      font-family: 'Inter', 'Segoe UI', sans-serif;
      font-size: 0.62rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      line-height: 1.25;
    }
    .stall-map-panel .ev-block-name {
      font-family: 'Inter', 'Segoe UI', sans-serif;
      font-size: 0.54rem;
      font-weight: 700;
      color: #475569;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      line-height: 1.25;
    }
    .stall-map-panel .ev-sections-grid--single {
      display: flex;
      justify-content: center;
      max-width: 420px;
      margin: 0 auto 0.75rem;
    }
    .stall-map-panel .ev-sections-grid--single .ev-section {
      width: 100%;
    }
    .stall-map-panel .ev-icons {
      display: inline-flex;
      align-items: center;
      gap: 0.15rem;
      font-size: 0.82rem;
      flex-shrink: 0;
    }
    .stall-map-panel .ev-title {
      font-family: 'Inter', 'Segoe UI', sans-serif;
      font-size: 0.65rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      line-height: 1.3;
    }
    .stall-map-panel .fp-block-sub {
      display: block;
      font-size: 0.52rem;
      font-weight: 600;
      opacity: 0.8;
      text-transform: none;
      letter-spacing: 0;
      margin-top: 1px;
    }
    /* ── Elevated plaza four-side entrances ── */
    .stall-map-panel .ev-floor-shell {
      align-items: center;
    }
    .stall-map-panel .ev-side-entrance {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 0.4rem;
      background: #1e3a5f;
      color: #fff;
      border: 2px solid #0f2744;
      border-radius: 8px;
      padding: 0.7rem 0.45rem;
      font-family: 'Inter', 'Segoe UI', sans-serif;
      font-size: 0.55rem;
      font-weight: 800;
      letter-spacing: 0.04em;
      box-shadow: 0 3px 0 #0f2744;
      align-self: center;
      flex-shrink: 0;
    }
    .stall-map-panel .ev-side-entrance span {
      writing-mode: vertical-rl;
      text-orientation: mixed;
    }
    .stall-map-panel .ev-side-entrance i { font-size: 0.7rem; }
    .stall-map-panel .ev-bottom-entrance {
      display: flex;
      justify-content: center;
      margin-top: 0.25rem;
    }
    .stall-map-panel .ev-bottom-entrance .gf-entrance-pill {
      flex-direction: row;
      gap: 0.4rem;
      min-width: 220px;
    }
    @media (max-width: 768px) {
      .stall-map-panel .ev-sections-grid {
        grid-template-columns: 1fr;
      }
      .stall-map-panel .ev-side-entrance {
        flex-direction: row;
        padding: 0.45rem 0.6rem;
      }
      .stall-map-panel .ev-side-entrance span {
        writing-mode: horizontal-tb;
      }
    }
    .stall-map-panel .gf-bottom-entrances {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 0.5rem;
      margin-top: 0.25rem;
    }
    .stall-map-panel .gf-entrance-pill {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 0.25rem;
      background: #1e3a5f;
      color: #fff;
      border: 2px solid #0f2744;
      border-radius: 6px;
      padding: 0.45rem 0.25rem;
      font-family: var(--gf-pixel);
      font-size: clamp(0.32rem, 0.85vw, 0.4rem);
      line-height: 1.4;
      letter-spacing: 0.02em;
      box-shadow: 0 3px 0 #0f2744;
    }
    .stall-map-panel .gf-entrance-pill i {
      font-size: 0.55rem;
      opacity: 0.9;
    }
    @media (max-width: 991px) {
      .stall-map-panel .gf-floor-canvas {
        overflow-x: auto;
      }
      .stall-map-panel .fp-building-layout {
        min-width: 700px;
      }
      .stall-map-panel .ev-floor-plan .gf-floor-canvas {
        overflow-x: auto;
      }
      .stall-map-panel .ev-sections-grid {
        min-width: 600px;
      }
    }
    @media (max-width: 768px) {
      .stall-map-panel .gf-bottom-entrances {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
      .stall-map-panel .gf-side-nav { display: none; }
      .stall-map-panel .gf-floor-title { font-size: 0.38rem; }
      .stall-map-panel .fp-block-title,
      .stall-map-panel .ev-title { font-size: 0.55rem; }
      .stall-map-panel .fp-stalls,
      .stall-map-panel .ev-section .fp-stalls { --fp-box: 30px; }
      .stall-map-panel .fp-stall { font-size: 0.46rem; height: 23px; }
    }

    @media (max-width: 991px) {
      .stall-map-section-wrapper {
        margin-left: -1rem;
        margin-right: -1rem;
        width: calc(100% + 2rem);
        padding: 3rem 1rem 3.5rem;
      }
    }
    @media (max-width: 576px) {
      .stall-map-section-wrapper {
        margin-left: -0.75rem;
        margin-right: -0.75rem;
        width: calc(100% + 1.5rem);
        padding: 2.5rem 0.75rem 3rem;
      }
      .stall-map-toggle {
        width: 100%;
        justify-content: center;
        font-size: 0.88rem;
        padding: 0.8rem 1.2rem;
      }
      .stall-section-legend-row {
        flex-direction: column;
        text-align: center;
      }
      .stall-section-info-hint {
        text-align: center;
        font-size: 0.88rem;
      }
    }

    /* ============================================
       SITE FOOTER (matches index.php)
       ============================================ */
    .site-footer {
      background: #2e236c;
      color: rgba(255, 255, 255, 0.88);
      padding: clamp(2.5rem, 5vw, 3.5rem) 1.5rem 1.5rem;
      border-top: none;
      margin-top: 0;
    }
    .site-footer .container {
      max-width: 1200px;
      margin: 0 auto;
    }
    .footer-grid {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
      gap: 2rem;
      padding-bottom: 2rem;
      border-bottom: 1px solid rgba(255, 255, 255, 0.12);
      align-items: start;
    }
    .footer-brand {
      justify-self: start;
      max-width: 15.5rem;
    }
    .footer-services {
      justify-self: center;
      text-align: center;
    }
    .footer-contact {
      justify-self: end;
    }
    .footer-brand .brand__mark {
      margin-bottom: 0.75rem;
    }
    .footer-brand .brand__mark img {
      border-radius: 8px;
    }
    .footer-brand h3 {
      font-family: 'Playfair Display', Georgia, serif;
      color: #ffffff;
      font-size: 1.15rem;
      margin-bottom: 0.65rem;
    }
    .footer-brand p {
      font-size: 0.88rem;
      opacity: 0.85;
      line-height: 1.6;
      margin-bottom: 1rem;
      color: rgba(255, 255, 255, 0.88);
      max-width: 15.5rem;
    }
    .social-row {
      display: flex;
      gap: 0.5rem;
    }
    .social-row a {
      width: 38px;
      height: 38px;
      border-radius: 10px;
      background: rgba(255, 255, 255, 0.12);
      display: grid;
      place-items: center;
      color: #ffffff;
      text-decoration: none;
    }
    .social-row a:hover {
      background: #5d5fef;
    }
    .footer-col h4 {
      font-size: 0.72rem;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      margin-bottom: 1rem;
      color: #ffffff;
    }
    .footer-col ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    .footer-col li {
      margin-bottom: 0.5rem;
      font-size: 0.9rem;
      opacity: 0.85;
      color: rgba(255, 255, 255, 0.88);
    }
    .footer-col a {
      font-size: 0.9rem;
      opacity: 0.85;
      color: rgba(255, 255, 255, 0.88);
      text-decoration: none;
    }
    .footer-col a:hover {
      opacity: 1;
      color: #ffffff;
    }
    .footer-contact li {
      display: flex;
      gap: 0.65rem;
      align-items: flex-start;
      margin-bottom: 0.65rem;
      font-size: 0.88rem;
      opacity: 0.9;
      color: rgba(255, 255, 255, 0.88);
    }
    .footer-contact i {
      margin-top: 0.2rem;
      color: #a5b4fc;
    }
    .footer-bar {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      gap: 0.75rem;
      padding-top: 1.25rem;
      font-size: 0.8rem;
      opacity: 0.75;
      color: rgba(255, 255, 255, 0.88);
    }
    @media (max-width: 900px) {
      .footer-grid { grid-template-columns: 1fr 1fr; }
      .footer-services {
        grid-column: 1 / -1;
        justify-self: start;
        text-align: left;
      }
      .footer-contact { justify-self: start; }
    }
    @media (max-width: 520px) {
      .footer-grid { grid-template-columns: 1fr; }
    }

    /* ============================================
       MEDIA CENTER / VIDEO SECTION
       ============================================ */

    /* Outer wrapper — full-bleed white background */
    .media-center-wrapper {
      background: #f3f4f8;
      margin-left: calc(-1.5rem);
      margin-right: calc(-1.5rem);
      width: calc(100% + 3rem);
      padding: 4rem 2rem 4.5rem;
    }

    /* Section header */
    .media-center-header {
      text-align: center;
      margin-bottom: 2.5rem;
    }
    .media-center-eyebrow {
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 3px;
      text-transform: uppercase;
      color: #667eea;
      margin-bottom: 0.6rem;
    }
    .media-center-title {
      font-family: 'Playfair Display', Georgia, serif;
      font-size: clamp(1.8rem, 3.5vw, 2.8rem);
      font-weight: 900;
      color: #1a1a2e;
      margin-bottom: 0.75rem;
    }
    .media-center-subtitle {
      font-size: 0.95rem;
      color: #64748b;
      max-width: 420px;
      margin: 0 auto;
      line-height: 1.6;
    }

    /* Card shell */
    .mc-card {
      background: #ffffff;
      border-radius: 20px;
      box-shadow: 0 4px 30px rgba(99, 102, 241, 0.08), 0 1px 6px rgba(0,0,0,0.06);
      max-width: 720px;
      margin: 0 auto;
      overflow: hidden;
    }

    /* Card top bar */
    .mc-card-topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.9rem 1.2rem 0.7rem;
      border-bottom: 1px solid #f1f5f9;
    }
    .mc-progress-dots {
      display: flex;
      gap: 5px;
      align-items: center;
    }
    .mc-dot {
      width: 22px;
      height: 5px;
      border-radius: 3px;
      background: #667eea;
      transition: opacity 0.3s;
    }
    .mc-dot.inactive {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #cbd5e1;
    }
    .mc-topbar-center {
      display: flex;
      align-items: center;
      gap: 0.6rem;
    }
    .mc-topbar-icon {
      color: #667eea;
      font-size: 0.9rem;
    }
    .mc-topbar-label {
      font-size: 0.88rem;
      font-weight: 600;
      color: #334155;
    }
    .mc-topbar-badge {
      background: #667eea;
      color: #fff;
      font-size: 0.7rem;
      font-weight: 700;
      padding: 0.15rem 0.55rem;
      border-radius: 50px;
      letter-spacing: 0.3px;
    }
    .mc-topbar-counter {
      font-size: 0.82rem;
      color: #94a3b8;
      font-weight: 500;
    }

    /* Video inside card */
    .mc-video-wrap {
      position: relative;
      width: 100%;
      background: #0f0f0f;
    }
    .mc-video-wrap .mc-slide { display: none; }
    .mc-video-wrap .mc-slide.active { display: block; }
    .mc-video-wrap video {
      width: 100%;
      display: block;
      max-height: 400px;
      object-fit: contain;
    }

    /* No media placeholder inside card */
    .mc-no-video {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: 280px;
      background: #f8fafc;
      color: #94a3b8;
      gap: 0.75rem;
    }
    .mc-no-video i { font-size: 2.5rem; color: #cbd5e1; }
    .mc-no-video p { font-size: 1rem; font-weight: 500; margin: 0; }
    .mc-no-video small { font-size: 0.82rem; }

    /* Card bottom info */
    .mc-card-info {
      padding: 1rem 1.2rem 1.2rem;
      border-top: 1px solid #f1f5f9;
    }
    .mc-card-info-title {
      font-size: 1rem;
      font-weight: 700;
      color: #1a1a2e;
      margin: 0 0 0.3rem;
    }
    .mc-card-info-desc {
      font-size: 0.85rem;
      color: #64748b;
      margin: 0;
      line-height: 1.5;
    }

    /* Nav arrows (outside card) */
    .mc-nav-prev,
    .mc-nav-next {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: #ffffff;
      border: 1.5px solid #e2e8f0;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.25s ease;
      color: #667eea;
      font-size: 1rem;
      z-index: 10;
    }
    .mc-nav-prev { left: -22px; }
    .mc-nav-next { right: -22px; }
    .mc-nav-prev:hover,
    .mc-nav-next:hover {
      background: #667eea;
      color: #fff;
      border-color: #667eea;
      transform: translateY(-50%) scale(1.08);
    }

    /* Wrapper for card + arrows */
    .mc-card-wrap {
      position: relative;
      max-width: 720px;
      margin: 0 auto;
    }

    /* Tab pills at the bottom */
    .mc-tabs {
      display: flex;
      justify-content: center;
      gap: 0.75rem;
      margin-top: 2rem;
      flex-wrap: wrap;
    }
    .mc-tab {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.55rem 1.3rem;
      border-radius: 50px;
      font-size: 0.88rem;
      font-weight: 600;
      cursor: pointer;
      border: 1.5px solid #e2e8f0;
      background: #ffffff;
      color: #64748b;
      transition: all 0.25s ease;
      box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    }
    .mc-tab i { font-size: 0.85rem; }
    .mc-tab.active {
      background: #667eea;
      color: #ffffff;
      border-color: #667eea;
      box-shadow: 0 4px 14px rgba(102, 126, 234, 0.35);
    }
    .mc-tab:not(.active):hover {
      border-color: #667eea;
      color: #667eea;
    }

    /* mc-slide: inner video panel visibility handled by .mc-video-wrap .mc-slide rules above */

    /* Responsive */
    @media (max-width: 991px) {
      .media-center-wrapper { margin-left: -1rem; margin-right: -1rem; width: calc(100% + 2rem); padding: 3rem 1.5rem 3.5rem; }
      .mc-nav-prev { left: -15px; }
      .mc-nav-next { right: -15px; }
    }
    @media (max-width: 768px) {
      .mc-nav-prev, .mc-nav-next { width: 36px; height: 36px; font-size: 0.85rem; }
      .mc-nav-prev { left: -10px; }
      .mc-nav-next { right: -10px; }
    }
    @media (max-width: 576px) {
      .media-center-wrapper { margin-left: -0.75rem; margin-right: -0.75rem; width: calc(100% + 1.5rem); padding: 2.5rem 1rem 3rem; }
      .mc-nav-prev { left: 4px; }
      .mc-nav-next { right: 4px; }
      .mc-tabs { gap: 0.5rem; }
      .mc-tab { padding: 0.5rem 1rem; font-size: 0.82rem; }
    }

    /* ============================================
       APPLICATION FEES — carousel + detail list
       ============================================ */
    .fee-rates-section {
      max-width: 720px;
      margin: 2.75rem auto 0;
    }

    .fee-rates-card {
      background: #ffffff;
      border-radius: 20px;
      box-shadow: 0 4px 24px rgba(15, 23, 42, 0.08), 0 1px 4px rgba(0, 0, 0, 0.04);
      border: 1px solid rgba(226, 232, 240, 0.9);
      overflow: hidden;
    }

    .fee-rates-head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      padding: 1.15rem 1.25rem 0.85rem;
    }

    .fee-rates-head h3 {
      margin: 0 0 0.35rem;
      font-size: 1.05rem;
      font-weight: 700;
      color: #0f172a;
      letter-spacing: -0.01em;
    }

    .fee-rates-meta {
      margin: 0;
      font-size: 0.78rem;
      color: #94a3b8;
      display: flex;
      align-items: center;
      gap: 0.35rem;
      flex-wrap: wrap;
    }

    .fee-rates-meta i {
      font-size: 0.72rem;
      color: #cbd5e1;
    }

    .fee-rates-more-btn {
      flex-shrink: 0;
      width: 36px;
      height: 36px;
      border: none;
      border-radius: 50%;
      background: #f8fafc;
      color: #64748b;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
    }

    .fee-rates-more-btn:hover {
      background: #eef2ff;
      color: #667eea;
      transform: translateX(2px);
    }

    .fee-rates-track-wrap {
      overflow: hidden;
      padding: 0.35rem 0 1rem;
      mask-image: linear-gradient(90deg, transparent 0, #000 24px, #000 calc(100% - 24px), transparent 100%);
      -webkit-mask-image: linear-gradient(90deg, transparent 0, #000 24px, #000 calc(100% - 24px), transparent 100%);
    }

    .fee-rates-track {
      display: flex;
      gap: 1rem;
      width: max-content;
      padding: 0 1.25rem;
      animation: feeRatesScrollLeft 18s linear infinite;
    }

    .fee-rates-track.is-paused {
      animation-play-state: paused;
    }

    @keyframes feeRatesScrollLeft {
      0% { transform: translateX(0); }
      100% { transform: translateX(-50%); }
    }

    .fee-rate-chip {
      flex: 0 0 min(340px, 78vw);
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1.15rem 1.25rem;
      background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      min-height: 96px;
      text-decoration: none;
      color: inherit;
      transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    }

    .fee-rate-chip:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 22px rgba(102, 126, 234, 0.12);
      border-color: #c7d2fe;
    }

    .fee-rate-chip-logo {
      width: 52px;
      height: 52px;
      border-radius: 50%;
      object-fit: cover;
      flex-shrink: 0;
      border: 2px solid #ffffff;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      background: #fff;
    }

    .fee-rate-chip-body {
      min-width: 0;
    }

    .fee-rate-label {
      display: block;
      font-size: 0.92rem;
      font-weight: 600;
      color: #334155;
      margin-bottom: 0.2rem;
    }

    .fee-rate-value {
      display: block;
      font-size: 1.45rem;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: -0.02em;
      line-height: 1.1;
    }

    .fee-rates-promo {
      display: flex;
      align-items: center;
      gap: 0.65rem;
      flex-wrap: wrap;
      padding: 0.85rem 1.25rem 1.15rem;
      border-top: 1px solid #f1f5f9;
      font-size: 0.82rem;
      color: #92400e;
    }

    .fee-promo-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.2rem 0.55rem;
      border-radius: 999px;
      background: #ffedd5;
      color: #c2410c;
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.02em;
    }

    .fee-rates-modal {
      position: fixed;
      inset: 0;
      z-index: 12000;
      display: none;
      align-items: flex-end;
      justify-content: center;
      padding: 0;
      background: rgba(15, 23, 42, 0.45);
      backdrop-filter: blur(4px);
      -webkit-backdrop-filter: blur(4px);
    }

    .fee-rates-modal.is-open {
      display: flex;
    }

    .fee-rates-modal-sheet {
      width: min(480px, 100%);
      max-height: 92vh;
      background: #ffffff;
      border-radius: 24px 24px 0 0;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      animation: feeSheetUp 0.32s cubic-bezier(0.16, 1, 0.3, 1);
      box-shadow: 0 -12px 40px rgba(0, 0, 0, 0.18);
    }

    @keyframes feeSheetUp {
      from { transform: translateY(100%); opacity: 0.6; }
      to { transform: translateY(0); opacity: 1; }
    }

    @media (min-width: 640px) {
      .fee-rates-modal {
        align-items: center;
        padding: 1.5rem;
      }
      .fee-rates-modal-sheet {
        border-radius: 24px;
        max-height: 85vh;
      }
    }

    .fee-rates-modal-header {
      background: linear-gradient(135deg, #667eea 0%, #5568d3 100%);
      color: #ffffff;
      padding: 1.35rem 1.25rem 1.5rem;
      position: relative;
    }

    .fee-rates-modal-close {
      position: absolute;
      top: 1rem;
      right: 1rem;
      width: 34px;
      height: 34px;
      border: none;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.2);
      color: #fff;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: background 0.2s ease;
    }

    .fee-rates-modal-close:hover {
      background: rgba(255, 255, 255, 0.32);
    }

    .fee-rates-modal-header h3 {
      margin: 0 0 0.25rem;
      font-size: 1.35rem;
      font-weight: 800;
    }

    .fee-rates-modal-header p {
      margin: 0;
      font-size: 0.88rem;
      opacity: 0.92;
    }

    .fee-rates-modal-body {
      padding: 1.25rem 1.25rem 1.5rem;
      overflow-y: auto;
    }

    .fee-rates-modal-body > h4 {
      margin: 0 0 0.35rem;
      font-size: 1.05rem;
      font-weight: 700;
      color: #0f172a;
    }

    .fee-rates-modal-updated {
      margin: 0 0 1.1rem;
      font-size: 0.78rem;
      color: #94a3b8;
    }

    .fee-rates-list {
      list-style: none;
      margin: 0;
      padding: 0;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      overflow: hidden;
      background: #fafafa;
    }

    .fee-rates-list li {
      display: flex;
      align-items: center;
      gap: 0.85rem;
      padding: 1rem 1.1rem;
      background: #ffffff;
      border-bottom: 1px solid #f1f5f9;
    }

    .fee-rates-list li:last-child {
      border-bottom: none;
    }

    .fee-rates-list-logo {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      object-fit: cover;
      flex-shrink: 0;
      border: 1px solid #e2e8f0;
      background: #fff;
    }

    .fee-rates-list-info {
      flex: 1;
      min-width: 0;
    }

    .fee-rates-list-code {
      display: block;
      font-size: 0.95rem;
      font-weight: 700;
      color: #0f172a;
    }

    .fee-rates-list-name {
      display: block;
      font-size: 0.78rem;
      color: #64748b;
      margin-top: 0.1rem;
    }

    .fee-rates-list-price {
      font-size: 1.05rem;
      font-weight: 800;
      color: #0f172a;
      white-space: nowrap;
    }

    .fee-rates-explore {
      display: block;
      width: 100%;
      margin-top: 1rem;
      padding: 0.85rem;
      border: none;
      border-radius: 12px;
      background: #f8fafc;
      color: #64748b;
      font-size: 0.88rem;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s ease, color 0.2s ease;
    }

    .fee-rates-explore:hover {
      background: #eef2ff;
      color: #667eea;
    }

    .fee-rates-spend-box {
      margin-top: 1.25rem;
      padding: 1.1rem;
      border-radius: 16px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
    }

    .fee-rates-spend-box h5 {
      margin: 0 0 0.75rem;
      font-size: 0.92rem;
      font-weight: 700;
      color: #334155;
    }

    .fee-rates-spend-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      padding: 0.85rem 1rem;
      background: #ffffff;
      border-radius: 12px;
      border: 1px solid #e2e8f0;
    }

    .fee-rates-spend-type {
      display: flex;
      align-items: center;
      gap: 0.55rem;
      font-size: 0.9rem;
      font-weight: 600;
      color: #334155;
    }

    .fee-rates-spend-type img {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      object-fit: cover;
    }

    .fee-rates-spend-note {
      margin: 0.65rem 0 0;
      font-size: 0.78rem;
      color: #16a34a;
      font-weight: 500;
    }

    @media (max-width: 576px) {
      .fee-rate-chip {
        flex-basis: min(300px, 85vw);
      }
      .fee-rates-section {
        margin-top: 2rem;
      }
    }

    /* ============================================
       RESPONSIVE DESIGN
       ============================================ */
    @media (max-width: 991px) {
      .navbar {
        padding: 0.75rem 1rem;
      }

      /* Sidebar Overlay */
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

      /* Same pill bar — only the position changes (pinned to the bottom) */
      .nav-pills-wrap {
        top: auto;
        bottom: calc(1rem + env(safe-area-inset-bottom, 0px));
        height: auto;
      }

      /* Sidebar: calendar, language, logout only */
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
        width: 100%;
        display: flex;
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
        width: 100%;
        display: flex;
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

      /* Hero responsive */
      .hero-section {
        grid-template-columns: 1fr;
        gap: 2rem;
        padding: 1rem;
      }
      .hero-visual {
        height: 340px;
        order: -1;
      }
      .hero-oval-1 { width: 380px; height: 380px; }
      .hero-oval-2 { width: 310px; height: 310px; }
      .hero-oval-3 { width: 240px; height: 240px; }
      .hero-photo-cluster {
        width: 300px;
        height: 300px;
      }
      .hero-photo--one { width: 122px; height: 122px; top: 92px; left: 4px; }
      .hero-photo--two { width: 116px; height: 116px; right: 14px; top: 10px; }
      .hero-photo--three { width: 166px; height: 166px; }
      .hero-title {
        font-size: 3rem;
        letter-spacing: -1px;
      }
      .hero-buttons {
        flex-direction: column;
        align-items: stretch;
        margin-bottom: 0.5rem;
      }
      .btn-hero-primary,
      .btn-hero-secondary {
        width: 100%;
        justify-content: center;
      }
      /* Remove the scroll indicator on mobile so it doesn't overlap the buttons */
      .scroll-indicator {
        display: none;
      }
      .hero-section {
        padding-bottom: 2rem;
      }

      .navbar-logo .brand__text {
        font-size: clamp(0.82rem, 2vw, 1rem);
      }
      .navbar-logo .brand__mark {
        width: 40px;
        height: 40px;
      }
    }

    @media (max-width: 768px) {
      .navbar {
        padding: 0.5rem 0.75rem;
      }
      .hero-section {
        padding: 0.5rem;
      }
      .hero-visual {
        height: 300px;
      }
      .hero-oval-1 { width: 320px; height: 320px; }
      .hero-oval-2 { width: 260px; height: 260px; }
      .hero-oval-3 { width: 200px; height: 200px; }
      .hero-photo-cluster {
        width: 260px;
        height: 260px;
      }
      .hero-photo--one { width: 106px; height: 106px; top: 80px; left: 2px; }
      .hero-photo--two { width: 100px; height: 100px; right: 10px; top: 8px; }
      .hero-photo--three { width: 144px; height: 144px; }
      .hero-title {
        font-size: 2.5rem;
        line-height: 1.1;
      }
      .hero-description {
        font-size: 1rem;
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
    }

    @media (max-width: 576px) {
      .navbar {
        padding: 0.5rem;
      }
      .navbar-content {
        gap: 0.5rem;
      }
      .hero-title {
        font-size: 2rem;
        letter-spacing: -1px;
      }
      .hero-description {
        font-size: 0.95rem;
        line-height: 1.5;
      }
      .navbar-left {
        gap: 0.5rem;
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
      .calendar-icon,
      .language-selector {
        width: 32px;
        height: 32px;
      }
      .main-container {
        padding: 0.75rem 0.75rem 0;
        padding-top: calc(70px + 0.75rem);
        padding-bottom: calc(5.25rem + env(safe-area-inset-bottom, 0px));
      }
      .btn-hero-primary,
      .btn-hero-secondary {
        padding: 1rem 2rem;
        font-size: 1rem;
      }
    }
  </style>
  <link rel="stylesheet" href="assets/css/vendor-mobile-sidebar-logout.css">
  <link rel="stylesheet" href="assets/css/vendor-navbar-glass.css?v=nav-icon-phone-1">
  <link rel="stylesheet" href="assets/css/vendor-nav-theme-lock.css?v=1">
  <link rel="stylesheet" href="assets/css/menu-landing-nav.css?v=menu-nav-lock-4">
  <link rel="stylesheet" href="assets/css/vendor-portal-dark-nav-pills.css?v=phone-glass-4">
</head>
<body class="page-menu">
  <script>try{if(localStorage.getItem('darkmode')==='active')document.body.classList.add('darkmode');}catch(e){}</script>
  <div class="main-container">
    <?php $vendor_nav_active = 'home'; include __DIR__ . '/includes/vendor_portal_navbar.php'; ?>

    <?php include __DIR__ . '/includes/menu_landing_hero.php'; ?>

    <!-- ═══════════════════════════════════════════
         MEDIA CENTER / VIDEO SECTION
         ═══════════════════════════════════════════ -->
    <div class="media-center-wrapper" id="mediaCenter">

      <!-- Section header -->
      <div class="media-center-header">
        <p class="media-center-eyebrow" data-translate="mc.eyebrow">Media Center</p>
        <h2 class="media-center-title" data-translate="mc.title">Market Videos &amp; Updates</h2>
        <p class="media-center-subtitle" data-translate="mc.subtitle">Watch the latest announcements, updated price, and permits guide from the Daet Public Market office.</p>
      </div>

      <!-- Shared card with swappable content -->
      <div class="mc-card-wrap">

        <!-- Left arrow -->
        <button class="mc-nav-prev" id="mcBtnPrev" aria-label="Previous">
          <i class="fas fa-chevron-left"></i>
        </button>

        <!-- Right arrow -->
        <button class="mc-nav-next" id="mcBtnNext" aria-label="Next">
          <i class="fas fa-chevron-right"></i>
        </button>

        <div class="mc-card">

          <!-- Top bar (updated by JS) -->
          <div class="mc-card-topbar">
            <div class="mc-progress-dots">
              <span class="mc-dot" id="mcDot0"></span>
              <span class="mc-dot inactive" id="mcDot1"></span>
              <span class="mc-dot inactive" id="mcDot2"></span>
            </div>
            <div class="mc-topbar-center">
              <i class="mc-topbar-icon" id="mcTopIcon"></i>
              <span class="mc-topbar-label" id="mcTopLabel">Updated Price</span>
              <span class="mc-topbar-badge" id="mcTopBadge">Market Rates</span>
            </div>
            <span class="mc-topbar-counter" id="mcTopCounter">1 / 3</span>
          </div>

          <!-- Video panels — only active one shown -->
          <div class="mc-video-wrap">

            <div class="mc-slide active" id="mcSlide0">
              <?php if ($price_video && file_exists($price_video['file_path'])): ?>
                <video controls muted playsinline preload="metadata" id="mcVideo0">
                  <source src="<?php echo htmlspecialchars($price_video['file_path']); ?>" type="<?php echo htmlspecialchars($price_video['mime_type']); ?>">
                </video>
              <?php else: ?>
                <div class="mc-no-video">
                  <i class="fas fa-video-slash"></i>
                  <p>No video available</p>
                  <small>Admin can upload a video in the admin panel</small>
                </div>
              <?php endif; ?>
            </div>

            <div class="mc-slide" id="mcSlide1">
              <?php if ($promotional_video && file_exists($promotional_video['file_path'])): ?>
                <video controls muted playsinline preload="metadata" id="mcVideo1">
                  <source src="<?php echo htmlspecialchars($promotional_video['file_path']); ?>" type="<?php echo htmlspecialchars($promotional_video['mime_type']); ?>">
                </video>
              <?php else: ?>
                <div class="mc-no-video">
                  <i class="fas fa-video-slash"></i>
                  <p>No video available</p>
                  <small>Admin can upload a video in the admin panel</small>
                </div>
              <?php endif; ?>
            </div>

            <div class="mc-slide" id="mcSlide2">
              <?php if ($announcement_video && file_exists($announcement_video['file_path'])): ?>
                <video controls muted playsinline preload="metadata" id="mcVideo2">
                  <source src="<?php echo htmlspecialchars($announcement_video['file_path']); ?>" type="<?php echo htmlspecialchars($announcement_video['mime_type']); ?>">
                </video>
              <?php else: ?>
                <div class="mc-no-video">
                  <i class="fas fa-video-slash"></i>
                  <p>No video available</p>
                  <small>Admin can upload a video in the admin panel</small>
                </div>
              <?php endif; ?>
            </div>

          </div><!-- end mc-video-wrap -->

          <!-- Bottom info (updated by JS) -->
          <div class="mc-card-info">
            <p class="mc-card-info-title" id="mcInfoTitle">Latest Market Price of Prime &amp; Other Commodities Monitoring Report</p>
            <p class="mc-card-info-desc" id="mcInfoDesc">Watch the official weekly monitoring report on prime and other commodity prices at Daet Public Market. Stay informed on prevailing market rates to guide vendors and shoppers.</p>
          </div>

        </div><!-- end mc-card -->
      </div><!-- end mc-card-wrap -->

      <!-- Tab pills -->
      <div class="mc-tabs">
        <button class="mc-tab active" id="mcTab0">
          <i class="fas fa-coins"></i> <span data-translate="video.price">Updated Price</span>
        </button>
        <button class="mc-tab" id="mcTab1">
          <i class="fas fa-check-circle"></i> <span data-translate="mc.tabPromo">Promotional Permit</span>
        </button>
        <button class="mc-tab" id="mcTab2">
          <i class="fas fa-bullhorn"></i> <span data-translate="mc.tabAnnounce">Announcement</span>
        </button>
      </div>

      <!-- Application Fees carousel -->
      <section class="fee-rates-section" aria-label="Application fees">
        <div class="fee-rates-card">
          <div class="fee-rates-head">
            <div>
              <h3 data-translate="fees.title">Application Fees</h3>
              <p class="fee-rates-meta">
                Estimated rates as of <?php echo date('d M Y, H:i'); ?>
                <i class="fas fa-info-circle" title="Official Daet Public Market fees"></i>
              </p>
            </div>
            <button type="button" class="fee-rates-more-btn" id="feeRatesOpenBtn" aria-label="View all application fees">
              <i class="fas fa-chevron-right"></i>
            </button>
          </div>

          <div class="fee-rates-track-wrap" id="feeRatesTrackWrap">
            <div class="fee-rates-track" id="feeRatesTrack">
              <?php for ($fee_loop = 0; $fee_loop < 2; $fee_loop++): ?>
                <?php foreach ($application_fees as $fee): ?>
              <a href="<?php echo htmlspecialchars($fee['href'], ENT_QUOTES, 'UTF-8'); ?>" class="fee-rate-chip">
                <img src="<?php echo htmlspecialchars($fee_logo_path, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="fee-rate-chip-logo" onerror="this.src='assets/imgs/logo.jpeg'">
                <div class="fee-rate-chip-body">
                  <span class="fee-rate-label"><?php echo htmlspecialchars($fee['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                  <strong class="fee-rate-value">₱ <?php echo number_format($fee['price']); ?></strong>
                </div>
              </a>
                <?php endforeach; ?>
              <?php endfor; ?>
            </div>
          </div>

          <div class="fee-rates-promo">
            <span class="fee-promo-badge">Official</span>
            <span>Stall application and renewal fees at Daet Public Market</span>
          </div>
        </div>
      </section>

    </div><!-- end media-center-wrapper -->

    <!-- Application fees detail sheet -->
    <div class="fee-rates-modal" id="feeRatesModal" role="dialog" aria-modal="true" aria-labelledby="feeRatesModalTitle" hidden>
      <div class="fee-rates-modal-sheet">
        <header class="fee-rates-modal-header">
          <button type="button" class="fee-rates-modal-close" id="feeRatesCloseBtn" aria-label="Close">
            <i class="fas fa-times"></i>
          </button>
          <h3 id="feeRatesModalTitle" data-translate="fees.title">Application Fees</h3>
          <p>Daet Public Market — official stall rates</p>
        </header>
        <div class="fee-rates-modal-body">
          <h4>Daily Application Fees</h4>
          <p class="fee-rates-modal-updated">Updated as of <?php echo date('d M Y, H:i'); ?></p>
          <ul class="fee-rates-list">
            <?php foreach ($application_fees as $fee): ?>
            <li>
              <img src="<?php echo htmlspecialchars($fee_logo_path, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="fee-rates-list-logo" onerror="this.src='assets/imgs/logo.jpeg'">
              <div class="fee-rates-list-info">
                <span class="fee-rates-list-code"><?php echo htmlspecialchars($fee['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="fee-rates-list-name"><?php echo htmlspecialchars($fee['subtitle'], ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
              <span class="fee-rates-list-price"><?php echo number_format($fee['price'], 2); ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
          <button type="button" class="fee-rates-explore" id="feeRatesExploreClose">Explore More &gt;</button>
          <div class="fee-rates-spend-box">
            <h5>When You Apply</h5>
            <div class="fee-rates-spend-row">
              <div class="fee-rates-spend-type">
                <img src="<?php echo htmlspecialchars($fee_logo_path, ENT_QUOTES, 'UTF-8'); ?>" alt="" onerror="this.src='assets/imgs/logo.jpeg'">
                <span><?php echo htmlspecialchars($fee_new['title'], ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
              <strong class="fee-rates-list-price">₱ <?php echo number_format((float)$fee_new['price'], 2); ?></strong>
            </div>
            <p class="fee-rates-spend-note">1 <?php echo htmlspecialchars($fee_new['title'], ENT_QUOTES, 'UTF-8'); ?> ⇌ ₱ <?php echo number_format((float)$fee_new['price'], 2); ?> PHP</p>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════
         FEATURES SECTION
         ═══════════════════════════════════════════ -->
    <div class="features-section-wrapper">
    <div class="features-leaves-container" aria-hidden="true">
      <model-viewer class="leaf fl1" src="https://api.getlayers.ai/storage/v1/object/public/public/assets/soda-14ff8a788d/leaves.glb" environment-image="neutral" exposure="1.35" shadow-intensity="0.35" interaction-prompt="none" camera-orbit="45deg 75deg 105%"></model-viewer>
      <model-viewer class="leaf fl2" src="https://api.getlayers.ai/storage/v1/object/public/public/assets/soda-14ff8a788d/leaves.glb" environment-image="neutral" exposure="1.35" shadow-intensity="0.35" interaction-prompt="none" camera-orbit="-30deg 60deg 105%"></model-viewer>
      <model-viewer class="leaf fl3" src="https://api.getlayers.ai/storage/v1/object/public/public/assets/soda-14ff8a788d/leaves.glb" environment-image="neutral" exposure="1.35" shadow-intensity="0.35" interaction-prompt="none" camera-orbit="120deg 85deg 105%"></model-viewer>
      <model-viewer class="leaf fl4" src="https://api.getlayers.ai/storage/v1/object/public/public/assets/soda-14ff8a788d/leaves.glb" environment-image="neutral" exposure="1.35" shadow-intensity="0.35" interaction-prompt="none" camera-orbit="10deg 45deg 105%"></model-viewer>
      <model-viewer class="leaf fl5" src="https://api.getlayers.ai/storage/v1/object/public/public/assets/soda-14ff8a788d/leaves.glb" environment-image="neutral" exposure="1.35" shadow-intensity="0.35" interaction-prompt="none" camera-orbit="75deg 55deg 105%"></model-viewer>
      <model-viewer class="leaf fl6" src="https://api.getlayers.ai/storage/v1/object/public/public/assets/soda-14ff8a788d/leaves.glb" environment-image="neutral" exposure="1.35" shadow-intensity="0.35" interaction-prompt="none" camera-orbit="-90deg 70deg 105%"></model-viewer>
    </div>
    <section class="features-section">
      <div class="features-heading">
        <h2 data-translate="stall.available">Available Stall Slot</h2>
        <p class="features-detail" data-translate="stall.availableDesc">These are the available slots. Check the stall map below to see more.</p>
      </div>

      <div class="features-cards" id="featuresCards">
        <!-- Card 1 -->
        <div class="feature-card">
          <img src="assets/imgs/available-slot-1.jpg" alt="Slot 1" class="feature-card-img">
          <div class="feature-card-overlay"></div>
          <div class="feature-card-num">01</div>
          <div class="feature-card-label">
            <p class="feature-card-title">Slot 1</p>
            
          </div>
        </div>

        <!-- Card 2 -->
        <div class="feature-card">
          <img src="assets/imgs/available-slot-2.jpg" alt="Slot 2" class="feature-card-img">
          <div class="feature-card-overlay"></div>
          <div class="feature-card-num">02</div>
          <div class="feature-card-label">
            <p class="feature-card-title">Slot 2</p>
           
          </div>
        </div>

        <!-- Card 3 -->
        <div class="feature-card">
          <img src="assets/imgs/available-slot-3.jpg" alt="Slot 3" class="feature-card-img">
          <div class="feature-card-overlay"></div>
          <div class="feature-card-num">03</div>
          <div class="feature-card-label">
            <p class="feature-card-title">Slot 3</p>
            
          </div>
        </div>

        <!-- Card 4: -->
        <div class="feature-card">
          <img src="assets/imgs/available-slot-4.jpg" alt="Slot 4" class="feature-card-img">
          <div class="feature-card-overlay"></div>
          <div class="feature-card-num">04</div>
          <div class="feature-card-label">
            <p class="feature-card-title">Slot 4</p>
            
          </div>
        </div>
      </div>
    </section>
    </div>

    <!-- ═══════════════════════════════════════════
         STALL MAP SECTION
         ═══════════════════════════════════════════ -->
    <div class="stall-map-section-wrapper">
      <section class="stall-map-section" id="stallMap">
        <div style="text-align:center;">
          <span class="stall-map-badge"><i class="fas fa-store"></i> Daet Public Market</span>
        </div>
        <h2 class="stall-map-heading"><span data-translate="map.explorePre">Explore Our</span> <em data-translate="map.exploreEm">Stall Map</em></h2>
        <p class="stall-map-desc">
          View the market site layout — blocks, streets, and landmarks match the official ground sketch.
          Individual stall numbers will be added to each block soon.
        </p>

        <div class="stall-map-hero">
          <img src="assets/imgs/palengke.jpg" alt="Daet Public Market aerial view">
          <div class="stall-map-hero-overlay"></div>
          <div class="stall-map-hero-caption">
            <small>Daet Public Market</small>
            <strong data-translate="map.heroGround">Ground Floor — Site Map</strong>
          </div>
        </div>

        <div class="stall-map-toggle-wrap">
          <button type="button" class="stall-map-toggle" id="stallMapToggle" aria-expanded="false" aria-controls="stallMapPanel">
            <i class="fas fa-eye" id="stallMapToggleIcon" aria-hidden="true"></i>
            <span id="stallMapToggleLabel" data-translate="map.toggleShow">Click here to see stall map</span>
            <i class="fas fa-chevron-down stall-map-toggle-chevron" aria-hidden="true"></i>
          </button>
        </div>

        <div class="stall-section-legend" id="stallSectionLegend" hidden>
          <div class="stall-section-legend-backdrop" data-legend-close></div>
          <div class="stall-section-legend-card" role="dialog" aria-modal="true" aria-labelledby="stallSectionLegendTitle">
            <div class="stall-section-legend-head">
              <h3 id="stallSectionLegendTitle" data-translate="map.legendTitle">Section colors</h3>
              <button type="button" class="stall-section-legend-close" data-legend-close aria-label="Close">&times;</button>
            </div>
            <ul class="stall-section-legend-list">
              <li><span class="stall-section-swatch stall-section-swatch--special" aria-hidden="true"></span><span class="stall-section-eq">=</span><span class="stall-section-name" data-translate="map.sec.special">Special Area Section</span></li>
              <li><span class="stall-section-swatch stall-section-swatch--meat" aria-hidden="true"></span><span class="stall-section-eq">=</span><span class="stall-section-name" data-translate="map.sec.meat">Meat and Chicken Section</span></li>
              <li><span class="stall-section-swatch stall-section-swatch--fish" aria-hidden="true"></span><span class="stall-section-eq">=</span><span class="stall-section-name" data-translate="map.sec.fish">Fresh Fish Section</span></li>
              <li><span class="stall-section-swatch stall-section-swatch--produce" aria-hidden="true"></span><span class="stall-section-eq">=</span><span class="stall-section-name" data-translate="map.sec.produce">Fruits and Vegetables Section</span></li>
              <li><span class="stall-section-swatch stall-section-swatch--grocery" aria-hidden="true"></span><span class="stall-section-eq">=</span><span class="stall-section-name" data-translate="map.sec.grocery">Groceries Sari-Sari Section</span></li>
              <li><span class="stall-section-swatch stall-section-swatch--service" aria-hidden="true"></span><span class="stall-section-eq">=</span><span class="stall-section-name" data-translate="map.sec.service">Service Section</span></li>
              <li><span class="stall-section-swatch stall-section-swatch--drygoods" aria-hidden="true"></span><span class="stall-section-eq">=</span><span class="stall-section-name" data-translate="map.sec.drygoods">Dry Goods Section</span></li>
              <li><span class="stall-section-swatch stall-section-swatch--driedfish" aria-hidden="true"></span><span class="stall-section-eq">=</span><span class="stall-section-name" data-translate="map.sec.dried">Dried and Salted Fish Section</span></li>
              <li><span class="stall-section-swatch stall-section-swatch--eateries" aria-hidden="true"></span><span class="stall-section-eq">=</span><span class="stall-section-name" data-translate="map.sec.eateries">Eateries and Cooked Food Section</span></li>
            </ul>
          </div>
        </div>

        <div class="stall-map-panel" id="stallMapPanel" hidden>
          <div class="stall-section-legend-row">
            <button type="button" class="stall-section-info" id="stallSectionInfo" aria-haspopup="dialog" aria-controls="stallSectionLegend" aria-label="Section color guide">
              <span class="stall-section-info-ring" aria-hidden="true"></span>
              <i class="fas fa-circle-info" aria-hidden="true"></i>
            </button>
            <p class="stall-section-info-hint" data-translate="map.legendHint">&lt;-- click this information icon to view the color of designated section</p>
          </div>
          <?php include __DIR__ . '/includes/market_site_map_schematic.php'; ?>
        </div>
      </section>
    </div>

  </div>

  <footer class="site-footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <div class="brand__mark" style="width:48px;height:48px;">
            <img src="assets/imgs/logo.jpeg" alt="" width="48" height="48">
          </div>
          <h3 data-translate="footer.brand">Daet Public Market</h3>
          <p data-translate="footer.blurb">If you have any questions or concerns, feel free to message us on our Facebook page.</p>
          <div class="social-row">
            <a href="https://facebook.com/daetpublicmarket" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
          </div>
        </div>
        <div class="footer-col footer-services">
          <h4 data-translate="footer.services">Services</h4>
          <ul>
            <li data-translate="footer.svc1">Stall Rental</li>
            <li data-translate="footer.svc2">Vendor Registration</li>
            <li data-translate="footer.svc3">Permit Renewal</li>
            <li data-translate="footer.svc4">Inspection Services</li>
            <li data-translate="footer.svc5">Vendor Support</li>
          </ul>
        </div>
        <div class="footer-col footer-contact">
          <h4 data-translate="footer.contact">Contact Info</h4>
          <ul>
            <li><i class="fas fa-location-dot"></i> Daet, Camarines Norte, Philippines</li>
            <li><i class="fab fa-facebook-f"></i> <a href="https://facebook.com/daetpublicmarket" target="_blank" rel="noopener noreferrer">facebook.com/daetpublicmarket</a></li>
            <li><i class="fas fa-clock"></i> <span data-translate="footer.hours">Open Mon-Fri: 8AM – 5PM</span></li>
          </ul>
        </div>
      </div>
      <div class="footer-bar">
        <span>&copy; <?php echo date('Y'); ?> Daet Public Market. All rights reserved.</span>
        <span>Local Government Unit of Daet, Camarines Norte</span>
      </div>
    </div>
  </footer>

  <!-- Calendar Modal -->
  <div class="modal fade" id="calendarModal" tabindex="-1" aria-labelledby="calendarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 15px;">
        <div class="modal-header" style="border-bottom: 1px solid rgba(99, 102, 241, 0.1);">
          <h5 class="modal-title" id="calendarModalLabel" style="color: #6366f1;" data-translate="modal.calendar">
            Calendar
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" style="padding: 1.5rem;">
          <div id="calendarContainer" style="color: #475569;">
            <p style="text-align: center; color: rgba(71, 85, 105, 0.7);" data-translate="modal.calendar.coming">Calendar functionality coming soon</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  <script>
    // ── Language change function ───────────────────────────────────────────────
    function changeLanguage(lang) {
      localStorage.setItem('preferredLanguage', lang);
      updateLanguageIcon(lang);
      translatePage(lang);
    }
    
    // ── Update language icon ──────────────────────────────────────────────────
    function updateLanguageIcon(lang) {
      const languageSelector = document.getElementById('languageSelector');
      const languageSelectorDesktop = document.getElementById('languageSelectorDesktop');
      
      if (languageSelector) {
        languageSelector.classList.remove('ph-selected', 'en-selected');
        languageSelector.classList.add(lang === 'ph' ? 'ph-selected' : 'en-selected');
      }
      if (languageSelectorDesktop) {
        languageSelectorDesktop.classList.remove('ph-selected', 'en-selected');
        languageSelectorDesktop.classList.add(lang === 'ph' ? 'ph-selected' : 'en-selected');
      }
    }
    
    // ── Translate page content ────────────────────────────────────────────────
    function translatePage(lang) {
      const translations = {
        en: {
          'nav.home': 'Home',
          'nav.application': 'Application',
          'nav.renewal': 'Renewal',
          'nav.profile': 'Profile',
          'nav.notifications': 'Notifications',
          'nav.logout': 'Logout',
          'hero.welcome': 'Welcome',
          'hero.description': 'Efficient, reliable, seamless. Our market clearance system is designed to stand out, just like your business. Apply, renew, and manage everything in one place.',
          'hero.apply': 'Apply Now',
          'hero.learnmore': 'Learn More',
          'modal.calendar': 'Calendar',
          'modal.calendar.coming': 'Calendar functionality coming soon',
          'confirm.logout': 'Are you sure you want to logout?',
          'video.promotional': 'Promotional Permit',
          'video.price': 'Updated Price',
          'video.announcement': 'Announcement',
          'building.title': 'Daet Public Market',
          'mc.eyebrow': 'Media Center',
          'mc.title': 'Market Videos & Updates',
          'mc.subtitle': 'Watch the latest announcements, updated price, and permits guide from the Daet Public Market office.',
          'mc.tabPromo': 'Promotional Permit',
          'mc.tabAnnounce': 'Announcement',
          'fees.title': 'Application Fees',
          'stall.available': 'Available Stall Slot',
          'stall.availableDesc': 'These are the available slots. Check the stall map below to see more.',
          'slot.word': 'Slot',
          'map.explorePre': 'Explore Our',
          'map.exploreEm': 'Stall Map',
          'map.plazaPre': 'Daet Elevated',
          'map.plazaEm': 'Plaza Map',
          'map.toggleShow': 'Click here to see stall map',
          'map.toggleHide': 'Hide stall map',
          'map.legendHint': '<-- click this information icon to view the color of designated section',
          'map.legendTitle': 'Section colors',
          'map.sec.special': 'Special Area Section',
          'map.sec.meat': 'Meat and Chicken Section',
          'map.sec.fish': 'Fresh Fish Section',
          'map.sec.produce': 'Fruits and Vegetables Section',
          'map.sec.grocery': 'Groceries Sari-Sari Section',
          'map.sec.service': 'Service Section',
          'map.sec.drygoods': 'Dry Goods Section',
          'map.sec.dried': 'Dried and Salted Fish Section',
          'map.sec.eateries': 'Eateries and Cooked Food Section',
          'map.toggleShowElev': 'Click here to see elevated stall map',
          'map.toggleHideElev': 'Hide elevated stall map',
          'map.heroGround': 'Ground Floor — Site Map',
          'map.heroPlaza': 'Ground Floor — Stall Slot',
          'footer.brand': 'Daet Public Market',
          'footer.blurb': 'If you have any questions or concerns, feel free to message us on our Facebook page.',
          'footer.services': 'Services',
          'footer.svc1': 'Stall Rental',
          'footer.svc2': 'Vendor Registration',
          'footer.svc3': 'Permit Renewal',
          'footer.svc4': 'Inspection Services',
          'footer.svc5': 'Vendor Support',
          'footer.contact': 'Contact Info',
          'footer.hours': 'Open Mon-Fri: 8AM – 5PM',
          'map.siteNote': 'Site layout from your sketch — stall numbers will be added later.',
          'map.siteClickHint': 'Click a stall on the map to view stall slots.',
          'map.stallSlotsTitle': 'Stall slots',
          'map.legendAvailable': 'Available',
          'map.legendOccupied': 'Occupied',
          'map.elevDesc': 'Browse available stalls inside the Daet Elevated Plaza. Green slots are ready for rent, red are occupied.'
        },
        ph: {
          'nav.home': 'Tahanan',
          'nav.application': 'Aplikasyon',
          'nav.renewal': 'Pag-renew',
          'nav.profile': 'Profile',
          'nav.notifications': 'Mga Abiso',
          'nav.logout': 'Mag-logout',
          'hero.welcome': 'Maligayang pagdating',
          'hero.description': 'Mahusay, maaasahan, walang kahirap-hirap. Ang aming sistema ng market clearance ay idinisenyo upang maging natatangi, tulad ng inyong negosyo.',
          'hero.apply': 'Mag-apply Ngayon',
          'hero.learnmore': 'Matuto Pa',
          'modal.calendar': 'Kalendaryo',
          'modal.calendar.coming': 'Ang functionality ng kalendaryo ay darating sa lalong madaling panahon',
          'confirm.logout': 'Sigurado ka bang gusto mong mag-logout?',
          'video.promotional': 'Promotional Permit',
          'video.price': 'Pinakabagong Presyo',
          'video.announcement': 'Anunsyo',
          'building.title': 'Daet Public Market',
          'mc.eyebrow': 'Media Center',
          'mc.title': 'Mga Video at Update ng Palengke',
          'mc.subtitle': 'Panoorin ang pinakabagong mga anunsyo, updated na presyo, at gabay sa permit mula sa opisina ng Daet Public Market.',
          'mc.tabPromo': 'Promotional Permit',
          'mc.tabAnnounce': 'Anunsyo',
          'fees.title': 'Mga Bayarin sa Aplikasyon',
          'stall.available': 'Mga Available na Stall Slot',
          'stall.availableDesc': 'Ito ang mga available na slot. Tingnan ang mapa ng stall sa ibaba para sa iba pa.',
          'slot.word': 'Slot',
          'map.explorePre': 'Tuklasin ang Aming',
          'map.exploreEm': 'Mapa ng Stall',
          'map.plazaPre': 'Daet Elevated',
          'map.plazaEm': 'Mapa ng Plaza',
          'map.toggleShow': 'I-click dito para makita ang mapa ng stall',
          'map.toggleHide': 'Itago ang mapa ng stall',
          'map.legendHint': '<-- i-click ang icon na ito para makita ang kulay ng bawat seksyon',
          'map.legendTitle': 'Mga kulay ng seksyon',
          'map.sec.special': 'Seksyon ng Special Area',
          'map.sec.meat': 'Seksyon ng Karne at Manok',
          'map.sec.fish': 'Seksyon ng Sariwang Isda',
          'map.sec.produce': 'Seksyon ng Prutas at Gulay',
          'map.sec.grocery': 'Seksyon ng Groceries / Sari-Sari',
          'map.sec.service': 'Seksyon ng Serbisyo',
          'map.sec.drygoods': 'Seksyon ng Dry Goods',
          'map.sec.dried': 'Seksyon ng Pinatuyo at Inasnan na Isda',
          'map.sec.eateries': 'Seksyon ng Kainan at Lutong Pagkain',
          'map.toggleShowElev': 'I-click dito para makita ang elevated na mapa ng stall',
          'map.toggleHideElev': 'Itago ang elevated na mapa ng stall',
          'map.heroGround': 'Ground Floor — Mapa ng Site',
          'map.heroPlaza': 'Ground Floor — Stall Slot',
          'footer.brand': 'Daet Public Market',
          'footer.blurb': 'Kung may tanong o alalahanin, mag-message sa aming Facebook page.',
          'footer.services': 'Mga Serbisyo',
          'footer.svc1': 'Pag-upa ng Stall',
          'footer.svc2': 'Rehistrasyon ng Vendor',
          'footer.svc3': 'Pag-renew ng Permit',
          'footer.svc4': 'Serbisyo ng Inspeksyon',
          'footer.svc5': 'Suporta sa Vendor',
          'footer.contact': 'Impormasyon sa Pakikipag-ugnayan',
          'footer.hours': 'Bukas Lunes-Biyernes: 8AM – 5PM',
          'map.siteNote': 'Layout ng site mula sa sketch — idadagdag ang mga numero ng stall mamaya.',
          'map.siteClickHint': 'I-click ang stall sa mapa para makita ang mga stall slot.',
          'map.stallSlotsTitle': 'Mga stall slot',
          'map.legendAvailable': 'Bakante',
          'map.legendOccupied': 'Okupado',
          'map.elevDesc': 'Tingnan ang mga stall sa Daet Elevated Plaza. Berde ang handa pang-upa, pula ang okupado.'
        }
      };
      
      const texts = translations[lang] || translations.en;
      window.__menuTexts = texts;
      
      // Translate all data-translate elements
      document.querySelectorAll('[data-translate]').forEach(element => {
        const key = element.getAttribute('data-translate');
        if (!key || key.indexOf('nav.') === 0) return;
        if (texts[key]) {
          element.textContent = texts[key];
        }
      });
      
      document.querySelectorAll('[data-translate-title]').forEach(element => {
        const key = element.getAttribute('data-translate-title');
        if (!key || key.indexOf('nav.') === 0) return;
        if (texts[key]) {
          element.title = texts[key];
        }
      });

      // ── Hero Section ──
      // Welcome badge
      const welcomeText = document.getElementById('welcomeText');
      if (welcomeText) {
        welcomeText.textContent = texts['hero.welcome'] + ',';
      }

      // Hero title stays "Daet Public Market" in both languages.

      // Hero description
      const heroDesc = document.getElementById('heroDescription');
      if (heroDesc) heroDesc.textContent = texts['hero.description'];

      // Apply Now button
      const applyBtn = document.getElementById('applyNowBtn');
      if (applyBtn) {
        applyBtn.innerHTML = texts['hero.apply'] + ' <span class="btn-arrow">→</span>';
      }

      // Learn More button
      const learnBtn = document.getElementById('learnMoreBtn');
      if (learnBtn) learnBtn.textContent = texts['hero.learnmore'];

      // Calendar modal
      const calModalLabel = document.querySelector('#calendarModalLabel');
      if (calModalLabel) calModalLabel.textContent = texts['modal.calendar'];
      const calComingEl = document.querySelector('[data-translate="modal.calendar.coming"]');
      if (calComingEl) calComingEl.textContent = texts['modal.calendar.coming'];

      window.dispatchEvent(new CustomEvent('menu-lang-change', { detail: { lang } }));
    }
    
    // ── Confirm logout ────────────────────────────────────────────────────────
    function confirmLogout() {
      const lang = localStorage.getItem('preferredLanguage') || 'en';
      const messages = {
        en: 'Are you sure you want to logout?',
        ph: 'Sigurado ka bang gusto mong mag-logout?'
      };
      return confirm(messages[lang] || messages.en);
    }

    // ── Calendar render ───────────────────────────────────────────────────────
    function renderCalendar() {
      const today = new Date();
      const currentYear  = today.getFullYear();
      const currentMonth = today.getMonth();
      const currentDate  = today.getDate();
      const firstDay     = new Date(currentYear, currentMonth, 1).getDay();
      const daysInMonth  = new Date(currentYear, currentMonth + 1, 0).getDate();
      const monthNames   = ['January','February','March','April','May','June','July','August','September','October','November','December'];
      const dayNames     = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
      
      let calendarHTML = `
        <div style="text-align:center;margin-bottom:1.5rem;">
          <h4 style="color:#6366f1;font-weight:600;margin:0;">${monthNames[currentMonth]} ${currentYear}</h4>
        </div>
        <table style="width:100%;border-collapse:collapse;">
          <thead><tr>
            ${dayNames.map(d => `<th style="padding:.5rem;color:#667eea;font-weight:600;font-size:.85rem;text-align:center;">${d}</th>`).join('')}
          </tr></thead>
          <tbody>`;
      
      let date = 1;
      for (let i = 0; i < 6; i++) {
        calendarHTML += '<tr>';
        for (let j = 0; j < 7; j++) {
          if (i === 0 && j < firstDay) {
            calendarHTML += '<td style="padding:.5rem;"></td>';
          } else if (date > daysInMonth) {
            calendarHTML += '<td style="padding:.5rem;"></td>';
          } else {
            const isToday = date === currentDate;
            const style = isToday
              ? 'background:#ff6b35;color:#ffffff;border-radius:50%;font-weight:700;'
              : 'color:#475569;';
            calendarHTML += `<td style="padding:.5rem;text-align:center;">
              <div style="width:35px;height:35px;display:flex;align-items:center;justify-content:center;margin:0 auto;${style}">${date}</div>
            </td>`;
            date++;
          }
        }
        calendarHTML += '</tr>';
        if (date > daysInMonth) break;
      }
      calendarHTML += '</tbody></table>';
      document.getElementById('calendarContainer').innerHTML = calendarHTML;
    }

    // ── DOMContentLoaded ──────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {

      // Language init
      const savedLang = localStorage.getItem('preferredLanguage') || 'en';
      const langSelectDesktop = document.getElementById('languageSelectDesktop');
      if (langSelectDesktop) {
        langSelectDesktop.value = savedLang;
        langSelectDesktop.addEventListener('change', function () {
          changeLanguage(this.value);
        });
      }

      updateLanguageIcon(savedLang);
      translatePage(savedLang);

      // Calendar modal
      const calendarModal = document.getElementById('calendarModal');
      if (calendarModal) {
        calendarModal.addEventListener('show.bs.modal', renderCalendar);
      }

      // Feature cards — keep last hovered card expanded (desktop accordion strip)
      (function () {
        const featuresCards = document.getElementById('featuresCards');
        if (!featuresCards) return;

        const cards = featuresCards.querySelectorAll('.feature-card');
        if (!cards.length) return;

        function setExpandedCard(card) {
          cards.forEach(function (c) {
            c.classList.toggle('is-expanded', c === card);
          });
        }

        setExpandedCard(cards[0]);

        cards.forEach(function (card) {
          card.addEventListener('mouseenter', function () {
            if (window.innerWidth <= 576) return;
            setExpandedCard(card);
          });
        });
      })();

      // Hamburger menu
      const navbarToggler = document.getElementById('navbarToggler');
      const navbarCenter  = document.getElementById('navbarCenter');
      const navbarOverlay = document.getElementById('navbarOverlay');
      const navbarRight   = document.querySelector('.navbar-right');

      function closeSidebar() {
        if (window.innerWidth <= 991) {
          navbarToggler.setAttribute('aria-expanded', 'false');
          navbarCenter.classList.remove('show');
          if (navbarOverlay) navbarOverlay.classList.remove('show');
          document.body.style.overflow = '';
        }
      }

      if (navbarToggler && navbarCenter) {
        navbarToggler.addEventListener('click', function (e) {
          e.stopPropagation();
          const isExpanded = this.getAttribute('aria-expanded') === 'true';
          this.setAttribute('aria-expanded', !isExpanded);
          navbarCenter.classList.toggle('show');
          if (window.innerWidth <= 991) {
            if (navbarOverlay) navbarOverlay.classList.toggle('show');
            document.body.style.overflow = isExpanded ? '' : 'hidden';
          }
        });

        if (navbarOverlay) {
          navbarOverlay.addEventListener('click', closeSidebar);
        }

        document.addEventListener('click', function (event) {
          if (window.innerWidth <= 991) {
            const isClickInside = navbarToggler.contains(event.target) || navbarCenter.contains(event.target);
            if (!isClickInside && navbarCenter.classList.contains('show')) {
              closeSidebar();
            }
          }
        });

        if (navbarRight) {
          const logoutBtn = navbarRight.querySelector('#logoutBtn');
          if (logoutBtn) {
            logoutBtn.addEventListener('click', function () {
              if (window.innerWidth <= 991) closeSidebar();
            });
          }
        }

        const calendarLink = document.getElementById('calendarIconLinkDesktop');
        if (calendarLink) {
          calendarLink.addEventListener('click', function () {
            if (window.innerWidth <= 991) closeSidebar();
          });
        }

        window.addEventListener('resize', function () {
          if (window.innerWidth > 991) {
            navbarCenter.classList.remove('show');
            navbarToggler.setAttribute('aria-expanded', 'false');
            if (navbarOverlay) navbarOverlay.classList.remove('show');
            document.body.style.overflow = '';
          }
        });
      }

    });
  </script>

  <script>
    // ── Media Center — fully self-contained ───────────────────────────────────
    (function () {
      const TOTAL = 3;
      let current = 0;
      let mediaCenterInView = false;

      function getMcVideo(index) {
        return document.getElementById('mcVideo' + index);
      }

      function pauseAllMcVideos() {
        for (let i = 0; i < TOTAL; i++) {
          const video = getMcVideo(i);
          if (video && !video.paused) {
            video.pause();
          }
        }
      }

      function playActiveMcVideo() {
        if (!mediaCenterInView) return;
        const activeVideo = getMcVideo(current);
        if (!activeVideo) return;
        activeVideo.muted = true;
        activeVideo.playsInline = true;
        const playPromise = activeVideo.play();
        if (playPromise && typeof playPromise.catch === 'function') {
          playPromise.catch(function () {});
        }
      }

      function setMediaCenterPlayback(shouldPlay) {
        mediaCenterInView = shouldPlay;
        if (shouldPlay) {
          playActiveMcVideo();
        } else {
          pauseAllMcVideos();
        }
      }

      // Slide metadata
      const slides = [
        {
          icon:    'fas fa-tags',
          label:   'Updated Price',
          badge:   'Market Rates',
          counter: '1 / 3',
          title:   'Latest Market Price of Prime & Other Commodities Monitoring Report',
          desc:    'Watch the official weekly monitoring report on prime and other commodity prices at Daet Public Market. Stay informed on prevailing market rates to guide vendors and shoppers.'
        },
        {
          icon:    'fas fa-check-circle',
          label:   'Promotional Permit',
          badge:   'Official Guide',
          counter: '2 / 3',
          title:   'Apply for Your Promotional Permit',
          desc:    'Learn how to apply for a promotional permit at Daet Public Market. This guide walks you through the complete process step by step.'
        },
        {
          icon:    'fas fa-bullhorn',
          label:   'Announcement',
          badge:   'Official',
          counter: '3 / 3',
          title:   'Official Market Announcements',
          desc:    'Important announcements from the Daet Public Market administration. Stay up to date with all official notices and events.'
        }
      ];

      function goTo(index) {
        pauseAllMcVideos();

        current = (index + TOTAL) % TOTAL;
        const s = slides[current];

        // Update video slides
        for (let i = 0; i < TOTAL; i++) {
          const sl = document.getElementById('mcSlide' + i);
          if (sl) sl.classList.toggle('active', i === current);
        }

        // Update progress dots
        for (let i = 0; i < TOTAL; i++) {
          const dot = document.getElementById('mcDot' + i);
          if (!dot) continue;
          if (i === current) {
            dot.classList.remove('inactive');
          } else {
            dot.classList.add('inactive');
          }
        }

        // Update top bar text
        const iconEl    = document.getElementById('mcTopIcon');
        const labelEl   = document.getElementById('mcTopLabel');
        const badgeEl   = document.getElementById('mcTopBadge');
        const counterEl = document.getElementById('mcTopCounter');
        if (iconEl)    { iconEl.className = s.icon + ' mc-topbar-icon'; }
        if (labelEl)   { labelEl.textContent = s.label; }
        if (badgeEl)   { badgeEl.textContent = s.badge; }
        if (counterEl) { counterEl.textContent = s.counter; }

        // Update bottom info
        const titleEl = document.getElementById('mcInfoTitle');
        const descEl  = document.getElementById('mcInfoDesc');
        if (titleEl) { titleEl.textContent = s.title; }
        if (descEl)  { descEl.textContent  = s.desc; }

        // Update tabs
        for (let i = 0; i < TOTAL; i++) {
          const tab = document.getElementById('mcTab' + i);
          if (tab) tab.classList.toggle('active', i === current);
        }

        playActiveMcVideo();
      }

      function initMediaCenterVisibility() {
        const mediaSection = document.getElementById('mediaCenter');
        if (!mediaSection) return;

        if ('IntersectionObserver' in window) {
          const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
              setMediaCenterPlayback(entry.isIntersecting && entry.intersectionRatio >= 0.35);
            });
          }, {
            threshold: [0, 0.2, 0.35, 0.5, 0.75, 1],
            rootMargin: '-8% 0px -8% 0px'
          });
          observer.observe(mediaSection);
        } else {
          function syncMediaCenterOnScroll() {
            const rect = mediaSection.getBoundingClientRect();
            const viewHeight = window.innerHeight || document.documentElement.clientHeight;
            const visibleHeight = Math.min(rect.bottom, viewHeight) - Math.max(rect.top, 0);
            const ratio = rect.height > 0 ? visibleHeight / rect.height : 0;
            setMediaCenterPlayback(ratio >= 0.35 && rect.bottom > 0 && rect.top < viewHeight);
          }
          window.addEventListener('scroll', syncMediaCenterOnScroll, { passive: true });
          window.addEventListener('resize', syncMediaCenterOnScroll);
          syncMediaCenterOnScroll();
        }
      }

      document.addEventListener('DOMContentLoaded', function () {
        // Wire up arrow buttons
        const btnPrev = document.getElementById('mcBtnPrev');
        const btnNext = document.getElementById('mcBtnNext');
        if (btnPrev) btnPrev.addEventListener('click', function () { goTo(current - 1); });
        if (btnNext) btnNext.addEventListener('click', function () { goTo(current + 1); });

        // Wire up tab pills
        for (let i = 0; i < TOTAL; i++) {
          (function (idx) {
            const tab = document.getElementById('mcTab' + idx);
            if (tab) tab.addEventListener('click', function () { goTo(idx); });
          })(i);
        }

        // Init to slide 0
        goTo(0);
        initMediaCenterVisibility();
      });
    })();

    // ── Application Fees carousel + detail sheet ─────────────────────────────
    (function () {
      const modal = document.getElementById('feeRatesModal');
      const openBtn = document.getElementById('feeRatesOpenBtn');
      const closeBtn = document.getElementById('feeRatesCloseBtn');
      const exploreClose = document.getElementById('feeRatesExploreClose');
      const track = document.getElementById('feeRatesTrack');
      const trackWrap = document.getElementById('feeRatesTrackWrap');

      function openFeeModal() {
        if (!modal) return;
        modal.removeAttribute('hidden');
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
      }

      function closeFeeModal() {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('hidden', '');
        document.body.style.overflow = '';
      }

      if (openBtn) openBtn.addEventListener('click', openFeeModal);
      if (closeBtn) closeBtn.addEventListener('click', closeFeeModal);
      if (exploreClose) exploreClose.addEventListener('click', function () {
        window.location.href = 'promotional-permit.php';
      });

      if (modal) {
        modal.addEventListener('click', function (e) {
          if (e.target === modal) closeFeeModal();
        });
      }

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) {
          closeFeeModal();
        }
      });

      if (track && trackWrap) {
        trackWrap.addEventListener('mouseenter', function () {
          track.classList.add('is-paused');
        });
        trackWrap.addEventListener('mouseleave', function () {
          track.classList.remove('is-paused');
        });
        trackWrap.addEventListener('focusin', function () {
          track.classList.add('is-paused');
        });
        trackWrap.addEventListener('focusout', function () {
          track.classList.remove('is-paused');
        });
      }
    })();

    // ── Stall map toggle (reusable) ───────────────────────────────────────────
    function mapToggleText(key, fallback) {
      return (window.__menuTexts && window.__menuTexts[key]) || fallback;
    }
    function makeMapToggle(toggleId, panelId, labelId, iconId, openKey, closeKey, openFb, closeFb) {
      const toggle = document.getElementById(toggleId);
      const panel  = document.getElementById(panelId);
      const label  = document.getElementById(labelId);
      const icon   = document.getElementById(iconId);
      if (!toggle || !panel) return;
      toggle.addEventListener('click', function () {
        const isOpen = panel.classList.toggle('is-open');
        panel.hidden = !isOpen;
        toggle.classList.toggle('is-open', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if (label) {
          label.setAttribute('data-translate', isOpen ? closeKey : openKey);
          label.textContent = isOpen ? mapToggleText(closeKey, closeFb) : mapToggleText(openKey, openFb);
        }
        if (icon)  icon.className    = isOpen ? 'fas fa-eye-slash' : 'fas fa-eye';
        if (isOpen) panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      });
    }
    makeMapToggle('stallMapToggle', 'stallMapPanel', 'stallMapToggleLabel', 'stallMapToggleIcon',
                  'map.toggleShow', 'map.toggleHide', 'Click here to see stall map', 'Hide stall map');

    (function () {
      var btn = document.getElementById('stallSectionInfo');
      var modal = document.getElementById('stallSectionLegend');
      if (!btn || !modal) return;
      function openLegend() {
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        var closeBtn = modal.querySelector('.stall-section-legend-close');
        if (closeBtn) closeBtn.focus();
      }
      function closeLegend() {
        if (modal.hidden) return;
        modal.hidden = true;
        document.body.style.overflow = '';
        btn.focus();
      }
      btn.addEventListener('click', openLegend);
      modal.addEventListener('click', function (e) {
        if (e.target.closest('[data-legend-close]')) closeLegend();
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeLegend();
      });
      var mapToggle = document.getElementById('stallMapToggle');
      if (mapToggle) {
        mapToggle.addEventListener('click', function () {
          var panel = document.getElementById('stallMapPanel');
          if (panel && !panel.classList.contains('is-open')) closeLegend();
        });
      }
    })();
  </script>
  <script src="assets/js/menu-landing-hero.js"></script>
  <?php
  $cs_contact_href = 'index.php#contact';
  $cs_announcement_href = 'index.php#announcement';
  $cs_show_menu_tools = true;
  include __DIR__ . '/includes/mobile_enhancements.php';
  include __DIR__ . '/includes/customer_support_widget.php';
  ?>
</body>
</html>