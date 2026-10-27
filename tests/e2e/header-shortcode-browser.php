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
