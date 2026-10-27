<?php
// Round-trip test for the Open Theme records exchange (Nine_Backup_Records).
// Exports Excel, CSV, JSON and an Excel-style re-saved workbook, damages the records, imports
// each file back and checks every field, plus security and partial-file behaviour.
// Run against a test site with Open Theme active:
//   OT_TEST_DIR=$PWD/tests/open-theme php .wp-test/wp-cli.phar --path=.wp-test/wp --allow-root eval-file tests/open-theme/records-roundtrip.php
wp_set_current_user( 1 );
$GLOBALS["pass"] = 0; $GLOBALS["fail"] = 0;
function rt_ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS $msg\n"; } else { $fail++; echo "FAIL $msg\n"; } }

// ---------- fixtures
$tricky_title = "O'Brien \"quoted\" \\ back\\slash — ünïcødé";
$tricky_content = "<!-- wp:paragraph -->\n<p>Line one &amp; <strong>bold</strong></p>\n<!-- /wp:paragraph -->\n\nLine \"two\" with a \\ backslash";
$parent_term = wp_insert_term( 'RT Parent', 'category', array( 'slug' => 'rt-parent' ) );
$child_term = wp_insert_term( 'RT Child', 'category', array( 'slug' => 'rt-child', 'parent' => $parent_term['term_id'], 'description' => 'Child "desc"' ) );
add_term_meta( $child_term['term_id'], 'rt_colour', 'green' );
$post_id = wp_insert_post( wp_slash( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $tricky_title, 'post_name' => 'rt-post', 'post_content' => $tricky_content, 'post_excerpt' => 'Excerpt é', 'post_date' => '2024-03-05 10:11:12' ) ) );
wp_set_object_terms( $post_id, array( (int) $child_term['term_id'] ), 'category' );
$meta = array(
    '_os_phone' => '+2348012345678',
    'rt_zero' => '007',
    'rt_version' => '2.10',
    'rt_formula' => '=SUM(A1:A3)',
    'rt_array' => array( 'a' => 1, 'b' => array( 'x', "y'z" ) ),
    'rt_quote' => "It's \"fine\" \\ ok",
    'rt_negative' => '-5',
);
foreach ( $meta as $k => $v ) { update_post_meta( $post_id, $k, wp_slash( $v ) ); }
add_post_meta( $post_id, 'rt_multi', 'one' );
add_post_meta( $post_id, 'rt_multi', 'two' );
$page_parent = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'RT Parent Page', 'post_name' => 'rt-parent-page' ) );
$page_child = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'RT Child Page', 'post_name' => 'rt-child-page', 'post_parent' => $page_parent, 'menu_order' => 3 ) );
$user_id = wp_insert_user( array( 'user_login' => 'rtscholar', 'user_email' => 'rt@example.org', 'user_pass' => 'Secret-Pass-123', 'first_name' => "O'Brien", 'last_name' => 'Ádébáyọ̀', 'description' => "Bio with \\ slash and \"quotes\"", 'role' => 'editor', 'user_url' => 'https://example.org/rt' ) );
update_user_meta( $user_id, '_os_affiliation', wp_slash( "Dept. of \"Physics\"" ) );
update_user_meta( $user_id, '_os_orcid', '0000-0002-1825-0097' );
update_user_meta( $user_id, 'rt_api_key', 'should-never-export' );
$hash_before = get_userdata( $user_id )->user_pass;
$c1 = wp_insert_comment( wp_slash( array( 'comment_post_ID' => $post_id, 'comment_author' => "Reader 'One'", 'comment_author_email' => 'r1@example.org', 'comment_content' => 'First "comment"', 'comment_date' => '2024-03-06 09:00:00', 'comment_approved' => 1 ) ) );
$c2 = wp_insert_comment( wp_slash( array( 'comment_post_ID' => $post_id, 'comment_author' => 'Reader Two', 'comment_author_email' => 'r2@example.org', 'comment_content' => 'Reply', 'comment_date' => '2024-03-06 10:00:00', 'comment_parent' => $c1, 'comment_approved' => 0 ) ) );

