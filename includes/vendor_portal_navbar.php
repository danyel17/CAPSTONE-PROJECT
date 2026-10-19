<?php
/**
 * Vendor portal top bar + pill nav (matches menu.php).
 * Set $vendor_nav_active before include: home|application|renewal|profile|notification
 */
$vendor_nav_active = isset($vendor_nav_active) ? (string) $vendor_nav_active : 'home';
$unread_notifications_count = (int) ($unread_notifications_count ?? 0);

$vpNavActive = static function (string $page) use ($vendor_nav_active): string {
    return $page === $vendor_nav_active ? ' active' : '';
};
?>
    <div class="navbar-overlay" id="navbarOverlay"></div>

    <nav class="navbar">
      <div class="navbar-content">
        <div class="navbar-left">
          <div class="navbar-logo">
            <span class="brand__mark">
              <img src="assets/imgs/logo.jpeg" alt="Daet Public Market" width="44" height="44">
            </span>
            <span class="brand__stack">
              <span class="brand__text" data-translate="nav.logoTitle">Daet Public Market</span>
              <span class="brand__place">CAMARINES NORTE</span>
            </span>
          </div>
        </div>

        <button class="navbar-toggler" type="button" id="navbarToggler" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon">
            <span></span>
          </span>
        </button>

        <div class="navbar-center" id="navbarCenter">
          <div class="navbar-right">
            <a href="logout.php" class="logout-btn" id="logoutBtn" onclick="return confirmLogout()">
              <i class="fas fa-sign-out-alt"></i> <span data-translate="nav.logout">Logout</span>
            </a>
          </div>
        </div>

        <div class="navbar-right-desktop">
          <a href="logout.php" class="btn-hero-primary logout-btn" id="logoutBtnDesktop" onclick="return confirmLogout()">
            <i class="fas fa-sign-out-alt"></i> <span data-translate="nav.logout">Logout</span>
          </a>
        </div>
      </div>
    </nav>

    <div class="nav-pills-wrap" id="navPillsWrap">
      <div class="nav-pills-container">
        <a href="menu.php" class="nav-pill<?php echo $vpNavActive('home'); ?>" data-translate-title="nav.home" title="Home">
          <i class="fas fa-home" aria-hidden="true"></i> <span class="nav-pill-label" data-translate="nav.home">Home</span>
        </a>
        <a href="application.php" class="nav-pill<?php echo $vpNavActive('application'); ?>" data-translate-title="nav.application" title="Application">
          <i class="fas fa-file-alt" aria-hidden="true"></i> <span class="nav-pill-label" data-translate="nav.application">Application</span>
        </a>
        <a href="renewal.php" class="nav-pill<?php echo $vpNavActive('renewal'); ?>" data-translate-title="nav.renewal" title="Renewal">
          <i class="fas fa-sync-alt" aria-hidden="true"></i> <span class="nav-pill-label" data-translate="nav.renewal">Renewal</span>
        </a>
        <a href="profile.php" class="nav-icon-pill<?php echo $vpNavActive('profile'); ?>" data-translate-title="nav.profile" title="Profile">
          <i class="fas fa-user-circle" aria-hidden="true"></i> <span class="nav-pill-label" data-translate="nav.profile">Profile</span>
        </a>
        <a href="notification.php" class="nav-icon-pill<?php echo $vpNavActive('notification'); ?>" data-translate-title="nav.notifications" title="Notifications">
          <i class="fas fa-bell" aria-hidden="true"></i> <span class="nav-pill-label" data-translate="nav.notifications">Notifications</span>
          <?php if ($unread_notifications_count > 0): ?>
            <span class="nav-icon-badge">
              <?php echo $unread_notifications_count > 9 ? '9+' : $unread_notifications_count; ?>
            </span>
          <?php endif; ?>
        </a>
      </div>
    </div>
