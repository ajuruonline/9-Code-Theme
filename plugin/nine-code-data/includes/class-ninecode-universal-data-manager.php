<?php
/**
 * Backward-compatibility layer for the first Universal Data Manager draft.
 *
 * The draft exposed `ninecode/v1/data/*` REST routes, an admin page `ninecode-universal-data`, admin-post
 * handlers and the `ninecode_data_manager_schema` filter. Those names are preserved here, but every one of
 * them now delegates to the Data API (includes/data-api) so that permission checks, provider ownership,
 * protected fields, preview-before-apply and audit history apply uniformly. The draft's unrestricted
 * metadata import is gone: the legacy import route is preview-only and points callers to the staged
 * `ninecode-data/v1/import/*` flow.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'NineCode_Universal_Data_Manager' ) ) {
	class NineCode_Universal_Data_Manager {
		const VERSION = '2.0.0';
		const CAP     = 'manage_ninecode_data';

		public static function instance() {
			static $instance = null;
			if ( null === $instance ) { $instance = new self(); }
			return $instance;
		}

		private function __construct() {
			add_action( 'admin_menu', array( $this, 'admin_menu' ), 25 );
			add_action( 'admin_init', array( $this, 'redirect_legacy_page' ) );
			add_action( 'rest_api_init', array( $this, 'rest_api' ) );
			add_action( 'admin_post_ninecode_universal_export', array( $this, 'handle_export' ) );
			add_action( 'admin_post_ninecode_universal_import', array( $this, 'handle_import' ) );
			add_filter( 'ninecode_data_manager_schema', array( $this, 'schema' ) );
		}

		/** Workspace URL, optionally pre-selecting an entity. */
		public static function workspace_url( array $args = array() ) {
			return add_query_arg( array_merge( array( 'page' => NCD_Admin::SLUG ), $args ), admin_url( 'admin.php' ) );
		}

		/** Finds the entity (from any provider) that manages a post type. */
		public static function entity_for_post_type( $post_type ) {
			$post_type = sanitize_key( $post_type );
			foreach ( NCD_Registry::providers() as $provider ) {
				foreach ( $provider['entities'] as $entity ) {
					if ( 'post' === $entity['kind'] && $entity['object_type'] === $post_type ) { return $entity; }
				}
			}
			return null;
		}

		public function admin_menu() {
			// Hidden page so old bookmarks resolve; admin_init redirects before it renders.
			add_submenu_page( '', 'Universal Data', 'Universal Data', self::CAP, 'ninecode-universal-data', array( $this, 'render_page' ) );
		}

		public function redirect_legacy_page() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect.
			if ( ! isset( $_GET['page'] ) || 'ninecode-universal-data' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { return; }
			$args = array();
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect.
			$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
			$entity = $type ? self::entity_for_post_type( $type ) : null;
			if ( $entity ) { $args = array( 'provider' => $entity['provider'], 'entity' => $entity['id'] ); }
			wp_safe_redirect( self::workspace_url( $args ) );
			exit;
		}

		public function render_page() {
			echo '<div class="wrap"><p><a href="' . esc_url( self::workspace_url() ) . '">' . esc_html__( 'Open Data Manager', 'nine-code-data' ) . '</a></p></div>';
		}

		/** Legacy schema shape, built from the provider registry. */
		public function schema( $schema = array() ) {
			$schema    = is_array( $schema ) ? $schema : array();
			$providers = array();
			$post_types = array();
			foreach ( NCD_Registry::providers() as $pid => $provider ) {
				$providers[ $pid ] = array(
					'id'       => $pid,
					'label'    => $provider['label'],
					'version'  => $provider['version'],
					'adapter'  => $provider['adapter'],
					'entities' => array_keys( $provider['entities'] ),
				);
				foreach ( $provider['entities'] as $entity ) {
					if ( 'post' !== $entity['kind'] || ! post_type_exists( $entity['object_type'] ) ) { continue; }
					$fields = array();
					foreach ( $entity['fields'] as $key => $f ) {
						$fields[ $key ] = array(
							'key'      => $key,
							'label'    => $f['label'],
							'type'     => $f['type'],
							'single'   => $f['single'],
							'writable' => ! $f['protected'] && $f['writable'],
							'source'   => $f['storage'],
						);
					}
					$post_types[ $entity['object_type'] ] = array(
						'key'        => $entity['object_type'],
						'label'      => $entity['label'],
						'provider'   => $pid,
						'entity'     => $entity['id'],
						'taxonomies' => array_values( get_object_taxonomies( $entity['object_type'], 'names' ) ),
						'fields'     => $fields,
					);
				}
			}
			return array_merge( $schema, array(
				'format'       => 'ninecode-universal-data-schema',
				'version'      => self::VERSION,
				'contract'     => NCD_Registry::CONTRACT,
				'generated_at' => current_time( 'c' ),
				'providers'    => $providers,
				'post_types'   => $post_types,
			) );
		}

		private function package( $post_type, array $ids = array() ) {
			$entity = self::entity_for_post_type( $post_type );
			if ( ! $entity ) { return new WP_Error( 'ncd_unknown_type', __( 'Unknown or unsupported post type.', 'nine-code-data' ), array( 'status' => 404 ) ); }
			if ( ! NCD_Service::can( 'read', $entity ) ) { return new WP_Error( 'ncd_forbidden', __( 'You cannot export this data.', 'nine-code-data' ), array( 'status' => 403 ) ); }
			$args = array( 'status' => 'any' );
			if ( $ids ) { $args['ids'] = array_map( 'absint', $ids ); }
			$records = NCD_Exchange::export_records( $entity, $args );
			if ( is_wp_error( $records ) ) { return $records; }
			return NCD_Exchange::package( $entity, $records );
		}

		public function handle_export() {
			$this->require_cap();
			check_admin_referer( 'ninecode_universal_export' );
			$type    = sanitize_key( wp_unslash( $_POST['post_type'] ?? '' ) );
			$package = $this->package( $type );
			if ( is_wp_error( $package ) ) { wp_die( esc_html( $package->get_error_message() ) ); }
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( $type . '-ninecode-data.json' ) );
			echo wp_json_encode( $package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			exit;
		}

		/** Old form posts are sent to the workspace, where imports are staged, previewed and applied selectively. */
		public function handle_import() {
			$this->require_cap();
			check_admin_referer( 'ninecode_universal_import' );
			wp_safe_redirect( self::workspace_url( array( 'view' => 'import' ) ) );
			exit;
		}

		/** Preview-only: returns the change plan for a legacy or v2 package without writing anything. */
		private function preview( $package ) {
			if ( ! is_array( $package ) ) { return new WP_Error( 'ncd_invalid_package', __( 'Invalid package.', 'nine-code-data' ), array( 'status' => 400 ) ); }
			$type   = sanitize_key( $package['scope']['post_type'] ?? ( $package['records'][0]['post_type'] ?? '' ) );
			$entity = isset( $package['scope']['provider'], $package['scope']['entity'] )
				? NCD_Registry::entity( $package['scope']['provider'], $package['scope']['entity'] )
				: self::entity_for_post_type( $type );
			if ( ! $entity ) { return new WP_Error( 'ncd_unknown_type', __( 'Unknown or unsupported data type.', 'nine-code-data' ), array( 'status' => 400 ) ); }
			$normalized = NCD_Exchange::normalize_package( $package, $entity );
			if ( is_wp_error( $normalized ) ) { return $normalized; }
			$plan = NCD_Exchange::preview( $entity, $normalized, 0, 200, array() );
			$plan['preview_only'] = true;
			$plan['message']      = __( 'This legacy endpoint never writes. Stage, preview and apply through ninecode-data/v1/import/{provider}/{entity}.', 'nine-code-data' );
			return $plan;
		}

		private function require_cap() {
			if ( ! NCD_Service::can_use() ) { wp_die( esc_html__( 'You do not have permission to use Data Manager.', 'nine-code-data' ) ); }
		}

		public function rest_api() {
			register_rest_route( 'ninecode/v1', '/data/schema', array(
				'methods'             => 'GET',
				'permission_callback' => array( $this, 'rest_permission' ),
				'callback'            => function () { return rest_ensure_response( $this->schema() ); },
			) );
			register_rest_route( 'ninecode/v1', '/data/export/(?P<post_type>[a-zA-Z0-9_-]+)', array(
				'methods'             => 'GET',
				'permission_callback' => array( $this, 'rest_permission' ),
				'callback'            => function ( $request ) { return rest_ensure_response( $this->package( $request['post_type'] ) ); },
			) );
			register_rest_route( 'ninecode/v1', '/data/import', array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'rest_permission' ),
				'callback'            => function ( $request ) { return rest_ensure_response( $this->preview( $request->get_json_params() ) ); },
			) );
		}

		public function rest_permission() { return is_user_logged_in() && NCD_Service::can_use(); }
	}
}
NineCode_Universal_Data_Manager::instance();
