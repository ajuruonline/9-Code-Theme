<?php
define('ABSPATH', __DIR__ . '/');
define('NINE55_ULTRON_DATA_VERSION','9.10.3');
$GLOBALS['hooks']=array();
function add_action($hook,$cb,$priority=10){$GLOBALS['hooks'][]=$hook.'@'.$priority;}
function admin_url($p=''){return 'https://example.test/wp-admin/'.$p;}
function self_admin_url($p=''){return admin_url($p);}
function get_post_types(){return array();}
function get_option($k,$d=array()){return $d;}
function apply_filters($tag,$value){return $value;}
function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_\-]/','',(string)$v));}
require dirname(__DIR__) . '/inc/block-edition/class-quick-actions.php';
function t($c,$m){if(!$c){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
N9BE_Quick_Actions::boot();
t(in_array('wp_enqueue_scripts@1001',$GLOBALS['hooks'],true),'front-end assets hook');
t(in_array('wp_footer@1001',$GLOBALS['hooks'],true),'front-end markup hook');
$catalog=N9BE_Quick_Actions::catalog();
t(isset($catalog['data_post_editor']),'Post Editor in catalog');
t(isset($catalog['data_category_manager']),'Category Manager in catalog');
t($catalog['add_plugin']['label']==='Install / Replace Plugin','replace plugin label');
t(N9BE_Quick_Actions::CURRENT_SCHEMA===7,'schema 7');
echo "PASS front-end Quick Actions runtime contract\n";
