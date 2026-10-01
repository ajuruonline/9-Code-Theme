<?php
// Three-suite surface-contract harness: discontinued Mason/9Page special cases stay absent.
define('ABSPATH', __DIR__);
function sanitize_key($v){ return strtolower(preg_replace('/[^a-z0-9_\-]/i','',$v)); }
function apply_filters($tag,$value){ return $value; }
require dirname(__DIR__) . '/inc/surface-contract.php';
function t($c,$m){if(!$c){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
t(!function_exists('ninecode_theme_known_companion_surface'),'discontinued companion resolver must stay removed');
$ctx=ninecode_theme_surface_context(array());
t(($ctx['owner']??'')==='wordpress','default owner is WordPress');
t(empty($ctx['suppress_theme_header']),'Theme header not suppressed by default');
t(empty($ctx['suppress_theme_footer']),'Theme footer not suppressed by default');
t(empty($ctx['suppress_theme_quick_actions']),'Quick Actions not suppressed by default');
t(!empty($ctx['inherit_design_tokens']),'design tokens inherit by default');
echo "PASS three-suite generic surface runtime\n";
