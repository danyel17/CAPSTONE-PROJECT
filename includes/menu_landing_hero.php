<?php
/** Landing-style hero for menu.php (matches samplelandingpage / soda/index.html). */
$menu_hero_first_name = $first_name ?? 'User';
?>
<section class="menu-landing-viewport" id="menuLandingViewport" aria-label="Home">
  <div id="bubbles-container"></div>
  <main class="hero">
    <div class="hero-content">
      <div class="leaves-container">
        <model-viewer class="leaf l1" src="https://api.getlayers.ai/storage/v1/object/public/public/assets/soda-14ff8a788d/leaves.glb" environment-image="neutral" exposure="1.35" shadow-intensity="0.35" interaction-prompt="none" camera-orbit="45deg 75deg 105%"></model-viewer>
        <model-viewer class="leaf l2" src="https://api.getlayers.ai/storage/v1/object/public/public/assets/soda-14ff8a788d/leaves.glb" environment-image="neutral" exposure="1.35" shadow-intensity="0.35" interaction-prompt="none" camera-orbit="-30deg 60deg 105%"></model-viewer>
        <model-viewer class="leaf l3" src="https://api.getlayers.ai/storage/v1/object/public/public/assets/soda-14ff8a788d/leaves.glb" environment-image="neutral" exposure="1.35" shadow-intensity="0.35" interaction-prompt="none" camera-orbit="120deg 85deg 105%"></model-viewer>
        <model-viewer class="leaf l4" src="https://api.getlayers.ai/storage/v1/object/public/public/assets/soda-14ff8a788d/leaves.glb" environment-image="neutral" exposure="1.35" shadow-intensity="0.35" interaction-prompt="none" camera-orbit="10deg 45deg 105%"></model-viewer>
        <model-viewer class="leaf l5" src="https://api.getlayers.ai/storage/v1/object/public/public/assets/soda-14ff8a788d/leaves.glb" environment-image="neutral" exposure="1.35" shadow-intensity="0.35" interaction-prompt="none" camera-orbit="75deg 55deg 105%"></model-viewer>
        <model-viewer class="leaf l6" src="https://api.getlayers.ai/storage/v1/object/public/public/assets/soda-14ff8a788d/leaves.glb" environment-image="neutral" exposure="1.35" shadow-intensity="0.35" interaction-prompt="none" camera-orbit="-90deg 70deg 105%"></model-viewer>
        <model-viewer class="leaf l7" src="https://api.getlayers.ai/storage/v1/object/public/public/assets/soda-14ff8a788d/leaves.glb" environment-image="neutral" exposure="1.35" shadow-intensity="0.35" interaction-prompt="none" camera-orbit="160deg 40deg 105%"></model-viewer>
        <model-viewer class="leaf l8" src="https://api.getlayers.ai/storage/v1/object/public/public/assets/soda-14ff8a788d/leaves.glb" environment-image="neutral" exposure="1.35" shadow-intensity="0.35" interaction-prompt="none" camera-orbit="-15deg 85deg 105%"></model-viewer>
      </div>

      <div class="hero-left">
        <h1 class="main-title" id="heroTitle">
          <span class="outline">Daet Public</span><br>
          <span class="accent">Market</span>
        </h1>
        <p class="description" id="heroDescription">
          Efficient, reliable, seamless. Our market clearance system is designed to stand out, just like your business. Apply, renew, and manage everything in one place.
        </p>
        <div class="hero-buttons">
          <a href="application.php" class="btn-hero-primary" id="applyNowBtn">
            Apply Now <span class="btn-arrow">→</span>
          </a>
          <a href="https://www.facebook.com/daetpublicmarket" target="_blank" rel="noopener noreferrer" class="btn-hero-secondary" id="learnMoreBtn">
            Learn More
          </a>
        </div>
      </div>

      <div class="berries-container-bg">
        <model-viewer class="berry b7" src="soda/models/kenney-pineapple/pineapple.glb" environment-image="neutral" exposure="1.35" shadow-intensity="0.35" interaction-prompt="none" camera-orbit="-20deg 110deg 105%"></model-viewer>
        <model-viewer class="berry b8" src="soda/models/kenney-pineapple/pineapple.glb" environment-image="neutral" exposure="1.35" shadow-intensity="0.35" interaction-prompt="none" camera-orbit="160deg 45deg 105%"></model-viewer>
        <model-viewer class="berry b9" src="soda/models/kenney-pineapple/pineapple.glb" environment-image="neutral" exposure="1.35" shadow-intensity="0.35" interaction-prompt="none" camera-orbit="45deg 20deg 105%"></model-viewer>
      </div>

      <div class="hero-center">
        <model-viewer id="product-model" src="soda/models/palengke-building.glb?v=walk-1" alt="Daet Public Market — Palengke building" camera-controls
          disable-zoom shadow-intensity="0.55" environment-image="neutral" exposure="1.35"
          interaction-prompt="none" camera-orbit="25deg 78deg 175%" field-of-view="32deg"
          class="main-product-3d">
        </model-viewer>
      </div>

      <div class="berries-container">
        <model-viewer class="berry b1" src="soda/models/kenney-pineapple/pineapple.glb" environment-image="neutral" exposure="1.45" shadow-intensity="0.4" interaction-prompt="none" camera-orbit="45deg 120deg 105%"></model-viewer>
        <model-viewer class="berry b2" src="soda/models/kenney-pineapple/pineapple.glb" environment-image="neutral" exposure="1.45" shadow-intensity="0.4" interaction-prompt="none" camera-orbit="-120deg 45deg 105%"></model-viewer>
        <model-viewer class="berry b3" src="soda/models/kenney-pineapple/pineapple.glb" environment-image="neutral" exposure="1.45" shadow-intensity="0.4" interaction-prompt="none" camera-orbit="200deg 90deg 105%"></model-viewer>
        <model-viewer class="berry b4" src="soda/models/kenney-pineapple/pineapple.glb" environment-image="neutral" exposure="1.45" shadow-intensity="0.4" interaction-prompt="none" camera-orbit="10deg 20deg 105%"></model-viewer>
        <model-viewer class="berry b5" src="soda/models/kenney-pineapple/pineapple.glb" environment-image="neutral" exposure="1.45" shadow-intensity="0.4" interaction-prompt="none" camera-orbit="-45deg 160deg 105%"></model-viewer>
        <model-viewer class="berry b6" src="soda/models/kenney-pineapple/pineapple.glb" environment-image="neutral" exposure="1.45" shadow-intensity="0.4" interaction-prompt="none" camera-orbit="80deg 75deg 105%"></model-viewer>
      </div>

      <div class="hero-right">
        <div class="product-carousel">
          <div class="carousel-cards">
            <div class="card active" data-flavor="classic">
              <img src="assets/imgs/displaythree.jpg" alt="Easy application">
              <div class="card-info"><span>easy application</span></div>
            </div>
            <div class="card" data-flavor="blue">
              <img src="assets/imgs/displayfour.jpg" alt="Quick renewal">
              <div class="card-info"><span>quick renewal</span></div>
            </div>
          </div>
        </div>
        <div class="hero-welcome-badge hero-welcome-badge--right">
          <span class="badge-dot"></span>
          <span class="hero-welcome-line"><span id="welcomeText">Welcome,</span> <span id="heroUserName"><?php echo htmlspecialchars(trim($menu_hero_first_name), ENT_QUOTES, 'UTF-8'); ?></span></span>
        </div>
        <div class="hero-right-bottom">
          <h2 class="side-title">
            <span class="outline">Join Our</span><br>
            <span class="side-accent">Market</span>
          </h2>
        </div>
      </div>
    </div>
  </main>
  <button type="button" class="menu-landing-scroll" onclick="document.getElementById('mediaCenter')?.scrollIntoView({behavior:'smooth'})" aria-label="Scroll to videos">
    <span class="menu-landing-scroll-mouse"><span class="menu-landing-scroll-dot"></span></span>
    <span>SCROLL</span>
  </button>
</section>
