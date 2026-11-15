/*
 * Mikhmon shell: mobile drawer, sidebar groups and the theme / accent switcher.
 * Plain JavaScript, no dependencies. Replaces js/mikhmon-ui.<theme>.min.js.
 *
 * Theme preference is stored per browser (localStorage):
 *   mikhmon.mode    "auto" | "light" | "dark"
 *   mikhmon.accent  "blue" | "green" | "pink" | "slate"
 * The inline script in include/headhtml.php applies it before first paint;
 * this file wires up the controls and keeps "auto" in sync with the OS.
 */
(function () {
  'use strict';
  var root = document.documentElement;
  var body = document.body;
  var mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  var MODES = ['auto', 'light', 'dark'];
  var ACCENTS = ['blue', 'green', 'pink', 'slate'];

  function store(key, value) {
    try { if (value === undefined) { return localStorage.getItem(key); } localStorage.setItem(key, value); } catch (e) { return null; }
  }

  /* ------------------------------------------------------------ theme */
  function currentMode() { return root.getAttribute('data-mode') || 'auto'; }
  function resolve(mode) { return mode === 'auto' ? (mq && mq.matches ? 'dark' : 'light') : mode; }

  function apply(mode, accent, persist) {
    if (MODES.indexOf(mode) === -1) { mode = 'auto'; }
    if (ACCENTS.indexOf(accent) === -1) { accent = 'blue'; }
    root.setAttribute('data-mode', mode);
    root.setAttribute('data-theme', resolve(mode));
    root.setAttribute('data-accent', accent);
    if (persist) { store('mikhmon.mode', mode); store('mikhmon.accent', accent); }
    syncControls();
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
      var bg = getComputedStyle(root).getPropertyValue('--surface').trim();
      if (bg) { meta.setAttribute('content', bg); }
    }
    try { document.dispatchEvent(new CustomEvent('mikhmon:theme', { detail: { theme: root.getAttribute('data-theme'), accent: accent } })); } catch (e) { /* old browser */ }
  }

  function syncControls() {
    var mode = currentMode(), accent = root.getAttribute('data-accent');
    [].forEach.call(document.querySelectorAll('[data-set-mode]'), function (b) {
      b.setAttribute('aria-pressed', b.getAttribute('data-set-mode') === mode ? 'true' : 'false');
    });
    [].forEach.call(document.querySelectorAll('[data-set-accent]'), function (b) {
      b.setAttribute('aria-pressed', b.getAttribute('data-set-accent') === accent ? 'true' : 'false');
    });
  }

  document.addEventListener('click', function (e) {
    var m = e.target.closest ? e.target.closest('[data-set-mode]') : null;
    if (m) { apply(m.getAttribute('data-set-mode'), root.getAttribute('data-accent'), true); return; }
    var a = e.target.closest ? e.target.closest('[data-set-accent]') : null;
    if (a) { apply(currentMode(), a.getAttribute('data-set-accent'), true); return; }
    // close the theme popover when clicking elsewhere
    var pops = document.querySelectorAll('details.popover[open]');
    [].forEach.call(pops, function (p) { if (!p.contains(e.target)) { p.removeAttribute('open'); } });
  });

  if (mq) {
    var onChange = function () { if (currentMode() === 'auto') { apply('auto', root.getAttribute('data-accent'), false); } };
    if (mq.addEventListener) { mq.addEventListener('change', onChange); } else if (mq.addListener) { mq.addListener(onChange); }
  }
  syncControls();

  /* ----------------------------------------------------------- drawer */
  var openBtn = document.getElementById('openNav');
  var scrim = document.getElementById('scrim');
  function setDrawer(open) {
    body.classList.toggle('nav-open', open);
    if (openBtn) { openBtn.setAttribute('aria-expanded', open ? 'true' : 'false'); }
  }
  if (openBtn) { openBtn.addEventListener('click', function () { setDrawer(!body.classList.contains('nav-open')); }); }
  if (scrim) { scrim.addEventListener('click', function () { setDrawer(false); }); }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      setDrawer(false);
      [].forEach.call(document.querySelectorAll('details.popover[open]'), function (p) { p.removeAttribute('open'); });
    }
  });
  window.addEventListener('resize', function () { if (window.innerWidth >= 1024) { setDrawer(false); } });
  var side = document.getElementById('sidenav');
  if (side) {
    side.addEventListener('click', function (e) {
      var link = e.target.closest ? e.target.closest('a[href]') : null;
      if (link && !link.classList.contains('connect')) { setDrawer(false); }
    });
  }

  /* ----------------------------------------------- collapsible groups */
  var buttons = document.querySelectorAll('.dropdown-btn');
  [].forEach.call(buttons, function (btn) {
    var box = btn.nextElementSibling;
    if (!box || !box.classList.contains('dropdown-container')) { return; }
    var open = box.classList.contains('menu-open');
    btn.setAttribute('role', 'button');
    btn.setAttribute('tabindex', '0');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) { btn.classList.add('is-open'); }
    function toggle() {
      // "menu-open" is set by PHP for the section of the current page, "is-open" by this script
      var visible = box.classList.contains('is-open') || box.classList.contains('menu-open');
      box.classList.remove('menu-open', 'is-open');
      if (!visible) { box.classList.add('is-open'); }
      btn.classList.toggle('is-open', !visible);
      btn.setAttribute('aria-expanded', visible ? 'false' : 'true');
    }
    btn.addEventListener('click', toggle);
    btn.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
    });
  });
})();
