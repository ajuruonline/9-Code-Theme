<?php
define('ABSPATH',__DIR__);
define('NCU_CORE_VERSION','14.2.0'); define('NCU_CORE_API_VERSION',14);
function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_\-]/i','',$v));}
function apply_filters($t,$v){return $v;}
require dirname(__DIR__).'/inc/integration-contracts.php';
function t($c,$m){if(!$c){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
t(function_exists('ninecodepress_interop_policy'),'interop policy exists');
$p=ninecodepress_interop_policy();
t(($p['routing_owner']??'')==='wordpress','WordPress owns routing');
t(($p['template_owner']??'')==='request_owner','request owner keeps template');
t(empty($p['register_foreign_cpts']??true),'Core does not register foreign CPTs');
t(empty($p['rewrite_foreign_meta']??true),'Core does not rewrite foreign meta');
t(!empty($p['elementor_yield']),'Core yields to Elementor');
t(!empty($p['acf_provider_only']),'ACF is provider, not Core-owned database');
echo "PASS Conference interop Core contract\n";
