<?php
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
$GLOBALS['ncu_test_meta'] = array();
$GLOBALS['ncu_test_options'] = array();
function add_action($a,$b,$c=null,$d=null){}
function register_post_meta($type,$key,$args){}
function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_\-]/','',(string)$v));}
function absint($v){return abs((int)$v);}
function get_post_meta($post_id,$key,$single=true){return isset($GLOBALS['ncu_test_meta'][$post_id][$key]) ? $GLOBALS['ncu_test_meta'][$post_id][$key] : '';}
function get_option($key,$default=false){return array_key_exists($key,$GLOBALS['ncu_test_options'])?$GLOBALS['ncu_test_options'][$key]:$default;}
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
$id=88;
t(ncu_should_show_post_feature($id,'title',true)===false,'site default hides Theme title');
t(ncu_should_show_post_feature($id,'meta',true)===false,'site default hides Theme post meta');
$GLOBALS['ncu_test_meta'][$id]['_ncu_display_title']='show';
t(ncu_should_show_post_feature($id,'title',false)===true,'per-item explicit Show overrides hidden site default');
$GLOBALS['ncu_test_meta'][$id]['_ncu_display_meta']='show';
t(ncu_should_show_post_feature($id,'meta',false)===true,'per-item meta Show overrides hidden site default');
$GLOBALS['ncu_test_options']['ncu_theme_display_defaults']=array('show_title'=>1,'show_meta'=>1);
$GLOBALS['ncu_test_meta'][$id]=array();
t(ncu_should_show_post_feature($id,'title',false)===true,'site setting can turn Theme title back on by default');
t(ncu_should_show_post_feature($id,'meta',false)===true,'site setting can turn Theme meta back on by default');
echo "PASS site display defaults runtime\n";
