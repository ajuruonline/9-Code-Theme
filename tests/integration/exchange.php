<?php
require __DIR__ . '/lib.php';
ncd_suite( 'exchange' );
ncd_as( 'admin' );
ncd_reboot();
$e = ncd_entity( 'wordpress', 'type-post' );
function ncd_tmpfile( $ext, $content ) { $f = wp_tempnam( 'ncd' ) . '.' . $ext; file_put_contents( $f, $content ); return $f; }

$a = ncd_post( array( 'post_title' => 'Alpha', 'post_excerpt' => '=HYPERLINK("http://evil")' ) );
$b = ncd_post( array( 'post_title' => 'Beta', 'post_status' => 'publish' ) );
$recs = NCD_Exchange::export_records( $e, array( 'ids' => array( $a, $b ) ) );
ncd_eq( count( $recs ), 2, 'export selected ids' );
$pkg = NCD_Exchange::package( $e, $recs );
ncd_t( 'ninecode-data-package' === $pkg['format'] && 2 === $pkg['package_version'] && ! empty( $pkg['instructions'] ) && ! empty( $pkg['schema']['fields'] ), 'AI package carries format, schema and instructions' );
ncd_t( isset( $pkg['records'][0]['_ref']['id'], $pkg['records'][0]['_ref']['revision'], $pkg['records'][0]['_ref']['base'] ), 'records carry stable _ref identifiers' );
ncd_t( ! isset( $pkg['records'][0]['fields']['meta:_edit_lock'] ), 'protected fields not offered as editable' );
$filtered = NCD_Exchange::export_records( $e, array( 'search' => 'Beta', 'status' => 'publish' ) );
ncd_t( in_array( $b, wp_list_pluck( wp_list_pluck( $filtered, '_ref' ), 'id' ), true ) && ! in_array( $a, wp_list_pluck( wp_list_pluck( $filtered, '_ref' ), 'id' ), true ), 'filtered export respects search/status' );

// CSV formula injection guard and lossless round trip.
$flat = NCD_Exchange::flatten( $e, $recs );
$csv  = NCD_Sheet::csv( $flat['headers'], $flat['rows'] );
ncd_t( false !== strpos( $csv, "'=HYPERLINK" ), 'CSV guards formula-looking cells' );
$sp = NCD_Exchange::parse( ncd_tmpfile( 'csv', $csv ), 'csv', $e, true );
$pv = NCD_Exchange::preview( $e, $sp, 0, 10, array() );
ncd_t( 'unchanged' === $pv['rows'][0]['action'] && 'unchanged' === $pv['rows'][1]['action'], 'CSV round trip is lossless (guard removed on import)', $pv['rows'] );
$x = NCD_Sheet::xlsx( $flat['headers'], $flat['rows'], $flat['guide'] );
ncd_t( ! is_wp_error( $x ) && 'PK' === substr( $x, 0, 2 ), 'XLSX generated' );
$xp = NCD_Exchange::parse( ncd_tmpfile( 'xlsx', $x ), 'xlsx', $e, true );
$pv = NCD_Exchange::preview( $e, $xp, 0, 10, array() );
ncd_t( ! is_wp_error( $xp ) && 'unchanged' === $pv['rows'][0]['action'], 'XLSX round trip is lossless' );
$zip = new ZipArchive(); $zf = ncd_tmpfile( 'xlsx', $x ); $zip->open( $zf );
ncd_t( false !== $zip->locateName( 'xl/worksheets/sheet2.xml' ), 'XLSX includes a Guide sheet' ); $zip->close();
// Semicolon CSV (European Excel) is sniffed.
$semi = "\xEF\xBB\xBF_id;_action;_revision;_label;post_title\n{$a};;;Alpha;Alpha semi\n";
$sp = NCD_Exchange::parse( ncd_tmpfile( 'csv', $semi ), 'csv', $e, true );
ncd_eq( $sp['records'][0]['fields']['post_title'] ?? null, 'Alpha semi', 'semicolon CSV parsed' );

