<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Nine_Post_Manager {
    private static $instance = null;
    private $menu_slug = 'nine-post-manager';
    private $nonce_action = 'npm9_action';
    private $snapshot_limit = 5;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'native_editor_recovery_assets' ], 99 );
        add_action( 'add_meta_boxes', [ $this, 'register_native_recovery_metabox' ], 99 );
        add_action( 'wp_enqueue_scripts', [ $this, 'frontend_assets' ] );
        add_action( 'wp_footer', [ $this, 'frontend_toolbox' ] );
        add_action( 'before_delete_post', [ $this, 'delete_snapshots' ] );
        add_action( 'admin_post_npm9_workspace', [ $this, 'render_standalone_workspace' ] );

        $ajax = [
            'search_posts'          => 'ajax_search_posts',
            'load_post'             => 'ajax_load_post',
            'save_post'             => 'ajax_save_post',
            'export_package'        => 'ajax_export_package',
            'preview_import'        => 'ajax_preview_import',
            'apply_import'          => 'ajax_apply_import',
            'preview_create_import' => 'ajax_preview_create_import',
            'create_from_import'    => 'ajax_create_from_import',
            'replace_image'         => 'ajax_replace_image',
            'restore_snapshot'      => 'ajax_restore_snapshot',
            'native_recovery_save'  => 'ajax_native_recovery_save',
        ];
        foreach ( $ajax as $action => $method ) {
            add_action( 'wp_ajax_npm9_' . $action, [ $this, $method ] );
        }
    }

    public function admin_menu() {
        add_menu_page(
            __( '9 Post Editor', 'nine-code-data' ),
            __( '9 Post Editor', 'nine-code-data' ),
            'edit_posts',
            $this->menu_slug,
            [ $this, 'render_admin_page' ],
            'dashicons-edit-page',
            25
        );
    }

    public function admin_assets( $hook ) {
        if ( 'toplevel_page_' . $this->menu_slug !== $hook ) {
            return;
        }
        $this->enqueue_workspace_assets();
    }

    /**
     * Keep a Data Manager-owned recovery path on the normal WordPress post screen.
     * This is intentionally scoped to post.php/post-new.php and only neutralizes
     * stale 9CODE focus classes; it does not unhide provider-controlled fields.
     */
    public function native_editor_recovery_assets( $hook ) {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }
        global $post;
        $post_id = $post instanceof WP_Post ? (int) $post->ID : ( isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0 );
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        wp_enqueue_style( 'npm9-admin', NPM9_URL . 'assets/admin.css', [], NPM9_VERSION );
        wp_enqueue_script( 'npm9-native-recovery', NPM9_URL . 'assets/native-editor-recovery.js', [], NPM9_VERSION, true );
        wp_localize_script( 'npm9-native-recovery', 'NPM9NativeRecovery', [
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( $this->nonce_action ),
            'postId'     => $post_id,
            'managerUrl' => admin_url( 'admin.php?page=' . $this->menu_slug . '&post_id=' . $post_id ),
        ] );
    }

    public function register_native_recovery_metabox() {
        $post_types = get_post_types( [ 'show_ui' => true ], 'objects' );
        $post_types = apply_filters( 'nine10_data_editable_post_types', $post_types, 'native-post-recovery' );
        foreach ( (array) $post_types as $post_type ) {
            if ( ! is_object( $post_type ) || 'attachment' === $post_type->name ) { continue; }
            add_meta_box(
                'npm9-native-content-meta-recovery',
                __( '9 Data - Post Content & Metadata', 'nine-code-data' ),
                [ $this, 'render_native_recovery_metabox' ],
                $post_type->name,
                'normal',
                'high'
            );
        }
    }

    public function render_native_recovery_metabox( $post ) {
        if ( ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) { return; }
        $meta = $this->discover_meta_fields( $post->ID );
        $ordinary = array_values( array_filter( $meta, static function( $row ) { return empty( $row['system'] ); } ) );
        $protected = array_values( array_filter( $meta, static function( $row ) { return ! empty( $row['system'] ); } ) );
        ?>
        <div class="npm9-native-recovery" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
            <p><strong><?php esc_html_e( 'Emergency-safe editing path.', 'nine-code-data' ); ?></strong> <?php esc_html_e( 'This panel exposes the real WordPress post_content and stored post metadata even if another editor layer is covering the normal canvas.', 'nine-code-data' ); ?></p>
            <p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '&post_id=' . $post->ID ) ); ?>"><?php esc_html_e( 'Open full 9 Data Post Editor', 'nine-code-data' ); ?></a></p>
            <label class="npm9-field-label" for="npm9-native-recovery-content"><?php esc_html_e( 'Post Content', 'nine-code-data' ); ?></label>
            <textarea id="npm9-native-recovery-content" class="npm9-native-recovery-content"><?php echo esc_textarea( $post->post_content ); ?></textarea>
            <div class="npm9-native-recovery-meta">
                <h3><?php esc_html_e( 'Post Metadata', 'nine-code-data' ); ?></h3>
                <?php if ( $ordinary ) : ?>
                    <?php foreach ( $ordinary as $row ) : ?>
                        <label class="npm9-native-recovery-meta-row" data-meta-key="<?php echo esc_attr( $row['key'] ); ?>">
                            <span><code><?php echo esc_html( $row['key'] ); ?></code></span>
                            <textarea><?php echo esc_textarea( $this->meta_value_for_editor( $row['value'] ) ); ?></textarea>
                        </label>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="description"><?php esc_html_e( 'No ordinary custom metadata is currently stored on this post.', 'nine-code-data' ); ?></p>
                <?php endif; ?>
                <?php if ( $protected ) : ?>
                    <details class="npm9-native-recovery-protected">
                        <summary><?php echo esc_html( sprintf( __( 'Protected / technical metadata (%d)', 'nine-code-data' ), count( $protected ) ) ); ?></summary>
                        <p class="description"><?php esc_html_e( 'Visible for diagnosis. These private/system keys are not changed by this recovery panel.', 'nine-code-data' ); ?></p>
                        <?php foreach ( $protected as $row ) : ?>
                            <label class="npm9-native-recovery-meta-row is-readonly">
                                <span><code><?php echo esc_html( $row['key'] ); ?></code></span>
                                <textarea readonly><?php echo esc_textarea( $this->meta_value_for_editor( $row['value'] ) ); ?></textarea>
                            </label>
                        <?php endforeach; ?>
                    </details>
                <?php endif; ?>
            </div>
            <div class="npm9-native-recovery-actions">
                <button type="button" class="button button-primary" id="npm9-native-recovery-save"><?php esc_html_e( 'Save Post Content & Metadata', 'nine-code-data' ); ?></button>
                <button type="button" class="button" id="npm9-native-recovery-restore"><?php esc_html_e( 'Restore Native Editor Visibility', 'nine-code-data' ); ?></button>
                <span id="npm9-native-recovery-status" role="status" aria-live="polite"></span>
            </div>
        </div>
        <?php
    }

    private function meta_value_for_editor( $value ) {
        if ( is_array( $value ) || is_object( $value ) ) {
            return wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        }
        return (string) $value;
    }

    public function ajax_native_recovery_save() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify_ajax( $post_id );
        $content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
        $meta_json = isset( $_POST['meta'] ) ? wp_unslash( $_POST['meta'] ) : '{}';
        $meta = json_decode( $meta_json, true );
        if ( ! is_array( $meta ) ) { $meta = []; }
        $safe_meta = [];
        foreach ( $meta as $key => $value ) {
            $key = sanitize_key( $key );
            if ( ! $key || 0 === strpos( $key, '_' ) ) { continue; }
            $safe_meta[ $key ] = $value;
        }
        $this->create_snapshot( $post_id, 'Before native editor recovery save' );
        $result = $this->apply_payload( $post_id, [
            'post' => [ 'content' => $content ],
            'meta' => $safe_meta,
        ], false );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }
        wp_send_json_success( [
            'message' => 'Post Content and Metadata saved.',
            'modifiedGmt' => get_post_modified_time( 'c', true, $post_id ),
        ] );
    }

    private function enqueue_workspace_assets() {
        wp_enqueue_media();
        wp_enqueue_style( 'npm9-admin', NPM9_URL . 'assets/admin.css', [], NPM9_VERSION );
        $app_deps = [ 'jquery' ];
        if ( class_exists( 'Nine_Code_Visual_Editor', false ) ) {
            // Visual (WYSIWYG) editor for the Post Content tab; the code textarea remains the fallback.
            Nine_Code_Visual_Editor::instance()->enqueue();
            $app_deps[] = Nine_Code_Visual_Editor::HANDLE;
        }
        wp_enqueue_script( 'npm9-app', NPM9_URL . 'assets/app.js', $app_deps, NPM9_VERSION, true );
        wp_localize_script( 'npm9-app', 'NPM9', $this->js_config() );
    }

    public function render_standalone_workspace() {
        $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
        if ( ! is_user_logged_in() || ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'You do not have permission to edit this post.', 'nine-code-data' ) );
        }
        check_admin_referer( 'npm9_workspace_' . $post_id );
        $this->enqueue_workspace_assets();
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_style( 'common' );
        wp_enqueue_style( 'forms' );
        wp_enqueue_style( 'buttons' );
        nocache_headers();
        header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
        ?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><?php wp_print_styles(); wp_print_head_scripts(); ?></head><body class="wp-admin npm9-standalone-body"><main class="npm9-standalone-main"><?php $this->render_admin_page(); ?></main><?php wp_print_footer_scripts(); ?></body></html><?php
        exit;
    }

    public function frontend_assets() {
        $theme_launcher = function_exists( 'nine10_data_theme_launcher_available' ) && nine10_data_theme_launcher_available();
        if ( $theme_launcher ) { return; }
        if ( ! is_user_logged_in() || ! is_singular() ) {
            return;
        }
        $post_id = get_queried_object_id();
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        wp_enqueue_style( 'npm9-front', NPM9_URL . 'assets/frontend.css', [], NPM9_VERSION );
        wp_enqueue_script( 'npm9-front', NPM9_URL . 'assets/frontend.js', [ 'jquery' ], NPM9_VERSION, true );
        $cfg = $this->js_config();
        $cfg['currentPostId'] = $post_id;
        $cfg['managerUrl'] = admin_url( 'admin.php?page=' . $this->menu_slug . '&post_id=' . $post_id );
        $cfg['workspaceUrl'] = wp_nonce_url( admin_url( 'admin-post.php?action=npm9_workspace&post_id=' . $post_id ), 'npm9_workspace_' . $post_id );
        if ( ! empty( $cfg['categoryManagerActive'] ) ) {
            $cfg['categoryManagerPostUrl'] = admin_url( 'admin.php?page=nine-category-manager&post_id=' . $post_id );
        }
        wp_localize_script( 'npm9-front', 'NPM9Front', $cfg );
    }

    private function js_config() {
        $category_manager_active = defined( 'NINECM_VERSION' ) || class_exists( 'NineCM_Core' );
        return [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( $this->nonce_action ),
            'categoryManagerActive' => $category_manager_active,
            'categoryManagerUrl' => $category_manager_active ? admin_url( 'admin.php?page=nine-category-manager' ) : '',
            'renderStyles' => class_exists( 'Nine_Post_Manager_Renderer' ) ? Nine_Post_Manager_Renderer::instance()->all_styles() : [],
            'strings' => [
                'loading' => __( 'Loading…', 'nine-code-data' ),
                'saved'   => __( 'Post updated.', 'nine-code-data' ),
                'error'   => __( 'Something went wrong.', 'nine-code-data' ),
            ],
        ];
    }

    public function render_admin_page() {
        $initial_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
        // A custom-role user may legitimately edit a CPT item without holding the generic
        // edit_posts capability. The full-screen front-end workspace follows object capability.
        if ( ! current_user_can( 'edit_posts' ) && ( ! $initial_id || ! current_user_can( 'edit_post', $initial_id ) ) ) {
            wp_die( esc_html__( 'You do not have permission to use this screen.', 'nine-code-data' ) );
        }
        $post_types = get_post_types( [ 'show_ui' => true ], 'objects' );
        $post_types = apply_filters( 'nine10_data_editable_post_types', $post_types, 'post-editor' );
        if ( ! is_array( $post_types ) ) { $post_types = array(); }
        ?>
        <div class="wrap npm9-wrap" data-initial-post="<?php echo esc_attr( $initial_id ); ?>">
            <header class="npm9-header">
                <div>
                    <h1>9 Post Editor</h1>
                    <p><?php esc_html_e( 'WordPress-native mobile post workspace. Edit core post data, Gutenberg/9 Elements, media, taxonomies and post-specific plugin controls without requiring ACF. Includes 9CF AI and portable post/category backups.', 'nine-code-data' ); ?></p>
                </div>
                <span class="npm9-version">v<?php echo esc_html( NPM9_VERSION ); ?></span>
            </header>

            <nav class="npm9-sister-nav" aria-label="Nine site management">
                <span class="npm9-sister-active">9 Post Editor · Content</span>
                <?php if ( defined( 'NINECM_VERSION' ) || class_exists( 'NineCM_Core' ) ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=nine-category-manager' . ( $initial_id ? '&post_id=' . $initial_id : '' ) ) ); ?>">9 Category Manager · Structure</a>
                <?php else : ?>
                    <span class="npm9-sister-missing">9 Category Manager not active</span>
                <?php endif; ?>
            </nav>

            <section class="npm9-card npm9-finder">
                <div class="npm9-grid npm9-grid-filter">
                    <label>
                        <span><?php esc_html_e( 'Post type', 'nine-code-data' ); ?></span>
                        <select id="npm9-post-type">
                            <?php foreach ( $post_types as $pt ) : if ( 'attachment' === $pt->name ) continue; ?>
                                <option value="<?php echo esc_attr( $pt->name ); ?>"><?php echo esc_html( $pt->labels->singular_name . ' (' . $pt->name . ')' ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e( 'Category / term', 'nine-code-data' ); ?></span>
                        <select id="npm9-term"><option value=""><?php esc_html_e( 'All', 'nine-code-data' ); ?></option></select>
                    </label>
                    <label class="npm9-grow">
                        <span><?php esc_html_e( 'Search', 'nine-code-data' ); ?></span>
                        <input type="search" id="npm9-search" placeholder="Title, ID or keyword">
                    </label>
                    <button type="button" class="button button-primary" id="npm9-find"><?php esc_html_e( 'Find posts', 'nine-code-data' ); ?></button>
                </div>
                <div class="npm9-create-line">
                    <button type="button" class="button" id="npm9-create-import-btn"><?php esc_html_e( 'Create New Post from JSON / Markdown', 'nine-code-data' ); ?></button>
                    <small><?php esc_html_e( 'Use an AI template or backup as the starting point for a completely new post.', 'nine-code-data' ); ?></small>
                    <input type="file" id="npm9-create-import-file" accept=".json,.md,.markdown,application/json,text/markdown,text/plain" hidden>
                </div>
                <div id="npm9-create-preview" class="npm9-create-preview" hidden></div>
                <div id="npm9-results" class="npm9-results"></div>
            </section>

            <?php if ( function_exists( 'acf_get_field_groups' ) ) : ?>
            <details class="npm9-card npm9-deploy-card npm9-major-details" id="npm9-deploy-card">
                <summary class="npm9-major-summary"><span><strong><?php esc_html_e( 'Quick Deploy · ACF Bridge / Legacy', 'nine-code-data' ); ?></strong><small><?php esc_html_e( 'Optional ACF schema import for existing ACF workflows', 'nine-code-data' ); ?></small></span><span class="npm9-badge npm9-badge-new">NEW · Instant Render</span></summary>
                <div class="npm9-major-body"><p class="description"><?php esc_html_e( 'Keep this for sites that already use ACF. 9CF is now the primary portable field contract; ACF remains an optional bridge for existing field groups and specialist Elementor workflows.', 'nine-code-data' ); ?></p>
                <div class="npm9-deploy-grid">
                    <label><span><?php esc_html_e( 'New page/post title', 'nine-code-data' ); ?></span><input type="text" id="npm9-deploy-title" placeholder="e.g. Lecturer Profile"></label>
                    <label><span><?php esc_html_e( 'Target post type', 'nine-code-data' ); ?></span><select id="npm9-deploy-post-type">
                        <?php foreach ( $post_types as $pt ) : if ( 'attachment' === $pt->name ) continue; ?>
                            <option value="<?php echo esc_attr( $pt->name ); ?>" <?php selected( 'page', $pt->name ); ?>><?php echo esc_html( $pt->labels->singular_name . ' (' . $pt->name . ')' ); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label><span><?php esc_html_e( 'Basic display style', 'nine-code-data' ); ?></span><select id="npm9-deploy-style"></select></label>
                    <label><span><?php esc_html_e( 'Initial status', 'nine-code-data' ); ?></span><select id="npm9-deploy-status"><option value="draft">Draft</option><option value="publish">Publish</option><option value="pending">Pending review</option><option value="private">Private</option></select></label>
                </div>
                <details class="npm9-render-colors npm9-deploy-colors"><summary><strong><?php esc_html_e( 'Optional colours', 'nine-code-data' ); ?></strong> — <?php esc_html_e( 'leave blank to use the selected style defaults', 'nine-code-data' ); ?></summary>
                    <div class="npm9-color-grid">
                        <label><span>Primary</span><input type="text" id="npm9-deploy-color-primary" placeholder="#17345c"></label>
                        <label><span>Accent</span><input type="text" id="npm9-deploy-color-accent" placeholder="#9a2233"></label>
                        <label><span>Background</span><input type="text" id="npm9-deploy-color-secondary" placeholder="#eef3f8"></label>
                        <label><span>Surface</span><input type="text" id="npm9-deploy-color-surface" placeholder="#ffffff"></label>
                        <label><span>Text</span><input type="text" id="npm9-deploy-color-text" placeholder="#172033"></label>
                        <label><span>Muted</span><input type="text" id="npm9-deploy-color-muted" placeholder="#64748b"></label>
                    </div>
                </details>
                <div class="npm9-deploy-actions">
                    <button type="button" class="button button-primary" id="npm9-deploy-acf-btn"><?php esc_html_e( 'Choose ACF JSON & Create', 'nine-code-data' ); ?></button>
                    <button type="button" class="button" id="npm9-deploy-template-btn"><?php esc_html_e( 'Download AI Deploy Template', 'nine-code-data' ); ?></button>
                    <input type="file" id="npm9-deploy-acf-file" accept=".json,application/json" hidden>
                    <small><?php esc_html_e( 'Administrator-only because this imports an ACF schema. The imported group is allocated to the selected post type.', 'nine-code-data' ); ?></small>
                </div>
                <div id="npm9-deploy-result" class="npm9-deploy-result" hidden></div>
                </div>
            </details>
            <?php endif; ?>

            <div id="npm9-editor-shell" hidden>
                <div class="npm9-sticky-actions">
                    <div class="npm9-current-title" id="npm9-current-title"></div>
                    <div class="npm9-quick-row" id="npm9-quick-row" hidden>
                        <a class="button button-small" id="npm9-view-live" target="_blank" rel="noopener">View Live</a>
                        <a class="button button-small" id="npm9-open-gutenberg" target="_blank" rel="noopener">Open Gutenberg</a>
                        <a class="button button-small npm9-structure-link" id="npm9-structure-link" href="<?php echo esc_url( admin_url( 'admin.php?page=nine-category-manager' ) ); ?>" <?php if ( ! ( defined( 'NINECM_VERSION' ) || class_exists( 'NineCM_Core' ) ) ) echo 'hidden'; ?>>Categories / Structure</a>
                        <button type="button" class="button button-small" id="npm9-copy-link">Copy Link</button>
                        <button type="button" class="button button-small" id="npm9-back-results">Back to Results</button>
                    </div>
                    <div class="npm9-action-row">
                        <button type="button" class="button button-primary button-hero" id="npm9-save"><?php esc_html_e( 'Update Post', 'nine-code-data' ); ?></button>
                        <button type="button" class="button npm9-publish-now" id="npm9-publish-now" hidden><?php esc_html_e( 'Publish Now', 'nine-code-data' ); ?></button>
                        <button type="button" class="button" id="npm9-backup-menu-btn"><?php esc_html_e( 'Backup / Restore', 'nine-code-data' ); ?></button>
                        <button type="button" class="button" id="npm9-export-menu-btn"><?php esc_html_e( 'AI / 9CF', 'nine-code-data' ); ?></button>
                        <button type="button" class="button" id="npm9-import-btn"><?php esc_html_e( 'Legacy Import', 'nine-code-data' ); ?></button>
                        <button type="button" class="button npm9-undo" id="npm9-undo" hidden><?php esc_html_e( 'Undo Last 9PM Change', 'nine-code-data' ); ?></button>
                    </div>
                </div>

                <input type="file" id="npm9-import-file" accept=".json,.md,.markdown,application/json,text/markdown,text/plain" hidden>


                <section class="npm9-card npm9-backup-panel" id="npm9-backup-panel" hidden>
                    <div class="npm9-section-head"><h2><?php esc_html_e( 'Post & Category Backup', 'nine-code-data' ); ?></h2><span class="npm9-badge npm9-badge-new">Native · ACF not required</span></div>
                    <p class="description"><?php esc_html_e( 'Create small portable backups containing only the selected post(s), their taxonomies/plugin post-meta and optional WordPress media. No theme, plugin or global site settings are included.', 'nine-code-data' ); ?></p>
                    <div class="npm9-backup-actions">
                        <button type="button" class="button button-primary npm9-native-backup-post" data-media="1">Back Up This Post + Images</button>
                        <button type="button" class="button npm9-native-backup-post" data-media="0">Back Up This Post · Data Only</button>
                    </div>
                    <details class="npm9-backup-category"><summary><strong>Back Up All Posts in a Category / Term</strong></summary>
                        <div class="npm9-backup-category-grid">
                            <label><span>Category / term</span><select id="npm9-backup-term"><option value="">Choose from this post’s taxonomies</option></select></label>
                            <label class="npm9-switch-line"><input type="checkbox" id="npm9-backup-category-media" checked> <span>Include images/media</span></label>
                            <button type="button" class="button" id="npm9-native-backup-category">Back Up Category / Term</button>
                        </div>
                    </details>
                    <details class="npm9-backup-import"><summary><strong>Import / Restore a .9post.zip Backup</strong></summary>
                        <div class="npm9-backup-import-grid">
                            <input type="file" id="npm9-native-backup-file" accept=".zip,.9post.zip,application/zip">
                            <label><span>Restore behaviour</span><select id="npm9-native-backup-mode"><option value="safe" selected>Safe restore (recommended)</option><option value="match_slug">Replace matching slug</option><option value="duplicate">Create new copies</option></select><small>Safe restore updates only posts previously restored from this source or the exact original post on the same site. It will not overwrite an unrelated same-slug post.</small></label>
                            <button type="button" class="button" id="npm9-native-backup-preview">Inspect Backup</button>
                        </div>
                        <div id="npm9-native-backup-result" class="npm9-backup-result" hidden></div>
                    </details>
                </section>

                <section class="npm9-card npm9-ninecf-panel" id="npm9-ninecf-panel" hidden>
                    <div class="npm9-section-head"><h2><?php esc_html_e( '9CF · AI Fill Workflow', 'nine-code-data' ); ?></h2><span class="npm9-badge npm9-badge-new">Gutenberg-first</span></div>
                    <p class="description"><?php esc_html_e( '9CF inventories fillable data already owned by WordPress, Gutenberg/9 Elements, taxonomies and post-specific plugin/meta fields. ACF is not required. Every field is numbered for human/AI tracking, but imports use stable field IDs so numbering can never corrupt the mapping.', 'nine-code-data' ); ?></p>
                    <div class="npm9-ninecf-actions">
                        <button type="button" class="button button-primary npm9-ninecf-export" data-mode="blank" data-format="9cf"><?php esc_html_e( 'Download Numbered 9CF AI Form', 'nine-code-data' ); ?></button>
                        <button type="button" class="button npm9-ninecf-export" data-mode="current" data-format="9cf"><?php esc_html_e( 'Download Current 9CF', 'nine-code-data' ); ?></button>
                        <button type="button" class="button npm9-ninecf-export" data-mode="blank" data-format="markdown"><?php esc_html_e( 'AI Form · Markdown', 'nine-code-data' ); ?></button>
                        <button type="button" class="button" id="npm9-ninecf-import"><?php esc_html_e( 'Import Filled 9CF', 'nine-code-data' ); ?></button>
                        <input type="file" id="npm9-ninecf-file" accept=".9cf,.json,.md,.markdown,application/json,text/plain" hidden>
                    </div>
                    <div class="npm9-ninecf-bridge">
                        <label class="npm9-switch-line"><input type="checkbox" id="npm9-ninecf-mirror"> <span><?php esc_html_e( '9CF Bridge Mirror for Elementor/custom-field consumers', 'nine-code-data' ); ?></span></label>
                        <button type="button" class="button" id="npm9-ninecf-sync"><?php esc_html_e( 'Sync Mirror Now', 'nine-code-data' ); ?></button>
                        <?php if ( function_exists( 'acf_get_field_groups' ) ) : ?><button type="button" class="button" id="npm9-ninecf-acf-bridge"><?php esc_html_e( 'Legacy ACF Bridge JSON', 'nine-code-data' ); ?></button><?php endif; ?>
                        <button type="button" class="button" id="npm9-legacy-export-toggle"><?php esc_html_e( 'Legacy Backup Exports', 'nine-code-data' ); ?></button>
                        <small><?php esc_html_e( 'The mirror copies values to stable 9CF meta keys. The optional ACF JSON maps those same keys for Elementor ACF Dynamic Tags. 9CF itself does not require ACF.', 'nine-code-data' ); ?></small>
                    </div>
                    <div id="npm9-ninecf-summary" class="npm9-ninecf-summary"></div>
                    <details class="npm9-ninecf-history-wrap" id="npm9-ninecf-history-wrap"><summary><?php esc_html_e( 'AI Import History', 'nine-code-data' ); ?></summary><div id="npm9-ninecf-history" class="npm9-ninecf-history"></div></details>
                </section>

                <section class="npm9-card npm9-export-panel" id="npm9-export-panel" hidden>
                    <div class="npm9-section-head"><h2><?php esc_html_e( 'Export and AI workflow', 'nine-code-data' ); ?></h2><span class="npm9-badge">Portable backup</span></div>
                    <div class="npm9-export-grid">
                        <button class="button npm9-export" data-format="json" data-mode="template">Empty AI Template — JSON</button>
                        <button class="button npm9-export" data-format="markdown" data-mode="template">Empty AI Template — Markdown</button>
                        <button class="button npm9-export" data-format="json" data-mode="backup">Current Backup — JSON</button>
                        <button class="button npm9-export" data-format="markdown" data-mode="backup">Current Backup — Markdown</button>
                        <button class="button npm9-export" data-format="csv" data-mode="backup">Field Report — Excel CSV</button>
                    </div>
                    <p class="description">AI templates retain the field structure and blank editable values. Backups preserve current values, including technical meta for emergency recovery.</p>
                </section>

                <?php if ( function_exists( 'acf_get_field_groups' ) ) : ?>
                <details class="npm9-card npm9-render-panel npm9-major-details" id="npm9-render-panel">
                    <summary class="npm9-major-summary"><span><strong><?php esc_html_e( 'ACF → Page Render', 'nine-code-data' ); ?></strong><small><?php esc_html_e( 'Instant presentation, reusable styles and colour controls', 'nine-code-data' ); ?></small></span><span class="npm9-render-status-badge" id="npm9-render-status">Off</span></summary>
                    <div class="npm9-major-body"><p class="description"><?php esc_html_e( 'Optional legacy presentation layer for sites that still use ACF. New v4 sites should normally use Gutenberg/9 Elements, Site Fields and .9pm designs instead.', 'nine-code-data' ); ?></p>
                    <div id="npm9-render-specialist" class="npm9-render-specialist" hidden></div>
                    <div class="npm9-render-grid">
                        <label class="npm9-switch-field"><span><?php esc_html_e( 'Instant ACF Render', 'nine-code-data' ); ?></span><label class="npm9-switch-line"><input type="checkbox" id="npm9-render-enabled"> <span><?php esc_html_e( 'Enable on this post', 'nine-code-data' ); ?></span></label></label>
                        <label><span><?php esc_html_e( 'Display style', 'nine-code-data' ); ?></span><select id="npm9-render-style"></select></label>
                        <label><span><?php esc_html_e( 'Where to render', 'nine-code-data' ); ?></span><select id="npm9-render-mode"><option value="after">After normal post content (safe default)</option><option value="before">Before normal post content</option><option value="replace">Replace normal content with ACF display</option></select></label>
                    </div>
                    <details class="npm9-render-colors"><summary><strong><?php esc_html_e( 'Edit Style Colours', 'nine-code-data' ); ?></strong> — <?php esc_html_e( 'optional per-post overrides', 'nine-code-data' ); ?></summary>
                        <div class="npm9-color-grid">
                            <label><span>Primary</span><input type="text" id="npm9-color-primary" placeholder="#17345c"></label>
                            <label><span>Accent</span><input type="text" id="npm9-color-accent" placeholder="#9a2233"></label>
                            <label><span>Background</span><input type="text" id="npm9-color-secondary" placeholder="#eef3f8"></label>
                            <label><span>Surface</span><input type="text" id="npm9-color-surface" placeholder="#ffffff"></label>
                            <label><span>Text</span><input type="text" id="npm9-color-text" placeholder="#172033"></label>
                            <label><span>Muted</span><input type="text" id="npm9-color-muted" placeholder="#64748b"></label>
                        </div>
                    </details>
                    <div class="npm9-render-actions">
                        <button type="button" class="button button-primary" id="npm9-render-save"><?php esc_html_e( 'Save Render Settings', 'nine-code-data' ); ?></button>
                        <button type="button" class="button" id="npm9-render-import-style"><?php esc_html_e( 'Import Style', 'nine-code-data' ); ?></button>
                        <input type="file" id="npm9-render-style-file" accept=".99gostyle,.99goalstyle,.json,application/json" hidden>
                        <button type="button" class="button" id="npm9-render-export-style"><?php esc_html_e( 'Export Style', 'nine-code-data' ); ?></button>
                        <button type="button" class="button" id="npm9-render-import-acf"><?php esc_html_e( 'Import ACF JSON to this Post Type', 'nine-code-data' ); ?></button>
                        <input type="file" id="npm9-render-acf-file" accept=".json,application/json" hidden>
                        <a class="button" id="npm9-render-view" target="_blank" rel="noopener"><?php esc_html_e( 'View Front End', 'nine-code-data' ); ?></a>
                    </div>
                    <p class="npm9-render-note"><?php esc_html_e( 'Imported .99gostyle packages use the same field-name contract as 99 ACF Go Builder. This panel does not rename ACF fields or keys.', 'nine-code-data' ); ?></p>
                    </div>
                </details>
                <?php endif; ?>

                <?php do_action( 'npm9_after_render_panel' ); ?>

                <section class="npm9-card" id="npm9-import-preview" hidden></section>
                <section class="npm9-phone-draft" id="npm9-phone-draft" hidden></section>
                <form id="npm9-editor" autocomplete="off"></form>
            </div>

            <div class="npm9-toast" id="npm9-toast" role="status" aria-live="polite"></div>
        </div>
        <?php
    }

    private function verify_ajax( $post_id = 0 ) {
        check_ajax_referer( $this->nonce_action, 'nonce' );
        if ( $post_id ) {
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
            }
        } elseif ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
        }
    }

    public function ajax_search_posts() {
        $this->verify_ajax();
        $post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : 'post';
        $search    = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $term      = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';

        $args = [
            'post_type'      => $post_type,
            'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future' ],
            'posts_per_page' => 50,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ];
        if ( $search ) {
            if ( 1 === preg_match( '/^\d+$/D', $search ) ) {
                $args['p'] = absint( $search );
            } else {
                $args['s'] = $search;
            }
        }
        if ( $term && false !== strpos( $term, ':' ) ) {
            [ $taxonomy, $term_id ] = array_pad( explode( ':', $term, 2 ), 2, '' );
            if ( taxonomy_exists( $taxonomy ) && absint( $term_id ) ) {
                $args['tax_query'] = [[
                    'taxonomy' => sanitize_key( $taxonomy ),
                    'field'    => 'term_id',
                    'terms'    => absint( $term_id ),
                ]];
            }
        }
        $q = new WP_Query( $args );
        $posts = [];
        foreach ( $q->posts as $post ) {
            if ( ! current_user_can( 'edit_post', $post->ID ) ) continue;
            $posts[] = [
                'id'       => $post->ID,
                'title'    => get_the_title( $post ) ?: '(no title)',
                'status'   => $post->post_status,
                'modified' => get_post_modified_time( 'Y-m-d H:i', false, $post ),
                'editUrl'  => get_edit_post_link( $post->ID, 'raw' ),
            ];
        }
        wp_send_json_success( [
            'posts' => $posts,
            'terms' => $this->filter_terms_for_post_type( $post_type ),
        ] );
    }

    private function filter_terms_for_post_type( $post_type ) {
        $out = [];
        $taxonomies = get_object_taxonomies( $post_type, 'objects' );
        foreach ( $taxonomies as $taxonomy ) {
            if ( ! $taxonomy->show_ui ) continue;
            $terms = get_terms( [ 'taxonomy' => $taxonomy->name, 'hide_empty' => false, 'number' => 500 ] );
            if ( is_wp_error( $terms ) ) continue;
            foreach ( $terms as $term ) {
                $out[] = [
                    'value'    => $taxonomy->name . ':' . $term->term_id,
                    'taxonomy' => $taxonomy->labels->singular_name,
                    'name'     => $term->name,
                ];
            }
        }
        return $out;
    }

    public function ajax_load_post() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify_ajax( $post_id );
        $package = $this->build_package( $post_id, 'backup' );
        if ( is_wp_error( $package ) ) {
            wp_send_json_error( [ 'message' => $package->get_error_message() ] );
        }
        wp_send_json_success( [
            'package' => $package,
            'ui'      => $this->build_ui_schema( $post_id ),
        ] );
    }

    public function view_url_for_post( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) { return ''; }
        if ( 'publish' === $post->post_status ) { return get_permalink( $post ); }
        $preview = get_preview_post_link( $post );
        return $preview ? $preview : get_permalink( $post );
    }

    private function build_ui_schema( $post_id ) {
        $post = get_post( $post_id );
        $taxonomies = [];
        foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $taxonomy ) {
            if ( ! $taxonomy->show_ui ) continue;
            $all = get_terms( [ 'taxonomy' => $taxonomy->name, 'hide_empty' => false, 'number' => 500 ] );
            if ( is_wp_error( $all ) ) $all = [];
            $assigned = wp_get_object_terms( $post_id, $taxonomy->name, [ 'fields' => 'ids' ] );
            $assigned = is_wp_error( $assigned ) ? [] : array_map( 'intval', $assigned );

            // A large taxonomy is intentionally capped for the phone UI, but every current
            // assignment must remain visible or saving the form could silently drop it.
            $loaded_ids = array_map( static function( $t ) { return (int) $t->term_id; }, $all );
            $missing_assigned = array_values( array_diff( $assigned, $loaded_ids ) );
            if ( $missing_assigned ) {
                $extra = get_terms( [ 'taxonomy' => $taxonomy->name, 'hide_empty' => false, 'include' => $missing_assigned ] );
                if ( ! is_wp_error( $extra ) ) { $all = array_merge( $all, $extra ); }
            }

            $tax_obj = get_taxonomy( $taxonomy->name );
            $assign_cap = $tax_obj && ! empty( $tax_obj->cap->assign_terms ) ? $tax_obj->cap->assign_terms : '';
            $total_terms = wp_count_terms( [ 'taxonomy' => $taxonomy->name, 'hide_empty' => false ] );
            $taxonomies[] = [
                'name'         => $taxonomy->name,
                'label'        => $taxonomy->label,
                'hierarchical' => (bool) $taxonomy->hierarchical,
                'terms'        => array_map( static function( $t ) { return [ 'id' => (int) $t->term_id, 'name' => $t->name, 'parent' => (int) $t->parent ]; }, $all ),
                'assigned'     => $assigned,
                'canAssign'    => $assign_cap ? current_user_can( $assign_cap ) : false,
                'truncated'    => ! is_wp_error( $total_terms ) && (int) $total_terms > 500,
                'totalTerms'   => is_wp_error( $total_terms ) ? count( $all ) : (int) $total_terms,
            ];
        }

        $pt_obj = get_post_type_object( $post->post_type );
        $publish_cap = ( $pt_obj && ! empty( $pt_obj->cap->publish_posts ) ) ? $pt_obj->cap->publish_posts : 'publish_posts';
        $edit_others_cap = ( $pt_obj && ! empty( $pt_obj->cap->edit_others_posts ) ) ? $pt_obj->cap->edit_others_posts : 'edit_others_posts';
        $can_assign_author = current_user_can( $edit_others_cap );
        $authors = [];
        if ( $can_assign_author ) {
            $author_users = get_users( [ 'capability' => 'edit_posts', 'orderby' => 'display_name', 'order' => 'ASC', 'number' => 500 ] );
            foreach ( $author_users as $author_user ) {
                $title = function_exists( 'nine_user_get_contact' ) ? nine_user_get_contact( 'title', $author_user->ID ) : get_user_meta( $author_user->ID, '_9um_title', true );
                $authors[] = [ 'id' => (int) $author_user->ID, 'name' => $author_user->display_name, 'title' => (string) $title ];
            }
        }

        return [
            'acf'           => $this->discover_acf_fields( $post_id ),
            'meta'          => $this->discover_meta_fields( $post_id ),
            'taxonomies'    => $taxonomies,
            'featured'      => $this->featured_image_data( $post_id ),
            'permalink'     => $this->view_url_for_post( $post_id ),
            'nativeEditUrl' => get_edit_post_link( $post_id, 'raw' ),
            'modifiedGmt'   => get_post_modified_time( 'c', true, $post_id ),
            'snapshot'      => $this->latest_snapshot_summary( $post_id ),
            'canPublish'    => current_user_can( $publish_cap ),
            'canAssignAuthor'=> $can_assign_author,
            'authors'       => $authors,
            'authorId'      => (int) $post->post_author,
            'postType'      => $post->post_type,
            'postTypeLabel' => $pt_obj ? $pt_obj->labels->singular_name : $post->post_type,
            'postOptions'    => $this->post_options_for_ui( $post_id ),
            'nativeFields'    => class_exists( 'Nine_Post_Manager_Fields' ) ? Nine_Post_Manager_Fields::instance()->schema_for_ui( $post_id ) : [ 'fields' => [], 'canManage' => false ],
            'contentImages'  => $this->discover_content_images( $post_id ),
            'acfActive'      => function_exists( 'acf_get_field_groups' ),
            'render'        => class_exists( 'Nine_Post_Manager_Renderer' ) ? Nine_Post_Manager_Renderer::instance()->state_for_post( $post_id ) : [],
            'ninecf'        => class_exists( 'Nine_Post_Manager_9CF' ) ? Nine_Post_Manager_9CF::instance()->ui_for_post( $post_id ) : [ 'fields' => [], 'blockFields' => [], 'pluginFields' => [], 'counts' => [], 'warnings' => [], 'mirrorEnabled' => false ],
        ];
    }

    private function post_options_for_ui( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) { return []; }
        $pt = get_post_type_object( $post->post_type );
        $supports = static function( $feature ) use ( $post ) { return post_type_supports( $post->post_type, $feature ); };
        $templates = [];
        if ( function_exists( 'wp_get_theme' ) ) {
            $theme = wp_get_theme();
            foreach ( (array) $theme->get_page_templates( $post, $post->post_type ) as $file => $label ) { $templates[] = [ 'value' => $file, 'label' => $label ]; }
        }
        $parents = [];
        if ( ! empty( $pt->hierarchical ) ) {
            $items = get_posts( [ 'post_type' => $post->post_type, 'post_status' => [ 'publish','draft','pending','private','future' ], 'posts_per_page' => 250, 'post__not_in' => [ $post_id ], 'orderby' => 'title', 'order' => 'ASC' ] );
            foreach ( $items as $item ) { $parents[] = [ 'id' => (int) $item->ID, 'title' => get_the_title( $item ) ?: '(no title)' ]; }
        }
        return [
            'date' => $post->post_date,
            'parentId' => (int) $post->post_parent,
            'parents' => $parents,
            'hierarchical' => ! empty( $pt->hierarchical ),
            'menuOrder' => (int) $post->menu_order,
            'commentStatus' => $post->comment_status,
            'pingStatus' => $post->ping_status,
            'password' => $post->post_password,
            'pageTemplate' => get_page_template_slug( $post_id ) ?: 'default',
            'templates' => $templates,
            'postFormat' => get_post_format( $post_id ) ?: '',
            'formats' => function_exists( 'get_post_format_strings' ) ? get_post_format_strings() : [],
            'sticky' => 'post' === $post->post_type ? is_sticky( $post_id ) : false,
            'supportsComments' => $supports( 'comments' ),
            'supportsTrackbacks' => $supports( 'trackbacks' ),
            'supportsExcerpt' => $supports( 'excerpt' ),
            'supportsThumbnail' => $supports( 'thumbnail' ),
            'supportsAuthor' => $supports( 'author' ),
        ];
    }

    private function discover_content_images( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) { return []; }
        $ids = [];
        $content = (string) $post->post_content;
        if ( preg_match_all( '/wp-image-(\d+)/', $content, $m ) ) {
            foreach ( $m[1] as $id ) { $id = absint( $id ); if ( $id && 'attachment' === get_post_type( $id ) && 0 === strpos( (string) get_post_mime_type( $id ), 'image/' ) ) { $ids[ $id ] = true; } }
        }
        if ( preg_match_all( '/https?:\/\/[^\s"\'<>]+/', $content, $urls ) ) {
            foreach ( $urls[0] as $url ) { $id = attachment_url_to_postid( html_entity_decode( $url ) ); if ( $id && 0 === strpos( (string) get_post_mime_type( $id ), 'image/' ) ) { $ids[ $id ] = true; } }
        }
        if ( function_exists( 'parse_blocks' ) ) {
            $walk = function( $blocks ) use ( &$walk, &$ids ) {
                foreach ( (array) $blocks as $block ) {
                    $attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
                    foreach ( [ 'id','mediaId','imageId','attachmentId' ] as $key ) { if ( ! empty( $attrs[ $key ] ) ) { $id = absint( $attrs[ $key ] ); if ( $id && 'attachment' === get_post_type( $id ) && 0 === strpos( (string) get_post_mime_type( $id ), 'image/' ) ) { $ids[ $id ] = true; } } }
                    if ( ! empty( $attrs['ids'] ) && is_array( $attrs['ids'] ) ) { foreach ( $attrs['ids'] as $id ) { $id = absint( $id ); if ( $id && 'attachment' === get_post_type( $id ) && 0 === strpos( (string) get_post_mime_type( $id ), 'image/' ) ) { $ids[ $id ] = true; } } }
                    if ( ! empty( $block['innerBlocks'] ) ) { $walk( $block['innerBlocks'] ); }
                }
            };
            $walk( parse_blocks( $content ) );
        }
        $out = [];
        foreach ( array_slice( array_keys( $ids ), 0, 100 ) as $id ) { $item = $this->attachment_data( $id ); $item['field'] = 'content_image:' . $id; $out[] = $item; }
        return $out;
    }

    private function replace_content_image( $post_id, $old_id, $new_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) { return new WP_Error( 'missing_post', 'Post not found.' ); }
        if ( ! $old_id || 'attachment' !== get_post_type( $old_id ) ) { return new WP_Error( 'bad_old_image', 'The old content image could not be identified.' ); }
        if ( ! $new_id || 'attachment' !== get_post_type( $new_id ) || 0 !== strpos( (string) get_post_mime_type( $new_id ), 'image/' ) ) { return new WP_Error( 'bad_new_image', 'Choose a valid replacement image.' ); }
        $content = (string) $post->post_content;
        $old_url = wp_get_attachment_url( $old_id );
        $new_url = wp_get_attachment_url( $new_id );
        if ( $old_url && $new_url ) { $content = str_replace( $old_url, $new_url, $content ); }
        $old_meta = wp_get_attachment_metadata( $old_id );
        $new_meta = wp_get_attachment_metadata( $new_id );
        $old_dir = $old_url ? trailingslashit( dirname( $old_url ) ) : '';
        $new_dir = $new_url ? trailingslashit( dirname( $new_url ) ) : '';
        if ( $old_dir && $new_dir && is_array( $old_meta ) && is_array( $new_meta ) ) {
            foreach ( (array) ( $old_meta['sizes'] ?? [] ) as $size => $data ) {
                if ( empty( $data['file'] ) ) { continue; }
                $new_file = $new_meta['sizes'][ $size ]['file'] ?? '';
                if ( $new_file ) { $content = str_replace( $old_dir . $data['file'], $new_dir . $new_file, $content ); }
            }
        }
        $content = str_replace( 'wp-image-' . $old_id, 'wp-image-' . $new_id, $content );
        $content = preg_replace( '/(["\']id["\']\s*:\s*)' . preg_quote( (string) $old_id, '/' ) . '(?=\s*[,}])/', '$1' . $new_id, $content );
        $updated = wp_update_post( wp_slash( [ 'ID' => $post_id, 'post_content' => $content ] ), true );
        if ( is_wp_error( $updated ) ) { return $updated; }
        return true;
    }

    private function discover_acf_fields( $post_id ) {
        if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
            return [];
        }
        $groups = acf_get_field_groups( [ 'post_id' => $post_id ] );
        $out = [];
        foreach ( (array) $groups as $group ) {
            $fields = acf_get_fields( $group['key'] );
            $prepared = [];
            foreach ( (array) $fields as $field ) {
                $prepared[] = $this->prepare_acf_field( $field, $post_id, true );
            }
            $out[] = [
                'key'    => $group['key'],
                'title'  => $group['title'],
                'fields' => $prepared,
            ];
        }
        return $out;
    }

    private function prepare_acf_field( $field, $post_id = 0, $load_value = false ) {
        $type = isset( $field['type'] ) ? $field['type'] : 'text';
        $value = $load_value && $post_id ? ( function_exists( 'get_field' ) ? get_field( $field['key'], $post_id, false ) : get_post_meta( $post_id, $field['name'], true ) ) : null;
        $sub_fields = [];
        foreach ( (array) ( $field['sub_fields'] ?? [] ) as $sub ) {
            $sub_fields[] = $this->prepare_acf_field( $sub, 0, false );
        }
        $layouts = [];
        foreach ( (array) ( $field['layouts'] ?? [] ) as $layout ) {
            $layout_fields = [];
            foreach ( (array) ( $layout['sub_fields'] ?? [] ) as $sub ) {
                $layout_fields[] = $this->prepare_acf_field( $sub, 0, false );
            }
            $layouts[] = [
                'key'        => $layout['key'] ?? '',
                'name'       => $layout['name'] ?? '',
                'label'      => $layout['label'] ?? ( $layout['name'] ?? '' ),
                'min'        => isset( $layout['min'] ) ? (int) $layout['min'] : 0,
                'max'        => isset( $layout['max'] ) ? (int) $layout['max'] : 0,
                'sub_fields' => $layout_fields,
            ];
        }
        $data = [
            'key'          => $field['key'] ?? '',
            'name'         => $field['name'] ?? '',
            'label'        => ! empty( $field['label'] ) ? $field['label'] : ( $field['name'] ?? '' ),
            'type'         => $type,
            'instructions' => $field['instructions'] ?? '',
            'required'     => ! empty( $field['required'] ),
            'choices'      => $field['choices'] ?? [],
            'multiple'     => ! empty( $field['multiple'] ),
            'placement'    => isset( $field['placement'] ) ? $field['placement'] : 'top',
            'endpoint'     => ! empty( $field['endpoint'] ),
            'min'          => isset( $field['min'] ) ? $field['min'] : '',
            'max'          => isset( $field['max'] ) ? $field['max'] : '',
            'step'         => isset( $field['step'] ) ? $field['step'] : '',
            'sub_fields'   => $sub_fields,
            'layouts'      => $layouts,
            'value'        => $value,
        ];
        if ( $load_value && in_array( $type, [ 'image', 'file' ], true ) && $value ) {
            $data['image'] = $this->attachment_data( absint( $value ) );
        }
        if ( $load_value && 'gallery' === $type ) {
            $data['gallery'] = [];
            foreach ( (array) $value as $id ) {
                $id = absint( is_array( $id ) && isset( $id['ID'] ) ? $id['ID'] : $id );
                if ( $id ) $data['gallery'][] = $this->attachment_data( $id );
            }
        }
        return $data;
    }

    private function discover_meta_fields( $post_id ) {
        $raw = get_post_meta( $post_id );
        $out = [];
        foreach ( $raw as $key => $values ) {
            if ( in_array( $key, [ '_edit_lock', '_edit_last' ], true ) || 0 === strpos( $key, '_npm9_' ) ) continue;
            $decoded = array_map( 'maybe_unserialize', $values );
            $is_system = ( 0 === strpos( $key, '_' ) );
            $is_acf_reference = $is_system && isset( $decoded[0] ) && is_string( $decoded[0] ) && 0 === strpos( $decoded[0], 'field_' );
            /* Private/plugin meta is provider-owned by default. It may be inspected
             * here, but only the provider can opt a key into direct Data Manager editing. */
            $provider_allows = (bool) apply_filters( 'nine10_data_meta_editable', false, $key, $post_id, $decoded );
            $readonly = $is_system && ! $provider_allows;
            if ( $is_acf_reference || in_array( $key, [ '_thumbnail_id', '_wp_page_template' ], true ) ) { $readonly = true; }
            $out[] = [
                'key'      => $key,
                'value'    => 1 === count( $decoded ) ? $decoded[0] : $decoded,
                'system'   => $is_system,
                'readonly' => $readonly,
            ];
        }
        usort( $out, static function( $a, $b ) { return strcmp( $a['key'], $b['key'] ); } );
        return $out;
    }

    private function featured_image_data( $post_id ) {
        $id = get_post_thumbnail_id( $post_id );
        return $id ? $this->attachment_data( $id ) : [ 'id' => 0, 'url' => '', 'thumb' => '', 'title' => '' ];
    }

    private function attachment_data( $id ) {
        $url = wp_get_attachment_url( $id );
        $thumb = wp_get_attachment_image_url( $id, 'medium' );
        return [
            'id'       => (int) $id,
            'url'      => $url ?: '',
            'thumb'    => $thumb ?: ( $url ?: '' ),
            'title'    => get_the_title( $id ),
            'filename' => $url ? wp_basename( wp_parse_url( $url, PHP_URL_PATH ) ) : '',
        ];
    }

    private function field_schema_for_export( $field ) {
        $out = $field;
        unset( $out['value'], $out['image'], $out['gallery'] );
        if ( ! empty( $out['sub_fields'] ) ) {
            $out['sub_fields'] = array_map( [ $this, 'field_schema_for_export' ], $out['sub_fields'] );
        }
        if ( ! empty( $out['layouts'] ) ) {
            foreach ( $out['layouts'] as &$layout ) {
                if ( ! empty( $layout['sub_fields'] ) ) {
                    $layout['sub_fields'] = array_map( [ $this, 'field_schema_for_export' ], $layout['sub_fields'] );
                }
            }
            unset( $layout );
        }
        return $out;
    }

    private function build_package( $post_id, $mode = 'backup' ) {
        $post = get_post( $post_id );
        if ( ! $post ) return new WP_Error( 'not_found', 'Post not found.' );

        $blank = ( 'template' === $mode );
        $ui = $this->build_ui_schema( $post_id );
        $acf = [];
        foreach ( $ui['acf'] as $group ) {
            foreach ( $group['fields'] as $field ) {
                $acf[ $field['name'] ] = [
                    'key'    => $field['key'],
                    'label'  => $field['label'],
                    'type'   => $field['type'],
                    'schema' => $this->field_schema_for_export( $field ),
                    'value'  => $blank ? $this->empty_value_for_field( $field['type'] ) : $field['value'],
                ];
            }
        }
        $meta = [];
        $system_meta = [];
        foreach ( $ui['meta'] as $m ) {
            if ( $m['system'] ) {
                if ( ! $blank ) $system_meta[ $m['key'] ] = $m['value'];
            } else {
                $meta[ $m['key'] ] = $blank ? '' : $m['value'];
            }
        }
        $tax = [];
        foreach ( $ui['taxonomies'] as $t ) {
            $tax[ $t['name'] ] = $blank ? [] : $t['assigned'];
        }
        return [
            'schema'       => '9pm-post-package',
            'version'      => '4.0',
            'mode'         => $mode,
            'generated_at' => current_time( 'mysql' ),
            'target'       => [ 'post_id' => $post_id, 'post_type' => $post->post_type ],
            'instructions_for_ai' => [
                'Edit values only unless explicitly asked to change structure.',
                'Keep ACF field keys, names, types and schema unchanged.',
                'Repeater values are arrays of row objects keyed by sub-field name.',
                'Flexible Content values are arrays of row objects and every row must retain acf_fc_layout.',
                'For image/file/gallery fields, existing WordPress attachment IDs are preferred. A URL, filename, or object containing id/ID/attachment_id/url/filename may also be supplied and the importer will try to match existing media.',
                'Return valid JSON for JSON templates. For Markdown, preserve and update the Machine Data JSON block.',
                'Keep the presentation and design blocks unless you intentionally want to change rendering. The design block may contain a portable linked .9pm Gutenberg/9 Elements or experimental Elementor recipe.',
            ],
            'post' => [
                'title'             => $blank ? '' : $post->post_title,
                'slug'              => $blank ? '' : $post->post_name,
                'status'            => $post->post_status,
                'excerpt'           => $blank ? '' : $post->post_excerpt,
                'content'           => $blank ? '' : $post->post_content,
                'featured_image_id' => $blank ? 0 : (int) get_post_thumbnail_id( $post_id ),
                'author_id'         => $blank ? 0 : (int) $post->post_author,
            ],
            'taxonomies' => $tax,
            'acf'        => $acf,
            'meta'       => $meta,
            'presentation' => class_exists( 'Nine_Post_Manager_Renderer' ) ? Nine_Post_Manager_Renderer::instance()->package_for_post( $post_id ) : [],
            'design'       => class_exists( 'Nine_Post_Manager_Design' ) ? Nine_Post_Manager_Design::instance()->package_for_post( $post_id ) : [],
            'system_meta_backup_only' => $system_meta,
        ];
    }

    private function empty_value_for_field( $type ) {
        if ( in_array( $type, [ 'checkbox', 'gallery', 'relationship', 'post_object', 'taxonomy', 'repeater', 'flexible_content', 'user' ], true ) ) return [];
        if ( in_array( $type, [ 'image', 'file', 'number', 'range', 'true_false' ], true ) ) return 0;
        if ( in_array( $type, [ 'group', 'link', 'google_map' ], true ) ) return (object) [];
        return '';
    }

    public function ajax_save_post() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify_ajax( $post_id );
        $payload = isset( $_POST['payload'] ) ? json_decode( wp_unslash( $_POST['payload'] ), true ) : null;
        if ( ! is_array( $payload ) ) wp_send_json_error( [ 'message' => 'Invalid payload.' ] );
        $expected_modified = isset( $_POST['expected_modified_gmt'] ) ? sanitize_text_field( wp_unslash( $_POST['expected_modified_gmt'] ) ) : '';
        $current_modified = get_post_modified_time( 'c', true, $post_id );
        if ( $expected_modified && $current_modified && $expected_modified !== $current_modified ) {
            wp_send_json_error( [ 'message' => 'This post changed after you opened it. Reload the latest version before saving; your phone draft has been kept.' ], 409 );
        }

        $this->create_snapshot( $post_id, 'Before manual update' );
        $result = $this->apply_payload( $post_id, $payload, false );
        if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        wp_send_json_success( [ 'message' => 'Post updated.', 'package' => $this->build_package( $post_id, 'backup' ) ] );
    }

    /**
     * Safe internal bridge for provider-neutral importers such as 9CF.
     * Creates a normal 9PM recovery snapshot before applying the payload.
     */
    public function apply_external_payload( $post_id, $payload, $reason = 'Before external field import', $from_import = true ) {
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return new WP_Error( 'permission', 'You do not have permission to edit this post.' );
        }
        $this->create_snapshot( $post_id, sanitize_text_field( $reason ) );
        return $this->apply_payload( $post_id, $payload, $from_import );
    }

    /**
     * Safe bridge for trusted companion interfaces that need to create a post
     * through 9 Post Editor rather than writing around it.
     */
    public function create_external_payload( $post_type, $payload, $reason = 'Created through external 9 Post Editor form' ) {
        $post_type = sanitize_key( $post_type );
        $obj = get_post_type_object( $post_type );
        if ( ! $obj || 'attachment' === $post_type || empty( $obj->show_ui ) ) {
            return new WP_Error( 'invalid_post_type', 'This post type cannot be created here.' );
        }
        $cap = ! empty( $obj->cap->create_posts ) ? $obj->cap->create_posts : 'edit_posts';
        if ( ! current_user_can( $cap ) ) {
            return new WP_Error( 'permission', 'You do not have permission to create this post type.' );
        }
        $core = isset( $payload['post'] ) && is_array( $payload['post'] ) ? $payload['post'] : array();
        $status = ! empty( $core['status'] ) && in_array( $core['status'], array( 'draft', 'pending', 'private', 'publish' ), true ) ? $core['status'] : 'draft';
        $publish_cap = ! empty( $obj->cap->publish_posts ) ? $obj->cap->publish_posts : 'publish_posts';
        if ( 'publish' === $status && ! current_user_can( $publish_cap ) ) { $status = 'pending'; }
        $post_id = wp_insert_post( array(
            'post_type'   => $post_type,
            'post_status' => $status,
            'post_title'  => ! empty( $core['title'] ) ? sanitize_text_field( $core['title'] ) : 'Untitled',
        ), true );
        if ( is_wp_error( $post_id ) ) { return $post_id; }
        $result = $this->apply_payload( $post_id, $payload, true );
        if ( is_wp_error( $result ) ) {
            wp_delete_post( $post_id, true );
            return $result;
        }
        do_action( 'npm9_external_post_created', $post_id, sanitize_text_field( $reason ), $payload );
        return array( 'post_id' => (int) $post_id, 'result' => $result );
    }

    private function apply_payload( $post_id, $payload, $from_import = false ) {
        $post = get_post( $post_id );
        if ( ! $post ) return new WP_Error( 'missing', 'Post not found.' );

        $incoming_design = isset( $payload['design']['package'] ) && is_array( $payload['design']['package'] ) && ! empty( $payload['design']['package'] );
        if ( ! $incoming_design && class_exists( 'Nine_Post_Manager_Design' ) && method_exists( Nine_Post_Manager_Design::instance(), 'preflight_linked_save' ) ) {
            $design_safe = Nine_Post_Manager_Design::instance()->preflight_linked_save( $post_id );
            if ( is_wp_error( $design_safe ) ) { return $design_safe; }
        }

        $core = isset( $payload['post'] ) && is_array( $payload['post'] ) ? $payload['post'] : [];
        if ( isset( $payload['taxonomies'] ) && is_array( $payload['taxonomies'] ) ) {
            foreach ( $payload['taxonomies'] as $taxonomy => $term_ids ) {
                $taxonomy = sanitize_key( $taxonomy );
                if ( ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( $post->post_type, $taxonomy ) ) { continue; }
                $tax_obj = get_taxonomy( $taxonomy );
                $assign_cap = $tax_obj && ! empty( $tax_obj->cap->assign_terms ) ? $tax_obj->cap->assign_terms : '';
                if ( ! $assign_cap || ! current_user_can( $assign_cap ) ) {
                    return new WP_Error( 'taxonomy_permission', 'You do not have permission to change ' . $taxonomy . ' assignments.' );
                }
            }
        }

        $update = [ 'ID' => $post_id ];
        if ( array_key_exists( 'title', $core ) ) $update['post_title'] = sanitize_text_field( $core['title'] );
        if ( array_key_exists( 'slug', $core ) ) $update['post_name'] = sanitize_title( $core['slug'] );
        if ( array_key_exists( 'excerpt', $core ) ) $update['post_excerpt'] = wp_kses_post( $core['excerpt'] );
        if ( array_key_exists( 'content', $core ) ) $update['post_content'] = $this->sanitize_post_content( $core['content'] );
        if ( array_key_exists( 'date', $core ) && $core['date'] ) $update['post_date'] = sanitize_text_field( $core['date'] );
        if ( array_key_exists( 'parent_id', $core ) ) {
            $parent_id = absint( $core['parent_id'] );
            $pt_obj_parent = get_post_type_object( $post->post_type );
            if ( $pt_obj_parent && ! empty( $pt_obj_parent->hierarchical ) && ( ! $parent_id || ( get_post_type( $parent_id ) === $post->post_type && $parent_id !== $post_id ) ) ) $update['post_parent'] = $parent_id;
        }
        if ( array_key_exists( 'menu_order', $core ) ) $update['menu_order'] = intval( $core['menu_order'] );
        if ( array_key_exists( 'comment_status', $core ) && in_array( $core['comment_status'], [ 'open', 'closed' ], true ) ) $update['comment_status'] = $core['comment_status'];
        if ( array_key_exists( 'ping_status', $core ) && in_array( $core['ping_status'], [ 'open', 'closed' ], true ) ) $update['ping_status'] = $core['ping_status'];
        if ( array_key_exists( 'password', $core ) ) $update['post_password'] = sanitize_text_field( $core['password'] );
        if ( array_key_exists( 'author_id', $core ) ) {
            $requested_author = absint( $core['author_id'] );
            $pt_obj = get_post_type_object( $post->post_type );
            $edit_others_cap = ( $pt_obj && ! empty( $pt_obj->cap->edit_others_posts ) ) ? $pt_obj->cap->edit_others_posts : 'edit_others_posts';
            if ( $requested_author && get_userdata( $requested_author ) && ( (int) $post->post_author === $requested_author || current_user_can( $edit_others_cap ) ) ) {
                $update['post_author'] = $requested_author;
            }
        }
        if ( array_key_exists( 'status', $core ) && in_array( $core['status'], [ 'publish', 'draft', 'pending', 'private', 'future' ], true ) ) {
            $requested_status = $core['status'];
            if ( 'publish' === $requested_status ) {
                $pt_obj = get_post_type_object( $post->post_type );
                $publish_cap = ( $pt_obj && ! empty( $pt_obj->cap->publish_posts ) ) ? $pt_obj->cap->publish_posts : 'publish_posts';
                if ( ! current_user_can( $publish_cap ) ) $requested_status = 'pending';
            }
            $update['post_status'] = $requested_status;
        }
        $updated = wp_update_post( wp_slash( $update ), true );
        if ( is_wp_error( $updated ) ) return $updated;

        if ( array_key_exists( 'featured_image_id', $core ) ) {
            $fid = $this->resolve_media_value( $core['featured_image_id'], 'image' );
            $fid ? set_post_thumbnail( $post_id, $fid ) : delete_post_thumbnail( $post_id );
        }

        if ( array_key_exists( 'page_template', $core ) ) {
            $template = sanitize_text_field( (string) $core['page_template'] );
            if ( '' === $template || 'default' === $template ) delete_post_meta( $post_id, '_wp_page_template' );
            else update_post_meta( $post_id, '_wp_page_template', $template );
        }
        if ( array_key_exists( 'post_format', $core ) && current_theme_supports( 'post-formats' ) ) {
            set_post_format( $post_id, sanitize_key( $core['post_format'] ) );
        }
        if ( 'post' === $post->post_type && array_key_exists( 'sticky', $core ) ) {
            ! empty( $core['sticky'] ) ? stick_post( $post_id ) : unstick_post( $post_id );
        }

        if ( isset( $payload['taxonomies'] ) && is_array( $payload['taxonomies'] ) ) {
            foreach ( $payload['taxonomies'] as $taxonomy => $term_ids ) {
                $taxonomy = sanitize_key( $taxonomy );
                if ( taxonomy_exists( $taxonomy ) && is_object_in_taxonomy( $post->post_type, $taxonomy ) ) {
                    $term_result = wp_set_object_terms( $post_id, array_values( array_filter( array_map( 'intval', (array) $term_ids ) ) ), $taxonomy, false );
                    if ( is_wp_error( $term_result ) ) { return $term_result; }
                }
            }
        }

        if ( isset( $payload['acf'] ) && is_array( $payload['acf'] ) ) {
            foreach ( $payload['acf'] as $name => $data ) {
                $value = is_array( $data ) && array_key_exists( 'value', $data ) ? $data['value'] : $data;
                $field_key = is_array( $data ) && ! empty( $data['key'] ) ? sanitize_key( $data['key'] ) : '';
                $field = null;
                if ( $field_key && function_exists( 'acf_get_field' ) ) $field = acf_get_field( $field_key );
                if ( ! $field && function_exists( 'get_field_object' ) ) $field = get_field_object( $field_key ?: sanitize_key( $name ), $post_id, false, false );
                if ( $field ) $value = $this->sanitize_acf_value( $field, $value );
                if ( function_exists( 'update_field' ) ) {
                    update_field( $field_key ?: sanitize_key( $name ), $value, $post_id );
                } else {
                    update_post_meta( $post_id, sanitize_key( $name ), $value );
                }
            }
        }

        if ( isset( $payload['meta'] ) && is_array( $payload['meta'] ) ) {
            foreach ( $payload['meta'] as $key => $value ) {
                $key = sanitize_key( $key );
                if ( ! $key || 0 === strpos( $key, '_' ) ) continue;
                update_post_meta( $post_id, $key, $this->sanitize_meta_value( $value ) );
            }
        }

        if ( isset( $payload['native_fields'] ) && is_array( $payload['native_fields'] ) && class_exists( 'Nine_Post_Manager_Fields' ) ) {
            $native_result = Nine_Post_Manager_Fields::instance()->save_values( $post_id, $payload['native_fields'] );
            if ( is_wp_error( $native_result ) ) { return $native_result; }
        }

        $ninecf_touched_blocks = false;
        if ( isset( $payload['ninecf'] ) && is_array( $payload['ninecf'] ) && class_exists( 'Nine_Post_Manager_9CF' ) ) {
            $ninecf_touched_blocks = Nine_Post_Manager_9CF::instance()->payload_touches_blocks( $post_id, $payload['ninecf'] );
            $ninecf_result = Nine_Post_Manager_9CF::instance()->apply_editor_values( $post_id, $payload['ninecf'] );
            if ( is_wp_error( $ninecf_result ) ) {
                return $ninecf_result;
            }
        }

        if ( $from_import && ! empty( $payload['restore_system_meta'] ) && ! empty( $payload['system_meta_backup_only'] ) && is_array( $payload['system_meta_backup_only'] ) ) {
            foreach ( $payload['system_meta_backup_only'] as $key => $value ) {
                $key = sanitize_key( $key );
                if ( ! $key || in_array( $key, [ '_edit_lock', '_edit_last', '_thumbnail_id' ], true ) || 0 === strpos( $key, '_npm9_' ) ) continue;
                update_post_meta( $post_id, $key, $value );
            }
        }
        if ( isset( $payload['presentation'] ) && is_array( $payload['presentation'] ) && class_exists( 'Nine_Post_Manager_Renderer' ) ) {
            Nine_Post_Manager_Renderer::instance()->apply_package_to_post( $post_id, $payload['presentation'] );
        }
        $portable_design_applied = false;
        if ( isset( $payload['design'] ) && is_array( $payload['design'] ) && class_exists( 'Nine_Post_Manager_Design' ) ) {
            $design_result = Nine_Post_Manager_Design::instance()->apply_portable_design( $post_id, $payload['design'] );
            if ( is_wp_error( $design_result ) ) {
                return $design_result;
            }
            $portable_design_applied = ! empty( $payload['design']['package'] );
        }

        /**
         * Allow linked design layers (for example .9pm Block Editor / Elementor recipes)
         * to rebuild after the ACF/meta payload has been committed.
         */
        if ( ! $portable_design_applied && ! $ninecf_touched_blocks ) {
            do_action( 'npm9_after_apply_payload', $post_id, $payload, $from_import );
        }

        /** Provider-neutral post-save hook used by 9CF mirrors and other Nine integrations. */
        do_action( 'npm9_after_post_manager_save', $post_id, $payload );

        clean_post_cache( $post_id );
        return true;
    }

    private function sanitize_meta_value( $value ) {
        if ( is_array( $value ) ) return array_map( [ $this, 'sanitize_meta_value' ], $value );
        if ( is_object( $value ) ) return (array) $value;
        if ( is_bool( $value ) || is_numeric( $value ) ) return $value;
        return wp_kses_post( (string) $value );
    }

    private function sanitize_acf_value( $field, $value ) {
        $type = $field['type'] ?? 'text';
        if ( in_array( $type, [ 'tab', 'accordion', 'message' ], true ) ) return '';
        if ( in_array( $type, [ 'image', 'file' ], true ) ) return $this->resolve_media_value( $value, $type );
        if ( 'gallery' === $type ) {
            $out = [];
            foreach ( (array) $value as $item ) {
                $id = $this->resolve_media_value( $item, 'image' );
                if ( $id ) $out[] = $id;
            }
            return array_values( array_unique( $out ) );
        }
        if ( 'group' === $type ) {
            $value = is_array( $value ) ? $value : [];
            $out = [];
            foreach ( (array) ( $field['sub_fields'] ?? [] ) as $sub ) {
                $name = $sub['name'];
                if ( array_key_exists( $name, $value ) ) $out[ $name ] = $this->sanitize_acf_value( $sub, $value[ $name ] );
            }
            return $out;
        }
        if ( 'repeater' === $type ) {
            $out = [];
            foreach ( (array) $value as $row ) {
                if ( ! is_array( $row ) ) continue;
                $clean = [];
                foreach ( (array) ( $field['sub_fields'] ?? [] ) as $sub ) {
                    $name = $sub['name'];
                    if ( array_key_exists( $name, $row ) ) $clean[ $name ] = $this->sanitize_acf_value( $sub, $row[ $name ] );
                }
                $out[] = $clean;
            }
            return $out;
        }
        if ( 'flexible_content' === $type ) {
            $layouts = [];
            foreach ( (array) ( $field['layouts'] ?? [] ) as $layout ) $layouts[ $layout['name'] ] = $layout;
            $out = [];
            foreach ( (array) $value as $row ) {
                if ( ! is_array( $row ) || empty( $row['acf_fc_layout'] ) ) continue;
                $layout_name = sanitize_key( $row['acf_fc_layout'] );
                if ( empty( $layouts[ $layout_name ] ) ) continue;
                $clean = [ 'acf_fc_layout' => $layout_name ];
                foreach ( (array) ( $layouts[ $layout_name ]['sub_fields'] ?? [] ) as $sub ) {
                    $name = $sub['name'];
                    if ( array_key_exists( $name, $row ) ) $clean[ $name ] = $this->sanitize_acf_value( $sub, $row[ $name ] );
                }
                $out[] = $clean;
            }
            return $out;
        }
        if ( 'link' === $type ) {
            $value = is_array( $value ) ? $value : [];
            return [
                'url'    => isset( $value['url'] ) ? esc_url_raw( $value['url'] ) : '',
                'title'  => isset( $value['title'] ) ? sanitize_text_field( $value['title'] ) : '',
                'target' => isset( $value['target'] ) && '_blank' === $value['target'] ? '_blank' : '',
            ];
        }
        if ( in_array( $type, [ 'relationship', 'post_object', 'taxonomy', 'user' ], true ) ) {
            if ( is_array( $value ) ) return array_values( array_filter( array_map( 'intval', $value ) ) );
            return absint( $value );
        }
        if ( 'true_false' === $type ) return empty( $value ) ? 0 : 1;
        if ( in_array( $type, [ 'number', 'range' ], true ) ) return is_numeric( $value ) ? 0 + $value : '';
        if ( 'email' === $type ) return sanitize_email( $value );
        if ( 'url' === $type ) return esc_url_raw( $value );
        if ( in_array( $type, [ 'textarea' ], true ) ) return sanitize_textarea_field( $value );
        if ( in_array( $type, [ 'wysiwyg', 'oembed' ], true ) ) return wp_kses_post( (string) $value );
        if ( in_array( $type, [ 'checkbox', 'select' ], true ) && is_array( $value ) ) return array_map( 'sanitize_text_field', $value );
        if ( is_array( $value ) ) return $this->sanitize_meta_value( $value );
        return sanitize_text_field( (string) $value );
    }

    private function resolve_media_value( $value, $kind = 'image' ) {
        if ( is_array( $value ) ) {
            foreach ( [ 'id', 'ID', 'attachment_id' ] as $k ) {
                if ( ! empty( $value[ $k ] ) ) return absint( $value[ $k ] );
            }
            if ( ! empty( $value['url'] ) ) return $this->find_attachment_by_reference( $value['url'] );
            if ( ! empty( $value['filename'] ) ) return $this->find_attachment_by_reference( $value['filename'] );
            return 0;
        }
        if ( is_numeric( $value ) ) return absint( $value );
        $value = trim( (string) $value );
        if ( '' === $value ) return 0;
        return $this->find_attachment_by_reference( $value );
    }

    private function find_attachment_by_reference( $reference ) {
        $reference = trim( (string) $reference );
        if ( filter_var( $reference, FILTER_VALIDATE_URL ) ) {
            $id = attachment_url_to_postid( $reference );
            if ( $id ) return (int) $id;
            $reference = wp_basename( wp_parse_url( $reference, PHP_URL_PATH ) );
        }
        $name = sanitize_file_name( $reference );
        if ( ! $name ) return 0;
        $posts = get_posts( [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 10,
            'meta_query'     => [[ 'key' => '_wp_attached_file', 'value' => $name, 'compare' => 'LIKE' ]],
            'fields'         => 'ids',
        ] );
        return ! empty( $posts[0] ) ? (int) $posts[0] : 0;
    }

    private function sanitize_post_content( $content ) {
        $content = (string) $content;
        if ( current_user_can( 'unfiltered_html' ) ) return $content;
        return wp_kses_post( $content );
    }

    public function ajax_export_package() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify_ajax( $post_id );
        $mode = ( isset( $_POST['mode'] ) && 'template' === $_POST['mode'] ) ? 'template' : 'backup';
        $requested_format = isset( $_POST['format'] ) ? sanitize_key( $_POST['format'] ) : 'json';
        $format = in_array( $requested_format, [ 'json', 'markdown', 'csv' ], true ) ? $requested_format : 'json';
        $package = $this->build_package( $post_id, $mode );
        if ( is_wp_error( $package ) ) wp_send_json_error( [ 'message' => $package->get_error_message() ] );

        $post = get_post( $post_id );
        $base = sanitize_file_name( ( $post->post_name ?: 'post-' . $post_id ) . '-' . $mode . '-' . gmdate( 'Ymd-His' ) );
        if ( 'markdown' === $format ) {
            $content = $this->package_to_markdown( $package );
            $filename = $base . '.md';
            $mime = 'text/markdown';
        } elseif ( 'csv' === $format ) {
            $content = $this->package_to_csv( $package );
            $filename = $base . '-field-report.csv';
            $mime = 'text/csv';
        } else {
            $content = wp_json_encode( $package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            $filename = $base . '.json';
            $mime = 'application/json';
        }
        wp_send_json_success( [ 'filename' => $filename, 'mime' => $mime, 'content' => $content ] );
    }

    private function package_to_csv( $package ) {
        $fh = fopen( 'php://temp', 'r+' );
        if ( ! $fh ) return '';
        // UTF-8 BOM keeps Excel from misreading Unicode labels/content.
        fwrite( $fh, "\xEF\xBB\xBF" );
        fputcsv( $fh, [ 'section', 'field_name', 'label', 'field_key', 'field_type', 'editable', 'value' ] );
        $stringify = static function( $value ) {
            if ( is_scalar( $value ) || null === $value ) return (string) $value;
            return wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        };
        foreach ( (array) ( $package['post'] ?? [] ) as $name => $value ) {
            fputcsv( $fh, [ 'post', $name, $name, '', 'core', 'yes', $stringify( $value ) ] );
        }
        foreach ( (array) ( $package['taxonomies'] ?? [] ) as $name => $value ) {
            fputcsv( $fh, [ 'taxonomy', $name, $name, '', 'taxonomy', 'yes', $stringify( $value ) ] );
        }
        foreach ( (array) ( $package['acf'] ?? [] ) as $name => $data ) {
            $data = is_array( $data ) ? $data : [ 'value' => $data ];
            fputcsv( $fh, [ 'acf', $name, $data['label'] ?? $name, $data['key'] ?? '', $data['type'] ?? '', 'yes', $stringify( $data['value'] ?? '' ) ] );
        }
        foreach ( (array) ( $package['meta'] ?? [] ) as $name => $value ) {
            fputcsv( $fh, [ 'meta', $name, $name, '', 'custom_meta', 'yes', $stringify( $value ) ] );
        }
        foreach ( (array) ( $package['presentation'] ?? [] ) as $name => $value ) {
            if ( 'style_package' === $name ) continue;
            fputcsv( $fh, [ 'presentation', $name, $name, '', '9pm_render', 'yes', $stringify( $value ) ] );
        }
        foreach ( (array) ( $package['system_meta_backup_only'] ?? [] ) as $name => $value ) {
            fputcsv( $fh, [ 'technical_meta', $name, $name, '', 'system_meta', 'no', $stringify( $value ) ] );
        }
        rewind( $fh );
        $csv = stream_get_contents( $fh );
        fclose( $fh );
        return (string) $csv;
    }

    private function package_to_markdown( $package ) {
        $lines = [];
        $lines[] = '# 9 Post Editor ' . ucfirst( $package['mode'] );
        $lines[] = '';
        $lines[] = '- Schema: `' . $package['schema'] . '`';
        $lines[] = '- Version: `' . $package['version'] . '`';
        $lines[] = '- Target post ID: `' . $package['target']['post_id'] . '`';
        $lines[] = '- Post type: `' . $package['target']['post_type'] . '`';
        $lines[] = '';
        $lines[] = '## AI Instructions';
        foreach ( $package['instructions_for_ai'] as $i ) $lines[] = '- ' . $i;
        $lines[] = '';
        $lines[] = '## Core Post Fields';
        foreach ( $package['post'] as $k => $v ) {
            $lines[] = '### ' . $k;
            $lines[] = '```text';
            $lines[] = is_scalar( $v ) ? (string) $v : wp_json_encode( $v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            $lines[] = '```';
            $lines[] = '';
        }
        $lines[] = '## ACF Fields';
        foreach ( $package['acf'] as $name => $data ) {
            $lines[] = '### ' . $name;
            $lines[] = '- Label: ' . $data['label'];
            $lines[] = '- Field key: `' . $data['key'] . '`';
            $lines[] = '- Type: `' . $data['type'] . '`';
            $lines[] = '```json';
            $lines[] = wp_json_encode( $data['value'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            $lines[] = '```';
            $lines[] = '';
        }
        $lines[] = '## Presentation';
        $lines[] = '```json';
        $lines[] = wp_json_encode( $package['presentation'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        $lines[] = '```';
        $lines[] = '';
        $lines[] = '## 9PM Design';
        $lines[] = '```json';
        $lines[] = wp_json_encode( $package['design'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        $lines[] = '```';
        $lines[] = '';
        $lines[] = '## Machine Data';
        $lines[] = 'The plugin imports the JSON block below. AI may edit values, but should preserve the schema and field identifiers.';
        $lines[] = '';
        $lines[] = '<!-- NPM9_DATA_START -->';
        $lines[] = '```json';
        $lines[] = wp_json_encode( $package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        $lines[] = '```';
        $lines[] = '<!-- NPM9_DATA_END -->';
        return implode( "\n", $lines );
    }

    private function parse_import_content( $content ) {
        $content = trim( (string) $content );
        if ( '' === $content ) return new WP_Error( 'empty', 'Import file is empty.' );
        $data = json_decode( $content, true );
        if ( is_array( $data ) ) return $data;

        if ( preg_match( '/<!--\s*NPM9_DATA_START\s*-->.*?```json\s*(\{.*?\})\s*```.*?<!--\s*NPM9_DATA_END\s*-->/si', $content, $m ) ) {
            $data = json_decode( $m[1], true );
            if ( is_array( $data ) ) return $data;
        }
        if ( preg_match( '/```json\s*(\{.*\})\s*```/si', $content, $m ) ) {
            $data = json_decode( $m[1], true );
            if ( is_array( $data ) ) return $data;
        }
        return new WP_Error( 'invalid', 'Could not find valid 9 Post Editor JSON data in this file.' );
    }

    private function normalize_import_package( $data ) {
        if ( isset( $data['schema'] ) && '9pm-post-package' === $data['schema'] ) return $data;
        if ( isset( $data['post'] ) || isset( $data['acf'] ) || isset( $data['meta'] ) ) {
            $data['schema'] = '9pm-post-package';
            $data['version'] = isset( $data['version'] ) ? $data['version'] : '1.0';
            return $data;
        }
        return new WP_Error( 'schema', 'This file does not contain a recognised post package.' );
    }

    public function ajax_preview_import() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify_ajax( $post_id );
        $content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
        $incoming = $this->parse_import_content( $content );
        if ( is_wp_error( $incoming ) ) wp_send_json_error( [ 'message' => $incoming->get_error_message() ] );
        $incoming = $this->normalize_import_package( $incoming );
        if ( is_wp_error( $incoming ) ) wp_send_json_error( [ 'message' => $incoming->get_error_message() ] );
        $current = $this->build_package( $post_id, 'backup' );
        $changes = $this->compare_packages( $current, $incoming );
        $warning = $this->target_warning( $post_id, $incoming );
        wp_send_json_success( [
            'package'       => $incoming,
            'changes'       => $changes,
            'targetWarning' => $warning,
            'requiresMismatchConfirmation' => '' !== $warning,
        ] );
    }

    private function target_warning( $post_id, $incoming ) {
        $post = get_post( $post_id );
        if ( empty( $incoming['target'] ) ) return '';
        $parts = [];
        if ( ! empty( $incoming['target']['post_type'] ) && $incoming['target']['post_type'] !== $post->post_type ) $parts[] = 'post type differs';
        if ( ! empty( $incoming['target']['post_id'] ) && (int) $incoming['target']['post_id'] !== (int) $post_id ) $parts[] = 'post ID differs';
        return $parts ? 'This file was created for another target (' . implode( ' and ', $parts ) . '). Import can still be applied only after you explicitly confirm the mismatch.' : '';
    }

    private function compare_packages( $current, $incoming ) {
        $changes = [];
        foreach ( [ 'post', 'taxonomies', 'acf', 'meta', 'presentation' ] as $section ) {
            if ( ! isset( $incoming[ $section ] ) || ! is_array( $incoming[ $section ] ) ) continue;
            foreach ( $incoming[ $section ] as $key => $value ) {
                $old = isset( $current[ $section ][ $key ] ) ? $current[ $section ][ $key ] : null;
                if ( wp_json_encode( $old ) !== wp_json_encode( $value ) ) {
                    $changes[] = [ 'section' => $section, 'field' => $key, 'old' => $old, 'new' => $value ];
                }
            }
        }
        return $changes;
    }

    public function ajax_apply_import() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify_ajax( $post_id );
        $content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
        $incoming = $this->parse_import_content( $content );
        if ( is_wp_error( $incoming ) ) wp_send_json_error( [ 'message' => $incoming->get_error_message() ] );
        $incoming = $this->normalize_import_package( $incoming );
        if ( is_wp_error( $incoming ) ) wp_send_json_error( [ 'message' => $incoming->get_error_message() ] );
        $warning = $this->target_warning( $post_id, $incoming );
        if ( $warning && empty( $_POST['confirm_mismatch'] ) ) {
            wp_send_json_error( [ 'message' => 'Target mismatch must be explicitly confirmed before import.' ], 409 );
        }
        $this->create_snapshot( $post_id, 'Before imported package' );
        $result = $this->apply_payload( $post_id, $incoming, true );
        if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        wp_send_json_success( [ 'message' => 'Import applied to selected post. A 9PM undo snapshot was saved first.' ] );
    }

    public function ajax_preview_create_import() {
        $this->verify_ajax();
        $content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
        $incoming = $this->parse_import_content( $content );
        if ( is_wp_error( $incoming ) ) wp_send_json_error( [ 'message' => $incoming->get_error_message() ] );
        $incoming = $this->normalize_import_package( $incoming );
        if ( is_wp_error( $incoming ) ) wp_send_json_error( [ 'message' => $incoming->get_error_message() ] );
        $post_type = ! empty( $incoming['target']['post_type'] ) ? sanitize_key( $incoming['target']['post_type'] ) : ( isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : 'post' );
        $obj = get_post_type_object( $post_type );
        if ( ! $obj || 'attachment' === $post_type || ! $obj->show_ui ) wp_send_json_error( [ 'message' => 'The package does not identify a usable post type.' ] );
        $cap = ! empty( $obj->cap->create_posts ) ? $obj->cap->create_posts : 'edit_posts';
        if ( ! current_user_can( $cap ) ) wp_send_json_error( [ 'message' => 'You do not have permission to create this post type.' ], 403 );
        $title = ! empty( $incoming['post']['title'] ) ? sanitize_text_field( $incoming['post']['title'] ) : '(untitled imported post)';
        wp_send_json_success( [
            'postType' => $post_type,
            'postTypeLabel' => $obj->labels->singular_name,
            'title' => $title,
            'acfCount' => isset( $incoming['acf'] ) && is_array( $incoming['acf'] ) ? count( $incoming['acf'] ) : 0,
            'metaCount' => isset( $incoming['meta'] ) && is_array( $incoming['meta'] ) ? count( $incoming['meta'] ) : 0,
        ] );
    }

    public function ajax_create_from_import() {
        $this->verify_ajax();
        $content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
        $incoming = $this->parse_import_content( $content );
        if ( is_wp_error( $incoming ) ) wp_send_json_error( [ 'message' => $incoming->get_error_message() ] );
        $incoming = $this->normalize_import_package( $incoming );
        if ( is_wp_error( $incoming ) ) wp_send_json_error( [ 'message' => $incoming->get_error_message() ] );
        $post_type = ! empty( $incoming['target']['post_type'] ) ? sanitize_key( $incoming['target']['post_type'] ) : ( isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : 'post' );
        $obj = get_post_type_object( $post_type );
        if ( ! $obj || 'attachment' === $post_type || ! $obj->show_ui ) wp_send_json_error( [ 'message' => 'Invalid post type.' ] );
        $cap = ! empty( $obj->cap->create_posts ) ? $obj->cap->create_posts : 'edit_posts';
        if ( ! current_user_can( $cap ) ) wp_send_json_error( [ 'message' => 'You do not have permission to create this post type.' ], 403 );

        $status = ! empty( $incoming['post']['status'] ) && in_array( $incoming['post']['status'], [ 'draft', 'pending', 'private', 'publish' ], true ) ? $incoming['post']['status'] : 'draft';
        if ( 'publish' === $status && ! current_user_can( $obj->cap->publish_posts ?? 'publish_posts' ) ) $status = 'pending';
        $post_id = wp_insert_post( [
            'post_type'   => $post_type,
            'post_status' => $status,
            'post_title'  => ! empty( $incoming['post']['title'] ) ? sanitize_text_field( $incoming['post']['title'] ) : 'Imported Post',
        ], true );
        if ( is_wp_error( $post_id ) ) wp_send_json_error( [ 'message' => $post_id->get_error_message() ] );

        $result = $this->apply_payload( $post_id, $incoming, true );
        if ( is_wp_error( $result ) ) {
            wp_delete_post( $post_id, true );
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }
        wp_send_json_success( [
            'message' => 'New post created from imported data.',
            'postId' => $post_id,
            'managerUrl' => admin_url( 'admin.php?page=' . $this->menu_slug . '&post_id=' . $post_id ),
            'viewUrl' => $this->view_url_for_post( $post_id ),
        ] );
    }

    public function ajax_replace_image() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify_ajax( $post_id );
        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
        $field = isset( $_POST['field'] ) ? sanitize_text_field( wp_unslash( $_POST['field'] ) ) : 'featured';
        $this->create_snapshot( $post_id, 'Before front-end media replacement' );
        if ( $attachment_id && 'attachment' !== get_post_type( $attachment_id ) ) {
            wp_send_json_error( [ 'message' => 'Selected media item is not a valid attachment.' ] );
        }
        if ( 'featured' === $field ) {
            if ( $attachment_id && 0 !== strpos( (string) get_post_mime_type( $attachment_id ), 'image/' ) ) { wp_send_json_error( [ 'message' => 'Featured media must be an image.' ] ); }
            $attachment_id ? set_post_thumbnail( $post_id, $attachment_id ) : delete_post_thumbnail( $post_id );
        } elseif ( 0 === strpos( $field, 'content_image:' ) ) {
            $old_id = absint( substr( $field, strlen( 'content_image:' ) ) );
            if ( ! $attachment_id ) { wp_send_json_error( [ 'message' => 'Content images are replaced rather than removed. Choose a replacement image.' ] ); }
            $replaced = $this->replace_content_image( $post_id, $old_id, $attachment_id );
            if ( is_wp_error( $replaced ) ) { wp_send_json_error( [ 'message' => $replaced->get_error_message() ] ); }
        } elseif ( 0 === strpos( $field, 'acf:' ) ) {
            $key = sanitize_key( substr( $field, 4 ) );
            $acf_field = function_exists( 'acf_get_field' ) ? acf_get_field( $key ) : null;
            if ( ! $acf_field || ! in_array( $acf_field['type'] ?? '', [ 'image', 'file' ], true ) ) { wp_send_json_error( [ 'message' => 'This legacy ACF field is not a supported image/file field.' ] ); }
            if ( $attachment_id && 'image' === $acf_field['type'] && 0 !== strpos( (string) get_post_mime_type( $attachment_id ), 'image/' ) ) { wp_send_json_error( [ 'message' => 'This legacy ACF image field requires an image.' ] ); }
            if ( function_exists( 'update_field' ) ) update_field( $key, $attachment_id, $post_id );
            else update_post_meta( $post_id, $key, $attachment_id );
        } else {
            wp_send_json_error( [ 'message' => 'Unsupported image field.' ] );
        }
        wp_send_json_success( [ 'image' => $attachment_id ? $this->attachment_data( $attachment_id ) : [ 'id' => 0, 'url' => '', 'thumb' => '' ] ] );
    }

    private function snapshot_option_name( $post_id ) {
        return 'npm9_snapshots_' . absint( $post_id );
    }

    public function create_recovery_snapshot( $post_id, $reason ) {
        if ( ! current_user_can( 'edit_post', absint( $post_id ) ) ) { return false; }
        return $this->create_snapshot( $post_id, $reason );
    }

    private function create_snapshot( $post_id, $reason ) {
        $package = $this->build_package( $post_id, 'backup' );
        if ( is_wp_error( $package ) ) return false;
        $name = $this->snapshot_option_name( $post_id );
        $items = get_option( $name, [] );
        if ( ! is_array( $items ) ) $items = [];
        // Keep undo snapshots compact. 9PM normal editing does not write technical/system meta,
        // so local undo only needs the editable payload, not large Elementor/plugin internals.
        $compact = [
            'schema'     => '9pm-post-package',
            'version'    => '4.0',
            'post'       => $package['post'],
            'taxonomies' => $package['taxonomies'],
            'acf'        => $package['acf'],
            'meta'       => $package['meta'],
            'presentation' => $package['presentation'] ?? [],
        ];
        foreach ( $compact['acf'] as &$acf_item ) {
            if ( is_array( $acf_item ) ) unset( $acf_item['schema'] );
        }
        unset( $acf_item );
        array_unshift( $items, [
            'created_at' => current_time( 'mysql' ),
            'reason'     => sanitize_text_field( $reason ),
            'package'    => $compact,
        ] );
        $items = array_slice( $items, 0, $this->snapshot_limit );
        update_option( $name, $items, false );
        return true;
    }

    private function latest_snapshot_summary( $post_id ) {
        $items = get_option( $this->snapshot_option_name( $post_id ), [] );
        if ( empty( $items[0] ) ) return null;
        return [
            'created_at' => $items[0]['created_at'] ?? '',
            'reason'     => $items[0]['reason'] ?? 'Previous 9PM change',
            'count'      => count( $items ),
        ];
    }

    public function ajax_restore_snapshot() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $this->verify_ajax( $post_id );
        $name = $this->snapshot_option_name( $post_id );
        $items = get_option( $name, [] );
        if ( empty( $items[0]['package'] ) ) wp_send_json_error( [ 'message' => 'There is no 9PM snapshot to restore.' ] );
        $snapshot = array_shift( $items );
        update_option( $name, array_values( $items ), false );
        $result = $this->apply_payload( $post_id, $snapshot['package'], false );
        if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        wp_send_json_success( [ 'message' => 'Previous 9PM state restored.' ] );
    }

    public function delete_snapshots( $post_id ) {
        delete_option( $this->snapshot_option_name( $post_id ) );
    }


    public function frontend_toolbox() {
        $theme_launcher = function_exists( 'nine10_data_theme_launcher_available' ) && nine10_data_theme_launcher_available();
        if ( $theme_launcher ) { return; }
        if ( ! is_user_logged_in() || ! is_singular() ) return;
        $post_id = get_queried_object_id();
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) return;
        $title = get_the_title( $post_id ) ?: __( 'Current post', 'nine-code-data' );
        ?>
        <button class="npm9-front-toggle<?php echo ( defined( 'NINECM_VERSION' ) || class_exists( 'NineCM_Core' ) ) ? ' npm9-has-sister' : ''; ?>" type="button" aria-expanded="false" aria-controls="npm9-front-overlay" title="<?php esc_attr_e( 'Open 9 Post Editor', 'nine-code-data' ); ?>">9P</button>
        <div class="npm9-front-overlay" id="npm9-front-overlay" aria-hidden="true" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( '9 Post Editor', 'nine-code-data' ); ?>">
            <div class="npm9-front-overlay-head">
                <strong><?php esc_html_e( '9 Post Editor', 'nine-code-data' ); ?></strong>
                <span><?php echo esc_html( $title ); ?></span>
            </div>
            <iframe class="npm9-front-frame" title="<?php esc_attr_e( 'Edit current post in 9 Post Editor', 'nine-code-data' ); ?>" data-src="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=npm9_workspace&post_id=' . $post_id ), 'npm9_workspace_' . $post_id ) ); ?>"></iframe>
            <div class="npm9-front-overlay-bottom">
                <span class="npm9-front-saved-state" aria-live="polite"><?php esc_html_e( 'Changes are saved only when you press Update.', 'nine-code-data' ); ?></span>
                <button type="button" class="npm9-front-close"><?php esc_html_e( 'Close 9 Post Editor', 'nine-code-data' ); ?></button>
            </div>
        </div>
        <?php
    }
}
