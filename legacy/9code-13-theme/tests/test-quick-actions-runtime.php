<?php
define('ABSPATH', __DIR__ . '/');
function admin_url($p=''){return 'https://example.test/wp-admin/'.$p;}
function self_admin_url($p=''){return admin_url($p);}
function get_post_types(){return array();}
function get_option($k,$d=array()){return $d;}
function apply_filters($tag,$value){return $value;}
function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_\-]/','',(string)$v));}
require dirname(__DIR__) . '/inc/block-edition/class-quick-actions.php';
function t($c,$m){if(!$c){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
$defaults=N9BE_Quick_Actions::defaults();
t(in_array('ninecode_theme',$defaults,true),'Theme is a default Quick Action');
$catalog=N9BE_Quick_Actions::catalog();
t(isset($catalog['ninecode_theme']),'Theme action exists in catalog');
t($catalog['ninecode_theme']['label']==='9Code Theme','Theme action label');
t(strpos($catalog['ninecode_theme']['url'],'themes.php?page=ninecode-theme-display')!==false,'Theme action opens Theme-owned display settings');
echo "PASS Quick Actions runtime catalog\n";
