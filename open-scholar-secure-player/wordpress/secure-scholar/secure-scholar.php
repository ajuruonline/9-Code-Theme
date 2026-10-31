<?php
/**
 * Plugin Name: Open Scholar Secure Player
 * Description: Secure and open protected videos, PDFs, slides, documents, images and saved web lessons through Open Scholar.
 * Version: 1.1.0
 * Author: OpenScholar.ng
 * License: MIT
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) { exit; }

define('SSV1_VERSION', '1.0.0');

function ssv1_register_routes() {
    add_rewrite_rule('^secure-scholar/owner/?$', 'index.php?secure_scholar_route=owner', 'top');
    add_rewrite_rule('^secure-scholar/(index\.html|sw\.js|manifest\.webmanifest|icon\.svg|icon-512\.png|icon-192\.png|apple-touch-icon\.png|favicon\.ico)$', 'index.php?secure_scholar_route=reader&secure_scholar_asset=$matches[1]', 'top');
    add_rewrite_rule('^secure-scholar/?$', 'index.php?secure_scholar_route=reader', 'top');
}
add_action('init', 'ssv1_register_routes');
add_filter('query_vars', function ($vars) { $vars[] = 'secure_scholar_route'; $vars[] = 'secure_scholar_asset'; return $vars; });

function ssv1_url($owner = false) { return home_url('/secure-scholar/' . ($owner ? 'owner/' : '')); }

function ssv1_serve_app() {
    $route = get_query_var('secure_scholar_route');
    if (!$route) { return; }
    $owner = ($route === 'owner');
    if ($owner) {
        if (!is_user_logged_in()) { auth_redirect(); exit; }
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Your account cannot secure learning files.', 'secure-scholar'), '', array('response' => 403));
        }
    }
    $asset = get_query_var('secure_scholar_asset') ?: 'index.html';
    $types = array(
        'index.html' => 'text/html; charset=utf-8', 'sw.js' => 'text/javascript; charset=utf-8',
        'manifest.webmanifest' => 'application/manifest+json', 'icon.svg' => 'image/svg+xml', 'icon-512.png' => 'image/png',
        'icon-192.png' => 'image/png', 'apple-touch-icon.png' => 'image/png', 'favicon.ico' => 'image/x-icon'
    );
    if (!isset($types[$asset])) { status_header(404); exit; }
    $path = __DIR__ . '/app/' . $asset;
    if (!is_file($path)) { status_header(503); wp_die(esc_html__('Secure Scholar files are missing. Reinstall the complete plugin ZIP.', 'secure-scholar')); }
    status_header(200);
    nocache_headers();
    header('Content-Type: ' . $types[$asset]);
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    if ($asset === 'index.html') {
        $integration = array(
            'readerOnly' => !$owner,
            'ownerOnly' => false,
            'onlineLimit' => 104857600,
            'basePath' => ssv1_url(false),
            'edition' => 'wordpress'
        );
        $html = file_get_contents($path);
        $script = '<script id="ss-integration">window.SECURE_SCHOLAR_INTEGRATION=' . wp_json_encode($integration, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';
        $html = str_replace('<script id="ss-integration">window.SECURE_SCHOLAR_INTEGRATION=null;</script>', $script, $html);
        // The existing Open Scholar shortcut remains the only phone installation.
        $html = preg_replace('/<link rel="manifest"[^>]*>/', '', $html);
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static audited application plus JSON_HEX settings.
    } else {
        readfile($path);
    }
    exit;
}
add_action('template_redirect', 'ssv1_serve_app', 0);

function ssv1_activate() {
    ssv1_register_routes();
    flush_rewrite_rules();
    if (!get_option('secure_scholar_tutorial_post')) {
        $content = '<h2>Secure Scholar Plugin</h2><p>Secure Scholar protects learning files while keeping the process extremely simple.</p>';
        $content .= '<h3>To open a secure file</h3><ol><li>Open the existing Open Scholar shortcut on your phone.</li><li>Choose Secure Scholar.</li><li>Import the file ending in <strong>.scholar</strong>.</li><li>Import its password file ending in <strong>.scholarpass</strong>.</li><li>Press Open file and view your remaining credits.</li></ol>';
        $content .= '<h3>To secure a file</h3><ol><li>Sign in with an eligible Open Scholar account.</li><li>Choose your file.</li><li>Choose how long it should work, how many openings and viewing time.</li><li>Press Secure file.</li><li>Download or share the secure file and password file.</li></ol>';
        $content .= '<p>Advanced controls remain hidden under Advanced Settings. Secure view blocks ordinary copying, printing, downloading and sharing, and applies recipient watermarks and access rules. System-level capture protection depends on the device and cannot stop an external camera.</p>';
        $content .= '[secure_scholar size="big"]<p>Powered by OpenScholar.ng.</p>';
        $post = wp_insert_post(array(
            'post_title' => 'How to Use Secure Scholar', 'post_content' => $content,
            'post_type' => 'post', 'post_status' => 'publish', 'post_author' => get_current_user_id()
        ), true);
        if (!is_wp_error($post)) { update_option('secure_scholar_tutorial_post', (int) $post); }
    }
}
register_activation_hook(__FILE__, 'ssv1_activate');
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

function ssv1_shortcode($attributes) {
    $attributes = shortcode_atts(array('size' => 'medium'), $attributes, 'secure_scholar');
    $size = in_array($attributes['size'], array('small', 'medium', 'big'), true) ? $attributes['size'] : 'medium';
    $url = esc_url(ssv1_url(false));
    if ($size === 'big') {
        return '<div class="secure-scholar-placement"><iframe src="' . $url . '" title="Secure Scholar" style="width:100%;height:860px;border:0" loading="lazy" allow="fullscreen"></iframe><p><a href="' . $url . '">Open Secure Scholar full screen</a></p><small>Powered by OpenScholar.ng.</small></div>';
    }
    $description = $size === 'small' ? '' : '<p style="margin:6px 0 12px;color:#52675b">Import your secure file and password, then press Open.</p>';
    return '<div class="secure-scholar-placement" style="background:#f1f7f2;padding:16px;border:0;color:#123526"><strong>Secure Scholar</strong>' . $description . '<p style="margin:8px 0"><a href="' . $url . '" style="display:inline-block;background:#00532d;color:#fff;padding:10px 15px;text-decoration:none">Open Secure Scholar</a></p><small>Powered by OpenScholar.ng.</small></div>';
}
add_shortcode('secure_scholar', 'ssv1_shortcode');

function ssv1_owner_shortcode() {
    if (!is_user_logged_in() || !current_user_can('edit_posts')) { return '<p>Please sign in with an eligible author account to secure a file.</p>'; }
    return '<p><a href="' . esc_url(ssv1_url(true)) . '" style="display:inline-block;background:#00532d;color:#fff;padding:10px 15px;text-decoration:none">Secure a file</a></p><small>Powered by OpenScholar.ng.</small>';
}
add_shortcode('secure_scholar_owner', 'ssv1_owner_shortcode');

function ssv1_admin_menu() {
    $icon = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="white" d="M3 8l9-5 9 5-9 5-9-5zm4 4v5l5 3 5-3v-5l-5 3-5-3z"/></svg>');
    add_menu_page('Open Scholar Secure Player', 'Secure Scholar', 'edit_posts', 'secure-scholar', 'ssv1_admin_page', $icon, 58);
}
add_action('admin_menu', 'ssv1_admin_menu');

function ssv1_admin_page() {
    if (!current_user_can('edit_posts')) { return; }
    echo '<div class="wrap"><h1>Open Scholar Secure Player 1.1</h1><p><strong>Extreme simplicity in front. Advanced protection behind the interface.</strong></p>';
    echo '<p><a class="button button-primary" href="' . esc_url(ssv1_url(true)) . '">Secure a file</a> <a class="button" href="' . esc_url(ssv1_url(false)) . '">Open a secure file</a></p>';
    echo '<details><summary><strong>Placement shortcodes</strong></summary>';
    foreach (array('small', 'medium', 'big') as $size) {
        echo '<p><label>' . esc_html(ucfirst($size)) . ' <input readonly onclick="this.select()" style="width:420px;max-width:100%" value="' . esc_attr('[secure_scholar size="' . $size . '"]') . '"></label></p>';
    }
    echo '<p>Owner button: <input readonly onclick="this.select()" style="width:420px;max-width:100%" value="[secure_scholar_owner]"></p></details>';
    echo '<details><summary><strong>Tutorial</strong></summary><p><a href="' . esc_url(get_permalink((int) get_option('secure_scholar_tutorial_post'))) . '">Open the Secure Scholar tutorial post</a></p></details>';
    echo '<details><summary><strong>Open Scholar phone shortcut</strong></summary><p>Add a Dashboard or menu item named <strong>Secure Scholar</strong> pointing to <code>' . esc_html(ssv1_url(false)) . '</code>. Users continue using the existing Open Scholar shortcut; do not create another phone app.</p></details>';
    echo '<details><summary><strong>Security and cache settings</strong></summary><p>Exclude the signed-in owner route and WordPress administration from public page caches. The recipient route may be cached by the existing Open Scholar PWA. Test Android, iPhone, Edge and the active cache plugin before public release.</p><p>The online edition processes files locally in the browser and does not upload content or private keys to WordPress.</p></details>';
    echo '<p>Powered by OpenScholar.ng.</p></div>';
}
