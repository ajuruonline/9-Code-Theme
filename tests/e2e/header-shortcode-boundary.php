<?php
/**
 * Disposable WordPress evidence probe for the 9 Code Alpha header popup.
 *
 * BLOCKING REGRESSION: unsafe shortcode output MUST exit nonzero. A passing
 * process only certifies these narrow HTML-output assertions, not browser
 * execution, permission boundaries or the complete security posture.
 * Do not register these fixture shortcodes on a production installation.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')) {
    fwrite(STDERR, "ERROR: run with wp eval-file on disposable WordPress.\n");
    exit(2);
}
if (!class_exists('ELHF_H_Renderer', false)) {
    require_once get_template_directory() . '/inc/header-footer/bootstrap.php';
    NCU_Theme_Header_Footer::instance()->boot();
}
if (!class_exists('ELHF_H_Renderer', false) || !class_exists('ELHF_H_Settings', false)) {
    fwrite(STDERR, "ERROR: header renderer unavailable.\n");
    exit(2);
}
add_shortcode('n9eh_fixture_video', static function () {
    return '<iframe title="Fixture video" src="https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ" allowfullscreen></iframe>';
});
add_shortcode('n9eh_fixture_form', static function () {
    return '<form method="post" action="/wp-admin/admin-post.php"><input type="hidden" name="fixture_nonce" value="safe-fixture"><button type="submit">Send</button></form>';
});
add_shortcode('n9eh_fixture_hostile', static function () {
    return '<script>window.__n9eh_script_probe=1</script><img src="x" onerror="window.__n9eh_event_probe=1"><a href="javascript:alert(1)">Bad link</a><iframe srcdoc="&lt;script&gt;alert(1)&lt;/script&gt;"></iframe>';
});
$method = new ReflectionMethod('ELHF_H_Renderer', 'render_popup');
$renderer = ELHF_H_Renderer::instance();
$settings = ELHF_H_Settings::defaults();
$settings['show_video_popup'] = 'yes';
$settings['show_form_popup'] = 'yes';
$settings['video_source'] = 'shortcode';
$settings['form_mode'] = 'shortcode';
$render = static function ($kind, $shortcode) use ($settings, $method, $renderer) {
    $s = $settings;
    $s[$kind . '_shortcode'] = $shortcode;
    ob_start();
    try {
        $method->invoke($renderer, $kind, $s, [], 1);
        return (string) ob_get_clean();
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
};
$video = $render('video', '[n9eh_fixture_video]');
$form = $render('form', '[n9eh_fixture_form]');
$hostile_video = $render('video', '[n9eh_fixture_hostile]');
$hostile_form = $render('form', '[n9eh_fixture_hostile]');
$valid_video = strpos($video, '<iframe') !== false && strpos($video, 'youtube-nocookie.com') !== false;
$valid_form = strpos($form, '<form') !== false && strpos($form, 'fixture_nonce') !== false;
$script_raw = strpos($hostile_video, '<script>') !== false || strpos($hostile_form, '<script>') !== false;
$handler_raw = strpos($hostile_video, ' onerror=') !== false || strpos($hostile_form, ' onerror=') !== false;
$unsafe_url_raw = strpos($hostile_video, 'javascript:') !== false || strpos($hostile_form, 'javascript:') !== false;
$srcdoc_raw = strpos($hostile_video, ' srcdoc=') !== false || strpos($hostile_form, ' srcdoc=') !== false;
$cases = [
    'LEGITIMATE_VIDEO_PRESERVED' => $valid_video,
    'LEGITIMATE_FORM_PRESERVED' => $valid_form,
    'RAW_SCRIPT_PRESENT' => $script_raw,
    'RAW_EVENT_HANDLER_PRESENT' => $handler_raw,
    'RAW_UNSAFE_URL_PRESENT' => $unsafe_url_raw,
    'RAW_SRCDOC_PRESENT' => $srcdoc_raw,
];
foreach ($cases as $name => $observed) {
    echo $name . '=' . ($observed ? 'YES' : 'NO') . "\n";
}
if (!$valid_video || !$valid_form) {
    fwrite(STDERR, "FAILED: legitimate video/form shortcode contract regressed.\n");
    exit(1);
}
if ($script_raw || $handler_raw || $unsafe_url_raw || $srcdoc_raw) {
    fwrite(STDERR, "SECURITY_GATE_FAILED: unsafe shortcode HTML reached public popup; browser exploitability not established.\n");
    exit(1);
}
echo "SHORTCODE_OUTPUT_ASSERTIONS_PASS: browser execution, permission and full compatibility tests remain outstanding.\n";
