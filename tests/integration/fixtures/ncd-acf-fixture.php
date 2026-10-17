<?php
/**
 * Plugin Name: NCD test fixture — ACF field group covering nested types
 * Description: Test-only. Registers a local ACF group with group/repeater/flexible/gallery/file/relationship/user/taxonomy/post object fields.
 */
add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) { return; }
	acf_add_local_field_group( array(
		'key'      => 'group_ncd_test',
		'title'    => 'NCD Test Fields',
		'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ),
		'fields'   => array(
			array( 'key' => 'field_ncd_subtitle', 'name' => 'ncd_subtitle', 'label' => 'Subtitle', 'type' => 'text', 'required' => 0 ),
			array( 'key' => 'field_ncd_rating', 'name' => 'ncd_rating', 'label' => 'Rating', 'type' => 'number', 'min' => 1, 'max' => 5 ),
			array( 'key' => 'field_ncd_color', 'name' => 'ncd_color', 'label' => 'Colour', 'type' => 'select', 'choices' => array( 'red' => 'Red', 'blue' => 'Blue' ) ),
			array( 'key' => 'field_ncd_featured', 'name' => 'ncd_featured', 'label' => 'Featured', 'type' => 'true_false' ),
			array( 'key' => 'field_ncd_api_password', 'name' => 'ncd_api_password', 'label' => 'API password', 'type' => 'password' ),
			array( 'key' => 'field_ncd_hero', 'name' => 'ncd_hero', 'label' => 'Hero', 'type' => 'group', 'sub_fields' => array(
				array( 'key' => 'field_ncd_hero_heading', 'name' => 'heading', 'label' => 'Heading', 'type' => 'text' ),
				array( 'key' => 'field_ncd_hero_image', 'name' => 'image', 'label' => 'Image', 'type' => 'image' ),
			) ),
			array( 'key' => 'field_ncd_speakers', 'name' => 'ncd_speakers', 'label' => 'Speakers', 'type' => 'repeater', 'max' => 5, 'sub_fields' => array(
				array( 'key' => 'field_ncd_speaker_name', 'name' => 'name', 'label' => 'Name', 'type' => 'text' ),
				array( 'key' => 'field_ncd_speaker_user', 'name' => 'user', 'label' => 'User', 'type' => 'user' ),
			) ),
			array( 'key' => 'field_ncd_blocks', 'name' => 'ncd_blocks', 'label' => 'Blocks', 'type' => 'flexible_content', 'layouts' => array(
				'layout_ncd_text'  => array( 'key' => 'layout_ncd_text', 'name' => 'text', 'label' => 'Text', 'sub_fields' => array(
					array( 'key' => 'field_ncd_block_body', 'name' => 'body', 'label' => 'Body', 'type' => 'textarea' ),
				) ),
				'layout_ncd_quote' => array( 'key' => 'layout_ncd_quote', 'name' => 'quote', 'label' => 'Quote', 'sub_fields' => array(
					array( 'key' => 'field_ncd_block_quote', 'name' => 'quote', 'label' => 'Quote', 'type' => 'text' ),
					array( 'key' => 'field_ncd_block_by', 'name' => 'by', 'label' => 'By', 'type' => 'text' ),
				) ),
			) ),
			array( 'key' => 'field_ncd_gallery', 'name' => 'ncd_gallery', 'label' => 'Gallery', 'type' => 'gallery' ),
			array( 'key' => 'field_ncd_file', 'name' => 'ncd_file', 'label' => 'File', 'type' => 'file' ),
			array( 'key' => 'field_ncd_related', 'name' => 'ncd_related', 'label' => 'Related', 'type' => 'relationship', 'post_type' => array( 'page' ) ),
			array( 'key' => 'field_ncd_owner', 'name' => 'ncd_owner', 'label' => 'Owner', 'type' => 'post_object', 'post_type' => array( 'page' ) ),
			array( 'key' => 'field_ncd_topics', 'name' => 'ncd_topics', 'label' => 'Topics', 'type' => 'taxonomy', 'taxonomy' => 'category', 'field_type' => 'multi_select', 'save_terms' => 0 ),
		),
	) );
} );
