<?php
/**
 * Contact Form 7 (pinned real plugin) versus the real Alpha header popup.
 * Disposable WP-CLI staging only. This is a render/HTML compatibility test,
 * not a browser/AJAX delivery test or a license to allow executable HTML.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')
    || strpos(wp_normalize_path(ABSPATH), '/.wp-test/wp/') === false
    || !in_array(wp_parse_url(home_url(), PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) {
    fwrite(STDERR, "CF7_TEST_REFUSED: requires disposable localhost .wp-test WordPress.\n");
    exit(2);
}
if (!class_exists('WPCF7_ContactForm') || !defined('WPCF7_VERSION')
    || WPCF7_VERSION !== '6.2.3') {
    fwrite(STDERR, "CF7_TEST_REFUSED: pinned Contact Form 7 6.2.3 is not active.\n");
    exit(2);
}
if (!class_exists('ELHF_H_Renderer', false)) {
    require_once get_template_directory() . '/inc/header-footer/bootstrap.php';
    NCU_Theme_Header_Footer::instance()->boot();
}
if (!class_exists('ELHF_H_Renderer', false) || !class_exists('ELHF_H_Settings', false)) {
    fwrite(STDERR, "CF7_TEST_REFUSED: Alpha header renderer unavailable.\n");
    exit(2);
}
$id = wp_insert_post([
    'post_type' => 'wpcf7_contact_form',
    'post_status' => 'publish',
    'post_title' => 'Disposable 9EH CF7 popup compatibility fixture',
], true);
if (is_wp_error($id) || !$id) {
    fwrite(STDERR, "CF7_TEST_FAILED: could not create temporary form.\n");
    exit(1);
}
$checks = [];
try {
    update_post_meta($id, '_form', '[text* your-name] [email* your-email] [textarea your-message] [submit "Send"]');
    $shortcode = '[contact-form-7 id="' . (int) $id . '"]';
    $raw = do_shortcode($shortcode);
    $checks['CF7_REAL_SHORTCODE_RENDERED'] = strpos($raw, '<form') !== false
        && strpos($raw, 'your-email') !== false;
    $settings = ELHF_H_Settings::defaults();
    $settings['show_form_popup'] = 'yes';
    $settings['form_mode'] = 'shortcode';
    $settings['form_shortcode'] = $shortcode;
    ob_start();
    try {
        (new ReflectionMethod('ELHF_H_Renderer', 'render_popup'))
            ->invoke(ELHF_H_Renderer::instance(), 'form', $settings, [], 1);
        $html = (string) ob_get_clean();
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $checks['CF7_FORM_PRESERVED'] = strpos($html, '<form') !== false
        && strpos($html, 'wpcf7-form') !== false;
    $checks['CF7_NAME_EMAIL_PRESERVED'] = strpos($html, 'name="your-name"') !== false
        && strpos($html, 'name="your-email"') !== false;
    $checks['CF7_NONCE_AND_SUBMIT_PRESERVED'] = strpos($html, 'name="_wpcf7"') !== false
        && (strpos($html, 'type="submit"') !== false);
    $checks['CF7_EXECUTABLE_MARKUP_ABSENT'] = !preg_match(
        '/<script\b|\son[a-z]+\s*=|javascript\s*:|\ssrcdoc\s*=/i',
        $html
    );
} finally {
    wp_delete_post($id, true);
    $checks['CF7_TEMP_FORM_REMOVED'] = get_post($id) === null;
}
foreach ($checks as $name => $pass) {
    echo $name . '=' . ($pass ? 'YES' : 'NO') . "\n";
}
if (count($checks) !== 6 || in_array(false, $checks, true)) {
    fwrite(STDERR, "CF7_HEADER_COMPATIBILITY_FAILED: do not claim named-plugin support.\n");
    exit(1);
}
echo "CF7_HEADER_HTML_COMPATIBILITY_PASS: browser/AJAX/backend remain NOT_RUN.\n";
