<?php
define('ABSPATH', __DIR__ . '/');
$GLOBALS['ncu_test_post_type']='nlf_linkpage';
$GLOBALS['ncu_test_template']='/plugins/9link-flyer/templates/page.php';
function add_filter($hook,$cb,$priority=10,$accepted=1){ return true; }
function apply_filters($hook,$value){ return $value; }
function wp_normalize_path($v){ return str_replace('\\','/',(string)$v); }
function untrailingslashit($v){ return rtrim((string)$v,'/\\'); }
function trailingslashit($v){ return rtrim((string)$v,'/\\').'/'; }
function is_singular(){ return true; }
function get_queried_object_id(){ return 77; }
function absint($v){ return abs((int)$v); }
function get_post_type($id=0){ return $GLOBALS['ncu_test_post_type']; }
function sanitize_key($v){ return strtolower(preg_replace('/[^a-z0-9_\-]/i','',(string)$v)); }
function sanitize_html_class($v){ return sanitize_key($v); }
function get_template_directory(){ return '/theme/9code'; }
function get_stylesheet_directory(){ return '/theme/9code'; }
require dirname(__DIR__) . '/inc/host-compatibility.php';
function t($c,$m){ if(!$c){ fwrite(STDERR,"FAIL: $m\n"); exit(1);} }
ncu_theme_capture_selected_template($GLOBALS['ncu_test_template']);
t(ncu_theme_is_external_template(),'external plugin template detected');
t(ncu_theme_host_mode()==='plugin','external template gets plugin host mode');
t(!ncu_theme_should_enqueue_presentation_assets(),'Theme presentation assets disabled for plugin template');
t(!ncu_theme_allows_style_takeover(),'aggressive takeover disabled for plugin template');
$ctx=ncu_theme_default_surface_context();
t(!empty($ctx['suppress_theme_header']),'Theme header suppressed for plugin-owned shell');
t(!empty($ctx['suppress_theme_footer']),'Theme footer suppressed for plugin-owned shell');
t(empty($ctx['suppress_theme_quick_actions']),'Quick Actions remain available');
$GLOBALS['ncu_test_template']='/theme/9code/single.php';
ncu_theme_capture_selected_template($GLOBALS['ncu_test_template']);
t(!ncu_theme_is_external_template(),'Theme template recognized');
t(ncu_theme_host_mode()==='compatibility','foreign CPT using Theme template gets compatibility mode');
t(ncu_theme_should_enqueue_presentation_assets(),'Theme base assets remain for Theme template');
t(!ncu_theme_allows_style_takeover(),'aggressive takeover disabled for foreign CPT compatibility');
$GLOBALS['ncu_test_post_type']='post';
t(ncu_theme_host_mode()==='theme','native post remains Theme-owned');
t(ncu_theme_allows_style_takeover(),'native post may use configured Theme takeover');
echo "PASS universal CPT host compatibility runtime\n";
