<?php
/**
 * Open Theme 6 — Open Navigation is the sole site chrome owner.
 * No theme header/footer is rendered; third-party site chrome is suppressed.
 * Keep wp_head(), wp_body_open(), wp_footer() for plugin compatibility.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Elementor Pro Theme Builder: tell its location engine not to render chrome.
add_filter( 'elementor/theme/need_override_location', function ( $override, $location = '' ) {
    return in_array( $location, array( 'header', 'footer' ), true ) ? true : $override;
}, PHP_INT_MAX, 2 );

// Elementor Header & Footer Builder: suppress selected templates without deleting them.
add_filter( 'hfe_header_enabled', '__return_false', PHP_INT_MAX );
add_filter( 'hfe_footer_enabled', '__return_false', PHP_INT_MAX );

function ncu_open_navigation_exclusive_css() {
    if ( is_admin() || is_feed() || is_embed() ) { return; }
    ?>
    <style id="open-theme-6-exclusive-navigation">
    body > header:not(.ninestagram-scholar-head),
    body > footer:not(.ninestagram-site-footer),
    body > #page > header, body > #page > footer,
    body > .site > header, body > .site > footer,
    body > .site-header, body > .site-footer,
    #masthead, #colophon, #hfe-header, #hfe-footer,
    .elementor-location-header, .elementor-location-footer,
    .hfe-site-header, .hfe-site-footer,
    [data-elementor-type="header"], [data-elementor-type="footer"],
    .wp-block-template-part[data-area="header"],
    .wp-block-template-part[data-area="footer"] {
        display: none !important; visibility: hidden !important;
        height: 0 !important; min-height: 0 !important;
        padding: 0 !important; margin: 0 !important;
    }
    </style>
    <?php
}
add_action( 'wp_head', 'ncu_open_navigation_exclusive_css', PHP_INT_MAX );

// Dynamic layout injection fallback; leave Open Navigation's own elements intact.
function ncu_open_navigation_exclusive_fallback() {
    if ( is_admin() || is_feed() || is_embed() ) { return; }
    ?>
    <script id="open-theme-6-exclusive-navigation-js">
    (function () {
      'use strict';
      var selectors = 'body > header:not(.ninestagram-scholar-head),body > footer:not(.ninestagram-site-footer),body > #page > header,body > #page > footer,body > .site > header,body > .site > footer,body > .site-header,body > .site-footer,#masthead,#colophon,#hfe-header,#hfe-footer,.elementor-location-header,.elementor-location-footer,.hfe-site-header,.hfe-site-footer,[data-elementor-type="header"],[data-elementor-type="footer"],.wp-block-template-part[data-area="header"],.wp-block-template-part[data-area="footer"]';
      var keep = '.ninestagram-root,.ninestagram-scholar-head,.ninestagram-site-footer';
      function remove(node) {
        if (node.closest && node.closest(keep)) return;
        node.remove();
      }
      /* Full pass once at start; afterwards only the elements that were added are checked. */
      function clean(root) {
        if (!root.querySelectorAll) return;
        root.querySelectorAll(selectors).forEach(remove);
      }
      function start() {
        clean(document);
        var added = [], scheduled = false;
        new MutationObserver(function (records) {
          records.forEach(function (record) {
            record.addedNodes.forEach(function (node) { if (node.nodeType === 1) added.push(node); });
          });
          if (scheduled || !added.length) return;
          scheduled = true;
          requestAnimationFrame(function () {
            var nodes = added; added = []; scheduled = false;
            nodes.forEach(function (node) {
              if (!node.isConnected) return;
              if (node.matches && node.matches(selectors)) remove(node);
              else clean(node);
            });
          });
        }).observe(document.body, { childList: true, subtree: true });
      }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
      else start();
    }());
    </script>
    <?php
}
add_action( 'wp_footer', 'ncu_open_navigation_exclusive_fallback', PHP_INT_MAX );
