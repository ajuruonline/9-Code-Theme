<?php
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/fakewp/');
define('NCU_THEME_VERSION','14.2.5');
define('NCU_THEME_URI','https://example.test/wp-content/themes/9code');
define('NCU_THEME_DIR',dirname(__DIR__));
$GLOBALS['actions']=[]; $GLOBALS['filters']=[]; $GLOBALS['post_type']='post'; $GLOBALS['post_id']=1; $GLOBALS['meta']=[];
function add_action($h,$cb,$p=10,$a=1){$GLOBALS['actions'][$h][]=$cb;}
function add_filter($h,$cb,$p=10,$a=1){$GLOBALS['filters'][$h][]=$cb;}
function apply_filters($h,$v){return $v;}
function get_template_directory(){return dirname(__DIR__);}
function get_stylesheet_directory(){return dirname(__DIR__);}
function wp_normalize_path($p){return str_replace('\\','/',$p);}
function is_singular(){return true;}
function get_post_type($id=0){return $GLOBALS['post_type'];}
function get_queried_object_id(){return $GLOBALS['post_id'];}
function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$key]??'';}
function get_page_template_slug($id=0){return $GLOBALS['meta']['_wp_page_template']??'';}
function get_query_var($k,$d=''){return $d;}
function wp_enqueue_style(){return true;} function wp_enqueue_script(){return true;} function wp_localize_script(){return true;}
function get_stylesheet_uri(){return 'https://example.test/style.css';}
function is_user_logged_in(){return false;} function current_user_can(){return false;} function admin_url($p=''){return $p;} function home_url($p=''){return $p;} function wp_create_nonce(){return 'n';} function wp_max_upload_size(){return 1;}
function absint($v){return abs((int)$v);} function get_option($k,$d=[]){return $d;} function sanitize_hex_color($v){return $v;} function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_\-]/i','',$v));}
function esc_attr($v){return $v;} function esc_url($v){return $v;} function esc_html($v){return $v;}
require dirname(__DIR__) . '/inc/frontend-safe.php';
function t($c,$m){if(!$c){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
t(function_exists('ncu_safe_capture_template_owner'),'template ownership recorder exists');
$external='/srv/www/wp-content/plugins/conference-suite/templates/single-conference.php';
ncu_safe_capture_template_owner($external);
t(false===ncu_safe_should_enqueue_presentation_css(),'external plugin template yields Theme presentation CSS');
$theme=dirname(__DIR__).'/single.php';
ncu_safe_capture_template_owner($theme);
$GLOBALS['post_type']='conference';
t(false===ncu_safe_should_enqueue_presentation_css(),'all custom post types yield by default without hard-coded slug');
$GLOBALS['post_type']='post';
$GLOBALS['meta']=['_elementor_edit_mode'=>'builder'];
t(false===ncu_safe_should_enqueue_presentation_css(),'Elementor-built post yields Theme presentation CSS');
$GLOBALS['meta']=[];
t(true===ncu_safe_should_enqueue_presentation_css(),'native WordPress post can use Theme presentation CSS');
echo "PASS Conference compatibility Theme contract\n";
