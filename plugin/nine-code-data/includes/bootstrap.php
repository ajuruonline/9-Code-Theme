<?php
/**
 * Nine Code Data bootstrap. Loaded from nine-code-data.php only after the
 * old-plugin guard has passed (top-level declarations here are hoisted by PHP,
 * so they must not live in the main plugin file).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }


/**
 * During migration, an older standalone engine may still be active. In that
 * case use that live engine for this request instead of loading the bundled
 * copy and causing duplicate classes. Once the standalone is deactivated the
 * bundled engine takes over automatically on the next request.
 */
function nine55_ultron_external_plugin_active( $main_file ) {
    $wanted_basename = basename( $main_file );
    $active = (array) get_option( 'active_plugins', array() );
    foreach ( $active as $plugin_file ) {
        if ( $plugin_file === $main_file || basename( $plugin_file ) === $wanted_basename ) { return true; }
    }
    if ( is_multisite() ) {
        $network = (array) get_site_option( 'active_sitewide_plugins', array() );
        foreach ( array_keys( $network ) as $plugin_file ) {
            if ( $plugin_file === $main_file || basename( $plugin_file ) === $wanted_basename ) { return true; }
        }
    }
    return false;
}


/**
 * The Theme owns the visible logged-in front-end control rail when available.
 * Data Manager keeps its workspaces loaded but suppresses its legacy floating
 * Post Editor / Category Manager buttons so users see one launcher only.
 */
function nine10_data_theme_launcher_available() {
    $available = class_exists( 'N9BE_Quick_Actions', false );
    return (bool) apply_filters( 'nine10_data_theme_launcher_available', $available );
}


/** Additive suite contract: Data Manager edits only WordPress/provider-authorized data. */
function nine10_data_component_contract( $contracts ) {
    $contracts = is_array( $contracts ) ? $contracts : array();
    $contracts['9-data-manager'] = array(
        'id' => '9-data-manager',
        'version' => NINE55_ULTRON_DATA_VERSION,
        'role' => 'editorial-data-workspace',
        'foreign_cpt_policy' => 'discover-dont-own',
        'private_meta_policy' => 'readonly-unless-provider-allows',
        'acf_policy' => 'provider-api',
        'elementor_policy' => 'presentation-owner',
        'taxonomy_policy' => 'registered-object-taxonomies-only',
    );
    return $contracts;
}
add_filter( 'ninecodepress_component_contracts', 'nine10_data_component_contract', 30 );

/**
 * Default editing boundary: native posts/pages plus public provider-owned CPTs.
 * Internal builder/configuration post types stay with Elementor, ACF, Frontend
 * Admin and other owning plugins unless they explicitly opt in through the
 * filter.
 */
function nine10_data_filter_internal_post_types( $post_types, $context = '' ) {
    if ( ! is_array( $post_types ) ) { return array(); }
    foreach ( $post_types as $key => $object ) {
        $name = is_object( $object ) && isset( $object->name ) ? (string) $object->name : (string) $key;
        if ( in_array( $name, array( 'post', 'page' ), true ) ) { continue; }
        $public = is_object( $object ) && ( ! empty( $object->public ) || ! empty( $object->publicly_queryable ) );
        $include = (bool) apply_filters( 'nine10_data_include_internal_post_type', $public, $name, $object, $context );
        if ( ! $include ) { unset( $post_types[ $key ] ); }
    }
    return $post_types;
}
add_filter( 'nine10_data_editable_post_types', 'nine10_data_filter_internal_post_types', 5, 2 );

$nine55_modules = array(
    'data' => array(
        'external' => 'ninecode-acf-data-engine/ninecode-acf-data-engine.php',
        'class'    => 'NineCode_ACF_Data_Engine',
        'file'     => NINE55_ULTRON_DATA_DIR . 'modules/data/ninecode-acf-data-engine.php',
    ),
    'post' => array(
        'external' => '9-post-manager/9-post-manager.php',
        'class'    => 'Nine_Post_Manager',
        'file'     => NINE55_ULTRON_DATA_DIR . 'modules/post/9-post-manager.php',
    ),
    'category' => array(
        'external' => 'nine-category-manager/nine-category-manager.php',
        'class'    => 'NineCM_Core',
        'file'     => NINE55_ULTRON_DATA_DIR . 'modules/category/nine-category-manager.php',
    ),
    'ai' => array(
        'external' => 'nine-ai-manager/nine-ai-manager.php',
        'class'    => 'Nine_AI_Manager',
        'file'     => NINE55_ULTRON_DATA_DIR . 'modules/ai/nine-ai-manager.php',
    ),
);

foreach ( $nine55_modules as $module ) {
    if ( ! class_exists( $module['class'], false ) && ! nine55_ultron_external_plugin_active( $module['external'] ) && is_file( $module['file'] ) ) {
        require_once $module['file'];
    }
}
unset( $nine55_modules, $module );

require_once NINE55_ULTRON_DATA_DIR . 'includes/class-nine10-form.php';
require_once NINE55_ULTRON_DATA_DIR . 'includes/class-nine10-data-backup.php';
require_once NINE55_ULTRON_DATA_DIR . 'includes/class-nine55-ultron-data.php';
Nine10_Form::instance();
Nine10_Data_Backup::instance();
Nine55_Ultron_Data::instance();

function nine55_ultron_data_activate() {
    if ( class_exists( 'NineCode_ACF_Data_Engine' ) && method_exists( 'NineCode_ACF_Data_Engine', 'activate' ) ) {
        NineCode_ACF_Data_Engine::activate();
    }
    if ( class_exists( 'NineCM_Core' ) && method_exists( 'NineCM_Core', 'activate' ) ) {
        NineCM_Core::activate();
    }
    if ( class_exists( 'Nine_AI_Manager' ) && method_exists( 'Nine_AI_Manager', 'activate' ) ) {
        Nine_AI_Manager::activate();
    }
    update_option( 'nine55_ultron_data_version', NINE55_ULTRON_DATA_VERSION, false );
    update_option( 'nine55_ultron_data_sources', array(
        'data' => defined( 'NINECODE_ACF_DATA_ENGINE_VERSION' ) ? NINECODE_ACF_DATA_ENGINE_VERSION : 'external',
        'post' => defined( 'NPM9_VERSION' ) ? NPM9_VERSION : 'external',
        'category' => defined( 'NINECM_VERSION' ) ? NINECM_VERSION : 'external',
        'ai' => defined( 'NINE_AI_MANAGER_VERSION' ) ? NINE_AI_MANAGER_VERSION : 'external',
    ), false );
}
register_activation_hook( NINE55_ULTRON_DATA_FILE, 'nine55_ultron_data_activate' );

function nine55_ultron_data_deactivate() {
    if ( class_exists( 'NineCode_ACF_Data_Engine' ) && method_exists( 'NineCode_ACF_Data_Engine', 'deactivate' ) ) {
        NineCode_ACF_Data_Engine::deactivate();
    }
    if ( class_exists( 'Nine_AI_Manager' ) && method_exists( 'Nine_AI_Manager', 'deactivate' ) ) {
        Nine_AI_Manager::deactivate();
    }
}
register_deactivation_hook( NINE55_ULTRON_DATA_FILE, 'nine55_ultron_data_deactivate' );
