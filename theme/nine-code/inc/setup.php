<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'after_setup_theme', 'ncu_theme_setup' );
function ncu_theme_setup() {
    load_theme_textdomain( 'nine-code-ultra', NCU_THEME_DIR . '/languages' );
    add_theme_support( 'automatic-feed-links' );
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'custom-logo', array( 'height' => 96, 'width' => 96, 'flex-height' => true, 'flex-width' => true ) );
    add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'align-wide' );
    add_theme_support( 'wp-block-styles' );
    add_theme_support( 'editor-styles' );
    add_editor_style( 'assets/css/editor.css' );
    add_theme_support( 'customize-selective-refresh-widgets' );

    register_nav_menus( array(
        'primary' => __( 'Primary Menu', 'nine-code' ),
        'quick'   => __( 'Quick Links', 'nine-code' ),
        'footer'  => __( 'Footer Menu', 'nine-code' ),
    ) );
}

add_action( 'widgets_init', 'ncu_widgets_init' );
function ncu_widgets_init() {
    register_sidebar( array(
        'name'          => __( 'Sidebar', 'nine-code' ),
        'id'            => 'sidebar-1',
        'description'   => __( 'Optional sidebar for native site templates.', 'nine-code' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ) );
}

add_action( 'init', 'ncu_register_block_designs' );
function ncu_register_block_designs() {
    if ( function_exists( 'register_block_pattern_category' ) ) {
        register_block_pattern_category( 'nine-code-ultra', array( 'label' => __( 'Nine Code', 'nine-code' ) ) );
    }
    if ( function_exists( 'register_block_style' ) ) {
        register_block_style( 'core/group', array( 'name' => 'ncu-card', 'label' => __( '9code Card', 'nine-code' ), 'inline_style' => '.wp-block-group.is-style-ncu-card{border:1px solid var(--ncu-border);border-radius:var(--ncu-radius);padding:clamp(16px,3vw,28px);background:var(--ncu-surface)}' ) );
        register_block_style( 'core/details', array( 'name' => 'ncu-accordion', 'label' => __( '9code Accordion', 'nine-code' ), 'inline_style' => '.wp-block-details.is-style-ncu-accordion{border:1px solid var(--ncu-border);border-radius:12px;padding:14px 16px}.wp-block-details.is-style-ncu-accordion summary{font-weight:750}' ) );
        register_block_style( 'core/list', array( 'name' => 'ncu-clean-list', 'label' => __( '9code Clean List', 'nine-code' ), 'inline_style' => '.wp-block-list.is-style-ncu-clean-list{padding-left:1.25em}.wp-block-list.is-style-ncu-clean-list li{margin:.4em 0}' ) );
    }
}

/* Keep the inserter focused on locally available, deterministic patterns. */
add_filter( 'should_load_remote_block_patterns', '__return_false' );