function rt_snapshot( $post_id, $user_id ) {
    $p = get_post( $post_id );
    $u = get_userdata( $user_id );
    $terms = wp_get_object_terms( $post_id, 'category', array( 'fields' => 'slugs' ) );
    sort( $terms );
    $child = get_term_by( 'slug', 'rt-child', 'category' );
    $parent = $child ? get_term( $child->parent, 'category' ) : null;
    $pc = get_page_by_path( 'rt-parent-page/rt-child-page', OBJECT, 'page' );
    $comments = get_comments( array( 'post_id' => $post_id, 'status' => 'all', 'orderby' => 'comment_date', 'order' => 'ASC' ) );
    $mm = get_post_meta( $post_id, 'rt_multi' ); sort( $mm );
    return array(
        'title' => $p->post_title, 'content' => $p->post_content, 'excerpt' => $p->post_excerpt, 'slug' => $p->post_name, 'status' => $p->post_status, 'date' => $p->post_date,
        'terms' => $terms,
        'm_phone' => get_post_meta( $post_id, '_os_phone', true ), 'm_zero' => get_post_meta( $post_id, 'rt_zero', true ), 'm_version' => get_post_meta( $post_id, 'rt_version', true ),
        'm_formula' => get_post_meta( $post_id, 'rt_formula', true ), 'm_array' => get_post_meta( $post_id, 'rt_array', true ), 'm_quote' => get_post_meta( $post_id, 'rt_quote', true ),
        'm_negative' => get_post_meta( $post_id, 'rt_negative', true ), 'm_multi' => $mm,
        'term_parent' => $parent && ! is_wp_error( $parent ) ? $parent->slug : '', 'term_desc' => $child ? $child->description : '', 'term_meta' => $child ? get_term_meta( $child->term_id, 'rt_colour', true ) : '',
        'page_child_parent_ok' => $pc ? 1 : 0, 'page_child_order' => $pc ? $pc->menu_order : -1, 'page_child_status' => $pc ? $pc->post_status : '',
        'u_first' => $u->first_name, 'u_last' => $u->last_name, 'u_desc' => $u->description, 'u_url' => $u->user_url, 'u_roles' => implode( ',', $u->roles ),
        'u_aff' => get_user_meta( $user_id, '_os_affiliation', true ), 'u_orcid' => get_user_meta( $user_id, '_os_orcid', true ),
        'comments' => array_map( function ( $c ) { return array( $c->comment_author, $c->comment_content, $c->comment_approved, $c->comment_parent ? get_comment( $c->comment_parent )->comment_author : '' ); }, $comments ),
    );
}
$original = rt_snapshot( $post_id, $user_id );
rt_ok( $original['m_multi'] === array( 'one', 'two' ) && $original['term_parent'] === 'rt-parent', 'fixtures in place' );

function rt_damage( $post_id, $user_id ) {
    wp_update_post( array( 'ID' => $post_id, 'post_title' => 'DAMAGED', 'post_content' => 'DAMAGED', 'post_excerpt' => '' ) );
    wp_set_object_terms( $post_id, array(), 'category' );
    foreach ( array( '_os_phone', 'rt_zero', 'rt_version', 'rt_formula', 'rt_array', 'rt_quote', 'rt_negative' ) as $k ) { update_post_meta( $post_id, $k, 'DAMAGED' ); }
    delete_post_meta( $post_id, 'rt_multi' );
    $child = get_term_by( 'slug', 'rt-child', 'category' );
    wp_update_term( $child->term_id, 'category', array( 'parent' => 0, 'description' => 'DAMAGED' ) );
    update_term_meta( $child->term_id, 'rt_colour', 'DAMAGED' );
    $pc = get_posts( array( 'name' => 'rt-child-page', 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => 1 ) );
    wp_update_post( array( 'ID' => $pc[0]->ID, 'post_parent' => 0, 'menu_order' => 0 ) );
    wp_update_user( array( 'ID' => $user_id, 'first_name' => 'DAMAGED', 'description' => 'DAMAGED' ) );
    update_user_meta( $user_id, '_os_affiliation', 'DAMAGED' );
    foreach ( get_comments( array( 'post_id' => $post_id, 'status' => 'all' ) ) as $c ) { wp_update_comment( array( 'comment_ID' => $c->comment_ID, 'comment_content' => 'DAMAGED' ) ); }
}

