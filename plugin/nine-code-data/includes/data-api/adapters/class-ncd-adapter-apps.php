<?php
/**
 * Bundled adapters for Nine Code apps that do not (yet) ship their own Data Manager provider.
 *
 * Each adapter starts from generic discovery for the app's post type (core fields, taxonomies,
 * registered and discovered meta) and only *authorizes* the editorial keys the app's own editor
 * writes, with the same cleaning rules the app applies (calling the app's public sanitizers where
 * it has them). Everything else the app stores stays protected. An adapter steps aside as soon as
 * the app registers its own provider or claims its post type, so ownership can move into the app
 * without a migration: field keys stay "meta:<key>".
 *
 * Apps covered by plain generic discovery (their meta is registered with auth_callback and
 * show_in_rest, or their data is not meant to be edited) are documented in
 * docs/DATA-MANAGER-APP-INVENTORY.md.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class NCD_Adapter_Apps {
	public static function boot() {
		add_action( 'ninecode_data_register_providers', array( __CLASS__, 'register' ), 100 );
	}

	public static function register() {
		$claimed = NCD_Registry::claimed_objects( 'post' );
		$apps    = array(
			'teaching-player' => array( 'class' => 'Nine_Code_Teaching_Player', 'type' => 'nine_tp_lecture', 'label' => '9CODE Teaching Player', 'build' => 'teaching_player' ),
			'lectureboard'    => array( 'class' => null, 'type' => 'nine_real_lecture', 'label' => '9/10 LectureBoard', 'build' => 'lectureboard' ),
			'live-lecture'    => array( 'class' => null, 'type' => 'gm_meeting', 'label' => 'Live Lecture (Google Meet)', 'build' => 'live_lecture' ),
		);
		foreach ( $apps as $id => $app ) {
			if ( NCD_Registry::provider( $id ) || isset( $claimed[ $app['type'] ] ) || ! post_type_exists( $app['type'] ) ) { continue; }
			if ( $app['class'] && ! class_exists( $app['class'] ) ) { continue; }
			$entity = call_user_func( array( __CLASS__, $app['build'] ) );
			ninecode_data_register_provider( $id, array(
				'label'       => $app['label'],
				'version'     => '1.0.0',
				'owner'       => $id,
				'adapter'     => 'bundled',
				'description' => 'Bundled adapter: authorizes the fields the app\'s own editor saves, with the app\'s rules. Other app data stays protected.',
				'entities'    => array( 'items' => $entity ),
			) );
		}
		if ( class_exists( 'NWS_DB' ) && ! NCD_Registry::provider( 'workshop' ) ) {
			ninecode_data_register_provider( 'workshop', array(
				'label'       => 'Workshop Lecture',
				'version'     => '1.0.0',
				'owner'       => 'nine-workshop-lecture',
				'adapter'     => 'bundled',
				'description' => 'Participant progress stored in the Workshop progress table. Read-only: it is written by participants\' browsers.',
				'entities'    => array( 'progress' => self::workshop_progress() ),
			) );
		}
	}

	/** Generic definition of $post_type plus authorized/overridden meta fields. */
	private static function extend( $post_type, $label, array $authorize, array $notes = array() ) {
		$e = NCD_Generic_Provider::post_entity_definition( $post_type );
		foreach ( $authorize as $key => $f ) {
			$f = array_merge( array( 'storage' => 'meta', 'source' => $key, 'group' => $label, 'origin' => 'app-adapter', 'protected' => false ), $f );
			$e['fields'][ 'meta:' . $key ] = $f;
		}
		$e['notes'] = array_merge( (array) ( $e['notes'] ?? array() ), $notes );
		return $e;
	}

	private static function clamp( $min, $max ) {
		return static function ( $v ) use ( $min, $max ) {
			if ( '' === trim( (string) $v ) ) { return new WP_Error( 'range', 'is required' ); }
			if ( ! is_numeric( $v ) ) { return new WP_Error( 'range', 'must be a number' ); }
			return max( $min, min( $max, (int) $v ) );
		};
	}

	private static function flag() {
		return static function ( $v ) { return ( true === $v || '1' === (string) $v || 'yes' === strtolower( (string) $v ) || 'true' === strtolower( (string) $v ) ) ? '1' : '0'; };
	}

	/** JSON kept as a slashed JSON string in meta (the app's storage format). */
	private static function json_string_field( $key, $label, $help, $clean = null ) {
		return array(
			'label' => $label, 'type' => 'json', 'storage' => 'callback', 'help' => $help,
			'read_callback'  => static function ( $id ) use ( $key ) { $d = json_decode( (string) get_post_meta( $id, $key, true ), true ); return is_array( $d ) ? $d : array(); },
			'write_callback' => static function ( $id, $value ) use ( $key, $clean ) {
				$value = $clean ? call_user_func( $clean, (array) $value ) : (array) $value;
				update_post_meta( $id, $key, wp_slash( wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) ) );
				return true;
			},
			'sanitize_callback' => static function ( $v ) {
				if ( is_string( $v ) ) { $v = '' === trim( $v ) ? array() : json_decode( $v, true ); }
				return is_array( $v ) ? $v : new WP_Error( 'json', 'must be valid JSON' );
			},
		);
	}

	/* ------------------------------------------------------------ Teaching Player (rules from save_meta) */

	private static function teaching_player() {
		$text = static function ( $v ) { return sanitize_text_field( (string) $v ); };
		$kses = static function ( $v ) { return wp_kses_post( (string) $v ); };
		$f = array(
			'_9tp_author_name'         => array( 'label' => 'Author name', 'type' => 'text', 'sanitize_callback' => $text ),
			'_9tp_author_role'         => array( 'label' => 'Author role', 'type' => 'text', 'sanitize_callback' => $text ),
			'_9tp_video_url'           => array( 'label' => 'Video URL', 'type' => 'url' ),
			'_9tp_author_image'        => array( 'label' => 'Author image URL', 'type' => 'url' ),
			'_9tp_author_bio'          => array( 'label' => 'Author bio', 'type' => 'html', 'sanitize_callback' => $kses ),
			'_9tp_lecture_description' => array( 'label' => 'Lecture description', 'type' => 'html', 'sanitize_callback' => $kses ),
			'_9tp_video_width'         => array( 'label' => 'Video width (px)', 'type' => 'integer', 'min' => 130, 'max' => 320, 'sanitize_callback' => self::clamp( 130, 320 ) ),
			'_9tp_strip_height'        => array( 'label' => 'Strip height (px)', 'type' => 'integer', 'min' => 90, 'max' => 160, 'sanitize_callback' => self::clamp( 90, 160 ) ),
			'_9tp_spacing'             => array( 'label' => 'Spacing', 'type' => 'integer', 'min' => 0, 'max' => 18, 'sanitize_callback' => self::clamp( 0, 18 ) ),
			'_9tp_content_width'       => array( 'label' => 'Content width (px)', 'type' => 'integer', 'min' => 520, 'max' => 980, 'sanitize_callback' => self::clamp( 520, 980 ) ),
			'_9tp_drawer_width'        => array( 'label' => 'Popup width (px)', 'type' => 'integer', 'min' => 320, 'max' => 760, 'sanitize_callback' => self::clamp( 320, 760 ) ),
			'_9tp_accent'              => array( 'label' => 'Accent colour', 'type' => 'text', 'example' => '#263a52', 'sanitize_callback' => static function ( $v ) { $c = sanitize_hex_color( (string) $v ); return $c ? $c : new WP_Error( 'hex', 'must be a hex colour like #263a52' ); } ),
			'_9tp_sticky'              => array( 'label' => 'Sticky player', 'type' => 'boolean', 'sanitize_callback' => self::flag() ),
			'_9tp_default_dark'        => array( 'label' => 'Dark mode by default', 'type' => 'boolean', 'sanitize_callback' => self::flag() ),
			'_9tp_full_page'           => array( 'label' => 'Full-page layout', 'type' => 'boolean', 'sanitize_callback' => self::flag() ),
		);
		$f['_9tp_blocks_json'] = self::json_string_field( '_9tp_blocks_json', 'Learning blocks', 'Cleaned by Teaching Player\'s own block sanitizer.', array( 'Nine_Code_Teaching_Player', 'sanitize_blocks' ) );
		return self::extend( 'nine_tp_lecture', 'Teaching Player', $f, array( 'Contrast mode, block theme and onboarding keys are maintained by Teaching Player.' ) );
	}

	/* ------------------------------------------------------------ LectureBoard (rules from save_meta) */

	private static function lectureboard() {
		$f = array(
			'_nine_lb_video_url'         => array( 'label' => 'Video URL', 'type' => 'url' ),
			'_nine_lb_back_url'          => array( 'label' => 'Back link URL', 'type' => 'url' ),
			'_nine_lb_learning_goal'     => array( 'label' => 'Learning goal', 'type' => 'text' ),
			'_nine_lb_estimated_minutes' => array( 'label' => 'Estimated minutes', 'type' => 'integer', 'min' => 0, 'max' => 1440, 'sanitize_callback' => static function ( $v ) { return min( 1440, absint( $v ) ); } ),
			'_nine_lb_autoplay'          => array( 'label' => 'Autoplay', 'type' => 'boolean', 'sanitize_callback' => self::flag() ),
			'_nine_lb_sticky'            => array( 'label' => 'Sticky video', 'type' => 'boolean', 'sanitize_callback' => self::flag() ),
			// LectureBoard keeps the raw JSON text so authors can correct it; its renderer is fail-safe.
			'_nine_lb_blocks_json'       => array( 'label' => 'Learning blocks (JSON)', 'type' => 'textarea', 'storage' => 'callback',
				'help' => 'Pasted JSON is stored as written, like LectureBoard\'s own editor; invalid JSON is rejected here.',
				'read_callback'     => static function ( $id ) { return (string) get_post_meta( $id, '_nine_lb_blocks_json', true ); },
				'write_callback'    => static function ( $id, $v ) { update_post_meta( $id, '_nine_lb_blocks_json', wp_slash( (string) $v ) ); return true; },
				'sanitize_callback' => static function ( $v ) {
					$v = trim( is_array( $v ) ? (string) wp_json_encode( $v ) : (string) $v );
					if ( '' !== $v ) { json_decode( $v, true ); if ( JSON_ERROR_NONE !== json_last_error() ) { return new WP_Error( 'json', 'is not valid JSON (' . json_last_error_msg() . ')' ); } }
					return $v;
				} ),
		);
		return self::extend( 'nine_real_lecture', 'LectureBoard', $f, array( 'Learner progress, notes and preferences (_nine_lb_* user meta) are personal data and are not exposed.' ) );
	}

	/* ------------------------------------------------------------ Live Lecture / Google Meet (rules from save_meta) */

	private static function live_lecture() {
		$f = array(
			'_gm_start'         => array( 'label' => 'Start (YYYY-MM-DDTHH:MM)', 'type' => 'text', 'example' => '2026-10-05T14:00', 'sanitize_callback' => static function ( $v ) {
				$v = sanitize_text_field( (string) $v );
				return ( '' === $v || preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $v ) ) ? $v : new WP_Error( 'start', 'must look like 2026-10-05T14:00' );
			} ),
			'_gm_duration'      => array( 'label' => 'Duration (minutes)', 'type' => 'integer', 'min' => 15, 'max' => 1440, 'sanitize_callback' => self::clamp( 15, 1440 ) ),
			'_gm_intro_summary' => array( 'label' => 'Intro summary', 'type' => 'textarea' ),
			'_gm_minutes'       => array( 'label' => 'Minutes', 'type' => 'html', 'sanitize_callback' => static function ( $v ) { return wp_kses_post( (string) $v ); } ),
			'_gm_recording_url' => array( 'label' => 'Recording URL', 'type' => 'url' ),
			'_gm_agenda'        => array( 'label' => 'Agenda', 'type' => 'repeater', 'sub_fields' => array(
				'title'       => array( 'label' => 'Title', 'type' => 'text' ),
				'image_id'    => array( 'label' => 'Image', 'type' => 'media' ),
				'description' => array( 'label' => 'Description', 'type' => 'textarea' ),
				'list'        => array( 'label' => 'List', 'type' => 'textarea' ),
				'summary'     => array( 'label' => 'Summary', 'type' => 'textarea' ),
			) ),
		);
		$f['_gm_meet_url'] = array( 'label' => 'Google Meet URL', 'type' => 'url', 'protected' => true, 'reason' => 'Validated against allowed Meet hosts in the Live Lecture editor' );
		return self::extend( 'gm_meeting', 'Live Lecture', $f, array( 'Host password/code hashes are secret. Reminder subscribers and access lists are personal data managed by Live Lecture.' ) );
	}

	/* ------------------------------------------------------------ Workshop progress table (read-only custom entity) */

	private static function workshop_progress() {
		global $wpdb;
		$table = NWS_DB::table();
		$fields = array();
		foreach ( array( 'workshop_id' => 'Workshop', 'participant_name' => 'Participant', 'participant_email' => 'Email', 'user_id' => 'User', 'current_step' => 'Current step', 'status' => 'Status', 'submitted_at' => 'Submitted', 'updated_at' => 'Updated', 'progress_json' => 'Progress', 'notes_json' => 'Notes', 'completed_json' => 'Completed steps', 'upload_json' => 'Uploads' ) as $col => $label ) {
			$fields[ $col ] = array( 'label' => $label, 'type' => 'readonly', 'storage' => 'callback', 'protected' => true, 'reason' => 'Written by the participant\'s workshop session' );
		}
		return array(
			'label' => 'Workshop progress', 'singular' => 'Progress record', 'kind' => 'custom',
			'columns' => array( 'participant_name', 'workshop_id', 'status', 'updated_at' ),
			'allow_create' => false, 'allow_trash' => false,
			'notes' => array( 'Read-only. Exported for review and reporting; participant data is personal data.' ),
			'fields' => $fields,
			'permission_callback' => static function ( $action ) { return 'read' === $action && current_user_can( 'edit_others_posts' ); },
			'list_callback' => static function ( $args ) use ( $wpdb, $table ) {
				$per = (int) $args['per_page']; $off = ( (int) $args['page'] - 1 ) * $per;
				$where = '1=1'; $params = array();
				if ( ! empty( $args['ids'] ) ) { $ids = array_map( 'absint', (array) $args['ids'] ); $where .= ' AND id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')'; $params = array_merge( $params, $ids ); }
				if ( '' !== (string) ( $args['search'] ?? '' ) ) { $like = '%' . $wpdb->esc_like( $args['search'] ) . '%'; $where .= ' AND (participant_name LIKE %s OR participant_email LIKE %s)'; $params[] = $like; $params[] = $like; }
				$order = 'ASC' === ( $args['order'] ?? 'DESC' ) ? 'ASC' : 'DESC';
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- table name from the owning plugin, values prepared.
				$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE {$where} ORDER BY updated_at {$order} LIMIT %d OFFSET %d", array_merge( $params, array( $per, $off ) ) ) );
				$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $params ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) );
				// phpcs:enable
				return array( 'ids' => $ids, 'total' => $total );
			},
			'read_callback' => static function ( $id ) use ( $wpdb, $table ) {
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
				if ( ! $row ) { return null; }
				foreach ( array( 'progress_json', 'notes_json', 'completed_json', 'upload_json' ) as $j ) { $d = json_decode( (string) $row[ $j ], true ); $row[ $j ] = is_array( $d ) ? $d : array(); }
				return array_intersect_key( $row, array_flip( array( 'workshop_id', 'participant_name', 'participant_email', 'user_id', 'current_step', 'status', 'submitted_at', 'updated_at', 'progress_json', 'notes_json', 'completed_json', 'upload_json' ) ) );
			},
			'label_callback' => static function ( $id ) use ( $wpdb, $table ) {
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT participant_name, status, updated_at, workshop_id FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
				return $row ? array( 'label' => ( $row['participant_name'] ?: 'Participant' ) . ' · ' . get_the_title( (int) $row['workshop_id'] ), 'status' => $row['status'], 'modified' => $row['updated_at'] ) : array();
			},
		);
	}
}
NCD_Adapter_Apps::boot();
