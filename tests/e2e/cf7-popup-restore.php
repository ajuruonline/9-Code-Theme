<?php
/** Restore the exact pre-fixture option on disposable WordPress, even after failure. */
if (!defined('ABSPATH') || !defined('WP_CLI')
    || strpos(wp_normalize_path(ABSPATH), '/.wp-test/wp/') === false
    || !in_array(wp_parse_url(home_url(), PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) {
    fwrite(STDERR, "CF7_POPUP_RESTORE_REFUSED: not disposable localhost.\n");
    exit(2);
}
$backup = getenv('CF7_BROWSER_SETTINGS_BACKUP');
if (!$backup || !is_file($backup)) {
    fwrite(STDERR, "CF7_POPUP_RESTORE_FAILED: missing settings backup.\n");
    exit(1);
}
$data = json_decode((string) file_get_contents($backup), true, 512, JSON_THROW_ON_ERROR);
if (!is_array($data) || !array_key_exists('exists', $data)
    || !array_key_exists('value', $data)) {
    fwrite(STDERR, "CF7_POPUP_RESTORE_FAILED: invalid settings backup.\n");
    exit(1);
}
$key = 'n9lh8_settings';
if ($data['exists']) {
    update_option($key, $data['value'], false);
    $ok = get_option($key) === $data['value'];
} else {
    delete_option($key);
    $ok = get_option($key, '__missing__') === '__missing__';
}
if (!$ok) {
    fwrite(STDERR, "CF7_POPUP_RESTORE_FAILED: option readback mismatch.\n");
    exit(1);
}
echo "CF7_POPUP_SETTINGS_RESTORED=YES\n";
