<?php
require_once 'config.php';
require_once 'auth_functions.php';

// Protect this page - require login
requireLogin();

// Get user information from session
$user_name = $_SESSION['full_name'] ?? 'User';
$user_email = $_SESSION['email'] ?? '';
$user_type = $_SESSION['user_type'] ?? 'vendor';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title> About Us</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link rel="icon" type="image/jpeg" href="assets/imgs/logo.jpeg">
  <style>
    body {
      background: linear-gradient(135deg, #667eea 100%, #FFA07A 0%); 
      min-height: 100vh;
      padding-top: 100px;
    }

    .navbar {
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .navbar-brand {
      display: flex;
      align-items: center;
      gap: 15px;
      font-weight: bold;
      color: #667eea !important;
      font-size: 1.5rem;
    }

    .navbar-brand img {
      border-radius: 10px;
    }

    .nav-link {
      color: #333 !important;
      font-weight: 500;
      padding: 0.5rem 1rem !important;
      margin: 0 0.25rem;
      border-radius: 5px;
      transition: all 0.3s;
    }

    .nav-link:hover,
    .nav-link.active {
      background-color: #667eea;
      color: white !important;
    }

    .nav-link.icon-only {
      padding: 0.5rem 0.75rem !important;
      display: flex;
      align-items: center;
      justify-content: center;
      min-width: 40px;
      position: relative;
    }

    .nav-link.icon-only:hover {
      background-color: #667eea;
      color: white !important;
    }

    main {
      max-width: 1200px;
      margin: 2rem auto;
      padding: 0 1rem;
    }

    .page-header {
      text-align: center;
      background-color: white;
      padding: 3rem 2rem;
      border-radius: 10px;
      margin-bottom: 2rem;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .page-header h1 {
      color: #667eea;
      margin-bottom: 1rem;
      font-size: 2.5rem;
      font-weight: bold;
    }

    .page-header p {
      color: #666;
      font-size: 1.2rem;
      max-width: 800px;
      margin: 0 auto;
    }

    .content-section {
      background-color: white;
      padding: 2.5rem;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      margin-bottom: 2rem;
      border-top: 4px solid #667eea;
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .content-section:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 35px rgba(102, 126, 234, 0.2);
      border-top-color: #FFA07A;
    }

    .content-section h2 {
      color: #667eea;
      margin-bottom: 1.5rem;
      font-size: 2rem;
      font-weight: bold;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: color 0.3s;
    }

    .content-section:hover h2 {
      color: #764ba2;
    }

    .content-section h2 i {
      font-size: 1.8rem;
      transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .content-section:hover h2 i {
      transform: scale(1.1) rotate(5deg);
    }

    .content-section p {
      color: #555;
      font-size: 1.05rem;
      line-height: 1.8;
      margin-bottom: 1rem;
    }

    .mission-vision-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 2rem;
      margin-top: 2rem;
    }

    .mv-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 2.5rem;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      position: relative;
      overflow: hidden;
    }

    .mv-card::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
      transition: transform 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .mv-card:hover::before {
      transform: scale(1.1);
    }

    .mv-card:hover {
      transform: translateY(-8px) scale(1.02);
      box-shadow: 0 15px 40px rgba(102, 126, 234, 0.5);
    }

    .mv-card i {
      font-size: 3rem;
      margin-bottom: 1rem;
      color: #FFA07A;
    }

    .mv-card h3 {
      font-size: 1.5rem;
      margin-bottom: 1rem;
      font-weight: bold;
    }

    .mv-card p {
      font-size: 1rem;
      line-height: 1.7;
      color: rgba(255, 255, 255, 0.95);
    }

    .values-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.5rem;
      margin-top: 2rem;
    }

    .value-card {
      background-color: #f8f9fa;
      padding: 2rem;
      border-radius: 10px;
      text-align: center;
      border-left: 4px solid #667eea;
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      cursor: pointer;
    }

    .value-card:hover {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      transform: translateX(8px) translateY(-3px);
      box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
      border-left-color: #FFA07A;
    }

    .value-card i {
      font-size: 2.5rem;
      color: #667eea;
      margin-bottom: 1rem;
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .value-card:hover i {
      color: #FFA07A;
      transform: scale(1.15) rotate(-5deg);
    }

    .value-card h4 {
      font-size: 1.2rem;
      margin-bottom: 0.5rem;
      font-weight: bold;
    }

    .value-card p {
      font-size: 0.95rem;
      color: #666;
      margin: 0;
    }

    .value-card:hover p {
      color: rgba(255, 255, 255, 0.9);
    }

    .team-section {
      text-align: center;
    }

    .team-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 2rem;
      margin-top: 2rem;
    }

    .team-member {
      background-color: white;
      padding: 2rem 1.5rem;
      border-radius: 10px;
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      border-top: 3px solid transparent;
    }

    .team-member:hover {
      transform: translateY(-8px) scale(1.03);
      box-shadow: 0 12px 35px rgba(102, 126, 234, 0.3);
      border-top-color: #667eea;
      background: linear-gradient(135deg, #ffffff 0%, #f0f4ff 100%);
    }

    .team-member i {
      font-size: 4rem;
      color: #667eea;
      margin-bottom: 1rem;
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .team-member:hover i {
      color: #FFA07A;
      transform: scale(1.1) rotate(5deg);
    }

    .team-member h4 {
      color: #333;
      font-size: 1.2rem;
      margin-bottom: 0.5rem;
      font-weight: bold;
    }

    .team-member p {
      color: #666;
      font-size: 0.95rem;
      margin: 0;
    }

    /* Footer Styles */
    footer {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 3rem 0 1rem;
      margin-top: 4rem;
    }

    .footer-content {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 1rem;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 2rem;
      margin-bottom: 2rem;
    }

    .footer-section h3 {
      font-size: 1.3rem;
      margin-bottom: 1rem;
      font-weight: bold;
    }

    .footer-logo {
      display: flex;
      align-items: center;
      gap: 15px;
      margin-bottom: 1rem;
    }

    .footer-logo img {
      width: 60px;
      height: 60px;
      border-radius: 10px;
      background: white;
      padding: 5px;
    }

    .footer-section p {
      line-height: 1.8;
      color: rgba(255, 255, 255, 0.9);
      margin-bottom: 0.5rem;
    }

    .footer-section i {
      margin-right: 10px;
      color: #FFA07A;
    }

    .social-links {
      display: flex;
      gap: 1rem;
      margin-top: 1rem;
    }

    .social-links a {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 50%;
      color: white;
      font-size: 1.2rem;
      transition: all 0.3s;
      text-decoration: none;
    }

    .social-links a:hover {
      background: #FFA07A;
      transform: translateY(-3px);
      box-shadow: 0 5px 15px rgba(255, 160, 122, 0.4);
    }

    .footer-bottom {
      text-align: center;
      padding-top: 2rem;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      color: rgba(255, 255, 255, 0.8);
      font-size: 0.9rem;
    }

    @media (max-width: 991px) {
      .navbar-brand {
        font-size: 1.2rem;
      }

      .page-header h1 {
        font-size: 2rem;
      }

      .page-header p {
        font-size: 1rem;
      }
    }

    @media (max-width: 768px) {
      .footer-content {
        grid-template-columns: 1fr;
        text-align: center;
      }

      .footer-logo {
        justify-content: center;
      }

      .social-links {
        justify-content: center;
      }
    }

    @media (max-width: 576px) {
      .page-header h1 {
        font-size: 1.5rem;
      }

      .content-section {
        padding: 1.5rem;
      }
    }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-light bg-white py-3 fixed-top" data-lang-preserve="en">
    <div class="container">
      <a class="navbar-brand" href="menu.php">
        <img src="assets/imgs/logo.jpeg" width="60" height="60" alt="Logo" onerror="this.style.display='none'">
        <span>Daet Public Market Office
</span>
      </a>
      
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
        data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" 
        aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      
      <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
          <li class="nav-item">
            <a class="nav-link" href="menu.php">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="application.php">Application</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="renewal.php">Renewal</a>
          </li>
          <li class="nav-item">
            <a class="nav-link icon-only" href="profile.php" title="Profile">
              <i class="fas fa-user-circle"></i>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link active" href="about_us.php">About Us</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="logout.php" onclick="return confirm('Are you sure you want to logout?')">
              <i class="fas fa-sign-out-alt"></i> Logout
            </a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <main>
    <div class="page-header">
      <h1>About Us</h1>
      <p>Learn more about our mission to simplify and streamline the market clearance process for vendors and businesses.</p>
    </div>

    <div class="content-section">
      <h2><i class="fas fa-info-circle" style="color: #667eea; margin-right: 10px;"></i>Our Objectives</h2>
      <p>The Market Clearance System is a comprehensive digital platform designed to modernize and streamline the process of obtaining market clearances. We serve vendors, businesses, and market operators by providing an efficient, transparent, and user-friendly system for managing applications and renewals.</p>
      <p>Our platform eliminates the hassle of traditional paper-based processes, reduces processing time, and provides real-time tracking of application status. We are committed to making the clearance process accessible to everyone, from small vendors to large-scale businesses.</p>
    </div>

    <div class="mission-vision-grid">
      <div class="mv-card">
        <i class="fas fa-bullseye"></i>
        <h3>Our Mission</h3>
        <p>Inclusive and progressive Daet Public Market with sustainable development and disciplined conduct of business and innovative services and operations for the wellfare of all Daetños.</p>
      </div>

      <div class="mv-card">
        <i class="fas fa-eye"></i>
        <h3>Our Vision</h3>
        <p>Sustainable and resilient Daet Public Market, self-sufficient and effective Local Economic Enterprise of the Local Government Unit of Daet.</p>
      </div>
    </div>

    <div class="content-section values-section">
      <h2><i class="fas fa-heart" style="color: #667eea; margin-right: 10px;"></i>Our Core Values</h2>
      <div class="values-grid">
        <div class="value-card">
          <i class="fas fa-shield-alt"></i>
          <h4>Integrity</h4>
          <p>We maintain the highest standards of honesty and transparency in all our operations.</p>
        </div>

        <div class="value-card">
          <i class="fas fa-lightbulb"></i>
          <h4>Innovation</h4>
          <p>We continuously improve our platform with cutting-edge technology and user feedback.</p>
        </div>

        <div class="value-card">
          <i class="fas fa-users"></i>
          <h4>Customer Focus</h4>
          <p>Your needs and satisfaction are at the heart of everything we do.</p>
        </div>

        <div class="value-card">
          <i class="fas fa-handshake"></i>
          <h4>Reliability</h4>
          <p>We provide consistent, dependable service you can count on every time.</p>
        </div>
      </div>
    </div>

    <div class="content-section team-section">
      <h2><i class="fas fa-user-friends" style="color: #667eea; margin-right: 10px;"></i>Customer Service</h2>
      <p style="max-width: 800px; margin: 0 auto 2rem;"> If you have question kindly message us on our Facebook Page.  </p>
      
      <div class="team-grid">
        <div class="team-member">
          <i class="fas fa-user-tie"></i>
          <h4>Management </h4>
          <p>Leading with vision and purpose</p>
        </div>

        <div class="team-member">
          <i class="fas fa-laptop-code"></i>
          <h4>Technical </h4>
          <p>Building robust solutions</p>
        </div>

        <div class="team-member">
          <i class="fas fa-headset"></i>
          <h4>Customer Service</h4>
          <p>Always here to help</p>
        </div>

        <div class="team-member">
          <i class="fas fa-chart-line"></i>
          <h4>Operations </h4>
          <p>Ensuring smooth processes</p>
        </div>
      </div>
    </div>
  </main>

  <footer>
    <div class="footer-content">
      <!-- About Section -->
      <div class="footer-section">
        <div class="footer-logo">
          <img src="assets/imgs/logo.jpeg" alt="Market Clearance Logo" onerror="this.src='assets/imgs/logo-placeholder.png'">
          <h3>Market Clearance</h3>
        </div>
        <p>Economic Sector</p>
        <p>Public Utilities</p>
        <p>Rene Rosales</p>
        <p>Market Administrator</p>
      </div>

      <!-- Contact Section -->
      <div class="footer-section">
        <h3>Contact Us</h3>
        <p><i class="fas fa-envelope"></i> Daet Public Market Office</p>
        <p><i class="fas fa-map-marker-alt"></i> J. Lukban Street, Bagasbas Road, Brgy. VII, Daet, Philippines</p>
      </div>

      <!-- Connect Section -->
      <div class="footer-section">
        <h3>Connect With Us and About Us</h3>
        <p>Follow us on social media for updates and announcements</p>
        <div class="social-links">
          <a href="https://www.facebook.com/daetpublicmarket" target="_blank" title="Facebook">
            <i class="fab fa-facebook-f"></i>
          </a>
          <a href="about_us.php" title="About Us">
            <i class="fas fa-info-circle"></i>
          </a>
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <p>&copy; Market Clearance System. Daet Public Market Office | <a href="#" style="color: rgba(255, 255, 255, 0.8); text-decoration: none;">Privacy Policy</a> | <a href="#" style="color: rgba(255, 255, 255, 0.8); text-decoration: none;">Terms of Service</a></p>
    </div>
  </footer>


  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const links = document.querySelectorAll(".nav-link");
      const currentPage = window.location.pathname.split("/").pop();

      links.forEach(link => {
        const href = link.getAttribute("href");
        if (href === currentPage) {
          link.classList.add("active");
        } else {
          link.classList.remove("active");
        }
      });
    });

    document.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', () => {
        const navbarCollapse = document.getElementById('navbarSupportedContent');
        const bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse);
        if (bsCollapse) {
          bsCollapse.hide();
        }
      });
    });
  </script>
  <?php
  $cs_contact_href = 'index.php#contact';
  $cs_announcement_href = 'index.php#announcement';
  include __DIR__ . '/includes/mobile_enhancements.php';
  include __DIR__ . '/includes/customer_support_widget.php';
  ?>
</body>
</html>