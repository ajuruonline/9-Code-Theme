<?php
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
$GLOBALS['ncu_test_meta'] = array();
function add_action($a,$b,$c=null,$d=null){}
function register_post_meta($type,$key,$args){}
function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_\-]/','',(string)$v));}
function absint($v){return abs((int)$v);}
function get_post_meta($post_id,$key,$single=true){return isset($GLOBALS['ncu_test_meta'][$post_id][$key]) ? $GLOBALS['ncu_test_meta'][$post_id][$key] : '';}
function get_option($key,$default=false){return $default;}
function current_user_can(){return true;}
function apply_filters($tag,$value){return $value;}
function sanitize_html_class($v){return preg_replace('/[^A-Za-z0-9_-]/','',(string)$v);}
function wp_enqueue_script(){}
function get_current_screen(){return null;}
function wp_nonce_field(){}
function esc_html($v){return $v;}
function esc_attr($v){return $v;}
function selected($a,$b,$echo=false){return $a===$b?' selected="selected"':'';}
function __($v){return $v;}
function wp_verify_nonce(){return true;}
function sanitize_text_field($v){return (string)$v;}
function wp_unslash($v){return $v;}
function update_post_meta($id,$key,$value){$GLOBALS['ncu_test_meta'][$id][$key]=$value;}
require dirname(__DIR__) . '/inc/post-display-settings.php';
function t($cond,$msg){if(!$cond){fwrite(STDERR,"FAIL: $msg\n");exit(1);}}
$id=77;
$GLOBALS['ncu_test_meta'][$id]=array('_ncu_presentation_owner'=>'plugin');
t(ncu_should_show_post_feature($id,'title',true)===false,'plugin owner suppresses title');
t(ncu_should_show_post_feature($id,'meta',true)===false,'plugin owner suppresses meta');
t(ncu_should_show_post_feature($id,'featured',true)===false,'plugin owner suppresses featured image');
t(ncu_should_show_post_feature($id,'comments',true)===true,'plugin owner does not silently suppress comments');
$GLOBALS['ncu_test_meta'][$id]['_ncu_display_title']='show';
t(ncu_should_show_post_feature($id,'title',false)===true,'explicit show overrides plugin owner');
$GLOBALS['ncu_test_meta'][$id]['_ncu_display_meta']='hide';
t(ncu_should_show_post_feature($id,'meta',true)===false,'explicit hide wins');
$GLOBALS['ncu_test_meta'][$id]['_ncu_content_width']='wide';
t(ncu_post_content_width($id)==='wide','wide content setting round-trips');
t(ncu_post_content_width_class($id)===' ncu-content-width-wide','wide class generated');
t(ncu_sanitize_display_setting('SCRIPT')==='inherit','invalid visibility is rejected');
echo "PASS display settings runtime\n";