// Invalid packages.
$bad = array(
	'not json'         => array( 'json', '{oops', false ),
	'wrong format'     => array( 'json', wp_json_encode( array( 'format' => 'something', 'records' => array() ) ), false ),
	'wrong entity'     => array( 'json', wp_json_encode( array( 'format' => 'ninecode-data-package', 'provider' => 'wordpress', 'entity' => 'type-page', 'records' => array() ) ), false ),
	'no records'       => array( 'json', wp_json_encode( array( 'format' => 'ninecode-data-package' ) ), false ),
	'record no fields' => array( 'json', wp_json_encode( array( 'format' => 'ninecode-data-package', 'records' => array( array( '_ref' => array( 'id' => $a ) ) ) ) ), false ),
	'csv without _id'  => array( 'csv', "title\nx\n", true ),
	'corrupt xlsx'     => array( 'xlsx', 'PK not really a zip', true ),
	'php upload'       => array( 'php', '<?php echo 1;', true ),
	'html upload'      => array( 'html', '<script>1</script>', true ),
);
foreach ( $bad as $label => $c ) {
	$r = $c[2] ? NCD_Exchange::parse( ncd_tmpfile( $c[0], $c[1] ), $c[0], $e, true ) : NCD_Exchange::parse( $c[1], $c[0], $e, false );
	ncd_t( is_wp_error( $r ), "invalid package rejected: {$label}", is_wp_error( $r ) ? null : 'accepted' );
}
$many = array( 'format' => 'ninecode-data-package', 'records' => array_fill( 0, NCD_Exchange::MAX_RECORDS + 1, array( 'fields' => array() ) ) );
ncd_t( is_wp_error( NCD_Exchange::normalize_package( $many, $e ) ), 'oversized package rejected' );
$big = "_id,post_title\n" . str_repeat( "1,x\n", NCD_Sheet::MAX_ROWS + 2 );
ncd_t( is_wp_error( NCD_Exchange::parse( ncd_tmpfile( 'csv', $big ), 'csv', $e, true ) ), 'oversized sheet rejected (not silently truncated)' );

// Preview never writes; apply is selective; structural actions need permission.
$pkg = NCD_Exchange::package( $e, NCD_Exchange::export_records( $e, array( 'ids' => array( $a, $b ) ) ) );
$pkg['records'][0]['fields']['post_title']   = 'Alpha AI';
$pkg['records'][0]['fields']['post_excerpt'] = 'Short';
$pkg['records'][1]['fields']['post_status']  = 'draft'; // unpublish
$pkg['records'][] = array( '_action' => 'create', 'fields' => array( 'post_title' => 'Created by AI' ) );
$pkg['records'][] = array( '_ref' => array( 'id' => $a ), '_action' => 'trash', 'fields' => array() );
$pkg['records'][] = array( '_ref' => array( 'id' => 999999 ), 'fields' => array( 'post_title' => 'ghost' ) );
$pkg['records'][] = array( '_ref' => array( 'id' => $b ), 'fields' => array( 'meta:_edit_lock' => '1', 'meta:made_up' => 'x' ) );
$n  = NCD_Exchange::normalize_package( json_decode( wp_json_encode( $pkg ), true ), $e );
$st = NCD_Exchange::stage( $n );
$staged = NCD_Exchange::load_stage( $st['token'] );
ncd_t( ! is_wp_error( $staged ) && 6 === count( $staged['records'] ), 'package staged and reloaded' );
$path = wp_upload_dir()['basedir'] . '/ncd-staging/' . $st['token'] . '.php';
ncd_t( 0 === strpos( file_get_contents( $path ), '<?php exit; ?>' ), 'staged file has an execution guard' );
$before = array( get_post( $a )->post_title, get_post_status( $b ), wp_count_posts()->draft );
$pv = NCD_Exchange::preview( $e, $staged, 0, 50, array() );
ncd_eq( array( ncd_fresh( $a )->post_title, get_post_status( $b ), wp_count_posts()->draft ), $before, 'preview performed no writes' );
$rows = $pv['rows'];
ncd_eq( $rows[0]['changes'], 2, 'preview: 2 field changes' );
ncd_t( 'status' === $rows[1]['fields'][0]['status'], 'preview: unpublish flagged as a status change' );
ncd_t( 'create' === $rows[2]['action'] && false !== strpos( $rows[2]['blocked'], 'Allow creating' ), 'preview: create shown and gated' );
ncd_t( 'trash' === $rows[3]['action'] && false !== strpos( $rows[3]['blocked'], 'trash' ), 'preview: trash shown and gated' );
ncd_t( $rows[4]['errors'] && false !== strpos( $rows[4]['blocked'], 'does not exist' ), 'preview: unknown id reported' );
$st4 = wp_list_pluck( $rows[5]['fields'], 'status', 'key' );
ncd_t( 'protected' === $st4['meta:_edit_lock'] && 'unknown' === $st4['meta:made_up'], 'preview: protected and unknown fields reported' );

