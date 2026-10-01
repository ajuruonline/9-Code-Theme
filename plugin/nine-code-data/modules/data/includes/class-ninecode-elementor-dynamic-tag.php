<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class NineCode_Elementor_ACF_Field_Tag extends \Elementor\Core\DynamicTags\Tag {
    public function get_name(): string { return 'ninecode-acf-field'; }
    public function get_title(): string { return '9Code ACF Field'; }
    public function get_group(): array { return array( 'site' ); }
    public function get_categories(): array { return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ); }

    protected function register_controls(): void {
        $options = array( '' => 'Select ACF field' );
        if ( function_exists( 'acf_get_field_groups' ) ) {
            foreach ( acf_get_field_groups() as $group ) {
                foreach ( (array) acf_get_fields( $group ) as $field ) {
                    $this->collect_field_options( $field, $options, $group['title'] . ' — ' );
                }
            }
        }
        $this->add_control( 'field_key', array( 'label' => 'ACF field', 'type' => \Elementor\Controls_Manager::SELECT2, 'options' => $options ) );
    }

    private function collect_field_options( $field, &$options, $prefix = '' ) {
        if ( ! empty( $field['key'] ) ) { $options[ $field['key'] ] = $prefix . ( $field['label'] ?? $field['name'] ) . ' [' . ( $field['name'] ?? '' ) . ']'; }
        foreach ( (array) ( $field['sub_fields'] ?? array() ) as $sub ) { $this->collect_field_options( $sub, $options, $prefix . '› ' ); }
        foreach ( (array) ( $field['layouts'] ?? array() ) as $layout ) {
            foreach ( (array) ( $layout['sub_fields'] ?? array() ) as $sub ) { $this->collect_field_options( $sub, $options, $prefix . ( $layout['label'] ?? 'Layout' ) . ' › ' ); }
        }
    }

    public function render(): void {
        if ( ! function_exists( 'get_field' ) ) { return; }
        $key = $this->get_settings( 'field_key' );
        if ( ! $key ) { return; }
        $value = get_field( $key );
        echo esc_html( NineCode_ACF_Data_Engine::generic_value_to_text( $value ) );
    }
}
