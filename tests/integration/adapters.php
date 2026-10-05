<?php
require __DIR__ . '/lib.php';
ncd_suite( 'adapters' );
ncd_as( 'admin' );
ncd_reboot();

/* ---------------------------------------------------------------- Open Scholar */
if ( ! class_exists( 'OSCH_Plugin' ) ) { echo "SKIP Open Scholar not active\n"; }
else {
	$p = NCD_Registry::provider( 'open-scholar' );
	ncd_t( $p && 'dedicated' === $p['adapter'] && OSCH_VERSION === $p['version'], 'Open Scholar provider registered via contract' );
	ncd_t( ! NCD_Registry::provider( 'wordpress' ) || ! isset( NCD_Registry::provider( 'wordpress' )['entities']['type-os_publication'] ), 'os_publication not duplicated by generic discovery' );
	$e = ncd_entity( 'open-scholar', 'publication' );
	ncd_t( count( $e['fields'] ) - 5 === count( OSCH_Fields::library() ), 'every OSCH_Fields library field exposed (no second definition system)' );
	ncd_eq( $e['fields']['os:peer_reviewed']['choices'], array( 'yes' => 'Yes', 'no' => 'No' ), 'select options come from OSCH_Fields' );
	ncd_eq( $e['fields']['os:primary_pdf_id']['type'], 'media', 'file field mapped to media' );
	ncd_eq( $e['fields']['os:project_gallery_image_ids']['type'], 'media_list', 'image list mapped to media_list' );
	ncd_eq( $e['fields']['os:related_publication_ids']['type'], 'post_list', 'related records mapped to post_list' );
	ncd_t( in_array( 'grant', $e['fields']['os:funder']['applies_to'], true ) && ! in_array( 'article', $e['fields']['os:funder']['applies_to'], true ), 'publication-type applicability recorded' );

	$scholar = ncd_user( 'ncd_scholar', 'author' );
	$plugin  = OSCH_Plugin::instance();
	$c = NCD_Service::create( $e, array( 'post_title' => 'Malaria surveillance', 'type' => 'article', 'scholars' => array( $scholar->ID ) ), array( 'allow_create' => true ) );
	ncd_t( ! is_wp_error( $c ) && $c['ok'] && 'article' === get_post_meta( $c['id'], '_os_type', true ), 'create through OSCH_Records::create_record', $c );
	$id = $c['id'];
	ncd_eq( $plugin->allocated_author_ids( $id ), array( $scholar->ID ), 'scholar allocated' );
	ncd_t( has_term( 'user-' . $scholar->ID, 'os_scholar', $id ), 'os_scholar taxonomy maintained by Open Scholar' );

	$r = NCD_Service::save( $e, $id, array( 'os:year' => 'published 2024', 'os:doi' => '10.1/abc', 'os:peer_reviewed' => 'Yes' ) );
	ncd_t( $r['ok'] && '2024' === get_post_meta( $id, '_os_year', true ) && 'yes' === get_post_meta( $id, '_os_peer_reviewed', true ), 'OSCH_Fields::sanitize applied (year extraction, label→value)', $r );
	$r2 = NCD_Service::save( $e, $id, array( 'os:contact_email' => 'x' ) );
	ncd_t( ! $r2['ok'], 'unknown field rejected' );
	$r = NCD_Service::save( $e, $id, array( 'os:funder' => 'Wellcome' ) );
	ncd_t( ! $r['ok'] && false !== strpos( $r['errors']['os:funder'], 'Not used by' ), 'field outside the record type rejected' );
	$r = NCD_Service::save( $e, $id, array( 'type' => 'grant', 'os:funder' => 'Wellcome' ) );
	ncd_t( $r['ok'] && 'Wellcome' === get_post_meta( $id, '_os_funder', true ), 'type change validates against the new layout' );
	NCD_Service::save( $e, $id, array( 'type' => 'article' ) );

	// Publishing rule enforced by Open Scholar.
	$r = NCD_Service::save( $e, $id, array( 'post_status' => 'publish' ), array( 'allow_status' => true ) );
	ncd_t( ! $r['ok'] && 'draft' === get_post_status( $id ) && false !== stripos( $r['errors']['post_status'], 'Authors' ), 'publishing blocked by Open Scholar rule (Authors empty)', $r );
	$r = NCD_Service::save( $e, $id, array( 'os:bibliographic_authors' => 'Okafor, A.; Bello, K.' ) );
	$r = NCD_Service::save( $e, $id, array( 'post_status' => 'publish' ) );
	ncd_t( ! $r['ok'] && 'draft' === get_post_status( $id ), 'publishing still needs explicit allow_status' );
	$r = NCD_Service::save( $e, $id, array( 'post_status' => 'publish' ), array( 'allow_status' => true ) );
	ncd_t( $r['ok'] && 'publish' === get_post_status( $id ), 'published through OSCH_Records::save_record' );
	$log = $plugin->records()->activity( $scholar->ID );
	ncd_t( ! empty( $log ) && (int) $log[0]['object'] === $id, 'Open Scholar activity log written' );

	// Files and relationships.
	$pdf = wp_insert_attachment( array( 'post_title' => 'paper', 'post_mime_type' => 'application/pdf', 'post_status' => 'inherit' ) );
	$other = ncd_post( array( 'post_type' => 'post' ) );
	$rel = NCD_Service::create( $e, array( 'post_title' => 'Related', 'type' => 'article' ), array( 'allow_create' => true ) )['id'];
	$r = NCD_Service::save( $e, $id, array( 'os:primary_pdf_id' => $pdf, 'os:related_publication_ids' => array( $rel, $other ) ) );
	ncd_eq( (int) get_post_meta( $id, '_os_primary_pdf_id', true ), $pdf, 'file reference saved' );
	ncd_eq( OSCH_Fields::get_record_value( $id, OSCH_Fields::library()['related_publication_ids'] + array( 'key' => 'related_publication_ids' ) ), array( $rel ), 'non-Open-Scholar ids dropped from related records by Open Scholar' );

	// Export → AI edit → import → undo.
	$pkg = NCD_Exchange::package( $e, NCD_Exchange::export_records( $e, array( 'ids' => array( $id ) ) ) );
	ncd_t( isset( $pkg['_links'] ) || isset( $pkg['records'][0]['_links']['os:primary_pdf_id'] ), 'export carries media link context' );
	$pkg['records'][0]['fields']['os:abstract'] = 'AI-written abstract';
	$pkg['records'][0]['fields']['post_status'] = 'draft';
	$n  = NCD_Exchange::normalize_package( json_decode( wp_json_encode( $pkg ), true ), $e );
	$pv = NCD_Exchange::preview( $e, $n, 0, 5, array() );
	$st = wp_list_pluck( $pv['rows'][0]['fields'], 'status', 'key' );
	ncd_t( 'change' === $st['os:abstract'] && 'status' === $st['post_status'], 'preview separates content change from unpublish' );
	$ap = NCD_Exchange::apply( $e, $n, array( 0 => true ), array() );
	ncd_t( 'AI-written abstract' === get_post_meta( $id, '_os_abstract', true ) && 'publish' === get_post_status( $id ), 'import applied content, did not unpublish' );
	NCD_Service::undo( $ap['history_id'] );
	ncd_t( '' === get_post_meta( $id, '_os_abstract', true ), 'undo restored Open Scholar field' );

	// CSV round trip lossless.
	$flat = NCD_Exchange::flatten( $e, NCD_Exchange::export_records( $e, array( 'ids' => array( $id ) ) ) );
	$f = wp_tempnam( 'ncd' ) . '.csv'; file_put_contents( $f, NCD_Sheet::csv( $flat['headers'], $flat['rows'] ) );
	$pv = NCD_Exchange::preview( $e, NCD_Exchange::parse( $f, 'csv', $e, true ), 0, 5, array() );
	ncd_eq( $pv['rows'][0]['action'], 'unchanged', 'Open Scholar CSV round trip is lossless' );

	// Permissions: a scholar may only manage their own archive.
	$other_scholar = ncd_user( 'ncd_scholar2', 'author' );
	$other_scholar->add_cap( 'manage_ninecode_data' );
	ncd_as( $other_scholar->ID ); ncd_reboot(); $e2 = ncd_entity( 'open-scholar', 'publication' );
	$r = NCD_Service::save( $e2, $id, array( 'os:abstract' => 'hijack' ) );
	ncd_t( is_wp_error( $r ) && '' === get_post_meta( $id, '_os_abstract', true ), 'another scholar cannot edit the record' );
	$own = NCD_Service::create( $e2, array( 'post_title' => 'Mine', 'type' => 'article', 'scholars' => array( $scholar->ID ) ), array( 'allow_create' => true ) );
	ncd_t( is_wp_error( $own ) || ! $own['ok'], 'cannot allocate a record to someone else\'s archive' );
	$other_scholar->remove_cap( 'manage_ninecode_data' );
	ncd_as( 'admin' ); ncd_reboot();

	// Scholar profiles (user meta through save_profile).
	$se = ncd_entity( 'open-scholar', 'scholar' );
	$r = NCD_Service::save( $se, $scholar->ID, array( 'os:orcid' => '0000-0002-1825-0097', 'os:website' => 'example.org', 'os:contact_email' => 'bad' ) );
	ncd_t( ! $r['ok'] && isset( $r['errors']['os:contact_email'] ), 'profile email validated by Open Scholar' );
	$r = NCD_Service::save( $se, $scholar->ID, array( 'os:orcid' => '0000-0002-1825-0097', 'os:website' => 'example.org' ) );
	ncd_t( $r['ok'] && 'https://example.org' === get_user_meta( $scholar->ID, '_os_website', true ), 'profile saved through save_profile (URL normalised)' );
	ncd_t( $se['fields']['enabled_types']['protected'] && ! isset( $se['fields']['meta:_os_activity_log'] ), 'activity log not exposed; archive types read-only' );

	foreach ( array( $id, $rel, $other ) as $x ) { wp_delete_post( $x, true ); }
	wp_delete_attachment( $pdf, true );
}

