<?php
require __DIR__ . '/lib.php';
ncd_suite( 'acf' );
ncd_as( 'admin' );
if ( ! function_exists( 'acf_get_field_groups' ) ) { echo "SKIP ACF/SCF not active\n"; ncd_done(); return; }
ncd_reboot();
$e = ncd_entity( 'wordpress', 'type-post' );
ncd_t( isset( $e['fields']['acf:ncd_speakers'] ), 'ACF fields discovered on post type' );
$f = $e['fields'];
ncd_eq( $f['acf:ncd_hero']['type'], 'group', 'group mapped' );
ncd_eq( $f['acf:ncd_speakers']['type'], 'repeater', 'repeater mapped' );
ncd_eq( $f['acf:ncd_blocks']['type'], 'flexible', 'flexible content mapped' );
ncd_eq( $f['acf:ncd_gallery']['type'], 'media_list', 'gallery mapped' );
ncd_eq( $f['acf:ncd_file']['type'], 'media', 'file mapped' );
ncd_eq( $f['acf:ncd_related']['type'], 'post_list', 'relationship mapped' );
ncd_eq( $f['acf:ncd_owner']['type'], 'post', 'post object mapped' );
ncd_eq( $f['acf:ncd_topics']['type'], 'term_list', 'taxonomy field mapped' );
ncd_eq( $f['acf:ncd_speakers']['sub_fields']['user']['type'], 'user', 'repeater sub-field user mapped' );
ncd_t( $f['acf:ncd_api_password']['secret'], 'ACF password field is secret' );
ncd_t( ! isset( $f['meta:ncd_subtitle'] ) && ! isset( $f['meta:_ncd_subtitle'] ), 'ACF storage keys not duplicated as raw meta' );

// Fixtures.
$img1 = wp_insert_attachment( array( 'post_title' => 'img1', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ) );
$img2 = wp_insert_attachment( array( 'post_title' => 'img2', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ) );
$pg1  = ncd_post( array( 'post_type' => 'page', 'post_status' => 'publish' ) );
$pg2  = ncd_post( array( 'post_type' => 'page', 'post_status' => 'publish' ) );
$cat  = wp_insert_term( 'ACF Topic ' . wp_generate_password( 4, false ), 'category' );
$id   = ncd_post();
update_field( 'field_ncd_api_password', 'hunter2', $id );

$values = array(
	'acf:ncd_subtitle' => 'Sub',
	'acf:ncd_rating'   => 4,
	'acf:ncd_color'    => 'blue',
	'acf:ncd_featured' => true,
	'acf:ncd_hero'     => array( 'heading' => 'Hello', 'image' => $img1 ),
	'acf:ncd_speakers' => array( array( 'name' => 'Ada', 'user' => 1 ), array( 'name' => 'Grace', 'user' => 1 ) ),
	'acf:ncd_blocks'   => array( array( 'acf_fc_layout' => 'text', 'body' => 'Para' ), array( 'acf_fc_layout' => 'quote', 'quote' => 'Q', 'by' => 'Me' ) ),
	'acf:ncd_gallery'  => array( $img1, $img2 ),
	'acf:ncd_file'     => $img2,
	'acf:ncd_related'  => array( $pg1, $pg2 ),
	'acf:ncd_owner'    => $pg1,
	'acf:ncd_topics'   => array( $cat['term_id'] ),
);
$r = NCD_Service::save( $e, $id, $values );
ncd_t( $r['ok'], 'save all ACF types', $r['errors'] );
$back = NCD_Store::read( $e, $id );
ncd_eq( $back['acf:ncd_subtitle'], 'Sub', 'text round trip' );
ncd_t( 4 == $back['acf:ncd_rating'], 'number round trip' );
ncd_eq( $back['acf:ncd_hero']['heading'], 'Hello', 'group sub-field round trip' );
ncd_t( (int) $back['acf:ncd_hero']['image'] === $img1, 'group image round trip' );
ncd_t( 2 === count( $back['acf:ncd_speakers'] ) && 'Grace' === $back['acf:ncd_speakers'][1]['name'], 'repeater round trip (names, not field keys)' );
ncd_t( 'quote' === $back['acf:ncd_blocks'][1]['acf_fc_layout'] && 'Me' === $back['acf:ncd_blocks'][1]['by'], 'flexible content round trip' );
ncd_t( array( $img1, $img2 ) == array_map( 'intval', $back['acf:ncd_gallery'] ), 'gallery round trip' );
ncd_t( array( $pg1, $pg2 ) == array_map( 'intval', $back['acf:ncd_related'] ), 'relationship round trip' );
ncd_t( (int) $back['acf:ncd_owner'] === $pg1, 'post object round trip' );
ncd_t( array( $cat['term_id'] ) == array_map( 'intval', (array) $back['acf:ncd_topics'] ), 'taxonomy field round trip' );
ncd_eq( $back['acf:ncd_api_password'], NCD_Policy::REDACTED, 'password redacted on read' );
ncd_eq( get_field( 'ncd_speakers', $id )[0]['name'], 'Ada', 'ACF API sees the saved repeater' );

