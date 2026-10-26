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
