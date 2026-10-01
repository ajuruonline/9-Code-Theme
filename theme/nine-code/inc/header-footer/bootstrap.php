<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'NCU_HF_VERSION', '9.2.0' );
define( 'NCU_HF_HEADER_VERSION', '8.14.0' );
define( 'NCU_HF_FOOTER_VERSION', '1.5.0' );
define( 'NCU_HF_HEADER_OPTION', 'n9lh8_settings' );
define( 'NCU_HF_FOOTER_OPTION', 'n9f_settings' );

function ncu_theme_header_footer_standalone_plugin_file() { return 'elearning-click-header-footer/e-learning-click-header-footer.php'; }
function ncu_theme_header_footer_standalone_loaded() { return defined( 'ELHF_VERSION' ) || class_exists( 'ELHF_Plugin', false ); }

/**
 * 14.1.14 one-time master-state repair.
 * OFF is authoritative across the Theme module, preserved standalone options,
 * and legacy Core flags. Administrators may explicitly enable either surface
 * after this migration; the marker prevents future requests from resetting it.
 */
function ncu_theme_force_hf_master_off() {
    if ( get_option( 'ncu_hf_master_off_migration_14114', false ) ) return;
    $header = get_option( 'n9lh8_settings', [] );
    $header = is_array( $header ) ? $header : [];
    $header['enabled'] = '';
    update_option( 'n9lh8_settings', $header, false );
    $footer = get_option( 'n9f_settings', [] );
    $footer = is_array( $footer ) ? $footer : [];
    $footer['enabled'] = '';
    update_option( 'n9f_settings', $footer, false );
    $core = get_option( 'ncu_settings', [] );
    if ( is_array( $core ) && $core ) {
        $core['header_enabled'] = 0;
        $core['footer_enabled'] = 0;
        update_option( 'ncu_settings', $core, false );
    }
    update_option( 'ncu_hf_master_off_migration_14114', 1, false );
}
ncu_theme_force_hf_master_off();

function ncu_theme_retire_standalone_header_footer() {
    if ( ! is_admin() || ! current_user_can( 'activate_plugins' ) ) { return; }
    if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'deactivate_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
    $plugin = ncu_theme_header_footer_standalone_plugin_file();
    if ( function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $plugin ) ) {
        update_option( 'ncu_header_footer_retirement_status', 'network-active-needs-network-admin', false );
        return;
    }
    if ( function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin ) ) {
        deactivate_plugins( $plugin, true );
        update_option( 'ncu_header_footer_retirement_status', 'standalone-deactivated-settings-preserved', false );
        set_transient( 'ncu_header_footer_retired_notice', 1, DAY_IN_SECONDS );
    }
}
add_action( 'admin_init', 'ncu_theme_retire_standalone_header_footer', 1 );
add_action( 'after_switch_theme', 'ncu_theme_retire_standalone_header_footer', 1 );

function ncu_theme_header_footer_retired_notice() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    if ( get_transient( 'ncu_header_footer_retired_notice' ) ) {
        delete_transient( 'ncu_header_footer_retired_notice' );
        echo '<div class="notice notice-success is-dismissible"><p><strong>Nine Code:</strong> standalone e-learning.click Header &amp; Footer was deactivated. Existing Header/Footer settings were preserved and the Theme now owns both surfaces.</p></div>';
    }
    if ( 'network-active-needs-network-admin' === get_option( 'ncu_header_footer_retirement_status' ) ) {
        echo '<div class="notice notice-warning"><p><strong>Nine Code:</strong> the standalone Header &amp; Footer plugin is network-active. A network administrator must deactivate it to complete the one-owner migration.</p></div>';
    }
}
add_action( 'admin_notices', 'ncu_theme_header_footer_retired_notice' );

/* Plugin code loads before themes. If the standalone plugin is still active in
 * this request, do not load duplicate classes; admin_init retires it and the
 * theme runtime becomes authoritative on the next request. */
if ( ncu_theme_header_footer_standalone_loaded() ) { return; }

