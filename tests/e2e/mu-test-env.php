<?php
// Test environment only: silence wp.org update checks (no network) so debug.log only holds real problems.
add_filter( 'pre_site_transient_update_core', '__return_null' );
add_filter( 'pre_site_transient_update_plugins', '__return_null' );
add_filter( 'pre_site_transient_update_themes', '__return_null' );
remove_action( 'admin_init', '_maybe_update_core' );
remove_action( 'admin_init', '_maybe_update_plugins' );
remove_action( 'admin_init', '_maybe_update_themes' );
remove_action( 'wp_version_check', 'wp_version_check' );
add_filter( 'translations_api', '__return_false' );
// Lets the editor test open the Classic editor: /wp-admin/post.php?post=N&action=edit&nc_classic=1
if ( isset( $_GET['nc_classic'] ) ) { add_filter( 'use_block_editor_for_post', '__return_false' ); }
