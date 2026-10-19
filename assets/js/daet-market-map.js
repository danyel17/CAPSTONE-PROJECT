/**
 * Mapbox map below 3D building on menu.php.
 * Expects window.__MAPBOX_TOKEN__, mapbox-gl, #daetMarketMap (data-marker-en / data-marker-ph).
 */
(function () {
  'use strict';

  var DAET_CENTER = [122.954, 14.121];
  var DEFAULT_ZOOM = 13.5;
  var marketPopup = null;

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function currentLang() {
    try {
      return localStorage.getItem('preferredLanguage') || 'en';
    } catch (e) {
      return 'en';
    }
  }

  function markerLabel(lang) {
    var el = document.getElementById('daetMarketMap');
    if (!el) return 'Daet Public Market';
    var en = el.getAttribute('data-marker-en') || 'Daet Public Market';
    var ph = el.getAttribute('data-marker-ph') || en;
    return lang === 'ph' ? ph : en;
  }

  function setPopupHtml(lang) {
    if (!marketPopup) return;
    marketPopup.setHTML('<strong>' + escapeHtml(markerLabel(lang)) + '</strong>');
  }

  function init() {
    var token = window.__MAPBOX_TOKEN__;
    var el = document.getElementById('daetMarketMap');
    if (!el || !token || !window.mapboxgl) return;

    mapboxgl.accessToken = token;
    var map = new mapboxgl.Map({
      container: el,
      style: 'mapbox://styles/mapbox/streets-v12',
      center: DAET_CENTER,
      zoom: DEFAULT_ZOOM
    });
    map.addControl(new mapboxgl.NavigationControl(), 'top-right');
    map.addControl(new mapboxgl.FullscreenControl(), 'top-right');

    map.on('load', function () {
      var lang = currentLang();
      marketPopup = new mapboxgl.Popup({ offset: 25 }).setHTML(
        '<strong>' + escapeHtml(markerLabel(lang)) + '</strong>'
      );
      new mapboxgl.Marker({ color: '#667eea' })
        .setLngLat(DAET_CENTER)
        .setPopup(marketPopup)
        .addTo(map);
    });
  }

  window.addEventListener('menu-lang-change', function (ev) {
    var lang = (ev.detail && ev.detail.lang) || currentLang();
    setPopupHtml(lang);
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