$ap = NCD_Exchange::apply( $e, $staged, array( 0 => array( 'post_title' ), 1 => true, 2 => true, 3 => true, 4 => true, 5 => true ), array() );
ncd_eq( ncd_fresh( $a )->post_title, 'Alpha AI', 'selected field applied' );
ncd_t( 'Short' !== ncd_fresh( $a )->post_excerpt, 'unselected field not applied' );
ncd_eq( get_post_status( $b ), 'publish', 'unpublish not applied without allow_status' );
ncd_t( ! get_posts( array( 'title' => 'Created by AI', 'post_type' => 'post', 'post_status' => 'any', 'fields' => 'ids' ) ), 'create not applied without allow_create' );
ncd_t( 'trash' !== get_post_status( $a ), 'trash not applied without allow_trash' );
ncd_eq( get_post_meta( $b, '_edit_lock', true ) === '1', false, 'protected key never written' );
$hist = NCD_History::get( $ap['history_id'] );
ncd_t( $hist && 'import' === $hist['operation'] && 1 === count( $hist['records'] ), 'one import history entry for applied records' );
$skipped = $ap['results'][0]['skipped'] ?? array();
ncd_t( isset( $skipped['post_excerpt'] ), 'apply reports skipped fields' );

// With explicit permissions, in a second batch appended to the same history entry.
$ap2 = NCD_Exchange::apply( $e, $staged, array( 1 => true, 2 => true, 3 => true ), array( 'allow_status' => true, 'allow_create' => true, 'allow_trash' => true, 'history_id' => $ap['history_id'] ) );
ncd_eq( get_post_status( $b ), 'draft', 'unpublish applied with allow_status' );
$created = get_posts( array( 'title' => 'Created by AI', 'post_type' => 'post', 'post_status' => 'any', 'fields' => 'ids' ) );
ncd_t( 1 === count( $created ) && 'draft' === get_post_status( $created[0] ), 'create applied as draft with allow_create' );
ncd_eq( get_post_status( $a ), 'trash', 'trash applied with allow_trash' );
ncd_eq( $ap2['history_id'], $ap['history_id'], 'second batch appended to the same history entry' );
ncd_eq( count( NCD_History::get( $ap['history_id'] )['records'] ), 4, 'history holds all batches' );

// One undo reverts the whole import.
$u = NCD_Service::undo( $ap['history_id'] );
ncd_t( ! is_wp_error( $u ), 'import undo ran', is_wp_error( $u ) ? $u->get_error_message() : $u );
ncd_eq( get_post_status( $a ), 'draft', 'undo restored trashed record' );
ncd_eq( ncd_fresh( $a )->post_title, 'Alpha', 'undo restored edited field' );
ncd_eq( get_post_status( $b ), 'publish', 'undo re-published' );
ncd_eq( get_post_status( $created[0] ), 'trash', 'undo trashed created record' );

