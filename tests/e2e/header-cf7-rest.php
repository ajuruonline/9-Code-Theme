<?php
/**
 * Disposable Contact Form 7 REST dispatch acceptance (pinned v6.2.3).
 * Each CF7_REST_CASE executes in a separate WP-CLI process because CF7
 * maintains a per-request submission singleton. This is NOT browser/AJAX HTTP.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')
    || strpos(wp_normalize_path(ABSPATH), '/.wp-test/wp/') === false
    || !in_array(wp_parse_url(home_url(), PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) {
    fwrite(STDERR, "CF7_REST_REFUSED: requires disposable localhost .wp-test WordPress.\n");
    exit(2);
}
if (!class_exists('WPCF7_ContactForm') || !defined('WPCF7_VERSION')
    || WPCF7_VERSION !== '6.2.3') {
    fwrite(STDERR, "CF7_REST_REFUSED: pinned Contact Form 7 6.2.3 is not active.\n");
    exit(2);
}
$case = getenv('CF7_REST_CASE');
if (!in_array($case, ['valid', 'invalid', 'missing_unit', 'guest_management', 'subscriber_management'], true)) {
    fwrite(STDERR, "CF7_REST_REFUSED: unknown case.\n");
    exit(2);
}
$id = wp_insert_post([
    'post_type' => 'wpcf7_contact_form',
    'post_status' => 'publish',
    'post_title' => 'Disposable 9EH CF7 REST ' . $case,
], true);
if (is_wp_error($id) || !$id) {
    fwrite(STDERR, "CF7_REST_FAILED: could not create temporary form.\n");
    exit(1);
}
$old_post = $_POST;
$old_user = get_current_user_id();
$temp_user_id = 0;
$mail_calls = 0;
$skip_mail = static function () { return true; };
$skip_spam = static function () { return true; }; // No claim about spam filters.
$intercept_mail = static function ($preempt) use (&$mail_calls) {
    ++$mail_calls;
    return true; // Second independent barrier: never deliver outbound mail.
};
$checks = [];
try {
    update_post_meta($id, '_form', '[text* your-name] [email* your-email] [textarea your-message] [submit "Send"]');
    // This public submission route intentionally allows guests. Form management does not.
    wp_set_current_user(0);
    add_filter('wpcf7_skip_mail', $skip_mail, 999);
    add_filter('wpcf7_skip_spam_check', $skip_spam, 999);
    add_filter('pre_wp_mail', $intercept_mail, 999);
    rest_get_server(); // Register actual Contact Form 7 REST routes.
    $route = '/contact-form-7/v1/contact-forms/' . (int) $id;
    if ($case === 'subscriber_management') {
        // Real logged-in low-privilege user, not a simulated capability filter.
        // The temporary user is deleted in finally, even if assertions fail.
        $temp_user_id = wp_insert_user([
            'user_login' => 'cf7_ci_' . strtolower(wp_generate_password(12, false, false)),
            'user_pass' => wp_generate_password(24, true, true),
            'user_email' => 'cf7-ci-' . strtolower(wp_generate_password(12, false, false)) . '@example.test',
            'role' => 'subscriber',
        ]);
        if (is_wp_error($temp_user_id) || !$temp_user_id) {
            throw new RuntimeException('CF7_REST_FAILED: temporary subscriber creation failed.');
        }
        wp_set_current_user($temp_user_id);
        $request = new WP_REST_Request('GET', $route);
        $response = rest_do_request($request);
        $data = $response->get_data();
        $checks['CF7_REST_SUBSCRIBER_MANAGEMENT_DENIED'] = $response->get_status() === 403
            && is_array($data) && ($data['code'] ?? '') === 'wpcf7_forbidden';
    } elseif ($case === 'guest_management') {
        $request = new WP_REST_Request('GET', $route);
        $response = rest_do_request($request);
        $data = $response->get_data();
        // Anonymous requests return 401; authenticated insufficient roles return 403.
        // CF7 v6.2.3 delegates this to rest_authorization_required_code().
        $checks['CF7_REST_GUEST_MANAGEMENT_DENIED'] = $response->get_status() === 401
            && is_array($data) && ($data['code'] ?? '') === 'wpcf7_forbidden';
    } else {
        $payload = [
            '_wpcf7' => (string) $id,
            '_wpcf7_version' => WPCF7_VERSION,
            '_wpcf7_locale' => get_locale(),
            '_wpcf7_unit_tag' => 'wpcf7-f' . (int) $id . '-o1',
            '_wpcf7_container_post' => '0',
            'your-name' => 'Disposable Tester',
            'your-email' => 'tester@example.test',
            'your-message' => 'Disposable CI test only',
        ];
        if ($case === 'invalid') {
            $payload['your-email'] = 'not-an-email';
        } elseif ($case === 'missing_unit') {
            unset($payload['_wpcf7_unit_tag']);
        }
        // CF7 submission processing consumes $_POST, while its REST controller
        // reads WP_REST_Request. Both must match the simulated multipart request.
        $_POST = $payload;
        $request = new WP_REST_Request('POST', $route . '/feedback');
        $request->set_header('Content-Type', 'multipart/form-data; boundary=9eh-disposable');
        $request->set_body_params($payload);
        $response = rest_do_request($request);
        $data = $response->get_data();
        if ($case === 'valid') {
            $checks['CF7_REST_GUEST_VALID_ACCEPTED'] = $response->get_status() === 200
                && is_array($data) && ($data['status'] ?? '') === 'mail_sent';
        } elseif ($case === 'invalid') {
            $invalid_fields = is_array($data) ? ($data['invalid_fields'] ?? []) : [];
            $names = array_column(is_array($invalid_fields) ? $invalid_fields : [], 'field');
            $checks['CF7_REST_INVALID_EMAIL_REJECTED'] = $response->get_status() === 200
                && ($data['status'] ?? '') === 'validation_failed'
                && in_array('your-email', $names, true);
        } else {
            $checks['CF7_REST_MISSING_UNIT_REJECTED'] = $response->get_status() === 400
                && is_array($data) && ($data['code'] ?? '') === 'wpcf7_unit_tag_not_found';
        }
    }
    $checks['CF7_REST_NO_OUTBOUND_MAIL'] = $mail_calls === 0;
} finally {
    $_POST = $old_post;
    wp_set_current_user($old_user);
    if (is_int($temp_user_id) && $temp_user_id > 0) {
        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        wp_delete_user($temp_user_id);
        $checks['CF7_REST_TEMP_USER_REMOVED'] = get_user_by('id', $temp_user_id) === false;
    }
    remove_filter('wpcf7_skip_mail', $skip_mail, 999);
    remove_filter('wpcf7_skip_spam_check', $skip_spam, 999);
    remove_filter('pre_wp_mail', $intercept_mail, 999);
    wp_delete_post($id, true);
    $checks['CF7_REST_TEMP_FORM_REMOVED'] = get_post($id) === null;
}
foreach ($checks as $name => $pass) {
    echo $name . '=' . ($pass ? 'YES' : 'NO') . "\n";
}
if (in_array(false, $checks, true) || count($checks) !== ($case === 'subscriber_management' ? 4 : 3)) {
    fwrite(STDERR, "CF7_REST_DISPATCH_FAILED: case=" . $case . "; no HTTP/browser claim.\n");
    exit(1);
}
echo "CF7_REST_DISPATCH_PASS=" . $case . "; mail suppressed; HTTP/browser/AJAX NOT_RUN.\n";