$types = array( 'post:post', 'post:page', 'users', 'terms', 'comments' );
$counts_before = array( 'posts' => (int) wp_count_posts( 'post' )->publish, 'users' => count_users()['total_users'], 'terms' => (int) wp_count_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) ), 'comments' => (int) wp_count_comments( $post_id )->total_comments );

foreach ( array_merge( array( 'xlsx', 'csv', 'json' ), getenv( 'OT_TEST_DIR' ) ? array( 'resaved' ) : array() ) as $format ) {
    echo "\n== $format\n";
    $file = Nine_Backup_Records::write( Nine_Backup_Records::collect( $types ), 'resaved' === $format ? 'xlsx' : $format );
    if ( 'resaved' === $format ) {
        // Rewrite the workbook as Excel saves it (shared strings, rich text, absolute paths).
        $converted = sys_get_temp_dir() . '/rt-resaved-' . wp_generate_password( 6, false, false ) . '.xlsx';
        shell_exec( 'python3 -I ' . escapeshellarg( rtrim( (string) getenv( 'OT_TEST_DIR' ), '/' ) . '/excelify.py' ) . ' ' . escapeshellarg( $file['path'] ) . ' ' . escapeshellarg( $converted ) );
        $zip = new ZipArchive(); $zip->open( $converted ); $has_shared = false !== $zip->locateName( 'xl/sharedStrings.xml' ); $zip->close();
        rt_ok( is_file( $converted ) && $has_shared, 'workbook rewritten with shared strings, as Excel saves it' );
        @unlink( $file['path'] );
        $file = array( 'path' => $converted, 'filename' => basename( $converted ) );
    }
    rt_ok( ! is_wp_error( $file ) && is_file( $file['path'] ), "$format written (" . ( is_wp_error( $file ) ? $file->get_error_message() : size_format( filesize( $file['path'] ) ) ) . ')' );
    $raw = 'json' === $format || 'csv' === $format ? '' : '';
    $groups = Nine_Backup_Records::parse( $file['path'], $file['filename'] );
    rt_ok( is_array( $groups ) && ! empty( $groups['users'] ), "$format parsed: " . ( is_array( $groups ) ? implode( ',', array_map( function ( $k, $v ) { return $k . '=' . count( $v ); }, array_keys( $groups ), $groups ) ) : 'error' ) );
    $flat = wp_json_encode( $groups );
    rt_ok( false === strpos( $flat, 'should-never-export' ) && false === strpos( $flat, 'Secret-Pass' ) && false === strpos( $flat, $hash_before ) && false === strpos( $flat, 'capabilities' ), "$format has no passwords, hashes, api keys or capabilities" );
    rt_damage( $post_id, $user_id );
    $preview = Nine_Backup_Records::import( $groups, 'both', true );
    rt_ok( 'DAMAGED' === get_post( $post_id )->post_title, "$format preview changed nothing" );
    $result = Nine_Backup_Records::import( $groups, 'both', false );
    $errors = 0; foreach ( $result['report'] as $r ) { $errors += $r['errors']; }
    rt_ok( 0 === $errors, "$format import without errors " . wp_json_encode( $result['report'] ) . ( $result['messages'] ? ' ' . implode( ' | ', $result['messages'] ) : '' ) );
    clean_post_cache( $post_id ); clean_user_cache( $user_id ); wp_cache_flush();
    $after = rt_snapshot( $post_id, $user_id );
    foreach ( $original as $key => $value ) {
        rt_ok( $after[ $key ] === $value, "$format $key restored" . ( $after[ $key ] === $value ? '' : ': got ' . wp_json_encode( $after[ $key ] ) . ' want ' . wp_json_encode( $value ) ) );
    }
    rt_ok( get_userdata( $user_id )->user_pass === $hash_before, "$format user password untouched" );
    $now = array( 'posts' => (int) wp_count_posts( 'post' )->publish, 'users' => count_users()['total_users'], 'terms' => (int) wp_count_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) ), 'comments' => (int) get_comments( array( 'post_id' => $post_id, 'status' => 'all', 'count' => true ) ) );
    rt_ok( $now === $counts_before, "$format created no duplicates " . wp_json_encode( $now ) );
    @unlink( $file['path'] );
}