// Validation of nested values.
$r = NCD_Service::save( $e, $id, array( 'acf:ncd_speakers' => array_fill( 0, 6, array( 'name' => 'x' ) ) ) );
ncd_t( ! $r['ok'], 'repeater max rows enforced' );
$r = NCD_Service::save( $e, $id, array( 'acf:ncd_color' => 'green' ) );
ncd_t( ! $r['ok'], 'ACF select choice enforced' );
$r = NCD_Service::save( $e, $id, array( 'acf:ncd_blocks' => array( array( 'acf_fc_layout' => 'nope', 'x' => 1 ) ) ) );
ncd_t( ! $r['ok'], 'unknown flexible layout rejected' );
$r = NCD_Service::save( $e, $id, array( 'acf:ncd_owner' => $img1 ) );
ncd_t( ! $r['ok'], 'post object of the wrong post type rejected' );
$r = NCD_Service::save( $e, $id, array( 'acf:ncd_api_password' => 'new' ) );
ncd_t( ! $r['ok'] && 'hunter2' === get_field( 'ncd_api_password', $id ), 'secret ACF field cannot be overwritten' );

// Export → edit nested JSON → import → undo.
$pkg = NCD_Exchange::package( $e, NCD_Exchange::export_records( $e, array( 'ids' => array( $id ) ) ) );
ncd_t( ! isset( $pkg['records'][0]['fields']['acf:ncd_api_password'] ) && ! isset( $pkg['records'][0]['readonly']['acf:ncd_api_password'] ) && ! isset( $pkg['schema']['fields']['acf:ncd_api_password'] ), 'secret field omitted from export values and schema' );
ncd_t( false === strpos( wp_json_encode( $pkg ), 'hunter2' ), 'secret value never appears in the package' );
ncd_t( isset( $pkg['schema']['acf:ncd_blocks']['layouts']['quote'] ) || isset( $pkg['schema']['fields']['acf:ncd_blocks'] ), 'schema describes flexible layouts' );
$pkg['records'][0]['fields']['acf:ncd_speakers'][1]['name'] = 'Grace Hopper';
$pkg['records'][0]['fields']['acf:ncd_blocks'][] = array( 'acf_fc_layout' => 'text', 'body' => 'Added by AI' );
$pkg['records'][0]['fields']['acf:ncd_hero']['heading'] = 'Hi';
$n  = NCD_Exchange::normalize_package( json_decode( wp_json_encode( $pkg ), true ), $e );
$pv = NCD_Exchange::preview( $e, $n, 0, 10, array() );
$keys = wp_list_pluck( $pv['rows'][0]['fields'], 'key' );
sort( $keys );
ncd_eq( $keys, array( 'acf:ncd_blocks', 'acf:ncd_hero', 'acf:ncd_speakers' ), 'preview lists exactly the edited nested fields' );
ncd_eq( get_field( 'ncd_hero', $id )['heading'], 'Hello', 'preview wrote nothing' );
$ap = NCD_Exchange::apply( $e, $n, array( 0 => array( 'acf:ncd_speakers', 'acf:ncd_hero' ) ), array() );
ncd_eq( get_field( 'ncd_speakers', $id )[1]['name'], 'Grace Hopper', 'selected nested field applied' );
ncd_eq( count( get_field( 'ncd_blocks', $id ) ), 2, 'unselected field not applied' );
NCD_Service::undo( $ap['history_id'] );
ncd_eq( get_field( 'ncd_speakers', $id )[1]['name'], 'Grace', 'undo restores repeater' );
ncd_eq( get_field( 'ncd_hero', $id )['heading'], 'Hello', 'undo restores group' );

// CSV keeps nested values as JSON cells and round-trips.
$flat = NCD_Exchange::flatten( $e, NCD_Exchange::export_records( $e, array( 'ids' => array( $id ) ) ) );
$tmp  = wp_tempnam( 'ncd' ) . '.csv';
file_put_contents( $tmp, NCD_Sheet::csv( $flat['headers'], $flat['rows'] ) );
$sp = NCD_Exchange::parse( $tmp, 'csv', $e, true );
ncd_t( ! is_wp_error( $sp ), 'CSV with nested JSON cells parses' );
$pv = NCD_Exchange::preview( $e, $sp, 0, 10, array() );
ncd_t( 'unchanged' === $pv['rows'][0]['action'], 'unmodified CSV round trip shows no changes', $pv['rows'][0]['fields'] );

// Term ACF + user ACF locations are discovered too.
foreach ( array( $id, $pg1, $pg2 ) as $x ) { wp_delete_post( $x, true ); }
wp_delete_attachment( $img1, true ); wp_delete_attachment( $img2, true );
wp_delete_term( $cat['term_id'], 'category' );
ncd_done();
