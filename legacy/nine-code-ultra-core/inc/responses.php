<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 9Core Responses hub.
 *
 * The theme does not invent a new submissions database. It discovers response,
 * submission, entry, inbox, Q&A and message screens exposed by installed
 * plugins and gives the mobile administrator one quiet place to reach them.
 */
function ncu_core_response_destinations() {
    global $menu, $submenu;
    $needles = array( 'response', 'responses', 'submission', 'submissions', 'entry', 'entries', 'inbox', 'message', 'messages', 'question', 'questions', 'answer', 'answers', 'feedback' );
    $items = array();
    $collect = static function( $label, $slug, $parent = '' ) use ( &$items, $needles ) {
        $label_text = trim( wp_strip_all_tags( (string) $label ) );
        $slug_text  = (string) $slug;
        $haystack   = strtolower( $label_text . ' ' . $slug_text );
        if ( ! $label_text || ! $slug_text || 'nine-code-ultra-responses' === $slug_text ) { return; }
        $match = false;
        foreach ( $needles as $needle ) { if ( false !== strpos( $haystack, $needle ) ) { $match = true; break; } }
        if ( ! $match ) { return; }
        $key = md5( $parent . '|' . $slug_text );
        if ( isset( $items[ $key ] ) ) { return; }
        if ( false !== strpos( $slug_text, '.php' ) ) {
            $url = admin_url( ltrim( $slug_text, '/' ) );
        } else {
            $url = admin_url( 'admin.php?page=' . rawurlencode( $slug_text ) );
        }
        $items[ $key ] = array( 'label' => $label_text, 'slug' => $slug_text, 'url' => $url, 'parent' => $parent );
    };

    foreach ( (array) $menu as $row ) {
        if ( isset( $row[0], $row[2] ) ) { $collect( $row[0], $row[2], '' ); }
    }
    foreach ( (array) $submenu as $parent => $rows ) {
        foreach ( (array) $rows as $row ) {
            if ( isset( $row[0], $row[2] ) ) { $collect( $row[0], $row[2], (string) $parent ); }
        }
    }
    $items = array_values( apply_filters( 'ncu_core_response_destinations', $items ) );
    usort( $items, static function( $a, $b ) { return strcasecmp( $a['label'], $b['label'] ); } );
    return $items;
}

function ncu_core_responses_page() {
    if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) { return; }
    $items = ncu_core_response_destinations();
    ?>
    <div class="wrap ncu-admin ncu-responses-hub">
        <?php if ( function_exists( 'ncu_admin_header' ) ) { ncu_admin_header( 'Responses', 'One place for responses, submissions, entries, inboxes, questions and answers created by the plugins installed on this site.' ); } else { echo '<h1>Responses</h1>'; } ?>
        <?php if ( $items ) : ?>
            <div class="ncu-dashboard-grid">
                <?php foreach ( $items as $item ) : ?>
                    <a class="ncu-dashboard-card" href="<?php echo esc_url( $item['url'] ); ?>"><span class="dashicons dashicons-email-alt"></span><h2><?php echo esc_html( $item['label'] ); ?></h2><p><?php echo esc_html( $item['parent'] ? 'Response screen from ' . $item['parent'] : 'Response screen' ); ?></p><strong>Open →</strong></a>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <section class="ncu-panel ncu-panel--padded"><h2>No response screens detected</h2><p>When a compatible 9 plugin or another installed plugin exposes Responses, Submissions, Entries, Inbox, Questions or Messages, it will appear here automatically. No duplicate response database is created.</p></section>
        <?php endif; ?>
    </div>
    <?php
}
