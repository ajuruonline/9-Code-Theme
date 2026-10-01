<?php
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/fakewp/');
$root = dirname(__DIR__);
function get_template_directory(){ return dirname(__DIR__); }
function get_template_directory_uri(){ return 'https://example.test/wp-content/themes/9code'; }
function is_admin(){ return false; }
function add_action($h,$cb,$p=10,$a=1){ $GLOBALS['actions'][$h][]=$cb; }
function add_filter($h,$cb,$p=10,$a=1){ $GLOBALS['filters'][$h][]=$cb; }
function load_theme_textdomain(){ return true; }
function add_theme_support(){ return true; }
function add_editor_style(){ return true; }
function register_nav_menus(){ return true; }
function register_sidebar(){ return true; }
function register_block_pattern_category(){ return true; }
function register_block_style(){ return true; }
function __($s){ return $s; }
function esc_html__($s){ return $s; }
function get_stylesheet_uri(){ return 'https://example.test/style.css'; }
function wp_enqueue_style(){ return true; }
function wp_enqueue_script(){ return true; }
function wp_localize_script(){ return true; }
function is_user_logged_in(){ return false; }
function current_user_can(){ return false; }
function admin_url($p=''){ return 'https://example.test/wp-admin/'.$p; }
function home_url($p=''){ return 'https://example.test/'.$p; }
function wp_create_nonce(){ return 'nonce'; }
function wp_max_upload_size(){ return 1000000; }
function absint($v){ return abs((int)$v); }
function get_option($k,$d=array()){ return $d; }
function sanitize_hex_color($v){ return is_string($v) ? $v : ''; }
require $root . '/functions.php';
if (!defined('NCU_THEME_VERSION') || NCU_THEME_VERSION !== '15.0.2') { fwrite(STDERR,"bad version\n"); exit(1); }
if (!function_exists('ncu_safe_public_enqueue')) { fwrite(STDERR,"safe public runtime missing\n"); exit(1); }
foreach (['ELHF_H_Settings','N9BE_Quick_Actions'] as $cls) {
    if (class_exists($cls,false)) { fwrite(STDERR,"heavy public class loaded: $cls\n"); exit(1); }
}
foreach (['ncu_theme_style_takeover_css','ncu_theme_host_mode','ncu_output_post_content'] as $fn) {
    if (function_exists($fn)) { fwrite(STDERR,"heavy public function loaded: $fn\n"); exit(1); }
}
echo "PASS 15.0.2 public bootstrap runtime\n";