if ( ! defined( 'ELHF_VERSION' ) ) define( 'ELHF_VERSION', NCU_HF_VERSION );
if ( ! defined( 'ELHF_H_VERSION' ) ) define( 'ELHF_H_VERSION', NCU_HF_HEADER_VERSION );
if ( ! defined( 'ELHF_F_VERSION' ) ) define( 'ELHF_F_VERSION', NCU_HF_FOOTER_VERSION );
if ( ! defined( 'ELHF_DIR' ) ) define( 'ELHF_DIR', NCU_THEME_DIR . '/inc/header-footer/' );
if ( ! defined( 'ELHF_URL' ) ) define( 'ELHF_URL', NCU_THEME_URI . '/inc/header-footer/' );
if ( ! defined( 'ELHF_H_DIR' ) ) define( 'ELHF_H_DIR', ELHF_DIR . 'header/' );
if ( ! defined( 'ELHF_H_URL' ) ) define( 'ELHF_H_URL', ELHF_URL . 'header/' );
if ( ! defined( 'ELHF_F_DIR' ) ) define( 'ELHF_F_DIR', ELHF_DIR . 'footer/' );
if ( ! defined( 'ELHF_F_URL' ) ) define( 'ELHF_F_URL', ELHF_URL . 'footer/' );

require_once ELHF_DIR . 'includes/class-elhh-design.php';
require_once ELHF_H_DIR . 'includes/class-settings.php';
require_once ELHF_F_DIR . 'includes/class-n9f-settings.php';

final class NCU_Theme_Header_Footer {
    private static $instance;
    public static function instance(){ return self::$instance ?: ( self::$instance = new self() ); }
    private function __construct(){
        add_action( 'after_setup_theme', [ $this, 'boot' ], 40 );
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ], 4 );
        add_action( 'widgets_init', [ $this, 'register_widget_areas' ] );
        add_action( 'elementor/widgets/register', [ $this, 'register_elementor_widget' ] );
        add_action( 'elementor/elements/categories_registered', [ $this, 'register_elementor_category' ] );
        add_filter( 'ncu_external_header_active', '__return_true', 100 );
        add_filter( 'ncu_render_builtin_header', '__return_false', 100 );
        add_filter( 'ncu_external_footer_active', '__return_true', 100 );
        add_filter( 'ncu_render_builtin_footer', '__return_false', 100 );
    }
    public function boot(){
        ELHF_H_Settings::instance(); ELHF_F_Settings::instance();
        require_once ELHF_H_DIR . 'includes/class-author.php';
        require_once ELHF_H_DIR . 'includes/class-contact.php';
        require_once ELHF_H_DIR . 'includes/class-renderer.php';
        require_once ELHF_F_DIR . 'includes/class-n9f-renderer.php';
        require_once ELHF_F_DIR . 'includes/class-n9f-sitewide.php';
        ELHF_H_Author::instance(); ELHF_H_Contact::instance(); ELHF_H_Renderer::instance();
        ELHF_F_Renderer::instance(); ELHF_F_Sitewide::instance();
    }
    public function register_assets(){
        wp_register_style( 'n9f-frontend', ELHF_F_URL . 'assets/css/frontend.css', [], ELHF_F_VERSION );
        wp_register_script( 'n9f-frontend', ELHF_F_URL . 'assets/js/frontend.js', [], ELHF_F_VERSION, true );
        wp_register_style( 'elhh-header-compat', ELHF_H_URL . 'assets/css/el-compat.css', [], ELHF_VERSION );
        wp_register_script( 'elhh-header-compat', ELHF_H_URL . 'assets/js/el-compat.js', [], ELHF_VERSION, true );
        wp_register_style( 'elhh-unified', ELHF_URL . 'assets/frontend-unified.css', [], ELHF_VERSION );
        $header_on = class_exists( 'ELHF_H_Settings', false ) && ELHF_H_Settings::is_enabled();
        $footer_on = class_exists( 'ELHF_F_Settings', false ) && ELHF_F_Settings::is_enabled();
        if ( $header_on ) { wp_enqueue_style( 'elhh-header-compat' ); wp_enqueue_script( 'elhh-header-compat' ); }
        if ( $header_on || $footer_on ) wp_enqueue_style( 'elhh-unified' );
    }
    public function register_widget_areas(){
        for ( $i = 1; $i <= 4; $i++ ) register_sidebar( [
            'name'=>sprintf( '9 Code Theme Footer Slot %d', $i ), 'id'=>'n9f-footer-slot-'.$i,
            'description'=>sprintf( 'Theme-owned footer module %d.', $i ),
            'before_widget'=>'<div id="%1$s" class="widget n9f-footer-widget %2$s">', 'after_widget'=>'</div>',
            'before_title'=>'<h4 class="n9f-footer-widget__title">', 'after_title'=>'</h4>',
        ] );
    }
    public function register_elementor_category( $manager ){
        if ( is_object($manager) && method_exists($manager,'add_category') ) $manager->add_category( 'nine-footer', [ 'title'=>'9 Code Theme Header & Footer', 'icon'=>'fa fa-plug' ] );
    }
    public function register_elementor_widget( $manager ){
        if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) return;
        require_once ELHF_F_DIR . 'includes/widget-n9f-global-footer.php';
        if ( is_object($manager) && method_exists($manager,'register') ) $manager->register( new ELHF_F_Widget_Global_Footer() );
    }
}
NCU_Theme_Header_Footer::instance();

