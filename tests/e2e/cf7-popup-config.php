<?php
/** Disposable local-only Alpha header configuration for real CF7 popup E2E. */
if (!defined('ABSPATH') || !defined('WP_CLI')
    || strpos(wp_normalize_path(ABSPATH), '/.wp-test/wp/') === false
    || !in_array(wp_parse_url(home_url(), PHP_URL_HOST), ['localhost', '127.0.0.1'], true)
    || !defined('WPCF7_VERSION') || WPCF7_VERSION !== '6.2.3') {
    fwrite(STDERR, "CF7_POPUP_REFUSED: requires disposable localhost WordPress and pinned CF7.\n");
    exit(2);
}
$id = (int) getenv('CF7_BROWSER_FORM_ID');
$backup = getenv('CF7_BROWSER_SETTINGS_BACKUP');
if ($id <= 0 || get_post_type($id) !== 'wpcf7_contact_form'
    || !$backup || !is_dir(dirname($backup)) || is_file($backup)) {
    fwrite(STDERR, "CF7_POPUP_REFUSED: invalid temporary form or settings backup.\n");
    exit(2);
}
if (!class_exists('ELHF_H_Settings', false)) {
    require_once get_template_directory() . '/inc/header-footer/bootstrap.php';
    NCU_Theme_Header_Footer::instance()->boot();
}
$key = ELHF_H_Settings::OPTION;
$missing = '__n9eh_option_missing_20261028__';
$original = get_option($key, $missing);
$payload = json_encode(['exists' => $original !== $missing, 'value' => $original],
    JSON_THROW_ON_ERROR);
if (file_put_contents($backup, $payload, LOCK_EX) === false) {
    fwrite(STDERR, "CF7_POPUP_REFUSED: cannot safely save original settings.\n");
    exit(2);
}
chmod($backup, 0600);
$s = array_merge(ELHF_H_Settings::defaults(),
    is_array($original) ? $original : []);
$s['enabled'] = 'yes';
$s['show_form_popup'] = 'yes';
$s['form_mode'] = 'shortcode';
$s['form_shortcode'] = '[contact-form-7 id="' . $id . '"]';
$s['form_popup_title'] = 'Disposable CF7 popup';
$s['header_actions'] = [[
    'uid' => 'n9eh-test-cf7', 'enabled' => 'yes', 'kind' => 'form',
    'label' => 'Open disposable form', 'icon' => 'form',
    'icon_url' => '', 'url' => '', 'target' => 'same', 'event_key' => '',
]];
update_option($key, $s, false);
if (!ELHF_H_Settings::is_enabled()) {
    fwrite(STDERR, "CF7_POPUP_FAILED: disposable header was not enabled.\n");
    exit(1);
}
echo "CF7_POPUP_TEMP_SETTINGS_CONFIGURED=YES\n";
