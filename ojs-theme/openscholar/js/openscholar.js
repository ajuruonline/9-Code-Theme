/*!
 * Open Scholar theme: menu accessibility.
 * The OJS default script opens and closes the menu; this keeps aria-expanded correct,
 * closes the menu with the Escape key, and closes it after a link is chosen on a phone.
 * Distributed under the GNU GPL v3.
 */
(function () {
  'use strict';
  var nav = document.querySelector('.os-nav');
  if (!nav) { return; }
  var toggle = nav.querySelector('.os-nav__toggle');
  var phone = window.matchMedia('(max-width: 782px)');

  function isOpen() { return nav.classList.contains('pkp_site_nav_menu--isOpen'); }

  function sync() {
    if (toggle) { toggle.setAttribute('aria-expanded', isOpen() ? 'true' : 'false'); }
  }

  function close(returnFocus) {
    nav.classList.remove('pkp_site_nav_menu--isOpen');
    if (toggle && toggle.classList.contains('pkp_site_nav_toggle--transform')) {
      toggle.classList.remove('pkp_site_nav_toggle--transform');
    }
    if (returnFocus && toggle) { toggle.focus(); }
    sync();
  }

  new MutationObserver(sync).observe(nav, { attributes: true, attributeFilter: ['class'] });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && isOpen()) { close(true); }
  });

  nav.addEventListener('click', function (event) {
    var link = event.target.closest && event.target.closest('.os-nav__links a');
    if (link && phone.matches) { close(false); }
  });

  sync();
}());
