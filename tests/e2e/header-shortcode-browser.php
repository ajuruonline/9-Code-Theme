<?php
/**
 * Disposable-only browser fixture: render a deliberately hostile shortcode
 * through the real Alpha popup boundary. Never register it on production.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')) {
    fwrite(STDERR, "ERROR: disposable WordPress WP-CLI required.\n");
    exit(2);
}
if (!class_exists('ELHF_H_Renderer', false)) {
    require_once get_template_directory() . '/inc/header-footer/bootstrap.php';
    NCU_Theme_Header_Footer::instance()->boot();
}
if (!class_exists('ELHF_H_Renderer', false)) {
    fwrite(STDERR, "ERROR: header renderer unavailable.\n");
    exit(2);
}
add_shortcode('n9eh_browser_hostile', static function () {
    return '<script>window.__n9eh_script_probe=1</script><img src="/n9eh-absent-image.png" onerror="window.__n9eh_event_probe=1">';
});
$settings = ELHF_H_Settings::defaults();
$settings['show_video_popup'] = 'yes';
$settings['video_source'] = 'shortcode';
$settings['video_shortcode'] = '[n9eh_browser_hostile]';
$method = new ReflectionMethod('ELHF_H_Renderer', 'render_popup');
$method->invoke(ELHF_H_Renderer::instance(), 'video', $settings, [], 1);

/*
 * Representative external-JavaScript form contract: a form shortcode supplies
 * safe HTML/data hooks, while its script is registered separately by a plugin.
 * This is a synthetic compatibility fixture, NOT a test of a named plugin.
 */
add_shortcode('n9eh_browser_form', static function () {
    return '<form class="n9eh-js-form" data-n9eh-form="integration" method="post" action="/wp-admin/admin-post.php">'
        . '<input type="hidden" name="nonce" value="fixture-nonce">'
        . '<label for="n9eh-email">Email</label>'
        . '<input id="n9eh-email" type="email" name="email" required aria-label="Email" data-n9eh-field="email" oninput="window.__n9eh_form_event_probe=1">'
        . '<label for="n9eh-topic">Topic</label>'
        . '<select id="n9eh-topic" name="topic"><option value="research">Research</option><option value="teaching">Teaching</option></select>'
        . '<textarea name="message" aria-label="Message"></textarea>'
        . '<button type="submit">Send</button></form>'
        . '<script>window.__n9eh_form_script_probe=1</script>';
});
$formSettings = ELHF_H_Settings::defaults();
$formSettings['show_form_popup'] = 'yes';
$formSettings['form_mode'] = 'shortcode';
$formSettings['form_shortcode'] = '[n9eh_browser_form]';
$method->invoke(ELHF_H_Renderer::instance(), 'form', $formSettings, [], 1);
