<?php
define('ABSPATH', __DIR__ . '/');
define('NCU_THEME_URI', 'https://example.test/theme');
define('NCU_THEME_VERSION', '14.2.5');
$GLOBALS['surface_suppress'] = true;
function add_filter($tag,$cb,$p=10,$a=1){}
function add_action($tag,$cb,$p=10,$a=1){}
function apply_filters($tag,$value){ return $value; }
function is_user_logged_in(){ return true; }
function current_user_can($cap){ return true; }
function ninecodepress_surface_context($context=array()) {
    return array(
        'owner'=>'test-plugin','owns_shell'=>true,'suppress_theme_header'=>true,
        'suppress_theme_footer'=>true,'suppress_theme_quick_actions'=>!empty($GLOBALS['surface_suppress']),
        'inherit_design_tokens'=>true,'reason'=>'test'
    );
}
require dirname(__DIR__) . '/inc/frontend-safe.php';
if ( ncu_safe_front_launcher_allowed() ) { fwrite(STDERR, "FAIL: launcher must yield when surface owner suppresses quick actions\n"); exit(1); }
$GLOBALS['surface_suppress'] = false;
if ( ! ncu_safe_front_launcher_allowed() ) { fwrite(STDERR, "FAIL: launcher should be available when surface allows it\n"); exit(1); }
echo "PASS: surface-owned launcher suppression\n";
