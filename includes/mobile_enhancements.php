<?php /* Shared defensive mobile refinements for vendor-facing pages. Kept conservative so it augments (not overrides) each page's own responsive CSS. */ ?>
<style>
  /* Prevent accidental horizontal scrolling from wide children */
  html, body { max-width: 100%; }
  body { overflow-x: clip; }
  @media (max-width: 768px) {
    html, body { overflow-x: visible; }
  }

  /* Media never overflows its container on any screen */
  img, canvas, svg, iframe { max-width: 100%; }
  video { max-width: 100%; }
  .about-notice-video video, #aboutNoticeVideo {
    max-width: none;
    width: 100%;
    height: 100%;
  }
  @media (max-width: 768px) {
    .about-notice-video {
      max-width: none !important;
    }
    .about-notice-video video,
    #aboutNoticeVideo {
      max-width: none !important;
      width: 100% !important;
      height: 100% !important;
      object-fit: cover !important;
    }
  }

  @media (max-width: 576px) {
    /* Avoid iOS auto-zoom on focus by keeping inputs >= 16px */
    input, select, textarea { font-size: 16px; }

    /* Comfortable tap targets */
    button, .btn, a.btn, .nav-pill, .nav-icon-pill { min-height: 42px; }

    /* Bootstrap modals sit flush with small screens */
    .modal-dialog { margin: 0.5rem; }
    .modal-body { max-height: 78vh; overflow-y: auto; }
  }

  @media (max-width: 400px) {
    /* Reduce oversized hero/section headings on very small phones */
    h1 { font-size: clamp(1.4rem, 8vw, 2rem); }
  }
</style>
