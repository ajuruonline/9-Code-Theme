<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) { return; }

class NineCM_Elementor_Widget extends \Elementor\Widget_Base {
    public function get_name() { return 'nine-post-of-contents'; }
    public function get_title() { return '9 Post of Contents'; }
    public function get_icon() { return 'eicon-table-of-contents'; }
    public function get_categories() { return array( 'nine-widgets' ); }
    public function get_keywords() { return array( 'category', 'pages', 'posts', 'contents', 'hierarchy', 'directory', 'planning' ); }
    public function get_style_depends() { return array( 'ninecm-frontend' ); }
    public function get_script_depends() { return array( 'ninecm-frontend' ); }

    protected function register_controls() {
        $taxes = array();
        foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $t ) {
            if ( $t->public ) { $taxes[ $t->name ] = $t->labels->name; }
        }
        $post_types = array();
        foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $p ) {
            if ( in_array( $p->name, array( 'attachment', 'nav_menu_item' ), true ) ) { continue; }
            $post_types[ $p->name ] = $p->labels->name;
        }

        $this->start_controls_section( 'content', array( 'label' => 'Content' ) );
        $this->add_control( 'taxonomy', array( 'label' => 'Taxonomy / tag system', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => $taxes, 'default' => 'category' ) );
        $this->add_control( 'root', array(
            'label' => 'Root term ID',
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 0,
            'min' => 0,
            'description' => '0 displays the complete hierarchy. Use a category ID to display only one branch; this avoids loading thousands of categories into the Elementor control itself.',
        ) );
        $this->add_control( 'depth', array( 'label' => 'Hierarchy depth (0 = all)', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 0, 'min' => 0, 'max' => 12 ) );
        $this->add_control( 'post_types', array(
            'label' => 'Content types',
            'type' => \Elementor\Controls_Manager::SELECT2,
            'multiple' => true,
            'options' => $post_types,
            'default' => array( 'page', 'post' ),
            'description' => 'Only content types actually registered to the selected taxonomy can render. Incompatible types are safely ignored.',
        ) );
        $this->add_control( 'hide_empty', array( 'label' => 'Hide empty categories', 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ) );
        $this->add_control( 'posts_per_category', array( 'label' => 'Items/category (0 = all)', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 0, 'min' => 0 ) );
        $this->add_control( 'show_counts', array( 'label' => 'Show item counts', 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => '' ) );
        $this->add_control( 'deduplicate', array( 'label' => 'Show each page/post only once', 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => '' ) );
        $this->end_controls_section();

        $this->start_controls_section( 'interaction', array( 'label' => 'Search & Dropdowns' ) );
        $this->add_control( 'collapsible', array( 'label' => 'Dropdown / collapsible', 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ) );
        $this->add_control( 'initially_open', array( 'label' => 'Start open', 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'condition' => array( 'collapsible' => 'yes' ) ) );
        $this->add_control( 'search', array( 'label' => 'Search', 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ) );
        $this->add_control( 'filter', array( 'label' => 'Category filter', 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ) );
        $this->add_control( 'empty_message', array( 'label' => 'Empty/search message', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'No matching content found.' ) );
        $this->end_controls_section();

        $this->start_controls_section( 'ordering', array( 'label' => 'Ordering & Markers' ) );
        $this->add_control( 'term_orderby', array( 'label' => 'Category order by', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => array( 'name' => 'Name', 'slug' => 'Slug', 'term_id' => 'ID', 'count' => 'Published count', 'ninecm_order' => '9 Manual order' ), 'default' => 'name' ) );
        $this->add_control( 'term_order', array( 'label' => 'Category order', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => array( 'ASC' => 'Ascending', 'DESC' => 'Descending' ), 'default' => 'ASC' ) );
        $this->add_control( 'post_orderby', array( 'label' => 'Page/post order by', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => array( 'title' => 'Title', 'date' => 'Published date', 'modified' => 'Modified date', 'menu_order' => 'Menu order', 'ID' => 'ID' ), 'default' => 'title' ) );
        $this->add_control( 'post_order', array( 'label' => 'Page/post order', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => array( 'ASC' => 'Ascending', 'DESC' => 'Descending' ), 'default' => 'ASC' ) );
        $this->add_control( 'marker', array( 'label' => 'Marker', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => array( 'number' => 'Numbering', 'bullet' => 'Bullets', 'icon' => 'Text icon', 'none' => 'None' ), 'default' => 'number' ) );
        $this->add_control( 'icon', array( 'label' => 'Text icon', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '›', 'condition' => array( 'marker' => 'icon' ) ) );
        $this->add_control( 'style_preset', array( 'label' => 'Style preset', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => array( 'clean' => 'Clean', 'compact' => 'Compact', 'bordered' => 'Bordered', 'academic' => 'Academic', 'minimal' => 'Minimal' ), 'default' => 'clean' ) );
        $this->end_controls_section();

        $this->start_controls_section( 'style', array( 'label' => 'Style', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
        $this->add_control( 'heading_color', array( 'label' => 'Category colour', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ninecm-term-title' => 'color: {{VALUE}}' ) ) );
        $this->add_control( 'link_color', array( 'label' => 'Page/post link colour', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ninecm-post a' => 'color: {{VALUE}}' ) ) );
        $this->add_control( 'muted_color', array( 'label' => 'Marker/count colour', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ninecm-marker, {{WRAPPER}} .ninecm-count' => 'color: {{VALUE}}' ) ) );
        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'category_typography', 'selector' => '{{WRAPPER}} .ninecm-term-title' ) );
        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'post_typography', 'selector' => '{{WRAPPER}} .ninecm-post a' ) );
        $this->add_responsive_control( 'indent', array( 'label' => 'Hierarchy indent', 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ), 'selectors' => array( '{{WRAPPER}} .ninecm-term-body' => 'padding-left: {{SIZE}}{{UNIT}};' ) ) );
        $this->add_responsive_control( 'item_spacing', array( 'label' => 'Page/post vertical spacing', 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ), 'selectors' => array( '{{WRAPPER}} .ninecm-post a' => 'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}};' ) ) );
        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        $html = NineCM_Renderer::render( array(
            'taxonomy' => $s['taxonomy'] ?? 'category',
            'root' => absint( $s['root'] ?? 0 ),
            'depth' => absint( $s['depth'] ?? 0 ),
            'postTypes' => (array) ( $s['post_types'] ?? array( 'page', 'post' ) ),
            'hideEmpty' => 'yes' === ( $s['hide_empty'] ?? '' ),
            'postsPerCategory' => absint( $s['posts_per_category'] ?? 0 ),
            'showCounts' => 'yes' === ( $s['show_counts'] ?? '' ),
            'deduplicate' => 'yes' === ( $s['deduplicate'] ?? '' ),
            'collapsible' => 'yes' === ( $s['collapsible'] ?? '' ),
            'initiallyOpen' => 'yes' === ( $s['initially_open'] ?? '' ),
            'showSearch' => 'yes' === ( $s['search'] ?? '' ),
            'showFilter' => 'yes' === ( $s['filter'] ?? '' ),
            'emptyMessage' => $s['empty_message'] ?? 'No matching content found.',
            'termOrderby' => $s['term_orderby'] ?? 'name',
            'termOrder' => $s['term_order'] ?? 'ASC',
            'postOrderby' => $s['post_orderby'] ?? 'title',
            'postOrder' => $s['post_order'] ?? 'ASC',
            'marker' => $s['marker'] ?? 'number',
            'icon' => $s['icon'] ?? '›',
            'style' => $s['style_preset'] ?? 'clean',
        ) );
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- NineCM_Renderer escapes every value it prints.
    }
}
