/*!
 * Open Scholar theme: keeps the bottom navigation bar from covering the page.
 * Only the collapsed bar is measured, so opening the menu lays over the page
 * instead of pushing the content up. Distributed under the GNU GPL v3.
 */
(function () {
  'use strict';
  var bar = document.querySelector('.os-nav');
  if (!bar) { return; }
  var list = bar.querySelector('.os-nav__inner');

  function reserve() {
    // Height of the bar with the menu list removed (the list opens over the page).
    var open = list ? list.offsetHeight : 0;
    var height = Math.max(0, bar.getBoundingClientRect().height - open);
    document.body.style.paddingBottom = Math.ceil(height) + 'px';
  }

  if ('ResizeObserver' in window) {
    new ResizeObserver(reserve).observe(bar);
  }
  window.addEventListener('resize', reserve);
  window.addEventListener('load', reserve);
  reserve();
}());
