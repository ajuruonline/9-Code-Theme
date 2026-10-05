<?php
/**
 * Bundled compatibility adapters for Nine Code apps that do not (yet) ship their own provider.
 * Each adapter only describes data and protection; validation stays with the owning plugin where it
 * exposes an API, and every adapter is skipped when its plugin is inactive or registers itself.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

foreach ( (array) glob( __DIR__ . '/class-ncd-adapter-*.php' ) as $ncd_adapter ) { require_once $ncd_adapter; }
unset( $ncd_adapter );