// Conflict detection: field changed on site after export.
$pkg = NCD_Exchange::package( $e, NCD_Exchange::export_records( $e, array( 'ids' => array( $a ) ) ) );
wp_update_post( array( 'ID' => $a, 'post_title' => 'Edited on site' ) );
$pkg['records'][0]['fields']['post_title'] = 'From AI';
$n  = NCD_Exchange::normalize_package( $pkg, $e );
$pv = NCD_Exchange::preview( $e, $n, 0, 5, array() );
ncd_eq( $pv['rows'][0]['fields'][0]['status'], 'conflict', 'conflict detected via base hash' );
NCD_Exchange::apply( $e, $n, array( 0 => true ), array() );
ncd_eq( ncd_fresh( $a )->post_title, 'Edited on site', 'conflict not overwritten by default selection' );
NCD_Exchange::apply( $e, $n, array( 0 => array( 'post_title' ) ), array() );
ncd_eq( ncd_fresh( $a )->post_title, 'From AI', 'conflict overwritten when explicitly ticked' );

// Stage ownership.
$st = NCD_Exchange::stage( $n );
$ed = ncd_user( 'ncd_editor', 'editor' );
ncd_as( $ed->ID );
ncd_t( is_wp_error( NCD_Exchange::load_stage( $st['token'] ) ), 'another user cannot load a staged import' );
ncd_as( 'admin' );
NCD_Exchange::discard_stage( $st['token'] );
ncd_t( is_wp_error( NCD_Exchange::load_stage( $st['token'] ) ), 'discarded stage is gone' );

// Draft (11.0 "universal") package back-compat.
$draft = array( 'format' => 'ninecode-universal-data-package', 'records' => array( array( 'id' => $a, 'post_type' => 'post', 'title' => 'Draft format', 'meta' => array( '_edit_lock' => 'x' ) ) ) );
$n = NCD_Exchange::normalize_package( $draft, $e );
ncd_t( ! is_wp_error( $n ) && 'Draft format' === $n['records'][0]['fields']['post_title'], 'draft package upgraded' );
ncd_t( ! isset( $n['records'][0]['fields']['meta:_edit_lock'] ) || 'protected' === wp_list_pluck( NCD_Exchange::preview( $e, $n, 0, 1, array() )['rows'][0]['fields'], 'status', 'key' )['meta:_edit_lock'], 'draft package cannot write protected keys' );

// Large import in batches.
$t0 = microtime( true );
$ids = array();
for ( $i = 0; $i < 600; $i++ ) { $ids[] = ncd_post( array( 'post_title' => 'Bulk ' . $i ) ); }
$pkg = NCD_Exchange::package( $e, NCD_Exchange::export_records( $e, array( 'ids' => $ids ) ) );
ncd_eq( count( $pkg['records'] ), 600, 'export 600 records' );
foreach ( $pkg['records'] as &$r ) { $r['fields']['post_excerpt'] = 'batched'; }
unset( $r );
$n  = NCD_Exchange::normalize_package( $pkg, $e );
$st = NCD_Exchange::stage( $n );
$staged = NCD_Exchange::load_stage( $st['token'] );
$hid = 0; $applied = 0;
for ( $off = 0; $off < 600; $off += 200 ) {
	$pv = NCD_Exchange::preview( $e, $staged, $off, 200, array() );
	$sel = array(); foreach ( $pv['rows'] as $row ) { $sel[ $row['index'] ] = true; }
	$ap = NCD_Exchange::apply( $e, $staged, $sel, array( 'history_id' => $hid ) );
	$hid = $ap['history_id']; $applied += $ap['applied'];
}
ncd_eq( $applied, 600, '600 records applied in 3 batches' );
ncd_eq( count( NCD_History::get( $hid )['records'] ), 600, 'large import is one undoable history entry' );
$secs = microtime( true ) - $t0;
ncd_t( $secs < 120, sprintf( 'large import finished in %.1fs', $secs ) );
NCD_Service::undo( $hid );
ncd_eq( ncd_fresh( $ids[599] )->post_excerpt, '', 'large import undone' );
NCD_Exchange::discard_stage( $st['token'] );

foreach ( array_merge( $ids, array( $a, $b ), $created ) as $x ) { wp_delete_post( $x, true ); }
ncd_done();
