/* Twisted Oak — small progressive enhancements. Everything works without this file. */
(function () {
  'use strict';

  /* ---------- Mobile drawer ---------- */
  var drawer = document.querySelector('[data-drawer]');
  var opener = document.querySelector('[data-drawer-open]');
  if (drawer && opener) {
    var panel = drawer.querySelector('[data-drawer-panel]');
    var closer = drawer.querySelector('[data-drawer-close]');
    var focusable = 'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])';

    var onKey = function (e) {
      if (e.key === 'Escape') { close(); return; }
      if (e.key !== 'Tab') return;
      var items = panel.querySelectorAll(focusable);
      if (!items.length) return;
      var first = items[0], last = items[items.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    };
    var open = function () {
      drawer.hidden = false;
      opener.setAttribute('aria-expanded', 'true');
      document.body.classList.add('drawer-open');
      document.addEventListener('keydown', onKey);
      panel.focus();
    };
    var close = function () {
      if (drawer.hidden) return;
      drawer.hidden = true;
      opener.setAttribute('aria-expanded', 'false');
      document.body.classList.remove('drawer-open');
      document.removeEventListener('keydown', onKey);
      opener.focus();
    };

    opener.addEventListener('click', open);
    if (closer) closer.addEventListener('click', close);
    drawer.addEventListener('click', function (e) {
      if (e.target === drawer) close();
      // Same-page anchors (e.g. "#catering-form") should close the drawer.
      var link = e.target.closest && e.target.closest('a[href*="#"]');
      if (link && link.pathname === location.pathname) close();
    });
    // The drawer is only reachable below the desktop breakpoint; close it if the window grows.
    var wide = window.matchMedia('(min-width: 960px)');
    var onWide = function () { if (wide.matches) close(); };
    if (wide.addEventListener) wide.addEventListener('change', onWide);
  }

  /* ---------- Header shadow once the page scrolls ---------- */
  var header = document.querySelector('[data-site-header]');
  if (header && 'IntersectionObserver' in window) {
    var sentinel = document.createElement('div');
    sentinel.setAttribute('aria-hidden', 'true');
    sentinel.style.cssText = 'position:absolute;top:0;left:0;width:1px;height:8px;pointer-events:none';
    document.body.prepend(sentinel);
    new IntersectionObserver(function (entries) {
      header.classList.toggle('is-scrolled', !entries[0].isIntersecting);
    }).observe(sentinel);
  }

  /* ---------- Quote form ---------- */
  var form = document.querySelector('[data-quote-form]');
  if (form) {
    // After a failed submit the server shows an error alert: move focus to it so it's announced.
    var alert = form.querySelector('.to-alert--error');
    if (alert && location.hash === '#catering-form-mount') {
      alert.focus({ preventScroll: true });
    }

    // Let the browser check required fields and formats, but report them with our own inline style.
    form.addEventListener('submit', function (e) {
      var invalid = Array.prototype.filter.call(form.elements, function (el) {
        return el.willValidate && !el.checkValidity();
      });
      form.querySelectorAll('[data-client-error]').forEach(function (n) { n.remove(); });
      Array.prototype.forEach.call(form.elements, function (el) {
        if (el.dataset && el.dataset.clientInvalid) {
          el.removeAttribute('aria-invalid');
          delete el.dataset.clientInvalid;
          var rest = (el.getAttribute('aria-describedby') || '').split(' ').filter(function (id) { return id && id !== el.id + '-cerr'; }).join(' ');
          if (rest) el.setAttribute('aria-describedby', rest); else el.removeAttribute('aria-describedby');
        }
      });
      if (!invalid.length) {
        var btn = form.querySelector('[type="submit"]');
        if (btn) { btn.setAttribute('aria-busy', 'true'); btn.disabled = true; }
        return;
      }
      e.preventDefault();
      invalid.forEach(function (el) {
        el.setAttribute('aria-invalid', 'true');
        el.dataset.clientInvalid = '1';
        var msg = document.createElement('p');
        msg.className = 'to-error-msg';
        msg.id = el.id + '-cerr';
        msg.setAttribute('data-client-error', '');
        msg.textContent = el.validationMessage;
        var field = el.closest('.to-field');
        if (field) field.appendChild(msg);
        el.setAttribute('aria-describedby', ((el.getAttribute('aria-describedby') || '') + ' ' + msg.id).trim());
      });
      invalid[0].focus();
    });

    // Restore the button if the page is shown again from the back/forward cache.
    window.addEventListener('pageshow', function () {
      var btn = form.querySelector('[type="submit"]');
      if (btn) { btn.disabled = false; btn.removeAttribute('aria-busy'); }
    });
  }

  /* ---------- Thank-you state: bring it into view ---------- */
  var sent = document.querySelector('.quote-form--sent');
  if (sent && location.hash === '#catering-form-mount') sent.focus({ preventScroll: true });
})();
