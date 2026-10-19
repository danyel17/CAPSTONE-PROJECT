/**
 * Site-wide language sync when PH/EN is chosen in the customer support widget.
 * Vendor portal pages with their own changeLanguage/translatePage use those hooks.
 * Other widget pages use per-page handlers keyed by window.DPM_I18N_PAGE.
 */
(function (global) {
  'use strict';

  function inPreserveZone(el) {
    return !!(el && el.closest && el.closest('[data-lang-preserve="en"]'));
  }

  function touchText(el, lang, phText) {
    if (!el || (lang === 'ph' && inPreserveZone(el))) return;
    if (!el.hasAttribute('data-dpm-en')) {
      el.setAttribute('data-dpm-en', el.textContent);
    }
    el.textContent = lang === 'ph' ? phText : el.getAttribute('data-dpm-en');
  }

  function touchHtml(el, lang, phHtml) {
    if (!el || (lang === 'ph' && inPreserveZone(el))) return;
    if (!el.hasAttribute('data-dpm-en-html')) {
      el.setAttribute('data-dpm-en-html', el.innerHTML);
    }
    el.innerHTML = lang === 'ph' ? phHtml : el.getAttribute('data-dpm-en-html');
  }

  function touchPlaceholder(el, lang, ph) {
    if (!el || (lang === 'ph' && inPreserveZone(el))) return;
    if (!el.hasAttribute('data-dpm-en-ph')) {
      el.setAttribute('data-dpm-en-ph', el.getAttribute('placeholder') || '');
    }
    el.setAttribute('placeholder', lang === 'ph' ? ph : el.getAttribute('data-dpm-en-ph'));
  }

  function applyDataTranslate(lang) {
    var pack = global.DPM_SITE_I18N || null;
    document.querySelectorAll('[data-translate]').forEach(function (el) {
      if (lang === 'ph' && inPreserveZone(el)) return;
      var key = el.getAttribute('data-translate');
      if (!key || key.indexOf('nav.') === 0) return;
      if (!el.hasAttribute('data-dpm-en')) {
        el.setAttribute('data-dpm-en', el.textContent);
      }
      if (lang === 'ph' && pack && pack.ph && pack.ph[key]) {
        el.textContent = pack.ph[key];
      } else if (lang === 'en') {
        el.textContent = el.getAttribute('data-dpm-en');
      }
    });
    document.querySelectorAll('[data-translate-placeholder]').forEach(function (el) {
      if (lang === 'ph' && inPreserveZone(el)) return;
      var key = el.getAttribute('data-translate-placeholder');
      if (!key) return;
      if (!el.hasAttribute('data-dpm-en-ph')) {
        el.setAttribute('data-dpm-en-ph', el.getAttribute('placeholder') || '');
      }
      if (lang === 'ph' && pack && pack.ph && pack.ph[key]) {
        el.setAttribute('placeholder', pack.ph[key]);
      } else if (lang === 'en') {
        el.setAttribute('placeholder', el.getAttribute('data-dpm-en-ph'));
      }
    });
  }

  function translateIndex(lang) {
    touchText(document.querySelector('.banner-scroll-hint'), lang, 'Mag-scroll');
    document.querySelectorAll('.banner-slide .btn-pill-light').forEach(function (el, i) {
      var ph = ['Tuklasin ang Palengke', 'Mga Serbisyo Namin', 'Portal ng User'];
      touchText(el, lang, ph[i] || el.getAttribute('data-dpm-en'));
    });

    touchText(document.querySelector('#about .about-notice-card__hint'), lang, 'anunsyo, presyo, kaarawan, atbp.');
    touchText(document.querySelector('#about .about-copy > .tag'), lang, 'Tungkol sa amin');
    document.querySelectorAll('#about .about-copy > p').forEach(function (el, i) {
      var ph = [
        'Nagsimula ang operasyon ng palengke noong 1980s at matapos ang sunog, muling itinayo at pinasinayaan noong 1997.',
        'Noong 2002, ang Daet Public Market ay pinabuti sa pamamagitan ng pautang sa Landbank of the Philippines na nagkakahalaga ng 51 milyon. May (2) antas na istruktura na may 566 stall at muling itinayo matapos ang sunog noong 2004.',
        'Sa ngayon, ang Daet Public Market ay sumasailalim sa mga pag-unlad at inobasyon na angkop sa kasalukuyang pangangailangan ng mga stakeholder.'
      ];
      touchText(el, lang, ph[i] || el.getAttribute('data-dpm-en'));
    });
    touchText(document.querySelector('#about .about-vm--vision .about-vm__label'), lang, 'Bisyon');
    touchText(document.querySelector('#about .about-vm--vision .about-vm__text'), lang, 'Matatag at Matibay na Daet Public Market, Self-sufficient at Epektibong Local Economic Enterprise ng LGU ng Daet.');
    touchText(document.querySelector('#about .about-vm--mission .about-vm__label'), lang, 'Misyon');
    touchText(document.querySelector('#about .about-vm--mission .about-vm__text'), lang, 'Inklusibo at Progresibong Daet Public Market na may Napapanatiling Pag-unlad at Disiplinadong Negosyo at Makabagong Serbisyo para sa kapakanan ng lahat ng Daetño.');
    touchText(document.querySelector('#about .stat-card:nth-child(1) .stat-card__label'), lang, 'Lokasyon');
    touchText(document.querySelector('#about .stat-card:nth-child(2) .stat-card__label'), lang, 'Oras ng bukas');
    touchText(document.querySelector('#about .btn-services'), lang, 'Mga Serbisyo Namin →');

    document.querySelectorAll('.features-section-white .feature-card').forEach(function (card, i) {
      var titles = ['Madaling Aplikasyon', 'Mabilis na Pag-renew', 'Subaybayan ang Progreso'];
      var desc = [
        'Isumite ang iyong market clearance nang mabilis sa aming pinasimpleng proseso.',
        'I-renew ang clearance sa ilang click at dokumento lamang.',
        'Subaybayan ang status ng aplikasyon nang real-time mula sa profile.'
      ];
      touchText(card.querySelector('h3'), lang, titles[i]);
      touchText(card.querySelector('p'), lang, desc[i]);
    });

    touchText(document.querySelector('#announcement .tag'), lang, 'Pinakabagong update');
    touchHtml(document.querySelector('#announcement .announce-head h2'), lang, 'Mga Anunsyo at <em>Balita</em>');
    touchText(document.querySelector('#announcement .announce-head > p'), lang, 'Manatiling updated sa balita, kaganapan, at mahahalagang paunawa mula sa Daet Public Market.');
    document.querySelectorAll('#announcement .read-more').forEach(function (el) {
      touchHtml(el, lang, 'Basahin Pa <i class="fas fa-arrow-right"></i>');
    });

    touchText(document.querySelector('#services .tag'), lang, 'Ano ang inaalok namin');
    touchText(document.querySelector('#services .services-head h2'), lang, 'Mga Serbisyo Namin');
    touchText(document.querySelector('#services .services-head > p'), lang, 'Lahat ng kailangan mo para magsimula at lumago sa Daet Public Market.');
    document.querySelectorAll('#services .service-card').forEach(function (card, i) {
      var t = ['Pag-upa ng Stall', 'Rehistrasyon ng Vendor', 'Pag-renew ng Permit', 'Serbisyo ng Inspeksyon', 'Suporta sa Vendor'];
      var d = [
        'Umupa ng stall sa website — mabilis at walang hassle.',
        'Magrehistro bilang opisyal na vendor. Kunin ang iyong mga dokumento ngayon.',
        'I-renew ang permit sa ilang click.',
        'Maaaring sumailalim sa inspeksyon pagkatapos magpadala ng resibo.',
        'Widget na tutulong at gagabay sa iyong mga tanong.'
      ];
      var p = ['Upa', 'Magrehistro na!', 'I-renew', 'Inspeksyon', 'Widget'];
      touchText(card.querySelector('h3'), lang, t[i]);
      touchText(card.querySelector('p'), lang, d[i]);
      touchText(card.querySelector('.service-card__price'), lang, p[i]);
    });
    touchHtml(document.querySelector('#services .btn-more-services'), lang, 'Higit pang Serbisyo <i class="fas fa-arrow-right" aria-hidden="true"></i>');

    touchText(document.querySelector('#contact .tag'), lang, 'Makipag-ugnayan');
    touchHtml(document.querySelector('#contact .contact-info h2'), lang, 'Makipag-ugnayan <em>sa Amin</em>');
    touchText(document.querySelector('#contact .contact-info > p'), lang, 'May tanong tungkol sa rehistrasyon, stall, o serbisyo? Mag-message sa FB page o bumisita sa opisina.');
    touchText(document.querySelector('#contact .contact-item:nth-child(1) small'), lang, 'Address');
    touchText(document.querySelector('#contact .contact-item:nth-child(2) small'), lang, 'Facebook Page');
    touchText(document.querySelector('#contact .contact-item:nth-child(3) small'), lang, 'Oras ng opisina');
    touchText(document.querySelector('#contact .contact-form-wrap h3'), lang, 'Magpadala ng Mensahe');
    touchText(document.querySelector('label[for="pf_name"]'), lang, 'Buong Pangalan');
    touchText(document.querySelector('label[for="pf_email"]'), lang, 'Email');
    touchText(document.querySelector('label[for="pf_message"]'), lang, 'Mensahe');
    touchPlaceholder(document.getElementById('pf_name'), lang, 'hal. Daniel Prado');
    touchPlaceholder(document.getElementById('pf_email'), lang, 'example@gmail.com');
    touchPlaceholder(document.getElementById('pf_message'), lang, 'Isulat ang mensahe mo dito...');
    touchText(document.querySelector('#promoContactForm .btn-submit'), lang, 'Ipadala ang Mensahe');

    touchHtml(document.querySelector('#user-portal .vendor-copy h2'), lang, 'Portal ng <span class="portal-line">User</span>');
    touchText(document.querySelector('#user-portal .vendor-copy > p'), lang, 'I-access ang account para pamahalaan ang stall, tingnan ang anunsyo, mag-renew ng permit, at subaybayan ang aktibidad — sa isang lugar.');
    document.querySelectorAll('#user-portal .feature-list li span').forEach(function (el, i) {
      var ph = ['Secure na pamamahala ng account', 'Online na pag-renew at aplikasyon', 'Real-time na anunsyo at update'];
      touchText(el, lang, ph[i]);
    });
    touchText(document.querySelector('#user-portal .login-card h3'), lang, 'Maligayang Pagbabalik');
    touchText(document.querySelector('#user-portal .login-card .sub'), lang, 'Mag-sign in sa iyong account');
    touchText(document.querySelector('label[for="promo_email"]'), lang, 'Email');
    touchText(document.querySelector('label[for="promo_password"]'), lang, 'Password');
    touchPlaceholder(document.getElementById('promo_email'), lang, 'Ilagay ang iyong email');
    touchPlaceholder(document.getElementById('promo_password'), lang, 'Ilagay ang iyong password');
    touchText(document.querySelector('#user-portal .login-options label'), lang, 'Tandaan ako');
    touchText(document.querySelector('#user-portal .login-options a'), lang, 'Nakalimutan ang password?');
    touchText(document.querySelector('#user-portal .login-card .btn-submit'), lang, 'Mag-sign In');
    touchHtml(document.querySelector('#user-portal .login-foot'), lang, 'Walang account? <a href="registrationform.php">Mag-sign up</a>');

    touchText(document.querySelector('.site-footer .footer-brand > p'), lang, 'Kung may tanong o alalahanin, mag-message sa aming Facebook page.');
    touchText(document.querySelector('.footer-col:nth-child(2) h4'), lang, 'Mabilis na Link');
    touchText(document.querySelector('.footer-col:nth-child(3) h4'), lang, 'Mga Serbisyo');
    touchText(document.querySelector('.footer-contact h4'), lang, 'Impormasyon sa Pakikipag-ugnayan');
    document.querySelectorAll('.footer-col:nth-child(2) a').forEach(function (el, i) {
      var ph = ['Tungkol', 'Mga Anunsyo', 'Serbisyo', 'Contact'];
      touchText(el, lang, ph[i]);
    });
    document.querySelectorAll('.footer-col:nth-child(3) li').forEach(function (el, i) {
      var ph = ['Pag-upa ng Stall', 'Rehistrasyon ng Vendor', 'Pag-renew ng Permit', 'Serbisyo ng Inspeksyon', 'Suporta sa Vendor'];
      touchText(el, lang, ph[i]);
    });
    touchText(document.querySelector('.footer-contact li:last-child'), lang, 'Bukas Lunes-Biyernes: 8AM – 5PM');
    document.querySelectorAll('.footer-bar span').forEach(function (el, i) {
      var ph = [
        '© ' + new Date().getFullYear() + ' Daet Public Market. Lahat ng karapatan ay nakalaan.',
        'Local Government Unit ng Daet, Camarines Norte'
      ];
      touchText(el, lang, ph[i]);
    });
  }

  function translateAuthForgot(lang) {
    touchHtml(document.querySelector('.auth-left .al-main h1'), lang, 'Seguridad ng<br><span>Account</span>');
    touchText(document.querySelector('.auth-left .al-main > p'), lang, 'I-reset ang password nang secure sa aming verified na multi-step process. Prayoridad namin ang seguridad ng account mo.');
    document.querySelectorAll('.al-feature').forEach(function (row, i) {
      var t = ['Email Verification', 'Time-Limited Codes', 'Strong Password Policy'];
      var s = [
        'Code na ipinapadala sa rehistradong email lamang',
        '10 minutong expiry para mas secure',
        'para masiguro ang account mo'
      ];
      var tp = ['Beripikasyon sa Email', 'May Limit na Oras ang Code', 'Matibay na Password'];
      var sp = [
        'Code na ipinapadala sa rehistradong email lamang',
        '10 minutong expiry para mas secure',
        'para masiguro ang account mo'
      ];
      touchText(row.querySelector('strong'), lang, lang === 'ph' ? tp[i] : t[i]);
      touchText(row.querySelector('span'), lang, sp[i]);
    });
    touchText(document.querySelector('.auth-right h1'), lang, 'I-reset ang Password');
    touchText(document.querySelector('.auth-right .card-sub'), lang, 'Walang problema! Ilagay ang rehistradong email at padadalhan ka namin ng secure na verification code.');
    touchText(document.querySelector('label[for="email"]'), lang, 'Email Address');
    touchPlaceholder(document.getElementById('email'), lang, 'ikaw@gmail.com');
    touchText(document.querySelector('.notice span'), lang, 'Para sa seguridad, mag-e-expire ang code sa 10 minuto. Tingnan ang spam folder kung wala sa inbox.');
    touchHtml(document.querySelector('.form-btn--submit'), lang, '<i class="fas fa-paper-plane"></i> Ipadala ang Verification Code');
    touchHtml(document.querySelector('.back-link'), lang, '<i class="fas fa-arrow-left"></i> Naalala mo ang password? <span class="accent">Mag-sign in</span>');
  }

  function translateMyApplications(lang) {
    touchText(document.querySelector('.header h1'), lang, 'Aking mga Aplikasyon');
    touchText(document.querySelector('.header p'), lang, 'Subaybayan ang iyong vendor application at renewal.');
    document.querySelectorAll('.status-card p').forEach(function (el, i) {
      var ph = ['Nakabinbing Aplikasyon', 'Naaprubahang Aplikasyon', 'Tinanggihang Aplikasyon'];
      touchText(el, lang, ph[i]);
    });
    touchText(document.querySelector('.table-header h2'), lang, 'Kasaysayan ng Aplikasyon');
    document.querySelectorAll('.applications-table thead th').forEach(function (th, i) {
      var ph = ['ID', 'Pangalan ng Vendor', 'Seksyon', 'Uri', 'Petsa ng Pagsumite', 'Status', 'Aksyon'];
      touchText(th, lang, ph[i] || th.getAttribute('data-dpm-en'));
    });
    touchText(document.querySelector('.no-applications h3'), lang, 'Walang Nahanap na Aplikasyon');
    touchText(document.querySelector('.no-applications p'), lang, 'Wala ka pang naisumiteng aplikasyon.');
    touchHtml(document.querySelector('.no-applications .btn-primary'), lang, '<i class="fas fa-plus"></i> Mag-sumite ng Bagong Aplikasyon');
    document.querySelectorAll('.applications-table tbody .btn-primary').forEach(function (btn) {
      touchHtml(btn, lang, '<i class="fas fa-eye"></i> Tingnan');
    });
  }

  function translateAboutUs(lang) {
    touchText(document.querySelector('.page-header h1'), lang, 'Tungkol sa Amin');
    touchText(document.querySelector('.page-header p'), lang, 'Alamin ang aming misyon na gawing simple at mabilis ang market clearance para sa mga vendor at negosyo.');
    document.querySelectorAll('.content-section > p').forEach(function (el, i) {
      var ph = [
        'Ang Market Clearance System ay digital platform na idinisenyo upang modernisahin ang proseso ng market clearance. Pinaglilingkuran namin ang mga vendor at operator sa pamamagitan ng mabilis, transparent, at madaling gamitin na sistema.',
        'Inaalis ng platform ang abala ng papel, binabawasan ang oras ng proseso, at nagbibigay ng real-time na pagsubaybay. Ginagawa naming accessible ang clearance mula sa maliliit na vendor hanggang malalaking negosyo.'
      ];
      if (ph[i]) touchText(el, lang, ph[i]);
    });
    document.querySelectorAll('.mv-card h3').forEach(function (el, i) {
      touchText(el, lang, i === 0 ? 'Aming Misyon' : 'Aming Bisyon');
    });
    document.querySelectorAll('.value-card h4').forEach(function (el, i) {
      var ph = ['Integridad', 'Inobasyon', 'Pokus sa Customer', 'Maasahan'];
      touchText(el, lang, ph[i]);
    });
  }

  global.dpmI18nTouch = {
    touchText: touchText,
    touchHtml: touchHtml,
    touchPlaceholder: touchPlaceholder,
    inPreserveZone: inPreserveZone
  };

  var handlers = {
    index: translateIndex,
    forgot_password: translateAuthForgot,
    reset_password: translateAuthForgot,
    verify_code: translateAuthForgot,
    my_applications: translateMyApplications,
    about_us: translateAboutUs
  };
  if (global.DPM_I18N_EXTRA_HANDLERS) {
    Object.assign(handlers, global.DPM_I18N_EXTRA_HANDLERS);
  }

  function dpmSiteTranslate(lang) {
    lang = lang === 'ph' ? 'ph' : 'en';
    applyDataTranslate(lang);
    var page = global.DPM_I18N_PAGE || '';
    var map = handlers;
    if (global.DPM_I18N_EXTRA_HANDLERS) {
      map = Object.assign({}, handlers, global.DPM_I18N_EXTRA_HANDLERS);
    }
    if (map[page]) {
      map[page](lang);
    }
    if (global.document && global.document.documentElement) {
      global.document.documentElement.lang = lang === 'ph' ? 'tl' : 'en';
    }
  }

  global.dpmSiteTranslate = dpmSiteTranslate;

  global.dpmSyncSiteLanguage = function (lang) {
    lang = lang === 'ph' ? 'ph' : 'en';
    if (typeof global.applyCsLanguage === 'function') {
      global.applyCsLanguage(lang);
    } else {
      try {
        global.localStorage.setItem('preferredLanguage', lang);
      } catch (e) { /* ignore */ }
    }
    if (typeof global.changeLanguage === 'function') {
      global.changeLanguage(lang);
      return;
    }
    if (typeof global.translatePage === 'function') {
      global.translatePage(lang);
      if (global.document && global.document.documentElement) {
        global.document.documentElement.lang = lang === 'ph' ? 'tl' : 'en';
      }
      global.dispatchEvent(new CustomEvent('menu-lang-change', { detail: { lang: lang } }));
      return;
    }
    dpmSiteTranslate(lang);
    global.dispatchEvent(new CustomEvent('menu-lang-change', { detail: { lang: lang } }));
  };
})(window);
