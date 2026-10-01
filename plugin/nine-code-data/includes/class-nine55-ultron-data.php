<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Nine55_Ultron_Data {
    private static $instance = null;
    private $slug = 'nine10-data-edition';
    private $legacy_pages = array(
        'ninecode-acf-data-engine', 'ninecode-acf-table', 'ninecode-acf-fields', 'ninecode-acf-allocator',
        'ninecode-acf-taxonomies', 'ninecode-acf-ai', 'ninecode-acf-versions', 'ninecode-acf-backups', 'ninecode-acf-health',
        'nine-post-manager', 'nine-category-manager', 'nine-ai-manager', 'nine-ai-manager-import', 'nine-ai-manager-post',
        'nine-ai-manager-page', 'nine-ai-manager-category', 'nine-ai-manager-landing', 'nine-ai-manager-settings-import',
        'nine-ai-manager-user', 'nine-ai-manager-plugin-update', 'nine-ai-manager-manifest', 'nine-ai-manager-integrations',
        'nine-ai-manager-history', 'nine-ai-manager-settings',
    );

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ), 5 );
        add_action( 'admin_menu', array( $this, 'hide_legacy_menus' ), 999 );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ), 999 );
        add_action( 'admin_notices', array( $this, 'render_ultron_bar' ), 1 );
        add_filter( 'parent_file', array( $this, 'keep_parent_highlighted' ) );
        add_filter( 'submenu_file', array( $this, 'keep_submenu_highlighted' ), 10, 2 );
        add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
        add_filter( 'nine_ai_manager_integrations', array( $this, 'register_ai_integration' ), 30, 1 );
    }

    public function register_menu() {
        $hook = add_menu_page(
            '9 Data Manager',
            '9 Data Manager',
            'edit_posts',
            $this->slug,
            array( $this, 'render_dashboard' ),
            'dashicons-database-view',
            3
        );
        if ( $hook ) {
            add_action( 'load-' . $hook, static function() {
                wp_safe_redirect( admin_url( 'admin.php?page=nine-post-manager' ) );
                exit;
            } );
        }
        $this->add_named_redirect_menu( 'Post Editor', 'post-editor', 'nine-post-manager', 'edit_posts' );
        $this->add_named_redirect_menu( 'Category Manager', 'category-manager', 'nine-category-manager', 'manage_categories' );
        $this->add_named_redirect_menu( 'Post Creator', 'post-creator', 'nine-ai-manager-post', 'manage_options' );
        add_submenu_page( $this->slug, 'Form Manager', 'Form Manager', 'edit_posts', 'nine10-form-manager', array( 'Nine10_Form', 'render_admin_page_static' ) );
        add_submenu_page( $this->slug, 'Data Backup', 'Data Backup', 'edit_posts', 'nine10-data-backup', array( 'Nine10_Data_Backup', 'render_admin_page_static' ) );
        // WordPress auto-adds the parent page as the first submenu item; remove it so Post Editor is visibly first.
        remove_submenu_page( $this->slug, $this->slug );
    }

    private function add_named_redirect_menu( $label, $slug_suffix, $target, $capability ) {
        $slug = $this->slug . '-' . sanitize_key( $slug_suffix );
        $hook = add_submenu_page( $this->slug, $label, $label, $capability, $slug, '__return_null' );
        if ( $hook ) {
            add_action( 'load-' . $hook, static function() use ( $target ) {
                wp_safe_redirect( admin_url( 'admin.php?page=' . rawurlencode( $target ) ) );
                exit;
            } );
        }
    }

    private function add_redirect_menu( $label, $target, $capability ) {
        $slug = $this->slug . '-' . sanitize_key( $target );
        $hook = add_submenu_page( $this->slug, $label, $label, $capability, $slug, '__return_null' );
        if ( $hook ) {
            add_action( 'load-' . $hook, static function() use ( $target ) {
                wp_safe_redirect( admin_url( 'admin.php?page=' . rawurlencode( $target ) ) );
                exit;
            } );
        }
    }

    public function hide_legacy_menus() {
        remove_menu_page( 'ninecode-acf-data-engine' );
        remove_menu_page( 'nine-post-manager' );
        remove_menu_page( 'nine-category-manager' );
        remove_menu_page( 'nine-ai-manager' );

        /*
         * The removed menu entries are still valid routed pages. WordPress finds
         * a page title by walking the menu, so without one the global $title is
         * null and wp-admin/admin-header.php passes null to strip_tags()
         * (deprecated since PHP 8.1). Supply the title for these routed pages.
         */
        $titles = array(
            'nine-post-manager'        => 'Post Editor',
            'nine-category-manager'    => 'Category Manager',
            'ninecode-acf-data-engine' => 'Data Engine',
            'nine-ai-manager'          => 'AI Manager',
        );
        foreach ( $titles as $page_slug => $page_title ) {
            add_action( 'load-toplevel_page_' . $page_slug, static function () use ( $page_title ) {
                global $title;
                if ( null === $title || '' === $title ) { $title = $page_title; }
            } );
        }
    }

    public function admin_assets() {
        if ( ! $this->is_ultron_area() ) { return; }
        wp_enqueue_style( 'nine55-ultron-data', NINE55_ULTRON_DATA_URL . 'assets/admin.css', array(), NINE55_ULTRON_DATA_VERSION );
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( in_array( $page, array( 'nine10-form-manager', 'nine10-data-backup' ), true ) ) {
            wp_enqueue_script( 'nine10-data-manager-tools', NINE55_ULTRON_DATA_URL . 'assets/form-manager.js', array(), NINE55_ULTRON_DATA_VERSION, true );
        }
    }

    public function admin_body_class( $classes ) {
        if ( $this->is_ultron_area() ) { $classes .= ' ultron955-integrated'; }
        return $classes;
    }

    public function keep_parent_highlighted( $parent_file ) {
        if ( $this->is_ultron_area() ) { return $this->slug; }
        return $parent_file;
    }

    public function keep_submenu_highlighted( $submenu_file, $parent_file ) {
        if ( ! $this->is_ultron_area() ) { return $submenu_file; }
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $map = array(
            'nine-post-manager'         => $this->slug . '-post-editor',
            'nine-category-manager'     => $this->slug . '-category-manager',
            'nine-ai-manager'           => $this->slug . '-post-creator',
            'nine-ai-manager-post'      => $this->slug . '-post-creator',
            'nine10-form-manager'       => 'nine10-form-manager',
            'nine10-data-backup'        => 'nine10-data-backup',
            'ninecode-acf-data-engine'  => 'nine10-data-backup',
            'ninecode-acf-ai'           => 'nine10-data-backup',
            'ninecode-acf-versions'     => 'nine10-data-backup',
            'ninecode-acf-backups'      => 'nine10-data-backup',
        );
        return isset( $map[ $page ] ) ? $map[ $page ] : $submenu_file;
    }

    private function is_ultron_area() {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        return $this->slug === $page || in_array( $page, array( 'nine10-form-manager', 'nine10-data-backup' ), true ) || 0 === strpos( $page, $this->slug . '-' ) || in_array( $page, $this->legacy_pages, true );
    }

    public function render_ultron_bar() {
        if ( ! $this->is_ultron_area() || ! current_user_can( 'edit_posts' ) ) { return; }
        $links = array(
            'Post Editor'      => admin_url( 'admin.php?page=nine-post-manager' ),
            'Category Manager' => admin_url( 'admin.php?page=nine-category-manager' ),
            'Post Creator'     => admin_url( 'admin.php?page=nine-ai-manager-post' ),
            'Form Manager'     => admin_url( 'admin.php?page=nine10-form-manager' ),
            'Data Backup'      => admin_url( 'admin.php?page=nine10-data-backup' ),
        );
        echo '<nav class="ultron955-bar" aria-label="9 Data Manager">';
        echo '<strong>9 Data Manager</strong><span class="ultron955-bar-links">';
        foreach ( $links as $label => $url ) { echo '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>'; }
        echo '</span></nav>';
    }

    public function render_dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have permission to manage this workspace.', 'nine-code-data' ) ); }
        $post_type = isset( $_GET['ultron_post_type'] ) ? sanitize_key( wp_unslash( $_GET['ultron_post_type'] ) ) : 'post';
        $search = isset( $_GET['ultron_search'] ) ? sanitize_text_field( wp_unslash( $_GET['ultron_search'] ) ) : '';
        $record_id = isset( $_GET['ultron_record'] ) ? absint( $_GET['ultron_record'] ) : 0;
        $view = isset( $_GET['ultron_view'] ) ? sanitize_key( wp_unslash( $_GET['ultron_view'] ) ) : 'recent';
        if ( ! in_array( $view, array( 'recent', 'attention' ), true ) ) { $view = 'recent'; }
        $types = get_post_types( array( 'show_ui' => true ), 'objects' );
        $types = apply_filters( 'nine10_data_editable_post_types', $types, 'data-manager' );
        if ( ! is_array( $types ) ) { $types = array(); }
        unset( $types['attachment'] );
        if ( ! isset( $types[ $post_type ] ) ) { $post_type = isset( $types['post'] ) ? 'post' : key( $types ); }
        $query_args = array(
            'post_type'      => $post_type,
            'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
            'posts_per_page' => 'attention' === $view ? 30 : 12,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        );
        if ( '' !== $search ) { $query_args['s'] = $search; }
        $records = get_posts( $query_args );
        $record_workflow = array();
        foreach ( $records as $record ) {
            $record_workflow[ $record->ID ] = $this->get_record_workflow_summary( $record );
        }
        if ( 'attention' === $view ) {
            $records = array_values( array_filter( $records, function( $record ) use ( $record_workflow ) {
                return isset( $record_workflow[ $record->ID ] ) && $record_workflow[ $record->ID ]['ready_count'] < 5;
            } ) );
            $records = array_slice( $records, 0, 12 );
        }
        ?>
        <div class="wrap ultron955-wrap">
            <header class="ultron955-hero">
                <div><p class="ultron955-kicker">EDITORIAL + DATA OPERATING WORKSPACE</p><h1>9 Data Manager</h1><p>Post Editor, Category Manager, Post Creator, Form Manager and Data Backup in one WordPress workspace.</p></div>
                <div class="ultron955-version">9.10</div>
            </header>

            <section class="ultron955-engines" aria-label="Managers">
                <?php $this->engine_card( 'Data', 'Edit ACF values, plugin fields, taxonomies, Excel and bulk data.', 'ninecode-acf-data-engine', defined( 'NINECODE_ACF_DATA_ENGINE_VERSION' ) ? NINECODE_ACF_DATA_ENGINE_VERSION : 'external', 'dashicons-database-view' ); ?>
                <?php $this->engine_card( 'Post Editor', 'Create and edit post identity, content, publishing and recovery.', 'nine-post-manager', defined( 'NPM9_VERSION' ) ? NPM9_VERSION : 'external', 'dashicons-edit-page' ); ?>
                <?php $this->engine_card( 'Category Manager', 'Manage categories, tags, taxonomy structure and content planning.', 'nine-category-manager', defined( 'NINECM_VERSION' ) ? NINECM_VERSION : 'external', 'dashicons-category' ); ?>
                <?php $this->engine_card( 'Post Creator', 'Create and prepare posts with AI packages and human preview.', 'nine-ai-manager-post', defined( 'NINE_AI_MANAGER_VERSION' ) ? NINE_AI_MANAGER_VERSION : 'external', 'dashicons-superhero-alt' ); ?>
                <?php $this->engine_card( 'Form Manager', 'Create forms, manage responses and download form data.', 'nine10-form-manager', '1.0', 'dashicons-feedback' ); ?>
            </section>

            <?php $this->render_engine_safety_gate(); ?>

            <?php if ( $record_id ) { $this->render_record_command_center( $record_id ); } ?>

            <section class="ultron955-panel">
                <div class="ultron955-panel-head"><div><h2>Find a record</h2><p>Choose a post type, find the record, then open the exact manager you need.</p></div></div>
                <form method="get" class="ultron955-find">
                    <input type="hidden" name="page" value="<?php echo esc_attr( $this->slug ); ?>">
                    <label><span>Post type</span><select name="ultron_post_type">
                        <?php foreach ( $types as $name => $object ) : ?>
                            <option value="<?php echo esc_attr( $name ); ?>" <?php selected( $post_type, $name ); ?>><?php echo esc_html( $object->labels->name ); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label class="ultron955-search"><span>Search</span><input type="search" name="ultron_search" value="<?php echo esc_attr( $search ); ?>" placeholder="Title or keyword"></label>
                    <label><span>Show</span><select name="ultron_view">
                        <option value="recent" <?php selected( $view, 'recent' ); ?>>Recent records</option>
                        <option value="attention" <?php selected( $view, 'attention' ); ?>>Needs attention</option>
                    </select></label>
                    <button class="button button-primary" type="submit"><?php echo 'attention' === $view ? 'Find work to finish' : 'Find records'; ?></button>
                </form>
                <?php if ( 'attention' === $view ) : ?><p class="ultron955-attention-note"><strong>Needs attention</strong> shows recent records missing one or more workflow parts. It reads the existing engines only; nothing is changed here.</p><?php endif; ?>
                <div class="ultron955-records">
                    <?php if ( ! $records ) : ?><p class="ultron955-empty"><?php echo 'attention' === $view ? 'No unfinished records found in the recent scan.' : 'No matching records found.'; ?></p><?php endif; ?>
                    <?php foreach ( $records as $record ) : $flow = $record_workflow[ $record->ID ]; ?>
                        <article class="ultron955-record <?php echo $flow['ready_count'] < 5 ? 'needs-attention' : 'is-ready'; ?>">
                            <div class="ultron955-record-main"><strong><?php echo esc_html( get_the_title( $record ) ?: '(no title)' ); ?></strong><span>#<?php echo intval( $record->ID ); ?> · <?php echo esc_html( get_post_status( $record ) ); ?> · updated <?php echo esc_html( get_the_modified_date( 'M j, Y', $record ) ); ?></span>
                                <div class="ultron955-record-flow"><b><?php echo intval( $flow['ready_count'] ); ?>/5 ready</b><?php foreach ( $flow['missing'] as $missing ) : ?><span><?php echo esc_html( $missing ); ?></span><?php endforeach; ?></div>
                            </div>
                            <div class="ultron955-record-actions">
                                <a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page' => $this->slug, 'ultron_post_type' => $post_type, 'ultron_search' => $search, 'ultron_view' => $view, 'ultron_record' => intval( $record->ID ) ), admin_url( 'admin.php' ) ) ); ?>">Open Record</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="ultron955-tools">
                <?php $this->tool_link( 'Edit Many', 'Work across many records without opening posts.', 'ninecode-acf-table' ); ?>
                <?php $this->tool_link( 'Import / Export', 'Excel, CSV and AI data round-trips.', 'ninecode-acf-ai' ); ?>
                <?php $this->tool_link( 'Fields', 'See ACF fields and safe plugin meta.', 'ninecode-acf-fields' ); ?>
                <?php $this->tool_link( 'Versions', 'Recover earlier data values.', 'ninecode-acf-versions' ); ?>
                <?php $this->tool_link( 'Backups', 'Portable data packs and restore.', 'ninecode-acf-backups' ); ?>
                <?php $this->tool_link( 'Health', 'Check the data engine and integrations.', 'ninecode-acf-health' ); ?>
            </section>
        </div>
        <?php
    }

    private function get_record_workflow_summary( $record ) {
        if ( ! $record instanceof WP_Post ) { $record = get_post( $record ); }
        if ( ! $record ) { return array( 'ready_count' => 0, 'missing' => array( 'Post', 'Data', 'Structure', 'Recovery', 'AI' ) ); }

        $post_ready = '' !== trim( (string) get_the_title( $record ) ) && (
            '' !== trim( wp_strip_all_tags( (string) $record->post_content ) ) ||
            '' !== trim( wp_strip_all_tags( (string) $record->post_excerpt ) )
        );

        $data_ready = false;
        if ( class_exists( 'NineCode_Data_Exporter' ) ) {
            $exporter = new NineCode_Data_Exporter();
            $data_record = $exporter->export_post_record( $record->ID );
            if ( is_array( $data_record ) ) {
                $data_ready = count( (array) ( $data_record['fields'] ?? array() ) ) + count( (array) ( $data_record['meta'] ?? array() ) ) > 0;
            }
        }

        $structure_ready = false;
        foreach ( (array) get_object_taxonomies( $record->post_type, 'objects' ) as $tax ) {
            if ( empty( $tax->show_ui ) ) { continue; }
            $term_ids = wp_get_object_terms( $record->ID, $tax->name, array( 'fields' => 'ids' ) );
            if ( ! is_wp_error( $term_ids ) && ! empty( $term_ids ) ) { $structure_ready = true; break; }
        }

        $recovery_ready = false;
        if ( class_exists( 'NineCode_Data_Version_Manager' ) ) {
            $recovery_ready = ! empty( NineCode_Data_Version_Manager::list_versions( 1, 'post', $record->ID ) );
        }

        $ai_history = get_post_meta( $record->ID, '_npm9_9cf_import_history', true );
        $ai_ready = is_array( $ai_history ) && ! empty( $ai_history );
        $states = array( 'Post' => $post_ready, 'Data' => $data_ready, 'Structure' => $structure_ready, 'Recovery' => $recovery_ready, 'AI' => $ai_ready );
        $missing = array();
        foreach ( $states as $label => $ready ) { if ( ! $ready ) { $missing[] = $label; } }
        return array( 'ready_count' => count( $states ) - count( $missing ), 'missing' => $missing );
    }

    private function render_record_command_center( $record_id ) {
        $record = get_post( absint( $record_id ) );
        if ( ! $record || 'attachment' === $record->post_type || ! current_user_can( 'edit_post', $record->ID ) ) {
            echo '<section class="ultron955-command ultron955-command-error"><strong>Record unavailable</strong><p>This record does not exist or you do not have permission to work with it.</p></section>';
            return;
        }

        $type = get_post_type_object( $record->post_type );
        $type_label = $type && ! empty( $type->labels->singular_name ) ? $type->labels->singular_name : $record->post_type;
        $title = get_the_title( $record );
        if ( '' === trim( (string) $title ) ) { $title = '(no title)'; }

        $data_summary = array( 'acf' => 0, 'meta' => 0, 'taxonomies' => 0 );
        if ( class_exists( 'NineCode_Data_Exporter' ) ) {
            $exporter = new NineCode_Data_Exporter();
            $data_record = $exporter->export_post_record( $record->ID );
            if ( is_array( $data_record ) ) {
                $data_summary['acf'] = count( (array) ( $data_record['fields'] ?? array() ) );
                $data_summary['meta'] = count( (array) ( $data_record['meta'] ?? array() ) );
                $tax_map = (array) ( $data_record['taxonomies'] ?? array() );
                foreach ( $tax_map as $values ) { if ( ! empty( $values ) ) { $data_summary['taxonomies']++; } }
            }
        }

        $versions = array();
        if ( class_exists( 'NineCode_Data_Version_Manager' ) ) {
            NineCode_Data_Version_Manager::maybe_install();
            $versions = NineCode_Data_Version_Manager::list_versions( 5, 'post', $record->ID );
        }

        $taxonomy_rows = array();
        foreach ( (array) get_object_taxonomies( $record->post_type, 'objects' ) as $tax ) {
            if ( empty( $tax->show_ui ) ) { continue; }
            $terms = wp_get_object_terms( $record->ID, $tax->name, array( 'fields' => 'names' ) );
            if ( is_wp_error( $terms ) || ! $terms ) { continue; }
            $taxonomy_rows[] = array( 'label' => $tax->labels->singular_name ?: $tax->name, 'values' => $terms );
        }

        // 9.55.3 workflow status is read-only. It derives state from the existing
        // WordPress record and the established engines; it does not create a fifth store.
        $post_has_title   = '' !== trim( (string) get_the_title( $record ) );
        $post_has_content = '' !== trim( wp_strip_all_tags( (string) $record->post_content ) ) || '' !== trim( wp_strip_all_tags( (string) $record->post_excerpt ) );
        $post_ready       = $post_has_title && $post_has_content;
        $data_total       = intval( $data_summary['acf'] ) + intval( $data_summary['meta'] );
        $data_ready       = $data_total > 0;
        $structure_ready  = ! empty( $taxonomy_rows );
        $recovery_ready   = ! empty( $versions );
        $post_ai_history  = get_post_meta( $record->ID, '_npm9_9cf_import_history', true );
        $post_ai_history  = is_array( $post_ai_history ) ? $post_ai_history : array();
        $ai_ready         = ! empty( $post_ai_history );
        $latest_ai        = $ai_ready && ! empty( $post_ai_history[0]['created_at'] ) ? sanitize_text_field( $post_ai_history[0]['created_at'] ) : '';
        $workflow_items   = array(
            array( 'label' => 'Post', 'ready' => $post_ready, 'detail' => $post_ready ? 'Title and content are present.' : ( $post_has_title ? 'Add or complete the post content.' : 'Add a clear post title and content.' ) ),
            array( 'label' => 'Data', 'ready' => $data_ready, 'detail' => $data_ready ? $data_total . ' ACF / safe plugin field(s) detected.' : 'No ACF or safe plugin values detected yet.' ),
            array( 'label' => 'Structure', 'ready' => $structure_ready, 'detail' => $structure_ready ? count( $taxonomy_rows ) . ' taxonomy group(s) assigned.' : 'No category or taxonomy assignment detected.' ),
            array( 'label' => 'Recovery', 'ready' => $recovery_ready, 'detail' => $recovery_ready ? count( $versions ) . ' recent recovery point(s) available.' : 'No Data Engine recovery point is available yet.' ),
            array( 'label' => 'AI', 'ready' => $ai_ready, 'detail' => $ai_ready ? 'Post Editor AI activity recorded' . ( $latest_ai ? ' · ' . $latest_ai : '' ) . '.' : 'No record-specific Post Manager AI import history yet.' ),
        );

        $data_url = add_query_arg( array( 'page' => 'ninecode-acf-data-engine', 'view' => 'editor', 'kind' => 'post', 'id' => $record->ID ), admin_url( 'admin.php' ) );
        $post_url = add_query_arg( array( 'page' => 'nine-post-manager', 'post_id' => $record->ID ), admin_url( 'admin.php' ) );
        $category_url = add_query_arg( array( 'page' => 'nine-category-manager', 'post_id' => $record->ID ), admin_url( 'admin.php' ) );
        $ai_url = add_query_arg( array( 'page' => 'nine-ai-manager', 'post_id' => $record->ID ), admin_url( 'admin.php' ) );
        $versions_url = add_query_arg( array( 'page' => 'ninecode-acf-versions' ), admin_url( 'admin.php' ) );
        $export_url = wp_nonce_url(
            add_query_arg( array( 'action' => 'ninecode_acf_export_record', 'kind' => 'post', 'id' => $record->ID ), admin_url( 'admin-post.php' ) ),
            'ninecode_export_record_' . $record->ID
        );
        $back_url = add_query_arg( array( 'page' => $this->slug, 'ultron_post_type' => $record->post_type ), admin_url( 'admin.php' ) );
        $frontend_url = get_permalink( $record );
        ?>
        <section class="ultron955-command" aria-label="Record Command Center">
            <div class="ultron955-command-head">
                <div>
                    <a class="ultron955-command-back" href="<?php echo esc_url( $back_url ); ?>">← All records</a>
                    <p class="ultron955-kicker">RECORD COMMAND CENTER</p>
                    <h2><?php echo esc_html( $title ); ?></h2>
                    <p><?php echo esc_html( $type_label ); ?> #<?php echo intval( $record->ID ); ?> · <?php echo esc_html( get_post_status( $record ) ); ?> · updated <?php echo esc_html( get_the_modified_date( 'M j, Y g:i a', $record ) ); ?></p>
                </div>
                <span class="ultron955-command-state">ONE RECORD</span>
            </div>

            <div class="ultron955-workflow" aria-label="Record workflow status">
                <div class="ultron955-workflow-head"><div><strong>Record workflow</strong><span>One glance at what is ready. Status is read from the existing managers.</span></div><span class="ultron955-workflow-count"><?php echo intval( count( array_filter( wp_list_pluck( $workflow_items, 'ready' ) ) ) ); ?>/5 ready</span></div>
                <div class="ultron955-workflow-grid">
                    <?php foreach ( $workflow_items as $item ) : ?>
                        <div class="ultron955-workflow-item <?php echo ! empty( $item['ready'] ) ? 'is-ready' : 'needs-work'; ?>">
                            <div><strong><?php echo esc_html( $item['label'] ); ?></strong><span><?php echo ! empty( $item['ready'] ) ? 'Ready' : 'Needs work'; ?></span></div>
                            <p><?php echo esc_html( $item['detail'] ); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="ultron955-command-stats" aria-label="Record data summary">
                <div><strong><?php echo intval( $data_summary['acf'] ); ?></strong><span>ACF fields</span></div>
                <div><strong><?php echo intval( $data_summary['meta'] ); ?></strong><span>Safe plugin fields</span></div>
                <div><strong><?php echo intval( $data_summary['taxonomies'] ); ?></strong><span>Taxonomies used</span></div>
                <div><strong><?php echo intval( count( $versions ) ); ?></strong><span>Recent data versions</span></div>
            </div>

            <div class="ultron955-command-actions">
                <a class="ultron955-command-action is-primary" href="<?php echo esc_url( $data_url ); ?>"><strong>Edit Data</strong><span>ACF, safe plugin fields and taxonomy values through Data Engine.</span></a>
                <a class="ultron955-command-action" href="<?php echo esc_url( $post_url ); ?>"><strong>Edit Post</strong><span>Title, content, publishing and post identity through Post Editor.</span></a>
                <a class="ultron955-command-action" href="<?php echo esc_url( $category_url ); ?>"><strong>Structure</strong><span>Categories, tags and structural allocation through Category Manager.</span></a>
                <a class="ultron955-command-action" href="<?php echo esc_url( $ai_url ); ?>"><strong>Post Creator</strong><span>Prepare and route AI work through the established AI workflow.</span></a>
                <a class="ultron955-command-action" href="<?php echo esc_url( $export_url ); ?>"><strong>Export AI JSON</strong><span>Download this record using Data Engine's existing protected record format.</span></a>
                <a class="ultron955-command-action" href="<?php echo esc_url( $versions_url ); ?>"><strong>Versions & Recovery</strong><span>Open Data Engine recovery history. This record has <?php echo intval( count( $versions ) ); ?> recent version(s) shown here.</span></a>
                <?php if ( $frontend_url && 'publish' === get_post_status( $record ) ) : ?>
                    <a class="ultron955-command-action" href="<?php echo esc_url( $frontend_url ); ?>" target="_blank" rel="noopener"><strong>View on Site</strong><span>Open the published record without changing anything.</span></a>
                <?php endif; ?>
            </div>

            <?php if ( $taxonomy_rows ) : ?>
                <div class="ultron955-command-context"><strong>Current structure</strong><div class="ultron955-command-taxonomies">
                    <?php foreach ( $taxonomy_rows as $row ) : ?><span><b><?php echo esc_html( $row['label'] ); ?>:</b> <?php echo esc_html( implode( ', ', array_map( 'strval', $row['values'] ) ) ); ?></span><?php endforeach; ?>
                </div></div>
            <?php endif; ?>

            <?php if ( $versions ) : ?>
                <details class="ultron955-command-history"><summary>Recent recovery points</summary><div>
                    <?php foreach ( $versions as $version ) : ?><p><strong><?php echo esc_html( $version['version_label'] ?: 'Saved data version' ); ?></strong><span><?php echo esc_html( $version['created_at'] . ' · ' . ucwords( str_replace( '_', ' ', $version['source'] ) ) ); ?></span></p><?php endforeach; ?>
                </div></details>
            <?php endif; ?>
        </section>
        <?php
    }

    private function engine_safety_status() {
        $checks = array(
            'data' => array(
                'label'       => 'Data Engine',
                'version'     => defined( 'NINECODE_ACF_DATA_ENGINE_VERSION' ) ? NINECODE_ACF_DATA_ENGINE_VERSION : '',
                'minimum'     => '0.16.0',
                'external'    => 'ninecode-acf-data-engine/ninecode-acf-data-engine.php',
                'classes'     => array( 'NineCode_ACF_Data_Engine', 'NineCode_Data_Importer', 'NineCode_Data_Version_Manager' ),
                'methods'     => array(
                    array( 'NineCode_Data_Importer', 'import_records_atomic' ),
                    array( 'NineCode_Data_Version_Manager', 'capture_records' ),
                    array( 'NineCode_Data_Version_Manager', 'restore_version' ),
                ),
                'purpose'     => 'ACF, safe plugin meta, taxonomies, versions and rollback',
            ),
            'post' => array(
                'label'       => 'Post Editor',
                'version'     => defined( 'NPM9_VERSION' ) ? NPM9_VERSION : '',
                'minimum'     => '4.0.0',
                'external'    => '9-post-manager/9-post-manager.php',
                'classes'     => array( 'Nine_Post_Manager' ),
                'methods'     => array( array( 'Nine_Post_Manager', 'apply_external_payload' ) ),
                'purpose'     => 'post identity, content, publishing and recovery',
            ),
            'category' => array(
                'label'       => 'Category Manager',
                'version'     => defined( 'NINECM_VERSION' ) ? NINECM_VERSION : '',
                'minimum'     => '4.0.0',
                'external'    => 'nine-category-manager/nine-category-manager.php',
                'classes'     => array( 'NineCM_Core', 'NineCM_REST', 'NineCM_Infrastructure' ),
                'methods'     => array(),
                'purpose'     => 'taxonomy structure and content planning',
            ),
            'ai' => array(
                'label'       => 'Post Creator',
                'version'     => defined( 'NINE_AI_MANAGER_VERSION' ) ? NINE_AI_MANAGER_VERSION : '',
                'minimum'     => '2.0.0',
                'external'    => 'nine-ai-manager/nine-ai-manager.php',
                'classes'     => array( 'Nine_AI_Manager', 'Nine_AI_Manager_Importer' ),
                'methods'     => array(
                    array( 'Nine_AI_Manager_Importer', 'stage_upload' ),
                    array( 'Nine_AI_Manager_Importer', 'build_preview' ),
                    array( 'Nine_AI_Manager_Importer', 'apply_package' ),
                ),
                'purpose'     => 'AI package staging, preview, apply and history',
            ),
        );

        foreach ( $checks as $key => &$engine ) {
            $issues = array();
            foreach ( $engine['classes'] as $class_name ) {
                if ( ! class_exists( $class_name ) ) { $issues[] = 'Required component is not loaded: ' . $class_name . '.'; }
            }
            foreach ( $engine['methods'] as $contract ) {
                if ( ! method_exists( $contract[0], $contract[1] ) ) { $issues[] = 'Required safety contract is missing: ' . $contract[1] . '().'; }
            }
            if ( empty( $engine['version'] ) ) {
                $issues[] = 'Engine version could not be verified.';
            } elseif ( version_compare( $engine['version'], $engine['minimum'], '<' ) ) {
                $issues[] = 'Engine ' . $engine['version'] . ' is older than the Data Edition minimum ' . $engine['minimum'] . '.';
            }
            $engine['source'] = function_exists( 'nine55_ultron_external_plugin_active' ) && nine55_ultron_external_plugin_active( $engine['external'] ) ? 'Standalone' : 'Bundled';
            $engine['ready']  = empty( $issues );
            $engine['issues'] = $issues;
        }
        unset( $engine );
        return $checks;
    }

    private function engine_ready_or_error( $engine_key, $operation_label ) {
        $status = $this->engine_safety_status();
        if ( empty( $status[ $engine_key ] ) || empty( $status[ $engine_key ]['ready'] ) ) {
            $engine = isset( $status[ $engine_key ] ) ? $status[ $engine_key ] : array( 'label' => ucfirst( $engine_key ), 'issues' => array( 'Engine is unavailable.' ) );
            return new WP_Error(
                'ultron_engine_safety_gate',
                sprintf(
                    '%s stopped. %s is not ready for safe Data Edition routing. %s',
                    $operation_label,
                    $engine['label'],
                    implode( ' ', (array) $engine['issues'] )
                )
            );
        }
        return true;
    }

    private function render_engine_safety_gate() {
        $status = $this->engine_safety_status();
        $all_ready = true;
        foreach ( $status as $engine ) { if ( empty( $engine['ready'] ) ) { $all_ready = false; break; } }
        ?>
        <section class="ultron955-safety <?php echo $all_ready ? 'is-ready' : 'needs-attention'; ?>" aria-label="Engine safety gate">
            <div class="ultron955-safety-head">
                <div><h2>Engine Safety Gate</h2><p><?php echo $all_ready ? 'All four managers match the safe contracts 9.10 Data Edition expects.' : '9.10 Data Edition found an engine that needs attention. Unsafe AI routing is blocked for affected write engines.'; ?></p></div>
                <span class="ultron955-safety-state"><?php echo $all_ready ? 'READY' : 'CHECK'; ?></span>
            </div>
            <div class="ultron955-safety-grid">
                <?php foreach ( $status as $engine ) : ?>
                    <div class="ultron955-safety-engine <?php echo ! empty( $engine['ready'] ) ? 'is-ready' : 'needs-attention'; ?>">
                        <div class="ultron955-safety-engine-title"><strong><?php echo esc_html( $engine['label'] ); ?></strong><span><?php echo ! empty( $engine['ready'] ) ? 'Ready' : 'Needs attention'; ?></span></div>
                        <small><?php echo esc_html( $engine['source'] . ' · v' . ( $engine['version'] ?: '?' ) . ' · minimum v' . $engine['minimum'] ); ?></small>
                        <p><?php echo esc_html( $engine['purpose'] ); ?></p>
                        <?php if ( ! empty( $engine['issues'] ) ) : ?><p class="ultron955-safety-issue"><?php echo esc_html( implode( ' ', $engine['issues'] ) ); ?></p><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    private function engine_card( $title, $description, $page, $version, $icon ) {
        echo '<a class="ultron955-engine" href="' . esc_url( admin_url( 'admin.php?page=' . rawurlencode( $page ) ) ) . '">';
        echo '<span class="dashicons ' . esc_attr( $icon ) . '"></span><span class="ultron955-engine-text"><strong>' . esc_html( $title ) . '</strong><small>' . esc_html( $description ) . '</small></span><em>v' . esc_html( $version ) . '</em></a>';
    }

    private function tool_link( $title, $description, $page ) {
        echo '<a class="ultron955-tool" href="' . esc_url( admin_url( 'admin.php?page=' . rawurlencode( $page ) ) ) . '"><strong>' . esc_html( $title ) . '</strong><span>' . esc_html( $description ) . '</span></a>';
    }

    public function register_ai_integration( $integrations ) {
        $integrations['nine10_data_edition'] = array(
            'plugin_file'  => plugin_basename( NINE55_ULTRON_DATA_FILE ),
            'plugin_slug'  => 'nine10-data-edition',
            'label'        => '9.10 Data Edition',
            'description'  => 'Unified data/post/taxonomy operating layer. Route data-only changes through Data Engine and post/content changes through Post Editor.',
            'capabilities' => array( 'read', 'describe', 'data-update', 'post-update', 'taxonomy-update', 'bulk-data', 'excel-roundtrip' ),
            'schema'       => array(
                'operation' => array( 'describe', 'data_records', 'post_payload' ),
                'data_records' => array( 'records' => '9Code Data Engine records array; existing records only.' ),
                'post_payload' => array( 'post_id' => 'Existing WordPress post ID', 'payload' => '9 Post Editor payload.' ),
            ),
            'instructions' => array(
                'Use data_records for ACF values, safe plugin meta and taxonomy assignments without post publishing changes.',
                'Use post_payload only when title/content/publishing/post identity changes are intentionally required.',
                'Never bypass the human preview and recovery systems of the destination engine.',
            ),
            'handler' => array( $this, 'ai_integration_handler' ),
        );
        return $integrations;
    }

    public function ai_integration_handler( $item, $context = array() ) {
        $payload = isset( $item['payload'] ) && is_array( $item['payload'] ) ? $item['payload'] : $item;
        $operation = isset( $payload['operation'] ) ? sanitize_key( $payload['operation'] ) : 'describe';

        if ( 'describe' === $operation ) {
            return array(
                'message' => '9.10 Data Edition is available.',
                'version' => NINE55_ULTRON_DATA_VERSION,
                'engines' => array(
                    'data' => defined( 'NINECODE_ACF_DATA_ENGINE_VERSION' ) ? NINECODE_ACF_DATA_ENGINE_VERSION : 'external',
                    'post' => defined( 'NPM9_VERSION' ) ? NPM9_VERSION : 'external',
                    'category' => defined( 'NINECM_VERSION' ) ? NINECM_VERSION : 'external',
                    'ai' => defined( 'NINE_AI_MANAGER_VERSION' ) ? NINE_AI_MANAGER_VERSION : 'external',
                ),
                'safety_gate' => $this->engine_safety_status(),
            );
        }

        if ( 'data_records' === $operation ) {
            $gate = $this->engine_ready_or_error( 'data', 'AI data change' );
            if ( is_wp_error( $gate ) ) { return $gate; }
            if ( ! current_user_can( 'manage_ninecode_data' ) && ! current_user_can( 'manage_options' ) ) {
                return new WP_Error( 'ultron_data_permission', 'You do not have permission to apply Data Engine changes.' );
            }
            if ( ! class_exists( 'NineCode_Data_Importer' ) || empty( $payload['records'] ) || ! is_array( $payload['records'] ) ) {
                return new WP_Error( 'ultron_data_payload', 'A valid Data Engine records array is required.' );
            }
            $importer = new NineCode_Data_Importer();
            $result = $importer->import_records_atomic( $payload['records'], array(
                'dry_run' => false,
                'create_missing' => false,
                'create_missing_records' => false,
                'create_missing_terms' => false,
                'import_identity' => false,
                'import_term_identity' => true,
                'import_terms' => true,
                'capture_versions' => true,
            ), '9.10 Data Edition AI data change' );
            if ( is_wp_error( $result ) ) { return $result; }
            return array( 'message' => 'Data Engine change completed.', 'report' => $result );
        }

        if ( 'post_payload' === $operation ) {
            $gate = $this->engine_ready_or_error( 'post', 'AI post change' );
            if ( is_wp_error( $gate ) ) { return $gate; }
            $post_id = isset( $payload['post_id'] ) ? absint( $payload['post_id'] ) : 0;
            if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
                return new WP_Error( 'ultron_post_permission', 'A valid editable post ID is required.' );
            }
            if ( ! class_exists( 'Nine_Post_Manager' ) || empty( $payload['post_payload'] ) || ! is_array( $payload['post_payload'] ) ) {
                return new WP_Error( 'ultron_post_payload', 'A valid 9 Post Editor payload is required.' );
            }
            $result = Nine_Post_Manager::instance()->apply_external_payload( $post_id, $payload['post_payload'], 'Before 9.10 Data Edition AI post change', true );
            if ( is_wp_error( $result ) ) { return $result; }
            return array( 'message' => 'Post Editor change completed.', 'post_id' => $post_id, 'result' => $result );
        }

        return new WP_Error( 'ultron_operation', 'Unsupported 9.10 Data Edition AI operation.' );
    }
}
