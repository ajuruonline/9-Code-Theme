<?php
/**
 * Nine Code Data: universal data API (provider contract v1, generic discovery, service, exchange,
 * REST, history) and the Data Workspace admin screen.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

foreach ( array( 'registry', 'policy', 'types', 'store', 'generic-provider', 'history', 'service', 'sheet', 'exchange', 'rest', 'admin' ) as $ncd_part ) {
	require_once __DIR__ . '/class-ncd-' . $ncd_part . '.php';
}
unset( $ncd_part );
require_once __DIR__ . '/adapters/bootstrap.php';

if ( ! function_exists( 'ninecode_data_register_provider' ) ) {
	/**
	 * Public contract entry point. Call inside the `ninecode_data_register_providers` action.
	 *
	 * @param string $id   Provider slug.
	 * @param array  $args Provider definition (see docs/DATA-PROVIDER-CONTRACT.md).
	 * @return true|WP_Error
	 */
	function ninecode_data_register_provider( $id, array $args ) {
		return NCD_Registry::register( $id, $args );
	}
}

if ( ! function_exists( 'ninecode_data_contract_version' ) ) {
	/** Highest provider contract version this Nine Code Data understands. */
	function ninecode_data_contract_version() { return NCD_Registry::CONTRACT; }
}

add_action( 'rest_api_init', array( 'NCD_REST', 'register' ) );
add_action( 'admin_init', array( 'NCD_History', 'maybe_install' ) );
NCD_Admin::instance();
