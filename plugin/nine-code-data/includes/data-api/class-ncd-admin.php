<?php
/**
 * Data Workspace admin screen (mobile-first single-page app on top of the REST API).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class NCD_Admin {
	const SLUG = 'ninecode-data-workspace';

	private static $instance;

	public static function instance() { return self::$instance ?: ( self::$instance = new self() ); }

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 6 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	private function cap() {
		return current_user_can( NCD_Service::CAP ) ? NCD_Service::CAP : 'manage_options';
	}

	public function menu() {
		add_submenu_page( 'nine10-data-edition', __( 'Data Workspace', 'nine-code-data' ), __( 'Data Workspace', 'nine-code-data' ), $this->cap(), self::SLUG, array( $this, 'render' ), 0 );
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) { return; }
		wp_enqueue_media();
		wp_enqueue_editor();
		wp_enqueue_style( 'ncd-workspace', NINE55_ULTRON_DATA_URL . 'assets/data-workspace.css', array( 'dashicons' ), NINE55_ULTRON_DATA_VERSION );
		wp_enqueue_script( 'ncd-workspace', NINE55_ULTRON_DATA_URL . 'assets/data-workspace.js', array( 'wp-api-fetch' ), NINE55_ULTRON_DATA_VERSION, true );
		wp_localize_script( 'ncd-workspace', 'NCDWorkspace', array(
			'root'     => esc_url_raw( rest_url( NCD_REST::NS ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'isAdmin'  => current_user_can( 'manage_options' ),
			'contract' => NCD_Registry::CONTRACT,
			'version'  => NINE55_ULTRON_DATA_VERSION,
			'initial'  => array(
				'provider' => isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : '',
				'entity'   => isset( $_GET['entity'] ) ? sanitize_key( wp_unslash( $_GET['entity'] ) ) : '',
				'id'       => isset( $_GET['record'] ) ? absint( $_GET['record'] ) : 0,
			),
		) );
	}

	public function render() {
		if ( ! NCD_Service::can_use() ) { wp_die( esc_html__( 'You do not have permission to use Data Manager.', 'nine-code-data' ) ); }
		echo '<div class="wrap ncd-wrap"><h1 class="ncd-title">' . esc_html__( 'Data Workspace', 'nine-code-data' ) . '</h1>';
		echo '<p class="ncd-lede">' . esc_html__( 'Find, edit, export and import data from every Nine Code app and plugin. Changes are validated by the plugin that owns the data, recorded in History and can be undone.', 'nine-code-data' ) . '</p>';
		echo '<div id="ncd-app" class="ncd-app" aria-live="polite"><p class="ncd-loading">' . esc_html__( 'Loading…', 'nine-code-data' ) . '</p></div>';
		echo '<noscript><p>' . esc_html__( 'Data Workspace needs JavaScript.', 'nine-code-data' ) . '</p></noscript></div>';
	}
}
