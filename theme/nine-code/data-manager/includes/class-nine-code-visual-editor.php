<?php
/**
 * Visual (WYSIWYG) content editor support for the Post Editor, and the "Form" block.
 *
 * - enqueue(): loads WordPress's bundled block-editor packages for the Post Editor's content tab.
 * - The nine-code-data/form block is registered server-side so forms render on the front end
 *   and are also available in the native block editor.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Nine_Code_Visual_Editor {
	const HANDLE = 'nine-code-visual-editor';
	const BLOCK  = 'nine-code-data/form';

	private static $instance;

	public static function instance() {
		return self::$instance ?: ( self::$instance = new self() );
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_form_block' ) );
	}

	public function register_block() {
		register_block_type( self::BLOCK, array(
			'api_version'     => 3,
			'attributes'      => array( 'formId' => array( 'type' => 'number', 'default' => 0 ) ),
			'render_callback' => array( $this, 'render_form_block' ),
		) );
	}

	public function render_form_block( $attributes ) {
		$form_id   = isset( $attributes['formId'] ) ? absint( $attributes['formId'] ) : 0;
		$shortcode = $form_id ? sprintf( '[nine10_form form_id="%d"]', $form_id ) : '[nine10_form mode="contact"]';
		return '<div class="wp-block-nine-code-data-form">' . do_shortcode( $shortcode ) . '</div>';
	}

	/** Forms available to the block's picker. */
	private function forms() {
		if ( ! current_user_can( 'edit_posts' ) ) { return array(); }
		$posts = get_posts( array(
			'post_type'      => 'nine10_form_def',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		$out = array();
		foreach ( $posts as $post ) { $out[] = array( 'id' => (int) $post->ID, 'title' => $post->post_title ); }
		return $out;
	}

	private function register_form_block_script() {
		if ( wp_script_is( 'nine-code-form-block', 'registered' ) ) { return; }
		wp_register_script(
			'nine-code-form-block',
			NINE55_ULTRON_DATA_URL . 'assets/form-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n' ),
			NINE55_ULTRON_DATA_VERSION,
			true
		);
		wp_add_inline_script( 'nine-code-form-block', 'window.NineCodeDataForms = ' . wp_json_encode( $this->forms() ) . ';', 'before' );
	}

	/** Make the Form block available in the native block editor too. */
	public function enqueue_form_block() {
		$this->register_form_block_script();
		wp_enqueue_script( 'nine-code-form-block' );
	}

	/** Everything the Post Editor's visual content editor needs. Call from the Post Editor's enqueue. */
	public function enqueue() {
		$this->register_form_block_script();

		$deps = array(
			'wp-element', 'wp-components', 'wp-i18n', 'wp-data', 'wp-blocks', 'wp-block-editor', 'wp-block-library',
			'wp-format-library', 'wp-editor', 'wp-media-utils', 'wp-autop', 'nine-code-form-block',
		);
		wp_enqueue_style( 'wp-edit-blocks' );
		wp_enqueue_style( 'wp-format-library' );
		wp_enqueue_style( 'wp-block-library-theme' );
		wp_enqueue_style( self::HANDLE, NINE55_ULTRON_DATA_URL . 'assets/visual-editor.css', array( 'wp-edit-blocks' ), NINE55_ULTRON_DATA_VERSION );
		wp_enqueue_script( self::HANDLE, NINE55_ULTRON_DATA_URL . 'assets/visual-editor.js', $deps, NINE55_ULTRON_DATA_VERSION, true );

		$sizes = array();
		foreach ( wp_get_registered_image_subsizes() as $slug => $size ) {
			$sizes[] = array( 'slug' => $slug, 'name' => ucwords( str_replace( array( '-', '_' ), ' ', $slug ) ) );
		}
		$sizes[] = array( 'slug' => 'full', 'name' => __( 'Full Size', 'nine-code-data' ) );
		wp_add_inline_script(
			self::HANDLE,
			'window.NineCodeVisualEditorConfig = ' . wp_json_encode( array(
				'imageSizes' => $sizes,
				'maxWidth'   => 1200,
				'isRTL'      => is_rtl(),
			) ) . ';',
			'before'
		);
	}
}
