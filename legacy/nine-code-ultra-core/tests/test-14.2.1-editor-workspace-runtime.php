<?php
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');
define('NCU_CORE_URL', 'https://example.test/wp-content/plugins/nine-code-ultra-core/');
define('NCU_CORE_VERSION', '14.2.3');
$GLOBALS['ncu_test_styles']=array();
$GLOBALS['ncu_test_scripts']=array();
$GLOBALS['ncu_test_localized']=array();
class NCU_Test_Screen { public $base='post'; public $post_type='post'; public function is_block_editor(){ return true; } }
function is_admin(){ return true; }
function get_current_screen(){ return new NCU_Test_Screen(); }
function sanitize_key($v){ return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v)); }
function wp_unslash($v){ return $v; }
function ncu_get_settings(){ return array('admin_editor_tools_drawer'=>1,'admin_editor_high_contrast'=>1); }
function add_filter(){ return true; }
function add_action(){ return true; }
function current_user_can($cap){ return true; }
function wp_enqueue_style($h,$src='',$deps=array(),$ver=false){ $GLOBALS['ncu_test_styles'][$h]=$src; }
function wp_enqueue_script($h,$src='',$deps=array(),$ver=false,$footer=false){ $GLOBALS['ncu_test_scripts'][$h]=$src; }
function wp_script_add_data(){ return true; }
function wp_localize_script($h,$name,$data){ $GLOBALS['ncu_test_localized'][$name]=$data; }
function admin_url($path=''){ return 'https://example.test/wp-admin/'.$path; }
function home_url($path=''){ return 'https://example.test'.$path; }
function ncu_core_data_manager_url(){ return admin_url('admin.php?page=nine-post-manager'); }
function apply_filters($tag,$value){ return $value; }
function __($text,$domain=null){ return $text; }
require dirname(__DIR__) . '/inc/editor-workspace.php';
if(!ncu_editor_workspace_is_post_editor() || !ncu_editor_workspace_is_block_editor()){fwrite(STDERR,"editor detection failed\n");exit(1);} 
$classes=ncu_editor_workspace_body_class('');
foreach(array('ncu-editor-workspace','ncu-editor-tools-enabled','ncu-editor-high-contrast') as $c){ if(strpos($classes,$c)===false){fwrite(STDERR,"missing class $c\n");exit(1);} }
ncu_editor_workspace_assets();
if(empty($GLOBALS['ncu_test_styles']['ncu-editor-workspace']) || empty($GLOBALS['ncu_test_scripts']['ncu-editor-workspace'])){fwrite(STDERR,"assets missing\n");exit(1);} 
$cfg=$GLOBALS['ncu_test_localized']['NCUEditorWorkspace']??array();
if(($cfg['breakpoint']??0)!==1180 || empty($cfg['shortcuts']) || ($cfg['title']??'')!=='Editor Tools'){fwrite(STDERR,"config bad\n");exit(1);} 
echo "PASS: 14.2.3 editor workspace runtime\n";
