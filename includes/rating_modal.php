<?php
/**
 * Shared Client Satisfaction Rating UI.
 * Include once near the end of <body>. Provides:
 *   1. App-Store-style success popup (logo.jpeg + "Rate Us Now")
 *   2. Client Satisfaction Survey modal (profile + 10 criteria + feedback)
 *
 * Set $rating_source = 'application' | 'renewal' before including to tag the source.
 * Call window.showRatingSuccessPopup() to open the success popup.
 */
$rating_source = isset($rating_source) && in_array($rating_source, ['application', 'renewal'], true)
    ? $rating_source
    : 'application';

$rating_criteria = [
    "I am satisfied with the staff/employee's approach to me.",
    "I am satisfied with the employee's responses to my question.",
    "Employees/staff given instructions and information are clear and easy to understand.",
    "The employee acted in a professional manner.",
    "Employees are able to guide and respond to my concerns.",
    "I am able to finish my transaction quickly.",
    "Employees respond in a timely manner.",
    "The given list of requirements are accurate and clearly stated.",
    "The office is neat and clean.",
    "I am satisfied with the overall service provided.",
];

$rating_scale = [
    5 => ['label' => 'Highly Satisfied', 'emoji' => '😄'],
    4 => ['label' => 'Satisfied',        'emoji' => '🙂'],
    3 => ['label' => 'Neutral',          'emoji' => '😐'],
    2 => ['label' => 'Dissatisfied',     'emoji' => '🙁'],
    1 => ['label' => 'Highly Dissatisfied', 'emoji' => '😞'],
];
?>
<!-- ===== Rating: App-Store-style success popup ===== -->
<div class="rate-success-overlay" id="rateSuccessOverlay" aria-hidden="true">
  <div class="rate-success-card" role="dialog" aria-modal="true" aria-labelledby="rateSuccessTitle">
    <img src="assets/imgs/logo.jpeg" alt="Daet Public Market" class="rate-success-logo"
         onerror="this.style.display='none'">
    <h3 class="rate-success-title" id="rateSuccessTitle">Application submitted successfully!</h3>
    <p class="rate-success-sub" data-rt="successSub">Tap a star to rate your experience with us.</p>
    <div class="rate-success-stars" id="rateSuccessStars">
      <button type="button" class="rate-star" data-star="1" aria-label="1 star">&#9734;</button>
      <button type="button" class="rate-star" data-star="2" aria-label="2 stars">&#9734;</button>
      <button type="button" class="rate-star" data-star="3" aria-label="3 stars">&#9734;</button>
      <button type="button" class="rate-star" data-star="4" aria-label="4 stars">&#9734;</button>
      <button type="button" class="rate-star" data-star="5" aria-label="5 stars">&#9734;</button>
    </div>
    <button type="button" class="rate-success-btn" id="rateUsNowBtn" data-rt="rateUsNow">Rate Us Now</button>
    <button type="button" class="rate-success-dismiss" id="rateSuccessDismiss" data-rt="maybeLater">Maybe later</button>
  </div>
</div>

