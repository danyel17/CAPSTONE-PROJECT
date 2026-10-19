/**
 * Full Tagalog for registrationform.php and promotional-permit.php (top bar preserved via data-lang-preserve="en").
 */
(function (global) {
  'use strict';

  var T = global.dpmI18nTouch || {};
  var touchText = T.touchText || function () {};
  var touchHtml = T.touchHtml || function () {};
  var touchPh = T.touchPlaceholder || function () {};

  global.REG_STEP_I18N = {
    en: {
      titles: { 1: 'Register here', 2: 'Almost there', 3: 'Secure your account' },
      hints: {
        1: 'Step 1 of 3 — name, email & phone',
        2: 'Step 2 of 3 — age, gender & address',
        3: 'Step 3 of 3 — create your password'
      }
    },
    ph: {
      titles: { 1: 'Magrehistro dito', 2: 'Malapit na', 3: 'I-secure ang iyong account' },
      hints: {
        1: 'Hakbang 1 ng 3 — pangalan, email at telepono',
        2: 'Hakbang 2 ng 3 — edad, kasarian at tirahan',
        3: 'Hakbang 3 ng 3 — gumawa ng password'
      }
    }
  };

  global.regApplyStepCopy = function (step) {
    var lang = 'en';
    try { lang = global.localStorage.getItem('preferredLanguage') || 'en'; } catch (e) {}
    var pack = global.REG_STEP_I18N[lang] || global.REG_STEP_I18N.en;
    var title = document.getElementById('stepTitle');
    var hint = document.getElementById('stepHint');
    if (title && pack.titles[step]) title.textContent = pack.titles[step];
    if (hint && pack.hints[step]) hint.textContent = pack.hints[step];
  };

  global.regPwStrengthLabel = function (met, empty) {
    var lang = 'en';
    try { lang = global.localStorage.getItem('preferredLanguage') || 'en'; } catch (e) {}
    if (empty) return '—';
    if (lang === 'ph') {
      if (met >= 4) return 'Malakas';
      if (met >= 2) return 'Katamtaman';
      return 'Mahina';
    }
    if (met >= 4) return 'Strong';
    if (met >= 2) return 'Medium';
    return 'Weak';
  };

  function translateRegistration(lang) {
    global.regApplyStepCopy(global.__regCurrentStep || 1);

    touchText(document.querySelector('.overlay-left h2'), lang, 'Simulan ang iyong paglalakbay ngayon');
    touchText(document.querySelector('.overlay-left p'), lang, 'Kung wala ka pang account, sumali at simulan ang iyong paglalakbay.');
    touchHtml(document.querySelector('#showRegisterBtn'), lang, 'Magrehistro <i class="fas fa-arrow-right"></i>');
    touchText(document.querySelector('.overlay-right h2'), lang, 'Maligayang pagdating sa MCS');
    touchText(document.querySelector('.overlay-right p'), lang, 'Kung may account ka na, mag-login dito at ipagpatuloy ang iyong market clearance journey.');
    touchHtml(document.querySelector('#showLoginBtn'), lang, 'Mag-login <i class="fas fa-arrow-right"></i>');

    touchText(document.querySelector('.sign-in-container h1'), lang, 'Mag-login dito');
    touchText(document.querySelector('.sign-in-container .step-hint'), lang, 'Maligayang pagbabalik — mag-sign in para magpatuloy');

    var labelMap = {
      'Full Name': 'Buong Pangalan',
      Email: 'Email',
      'Phone Number': 'Numero ng Telepono',
      Age: 'Edad',
      Gender: 'Kasarian',
      Password: 'Password',
      'Confirm Password': 'Kumpirmahin ang Password',
      'Residential Address': 'Tirahan'
    };
    document.querySelectorAll('.sign-up-container .field > label, .sign-in-container .field > label').forEach(function (el) {
      var en = el.getAttribute('data-dpm-en') || el.textContent.trim();
      if (!el.hasAttribute('data-dpm-en')) el.setAttribute('data-dpm-en', en);
      el.textContent = (lang === 'ph' && labelMap[en]) ? labelMap[en] : en;
    });

    touchPh(document.getElementById('fullname'), lang, 'Iyong buong pangalan');
    touchPh(document.getElementById('email'), lang, 'example@gmail.com');
    touchPh(document.getElementById('phone_number'), lang, '09*********');
    touchPh(document.getElementById('age'), lang, 'hal. 25');
    touchPh(document.getElementById('residential_address'), lang, 'Buong tirahan');
    touchPh(document.getElementById('password'), lang, 'Min. 8 character');
    touchPh(document.getElementById('confirm_password'), lang, 'Ulitin ang password');
    touchPh(document.getElementById('login_email'), lang, 'Ilagay ang iyong email');
    touchPh(document.getElementById('login_password'), lang, 'Ilagay ang iyong password');

    var genderSel = document.getElementById('gender');
    if (genderSel) {
      Array.prototype.forEach.call(genderSel.options, function (opt) {
        if (!opt.value) {
          touchText(opt, lang, 'Pumili ng kasarian');
          return;
        }
        var gm = { Male: 'Lalaki', Female: 'Babae', 'Prefer not to say': 'Mas pipiliin na huwag sabihin' };
        if (!opt.hasAttribute('data-dpm-en')) opt.setAttribute('data-dpm-en', opt.textContent);
        opt.textContent = (lang === 'ph' && gm[opt.value]) ? gm[opt.value] : opt.getAttribute('data-dpm-en');
      });
    }

    touchText(document.querySelector('.pw-strength__label'), lang, 'Lakas ng Password');
    document.querySelectorAll('.pw-rule').forEach(function (el) {
      var rule = el.getAttribute('data-rule');
      var ph = { len: '8 Character', upper: 'A-Z', lower: 'a-z', num: '123' };
      if (!el.hasAttribute('data-dpm-en-html')) el.setAttribute('data-dpm-en-html', el.innerHTML);
      if (lang === 'ph' && rule && ph[rule]) {
        el.innerHTML = '<i class="fas fa-check"></i> ' + ph[rule];
      } else if (lang === 'en') {
        el.innerHTML = el.getAttribute('data-dpm-en-html');
      }
    });

    var termsLabel = document.querySelector('label[for="terms_accepted"]');
    if (termsLabel) {
      if (!termsLabel.hasAttribute('data-dpm-en-html')) {
        termsLabel.setAttribute('data-dpm-en-html', termsLabel.innerHTML);
      }
      if (lang === 'ph') {
        termsLabel.innerHTML = 'Sumasang-ayon ako sa <a href="#" id="openTermsLink">Mga Tuntunin at Kundisyon</a>';
      } else {
        termsLabel.innerHTML = termsLabel.getAttribute('data-dpm-en-html');
      }
    }

    touchText(document.getElementById('btnNext'), lang, 'Susunod');
    touchText(document.getElementById('btnRegister'), lang, 'Magrehistro');
    touchText(document.querySelector('.btn-login-submit'), lang, 'Mag-login');
    touchText(document.querySelector('.login-options label'), lang, 'Tandaan ako');
    touchText(document.querySelector('.login-options a'), lang, 'Nakalimutan ang password?');

    touchText(document.querySelector('#termsModal h2'), lang, 'Mga Tuntunin at Kundisyon');
    touchText(document.querySelector('.terms-modal-body h3:nth-of-type(1)'), lang, '1. Pagtanggap sa mga Tuntunin');
    touchText(document.querySelector('.terms-modal-body p:nth-of-type(1)'), lang, 'Sa pag-access at paggamit ng Daet Public Market Office - Market Clearance System (MCS), sumasang-ayon kang sumunod sa mga tuntunin ng kasunduang ito.');
    touchText(document.querySelector('.terms-modal-body h3:nth-of-type(2)'), lang, '2. Pagrehistro ng Account');
    touchText(document.querySelector('.terms-modal-body p:nth-of-type(2)'), lang, 'Sumasang-ayon kang magbigay ng tamang impormasyon, panatilihin ang seguridad ng password, at tanggapin ang responsibilidad sa aktibidad sa iyong account.');
    touchText(document.querySelector('.terms-modal-body h3:nth-of-type(3)'), lang, '3. Responsibilidad ng Vendor');
    document.querySelectorAll('.terms-modal-body ul:first-of-type li').forEach(function (li, i) {
      var ph = [
        'Magbigay ng tamang impormasyon at tunay na dokumento',
        'Sumunod sa mga regulasyon ng palengke',
        'Panatilihing lihim ang credentials ng account'
      ];
      touchText(li, lang, ph[i]);
    });
    touchText(document.querySelector('.terms-modal-body h3:nth-of-type(4)'), lang, '4. Proseso ng Aplikasyon');
    touchText(document.querySelector('.terms-modal-body p:nth-of-type(3)'), lang, 'Lahat ng aplikasyon ay sinusuri ng DPMO. Maling impormasyon ay maaaring magresulta sa pagtanggi o suspensyon ng account.');
    touchText(document.querySelector('.terms-modal-body h3:nth-of-type(5)'), lang, '5. Privacy ng Data');
    touchText(document.querySelector('.terms-modal-body p:nth-of-type(4)'), lang, 'Ang personal na impormasyon ay ginagamit para sa market clearance at ligtas na iniimbak alinsunod sa batas sa proteksyon ng data.');
    touchText(document.querySelector('.terms-modal-body h3:nth-of-type(6)'), lang, '6. Pakikipag-ugnayan');
    touchText(document.querySelector('.terms-modal-body p:nth-of-type(5)'), lang, 'Para sa mga tanong, makipag-ugnayan sa Daet Public Market Office sa opisyal na mga channel.');
    touchText(document.querySelector('.terms-modal-btn'), lang, 'Naiintindihan Ko');

    touchText(document.querySelector('#successPopup h2'), lang, 'Matagumpay na Nagawa ang Account');
    touchText(document.querySelector('#successPopup p'), lang, 'Ire-redirect sa home page...');

    document.querySelectorAll('.eye-btn').forEach(function (btn) {
      touchText(btn, lang, lang === 'ph' ? 'Ipakita ang password' : 'Show password');
      btn.setAttribute('aria-label', lang === 'ph' ? 'Ipakita ang password' : 'Show password');
    });
    var backBtn = document.getElementById('btnBack');
    if (backBtn) {
      backBtn.setAttribute('title', lang === 'ph' ? 'Bumalik' : 'Back');
      backBtn.setAttribute('aria-label', lang === 'ph' ? 'Bumalik' : 'Back');
    }

    var pw = document.getElementById('password');
    if (pw) pw.dispatchEvent(new Event('input', { bubbles: true }));
  }

  var PROMO_TICKER_PH = [
    'Promotional permit sa loob ng 12 minuto',
    '108 CCTV unit para sa kaligtasan ng palengke',
    'One-stop market office para sa vendor at kliyente',
    'Timbangan ng Bayan para sa tamang timbang',
    'LED wall at banner ads para sa promosyon sa loob ng palengke',
    'Rehistrasyon ng stall at pag-renew ng permit sa ilang hakbang'
  ];

  function translatePromotionalPermit(lang) {
    touchText(document.querySelector('.hero__badge'), lang, 'Daet, Camarines Norte');
    touchHtml(document.querySelector('.hero h1'), lang,
      'Lahat ng kailangan mo sa Daet Public Market <span class="hero__word" id="heroWord"><span>Mga Permit</span></span>');
    touchText(document.querySelector('.hero__inner > p'), lang,
      'Malinaw na requirements, transparent na bayad, at gabay na hakbang-hakbang — para sa vendor, negosyante, at mamimili. Sumusunod, mabilis, at maaaring maproseso sa loob ng 12 minuto.');
    touchHtml(document.querySelector('.hero .btn-solid'), lang, 'Tuklasin ang Serbisyo ng Palengke <i class="fas fa-arrow-right"></i>');
    touchHtml(document.querySelector('.hero .btn-ghost'), lang, '<i class="far fa-file-lines"></i> Mga Permit at Bayad');
    /* Hero stats: value line + detail phrase (EN / PH) */
    document.querySelectorAll('.hero .stat').forEach(function (stat, i) {
      var enStrong = ['Market', '12 min', '108', 'Php 0'];
      var enDetail = ['Office services', 'Average processing', 'CCTV units active', 'Permit processing fee'];
      var phDetail = ['Serbisyo ng opisina', 'Karaniwang oras ng proseso', 'Aktibong CCTV unit', 'Bayad sa pagproseso ng permit'];
      var st = stat.querySelector('strong');
      var sp = stat.querySelector('.hero-stat-detail');
      if (st) {
        if (!st.hasAttribute('data-dpm-en')) st.setAttribute('data-dpm-en', st.textContent.trim());
        st.textContent = enStrong[i] || st.getAttribute('data-dpm-en');
      }
      if (sp) {
        if (!sp.hasAttribute('data-dpm-en')) sp.setAttribute('data-dpm-en', sp.textContent.trim());
        sp.textContent = lang === 'ph' ? phDetail[i] : (sp.getAttribute('data-dpm-en') || enDetail[i]);
      }
    });
    touchHtml(document.querySelector('.scroll-cue'), lang, 'MAG-SCROLL <i class="fas fa-chevron-down"></i>');

    document.querySelectorAll('.ticker__item').forEach(function (el, i) {
      var idx = i % PROMO_TICKER_PH.length;
      if (!el.hasAttribute('data-dpm-en-html')) el.setAttribute('data-dpm-en-html', el.innerHTML);
      if (lang === 'ph') {
        el.innerHTML = '<i class="fas fa-star"></i> ' + PROMO_TICKER_PH[idx];
      } else {
        el.innerHTML = el.getAttribute('data-dpm-en-html');
      }
    });

    touchText(document.querySelector('#services .kicker'), lang, 'Mga serbisyo ng palengke');
    touchHtml(document.querySelector('#services .section-title'), lang, 'Ano ang maaari mong gawin sa <em>Daet Market</em>');
    touchText(document.querySelector('#services .section-lead'), lang,
      'Mula sa promotional permit hanggang rehistrasyon ng stall at kaligtasan sa buong palengke — ito ang hinahawakan ng opisina araw-araw para sa vendor at kliyente.');

    var cards = document.querySelectorAll('#services .card');
    var cardData = [
      { pill: 'MGA PERMIT', h: 'Promotional Permit', p: 'Mag-apply para magsagawa ng promotional activity sa loob ng saklaw ng pamamahala ng palengke. Sumusunod at maaaring maproseso sa 12 minuto.', btn: 'Matuto pa' },
      { pill: 'ADVERTISING', h: 'Billboards at Streamers', p: 'Commercial LED wall, banner, at streamer para sa advertising, may malinaw na buwanang bayad at patakaran sa display.', btn: 'Matuto pa' },
      { pill: 'MGA VENDOR', h: 'Rehistrasyon ng Stall', p: 'Bagong at nagre-renew na vendor ay maaaring magrehistro, magbayad ng upa, at makakuha ng puwesto sa trading area.', btn: 'Matuto pa' },
      { pill: 'SEGURIDAD', h: 'CCTV Monitoring', p: '108 camera unit ang sumasaklaw sa stall, daanan, pasukan, at common area para sa kaligtasan.', btn: 'Matuto pa' },
      { pill: 'PATAS NA KALAKALAN', h: 'Timbangan ng Bayan', p: 'Pampublikong timbangan para sa produkto, karne, at isda bago ang bentahan.', btn: 'Matuto pa' },
      { pill: 'TULONG', h: 'Client Assistance Desk', p: 'Tutulungan ka ng staff sa aplikasyon at tanong tungkol sa permit, resibo, at requirements.', btn: 'Matuto pa' }
    ];
    cards.forEach(function (card, i) {
      var d = cardData[i];
      if (!d) return;
      touchText(card.querySelector('.pill'), lang, d.pill);
      touchText(card.querySelector('h3'), lang, d.h);
      touchText(card.querySelector('p'), lang, d.p);
      var link = card.querySelector('.card__link');
      if (link) touchHtml(link, lang, d.btn + ' <i class="fas fa-arrow-right"></i>');
    });

    touchText(document.querySelector('#fees .kicker'), lang, 'Mga permit at bayad');
    touchHtml(document.querySelector('#fees .section-title'), lang, 'Malinaw na bayad para sa ads <em>sa loob ng palengke</em>');
    touchText(document.querySelector('#fees .section-lead'), lang,
      'Commercial billboard at streamer para sa advertising sa loob ng saklaw ng pamamahala ng palengke.');

    document.querySelectorAll('#fees .fee').forEach(function (fee, i) {
      var blocks = [
        { sm: 'LED WALL AD. DISPLAY', h: 'LED Wall Advertisement', p: 'Karagdagang requirement: electronic copy ng advertisement para sa display.', tail: 'Para sa 2 oras na playback bawat araw.', link: 'Mag-apply para sa ad na ito' },
        { sm: 'LED SCROLLER / BANNER ADS', h: 'LED Scroller / Banner Advertisement', p: 'Karagdagang requirement: kopya ng impormasyon para sa display.', tail: 'Hindi bababa sa tatlumpung (30) character, 2-oras na scroll bawat araw.', link: 'Mag-apply para sa ad na ito' },
        { sm: 'PROMOTIONAL OFFICIAL RECEIPT', h: 'Promotional Official Receipt', p: 'Karagdagang requirement: 1 original at 1 photocopy ng official receipt.', tail: 'Bayad sa Municipal Treasury Office.', link: 'Tingnan ang requirements' }
      ];
      var b = blocks[i];
      if (!b) return;
      touchText(fee.querySelector('.fee__top small'), lang, b.sm);
      touchText(fee.querySelector('h3'), lang, b.h);
      var ps = fee.querySelectorAll('p');
      if (ps[0]) touchText(ps[0], lang, b.p);
      if (ps[1]) touchText(ps[1], lang, b.tail);
      var a = fee.querySelector('a');
      if (a) touchHtml(a, lang, b.link + ' <i class="fas fa-arrow-right"></i>');
    });

    touchHtml(document.querySelector('#fees .note p'), lang,
      '<i class="fas fa-circle-info"></i> Ang bayad ay ibinabayad sa Municipal Treasury Office. Ipakita ang official receipt sa Market Office kapag nagfa-file at kumukuha ng permit.');
    touchHtml(document.querySelector('#fees .note a'), lang, 'Tingnan ang buong proseso <i class="fas fa-arrow-right"></i>');

    touchText(document.querySelector('#value .kicker'), lang, 'Ano ito');
    touchHtml(document.querySelector('#value .section-title'), lang, 'Pauna bago ang <em>promotional activity</em>');
    touchText(document.querySelector('#value .section-lead'), lang,
      'Kinakailangan ang Market Promotional Permit bago ang anumang promosyon sa loob ng saklaw ng Daet Public Market. Kino-confirm nito ang detalye ng aplikante at pagsunod sa Revised Market Code of 2017.');

    touchText(document.querySelector('#requirements .panel h3'), lang, 'Checklist ng mga requirement');
    document.querySelectorAll('#requirements .checks li').forEach(function (li, i) {
      var ph = [
        'Government-issued ID, para sa presentation',
        'Letter of Intent na may profile ng aplikante',
        'Produkto o kalakal na ipo-promote',
        'Saklaw na petsa at time frame'
      ];
      touchText(li, lang, ph[i]);
    });
    touchText(document.querySelector('#requirements .panel p[style*="WHERE"]'), lang, 'SAAN KUMUHA NG VALID ID');
    touchText(document.querySelector('.dark-panel h3'), lang, 'Saan iproproseso');
    document.querySelectorAll('.dark-panel .row').forEach(function (row, i) {
      var left = [
        'Natanggap at naaprubahang Letter of Intent',
        'Promotional Official Receipt\n1 original, 1 photocopy',
        'Opisina / Dibisyon',
        'Klasipikasyon'
      ];
      var right = ['Opisina ng Mayor', 'Municipal Treasury\nPhp 1,000.00', 'Market Division', 'Complex · G2B & G2C'];
      var l = row.querySelector('span');
      var r = row.querySelector('b');
      if (l) touchText(l, lang, left[i]);
      if (r) touchText(r, lang, right[i]);
    });

    document.querySelectorAll('.avail article').forEach(function (art, i) {
      var titles = [
        'Daet Public Market — Barangay VI',
        'Daet Elevated Town Plaza',
        'Satellite Market — Barangay Lag-on',
        'Satellite Market — Camambugan'
      ];
      var desc = [
        'Kasama ang mga istruktura ng BOT scheme sa paligid nito.',
        'Mga stall sa Build-Operate-Transfer scheme.',
        'Kasama ang mga istruktura ng BOT sa lugar.',
        'Sinumang nagpo-promote sa loob ng premises ng palengke.'
      ];
      touchText(art.querySelector('h4'), lang, titles[i]);
      touchText(art.querySelector('p'), lang, desc[i]);
    });

    touchText(document.querySelector('#process .kicker'), lang, 'Hakbang-hakbang');
    touchHtml(document.querySelector('#process .section-title'), lang, 'Proseso ng kliyente sa <em>12 minuto</em>');
    touchText(document.querySelector('#process .section-lead'), lang,
      'Simple at walang processing charge. Ang bayad sa advertising, kung kinakailangan, ay hiwalay na binabayaran sa treasury.');
    document.querySelectorAll('#process .step').forEach(function (step, i) {
      var h = [
        'Mag-sign in sa Client Log Book',
        'Isumite ang kinakailangang dokumento',
        'Pag-schedule ng naaprubahang activity',
        'Isagawa ang promotional activity'
      ];
      var p = [
        'Mag-sign in sa pasukan ng opisina at sundin ang public health standards. Ibibigay ng staff ang log book.',
        'Titingnan ng staff ang kumpleto, ifa-file ang photocopy, at kokolektahin ang dagdag na requirements para sa billboard at streamer.',
        'Ilalagay ang naaprubahang promosyon sa Calendar of Promotional Activities.',
        'Tutulungan ng market enforcer at titingnan ang pagsunod sa approved permit at kondisyon.'
      ];
      touchText(step.querySelector('h4'), lang, h[i]);
      touchText(step.querySelector('p'), lang, p[i]);
      step.querySelectorAll('.meta span').forEach(function (sp, j) {
        var meta = [['Bayad: Wala', '3 minuto', 'Market Office Staff'], ['Bayad: Wala', '3 minuto', 'Market Supervisor II & Staff'], ['Bayad: Wala', '1 minuto', 'Market Supervisor / Staff'], ['Bayad: Wala', '5 minuto', 'Market Enforcer']];
        touchText(sp, lang, meta[i][j]);
      });
    });
    touchHtml(document.querySelector('#process .total'), lang,
      'Kabuuang processing fee: <strong>Php 0.00</strong> · Kabuuang oras: <strong>12 minuto</strong>');

    touchText(document.querySelector('#charter .kicker'), lang, 'Opisyal na sanggunian');
    touchHtml(document.querySelector('#charter .section-title'), lang, 'Mga slide ng <em>Citizen\'s Charter</em>');
    touchText(document.querySelector('#charter .section-lead'), lang, 'Mag-swipe o mag-click sa opisyal na impormasyon tungkol sa market promotional permit.');

    var capPh = [
      'Ano ang Market Promotional Permit',
      'Opisina, klasipikasyon at sino ang maaaring mag-avail',
      'Checklist ng mga requirement',
      'Saan iproproseso at mga bayad',
      'LED Wall Ads display',
      'LED Scroller / Banner Ads',
      'Proseso ng kliyente — hakbang 1 at 2',
      'Proseso ng kliyente — hakbang 3 at 4'
    ];
    document.querySelectorAll('#galleryTrack img').forEach(function (img, i) {
      if (!img.hasAttribute('data-dpm-en-cap')) img.setAttribute('data-dpm-en-cap', img.getAttribute('data-caption') || '');
      var cap = lang === 'ph' ? (capPh[i] || img.getAttribute('data-dpm-en-cap')) : img.getAttribute('data-dpm-en-cap');
      img.setAttribute('data-caption', cap);
      img.setAttribute('alt', cap);
    });
    var galleryCaption = document.getElementById('galleryCaption');
    if (galleryCaption && lang === 'ph') {
      var active = document.querySelector('#galleryTrack .gallery-slide');
      if (active) {
        var im = active.querySelector('img');
        if (im) galleryCaption.textContent = im.getAttribute('data-caption') || '';
      }
    }

    touchText(document.querySelector('#faq .kicker'), lang, 'Mainam na malaman');
    touchHtml(document.querySelector('#faq .section-title'), lang, 'Mga madalas itanong na <em>katanungan</em>');
    touchText(document.querySelector('#faq .faq__side > p'), lang, 'Mabilis na sagot sa mga tanong mula sa vendor at aplikante.');
    touchText(document.querySelector('.help strong'), lang, 'May tanong pa?');
    touchText(document.querySelector('.help p'), lang, 'Handang tumulong ang client assistance desk sa oras ng opisina.');
    touchHtml(document.querySelector('.help a'), lang, 'Magpadala ng inquiry <i class="fas fa-arrow-right"></i>');

    var faq = document.querySelectorAll('.faq__item');
    var faqQ = [
      'Ano ang Market Promotional Permit?',
      'Gaano katagal ang proseso?',
      'Saan ako magbabayad ng fees?',
      'Kailangan ba ng permit ang LED walls at streamers?',
      'May CCTV ba ang palengke?'
    ];
    var faqA = [
      'Ito ang dokumentong kailangan bago magsagawa ng promotional activity sa loob ng saklaw ng Daet Public Market.',
      'Mga 12 minuto ang kabuuang hakbang: sign-in, check ng dokumento, scheduling, at inspeksyon ng activity.',
      'Magbayad sa Municipal Treasury Office, pagkatapos ipakita ang official receipt sa Market Office. Walang processing fee ang permit mismo.',
      'Oo. Ang LED wall at scroller/banner ads ay may dagdag na requirements at Php 5,000.00 buwanang display fee.',
      'Oo. 108 CCTV unit ang sumasaklaw sa stall, daanan, pasukan, at common areas.'
    ];
    faq.forEach(function (item, i) {
      touchHtml(item.querySelector('button'), lang, faqQ[i] + ' <i class="fas fa-chevron-down"></i>');
      touchText(item.querySelector('div'), lang, faqA[i]);
    });

    document.querySelectorAll('.service-modal-source').forEach(function (src) {
      if (!src.hasAttribute('data-dpm-en-html')) src.setAttribute('data-dpm-en-html', src.innerHTML);
      if (lang === 'en') src.innerHTML = src.getAttribute('data-dpm-en-html');
    });
    if (lang === 'ph') {
      touchHtml(document.getElementById('modal-src-promo'), lang,
        '<p>Kailangan ang <strong style="color:var(--indigo-deep);">Market Promotional Permit</strong> bago ang promosyon sa loob ng saklaw ng Daet Public Market. Libre ang pagproseso at maaaring umabot sa <strong>12 minuto</strong>.</p><ul><li><i class="fas fa-check"></i> Government-issued ID para sa presentation</li><li><i class="fas fa-check"></i> Letter of Intent na may profile ng aplikante</li><li><i class="fas fa-check"></i> Produkto o kalakal na ipo-promote, kasama ang petsa at oras</li><li><i class="fas fa-check"></i> Promotional Official Receipt mula sa Municipal Treasury kung applicable</li></ul><p><a href="#process" class="service-modal__cta">Tingnan ang buong proseso <i class="fas fa-arrow-right"></i></a></p>');
      touchHtml(document.getElementById('modal-src-ads'), lang,
        '<p>Kailangan ng approval at buwanang bayad ang <strong style="color:var(--indigo-deep);">LED wall, scroller, banner, at streamer</strong> sa loob ng palengke.</p><ul><li><i class="fas fa-check"></i> LED Wall Advertisement — Php 5,000.00 / buwan (2 oras playback bawat araw)</li><li><i class="fas fa-check"></i> LED Scroller / Banner — Php 5,000.00 / buwan (30+ character, 2-oras scroll bawat araw)</li><li><i class="fas fa-check"></i> Isumite ang electronic o printed copy ng ad content</li></ul><p><a href="#fees" class="service-modal__cta">Tingnan ang bayad at requirements <i class="fas fa-arrow-right"></i></a></p>');
      touchHtml(document.getElementById('modal-src-stall'), lang,
        '<p>Ang <strong style="color:var(--indigo-deep);">bagong at nagre-renew na vendor</strong> ay maaaring magrehistro ng stall, magbayad ng upa, at makakuha ng puwesto sa trading area sa vendor portal.</p><ul><li><i class="fas fa-check"></i> Pag-assign ng stall sa Daet Public Market at satellite areas</li><li><i class="fas fa-check"></i> Upa at clearance workflow sa isang lugar</li><li><i class="fas fa-check"></i> Suporta mula sa Market Division</li></ul><p><a href="registrationform.php" class="service-modal__cta">Pumunta sa registration form <i class="fas fa-arrow-right"></i></a></p>');
      touchHtml(document.getElementById('modal-src-cctv'), lang,
        '<p><strong style="color:var(--indigo-deep);">108 camera unit</strong> ang binabantayan ang stall, daanan, pasukan, at common area. Tumutulong ang recording sa insidente at reported concerns.</p><ul><li><i class="fas fa-check"></i> Saklaw sa trading area at mahahalagang pasukan</li><li><i class="fas fa-check"></i> Sumusuporta sa crowd management at seguridad pagkatapos ng oras</li><li><i class="fas fa-check"></i> Tumutulong ipatupad ang patakaran ng palengke at public welfare</li></ul>');
      touchHtml(document.getElementById('modal-src-timbangan'), lang,
        '<p>Ang pampublikong timbangan ay nagbibigay ng neutral na timbang para sa sariwa bago ang bentahan.</p><ul><li><i class="fas fa-check"></i> Para sa produkto, karne, isda, at iba pang paninda</li><li><i class="fas fa-check"></i> Sumusuporta sa patas na presyo at proteksyon ng consumer</li><li><i class="fas fa-check"></i> Koordinado sa Market Division</li></ul><p><strong style="color:var(--indigo-deep);">Tip:</strong> Hanapin ang Timbangan ng Bayan kapag gusto mong timbangin bago bumili o magbenta.</p>');
      touchHtml(document.getElementById('modal-src-assist'), lang,
        '<p>Tutulungan ka ng staff ng market office at sasagot sa tanong tungkol sa permit, official receipt, stall rules, at requirements.</p><ul><li><i class="fas fa-check"></i> Gabay sa promotional permit at advertising requirements</li><li><i class="fas fa-check"></i> Tulong sa treasury receipts at document checklist</li><li><i class="fas fa-check"></i> Lunes–Biyernes, 8:00 AM – 5:00 PM sa Daet Public Market</li></ul><p><a href="#faq" class="service-modal__cta">Basahin ang FAQs <i class="fas fa-arrow-right"></i></a> · <a href="https://facebook.com/daetpublicmarket" target="_blank" rel="noopener noreferrer" class="service-modal__cta">Mag-message sa Facebook <i class="fas fa-arrow-right"></i></a></p>');
    }

    global.PROMO_SERVICE_TITLES_PH = {
      'Promotional Permit': 'Promotional Permit',
      'Billboards & Streamers': 'Billboards at Streamers',
      'Stall Registration': 'Rehistrasyon ng Stall',
      'CCTV Monitoring': 'Pagsubaybay sa CCTV',
      'Timbangan ng Bayan': 'Timbangan ng Bayan',
      'Client Assistance Desk': 'Client Assistance Desk'
    };

    document.querySelectorAll('.site-footer .footer-col a').forEach(function (a, i) {
      var ph = [
        'Mga Serbisyo', 'Mga Permit at Bayad', 'Proseso', 'Charter',
        'Home ng palengke', 'Tungkol', 'Mga Anunsyo', 'Serbisyo', 'Contact', 'Vendor portal'
      ];
      if (ph[i]) touchText(a, lang, ph[i]);
    });
    document.querySelectorAll('.site-footer .footer-col:last-child li').forEach(function (li, i) {
      if (i === 0) touchText(li, lang, 'Daet, Camarines Norte');
      if (i === 2) touchText(li, lang, 'Lunes–Biyernes, 8:00 AM – 5:00 PM');
    });

    touchText(document.querySelector('.site-footer .footer-brand p'), lang, 'May tanong tungkol sa permit, stall, o serbisyo? I-message ang opisyal na Facebook page.');
    touchText(document.querySelector('.footer-col:nth-child(2) h4'), lang, 'Sa pahinang ito');
    touchText(document.querySelector('.footer-col:nth-child(3) h4'), lang, 'Shortcut links');
    touchText(document.querySelector('.footer-col:nth-child(4) h4'), lang, 'Pakikipag-ugnayan');
    document.querySelectorAll('.site-footer .footer-bar span').forEach(function (el, i) {
      var ph = [
        '© ' + new Date().getFullYear() + ' Daet Public Market. Lahat ng karapatan ay nakalaan.',
        'Local Government Unit ng Daet, Camarines Norte'
      ];
      touchText(el, lang, ph[i]);
    });

    if (typeof global.promoApplyHeroWordLang === 'function') global.promoApplyHeroWordLang(lang);
  }

  global.DPM_I18N_EXTRA_HANDLERS = global.DPM_I18N_EXTRA_HANDLERS || {};
  global.DPM_I18N_EXTRA_HANDLERS.registrationform = translateRegistration;
  global.DPM_I18N_EXTRA_HANDLERS['promotional-permit'] = translatePromotionalPermit;

  global.addEventListener('menu-lang-change', function (ev) {
    var lang = (ev.detail && ev.detail.lang) || 'en';
    var page = global.DPM_I18N_PAGE || '';
    if (page === 'registrationform' && typeof translateRegistration === 'function') {
      translateRegistration(lang);
    }
    if (page === 'promotional-permit' && typeof translatePromotionalPermit === 'function') {
      translatePromotionalPermit(lang);
    }
  });
})(window);