function ncu_theme_header_footer_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $surface = isset($_GET['surface']) ? sanitize_key(wp_unslash($_GET['surface'])) : 'header';
    if ( ! in_array($surface,['header','footer'],true) ) $surface='header';
    $state=ELHF_Design::state();
    wp_enqueue_style( 'elhh-admin', ELHF_URL . 'assets/admin-unified.css', [], ELHF_VERSION );
    wp_enqueue_script( 'elhh-admin', ELHF_URL . 'assets/admin-unified.js', [], ELHF_VERSION, true );
    ?>
    <div class="wrap elhh-admin" data-elhh-admin data-active-surface="<?php echo esc_attr($surface); ?>">
        <header class="elhh-admin__hero"><div><span class="elhh-admin__kicker">NINE CODE · SITE SHELL</span><h1>Header &amp; Footer</h1><p>Header and Footer are optional Theme surfaces and start OFF. Turn on only the edge you explicitly want the Theme to render.</p></div><div class="elhh-authority"><small>Default style authority</small><strong>Nine Code Skin</strong><span><?php echo esc_html(ucwords(str_replace('-',' ',ELHF_Design::style_slug()))); ?> · <?php echo esc_html(ELHF_Design::authority_label()); ?></span></div></header>
        <div class="elhh-toolbar" role="region" aria-label="Header and footer settings navigation"><div class="elhh-tabs" role="tablist"><button type="button" class="elhh-tab" data-elhh-tab="header" role="tab">Header</button><button type="button" class="elhh-tab" data-elhh-tab="footer" role="tab">Footer</button></div><label class="elhh-search"><span class="dashicons dashicons-search" aria-hidden="true"></span><span class="screen-reader-text">Search Header and Footer settings</span><input type="search" data-elhh-search placeholder="Find any setting…" autocomplete="off"><button type="button" data-elhh-clear aria-label="Clear search">×</button></label></div>
        <div class="notice notice-info inline elhh-runtime-note"><p><strong>Theme Skin is authoritative by default.</strong> Local Header/Footer colours are compatibility fallbacks. Change Popular Site or Skin under <strong>Nine Code → Style Authority</strong> to restyle both edges together.</p></div>
        <section class="elhh-surface" data-elhh-surface="header"><?php ELHF_H_Settings::instance()->page(); ?></section>
        <section class="elhh-surface" data-elhh-surface="footer"><?php ELHF_F_Settings::instance()->page(); ?></section>
    </div><?php
}