<!-- ===== Rating: Client Satisfaction Survey modal ===== -->
<div class="rate-survey-overlay" id="rateSurveyOverlay" aria-hidden="true">
  <div class="rate-survey-card" role="dialog" aria-modal="true" aria-labelledby="rateSurveyHeading">
    <button type="button" class="rate-survey-close" id="rateSurveyClose" aria-label="Close">&times;</button>

    <div class="rate-survey-gov-head">
      <p>Republic of the Philippines</p>
      <p>Province of Camarines Norte</p>
      <p>Municipality of Daet</p>
      <h2 id="rateSurveyHeading">MARKET DIVISION OFFICE</h2>
      <span class="rate-survey-gov-sub" data-rt="surveySub">Client Satisfaction Feedback</span>
    </div>

    <form id="rateSurveyForm" novalidate>
      <input type="hidden" name="source" value="<?php echo htmlspecialchars($rating_source, ENT_QUOTES, 'UTF-8'); ?>">

      <!-- I. Client's Profile -->
      <section class="rate-survey-section">
        <h4 class="rate-survey-section-title" data-rt="secProfile">I. Client's Profile</h4>

        <div class="rate-field">
          <label for="rateClientName"><span data-rt="fName">Name</span> <span class="rate-optional" data-rt="optional">(Optional)</span></label>
          <input type="text" id="rateClientName" name="client_name" maxlength="255">
        </div>

        <div class="rate-field">
          <span class="rate-field-label" data-rt="fSex">Sex</span>
          <div class="rate-choices">
            <label><input type="radio" name="sex" value="Male"> <span data-rt="male">Male</span></label>
            <label><input type="radio" name="sex" value="Female"> <span data-rt="female">Female</span></label>
          </div>
        </div>

        <div class="rate-field">
          <span class="rate-field-label" data-rt="fAge">Age</span>
          <div class="rate-choices rate-choices-grid">
            <label><input type="radio" name="age_group" value="17 and below"> <span data-rt="age1">17 and below</span></label>
            <label><input type="radio" name="age_group" value="18 and above"> <span data-rt="age2">18 and above</span></label>
            <label><input type="radio" name="age_group" value="19-24"> 19 &ndash; 24</label>
            <label><input type="radio" name="age_group" value="25-39"> 25 &ndash; 39</label>
            <label><input type="radio" name="age_group" value="40-59"> 40 &ndash; 59</label>
            <label><input type="radio" name="age_group" value="60 and above"> <span data-rt="age6">60 and above</span></label>
          </div>
        </div>

        <div class="rate-field">
          <label for="rateAddress" data-rt="fAddress">Address</label>
          <input type="text" id="rateAddress" name="address" maxlength="255">
        </div>

        <div class="rate-field-row">
          <div class="rate-field">
            <label for="rateEmail"><span data-rt="fEmail">Email</span> <span class="rate-optional" data-rt="optional">(Optional)</span></label>
            <input type="email" id="rateEmail" name="email" maxlength="255">
          </div>
          <div class="rate-field">
            <label for="rateContact"><span data-rt="fContact">Contact No.</span> <span class="rate-optional" data-rt="optional">(Optional)</span></label>
            <input type="text" id="rateContact" name="contact_no" maxlength="50">
          </div>
        </div>

        <div class="rate-field">
          <span class="rate-field-label" data-rt="fClass">Client Classification</span>
          <div class="rate-choices">
            <label><input type="radio" name="client_class" value="General Public"> <span data-rt="clsGeneral">General Public</span></label>
            <label><input type="radio" name="client_class" value="Government Employee"> <span data-rt="clsGov">Government Employee</span></label>
            <label><input type="radio" name="client_class" value="Business Organization/Sector"> <span data-rt="clsBiz">Business Organization/Sector</span></label>
          </div>
        </div>

        <div class="rate-field-row">
          <div class="rate-field">
            <label for="rateService" data-rt="fService">Service/s Availed</label>
            <input type="text" id="rateService" name="service_availed" maxlength="255">
          </div>
          <div class="rate-field">
            <label for="rateTxnDate" data-rt="fTxnDate">Date of Transaction</label>
            <input type="date" id="rateTxnDate" name="transaction_date">
          </div>
        </div>
      </section>

      <!-- II. Client Satisfaction Rating -->
      <section class="rate-survey-section">
        <h4 class="rate-survey-section-title" data-rt="secRating">II. Client Satisfaction Rating</h4>
        <p class="rate-survey-directions" data-rt="directions">Kindly rate the quality of the service provided by selecting the appropriate option.</p>

        <div class="rate-legend">
          <?php foreach ($rating_scale as $val => $meta): ?>
            <span class="rate-legend-item"><span class="rate-legend-emoji"><?php echo $meta['emoji']; ?></span> <?php echo htmlspecialchars($meta['label']); ?> (<?php echo $val; ?>)</span>
          <?php endforeach; ?>
        </div>

        <div class="rate-table" role="table">
          <div class="rate-table-head" role="row">
            <span class="rate-table-crit" role="columnheader">Criteria</span>
            <?php foreach ($rating_scale as $val => $meta): ?>
              <span class="rate-table-col" role="columnheader">
                <span class="rate-col-emoji"><?php echo $meta['emoji']; ?></span>
                <span class="rate-col-num"><?php echo $val; ?></span>
              </span>
            <?php endforeach; ?>
          </div>
          <?php foreach ($rating_criteria as $idx => $crit): $qn = $idx + 1; ?>
            <div class="rate-table-row" role="row" data-question="q<?php echo $qn; ?>">
              <span class="rate-table-crit" role="cell"><span class="rate-crit-num"><?php echo $qn; ?>.</span> <?php echo htmlspecialchars($crit); ?></span>
              <?php foreach ($rating_scale as $val => $meta): ?>
                <span class="rate-table-col" role="cell">
                  <label class="rate-radio">
                    <input type="radio" name="q<?php echo $qn; ?>" value="<?php echo $val; ?>">
                    <span class="rate-radio-dot" title="<?php echo htmlspecialchars($meta['label']); ?>"></span>
                  </label>
                </span>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- III. Feedback -->
      <section class="rate-survey-section">
        <h4 class="rate-survey-section-title" data-rt="secFeedback">III. Feedback</h4>
        <label for="rateComments" data-rt="fComments">Comments / Suggestions / Complaints</label>
        <textarea id="rateComments" name="comments" rows="3" maxlength="2000"></textarea>
      </section>

      <div class="rate-survey-actions">
        <p class="rate-survey-thanks" data-rt="thanks">Thank you for your response! &#128522;</p>
        <button type="submit" class="rate-survey-submit" id="rateSurveySubmit">
          <i class="fas fa-paper-plane"></i> <span data-rt="submitRating">Submit Rating</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ===== Rating: Thank-you popup (shown after submitting a rating) ===== -->