// ---------- create path: records deleted, then imported back
echo "\n== recreate\n";
$file = Nine_Backup_Records::write( Nine_Backup_Records::collect( $types ), 'xlsx' );
$groups = Nine_Backup_Records::parse( $file['path'], $file['filename'] );
wp_delete_post( $post_id, true );
$result = Nine_Backup_Records::import( $groups, 'new', false );
$new = get_posts( array( 'name' => 'rt-post', 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => 1 ) );
rt_ok( (bool) $new, 'deleted post recreated (only-new mode) ' . wp_json_encode( $result['report'] ) );
if ( $new ) {
    $again = rt_snapshot( $new[0]->ID, $user_id );
    foreach ( array( 'title', 'content', 'terms', 'm_phone', 'm_array', 'm_multi', 'm_formula', 'date' ) as $key ) { rt_ok( $again[ $key ] === $original[ $key ], "recreated $key" . ( $again[ $key ] === $original[ $key ] ? '' : ': got ' . wp_json_encode( $again[ $key ] ) ) ); }
    rt_ok( 2 === count( $again['comments'] ) && $again['comments'][1][3] === "Reader 'One'", 'comments recreated with reply threading ' . wp_json_encode( $again['comments'] ) );
    $post_id = $new[0]->ID;
}
@unlink( $file['path'] );

// ---------- security: a crafted row cannot grant capabilities or set secrets
echo "\n== security\n";
$evil = array( 'users' => array( array( 'record_type' => 'user', 'login' => 'rtscholar', 'email' => 'rt@example.org', 'meta:wp_capabilities' => '{"administrator":true}', 'meta:wp_user_level' => '10', 'meta:session_tokens' => '[]', 'meta:rt_api_key' => 'x', 'roles' => 'editor' ) ) );
Nine_Backup_Records::import( $evil, 'both', false );
clean_user_cache( $user_id );
rt_ok( ! user_can( $user_id, 'manage_options' ) && 'should-never-export' === get_user_meta( $user_id, 'rt_api_key', true ), 'capabilities/secret meta in a file are ignored' );
$self = array( 'users' => array( array( 'record_type' => 'user', 'login' => get_userdata( 1 )->user_login, 'roles' => 'subscriber' ) ) );
Nine_Backup_Records::import( $self, 'both', false );
rt_ok( user_can( 1, 'manage_options' ), 'the importing admin cannot demote themselves' );
$bad = Nine_Backup_Records::import( array( 'x' => array( array( 'record_type' => 'post', 'post_type' => 'not_a_type', 'title' => 'x' ), array( 'record_type' => 'user', 'login' => 'nomail' ) ) ), 'both', false );
rt_ok( 1 === $bad['report']['post']['errors'] && 1 === $bad['report']['user']['errors'], 'bad rows are reported, not fatal: ' . implode( ' | ', $bad['messages'] ) );

// ---------- partial spreadsheet: only id/slug/title columns
$partial = array( 'post' => array( array( 'record_type' => 'post', 'post_type' => 'post', 'slug' => 'rt-post', 'title' => 'Renamed only' ) ) );
Nine_Backup_Records::import( $partial, 'update', false );
clean_post_cache( $post_id );
rt_ok( 'Renamed only' === get_post( $post_id )->post_title && get_post( $post_id )->post_content === $tricky_content && '+2348012345678' === get_post_meta( $post_id, '_os_phone', true ), 'a partial file changes only its columns' );

// ---------- cleanup
wp_delete_post( $post_id, true ); wp_delete_post( $page_child, true ); wp_delete_post( $page_parent, true );
wp_delete_term( $child_term['term_id'], 'category' ); wp_delete_term( $parent_term['term_id'], 'category' );
require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $user_id );
echo "\nrecords round trip: " . $GLOBALS["pass"] . " passed, " . $GLOBALS["fail"] . " failed\n";
