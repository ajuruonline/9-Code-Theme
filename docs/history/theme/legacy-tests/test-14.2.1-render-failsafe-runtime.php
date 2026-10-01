<?php
error_reporting(E_ALL);
define('ABSPATH', __DIR__.'/'); define('NCU_THEME_VERSION','14.2.1');
function absint($v){return abs((int)$v);} function sanitize_key($v){return (string)$v;} function sanitize_html_class($v){return (string)$v;}
function ncu_theme_core_compatible(){return false;} function get_post_meta(){return '';} function get_post_type(){return 'page';}
function ncu_theme_post_type_is_custom(){return false;} function get_the_ID(){return 10;} function get_post_field($field,$id){return '<p>Native fallback</p>';}
function wp_kses_post($v){return $v;}
function apply_filters($tag,$value,...$args){ if($tag==='ncu_builder_full_width' || $tag==='ncu_auto_builder_html'){ throw new Error('synthetic external renderer failure'); } return $value; }
function has_blocks(){return false;} function do_blocks($v){return $v;} function wptexturize($v){return $v;} function convert_smilies($v){return $v;} function wpautop($v){return $v;} function shortcode_unautop($v){return $v;} function do_shortcode($v){return $v;}
require dirname(__DIR__).'/inc/renderers.php';
try { $full=ncu_builder_full_width(10); } catch(Throwable $e){fwrite(STDERR,"FAIL full width escaped\n");exit(1);} if($full!==false){fwrite(STDERR,"FAIL unsafe full-width fallback\n");exit(1);} 
ob_start(); try { ncu_output_post_content(10); } catch(Throwable $e){ob_end_clean();fwrite(STDERR,"FAIL content escaped\n");exit(1);} $out=ob_get_clean();
if($out!=='<p>Native fallback</p>'){fwrite(STDERR,"FAIL native content fallback mismatch: $out\n");exit(1);} 
echo "PASS Theme render fail-safe runtime\n";
