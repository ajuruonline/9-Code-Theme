<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 9PM Design File layer.
 *
 * A .9pm file is JSON describing how content fields can be composed
 * into either editable Gutenberg / 9 Elements blocks or, experimentally,
 * per-post Elementor data. 9CF/Gutenberg is the primary portable content contract; ACF remains a supported bridge and legacy/specialist provider. The design recipe is stored separately from field values so content can be re-synchronised without changing the underlying schema.
 */
final class Nine_Post_Manager_Design {
    private static $instance = null;

    const META_PACKAGE   = '_npm9_design_package';
    const META_ENGINE    = '_npm9_design_engine';
    const META_LINKED    = '_npm9_design_linked';
    const META_SYNCED    = '_npm9_design_synced_at';
    const META_BACKUP    = '_npm9_design_previous_state';
    const META_BASE_CONTENT = '_npm9_design_base_content';
    const META_OUTPUT_HASH  = '_npm9_design_output_hash';
    const NONCE_ACTION   = 'npm9_action';

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
        add_action( 'enqueue_block_editor_assets', [ $this, 'editor_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'frontend_assets' ] );
        add_action( 'npm9_after_render_panel', [ $this, 'render_admin_panel' ] );
        add_action( 'npm9_after_apply_payload', [ $this, 'sync_after_post_update' ], 10, 3 );

        $ajax = [
            'design_state'          => 'ajax_state',
            'design_preview'        => 'ajax_preview',
            'design_apply'          => 'ajax_apply',
            'design_sync'           => 'ajax_sync',
            'design_detach'         => 'ajax_detach',
            'design_clear'          => 'ajax_clear',
            'design_export'         => 'ajax_export',
            'design_acf_brief'      => 'ajax_acf_brief',
            'design_elementor_export' => 'ajax_elementor_export',
        ];
        foreach ( $ajax as $action => $method ) {
            add_action( 'wp_ajax_npm9_' . $action, [ $this, $method ] );
        }
    }

    public function admin_assets( $hook ) {
        if ( 'toplevel_page_nine-post-manager' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'npm9-design', NPM9_URL . 'assets/design.css', [ 'npm9-admin' ], NPM9_VERSION );
        wp_enqueue_script( 'npm9-design', NPM9_URL . 'assets/design.js', [ 'jquery', 'npm9-app' ], NPM9_VERSION, true );
        wp_localize_script( 'npm9-design', 'NPM9Design', [
            'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
            'nonce'              => wp_create_nonce( self::NONCE_ACTION ),
            'nineElementsActive' => $this->nine_elements_active(),
            'elementorActive'    => did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' ),
            'blockGuideUrl'      => NPM9_URL . 'docs/9PM-AI-AUTHORING-GUIDE-BLOCK-EDITOR.md',
            'elementorGuideUrl'  => NPM9_URL . 'docs/9PM-AI-AUTHORING-GUIDE-ELEMENTOR-EXPERIMENTAL.md',
            'schemaUrl'          => NPM9_URL . 'docs/9PM-DESIGN-SCHEMA.md',
            'exampleUrl'         => NPM9_URL . 'docs/examples/basic-profile.9pm',
        ] );
    }

    public function editor_assets() {
        $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
        if ( ! $post_id || 'blocks' !== get_post_meta( $post_id, self::META_ENGINE, true ) ) {
            return;
        }
        wp_enqueue_style( 'npm9-design-blocks', NPM9_URL . 'assets/design-blocks.css', [], NPM9_VERSION );
    }

    public function frontend_assets() {
        if ( ! is_singular() ) {
            return;
        }
        $post_id = get_queried_object_id();
        if ( ! $post_id || 'blocks' !== get_post_meta( $post_id, self::META_ENGINE, true ) ) {
            return;
        }
        wp_enqueue_style( 'npm9-design-blocks', NPM9_URL . 'assets/design-blocks.css', [], NPM9_VERSION );
    }

    public function render_admin_panel() {
        ?>
        <details class="npm9-card npm9-major-details npm9-design-card" id="npm9-design-panel">
            <summary class="npm9-major-summary">
                <span><strong>9PM Design File</strong><small>9CF / Site Fields → Gutenberg / 9 Elements, with an experimental per-post Elementor route</small></span>
                <span class="npm9-badge npm9-badge-new">.9pm · AI portable</span>
            </summary>
            <div class="npm9-major-body">
                <div class="npm9-design-tabs" role="tablist" aria-label="9PM design engines">
                    <button type="button" class="button is-active" data-npm9-design-tab="blocks">Block Editor / 9 Elements</button>
                    <button type="button" class="button" data-npm9-design-tab="elementor">Elementor <span class="npm9-exp">Experimental</span></button>
                    <button type="button" class="button" data-npm9-design-tab="ai">AI Authoring Files</button>
                </div>

                <section class="npm9-design-pane is-active" data-npm9-design-pane="blocks">
                    <div class="npm9-design-intro">
                        <div><strong>Gutenberg + 9CF is the primary day-to-day layer.</strong><p>Import a .9pm design recipe to compile real Gutenberg / 9 Elements blocks from WordPress-native 9CF/Site Fields. Existing ACF contracts remain supported only as a compatibility bridge; specialist Elementor work stays optional.</p></div>
                        <span id="npm9-design-block-status" class="npm9-render-status-badge">Not loaded</span>
                    </div>
                    <div id="npm9-design-manual-warning" class="npm9-design-warning" hidden></div>
                    <div id="npm9-design-nine-elements-warning" class="npm9-design-warning" hidden></div>
                    <div class="npm9-design-actions">
                        <button type="button" class="button button-primary" id="npm9-design-import-blocks">Import .9pm Design</button>
                        <input type="file" id="npm9-design-blocks-file" accept=".9pm,.json,application/json,text/plain" hidden>
                        <button type="button" class="button" id="npm9-design-sync">Rebuild from Current Fields</button>
                        <button type="button" class="button" id="npm9-design-export">Export Current .9pm</button>
                        <button type="button" class="button" id="npm9-design-detach">Detach Design</button>
                        <button type="button" class="button button-link-delete" id="npm9-design-clear">Remove Design Link</button>
                    </div>
                    <div id="npm9-design-block-preview" class="npm9-design-preview" hidden></div>
                    <p class="description"><strong>Linked</strong> means saving linked field data through 9 Post Editor can rebuild the generated blocks. <strong>Detached</strong> keeps the blocks exactly as they are so you can continue editing them manually in Gutenberg.</p>
                </section>

                <section class="npm9-design-pane" data-npm9-design-pane="elementor" hidden>
                    <div class="npm9-experimental-notice"><strong>Experimental — per post only.</strong> This does not import a global Theme Builder template. It writes Elementor JSON to the selected post/page only, keeps the original .9pm recipe for optional re-sync, and can be detached before manual Elementor editing.</div>
                    <div class="npm9-design-intro">
                        <div><strong>Accepted:</strong><p>a standard Elementor exported JSON file, or a .9pm wrapper with <code>"engine":"elementor"</code>. Field placeholders such as <code>{{field:full_name}}</code> are resolved from 9CF/Site Fields; legacy <code>{{acf:full_name}}</code> remains supported when ACF is present.</p></div>
                        <span id="npm9-design-elementor-status" class="npm9-render-status-badge">Experimental</span>
                    </div>
                    <div id="npm9-design-elementor-warning" class="npm9-design-warning" hidden></div>
                    <div class="npm9-design-actions">
                        <button type="button" class="button button-primary" id="npm9-design-import-elementor">Import Elementor / .9pm</button>
                        <input type="file" id="npm9-design-elementor-file" accept=".9pm,.json,application/json,text/plain" hidden>
                        <button type="button" class="button" id="npm9-design-elementor-sync">Re-apply from Current Fields</button>
                        <button type="button" class="button" id="npm9-design-elementor-export">Export Current Elementor JSON</button>
                        <button type="button" class="button" id="npm9-design-elementor-detach">Detach Design</button>
                    </div>
                    <div id="npm9-design-elementor-preview" class="npm9-design-preview" hidden></div>
                </section>

                <section class="npm9-design-pane" data-npm9-design-pane="ai" hidden>
                    <p>For new work, send the AI a numbered 9CF export together with the authoring guide. ACF is not required. The guide explains the exact 9PM schema, supported 9 Elements types and the difference between Block Editor and Elementor engines.</p>
                    <div class="npm9-guide-grid">
                        <a class="button button-primary" target="_blank" rel="noopener" href="<?php echo esc_url( NPM9_URL . 'docs/9PM-AI-AUTHORING-GUIDE-BLOCK-EDITOR.md' ); ?>">Block Editor AI Guide · Markdown</a>
                        <a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( NPM9_URL . 'docs/9PM-AI-AUTHORING-GUIDE-ELEMENTOR-EXPERIMENTAL.md' ); ?>">Elementor AI Guide · Markdown</a>
                        <a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( NPM9_URL . 'docs/9PM-DESIGN-SCHEMA.md' ); ?>">9PM Schema Reference</a>
                        <a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( NPM9_URL . 'docs/examples/basic-profile.9pm' ); ?>">Example .9pm File</a>
                        <button type="button" class="button" id="npm9-design-acf-brief">Export Current Field Design Brief</button>
                    </div>
                    <div id="npm9-design-ai-result" class="npm9-design-preview" hidden></div>
                </section>
            </div>
        </details>
        <?php
    }

    private function verify( $post_id = 0 ) {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( $post_id ) {
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
            }
        } elseif ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
        }
    }

    private function nine_elements_active() {
        return class_exists( 'Nine_Elements_Plugin' ) || ( class_exists( 'WP_Block_Type_Registry' ) && WP_Block_Type_Registry::get_instance()->is_registered( 'nine/elements' ) );
    }

    private function elementor_active() {
        return did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' ) || class_exists( '\\Elementor\\Plugin' );
    }

    public function package_for_post( $post_id ) {
        $package = get_post_meta( $post_id, self::META_PACKAGE, true );
        if ( ! is_array( $package ) || empty( $package ) ) {
            return [];
        }
        return [
            'engine'  => sanitize_key( get_post_meta( $post_id, self::META_ENGINE, true ) ?: ( $package['engine'] ?? 'blocks' ) ),
            'linked'  => '1' === get_post_meta( $post_id, self::META_LINKED, true ),
            'package' => $package,
        ];
    }

    public function apply_portable_design( $post_id, $data ) {
        if ( ! is_array( $data ) || empty( $data['package'] ) || ! is_array( $data['package'] ) ) {
            return true;
        }
        $package = $data['package'];
        if ( ! empty( $data['engine'] ) ) {
            $package['engine'] = sanitize_key( $data['engine'] );
        }
        $result = $this->apply_package( $post_id, $package, $package['bindings'] ?? [], false );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        if ( array_key_exists( 'linked', $data ) && empty( $data['linked'] ) ) {
            update_post_meta( $post_id, self::META_LINKED, '0' );
        }
        return true;
    }

    public function ajax_state() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify( $post_id );
        wp_send_json_success( $this->state( $post_id ) );
    }

    private function state( $post_id ) {
        $package = get_post_meta( $post_id, self::META_PACKAGE, true );
        $engine  = sanitize_key( get_post_meta( $post_id, self::META_ENGINE, true ) );
        $linked  = '1' === get_post_meta( $post_id, self::META_LINKED, true );
        $fields  = $this->available_acf_fields( $post_id );
        $stored_hash = (string) get_post_meta( $post_id, self::META_OUTPUT_HASH, true );
        $manual_changed = $linked && $stored_hash && $stored_hash !== $this->current_output_hash( $post_id, $engine );
        return [
            'active'             => is_array( $package ) && ! empty( $package ),
            'engine'             => $engine ?: '',
            'linked'             => $linked,
            'name'               => is_array( $package ) ? sanitize_text_field( $package['name'] ?? '' ) : '',
            'syncedAt'           => get_post_meta( $post_id, self::META_SYNCED, true ),
            'manualChanged'       => $manual_changed,
            'nineElementsActive' => $this->nine_elements_active(),
            'elementorActive'    => $this->elementor_active(),
            'acfFields'          => array_values( $fields ),
            'gutenbergEditUrl'   => get_edit_post_link( $post_id, 'raw' ),
            'elementorEditUrl'   => $this->elementor_active() ? admin_url( 'post.php?post=' . $post_id . '&action=elementor' ) : '',
        ];
    }

    public function ajax_preview() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify( $post_id );
        $content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
        $engine_hint = isset( $_POST['engine'] ) ? sanitize_key( $_POST['engine'] ) : '';
        $package = $this->parse_package( $content, $engine_hint );
        if ( is_wp_error( $package ) ) {
            wp_send_json_error( [ 'message' => $package->get_error_message() ] );
        }
        $match = $this->match_contract( $post_id, $package, [] );
        wp_send_json_success( [
            'engine'          => $package['engine'],
            'name'            => $package['name'] ?? 'Untitled 9PM design',
            'description'     => $package['description'] ?? '',
            'requiredMissing' => $match['required_missing'],
            'optionalMissing' => $match['optional_missing'],
            'resolved'        => $match['bindings'],
            'availableFields' => array_values( $this->available_acf_fields( $post_id ) ),
            'nineElementsActive' => $this->nine_elements_active(),
            'elementorActive' => $this->elementor_active(),
        ] );
    }

    public function ajax_apply() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify( $post_id );
        $content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
        $engine_hint = isset( $_POST['engine'] ) ? sanitize_key( $_POST['engine'] ) : '';
        $mappings = isset( $_POST['mappings'] ) ? json_decode( wp_unslash( $_POST['mappings'] ), true ) : [];
        if ( ! is_array( $mappings ) ) {
            $mappings = [];
        }
        $package = $this->parse_package( $content, $engine_hint );
        if ( is_wp_error( $package ) ) {
            wp_send_json_error( [ 'message' => $package->get_error_message() ] );
        }
        $result = $this->apply_package( $post_id, $package, $mappings, true );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }
        wp_send_json_success( [
            'message' => '9PM design applied to this post.',
            'state'   => $this->state( $post_id ),
            'viewUrl' => class_exists( 'Nine_Post_Manager' ) ? Nine_Post_Manager::instance()->view_url_for_post( $post_id ) : get_permalink( $post_id ),
        ] );
    }

    public function ajax_sync() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify( $post_id );
        $result = $this->sync_post( $post_id, true );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }
        wp_send_json_success( [ 'message' => 'Design rebuilt from current post fields.', 'state' => $this->state( $post_id ) ] );
    }

    public function ajax_detach() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify( $post_id );
        update_post_meta( $post_id, self::META_LINKED, '0' );
        wp_send_json_success( [ 'message' => 'Design detached. Current Gutenberg/Elementor content is preserved.', 'state' => $this->state( $post_id ) ] );
    }

    public function ajax_clear() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify( $post_id );
        delete_post_meta( $post_id, self::META_PACKAGE );
        delete_post_meta( $post_id, self::META_ENGINE );
        delete_post_meta( $post_id, self::META_LINKED );
        delete_post_meta( $post_id, self::META_SYNCED );
        delete_post_meta( $post_id, self::META_BASE_CONTENT );
        delete_post_meta( $post_id, self::META_OUTPUT_HASH );
        wp_send_json_success( [ 'message' => '9PM design link removed. Existing post content was not deleted.', 'state' => $this->state( $post_id ) ] );
    }

    public function ajax_export() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify( $post_id );
        $package = get_post_meta( $post_id, self::META_PACKAGE, true );
        if ( ! is_array( $package ) || empty( $package ) ) {
            wp_send_json_error( [ 'message' => 'No .9pm design is stored for this post.' ] );
        }
        $slug = sanitize_title( get_the_title( $post_id ) ?: 'post-' . $post_id );
        wp_send_json_success( [
            'filename' => $slug . '.9pm',
            'mime'     => 'application/json',
            'content'  => wp_json_encode( $package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
        ] );
    }

    public function ajax_acf_brief() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify( $post_id );
        $fields = array_values( $this->available_acf_fields( $post_id ) );
        $lines = [
            '# 9PM Field Design Brief',
            '',
            'Target post: ' . get_the_title( $post_id ) . ' (#' . $post_id . ')',
            'Post type: ' . get_post_type( $post_id ),
            '',
            'Use this file together with the 9PM AI Authoring Guide. Design a `.9pm` package; do not rename the listed 9CF/Site Field aliases.',
            '',
            '## Available Post Fields',
            '',
            '| Alias | Provider | Type | Label |',
            '|---|---|---|---|',
        ];
        foreach ( $fields as $field ) {
            $lines[] = '| `' . str_replace( '|', '\\|', $field['name'] ) . '` | `' . str_replace( '|', '\\|', $field['provider'] ?? '9cf' ) . '` | `' . str_replace( '|', '\\|', $field['type'] ) . '` | ' . str_replace( '|', '\\|', $field['label'] ) . ' |';
        }
        $lines[] = '';
        $lines[] = '## Required output';
        $lines[] = '';
        $lines[] = 'Return one valid JSON `.9pm` file following the selected Block Editor or Elementor guide.';
        wp_send_json_success( [
            'filename' => sanitize_title( get_the_title( $post_id ) ?: 'post-' . $post_id ) . '-field-design-brief.md',
            'mime'     => 'text/markdown',
            'content'  => implode( "\n", $lines ),
        ] );
    }

    public function ajax_elementor_export() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify( $post_id );
        if ( ! $this->elementor_active() ) {
            wp_send_json_error( [ 'message' => 'Elementor is not active.' ] );
        }
        $raw = get_post_meta( $post_id, '_elementor_data', true );
        $content = json_decode( (string) $raw, true );
        if ( ! is_array( $content ) ) {
            $content = [];
        }
        $settings = get_post_meta( $post_id, '_elementor_page_settings', true );
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }
        $export = [
            'title'         => get_the_title( $post_id ),
            'type'          => get_post_type( $post_id ) ?: 'page',
            'version'       => '0.4',
            'page_settings' => $settings,
            'content'       => $content,
        ];
        wp_send_json_success( [
            'filename' => sanitize_title( get_the_title( $post_id ) ?: 'post-' . $post_id ) . '-elementor.json',
            'mime'     => 'application/json',
            'content'  => wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
        ] );
    }

    private function current_output_hash( $post_id, $engine ) {
        if ( 'elementor' === $engine ) {
            $value = get_post_meta( $post_id, '_elementor_data', true );
            $raw = is_array( $value ) || is_object( $value ) ? wp_json_encode( $value ) : (string) $value;
        } else {
            $raw = (string) get_post_field( 'post_content', $post_id, 'raw' );
        }
        return hash( 'sha256', $raw );
    }

    private function meta_exists( $post_id, $key ) {
        return function_exists( 'metadata_exists' ) ? metadata_exists( 'post', $post_id, $key ) : '' !== get_post_meta( $post_id, $key, true );
    }

    /**
     * Initialise change tracking for linked designs created before v2.0.1.
     * For append/prepend Block designs, recover the original base content by
     * stripping the currently generated design from the stored post content.
     */
    private function ensure_legacy_tracking( $post_id, $engine, $package ) {
        if ( get_post_meta( $post_id, self::META_OUTPUT_HASH, true ) ) { return true; }
        if ( 'blocks' === $engine ) {
            $placement = sanitize_key( $package['settings']['placement'] ?? 'replace' );
            if ( in_array( $placement, [ 'prepend', 'append' ], true ) && ! $this->meta_exists( $post_id, self::META_BASE_CONTENT ) ) {
                $current = (string) get_post_field( 'post_content', $post_id, 'raw' );
                $generated = $this->compile_blocks( $post_id, $package );
                if ( is_wp_error( $generated ) ) { return $generated; }
                $sep = "\n\n";
                $base = null;
                if ( 'prepend' === $placement ) {
                    $prefix = $generated . $sep;
                    if ( 0 === strpos( $current, $prefix ) ) { $base = substr( $current, strlen( $prefix ) ); }
                    elseif ( $current === $generated ) { $base = ''; }
                } else {
                    $suffix = $sep . $generated;
                    if ( strlen( $current ) >= strlen( $suffix ) && substr( $current, -strlen( $suffix ) ) === $suffix ) { $base = substr( $current, 0, strlen( $current ) - strlen( $suffix ) ); }
                    elseif ( $current === $generated ) { $base = ''; }
                }
                if ( null === $base ) {
                    return new WP_Error( 'npm9_design_legacy_base', 'This older linked append/prepend design cannot be safely separated from manual post content. Detach the design to preserve the current layout, or explicitly reapply the .9pm design after reviewing the post.' );
                }
                update_post_meta( $post_id, self::META_BASE_CONTENT, $base );
            }
        }
        update_post_meta( $post_id, self::META_OUTPUT_HASH, $this->current_output_hash( $post_id, $engine ) );
        return true;
    }

    /**
     * Protect manual Gutenberg/Elementor work while a design is linked.
     * Automatic ACF-driven rebuilds are allowed only while the generated output
     * still matches the last 9PM sync. An explicit Rebuild button remains the
     * intentional override.
     */
    public function preflight_linked_save( $post_id ) {
        if ( '1' !== get_post_meta( $post_id, self::META_LINKED, true ) ) { return true; }
        $package = get_post_meta( $post_id, self::META_PACKAGE, true );
        if ( ! is_array( $package ) || empty( $package ) ) { return true; }
        $engine = sanitize_key( get_post_meta( $post_id, self::META_ENGINE, true ) ?: ( $package['engine'] ?? 'blocks' ) );
        $legacy = $this->ensure_legacy_tracking( $post_id, $engine, $package );
        if ( is_wp_error( $legacy ) ) { return $legacy; }
        $stored = (string) get_post_meta( $post_id, self::META_OUTPUT_HASH, true );
        $current = $this->current_output_hash( $post_id, $engine );
        if ( $stored && $stored !== $current ) {
            $editor = 'elementor' === $engine ? 'Elementor' : 'Gutenberg';
            return new WP_Error( 'npm9_design_manual_change', 'Manual ' . $editor . ' design changes were detected after the last 9PM sync. To protect that work, 9PM did not overwrite it. Detach the design to keep the manual layout, or use Rebuild from Current Fields if you intentionally want the linked .9pm recipe to replace those manual changes.' );
        }
        return true;
    }

    public function sync_after_post_update( $post_id, $payload, $from_import ) {
        if ( '1' !== get_post_meta( $post_id, self::META_LINKED, true ) ) {
            return;
        }
        $this->sync_post( $post_id, false );
    }

    private function sync_post( $post_id, $manual = false ) {
        $package = get_post_meta( $post_id, self::META_PACKAGE, true );
        if ( ! is_array( $package ) || empty( $package ) ) {
            return new WP_Error( 'npm9_design_missing', 'No linked .9pm design is stored for this post.' );
        }
        if ( ! $manual && '1' !== get_post_meta( $post_id, self::META_LINKED, true ) ) {
            return true;
        }
        if ( ! $manual ) {
            $safe = $this->preflight_linked_save( $post_id );
            if ( is_wp_error( $safe ) ) { return $safe; }
        }
        return $this->apply_package( $post_id, $package, $package['bindings'] ?? [], false );
    }

    private function apply_package( $post_id, $package, $mappings, $snapshot ) {
        $engine = sanitize_key( $package['engine'] ?? 'blocks' );
        if ( ! in_array( $engine, [ 'blocks', 'elementor' ], true ) ) {
            return new WP_Error( 'npm9_design_engine', 'Unsupported 9PM design engine.' );
        }
        if ( 'blocks' === $engine && ! $this->nine_elements_active() ) {
            return new WP_Error( 'npm9_elements_missing', '9 Elements is required for this Block Editor design. Activate 9 Elements, then import the .9pm file again.' );
        }
        if ( 'elementor' === $engine && ! $this->elementor_active() ) {
            return new WP_Error( 'npm9_elementor_missing', 'Elementor is not active. The Elementor route is experimental and only applies when Elementor is installed and active.' );
        }

        $match = $this->match_contract( $post_id, $package, $mappings );
        if ( ! empty( $match['required_missing'] ) ) {
            return new WP_Error( 'npm9_design_mapping', 'Required post-field mappings are missing: ' . implode( ', ', $match['required_missing'] ) );
        }
        $package['bindings'] = $match['bindings'];
        $package['format']   = '9pm-design';
        $package['version']  = isset( $package['version'] ) ? (int) $package['version'] : 1;
        $package['engine']   = $engine;

        if ( $snapshot ) {
            $this->snapshot_state( $post_id );
        }

        if ( 'blocks' === $engine ) {
            $compiled = $this->compile_blocks( $post_id, $package );
            if ( is_wp_error( $compiled ) ) {
                return $compiled;
            }
            $post = get_post( $post_id );
            $placement = sanitize_key( $package['settings']['placement'] ?? 'replace' );
            if ( ! in_array( $placement, [ 'replace', 'prepend', 'append' ], true ) ) {
                $placement = 'replace';
            }
            $current = $post ? (string) $post->post_content : '';
            $has_base = $this->meta_exists( $post_id, self::META_BASE_CONTENT );
            $was_linked = '1' === get_post_meta( $post_id, self::META_LINKED, true );
            if ( $snapshot ) {
                // When replacing an already-linked recipe, keep the original base rather than
                // treating generated content as new base. A detached post intentionally starts fresh.
                $base = ( $was_linked && $has_base ) ? (string) get_post_meta( $post_id, self::META_BASE_CONTENT, true ) : $current;
                update_post_meta( $post_id, self::META_BASE_CONTENT, $base );
            } elseif ( $has_base ) {
                $base = (string) get_post_meta( $post_id, self::META_BASE_CONTENT, true );
            } else {
                $base = $current;
                update_post_meta( $post_id, self::META_BASE_CONTENT, $base );
            }
            if ( 'prepend' === $placement ) {
                $compiled = $compiled . "\n\n" . $base;
            } elseif ( 'append' === $placement ) {
                $compiled = $base . "\n\n" . $compiled;
            }
            $updated = wp_update_post( wp_slash( [ 'ID' => $post_id, 'post_content' => $compiled ] ), true );
            if ( is_wp_error( $updated ) ) {
                return $updated;
            }
            // Do not run the older Instant ACF renderer on top of a compiled 9PM design.
            update_post_meta( $post_id, '_npm9_render_enabled', '0' );
        } else {
            $applied = $this->apply_elementor( $post_id, $package );
            if ( is_wp_error( $applied ) ) {
                return $applied;
            }
            update_post_meta( $post_id, '_npm9_render_enabled', '0' );
        }

        update_post_meta( $post_id, self::META_PACKAGE, $package );
        update_post_meta( $post_id, self::META_ENGINE, $engine );
        update_post_meta( $post_id, self::META_LINKED, '1' );
        update_post_meta( $post_id, self::META_SYNCED, current_time( 'mysql' ) );
        update_post_meta( $post_id, self::META_OUTPUT_HASH, $this->current_output_hash( $post_id, $engine ) );
        clean_post_cache( $post_id );
        return true;
    }

    private function snapshot_state( $post_id ) {
        $snapshot = [
            'time'             => current_time( 'mysql' ),
            'post_content'     => get_post_field( 'post_content', $post_id, 'raw' ),
            'elementor_data'   => get_post_meta( $post_id, '_elementor_data', true ),
            'elementor_mode'   => get_post_meta( $post_id, '_elementor_edit_mode', true ),
            'elementor_settings' => get_post_meta( $post_id, '_elementor_page_settings', true ),
            'package'          => get_post_meta( $post_id, self::META_PACKAGE, true ),
            'engine'           => get_post_meta( $post_id, self::META_ENGINE, true ),
        ];
        update_post_meta( $post_id, self::META_BACKUP, $snapshot );
    }

    private function parse_package( $content, $engine_hint = '' ) {
        $content = trim( (string) $content );
        if ( '' === $content ) {
            return new WP_Error( 'npm9_design_empty', 'The design file is empty.' );
        }
        $decoded = json_decode( $content, true );
        if ( ! is_array( $decoded ) && preg_match( '/```(?:json)?\s*(\{.*\})\s*```/is', $content, $m ) ) {
            $decoded = json_decode( $m[1], true );
        }
        if ( ! is_array( $decoded ) ) {
            return new WP_Error( 'npm9_design_json', 'The .9pm file must contain valid JSON.' );
        }

        // Standard Elementor exported JSON can be wrapped automatically.
        if ( empty( $decoded['format'] ) && isset( $decoded['content'] ) && is_array( $decoded['content'] ) && isset( $decoded['version'] ) ) {
            return [
                'format'       => '9pm-design',
                'version'      => 1,
                'name'         => sanitize_text_field( $decoded['title'] ?? 'Elementor Design' ),
                'description'  => 'Imported standard Elementor template JSON.',
                'engine'       => 'elementor',
                'field_contract' => [],
                'acf_contract' => [], // legacy compatibility
                'bindings'     => [],
                'settings'     => [ 'placement' => 'replace' ],
                'elementor'    => [
                    'version'       => sanitize_text_field( $decoded['version'] ?? '0.4' ),
                    'type'          => sanitize_key( $decoded['type'] ?? 'page' ),
                    'page_settings' => isset( $decoded['page_settings'] ) && is_array( $decoded['page_settings'] ) ? $decoded['page_settings'] : [],
                    'content'       => $decoded['content'],
                ],
            ];
        }

        $format = sanitize_key( $decoded['format'] ?? '' );
        if ( ! in_array( $format, [ '9pm-design', '9-post-manager-design', '9pm' ], true ) ) {
            return new WP_Error( 'npm9_design_format', 'This is not a recognised 9PM design package.' );
        }
        $engine = sanitize_key( $decoded['engine'] ?? $engine_hint ?: 'blocks' );
        if ( ! in_array( $engine, [ 'blocks', 'elementor' ], true ) ) {
            return new WP_Error( 'npm9_design_engine', 'The 9PM engine must be "blocks" or "elementor".' );
        }
        $decoded['engine'] = $engine;
        $decoded['name']   = sanitize_text_field( $decoded['name'] ?? 'Untitled 9PM Design' );
        return $decoded;
    }

    /**
     * Provider-neutral field inventory used by .9pm designs.
     *
     * The historical method name is retained internally to avoid breaking older design packages,
     * but the returned inventory is now primarily sourced from 9CF and therefore works without ACF.
     */
    private function available_acf_fields( $post_id ) {
        $out = [];

        if ( class_exists( 'Nine_Post_Manager_9CF' ) ) {
            $contract = Nine_Post_Manager_9CF::instance()->contract_for_post( $post_id, 'current' );
            if ( ! is_wp_error( $contract ) ) {
                foreach ( (array) ( $contract['fields'] ?? [] ) as $field ) {
                    if ( ! is_array( $field ) || empty( $field['id'] ) ) { continue; }
                    $provider = sanitize_key( $field['provider'] ?? '' );
                    if ( 'block' === $provider ) { continue; } // avoid circular design-from-generated-block bindings.
                    $source = isset( $field['source'] ) && is_array( $field['source'] ) ? $field['source'] : [];
                    $field_id = (string) $field['id'];
                    $alias = '';
                    if ( ! empty( $source['meta_key'] ) ) {
                        $alias = sanitize_key( $source['meta_key'] );
                        if ( 0 === strpos( $alias, 'npm9f_' ) ) { $alias = substr( $alias, 6 ); }
                    } elseif ( ! empty( $source['acf_name'] ) ) {
                        $alias = sanitize_key( $source['acf_name'] );
                    } elseif ( ! empty( $source['core_key'] ) ) {
                        $alias = sanitize_key( $source['core_key'] );
                    } elseif ( ! empty( $source['taxonomy'] ) ) {
                        $alias = sanitize_key( $source['taxonomy'] );
                    } else {
                        $parts = explode( ':', $field_id, 2 );
                        $alias = sanitize_key( end( $parts ) );
                    }
                    if ( ! $alias ) { continue; }
                    $item = [
                        'name' => $alias,
                        'key' => sanitize_key( $source['acf_key'] ?? '' ),
                        'label' => sanitize_text_field( $field['label'] ?? $alias ),
                        'type' => sanitize_key( $field['type'] ?? 'text' ),
                        'field_id' => $field_id,
                        'provider' => $provider ?: '9cf',
                    ];
                    // Prefer native Site Fields / semantic providers over a later duplicate alias.
                    if ( ! isset( $out[ $alias ] ) || in_array( $provider, [ 'meta', 'plugin' ], true ) ) {
                        $out[ $alias ] = $item;
                    }
                }
            }
        }

        // ACF-specific aliases remain available for legacy .9pm packages when ACF exists.
        if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
            $groups = acf_get_field_groups( [ 'post_id' => $post_id ] );
            foreach ( (array) $groups as $group ) {
                $fields = acf_get_fields( $group['key'] );
                foreach ( (array) $fields as $field ) {
                    $this->flatten_acf_field( $field, $out );
                }
            }
        }
        return $out;
    }

    private function flatten_acf_field( $field, &$out ) {
        if ( ! is_array( $field ) ) {
            return;
        }
        if ( ! empty( $field['name'] ) ) {
            $name = sanitize_key( $field['name'] );
            if ( ! isset( $out[ $name ] ) ) {
                $out[ $name ] = [
                    'name'  => $name,
                    'key'   => sanitize_key( $field['key'] ?? '' ),
                    'label' => sanitize_text_field( $field['label'] ?? $field['name'] ),
                    'type'  => sanitize_key( $field['type'] ?? 'text' ),
                    'field_id' => 'acf:' . sanitize_key( $field['key'] ?? $name ),
                    'provider' => 'acf',
                ];
            }
        }
        foreach ( (array) ( $field['sub_fields'] ?? [] ) as $sub ) {
            $this->flatten_acf_field( $sub, $out );
        }
        foreach ( (array) ( $field['layouts'] ?? [] ) as $layout ) {
            foreach ( (array) ( $layout['sub_fields'] ?? [] ) as $sub ) {
                $this->flatten_acf_field( $sub, $out );
            }
        }
    }

    private function contract_from_package( $package ) {
        $contract = [];
        $raw_contract = [];
        if ( ! empty( $package['field_contract'] ) && is_array( $package['field_contract'] ) ) {
            $raw_contract = $package['field_contract'];
        } elseif ( ! empty( $package['acf_contract'] ) && is_array( $package['acf_contract'] ) ) {
            $raw_contract = $package['acf_contract']; // legacy v2 packages.
        }
        if ( $raw_contract ) {
            foreach ( $raw_contract as $item ) {
                if ( ! is_array( $item ) ) {
                    continue;
                }
                $alias = sanitize_key( $item['alias'] ?? $item['field'] ?? $item['name'] ?? '' );
                if ( ! $alias ) {
                    continue;
                }
                $contract[ $alias ] = [
                    'alias'    => $alias,
                    'field'    => sanitize_key( $item['field'] ?? $item['name'] ?? $alias ),
                    'field_id' => sanitize_text_field( $item['field_id'] ?? '' ),
                    'key'      => sanitize_key( $item['key'] ?? '' ),
                    'type'     => sanitize_key( $item['type'] ?? '' ),
                    'required' => ! array_key_exists( 'required', $item ) || ! empty( $item['required'] ),
                ];
            }
        }
        if ( empty( $contract ) ) {
            $refs = [];
            $this->collect_source_refs( $package, $refs );
            foreach ( array_keys( $refs ) as $alias ) {
                if ( ! isset( $contract[ $alias ] ) ) {
                    $contract[ $alias ] = [ 'alias' => $alias, 'field' => $alias, 'key' => '', 'type' => '', 'required' => false ];
                }
            }
        }
        return $contract;
    }

    private function collect_source_refs( $node, &$refs ) {
        if ( ! is_array( $node ) ) {
            return;
        }
        foreach ( $node as $key => $value ) {
            if ( in_array( $key, [ 'source', 'repeat_source', 'url_source' ], true ) ) {
                $alias = '';
                if ( is_string( $value ) ) {
                    $alias = $value;
                } elseif ( is_array( $value ) ) {
                    $alias = $value['acf'] ?? $value['field'] ?? '';
                }
                $raw_alias = (string) $alias;
                if ( 0 === strpos( $raw_alias, '@post.' ) ) {
                    continue;
                }
                $alias = sanitize_key( preg_replace( '/^acf:/', '', $raw_alias ) );
                if ( $alias ) {
                    $refs[ $alias ] = true;
                }
            }
            if ( is_array( $value ) ) {
                $this->collect_source_refs( $value, $refs );
            }
        }
    }

    private function match_contract( $post_id, $package, $manual ) {
        $fields   = $this->available_acf_fields( $post_id );
        $contract = $this->contract_from_package( $package );
        $by_key   = [];
        foreach ( $fields as $field ) {
            if ( ! empty( $field['key'] ) ) {
                $by_key[ $field['key'] ] = $field['name'];
            }
        }
        $bindings = [];
        $required_missing = [];
        $optional_missing = [];
        foreach ( $contract as $alias => $item ) {
            $mapped = isset( $manual[ $alias ] ) ? sanitize_text_field( $manual[ $alias ] ) : '';
            if ( $mapped ) {
                foreach ( $fields as $candidate ) {
                    if ( $mapped === ( $candidate['field_id'] ?? '' ) || $mapped === ( $candidate['name'] ?? '' ) ) {
                        $bindings[ $alias ] = (string) ( $candidate['field_id'] ?? $candidate['name'] );
                        continue 2;
                    }
                }
            }
            if ( ! empty( $item['field_id'] ) ) {
                foreach ( $fields as $candidate ) {
                    if ( $item['field_id'] === ( $candidate['field_id'] ?? '' ) ) {
                        $bindings[ $alias ] = (string) $candidate['field_id'];
                        continue 2;
                    }
                }
            }
            if ( ! empty( $item['key'] ) && isset( $by_key[ $item['key'] ] ) ) {
                $candidate_name = $by_key[ $item['key'] ];
                $bindings[ $alias ] = (string) ( $fields[ $candidate_name ]['field_id'] ?? $candidate_name );
                continue;
            }
            if ( ! empty( $item['field'] ) && isset( $fields[ $item['field'] ] ) ) {
                $bindings[ $alias ] = (string) ( $fields[ $item['field'] ]['field_id'] ?? $item['field'] );
                continue;
            }
            if ( isset( $fields[ $alias ] ) ) {
                $bindings[ $alias ] = (string) ( $fields[ $alias ]['field_id'] ?? $alias );
                continue;
            }
            if ( ! empty( $item['required'] ) ) {
                $required_missing[] = $alias;
            } else {
                $optional_missing[] = $alias;
            }
        }
        return compact( 'bindings', 'required_missing', 'optional_missing' );
    }

    private function compile_blocks( $post_id, $package ) {
        $sections = isset( $package['sections'] ) && is_array( $package['sections'] ) ? $package['sections'] : [];
        if ( empty( $sections ) ) {
            return new WP_Error( 'npm9_design_sections', 'The Block Editor .9pm file has no sections.' );
        }
        $settings = isset( $package['settings'] ) && is_array( $package['settings'] ) ? $package['settings'] : [];
        $template = sanitize_key( $settings['template'] ?? 'default' );
        $root_class = 'npm9-design-root npm9-design-template-' . ( $template ?: 'default' );
        $inner = '';
        foreach ( $sections as $section ) {
            $inner .= $this->compile_section( $post_id, $section, $package, null );
        }
        return $this->group_block( $inner, $root_class, 'constrained' );
    }

    private function compile_section( $post_id, $section, $package, $context ) {
        if ( ! is_array( $section ) ) {
            return '';
        }
        $layout = sanitize_key( $section['layout'] ?? $section['container'] ?? 'stack' );
        $classes = [ 'npm9-design-section', 'npm9-layout-' . $layout ];
        if ( ! empty( $section['className'] ) ) {
            $classes[] = $this->safe_classes( $section['className'] );
        }
        if ( ! empty( $section['tone'] ) ) {
            $classes[] = 'npm9-tone-' . sanitize_html_class( $section['tone'] );
        }
        $inner = '';
        if ( ! empty( $section['title'] ) ) {
            $title = $this->resolve_source( $post_id, $section['title'], $package, $context );
            $inner .= $this->nine_element_block( 'heading', [ 'content' => $this->scalar_text( $title ), 'headingLevel' => intval( $section['headingLevel'] ?? 2 ), 'preset' => sanitize_key( $section['preset'] ?? 'inherit' ) ] );
        }

        if ( ! empty( $section['repeat'] ) && is_array( $section['repeat'] ) ) {
            $source = $section['repeat']['source'] ?? $section['repeat_source'] ?? '';
            $rows = $this->resolve_source( $post_id, $source, $package, $context );
            $cards = '';
            foreach ( is_array( $rows ) ? $rows : [] as $row ) {
                $card_inner = '';
                foreach ( (array) ( $section['repeat']['template'] ?? [] ) as $element ) {
                    $card_inner .= $this->compile_element( $post_id, $element, $package, is_array( $row ) ? $row : [ 'value' => $row ] );
                }
                if ( $card_inner ) {
                    $cards .= $this->group_block( $card_inner, 'npm9-design-repeat-card npm9-design-card-shell', 'constrained' );
                }
            }
            $classes[] = 'npm9-design-grid';
            $classes[] = 'npm9-grid-' . max( 1, min( 6, intval( $section['columns'] ?? 3 ) ) );
            $inner .= $cards;
        } elseif ( 'columns' === $layout && ! empty( $section['columns'] ) && is_array( $section['columns'] ) ) {
            $columns = '';
            foreach ( $section['columns'] as $column ) {
                $column_inner = '';
                foreach ( (array) ( $column['elements'] ?? [] ) as $element ) {
                    $column_inner .= $this->compile_element( $post_id, $element, $package, $context );
                }
                $columns .= $this->column_block( $column_inner, $this->safe_classes( $column['className'] ?? '' ) );
            }
            $inner .= $this->columns_block( $columns, 'npm9-design-columns' );
        } else {
            foreach ( (array) ( $section['elements'] ?? [] ) as $element ) {
                $inner .= $this->compile_element( $post_id, $element, $package, $context );
            }
        }

        if ( '' === trim( $inner ) && ! empty( $section['hide_if_empty'] ) ) {
            return '';
        }
        return $this->group_block( $inner, trim( implode( ' ', array_filter( $classes ) ) ), 'constrained' );
    }

    private function compile_element( $post_id, $element, $package, $context ) {
        if ( ! is_array( $element ) ) {
            return '';
        }
        $type = sanitize_key( $element['type'] ?? 'text' );
        if ( 'tabs' === $type ) {
            $lines = [];
            foreach ( (array) ( $element['tabs'] ?? [] ) as $tab ) {
                $label = sanitize_text_field( $tab['title'] ?? 'Tab' );
                $value = $this->resolve_source( $post_id, $tab['source'] ?? $tab['value'] ?? '', $package, $context );
                if ( '' === $this->scalar_text( $value ) && ! empty( $tab['hide_if_empty'] ) ) {
                    continue;
                }
                $tab_text = preg_replace( '/\r\n|\r|\n/', '<br>', $this->scalar_text( $value ) );
                $lines[] = $label . ' | ' . $tab_text;
            }
            return $this->nine_element_block( 'tabs', [ 'items' => implode( "\n", $lines ), 'preset' => sanitize_key( $element['preset'] ?? 'inherit' ) ] );
        }

        if ( 'gallery' === $type ) {
            $value = $this->resolve_source( $post_id, $element['source'] ?? '', $package, $context );
            $items = is_array( $value ) ? $value : [];
            $inner = '';
            foreach ( $items as $media ) {
                $info = $this->media_info( $media );
                if ( empty( $info['url'] ) ) {
                    continue;
                }
                $inner .= $this->nine_element_block( 'image', [
                    'mediaId'  => $info['id'],
                    'mediaUrl' => $info['url'],
                    'alt'      => $info['alt'],
                    'caption'  => $info['caption'],
                    'preset'   => sanitize_key( $element['preset'] ?? 'inherit' ),
                ] );
            }
            if ( ! $inner ) {
                return '';
            }
            return $this->group_block( $inner, 'npm9-design-gallery npm9-design-grid npm9-grid-' . max( 1, min( 6, intval( $element['columns'] ?? 3 ) ) ), 'constrained' );
        }

        if ( 'button' === $type ) {
            $label = $this->resolve_source( $post_id, $element['source'] ?? $element['label'] ?? 'Learn more', $package, $context );
            $url   = $this->resolve_source( $post_id, $element['url_source'] ?? $element['url'] ?? '', $package, $context );
            if ( ! $url ) {
                return '';
            }
            return $this->button_block( $this->scalar_text( $label ), esc_url_raw( $this->scalar_text( $url ) ), $this->safe_classes( $element['className'] ?? '' ) );
        }

        if ( 'separator' === $type ) {
            return "<!-- wp:separator --><hr class=\"wp-block-separator has-alpha-channel-opacity\"/><!-- /wp:separator -->";
        }
        if ( 'spacer' === $type ) {
            $height = max( 8, min( 200, intval( $element['height'] ?? 24 ) ) );
            return '<!-- wp:spacer {"height":"' . $height . 'px"} --><div style="height:' . $height . 'px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->';
        }

        $value = $this->resolve_source( $post_id, array_key_exists( 'source', $element ) ? $element['source'] : ( $element['value'] ?? '' ), $package, $context );
        $text  = $this->scalar_text( $value );
        if ( '' === $text && ! empty( $element['hide_if_empty'] ) ) {
            return '';
        }
        $preset = sanitize_key( $element['preset'] ?? 'inherit' );
        $attrs = [ 'preset' => $preset ];
        switch ( $type ) {
            case 'heading':
                $attrs['content'] = $text;
                $attrs['headingLevel'] = max( 1, min( 6, intval( $element['level'] ?? $element['headingLevel'] ?? 2 ) ) );
                return $this->nine_element_block( 'heading', $attrs );
            case 'paragraph':
            case 'text':
                $attrs['content'] = $text;
                return $this->nine_element_block( $type, $attrs );
            case 'list':
            case 'icon-list':
                $attrs['items'] = is_array( $value ) ? implode( "\n", array_map( [ $this, 'scalar_text' ], $value ) ) : $text;
                $attrs['listStyle'] = 'ol' === ( $element['listStyle'] ?? '' ) ? 'ol' : 'ul';
                $attrs['icon'] = sanitize_text_field( $element['icon'] ?? '✓' );
                return $this->nine_element_block( $type, $attrs );
            case 'image':
            case 'audio':
            case 'video':
                $info = $this->media_info( $value );
                if ( empty( $info['url'] ) ) {
                    return '';
                }
                $attrs['mediaId']  = $info['id'];
                $attrs['mediaUrl'] = $info['url'];
                $attrs['alt']      = $info['alt'];
                $attrs['caption']  = $info['caption'];
                return $this->nine_element_block( $type, $attrs );
            default:
                $attrs['content'] = $text;
                return $this->nine_element_block( 'text', $attrs );
        }
    }

    private function resolve_source( $post_id, $source, $package, $context = null ) {
        if ( is_array( $source ) && array_key_exists( 'value', $source ) ) {
            return $source['value'];
        }
        $path = '';
        $alias = '';
        if ( is_array( $source ) ) {
            $alias = $source['acf'] ?? $source['field'] ?? '';
            $path  = sanitize_text_field( $source['path'] ?? '' );
        } else {
            $alias = (string) $source;
        }
        if ( 0 === strpos( $alias, '@post.' ) ) {
            switch ( substr( $alias, 6 ) ) {
                case 'title': return get_the_title( $post_id );
                case 'excerpt': return get_post_field( 'post_excerpt', $post_id, 'raw' );
                case 'content': return get_post_field( 'post_content', $post_id, 'raw' );
                case 'permalink': return get_permalink( $post_id );
                case 'featured_image': return (int) get_post_thumbnail_id( $post_id );
            }
        }
        $alias = sanitize_key( preg_replace( '/^acf:/', '', $alias ) );
        if ( is_array( $context ) && array_key_exists( $alias, $context ) ) {
            $value = $context[ $alias ];
        } else {
            $bindings = isset( $package['bindings'] ) && is_array( $package['bindings'] ) ? $package['bindings'] : [];
            $field_ref = (string) ( $bindings[ $alias ] ?? $alias );
            if ( false !== strpos( $field_ref, ':' ) && class_exists( 'Nine_Post_Manager_9CF' ) ) {
                $value = Nine_Post_Manager_9CF::instance()->value_for_id( $post_id, $field_ref );
            } else {
                $field = sanitize_key( $field_ref );
                if ( function_exists( 'get_field' ) ) {
                    $value = get_field( $field, $post_id, false );
                } else {
                    $value = get_post_meta( $post_id, $field, true );
                }
            }
        }
        if ( $path && is_array( $value ) ) {
            foreach ( explode( '.', $path ) as $part ) {
                if ( ! is_array( $value ) || ! array_key_exists( $part, $value ) ) {
                    return '';
                }
                $value = $value[ $part ];
            }
        }
        return $value;
    }

    private function scalar_text( $value ) {
        if ( is_bool( $value ) ) {
            return $value ? 'Yes' : 'No';
        }
        if ( is_scalar( $value ) ) {
            return wp_kses_post( (string) $value );
        }
        if ( is_array( $value ) ) {
            $parts = [];
            foreach ( $value as $item ) {
                if ( is_scalar( $item ) ) {
                    $parts[] = (string) $item;
                } elseif ( is_array( $item ) && isset( $item['label'] ) ) {
                    $parts[] = (string) $item['label'];
                } elseif ( is_array( $item ) && isset( $item['title'] ) ) {
                    $parts[] = (string) $item['title'];
                }
            }
            return wp_kses_post( implode( ', ', $parts ) );
        }
        return '';
    }

    private function media_info( $value ) {
        $id = 0;
        $url = '';
        $alt = '';
        $caption = '';
        if ( is_numeric( $value ) ) {
            $id = absint( $value );
        } elseif ( is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ) {
            $url = esc_url_raw( $value );
            $id = attachment_url_to_postid( $url );
        } elseif ( is_array( $value ) ) {
            $id = absint( $value['ID'] ?? $value['id'] ?? $value['attachment_id'] ?? 0 );
            $url = esc_url_raw( $value['url'] ?? '' );
            $alt = sanitize_text_field( $value['alt'] ?? '' );
            $caption = sanitize_text_field( $value['caption'] ?? '' );
        }
        if ( $id ) {
            $url = wp_get_attachment_url( $id ) ?: $url;
            if ( ! $alt ) {
                $alt = get_post_meta( $id, '_wp_attachment_image_alt', true );
            }
            if ( ! $caption ) {
                $caption = wp_get_attachment_caption( $id );
            }
        }
        return [ 'id' => $id, 'url' => $url, 'alt' => $alt, 'caption' => $caption ];
    }

    private function nine_element_block( $type, $attrs ) {
        $attrs = array_merge( [ 'elementType' => $type ], $attrs );
        return '<!-- wp:nine/elements ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ' /-->';
    }

    private function group_block( $inner, $class_name = '', $layout = 'constrained' ) {
        $class_name = trim( $this->safe_classes( $class_name ) );
        $attrs = [ 'layout' => [ 'type' => 'constrained' === $layout ? 'constrained' : 'default' ] ];
        if ( $class_name ) {
            $attrs['className'] = $class_name;
        }
        return '<!-- wp:group ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES ) . ' --><div class="wp-block-group' . ( $class_name ? ' ' . esc_attr( $class_name ) : '' ) . '">' . $inner . '</div><!-- /wp:group -->';
    }

    private function columns_block( $inner, $class_name = '' ) {
        $class_name = trim( $this->safe_classes( $class_name ) );
        $attrs = $class_name ? [ 'className' => $class_name ] : [];
        return '<!-- wp:columns ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES ) . ' --><div class="wp-block-columns' . ( $class_name ? ' ' . esc_attr( $class_name ) : '' ) . '">' . $inner . '</div><!-- /wp:columns -->';
    }

    private function column_block( $inner, $class_name = '' ) {
        $class_name = trim( $this->safe_classes( $class_name ) );
        $attrs = $class_name ? [ 'className' => $class_name ] : [];
        return '<!-- wp:column ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES ) . ' --><div class="wp-block-column' . ( $class_name ? ' ' . esc_attr( $class_name ) : '' ) . '">' . $inner . '</div><!-- /wp:column -->';
    }

    private function button_block( $label, $url, $class_name = '' ) {
        $class_name = trim( $this->safe_classes( $class_name ) );
        $attrs = $class_name ? [ 'className' => $class_name ] : [];
        return '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES ) . ' --><div class="wp-block-button' . ( $class_name ? ' ' . esc_attr( $class_name ) : '' ) . '"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
    }

    private function safe_classes( $classes ) {
        $out = [];
        foreach ( preg_split( '/\s+/', (string) $classes ) as $class ) {
            $class = sanitize_html_class( $class );
            if ( $class ) {
                $out[] = $class;
            }
        }
        return implode( ' ', array_unique( $out ) );
    }

    private function apply_elementor( $post_id, $package ) {
        $elementor = isset( $package['elementor'] ) && is_array( $package['elementor'] ) ? $package['elementor'] : [];
        $content = isset( $elementor['content'] ) && is_array( $elementor['content'] ) ? $elementor['content'] : [];
        if ( empty( $content ) ) {
            return new WP_Error( 'npm9_elementor_content', 'The Elementor package does not contain a content array.' );
        }
        $resolved = $this->resolve_elementor_placeholders( $content, $post_id, $package );
        $settings = isset( $elementor['page_settings'] ) && is_array( $elementor['page_settings'] ) ? $this->resolve_elementor_placeholders( $elementor['page_settings'], $post_id, $package ) : [];

        update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
        update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $resolved, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) );
        update_post_meta( $post_id, '_elementor_page_settings', $settings );
        if ( defined( 'ELEMENTOR_VERSION' ) ) {
            update_post_meta( $post_id, '_elementor_version', ELEMENTOR_VERSION );
        }
        if ( class_exists( '\\Elementor\\Plugin' ) ) {
            try {
                $plugin = \Elementor\Plugin::$instance;
                if ( isset( $plugin->files_manager ) && method_exists( $plugin->files_manager, 'clear_cache' ) ) {
                    $plugin->files_manager->clear_cache();
                }
            } catch ( \Throwable $e ) {
                // Experimental integration: do not turn a cache refresh problem into a fatal error.
            }
        }
        return true;
    }

    private function resolve_elementor_placeholders( $value, $post_id, $package ) {
        if ( is_array( $value ) ) {
            foreach ( $value as $key => $item ) {
                $value[ $key ] = $this->resolve_elementor_placeholders( $item, $post_id, $package );
            }
            return $value;
        }
        if ( ! is_string( $value ) || ( false === strpos( $value, '{{field:' ) && false === strpos( $value, '{{acf:' ) ) ) {
            return $value;
        }
        if ( preg_match( '/^\{\{(?:field|acf):([a-zA-Z0-9_-]+)\}\}$/', $value, $m ) ) {
            return $this->resolve_source( $post_id, sanitize_key( $m[1] ), $package, null );
        }
        return preg_replace_callback( '/\{\{(?:field|acf):([a-zA-Z0-9_-]+)\}\}/', function( $m ) use ( $post_id, $package ) {
            return $this->scalar_text( $this->resolve_source( $post_id, sanitize_key( $m[1] ), $package, null ) );
        }, $value );
    }
}