/* ---------------------------------------------------------------- Conference.lat */
if ( ! class_exists( 'ALP_Plugin' ) ) { echo "SKIP Conference.lat not active\n"; }
else {
	$p = NCD_Registry::provider( 'conference-lat' );
	ncd_t( $p && ALP_VERSION === $p['version'], 'Conference.lat provider registered via contract' );
	$admin = ALP_Plugin::instance()->admin;
	$ne = ncd_entity( 'conference-lat', 'note' );
	$ce = ncd_entity( 'conference-lat', 'conference' );
	ncd_eq( count( $ne['fields'] ) - 5, count( $admin->data_note_schema() ), 'every Conference Table column exposed' );
	ncd_t( $ne['fields']['conference']['protected'] && $ne['fields']['slot']['protected'] && $ne['fields']['pending_submission']['protected'], 'structural note data read-only' );
	ncd_t( ! $ne['allow_create'] && ! $ce['allow_create'] && ! $ce['allow_trash'], 'create/delete stays in the Table Manager' );

	$conf = (int) wp_insert_post( array( 'post_type' => 'alp_proceeding', 'post_title' => 'NCD Test Conference', 'post_status' => 'draft' ) );
	$note = (int) wp_insert_post( array( 'post_type' => 'alp_article', 'post_title' => 'Paper one', 'post_status' => 'draft' ) );
	update_post_meta( $note, '_alp_proceeding_id', $conf ); update_post_meta( $note, '_alp_slot', 1 );
	update_post_meta( $note, '_alp_pending_submission', array( 'title' => 'Participant edit' ) );

	$r = NCD_Service::save( $ne, $note, array( 'doi' => 'https://doi.org/10.1234/ABC.5', 'authors' => 'Ada Lovelace' ) );
	ncd_t( $r['ok'] && '10.1234/abc.5' === strtolower( get_post_meta( $note, '_alp_doi', true ) ), 'DOI normalised by Conference.lat', $r );
	ncd_t( '' !== (string) get_post_meta( $note, '_alp_edit_version', true ), 'edit version touched like the Table Manager' );
	$r = NCD_Service::save( $ne, $note, array( 'doi' => 'not-a-doi' ) );
	ncd_t( ! $r['ok'] && isset( $r['errors']['doi'] ), 'invalid DOI rejected by Conference.lat' );
	$r = NCD_Service::save( $ne, $note, array( 'orcids' => '0000-0002-1825-0098' ) );
	ncd_t( ! $r['ok'], 'ORCID checksum enforced' );
	$img = wp_insert_attachment( array( 'post_title' => 'not pdf', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ) );
	$r = NCD_Service::save( $ne, $note, array( 'pdf_attachment_id' => $img ) );
	ncd_t( ! $r['ok'], 'non-PDF attachment rejected' );
	$r = NCD_Service::save( $ne, $note, array( 'slot' => '9', 'conference' => '1' ) );
	ncd_t( ! $r['ok'] && '1' === (string) get_post_meta( $note, '_alp_slot', true ), 'slot and conference allocation not writable' );
	$r = NCD_Service::save( $ne, $note, array( 'status' => 'publish' ) );
	ncd_t( ! $r['ok'] && 'draft' === get_post_status( $note ), 'publishing needs allow_status' );
	$r = NCD_Service::save( $ne, $note, array( 'status' => 'publish' ), array( 'allow_status' => true ) );
	ncd_t( $r['ok'] && 'publish' === get_post_status( $note ), 'published with explicit permission' );
	ncd_t( ! empty( get_post_meta( $note, '_alp_pending_submission', true ) ), 'pending submission untouched' );

	// Conference JSON repeaters.
	$speakers = array( array( 'name' => 'Grace Hopper', 'role' => 'Keynote' ) );
	$r = NCD_Service::save( $ce, $conf, array( 'speakers_json' => $speakers, 'conference_venue' => 'Lagos', 'open_scholar_enabled' => true ) );
	ncd_t( $r['ok'] && 'Grace Hopper' === get_post_meta( $conf, '_alp_proceeding_speakers', true )[0]['name'] && '1' === get_post_meta( $conf, '_alp_open_scholar_enabled', true ), 'conference JSON repeater + bool saved via Conference.lat', $r );
	$r = NCD_Service::save( $ce, $conf, array( 'editors_json' => '{broken' ) );
	ncd_t( ! $r['ok'], 'malformed JSON rejected (never wipes the list)' );
	$r = NCD_Service::save( $ce, $conf, array( 'conference_status' => 'publish' ) );
	ncd_t( ! $r['ok'] && 'draft' === get_post_status( $conf ), 'conference publish needs allow_status' );

	// Export/import round trip + undo.
	$pkg = NCD_Exchange::package( $ce, NCD_Exchange::export_records( $ce, array( 'ids' => array( $conf ) ) ) );
	$pkg['records'][0]['fields']['speakers_json'][] = array( 'name' => 'Added by AI', 'role' => 'Panel' );
	$n  = NCD_Exchange::normalize_package( json_decode( wp_json_encode( $pkg ), true ), $ce );
	$ap = NCD_Exchange::apply( $ce, $n, array( 0 => true ), array() );
	ncd_eq( count( get_post_meta( $conf, '_alp_proceeding_speakers', true ) ), 2, 'AI-added speaker imported' );
	NCD_Service::undo( $ap['history_id'] );
	ncd_eq( count( get_post_meta( $conf, '_alp_proceeding_speakers', true ) ), 1, 'undo restored speakers' );
	$flat = NCD_Exchange::flatten( $ne, NCD_Exchange::export_records( $ne, array( 'ids' => array( $note ) ) ) );
	$f = wp_tempnam( 'ncd' ) . '.xlsx'; file_put_contents( $f, NCD_Sheet::xlsx( $flat['headers'], $flat['rows'], $flat['guide'] ) );
	$pv = NCD_Exchange::preview( $ne, NCD_Exchange::parse( $f, 'xlsx', $ne, true ), 0, 5, array() );
	ncd_eq( $pv['rows'][0]['action'], 'unchanged', 'Conference note XLSX round trip is lossless' );

	// Trash re-sequences slots (Table Manager behaviour).
	$note2 = (int) wp_insert_post( array( 'post_type' => 'alp_article', 'post_title' => 'Paper two', 'post_status' => 'draft' ) );
	update_post_meta( $note2, '_alp_proceeding_id', $conf ); update_post_meta( $note2, '_alp_slot', 2 );
	$t = NCD_Service::trash( $ne, $note, array( 'allow_trash' => true ) );
	ncd_t( ! is_wp_error( $t ) && 'trash' === get_post_status( $note ) && '1' === (string) get_post_meta( $note2, '_alp_slot', true ), 'trash through Conference.lat re-sequences slots' );

	foreach ( array( $note, $note2, $conf ) as $x ) { wp_delete_post( $x, true ); }
	wp_delete_attachment( $img, true );
}
/* ---------------------------------------------------------------- Nine Code apps */
$expect = array( 'nine_tp_lecture' => 'Nine_Code_Teaching_Player', 'nine_real_lecture' => null, 'gm_meeting' => null, 'dsg_showglass' => null, 'dsg_item' => null, 'dsg_order' => null, 'ninestagram_offer' => null, 'nls_workshop' => 'NWS_DB' );
$found = array();
foreach ( NCD_Registry::providers() as $prov ) { foreach ( $prov['entities'] as $en ) { if ( 'post' === $en['kind'] ) { $found[ $en['object_type'] ] = $prov['id']; } } }
foreach ( $expect as $pt => $cls ) {
	if ( ! post_type_exists( $pt ) ) { echo "SKIP app post type {$pt} not active\n"; continue; }
	ncd_t( isset( $found[ $pt ] ), "app post type {$pt} discovered (" . ( $found[ $pt ] ?? 'none' ) . ')' );
}
if ( class_exists( 'Nine_Code_Teaching_Player' ) ) {
	$te = ncd_entity( 'teaching-player', 'items' );
	$id = ncd_post( array( 'post_type' => 'nine_tp_lecture' ) );
	$r  = NCD_Service::save( $te, $id, array( 'meta:_9tp_video_width' => 999, 'meta:_9tp_sticky' => true, 'meta:_9tp_accent' => '#123abc', 'meta:_9tp_author_bio' => '<p>Bio</p><script>x</script>' ) );
	ncd_t( $r['ok'] && '320' === (string) get_post_meta( $id, '_9tp_video_width', true ) && '1' === get_post_meta( $id, '_9tp_sticky', true ), 'Teaching Player: range clamped and flag stored as 1/0 (app rules)', $r );
	ncd_t( false === strpos( get_post_meta( $id, '_9tp_author_bio', true ), '<script' ), 'Teaching Player: bio kses-cleaned' );
	ncd_t( ! NCD_Service::save( $te, $id, array( 'meta:_9tp_accent' => 'red' ) )['ok'], 'Teaching Player: invalid hex colour rejected' );
	$r = NCD_Service::save( $te, $id, array( 'meta:_9tp_blocks_json' => array( array( 'title' => 'Intro <b>x</b>', 'body' => 'Hello' ) ) ) );
	$raw = get_post_meta( $id, '_9tp_blocks_json', true );
	ncd_t( $r['ok'] && is_array( json_decode( $raw, true ) ), 'Teaching Player: blocks stored as JSON text via its own sanitize_blocks' );
	ncd_t( is_array( NCD_Store::read( $te, $id )['meta:_9tp_blocks_json'] ), 'Teaching Player: blocks read back as structured data' );
	update_post_meta( $id, '_9tp_contrast_mode', 'elements' );
	ncd_reboot(); $te = ncd_entity( 'teaching-player', 'items' );
	ncd_t( ! NCD_Service::save( $te, $id, array( 'meta:_9tp_contrast_mode' => 'x' ) )['ok'], 'Teaching Player: internal keys stay protected' );
	wp_delete_post( $id, true );
}
if ( post_type_exists( 'nine_real_lecture' ) && NCD_Registry::provider( 'lectureboard' ) ) {
	$le = ncd_entity( 'lectureboard', 'items' );
	$id = ncd_post( array( 'post_type' => 'nine_real_lecture' ) );
	ncd_t( ! NCD_Service::save( $le, $id, array( 'meta:_nine_lb_blocks_json' => '[{"title": broken' ) )['ok'], 'LectureBoard: invalid blocks JSON rejected' );
	$json = '[{"title":"A \"quoted\" title","body":"line\nbreak"}]';
	$r = NCD_Service::save( $le, $id, array( 'meta:_nine_lb_blocks_json' => $json, 'meta:_nine_lb_estimated_minutes' => 5000 ) );
	ncd_t( $r['ok'] && $json === get_post_meta( $id, '_nine_lb_blocks_json', true ), 'LectureBoard: JSON escapes preserved exactly (wp_slash)' );
	ncd_eq( (int) get_post_meta( $id, '_nine_lb_estimated_minutes', true ), 1440, 'LectureBoard: minutes capped like the app' );
	wp_delete_post( $id, true );
}
if ( post_type_exists( 'gm_meeting' ) && NCD_Registry::provider( 'live-lecture' ) ) {
	$id = ncd_post( array( 'post_type' => 'gm_meeting' ) );
	update_post_meta( $id, '_gm_host_password_hash', wp_hash_password( 'secret1' ) );
	update_post_meta( $id, '_gm_reminder_subscribers', array( 'h' => array( 'email' => 'a@example.test' ) ) );
	delete_transient( 'ncd_meta_keys_' . md5( 'post|gm_meeting|' . wp_cache_get_last_changed( 'posts' ) . wp_cache_get_last_changed( 'terms' ) . wp_cache_get_last_changed( 'users' ) ) );
	ncd_reboot(); $ge = ncd_entity( 'live-lecture', 'items' );
	ncd_t( $ge['fields']['meta:_gm_host_password_hash']['secret'], 'Live Lecture: host password hash is secret' );
	ncd_t( false === strpos( wp_json_encode( NCD_Exchange::export_records( $ge, array( 'ids' => array( $id ) ) ) ), '$P$' ) && false === strpos( wp_json_encode( NCD_Exchange::export_records( $ge, array( 'ids' => array( $id ) ) ) ), '$wp' ), 'Live Lecture: hash never exported' );
	ncd_t( $ge['fields']['meta:_gm_reminder_subscribers']['protected'], 'Live Lecture: subscriber emails protected' );
	ncd_t( ! NCD_Service::save( $ge, $id, array( 'meta:_gm_start' => 'tomorrow' ) )['ok'], 'Live Lecture: start format enforced' );
	$r = NCD_Service::save( $ge, $id, array( 'meta:_gm_start' => '2026-10-05T14:00', 'meta:_gm_duration' => 5, 'meta:_gm_agenda' => array( array( 'title' => 'Welcome', 'summary' => 'Hi' ) ) ) );
	ncd_t( $r['ok'] && 15 === (int) get_post_meta( $id, '_gm_duration', true ) && 'Welcome' === get_post_meta( $id, '_gm_agenda', true )[0]['title'], 'Live Lecture: duration clamped, agenda repeater saved', $r );
	ncd_t( ! NCD_Service::save( $ge, $id, array( 'meta:_gm_meet_url' => 'https://evil.example' ) )['ok'], 'Live Lecture: Meet URL locked to the app editor' );
	wp_delete_post( $id, true );
}
if ( post_type_exists( 'dsg_showglass' ) ) {
	$de = ncd_entity( 'wordpress', 'type-dsg_showglass' );
	ncd_t( $de['fields']['meta:_dsg_whatsapp_webhook_token']['secret'], 'Showglass: webhook token secret (generic discovery)' );
	$oe = ncd_entity( 'wordpress', 'type-dsg_order' );
	ncd_t( $oe['fields']['meta:_dsg_order_customer_email']['protected'] && $oe['fields']['meta:_dsg_order_items']['protected'], 'Showglass: order customer data read-only' );
	$editable = array_filter( $de['fields'], static function ( $f ) { return 'registered-meta' === $f['origin'] && ! $f['protected']; } );
	ncd_t( count( $editable ) > 0, 'Showglass: meta registered with auth_callback + show_in_rest is editable without an adapter (' . count( $editable ) . ' keys)' );
}
if ( class_exists( 'NWS_DB' ) ) {
	global $wpdb;
	$wid = ncd_post( array( 'post_type' => 'nls_workshop', 'post_title' => 'Workshop A' ) );
	$wpdb->insert( NWS_DB::table(), array( 'workshop_id' => $wid, 'identity_key' => 'k' . wp_generate_password( 6, false ), 'participant_name' => 'Pat Learner', 'participant_email' => 'pat@example.test', 'progress_json' => '{"step":2}', 'status' => 'in_progress', 'created_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ) );
	$pid = (int) $wpdb->insert_id;
	$we = ncd_entity( 'workshop', 'progress' );
	$l = NCD_Service::list_records( $we, array( 'search' => 'Pat' ) );
	ncd_t( 1 === $l['total'] && false !== strpos( $l['rows'][0]['summary']['label'], 'Workshop A' ), 'Workshop: progress table listed through provider callbacks' );
	ncd_eq( NCD_Store::read( $we, $pid )['progress_json'], array( 'step' => 2 ), 'Workshop: JSON columns decoded' );
	$r = NCD_Service::save( $we, $pid, array( 'status' => 'submitted' ) );
	ncd_t( ( is_wp_error( $r ) || ! $r['ok'] ) && 'in_progress' === $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . NWS_DB::table() . ' WHERE id = %d', $pid ) ), 'Workshop: progress records are read-only' );
	$wpdb->delete( NWS_DB::table(), array( 'id' => $pid ) );
	wp_delete_post( $wid, true );
}
ncd_done();
