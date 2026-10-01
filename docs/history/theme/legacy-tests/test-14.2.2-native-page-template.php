<?php
/* Regression: the public Page template must render with WordPress primitives only. */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['ncu_test_loop'] = 0;
function get_header(){ echo '<HEADER>'; }
function get_footer(){ echo '<FOOTER>'; }
function have_posts(){ return $GLOBALS['ncu_test_loop'] < 1; }
function the_post(){ $GLOBALS['ncu_test_loop']++; }
function the_ID(){ echo '123'; }
function post_class($class=''){ echo 'class="' . $class . '"'; }
function the_content(){ echo '<p>NATIVE PAGE CONTENT</p>'; }
function wp_link_pages(){ echo '<LINKS>'; }

ob_start();
include dirname(__DIR__) . '/page.php';
$out = ob_get_clean();
if (false === strpos($out, 'NATIVE PAGE CONTENT')) { fwrite(STDERR, "FAIL: native content missing\n"); exit(1); }
if (false === strpos($out, '<HEADER>') || false === strpos($out, '<FOOTER>')) { fwrite(STDERR, "FAIL: shell missing\n"); exit(1); }
echo "PASS: Page template renders with WordPress primitives only\n";
