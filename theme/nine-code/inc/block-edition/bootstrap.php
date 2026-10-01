<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/class-quick-actions.php';
N9BE_Quick_Actions::boot();

/**
 * Edition 9.10 synchronized suite boundary:
 * - Theme owns Quick Actions + public presentation.
 * - Core owns shared infrastructure.
 * - 9 Data Manager owns editorial/data operations.
 * - Mason and 9Page are discontinued from this suite and have no active
 *   compatibility branches in the Theme runtime.
 */
function ninecode_block_edition_quick_actions( $catalog ) {
    return apply_filters( 'ninecode_block_edition_quick_action_catalog', $catalog );
}

do_action( 'ninecode_theme_block_shell_loaded' );