<div class="rate-thanks-overlay" id="rateThanksOverlay" aria-hidden="true">
  <div class="rate-thanks-card" role="dialog" aria-modal="true" aria-labelledby="rateThanksTitle">
    <img src="assets/imgs/logo.jpeg" alt="Daet Public Market" class="rate-thanks-logo" onerror="this.style.display='none'">
    <h3 class="rate-thanks-title" id="rateThanksTitle" data-rt="thanksUsing">Thank you for using MCS!</h3>
    <button type="button" class="rate-thanks-ok" id="rateThanksOk" data-rt="okBtn">OK</button>
  </div>
</div>

<style>
  /* ---- Success popup (App Store style) ---- */
  .rate-success-overlay, .rate-survey-overlay {
    position: fixed; inset: 0; z-index: 4000; display: none;
    align-items: center; justify-content: center; padding: 1rem;
    background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(3px);
  }
  .rate-success-overlay.is-open, .rate-survey-overlay.is-open { display: flex; }

  /* Light mode: liquid glass (matches the "Submit all" glass in application.php) */
  .rate-success-card {
    width: min(92vw, 360px);
    background: rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(14px) saturate(160%);
    -webkit-backdrop-filter: blur(14px) saturate(160%);
    border: 1px solid rgba(255, 255, 255, 0.35);
    border-radius: 22px; padding: 1.8rem 1.5rem 1.4rem; text-align: center;
    box-shadow: 0 30px 70px rgba(0,0,0,0.35); color: #fff; animation: ratePop 0.25s ease;
  }
  /* Dark mode: keep the solid dark panel */
  body.darkmode .rate-success-card {
    background: linear-gradient(160deg, #1f2937, #111827);
    backdrop-filter: none;
    -webkit-backdrop-filter: none;
    border: none;
    box-shadow: 0 30px 70px rgba(0,0,0,0.5);
  }
  @keyframes ratePop { from { opacity: 0; transform: scale(0.92); } to { opacity: 1; transform: scale(1); } }
  .rate-success-logo {
    width: 76px; height: 76px; border-radius: 18px; object-fit: cover;
    margin: 0 auto 1rem; display: block; box-shadow: 0 8px 20px rgba(0,0,0,0.4);
  }
  .rate-success-title { font-size: 1.2rem; font-weight: 800; margin: 0 0 0.4rem; line-height: 1.3; }
  .rate-success-sub { font-size: 0.9rem; color: #cbd5e1; margin: 0 0 1.1rem; }
  .rate-success-stars { display: flex; justify-content: center; gap: 0.35rem; margin-bottom: 1.3rem; }
  .rate-star {
    background: none; border: none; cursor: pointer; font-size: 2.1rem; line-height: 1;
    color: #60a5fa; transition: transform 0.12s ease, color 0.12s ease; padding: 0;
  }
  .rate-star:hover { transform: scale(1.15); }
  .rate-star.is-active { color: #fbbf24; }
  .rate-success-btn {
    width: 100%; border: none; border-radius: 12px; padding: 0.85rem 1rem;
    background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff;
    font-weight: 800; font-size: 1rem; cursor: pointer; transition: transform 0.15s ease, box-shadow 0.15s ease;
  }
  .rate-success-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(99,102,241,0.45); }
  .rate-success-dismiss {
    margin-top: 0.75rem; background: none; border: none; color: #94a3b8;
    font-size: 0.88rem; cursor: pointer; text-decoration: underline;
  }

  /* ---- Thank-you popup (liquid glass in light, dark panel in dark) ---- */
  .rate-thanks-overlay {
    position: fixed; inset: 0; z-index: 4100; display: none;
    align-items: center; justify-content: center; padding: 1rem;
    background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(3px);
  }
  .rate-thanks-overlay.is-open { display: flex; }
  .rate-thanks-card {
    width: min(92vw, 340px);
    background: rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(14px) saturate(160%);
    -webkit-backdrop-filter: blur(14px) saturate(160%);
    border: 1px solid rgba(255, 255, 255, 0.35);
    border-radius: 22px; padding: 2rem 1.5rem 1.6rem; text-align: center;
    box-shadow: 0 30px 70px rgba(0,0,0,0.35); color: #fff; animation: ratePop 0.25s ease;
  }
  body.darkmode .rate-thanks-card {
    background: linear-gradient(160deg, #1f2937, #111827);
    backdrop-filter: none; -webkit-backdrop-filter: none; border: none;
    box-shadow: 0 30px 70px rgba(0,0,0,0.5);
  }
  .rate-thanks-logo {
    width: 72px; height: 72px; border-radius: 18px; object-fit: cover;
    margin: 0 auto 0.9rem; display: block; box-shadow: 0 8px 20px rgba(0,0,0,0.4);
  }
  .rate-thanks-title { font-size: 1.3rem; font-weight: 800; margin: 0 0 1.4rem; line-height: 1.3; }
  .rate-thanks-ok {
    width: 100%; border: none; border-radius: 12px; padding: 0.85rem 1rem;
    background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff;
    font-weight: 800; font-size: 1rem; cursor: pointer; transition: transform 0.15s ease, box-shadow 0.15s ease;
  }
  .rate-thanks-ok:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(99,102,241,0.45); }

  /* ---- Survey modal ---- */
  .rate-survey-card {
    width: min(96vw, 720px); max-height: 92vh; overflow-y: auto; background: #fff;
    border-radius: 16px; padding: 1.6rem 1.5rem 1.4rem; position: relative;
    box-shadow: 0 30px 70px rgba(0,0,0,0.4); animation: ratePop 0.25s ease; color: #1e293b;
  }
  .rate-survey-close {
    position: absolute; top: 0.7rem; right: 0.9rem; background: none; border: none;
    font-size: 1.9rem; line-height: 1; color: #94a3b8; cursor: pointer;
  }
  .rate-survey-close:hover { color: #475569; }
  .rate-survey-gov-head { text-align: center; margin-bottom: 1.2rem; }
  .rate-survey-gov-head p { margin: 0; font-size: 0.82rem; color: #475569; line-height: 1.35; }
  .rate-survey-gov-head h2 { margin: 0.3rem 0 0; font-size: 1.15rem; font-weight: 800; color: #1e3a8a; letter-spacing: 0.02em; }
  .rate-survey-gov-sub { display: inline-block; margin-top: 0.15rem; font-size: 0.8rem; color: #64748b; border-top: 1px solid #cbd5e1; padding-top: 0.15rem; }

  .rate-survey-section { margin-bottom: 1.4rem; }
  .rate-survey-section-title {
    font-size: 0.98rem; font-weight: 800; color: #1e293b; margin: 0 0 0.8rem;
    padding-bottom: 0.35rem; border-bottom: 2px solid #e2e8f0;
  }
  .rate-survey-directions { font-size: 0.82rem; color: #64748b; margin: 0 0 0.7rem; }

  .rate-field { margin-bottom: 0.85rem; }
  .rate-field-row { display: flex; gap: 1rem; flex-wrap: wrap; }
  .rate-field-row .rate-field { flex: 1; min-width: 200px; }
  .rate-field label, .rate-field-label { display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 0.3rem; }
  .rate-optional { font-weight: 500; color: #94a3b8; }
  .rate-field input[type="text"], .rate-field input[type="email"], .rate-field input[type="date"], .rate-survey-card textarea {
    width: 100%; padding: 0.55rem 0.7rem; border: 1px solid #cbd5e1; border-radius: 8px;
    font-size: 0.9rem; color: #1e293b; background: #fff; font-family: inherit;
  }
  .rate-field input:focus, .rate-survey-card textarea:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
  .rate-choices { display: flex; flex-wrap: wrap; gap: 0.5rem 1.2rem; }
  .rate-choices.rate-choices-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); }
  .rate-choices label { display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; font-weight: 500; color: #334155; margin: 0; cursor: pointer; }

  .rate-legend { display: flex; flex-wrap: wrap; gap: 0.4rem 0.9rem; margin-bottom: 0.8rem; font-size: 0.75rem; color: #475569; }
  .rate-legend-item { display: inline-flex; align-items: center; gap: 0.25rem; }
  .rate-legend-emoji { font-size: 0.95rem; }

  .rate-table { border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
  .rate-table-head, .rate-table-row { display: grid; grid-template-columns: minmax(0,1fr) repeat(5, 46px); align-items: center; }
  .rate-table-head { background: #f1f5f9; font-weight: 700; }
  .rate-table-row { border-top: 1px solid #e2e8f0; }
  .rate-table-row:nth-child(even) { background: #fafbfc; }
  .rate-table-crit { padding: 0.55rem 0.6rem; font-size: 0.82rem; color: #334155; line-height: 1.3; }
  .rate-crit-num { font-weight: 700; color: #6366f1; }
  .rate-table-col { text-align: center; padding: 0.4rem 0; }
  .rate-col-emoji { display: block; font-size: 1rem; line-height: 1; }
  .rate-col-num { display: block; font-size: 0.7rem; color: #64748b; font-weight: 700; }
  .rate-radio { display: inline-flex; cursor: pointer; }
  .rate-radio input { position: absolute; opacity: 0; width: 0; height: 0; }
  .rate-radio-dot { width: 20px; height: 20px; border: 2px solid #94a3b8; border-radius: 50%; display: inline-block; transition: all 0.12s ease; }
  .rate-radio:hover .rate-radio-dot { border-color: #6366f1; }
  .rate-radio input:checked + .rate-radio-dot { border-color: #4f46e5; background: #4f46e5; box-shadow: inset 0 0 0 3px #fff; }
  .rate-table-row.rate-row-error { background: #fef2f2; }

  .rate-survey-actions { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-top: 0.5rem; }
  .rate-survey-thanks { margin: 0; font-size: 0.85rem; color: #64748b; }
  .rate-survey-submit {
    border: none; border-radius: 10px; padding: 0.8rem 1.5rem;
    background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff;
    font-weight: 800; font-size: 0.95rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
  }
  .rate-survey-submit:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(99,102,241,0.4); }
  .rate-survey-submit:disabled { opacity: 0.6; cursor: not-allowed; }

  /* ---- Survey modal: dark mode ---- */
  body.darkmode .rate-survey-card {
    background: linear-gradient(160deg, #1f2937, #111827);
    color: #e2e8f0;
    box-shadow: 0 30px 70px rgba(0,0,0,0.6);
  }
  body.darkmode .rate-survey-close { color: #94a3b8; }
  body.darkmode .rate-survey-close:hover { color: #e2e8f0; }
  body.darkmode .rate-survey-gov-head p { color: #cbd5e1; }
  body.darkmode .rate-survey-gov-head h2 { color: #93c5fd; }
  body.darkmode .rate-survey-gov-sub { color: #94a3b8; border-top-color: #334155; }
  body.darkmode .rate-survey-section-title { color: #f1f5f9; border-bottom-color: #334155; }
  body.darkmode .rate-survey-directions { color: #94a3b8; }
  body.darkmode .rate-field label,
  body.darkmode .rate-field-label { color: #cbd5e1; }
  body.darkmode .rate-optional { color: #64748b; }
  body.darkmode .rate-field input[type="text"],
  body.darkmode .rate-field input[type="email"],
  body.darkmode .rate-field input[type="date"],
  body.darkmode .rate-survey-card textarea {
    background: #0f172a; border-color: #334155; color: #e2e8f0;
  }
  body.darkmode .rate-field input:focus,
  body.darkmode .rate-survey-card textarea:focus {
    border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.25);
  }
  body.darkmode .rate-choices label { color: #cbd5e1; }
  body.darkmode .rate-legend { color: #94a3b8; }
  body.darkmode .rate-table { border-color: #334155; }
  body.darkmode .rate-table-head { background: #0f172a; }
  body.darkmode .rate-table-row { border-top-color: #334155; }
  body.darkmode .rate-table-row:nth-child(even) { background: rgba(255,255,255,0.03); }
  body.darkmode .rate-table-crit { color: #cbd5e1; }
  body.darkmode .rate-crit-num { color: #93c5fd; }
  body.darkmode .rate-col-num { color: #94a3b8; }
  body.darkmode .rate-radio-dot { border-color: #64748b; }
  body.darkmode .rate-radio:hover .rate-radio-dot { border-color: #818cf8; }
  body.darkmode .rate-radio input:checked + .rate-radio-dot { border-color: #6366f1; background: #6366f1; box-shadow: inset 0 0 0 3px #111827; }
  body.darkmode .rate-table-row.rate-row-error { background: rgba(239,68,68,0.15); }
  body.darkmode .rate-survey-thanks { color: #94a3b8; }

  @media (max-width: 560px) {
    .rate-table-head, .rate-table-row { grid-template-columns: minmax(0,1fr) repeat(5, 34px); }
    .rate-table-col { padding: 0.3rem 0; }
    .rate-radio-dot { width: 17px; height: 17px; }
  }
</style>

<script>
(function () {
  var successOverlay = document.getElementById('rateSuccessOverlay');
  var surveyOverlay = document.getElementById('rateSurveyOverlay');
  var thanksOverlay = document.getElementById('rateThanksOverlay');
  if (!successOverlay || !surveyOverlay) return;

  // ---- Bilingual (EN / PH) support for the rating UI ----
  var RATE_I18N = {
    en: {
      successSub: 'Tap a star to rate your experience with us.',
      rateUsNow: 'Rate Us Now', maybeLater: 'Maybe later',
      surveySub: 'Client Satisfaction Feedback',
      secProfile: "I. Client's Profile", secRating: 'II. Client Satisfaction Rating', secFeedback: 'III. Feedback',
      fName: 'Name', optional: '(Optional)', fSex: 'Sex', male: 'Male', female: 'Female',
      fAge: 'Age', age1: '17 and below', age2: '18 and above', age6: '60 and above',
      fAddress: 'Address', fEmail: 'Email', fContact: 'Contact No.',
      fClass: 'Client Classification', clsGeneral: 'General Public', clsGov: 'Government Employee', clsBiz: 'Business Organization/Sector',
      fService: 'Service/s Availed', fTxnDate: 'Date of Transaction',
      directions: 'Kindly rate the quality of the service provided by selecting the appropriate option.',
      fComments: 'Comments / Suggestions / Complaints',
      thanks: 'Thank you for your response! \uD83D\uDE0A', submitRating: 'Submit Rating',
      thanksUsing: 'Thank you for using MCS!', okBtn: 'OK'
    },
    ph: {
      successSub: 'Pindutin ang bituin upang i-rate ang inyong karanasan sa amin.',
      rateUsNow: 'I-rate Kami Ngayon', maybeLater: 'Sa susunod na lang',
      surveySub: 'Puna sa Kasiyahan ng Kliyente',
      secProfile: 'I. Profile ng Kliyente', secRating: 'II. Rating ng Kasiyahan ng Kliyente', secFeedback: 'III. Puna',
      fName: 'Pangalan', optional: '(Opsyonal)', fSex: 'Kasarian', male: 'Lalaki', female: 'Babae',
      fAge: 'Edad', age1: '17 pababa', age2: '18 pataas', age6: '60 pataas',
      fAddress: 'Tirahan', fEmail: 'Email', fContact: 'Contact No.',
      fClass: 'Klasipikasyon ng Kliyente', clsGeneral: 'Pangkalahatang Publiko', clsGov: 'Empleyado ng Gobyerno', clsBiz: 'Negosyo/Organisasyon',
      fService: 'Serbisyong Ginamit', fTxnDate: 'Petsa ng Transaksyon',
      directions: 'Mangyaring i-rate ang kalidad ng serbisyong ibinigay sa pamamagitan ng pagpili ng naaangkop na opsyon.',
      fComments: 'Mga Puna / Mungkahi / Reklamo',
      thanks: 'Salamat sa inyong tugon! \uD83D\uDE0A', submitRating: 'Isumite ang Rating',
      thanksUsing: 'Salamat sa paggamit ng MCS!', okBtn: 'OK'
    }
  };
  function rateLang() {
    try { return (localStorage.getItem('preferredLanguage') === 'ph') ? 'ph' : 'en'; } catch (e) { return 'en'; }
  }
  function applyRateLang() {
    var pack = RATE_I18N[rateLang()] || RATE_I18N.en;
    [successOverlay, surveyOverlay, thanksOverlay].forEach(function (root) {
      if (!root) return;
      root.querySelectorAll('[data-rt]').forEach(function (el) {
        var key = el.getAttribute('data-rt');
        if (pack[key] != null) el.textContent = pack[key];
      });
    });
  }
  // Re-translate when the site language changes elsewhere.
  window.addEventListener('storage', function (e) { if (e.key === 'preferredLanguage') applyRateLang(); });
  var _origChangeLang = window.changeLanguage;
  if (typeof _origChangeLang === 'function') {
    window.changeLanguage = function (lang) { _origChangeLang.apply(this, arguments); applyRateLang(); };
  }
  applyRateLang();

  var stars = Array.prototype.slice.call(document.querySelectorAll('#rateSuccessStars .rate-star'));
  var preselectedStars = 0;

  function openSuccess() {
    applyRateLang();
    successOverlay.classList.add('is-open');
    successOverlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }
  function closeSuccess() {
    successOverlay.classList.remove('is-open');
    successOverlay.setAttribute('aria-hidden', 'true');
    if (!surveyOverlay.classList.contains('is-open')) document.body.style.overflow = '';
  }
  function paintStars(n) {
    stars.forEach(function (s) {
      var v = parseInt(s.getAttribute('data-star'), 10);
      s.innerHTML = v <= n ? '&#9733;' : '&#9734;';
      s.classList.toggle('is-active', v <= n);
    });
  }

  stars.forEach(function (s) {
    s.addEventListener('mouseenter', function () { paintStars(parseInt(s.getAttribute('data-star'), 10)); });
    s.addEventListener('mouseleave', function () { paintStars(preselectedStars); });
    s.addEventListener('click', function () {
      preselectedStars = parseInt(s.getAttribute('data-star'), 10);
      paintStars(preselectedStars);
      openSurvey();
    });
  });

  function openSurvey() {
    applyRateLang();
    closeSuccess();
    surveyOverlay.classList.add('is-open');
    surveyOverlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    // Note: we intentionally do NOT prefill the overall (Q10) criterion from the
    // star tap — the user should choose every rating themselves.
  }
  function closeSurvey() {
    surveyOverlay.classList.remove('is-open');
    surveyOverlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  function openThanks() {
    if (!thanksOverlay) return;
    applyRateLang();
    thanksOverlay.classList.add('is-open');
    thanksOverlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }
  function closeThanks() {
    if (!thanksOverlay) return;
    thanksOverlay.classList.remove('is-open');
    thanksOverlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  var rateUsBtn = document.getElementById('rateUsNowBtn');
  var dismissBtn = document.getElementById('rateSuccessDismiss');
  var surveyClose = document.getElementById('rateSurveyClose');
  var thanksOk = document.getElementById('rateThanksOk');
  if (rateUsBtn) rateUsBtn.addEventListener('click', openSurvey);
  if (dismissBtn) dismissBtn.addEventListener('click', closeSuccess);
  if (surveyClose) surveyClose.addEventListener('click', closeSurvey);
  if (thanksOk) thanksOk.addEventListener('click', closeThanks);

  successOverlay.addEventListener('click', function (e) { if (e.target === successOverlay) closeSuccess(); });
  surveyOverlay.addEventListener('click', function (e) { if (e.target === surveyOverlay) closeSurvey(); });
  if (thanksOverlay) thanksOverlay.addEventListener('click', function (e) { if (e.target === thanksOverlay) closeThanks(); });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (thanksOverlay && thanksOverlay.classList.contains('is-open')) closeThanks();
    else if (surveyOverlay.classList.contains('is-open')) closeSurvey();
    else if (successOverlay.classList.contains('is-open')) closeSuccess();
  });

  var form = document.getElementById('rateSurveyForm');
  var submitBtn = document.getElementById('rateSurveySubmit');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      // Require at least one criterion answered
      var answered = form.querySelectorAll('input[type="radio"][name^="q"]:checked').length;
      if (answered === 0) {
        alert('Please rate at least one criterion before submitting.');
        return;
      }
      var original = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
      var fd = new FormData(form);
      fetch('submit_rating.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) throw new Error(data.message || 'Failed to submit.');
          submitBtn.disabled = false;
          submitBtn.innerHTML = original;
          closeSurvey();
          openThanks();
          form.reset();
        })
        .catch(function (err) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = original;
          alert('Error: ' + err.message);
        });
    });
  }

  var TITLE_I18N = {
    'Application submitted successfully!': { en: 'Application submitted successfully!', ph: 'Matagumpay na naisumite ang aplikasyon!' },
    'Renewal submitted successfully!': { en: 'Renewal submitted successfully!', ph: 'Matagumpay na naisumite ang renewal!' }
  };

  // Public trigger
  window.showRatingSuccessPopup = function (title) {
    var t = document.getElementById('rateSuccessTitle');
    if (t && title) {
      var map = TITLE_I18N[title];
      t.textContent = map ? (map[rateLang()] || map.en) : title;
    }
    preselectedStars = 0;
    paintStars(0);
    openSuccess();
  };
  window.showRatingSurvey = openSurvey;
})();
</script>
