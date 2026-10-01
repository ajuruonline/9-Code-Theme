<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NCU_Data_ACF_Data_Engine {
    private static $instance = null;
    private $menu_hook = '';
    private $table_hook = '';
    private $capability = 'manage_ninecode_data';

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate() {
        $role = get_role( 'administrator' );
        if ( $role && ! $role->has_cap( 'manage_ninecode_data' ) ) {
            $role->add_cap( 'manage_ninecode_data' );
        }
        if ( false === get_option( 'ninecode_acf_allocations', false ) ) {
            add_option( 'ninecode_acf_allocations', array(), '', false );
        }
        if ( false === get_option( 'ninecode_acf_managed_registry', false ) ) {
            add_option( 'ninecode_acf_managed_registry', array( 'post_types' => array(), 'taxonomies' => array() ), '', false );
        }
        if ( class_exists( 'NCU_Data_Data_Version_Manager' ) ) {
            NCU_Data_Data_Version_Manager::install();
        }
        update_option( 'ncu_data_engine_version', NCU_DATA_ENGINE_VERSION, false );
    }

    public static function deactivate() {
        // Intentionally preserve data, allocations, history and registry definitions.
    }

    private function __construct() {
        add_action( 'admin_init', array( $this, 'maybe_upgrade' ), 5 );
        add_action( 'init', array( $this, 'register_managed_registry' ), PHP_INT_MAX );
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
        add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
        add_filter( 'acf/load_field_group', array( $this, 'apply_virtual_allocations' ), 30 );
        add_shortcode( 'ninecode_acf', array( $this, 'shortcode_acf' ) );
        add_action( 'elementor/dynamic_tags/register', array( $this, 'register_elementor_dynamic_tag' ) );
        add_action( 'acf/save_post', array( $this, 'capture_before_acf_save' ), 5 );

        $actions = array(
            'ninecode_save_term_data'      => 'handle_save_term_data',
            'ninecode_save_data_view'      => 'handle_save_data_view',
            'ninecode_delete_data_view'    => 'handle_delete_data_view',
            'ninecode_save_terms'          => 'handle_save_terms',
            'ninecode_save_plugin_meta'    => 'handle_save_plugin_meta',
            'ninecode_bulk_category_update'=> 'handle_bulk_category_update',
            'ninecode_save_allocations'    => 'handle_save_allocations',
            'ninecode_save_registry'       => 'handle_save_registry',
            'ninecode_delete_registry'     => 'handle_delete_registry',
            'ninecode_acf_export_record'   => 'handle_export_record',
            'ninecode_acf_export_collection'=> 'handle_export_collection',
            'ninecode_acf_export_schema'   => 'handle_export_schema',
            'ninecode_acf_export_backup'   => 'handle_export_backup',
            'ninecode_acf_import'          => 'handle_import',
            'ninecode_acf_apply_stage'     => 'handle_apply_stage',
            'ninecode_acf_restore_backup'  => 'handle_restore_backup',
            'ninecode_acf_undo_import'     => 'handle_undo_import',
            'ninecode_acf_restore_version' => 'handle_restore_version',
        );
        foreach ( $actions as $action => $method ) {
            add_action( 'admin_post_' . $action, array( $this, $method ) );
        }
    }


    public function maybe_upgrade() {
        if ( class_exists( 'NCU_Data_Data_Version_Manager' ) ) {
            NCU_Data_Data_Version_Manager::maybe_install();
        }
        if ( NCU_DATA_ENGINE_VERSION !== get_option( 'ncu_data_engine_version' ) ) {
            update_option( 'ncu_data_engine_version', NCU_DATA_ENGINE_VERSION, false );
        }
    }

    public function capture_before_acf_save( $post_id ) {
        if ( empty( $_POST['ninecode_data_engine_save'] ) || ! current_user_can( $this->capability ) ) {
            return;
        }
        if ( class_exists( 'NCU_Data_Data_Version_Manager' ) ) {
            NCU_Data_Data_Version_Manager::capture_acf_post_id( $post_id, 'Before manual data save', 'manual' );
        }
    }

    public function dependency_notice() {
        /* ACF is an optional field adapter, not a dependency. The Data Manager
         * remains fully usable for registered plugin metadata and taxonomies. */
        return;
    }

    public function admin_menu() {
        /* Core owns the parent menu; Data Manager is one dependable workspace
         * inside the unified AI Data Manager instead of a competing second top-level product. */
        $this->menu_hook = add_submenu_page( null, 'AI Data Manager', 'AI Data Manager', $this->capability, 'nine-code-ultra-data-engine', array( $this, 'render_browser' ) );
        /* Secondary Data Manager views remain routable and permission-checked,
         * but are internal tabs rather than ten scattered WordPress submenus. */
        $this->table_hook = add_submenu_page( null, 'Edit Many', 'Edit Many', $this->capability, 'ninecode-acf-table', array( $this, 'render_data_table' ) );
        add_submenu_page( null, 'Fields', 'Fields', $this->capability, 'ninecode-acf-fields', array( $this, 'render_fields' ) );
        add_submenu_page( null, 'Field Groups', 'Field Groups', $this->capability, 'ninecode-acf-allocator', array( $this, 'render_allocator' ) );
        add_submenu_page( null, 'Taxonomies', 'Taxonomies', $this->capability, 'ninecode-acf-taxonomies', array( $this, 'render_taxonomies' ) );
        add_submenu_page( null, 'Excel / AI', 'Excel / AI', $this->capability, 'ninecode-acf-ai', array( $this, 'render_ai' ) );
        add_submenu_page( null, 'Versions', 'Versions', $this->capability, 'ninecode-acf-versions', array( $this, 'render_versions' ) );
        add_submenu_page( null, 'Backups', 'Backups', $this->capability, 'ninecode-acf-backups', array( $this, 'render_backups' ) );
        add_submenu_page( null, 'Health', 'Health', $this->capability, 'ninecode-acf-health', array( $this, 'render_health' ) );

        if ( $this->menu_hook ) {
            add_action( 'load-' . $this->menu_hook, array( $this, 'load_browser_page' ) );
        }
        if ( $this->table_hook ) {
            add_action( 'load-' . $this->table_hook, array( $this, 'load_data_table_page' ) );
        }
    }

    public function load_browser_page() {
        if ( isset( $_GET['view'] ) && 'editor' === sanitize_key( wp_unslash( $_GET['view'] ) ) && function_exists( 'acf_form_head' ) ) {
            acf_form_head();
        }
    }

    public function load_data_table_page() {
        if ( function_exists( 'acf_form_head' ) ) {
            acf_form_head();
        }
    }

    public function admin_assets( $hook ) {
        if ( false === strpos( (string) $hook, 'ninecode-acf' ) && false === strpos( (string) $hook, 'nine-code-ultra-data-engine' ) && false === strpos( (string) $hook, 'toplevel_page_nine-code-ultra' ) ) {
            $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
            if ( ! in_array( $page, array( 'nine-code-ultra', 'nine-code-ultra-data-engine' ), true ) ) { return; }
        }
        wp_enqueue_style( 'nine-code-ultra-data-engine', NCU_DATA_ENGINE_URL . 'assets/admin.css', array(), NCU_DATA_ENGINE_VERSION );
        wp_enqueue_script( 'nine-code-ultra-data-engine', NCU_DATA_ENGINE_URL . 'assets/admin.js', array(), NCU_DATA_ENGINE_VERSION, true );
        wp_localize_script( 'nine-code-ultra-data-engine', 'NineCodeDataEngine', array(
            'confirmImport' => 'Import these data changes now? A recovery snapshot will be saved first.',
            'confirmUndo'   => 'Undo the latest import and restore the recorded previous data values?',
            'confirmVersionRestore' => 'Restore this saved data version? A new safety version will be created first.',
        ) );
        if ( function_exists( 'acf_enqueue_scripts' ) ) {
            acf_enqueue_scripts();
        }
    }

    private function require_cap() {
        if ( ! current_user_can( $this->capability ) ) {
            wp_die( esc_html__( 'You do not have permission to manage 9Code data.', 'nine-code-ultra-data-engine' ) );
        }
    }

    private function page_header( $title, $description = '' ) {
        echo '<div class="wrap ninecode-wrap">';
        echo '<div class="ninecode-hero"><div><span class="ninecode-kicker">9CODE • DATA</span><h1>' . esc_html( $title ) . '</h1>';
        if ( $description ) {
            echo '<p>' . esc_html( $description ) . '</p>';
        }
        echo '</div><div class="ninecode-status">v' . esc_html( NCU_DATA_ENGINE_VERSION ) . '</div></div>';
        $this->render_manager_switcher();
        $this->render_flash_notice();
    }

    private function render_manager_switcher() {
        $data_url = admin_url( 'admin.php?page=nine-code-ultra-data-engine' );
        $category_url = $this->resolve_manager_url( array( 'ninecode-site-manager', 'ninecode-category-manager', 'ninecode-lecture-manager', 'ninecode-elearning-manager' ) );
        $post_url = $this->resolve_manager_url( array( 'ninecode-post-manager', 'nine-post-manager', '9-post-manager' ) );
        $category_url = apply_filters( 'ninecode_data_engine_category_manager_url', $category_url );
        $post_url = apply_filters( 'ninecode_data_engine_post_manager_url', $post_url );

        echo '<nav class="ninecode-manager-switcher" aria-label="9Code manager switcher">';
        echo '<a class="button button-primary is-active" href="' . esc_url( $data_url ) . '">Data Manager</a>';
        if ( $category_url ) {
            echo '<a class="button" href="' . esc_url( $category_url ) . '">Category / Lecture Manager</a>';
        } else {
            echo '<span class="button disabled" title="Install or activate the compatible category, site or lecture manager to enable this shortcut.">Category / Lecture Manager</span>';
        }
        if ( $post_url ) {
            echo '<a class="button" href="' . esc_url( $post_url ) . '">Post Manager</a>';
        } else {
            echo '<span class="button disabled" title="Install or activate 9 Post Manager to enable this shortcut.">Post Manager</span>';
        }
        echo '</nav>';
    }

    private function resolve_manager_url( $candidate_slugs ) {
        foreach ( (array) $candidate_slugs as $slug ) {
            $url = menu_page_url( $slug, false );
            if ( $url ) { return $url; }
        }
        return '';
    }

    private function page_footer() {
        echo '</div>';
    }

    private function render_flash_notice() {
        if ( ! empty( $_GET['ninecode_saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>Data saved.</p></div>';
        }
        if ( ! empty( $_GET['ninecode_message'] ) ) {
            $message = sanitize_text_field( wp_unslash( $_GET['ninecode_message'] ) );
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
        }
        $report_key = isset( $_GET['ninecode_report'] ) ? sanitize_key( wp_unslash( $_GET['ninecode_report'] ) ) : '';
        if ( $report_key ) {
            $report = get_transient( 'ninecode_report_' . get_current_user_id() . '_' . $report_key );
            if ( $report ) {
                delete_transient( 'ninecode_report_' . get_current_user_id() . '_' . $report_key );
                $mode = strtoupper( $report['mode'] ?? 'IMPORT' );
                $affected = intval( $report['affected_records_count'] ?? 0 );
                $actual_changes = intval( $report['change_details_total'] ?? ( $report['changed'] ?? 0 ) );
                $types = (array) ( $report['change_types'] ?? array() );
                $is_preview = 'PREVIEW' === $mode;
                echo '<div class="ninecode-card ninecode-report ninecode-change-review">';
                echo '<div class="ninecode-review-head"><div><span class="ninecode-review-kicker">' . esc_html( $is_preview ? 'CHANGE REVIEW — NOTHING CHANGED YET' : 'CHANGE RESULT' ) . '</span><h2>' . esc_html( $is_preview ? 'Review before you apply' : 'Import result' ) . '</h2><p>' . esc_html( $is_preview ? 'Check exactly what will change. WordPress data has not been changed by this preview.' : 'See exactly what this operation changed.' ) . '</p></div>';
                if ( isset( $report['scope_lock'] ) ) { echo '<span class="ninecode-safety-pill ' . ( 'verified' === $report['scope_lock'] ? 'is-safe' : 'is-legacy' ) . '">' . esc_html( 'verified' === $report['scope_lock'] ? 'Scope Lock verified' : 'Legacy file' ) . '</span>'; }
                echo '</div>';
                echo '<div class="ninecode-review-stats">';
                $stats = array( array( $affected, 'Records affected' ), array( $actual_changes, 'Value changes' ), array( intval( $types['core'] ?? 0 ), 'Core fields' ), array( intval( $types['acf'] ?? 0 ), 'ACF fields' ), array( intval( $types['taxonomy'] ?? 0 ), 'Taxonomy changes' ), array( intval( $report['conflicts'] ?? 0 ), 'Conflicts protected' ), array( intval( $report['errors_count'] ?? 0 ), 'Errors' ) );
                foreach ( $stats as $stat ) { echo '<div><strong>' . esc_html( $stat[0] ) . '</strong><span>' . esc_html( $stat[1] ) . '</span></div>'; }
                echo '</div>';
                if ( ! empty( $report['change_details'] ) ) {
                    echo '<div class="ninecode-change-list">';
                    foreach ( (array) $report['change_details'] as $change ) {
                        $change_id = sanitize_key( $change['change_id'] ?? '' );
                        echo '<details class="ninecode-change-row"><summary><span><strong>' . esc_html( $change['record'] ?? 'Record' ) . '</strong><small>#' . esc_html( intval( $change['object_id'] ?? 0 ) ) . ' · ' . esc_html( strtoupper( $change['type'] ?? 'data' ) ) . '</small></span><b>' . esc_html( $change['field'] ?? 'Data' ) . '</b></summary>';
                        echo '<div class="ninecode-before-after"><div><small>Before</small><p>' . esc_html( $change['before'] ?? '—' ) . '</p></div><div><small>After</small><p>' . esc_html( $change['after'] ?? '—' ) . '</p></div></div>';
                        if ( $is_preview && $change_id ) { echo '<label class="ninecode-change-choice"><input form="ninecode-selective-apply" type="checkbox" name="approved_change_ids[]" value="' . esc_attr( $change_id ) . '" checked> Apply this change</label>'; }
                        echo '</details>';
                    }
                    if ( $actual_changes > count( (array) $report['change_details'] ) ) { echo '<p class="description">Showing the first ' . esc_html( count( (array) $report['change_details'] ) ) . ' changes of ' . esc_html( $actual_changes ) . '.</p>'; }
                    echo '</div>';
                } elseif ( empty( $report['errors_count'] ) ) { echo '<p class="ninecode-no-change">No value differences were found.</p>'; }
                if ( $is_preview && empty( $report['errors_count'] ) && $actual_changes > 0 && ! empty( $report['stage_token'] ) ) {
                    echo '<div class="ninecode-apply-gate"><div><strong>Ready to apply this reviewed copy</strong><span>The uploaded file is locked to this preview. If live data changes before Apply, 9Code will stop and ask you to preview again.</span>';
                    if ( ! empty( $report['stage_hash_short'] ) ) { echo '<small>Reviewed file: ' . esc_html( $report['stage_hash_short'] ) . '</small>'; }
                    echo '<div class="ninecode-select-tools"><button type="button" class="button" data-ninecode-select-changes="all">Select all shown</button><button type="button" class="button" data-ninecode-select-changes="none">Clear shown</button></div>';
                    echo '</div><form id="ninecode-selective-apply" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-ninecode-confirm="import"><input type="hidden" name="action" value="ninecode_acf_apply_stage"><input type="hidden" name="stage_token" value="' . esc_attr( $report['stage_token'] ) . '">';
                    $shown_ids = array_values( array_filter( array_map( 'sanitize_key', wp_list_pluck( (array) ( $report['change_details'] ?? array() ), 'change_id' ) ) ) );
                    foreach ( array_diff( (array) ( $report['change_ids'] ?? array() ), $shown_ids ) as $hidden_change_id ) { echo '<input type="hidden" name="approved_change_ids[]" value="' . esc_attr( sanitize_key( $hidden_change_id ) ) . '">'; }
                    wp_nonce_field( 'ninecode_acf_apply_stage_' . $report['stage_token'] );
                    echo '<button class="button button-primary button-hero">Apply selected changes</button></form></div>';
                } elseif ( $is_preview && empty( $report['errors_count'] ) && $actual_changes > 0 ) {
                    echo '<div class="notice notice-warning inline"><p>This preview cannot be applied because its temporary reviewed copy is unavailable. Upload the file again and Preview.</p></div>';
                }
                echo '<p class="ninecode-report-line"><strong>' . esc_html( $mode ) . '</strong> — ' . esc_html( intval( $report['changed'] ?? 0 ) ) . ' operations, ' . esc_html( intval( $report['created'] ?? 0 ) ) . ' created, ' . esc_html( intval( $report['skipped'] ?? 0 ) ) . ' skipped.</p>';
                if ( ! empty( $report['messages'] ) ) {
                    echo '<details class="ninecode-technical-details"><summary>Validation and safety details</summary><ul class="ninecode-log">';
                    foreach ( array_slice( $report['messages'], 0, 100 ) as $line ) { echo '<li>' . esc_html( $line ) . '</li>'; }
                    echo '</ul></details>';
                }
                echo '</div>';
            }
        }
    }

    public function render_browser() {
        $this->require_cap();
        $view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
        if ( 'legacy' !== $view && function_exists( 'nce_ai_editor_page' ) ) {
            nce_ai_editor_page();
            return;
        }
        if ( 'editor' === $view ) {
            $this->render_data_editor();
            return;
        }
        $this->page_header( 'Data', 'Choose a record. You will see only its field values and taxonomies.' );

        $kind      = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : 'post';
        $post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
        $taxonomy  = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : 'category';
        $search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $sort      = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : 'modified_desc';

        echo '<div class="ninecode-card"><form method="get" class="ninecode-filter">';
        echo '<input type="hidden" name="page" value="nine-code-ultra-data-engine">';
        echo '<label>What do you want to edit?<select name="kind" data-ninecode-auto-submit><option value="post"' . selected( $kind, 'post', false ) . '>Records</option><option value="term"' . selected( $kind, 'term', false ) . '>Categories, tags and other taxonomy items</option></select></label>';
        if ( 'term' === $kind ) {
            echo '<label>Taxonomy<select name="taxonomy">';
            foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $tax ) {
                echo '<option value="' . esc_attr( $tax->name ) . '"' . selected( $taxonomy, $tax->name, false ) . '>' . esc_html( $tax->labels->singular_name ) . '</option>';
            }
            echo '</select></label>';
        } else {
            echo '<label>Data type<select name="post_type">';
            foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {
                if ( in_array( $type->name, array( 'attachment' ), true ) ) { continue; }
                echo '<option value="' . esc_attr( $type->name ) . '"' . selected( $post_type, $type->name, false ) . '>' . esc_html( $type->labels->singular_name ) . '</option>';
            }
            echo '</select></label>';
            echo '<label>Sort<select name="sort"><option value="modified_desc"' . selected( $sort, 'modified_desc', false ) . '>Recently updated</option><option value="modified_asc"' . selected( $sort, 'modified_asc', false ) . '>Oldest updated</option><option value="title_asc"' . selected( $sort, 'title_asc', false ) . '>Title A–Z</option><option value="title_desc"' . selected( $sort, 'title_desc', false ) . '>Title Z–A</option><option value="id_desc"' . selected( $sort, 'id_desc', false ) . '>Newest records</option><option value="id_asc"' . selected( $sort, 'id_asc', false ) . '>Oldest records</option></select></label>';
        }
        echo '<label class="ninecode-grow">Search<input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="Title, name or keyword"></label>';
        echo '<button class="button button-primary">Load data</button></form></div>';

        if ( 'term' === $kind ) {
            $this->render_term_browser( $taxonomy, $search );
        } else {
            $this->render_post_browser( $post_type, $search, $sort );
        }
        $this->page_footer();
    }

    private function render_post_browser( $post_type, $search, $sort = 'modified_desc' ) {
        if ( ! post_type_exists( $post_type ) ) {
            echo '<div class="notice notice-error"><p>Unknown post type.</p></div>';
            return;
        }
        $paged = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
        $sort_map = array(
            'modified_desc' => array( 'modified', 'DESC' ),
            'modified_asc'  => array( 'modified', 'ASC' ),
            'title_asc'     => array( 'title', 'ASC' ),
            'title_desc'    => array( 'title', 'DESC' ),
            'id_desc'       => array( 'ID', 'DESC' ),
            'id_asc'        => array( 'ID', 'ASC' ),
        );
        if ( ! isset( $sort_map[ $sort ] ) ) { $sort = 'modified_desc'; }
        list( $orderby, $order ) = $sort_map[ $sort ];
        $query = new WP_Query( array(
            'post_type'      => $post_type,
            'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
            'posts_per_page' => 25,
            'paged'          => $paged,
            's'              => $search,
            'orderby'        => $orderby,
            'order'          => $order,
        ) );

        echo '<div class="ninecode-actions">';
        $xlsx_url = wp_nonce_url( add_query_arg( array( 'action' => 'ninecode_acf_export_collection', 'kind' => 'post', 'post_type' => $post_type, 'format' => 'xlsx', 's' => $search ), admin_url( 'admin-post.php' ) ), 'ninecode_export_collection' );
        $json_url = wp_nonce_url( add_query_arg( array( 'action' => 'ninecode_acf_export_collection', 'kind' => 'post', 'post_type' => $post_type, 'format' => 'json', 's' => $search ), admin_url( 'admin-post.php' ) ), 'ninecode_export_collection' );
        $csv_url  = wp_nonce_url( add_query_arg( array( 'action' => 'ninecode_acf_export_collection', 'kind' => 'post', 'post_type' => $post_type, 'format' => 'csv', 's' => $search ), admin_url( 'admin-post.php' ) ), 'ninecode_export_collection' );
        $import_url = add_query_arg( array( 'page' => 'ninecode-acf-ai' ), admin_url( 'admin.php' ) );
        echo '<a class="button button-primary" href="' . esc_url( $xlsx_url ) . '">Export Excel</a><a class="button" href="' . esc_url( $csv_url ) . '">Export CSV</a><a class="button" href="' . esc_url( $json_url ) . '">Export AI / JSON</a><a class="button" href="' . esc_url( $import_url ) . '">Import Data</a>';
        echo '<span class="ninecode-count">' . esc_html( $query->found_posts ) . ' records</span></div>';

        echo '<div class="ninecode-record-grid">';
        $meta_exporter = new NCU_Data_Data_Exporter();
        foreach ( $query->posts as $post ) {
            $acf_count = function_exists( 'get_field_objects' ) ? count( (array) get_field_objects( $post->ID, false, false ) ) : 0;
            $plugin_meta_count = count( $meta_exporter->export_meta_for_post( $post->ID ) );
            $data_count = $acf_count + $plugin_meta_count;
            $edit_url = add_query_arg( array( 'page' => 'nine-code-ultra-data-engine', 'view' => 'editor', 'kind' => 'post', 'id' => $post->ID ), admin_url( 'admin.php' ) );
            $export_url = wp_nonce_url( add_query_arg( array( 'action' => 'ninecode_acf_export_record', 'kind' => 'post', 'id' => $post->ID ), admin_url( 'admin-post.php' ) ), 'ninecode_export_record_' . $post->ID );
            echo '<article class="ninecode-record"><div class="ninecode-record-main"><h3>' . esc_html( get_the_title( $post ) ?: 'Untitled record' ) . '</h3><p>' . esc_html( $data_count ) . ' data fields · ' . esc_html( $acf_count ) . ' ACF · ' . esc_html( $plugin_meta_count ) . ' plugin</p></div><div class="ninecode-record-actions"><a class="button button-primary" href="' . esc_url( $edit_url ) . '">Edit Data</a><a class="button" href="' . esc_url( $export_url ) . '">Export</a></div></article>';
        }
        if ( ! $query->posts ) {
            echo '<div class="ninecode-empty">No records found.</div>';
        }
        echo '</div>';
        echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'total' => max( 1, $query->max_num_pages ), 'current' => $paged ) ) ) . '</div></div>';
    }

    private function render_term_browser( $taxonomy, $search ) {
        if ( ! taxonomy_exists( $taxonomy ) ) {
            echo '<div class="notice notice-error"><p>Unknown taxonomy.</p></div>';
            return;
        }
        $page = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
        $per_page = 30;
        $args = array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => $per_page, 'offset' => ( $page - 1 ) * $per_page, 'search' => $search );
        $terms = get_terms( $args );
        $count = wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );

        echo '<div class="ninecode-actions">';
        $xlsx_url = wp_nonce_url( add_query_arg( array( 'action' => 'ninecode_acf_export_collection', 'kind' => 'term', 'taxonomy' => $taxonomy, 'format' => 'xlsx', 's' => $search ), admin_url( 'admin-post.php' ) ), 'ninecode_export_collection' );
        $json_url = wp_nonce_url( add_query_arg( array( 'action' => 'ninecode_acf_export_collection', 'kind' => 'term', 'taxonomy' => $taxonomy, 'format' => 'json', 's' => $search ), admin_url( 'admin-post.php' ) ), 'ninecode_export_collection' );
        $csv_url  = wp_nonce_url( add_query_arg( array( 'action' => 'ninecode_acf_export_collection', 'kind' => 'term', 'taxonomy' => $taxonomy, 'format' => 'csv', 's' => $search ), admin_url( 'admin-post.php' ) ), 'ninecode_export_collection' );
        $import_url = add_query_arg( array( 'page' => 'ninecode-acf-ai' ), admin_url( 'admin.php' ) );
        echo '<a class="button button-primary" href="' . esc_url( $xlsx_url ) . '">Export Excel</a><a class="button" href="' . esc_url( $csv_url ) . '">Export CSV</a><a class="button" href="' . esc_url( $json_url ) . '">Export AI / JSON</a><a class="button" href="' . esc_url( $import_url ) . '">Import Data</a><span class="ninecode-count">' . esc_html( intval( $count ) ) . ' terms</span></div>';

        echo '<div class="ninecode-record-grid">';
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $acf_id = 'term_' . $term->term_id;
                $acf_count = function_exists( 'get_field_objects' ) ? count( (array) get_field_objects( $acf_id, false, false ) ) : 0;
                $edit_url = add_query_arg( array( 'page' => 'nine-code-ultra-data-engine', 'view' => 'editor', 'kind' => 'term', 'id' => $term->term_id, 'taxonomy' => $taxonomy ), admin_url( 'admin.php' ) );
                $export_url = wp_nonce_url( add_query_arg( array( 'action' => 'ninecode_acf_export_record', 'kind' => 'term', 'taxonomy' => $taxonomy, 'id' => $term->term_id ), admin_url( 'admin-post.php' ) ), 'ninecode_export_record_' . $term->term_id );
                echo '<article class="ninecode-record"><div class="ninecode-record-main"><h3>' . esc_html( $term->name ) . '</h3><p>' . esc_html( $acf_count ) . ' data fields</p></div><div class="ninecode-record-actions"><a class="button button-primary" href="' . esc_url( $edit_url ) . '">Edit Data</a><a class="button" href="' . esc_url( $export_url ) . '">Export</a></div></article>';
            }
        }
        if ( is_wp_error( $terms ) || ! $terms ) {
            echo '<div class="ninecode-empty">No terms found.</div>';
        }
        echo '</div>';
        $total_pages = $per_page ? ceil( intval( $count ) / $per_page ) : 1;
        echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'total' => max( 1, $total_pages ), 'current' => $page ) ) ) . '</div></div>';
    }

    private function get_object_context() {
        $kind = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : 'post';
        $id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( ! $id ) {
            return new WP_Error( 'missing_id', 'Missing data record ID.' );
        }
        if ( 'term' === $kind ) {
            $taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
            $term = get_term( $id, $taxonomy ?: '' );
            if ( ! $term || is_wp_error( $term ) ) {
                return new WP_Error( 'term_not_found', 'Taxonomy term not found.' );
            }
            return array( 'kind' => 'term', 'id' => $id, 'taxonomy' => $term->taxonomy, 'term' => $term, 'acf_id' => 'term_' . $id );
        }
        $post = get_post( $id );
        if ( ! $post ) {
            return new WP_Error( 'post_not_found', 'Post record not found.' );
        }
        return array( 'kind' => 'post', 'id' => $id, 'post' => $post, 'post_type' => $post->post_type, 'acf_id' => $id );
    }

    private function render_data_editor() {
        $this->require_cap();
        $context = $this->get_object_context();
        if ( is_wp_error( $context ) ) {
            $this->page_header( 'Data Editor' );
            echo '<div class="notice notice-error"><p>' . esc_html( $context->get_error_message() ) . '</p></div>';
            $this->page_footer();
            return;
        }

        $title = 'post' === $context['kind'] ? ( get_the_title( $context['post'] ) ?: 'Untitled record' ) : $context['term']->name;
        $this->page_header( 'Data Editor', 'Edit values. Save them here and the post, page or template that uses these fields will show the saved data.' );

        echo '<div class="ninecode-editor-toolbar"><a class="button" href="' . esc_url( admin_url( 'admin.php?page=nine-code-ultra-data-engine' ) ) . '">← Data</a><strong>' . esc_html( $title ) . '</strong>';
        $export_url = wp_nonce_url( add_query_arg( array( 'action' => 'ninecode_acf_export_record', 'kind' => $context['kind'], 'id' => $context['id'], 'taxonomy' => $context['taxonomy'] ?? '' ), admin_url( 'admin-post.php' ) ), 'ninecode_export_record_' . $context['id'] );
        echo '<a class="button" href="' . esc_url( $export_url ) . '">Export Data</a></div>';

        if ( 'term' === $context['kind'] ) {
            $this->render_term_data_panel( $context );
        } else {
            $this->render_term_assignment_panel( $context['post'] );
        }

        if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_form' ) ) {
            echo '<div class="ninecode-card"><h2>ACF fields are not available</h2><p>Activate Advanced Custom Fields (ACF) to edit ACF values. Plugin fields and taxonomy data remain available.</p></div>';
        } else {
            $groups = acf_get_field_groups( array( 'post_id' => $context['acf_id'] ) );
            $group_ids = array();
            $field_count = 0;
            foreach ( $groups as $group ) {
                $group_ids[] = $group['key'] ?? $group['ID'];
                if ( function_exists( 'acf_get_fields' ) ) {
                    $field_count += count( (array) acf_get_fields( $group ) );
                }
            }

            echo '<section class="ninecode-card ninecode-acf-editor"><div class="ninecode-section-head"><div><span class="ninecode-kicker">ACF VALUES</span><h2>ACF fields</h2><p>Edit headings, paragraphs, text, links, numbers, dates, images, files, galleries, choices, relationships, repeaters and other ACF data.</p></div><span class="ninecode-badge">' . esc_html( $field_count ) . ' fields</span></div>';
            if ( ! $groups ) {
                echo '<div class="ninecode-empty">No ACF fields are allocated to this record yet. Use <strong>Field Groups</strong> if you need to allocate one.</div>';
            } else {
                echo '<div class="ninecode-field-find"><label for="ninecode-field-search">Find an ACF field</label><input id="ninecode-field-search" type="search" placeholder="Heading, Description, Image..." autocomplete="off"><span data-ninecode-field-count>' . esc_html( $field_count ) . ' fields shown</span></div>';
                acf_form( array(
                    'post_id'          => $context['acf_id'],
                    'field_groups'     => $group_ids,
                    'form'             => true,
                    'return'           => add_query_arg( array( 'page' => 'nine-code-ultra-data-engine', 'view' => 'editor', 'kind' => $context['kind'], 'id' => $context['id'], 'taxonomy' => $context['taxonomy'] ?? '', 'ninecode_saved' => 1 ), admin_url( 'admin.php' ) ),
                    'submit_value'     => 'Save ACF Data',
                    'updated_message'  => 'Data saved.',
                    'html_before_fields' => '<input type="hidden" name="ninecode_data_engine_save" value="1">',
                    'html_submit_button' => '<button type="submit" class="button button-primary button-hero ninecode-primary-save">%s</button>',
                ) );
            }
            echo '</section>';
        }
        if ( 'post' === $context['kind'] ) {
            $this->render_plugin_meta_panel( $context['post'] );
        }
        $this->page_footer();
    }

    private function render_term_data_panel( $context ) {
        $term = $context['term'];
        echo '<section class="ninecode-card"><div class="ninecode-section-head"><div><span class="ninecode-kicker">TAXONOMY VALUE</span><h2>Taxonomy data</h2><p>Edit the visible name and description of this category, tag or taxonomy item. Technical settings stay out of this screen.</p></div></div>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ninecode-form-grid">';
        wp_nonce_field( 'ninecode_save_term_data_' . $term->term_id );
        echo '<input type="hidden" name="action" value="ninecode_save_term_data"><input type="hidden" name="id" value="' . esc_attr( $term->term_id ) . '"><input type="hidden" name="taxonomy" value="' . esc_attr( $term->taxonomy ) . '">';
        echo '<label class="ninecode-span-2">Name<input type="text" name="name" value="' . esc_attr( $term->name ) . '" required></label>';
        echo '<label class="ninecode-span-2">Description<textarea name="description" rows="4">' . esc_textarea( $term->description ) . '</textarea></label>';
        echo '<div class="ninecode-form-actions ninecode-span-2"><button class="button button-primary">Save Taxonomy Data</button></div></form></section>';
    }

    private function render_term_assignment_panel( $post ) {
        $taxonomies = get_object_taxonomies( $post->post_type, 'objects' );
        $visible = array_filter( (array) $taxonomies, function( $tax ) { return ! empty( $tax->show_ui ); } );
        if ( ! $visible ) { return; }
        echo '<details class="ninecode-card ninecode-details ninecode-taxonomy-details">';
        echo '<summary><div><strong>Taxonomies</strong><span>Categories, tags and other classifications attached to this record.</span></div><span class="ninecode-badge">' . esc_html( count( $visible ) ) . '</span></summary>';
        echo '<div class="ninecode-details-body"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ninecode-form-grid">';
        wp_nonce_field( 'ninecode_save_terms_' . $post->ID );
        echo '<input type="hidden" name="action" value="ninecode_save_terms"><input type="hidden" name="id" value="' . esc_attr( $post->ID ) . '">';
        foreach ( $visible as $tax ) {
            $assigned = wp_get_object_terms( $post->ID, $tax->name, array( 'fields' => 'names' ) );
            $assigned = is_wp_error( $assigned ) ? array() : $assigned;
            echo '<label class="ninecode-span-2">' . esc_html( $tax->labels->name ) . '<input type="text" name="terms[' . esc_attr( $tax->name ) . ']" value="' . esc_attr( implode( ', ', $assigned ) ) . '" placeholder="Names separated by commas"><small>Use an existing name or type a new taxonomy value.</small></label>';
        }
        echo '<div class="ninecode-form-actions ninecode-span-2"><button class="button button-primary">Save Taxonomies</button></div>';
        echo '</form></div></details>';
    }

    private function render_plugin_meta_panel( $post ) {
        $exporter = new NCU_Data_Data_Exporter();
        $rows = $exporter->export_meta_for_post( $post->ID );
        echo '<details class="ninecode-card ninecode-details ninecode-plugin-meta">';
        echo '<summary><div><strong>Plugin fields</strong><span>Custom post meta from 9 plugins and other plugins. WordPress and Elementor design internals stay protected.</span></div><span class="ninecode-badge">' . esc_html( count( $rows ) ) . ' fields</span></summary>';
        echo '<div class="ninecode-details-body">';
        if ( ! $rows ) {
            echo '<div class="ninecode-empty">No editable plugin meta is stored or registered for this record.</div></div></details>';
            return;
        }
        echo '<div class="ninecode-field-find ninecode-meta-find"><label for="ninecode-meta-search">Find a plugin field</label><input id="ninecode-meta-search" type="search" placeholder="Search plugin field name or meta key" autocomplete="off"><span data-ninecode-meta-count>' . esc_html( count( $rows ) ) . ' fields shown</span></div>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ninecode-meta-form">';
        wp_nonce_field( 'ninecode_save_plugin_meta_' . $post->ID );
        echo '<input type="hidden" name="action" value="ninecode_save_plugin_meta"><input type="hidden" name="id" value="' . esc_attr( $post->ID ) . '">';
        echo '<div class="ninecode-meta-grid">';
        foreach ( $rows as $row ) {
            $key = (string) ( $row['key'] ?? '' );
            $storage = 'multi' === ( $row['storage'] ?? '' ) ? 'multi' : 'single';
            $value = $row['value'] ?? '';
            $structured = 'multi' === $storage || is_array( $value ) || is_object( $value );
            $encoding = $structured ? 'json' : 'plain';
            $display = $structured ? wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : ( is_bool( $value ) ? ( $value ? 'true' : 'false' ) : (string) $value );
            $long = $structured || false !== strpos( $display, "\n" ) || strlen( $display ) > 140;
            echo '<div class="ninecode-meta-field" data-ninecode-meta-field data-search="' . esc_attr( strtolower( ( $row['label'] ?? '' ) . ' ' . $key ) ) . '">';
            echo '<label><strong>' . esc_html( $row['label'] ?? $key ) . '</strong><small>Meta key: <code>' . esc_html( $key ) . '</code>' . ( ! empty( $row['instructions'] ) ? ' · ' . esc_html( $row['instructions'] ) : '' ) . '</small>';
            echo '<input type="hidden" name="meta_keys[]" value="' . esc_attr( $key ) . '"><input type="hidden" name="meta_storage[]" value="' . esc_attr( $storage ) . '"><input type="hidden" name="meta_encoding[]" value="' . esc_attr( $encoding ) . '">';
            if ( $long ) {
                echo '<textarea name="meta_values[]" rows="' . esc_attr( $structured ? 6 : 4 ) . '">' . esc_textarea( $display ) . '</textarea>';
                if ( $structured ) { echo '<small>Structured plugin data. Keep valid JSON.</small>'; }
            } else {
                echo '<input type="text" name="meta_values[]" value="' . esc_attr( $display ) . '">';
            }
            echo '</label></div>';
        }
        echo '</div><div class="ninecode-sticky-save"><button class="button button-primary button-hero">Save Plugin Data</button></div></form></div></details>';
    }

    public function handle_save_plugin_meta() {
        $this->require_cap();
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        check_admin_referer( 'ninecode_save_plugin_meta_' . $id );
        $post = get_post( $id );
        if ( ! $post ) { wp_die( 'Record not found.' ); }

        $exporter = new NCU_Data_Data_Exporter();
        $allowed_rows = $exporter->export_meta_for_post( $id );
        $allowed = array();
        foreach ( $allowed_rows as $row ) { if ( ! empty( $row['key'] ) ) { $allowed[ (string) $row['key'] ] = $row; } }

        $keys = isset( $_POST['meta_keys'] ) ? (array) wp_unslash( $_POST['meta_keys'] ) : array();

        // Edit Many can deliberately populate a safe plugin field that exists on
        // another record of the same post type but is still empty on this record.
        // Only fields discovered by the protected workspace inventory are added.
        if ( ! empty( $_POST['workspace_meta'] ) ) {
            $workspace = $this->get_workspace_fields( 'post', $post->post_type, '' );
            foreach ( $workspace as $workspace_field ) {
                if ( 'meta' !== ( $workspace_field['source'] ?? '' ) || empty( $workspace_field['meta_key'] ) ) { continue; }
                $workspace_key = (string) $workspace_field['meta_key'];
                if ( ! in_array( $workspace_key, $keys, true ) || isset( $allowed[ $workspace_key ] ) ) { continue; }
                $allowed[ $workspace_key ] = array(
                    'key' => $workspace_key,
                    'label' => $workspace_field['label'] ?? $workspace_key,
                    'storage' => 'multi' === ( $workspace_field['storage'] ?? '' ) ? 'multi' : 'single',
                    'instructions' => $workspace_field['instructions'] ?? '',
                );
            }
        }

        $storages = isset( $_POST['meta_storage'] ) ? (array) wp_unslash( $_POST['meta_storage'] ) : array();
        $encodings = isset( $_POST['meta_encoding'] ) ? (array) wp_unslash( $_POST['meta_encoding'] ) : array();
        $values = isset( $_POST['meta_values'] ) ? (array) wp_unslash( $_POST['meta_values'] ) : array();
        $prepared = array();
        $error = '';
        foreach ( $keys as $i => $key ) {
            $key = (string) $key;
            if ( ! isset( $allowed[ $key ] ) ) { continue; }
            $storage = 'multi' === ( $storages[ $i ] ?? '' ) ? 'multi' : 'single';
            $encoding = 'json' === ( $encodings[ $i ] ?? '' ) ? 'json' : 'plain';
            $raw = (string) ( $values[ $i ] ?? '' );
            if ( 'json' === $encoding ) {
                $decoded = json_decode( $raw, true );
                if ( JSON_ERROR_NONE !== json_last_error() ) {
                    $error = 'Plugin field “' . ( $allowed[ $key ]['label'] ?? $key ) . '” contains invalid JSON. Nothing was saved.';
                    break;
                }
                $value = $decoded;
            } else {
                $value = $raw;
            }
            if ( 'multi' === $storage && ! is_array( $value ) ) { $value = array( $value ); }
            $prepared[] = array( 'key' => $key, 'storage' => $storage, 'value' => $value );
        }

        $base = add_query_arg( array( 'page' => 'nine-code-ultra-data-engine', 'view' => 'editor', 'kind' => 'post', 'id' => $id ), admin_url( 'admin.php' ) );
        $return_to = isset( $_POST['return_to'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['return_to'] ) ), $base ) : $base;
        if ( $error ) {
            wp_safe_redirect( add_query_arg( 'ninecode_message', $error, $return_to ) );
            exit;
        }
        if ( class_exists( 'NCU_Data_Data_Version_Manager' ) && $prepared ) {
            NCU_Data_Data_Version_Manager::capture_object( 'post', $id, '', 'Before plugin meta save', 'manual' );
        }
        $changed = 0;
        foreach ( $prepared as $row ) {
            $key = $row['key'];
            $storage = $row['storage'];
            $value = $row['value'];
            $before = 'multi' === $storage ? array_map( 'maybe_unserialize', (array) get_post_meta( $id, $key, false ) ) : maybe_unserialize( get_post_meta( $id, $key, true ) );
            if ( maybe_serialize( $before ) === maybe_serialize( $value ) ) { continue; }
            if ( 'multi' === $storage ) {
                delete_post_meta( $id, $key );
                foreach ( (array) $value as $item ) { add_post_meta( $id, $key, $item, false ); }
            } else {
                update_post_meta( $id, $key, $value );
            }
            $changed++;
        }
        wp_safe_redirect( add_query_arg( array( 'ninecode_saved' => 1, 'ninecode_message' => $changed . ' plugin field' . ( 1 === $changed ? '' : 's' ) . ' saved.' ), $return_to ) );
        exit;
    }

    public function handle_bulk_category_update() {
        $this->require_cap();
        check_admin_referer( 'ninecode_bulk_category_update' );
        if ( empty( $_POST['confirm_bulk'] ) ) { wp_die( 'Confirm the bulk data change before applying it.' ); }

        $post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
        $filter_taxonomy = isset( $_POST['filter_taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['filter_taxonomy'] ) ) : '';
        $filter_term = isset( $_POST['filter_term'] ) ? absint( $_POST['filter_term'] ) : 0;
        $target = isset( $_POST['bulk_target'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_target'] ) ) : '';
        $operation = isset( $_POST['bulk_operation'] ) ? sanitize_key( wp_unslash( $_POST['bulk_operation'] ) ) : 'set';
        $raw_value = isset( $_POST['bulk_value'] ) ? trim( (string) wp_unslash( $_POST['bulk_value'] ) ) : '';
        $fields = isset( $_POST['fields'] ) ? array_values( array_filter( array_map( 'sanitize_key', (array) wp_unslash( $_POST['fields'] ) ) ) ) : array();

        if ( ! post_type_exists( $post_type ) ) { wp_die( 'That data type is not available.' ); }
        if ( $filter_taxonomy && ( ! taxonomy_exists( $filter_taxonomy ) || ! is_object_in_taxonomy( $post_type, $filter_taxonomy ) ) ) { wp_die( 'That category/taxonomy does not belong to this data type.' ); }
        if ( $filter_term && ! $filter_taxonomy ) { wp_die( 'Choose the taxonomy that contains this category/item.' ); }
        if ( $filter_term ) {
            $term = get_term( $filter_term, $filter_taxonomy );
            if ( ! $term || is_wp_error( $term ) ) { wp_die( 'The selected category/taxonomy item no longer exists.' ); }
        }

        $query_args = array( 'post_type' => $post_type, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'posts_per_page' => 1001, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' );
        if ( $filter_taxonomy && $filter_term ) { $query_args['tax_query'] = array( array( 'taxonomy' => $filter_taxonomy, 'field' => 'term_id', 'terms' => array( $filter_term ) ) ); }
        $ids = array_values( array_map( 'absint', (array) get_posts( $query_args ) ) );
        if ( count( $ids ) > 1000 ) { wp_die( 'This group has more than 1,000 records. Narrow the category/group before applying one bulk change.' ); }
        if ( ! $ids ) { wp_die( 'No matching records were found.' ); }

        $available = $this->get_workspace_fields( 'post', $post_type, '' );
        $records = array();
        $target_label = $target;
        if ( 0 === strpos( $target, 'field:' ) ) {
            $field_key = sanitize_key( substr( $target, 6 ) );
            if ( empty( $available[ $field_key ] ) ) { wp_die( 'That field is not available for this data type.' ); }
            if ( ! in_array( $operation, array( 'set', 'clear' ), true ) ) { wp_die( 'Use Set or Clear for ACF and plugin fields.' ); }
            $field = $available[ $field_key ];
            $value = 'clear' === $operation ? '' : $raw_value;
            if ( 'set' === $operation && '' !== $raw_value && in_array( substr( $raw_value, 0, 1 ), array( '[', '{' ), true ) ) {
                $decoded = json_decode( $raw_value, true );
                if ( JSON_ERROR_NONE !== json_last_error() ) { wp_die( 'The structured value is not valid JSON. Nothing was changed.' ); }
                $value = $decoded;
            }
            $target_label = (string) ( $field['label'] ?? $field['name'] ?? $field_key );
            foreach ( $ids as $id ) {
                $record = array( 'object' => array( 'kind' => 'post', 'id' => $id, 'post_type' => $post_type ), 'fields' => array(), 'meta' => array(), 'taxonomies' => array() );
                if ( 'meta' === ( $field['source'] ?? 'acf' ) ) {
                    $record['meta'][] = array( 'key' => (string) $field['meta_key'], 'storage' => 'multi' === ( $field['storage'] ?? '' ) ? 'multi' : 'single', 'value' => $value );
                } else {
                    $record['fields'][] = array( 'key' => $field_key, 'name' => (string) ( $field['name'] ?? '' ), 'value' => $value );
                }
                $records[] = $record;
            }
        } elseif ( 0 === strpos( $target, 'tax:' ) ) {
            $target_taxonomy = sanitize_key( substr( $target, 4 ) );
            if ( ! taxonomy_exists( $target_taxonomy ) || ! is_object_in_taxonomy( $post_type, $target_taxonomy ) ) { wp_die( 'That taxonomy is not available for this data type.' ); }
            if ( ! in_array( $operation, array( 'set', 'clear', 'add_terms', 'remove_terms' ), true ) ) { wp_die( 'Choose a valid taxonomy bulk change.' ); }
            $tax_obj = get_taxonomy( $target_taxonomy );
            $target_label = $tax_obj ? $tax_obj->labels->singular_name : $target_taxonomy;
            $requested = array_values( array_filter( array_map( 'trim', explode( ',', $raw_value ) ) ) );
            foreach ( $ids as $id ) {
                $existing = wp_get_object_terms( $id, $target_taxonomy, array( 'fields' => 'all' ) );
                if ( is_wp_error( $existing ) ) { wp_die( esc_html( $existing->get_error_message() ) ); }
                $slugs = array_map( function( $t ) { return (string) $t->slug; }, (array) $existing );
                $requested_slugs = array_map( 'sanitize_title', $requested );
                $requested_names = array();
                foreach ( $requested as $i => $requested_name ) { if ( ! empty( $requested_slugs[ $i ] ) ) { $requested_names[ $requested_slugs[ $i ] ] = sanitize_text_field( $requested_name ); } }
                if ( 'clear' === $operation ) { $slugs = array(); }
                elseif ( 'add_terms' === $operation ) { $slugs = array_values( array_unique( array_merge( $slugs, $requested_slugs ) ) ); }
                elseif ( 'remove_terms' === $operation ) { $slugs = array_values( array_diff( $slugs, $requested_slugs ) ); }
                else { $slugs = $requested_slugs; }
                $tax_items = array();
                foreach ( $slugs as $slug ) { $tax_items[] = array( 'slug' => $slug, 'name' => $requested_names[ $slug ] ?? $slug, 'taxonomy' => $target_taxonomy ); }
                $records[] = array( 'object' => array( 'kind' => 'post', 'id' => $id, 'post_type' => $post_type ), 'fields' => array(), 'meta' => array(), 'taxonomies' => array( $target_taxonomy => $tax_items ) );
            }
        } else { wp_die( 'Choose the data field or taxonomy to change.' ); }

        $group_label = 'all ' . $post_type . ' records';
        if ( $filter_taxonomy && $filter_term ) {
            $term = get_term( $filter_term, $filter_taxonomy );
            if ( $term && ! is_wp_error( $term ) ) { $group_label = $term->name . ' (' . $filter_taxonomy . ')'; }
        }
        $label = 'Category bulk: ' . $group_label . ' → ' . $target_label;
        $importer = new NCU_Data_Data_Importer();
        $report = $importer->import_records_atomic( $records, array(
            'create_missing_terms' => ! empty( $_POST['create_missing_terms'] ),
            'import_terms' => true,
            'capture_versions' => true,
            'import_identity' => true,
            'import_term_identity' => false,
        ), $label );

        $default = $this->data_table_url( array( 'kind' => 'post', 'post_type' => $post_type, 'filter_taxonomy' => $filter_taxonomy, 'filter_term' => $filter_term, 'fields' => $fields ) );
        $return_to = isset( $_POST['return_to'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['return_to'] ) ), $default ) : $default;
        if ( is_wp_error( $report ) ) { $message = $report->get_error_message(); }
        else {
            $status = (string) ( $report['atomic_status'] ?? '' );
            if ( 'committed' === $status ) { $message = intval( $report['changed'] ?? 0 ) . ' data change' . ( 1 === intval( $report['changed'] ?? 0 ) ? '' : 's' ) . ' applied across ' . count( $ids ) . ' matching records. Recovery versions and audit history were saved.'; }
            elseif ( 'rolled_back' === $status ) { $message = 'The bulk change failed and was automatically rolled back. Review Versions before trying again.'; }
            else { $message = 'Validation blocked the bulk change. Nothing was changed.'; }
        }
        wp_safe_redirect( add_query_arg( array( 'ninecode_saved' => 1, 'ninecode_message' => $message ), $return_to ) );
        exit;
    }

    public function handle_save_term_data() {
        $this->require_cap();
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
        check_admin_referer( 'ninecode_save_term_data_' . $id );
        $term = get_term( $id, $taxonomy );
        if ( ! $term || is_wp_error( $term ) ) { wp_die( 'Taxonomy item not found.' ); }
        if ( class_exists( 'NCU_Data_Data_Version_Manager' ) ) {
            NCU_Data_Data_Version_Manager::capture_object( 'term', $id, $taxonomy, 'Before taxonomy data save', 'manual' );
        }
        $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : $term->name;
        $description = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : $term->description;
        wp_update_term( $id, $taxonomy, array( 'name' => $name, 'description' => $description ) );
        $default = add_query_arg( array( 'page' => 'nine-code-ultra-data-engine', 'view' => 'editor', 'kind' => 'term', 'id' => $id, 'taxonomy' => $taxonomy, 'ninecode_saved' => 1 ), admin_url( 'admin.php' ) );
        $return_to = isset( $_POST['return_to'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['return_to'] ) ), $default ) : $default;
        wp_safe_redirect( $return_to );
        exit;
    }

    public function handle_save_terms() {
        $this->require_cap();
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        check_admin_referer( 'ninecode_save_terms_' . $id );
        $post = get_post( $id );
        if ( ! $post ) { wp_die( 'Record not found.' ); }
        if ( class_exists( 'NCU_Data_Data_Version_Manager' ) ) {
            NCU_Data_Data_Version_Manager::capture_object( 'post', $id, '', 'Before taxonomy assignment save', 'manual' );
        }
        $submitted = isset( $_POST['terms'] ) && is_array( $_POST['terms'] ) ? wp_unslash( $_POST['terms'] ) : array();
        foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $tax ) {
            if ( ! isset( $submitted[ $tax->name ] ) ) { continue; }
            $names = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( $submitted[ $tax->name ] ) ) ) );
            wp_set_object_terms( $id, $names, $tax->name, false );
        }
        $default = add_query_arg( array( 'page' => 'nine-code-ultra-data-engine', 'view' => 'editor', 'kind' => 'post', 'id' => $id, 'ninecode_saved' => 1 ), admin_url( 'admin.php' ) );
        $return_to = isset( $_POST['return_to'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['return_to'] ) ), $default ) : $default;
        wp_safe_redirect( $return_to );
        exit;
    }

    public function render_data_table() {
        $this->require_cap();
        $this->page_header( 'Data Table', 'Choose the fields you want to work with, then edit several records from one data-only screen.' );

        if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_form' ) ) {
            echo '<div class="notice notice-info"><p><strong>ACF is optional.</strong> This table is ready for safe plugin metadata and taxonomy values. Activate ACF only when you need its field groups.</p></div>';
        }

        $views = $this->get_saved_data_views();
        if ( $views ) {
            echo '<div class="ninecode-card ninecode-workspaces"><div class="ninecode-section-head"><div><span class="ninecode-kicker">REOPEN YOUR DATA</span><h2>Saved Data Workspaces</h2><p>A workspace remembers the data type, category/group and fields you chose. Open it or export the same working set directly to AI, Excel or CSV.</p></div><span class="ninecode-badge">' . esc_html( count( $views ) ) . ' saved</span></div><div class="ninecode-workspace-grid">';
            foreach ( $views as $key => $view ) {
                $kind_view = 'term' === ( $view['kind'] ?? 'post' ) ? 'term' : 'post';
                $post_type_view = sanitize_key( $view['post_type'] ?? 'post' );
                $taxonomy_view = sanitize_key( $view['taxonomy'] ?? 'category' );
                $filter_taxonomy_view = sanitize_key( $view['filter_taxonomy'] ?? '' );
                $filter_term_view = absint( $view['filter_term'] ?? 0 );
                $fields_view = array_values( array_filter( array_map( 'sanitize_key', (array) ( $view['fields'] ?? array() ) ) ) );
                $url = $this->data_table_url( array(
                    'kind' => $kind_view,
                    'post_type' => $post_type_view,
                    'taxonomy' => $taxonomy_view,
                    'fields' => $fields_view,
                    'filter_taxonomy' => $filter_taxonomy_view,
                    'filter_term' => $filter_term_view,
                ) );
                $context = 'term' === $kind_view ? $taxonomy_view : $post_type_view;
                $group_label = 'All records';
                if ( 'term' === $kind_view ) { $group_label = 'All taxonomy items'; }
                if ( 'post' === $kind_view && $filter_taxonomy_view && $filter_term_view ) {
                    $group_term = get_term( $filter_term_view, $filter_taxonomy_view );
                    if ( $group_term && ! is_wp_error( $group_term ) ) { $group_label = $group_term->name; }
                }
                $export_args = array(
                    'action' => 'ninecode_acf_export_collection',
                    'kind' => $kind_view,
                    'format' => 'json',
                    'fields' => $fields_view,
                );
                if ( 'term' === $kind_view ) {
                    $export_args['taxonomy'] = $taxonomy_view;
                } else {
                    $export_args['post_type'] = $post_type_view;
                    if ( $filter_taxonomy_view && $filter_term_view ) {
                        $group_term = get_term( $filter_term_view, $filter_taxonomy_view );
                        if ( $group_term && ! is_wp_error( $group_term ) ) {
                            $export_args['taxonomy'] = $filter_taxonomy_view;
                            $export_args['term'] = $group_term->slug;
                        }
                    }
                }
                $ai_url = wp_nonce_url( add_query_arg( $export_args, admin_url( 'admin-post.php' ) ), 'ninecode_export_collection' );
                $export_args['format'] = 'xlsx';
                $xlsx_url = wp_nonce_url( add_query_arg( $export_args, admin_url( 'admin-post.php' ) ), 'ninecode_export_collection' );
                $export_args['format'] = 'csv';
                $csv_url = wp_nonce_url( add_query_arg( $export_args, admin_url( 'admin-post.php' ) ), 'ninecode_export_collection' );
                $delete = wp_nonce_url( add_query_arg( array( 'action' => 'ninecode_delete_data_view', 'view_key' => $key ), admin_url( 'admin-post.php' ) ), 'ninecode_delete_data_view_' . $key );
                echo '<article class="ninecode-workspace-card"><div class="ninecode-workspace-main"><h3>' . esc_html( $view['name'] ?? $key ) . '</h3><p>' . esc_html( $context ) . ' · ' . esc_html( $group_label ) . '</p><small>' . esc_html( count( $fields_view ) ) . ' selected data field' . ( 1 === count( $fields_view ) ? '' : 's' ) . '</small></div><div class="ninecode-workspace-actions"><a class="button button-primary" href="' . esc_url( $url ) . '">Edit Data</a><a class="button" href="' . esc_url( $ai_url ) . '">AI</a><a class="button" href="' . esc_url( $xlsx_url ) . '">Excel</a><a class="button" href="' . esc_url( $csv_url ) . '">CSV</a><a class="ninecode-view-delete" href="' . esc_url( $delete ) . '" aria-label="Delete saved workspace">×</a></div></article>';
            }
            echo '</div></div>';
        }

        $kind = isset( $_GET['kind'] ) && 'term' === sanitize_key( wp_unslash( $_GET['kind'] ) ) ? 'term' : 'post';
        $post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
        $taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : 'category';
        $available = $this->get_workspace_fields( $kind, $post_type, $taxonomy );
        $available_keys = array_keys( $available );
        $selected = isset( $_GET['fields'] ) ? array_values( array_filter( array_map( 'sanitize_key', (array) wp_unslash( $_GET['fields'] ) ) ) ) : array_slice( $available_keys, 0, 4 );
        $selected = array_values( array_intersect( $selected, $available_keys ) );
        if ( ! $selected && $available_keys ) { $selected = array_slice( $available_keys, 0, 4 ); }

        $filter_taxonomy = isset( $_GET['filter_taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['filter_taxonomy'] ) ) : '';
        $filter_term = isset( $_GET['filter_term'] ) ? absint( $_GET['filter_term'] ) : 0;
        $group_taxonomies = array();
        if ( 'post' === $kind ) {
            foreach ( (array) get_object_taxonomies( $post_type, 'objects' ) as $group_tax ) {
                if ( ! empty( $group_tax->show_ui ) ) { $group_taxonomies[ $group_tax->name ] = $group_tax; }
            }
            if ( $filter_taxonomy && ! isset( $group_taxonomies[ $filter_taxonomy ] ) ) { $filter_taxonomy = ''; $filter_term = 0; }
        }

        echo '<div class="ninecode-card"><form method="get" class="ninecode-table-setup">';
        echo '<input type="hidden" name="page" value="ninecode-acf-table">';
        echo '<div class="ninecode-form-grid">';
        echo '<label>Data source<select name="kind" data-ninecode-auto-submit><option value="post"' . selected( $kind, 'post', false ) . '>Records</option><option value="term"' . selected( $kind, 'term', false ) . '>Categories, tags and taxonomy items</option></select></label>';
        if ( 'term' === $kind ) {
            echo '<label>Taxonomy<select name="taxonomy" data-ninecode-auto-submit>';
            foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $tax ) {
                echo '<option value="' . esc_attr( $tax->name ) . '"' . selected( $taxonomy, $tax->name, false ) . '>' . esc_html( $tax->labels->singular_name ) . '</option>';
            }
            echo '</select></label>';
        } else {
            echo '<label>Data type<select name="post_type" data-ninecode-auto-submit>';
            foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {
                if ( 'attachment' === $type->name ) { continue; }
                echo '<option value="' . esc_attr( $type->name ) . '"' . selected( $post_type, $type->name, false ) . '>' . esc_html( $type->labels->singular_name ) . '</option>';
            }
            echo '</select></label>';
        }
        echo '</div>';
        if ( 'post' === $kind && $group_taxonomies ) {
            echo '<div class="ninecode-category-filter"><div><strong>Work with a category or group</strong><span>Optional. Choose a taxonomy and one item to see only matching records.</span></div><div class="ninecode-form-grid">';
            echo '<label>Group by<select name="filter_taxonomy" data-ninecode-auto-submit><option value="">All records</option>';
            foreach ( $group_taxonomies as $tax ) { echo '<option value="' . esc_attr( $tax->name ) . '"' . selected( $filter_taxonomy, $tax->name, false ) . '>' . esc_html( $tax->labels->singular_name ) . '</option>'; }
            echo '</select></label>';
            echo '<label>Category / item<select name="filter_term"' . ( $filter_taxonomy ? '' : ' disabled' ) . '><option value="0">All items</option>';
            if ( $filter_taxonomy ) {
                $filter_terms = get_terms( array( 'taxonomy' => $filter_taxonomy, 'hide_empty' => false ) );
                if ( ! is_wp_error( $filter_terms ) ) { foreach ( $filter_terms as $term ) { echo '<option value="' . esc_attr( $term->term_id ) . '"' . selected( $filter_term, $term->term_id, false ) . '>' . esc_html( $term->name ) . '</option>'; } }
            }
            echo '</select></label><button class="button">Show Group</button></div></div>';
        }

        echo '<div class="ninecode-field-picker"><div class="ninecode-section-head"><div><h2>Choose data fields</h2><p>Choose ACF fields and plugin fields. Only the fields you tick will appear in this view.</p></div><span class="ninecode-badge">' . esc_html( count( $available ) ) . ' available</span></div>';
        if ( ! $available ) {
            echo '<div class="ninecode-empty">No editable data fields were found for this data type.</div>';
        } else {
            echo '<div class="ninecode-check-grid ninecode-field-choices">';
            foreach ( $available as $key => $field ) {
                echo '<label><input type="checkbox" name="fields[]" value="' . esc_attr( $key ) . '"' . checked( in_array( $key, $selected, true ), true, false ) . '> <strong>' . esc_html( $field['label'] ?: $field['name'] ) . '</strong><small>' . esc_html( $this->friendly_field_type( $field['type'] ?? '' ) ) . '</small></label>';
            }
            echo '</div><div class="ninecode-actions"><button class="button button-primary">Show Data</button></div>';
        }
        echo '</div></form>';

        if ( $selected ) {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ninecode-save-view">';
            wp_nonce_field( 'ninecode_save_data_view' );
            echo '<input type="hidden" name="action" value="ninecode_save_data_view"><input type="hidden" name="kind" value="' . esc_attr( $kind ) . '"><input type="hidden" name="post_type" value="' . esc_attr( $post_type ) . '"><input type="hidden" name="taxonomy" value="' . esc_attr( $taxonomy ) . '"><input type="hidden" name="filter_taxonomy" value="' . esc_attr( $filter_taxonomy ) . '"><input type="hidden" name="filter_term" value="' . esc_attr( $filter_term ) . '">';
            foreach ( $selected as $key ) { echo '<input type="hidden" name="fields[]" value="' . esc_attr( $key ) . '">'; }
            echo '<label>Save this data workspace<input type="text" name="view_name" placeholder="Example: ENT 212 Lectures" required></label><button class="button">Save Workspace</button></form>';
        }
        echo '</div>';

        if ( ! $selected ) {
            $this->page_footer();
            return;
        }

        $paged = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
        $return_url = $this->data_table_url( array( 'kind' => $kind, 'post_type' => $post_type, 'taxonomy' => $taxonomy, 'fields' => $selected, 'filter_taxonomy' => $filter_taxonomy, 'filter_term' => $filter_term, 'paged' => $paged, 'ninecode_saved' => 1 ) );
        $records = array();
        $total_pages = 1;
        if ( 'term' === $kind ) {
            $per_page = 10;
            $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => $per_page, 'offset' => ( $paged - 1 ) * $per_page ) );
            $records = is_wp_error( $terms ) ? array() : $terms;
            $count = wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
            $total_pages = max( 1, (int) ceil( intval( $count ) / $per_page ) );
        } else {
            $query_args = array( 'post_type' => $post_type, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'posts_per_page' => 10, 'paged' => $paged, 'orderby' => 'modified', 'order' => 'DESC' );
            if ( $filter_taxonomy && $filter_term ) {
                $query_args['tax_query'] = array( array( 'taxonomy' => $filter_taxonomy, 'field' => 'term_id', 'terms' => array( $filter_term ) ) );
            }
            $query = new WP_Query( $query_args );
            $records = $query->posts;
            $total_pages = max( 1, (int) $query->max_num_pages );
            $matching_count = intval( $query->found_posts );
        }

        if ( 'post' === $kind ) {
            $matching_count = isset( $matching_count ) ? $matching_count : count( $records );
            $group_name = 'All ' . ( $post_type ?: 'records' );
            if ( $filter_taxonomy && $filter_term ) {
                $group_term = get_term( $filter_term, $filter_taxonomy );
                if ( $group_term && ! is_wp_error( $group_term ) ) { $group_name = $group_term->name; }
            }
            echo '<section class="ninecode-card ninecode-category-bulk"><div class="ninecode-section-head"><div><span class="ninecode-kicker">BULK EDIT BY GROUP</span><h2>Bulk change this group</h2><p>Apply one data change to every matching record. A recovery version is created first for each affected record.</p></div><span class="ninecode-badge">' . esc_html( $matching_count ) . ' matching</span></div>';
            if ( $matching_count > 1000 ) {
                echo '<div class="notice notice-warning inline"><p>This group has more than 1,000 records. Narrow the group before using one-click bulk change.</p></div>';
            } elseif ( $matching_count > 0 ) {
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ninecode-category-bulk-form" data-ninecode-confirm="bulk-group">';
                wp_nonce_field( 'ninecode_bulk_category_update' );
                echo '<input type="hidden" name="action" value="ninecode_bulk_category_update"><input type="hidden" name="post_type" value="' . esc_attr( $post_type ) . '"><input type="hidden" name="filter_taxonomy" value="' . esc_attr( $filter_taxonomy ) . '"><input type="hidden" name="filter_term" value="' . esc_attr( $filter_term ) . '"><input type="hidden" name="return_to" value="' . esc_attr( $return_url ) . '">';
                foreach ( $selected as $key ) { echo '<input type="hidden" name="fields[]" value="' . esc_attr( $key ) . '">'; }
                echo '<label>Data to change<select name="bulk_target" required><option value="">Choose a field</option>';
                foreach ( $available as $key => $field ) { echo '<option value="field:' . esc_attr( $key ) . '">' . esc_html( $field['label'] ?: $field['name'] ) . ' · ' . esc_html( 'meta' === ( $field['source'] ?? '' ) ? 'Plugin field' : 'ACF' ) . '</option>'; }
                foreach ( $group_taxonomies as $tax ) { echo '<option value="tax:' . esc_attr( $tax->name ) . '">' . esc_html( $tax->labels->singular_name ) . ' · Taxonomy</option>'; }
                echo '</select></label>';
                echo '<label>Change<select name="bulk_operation"><option value="set">Set / replace value</option><option value="clear">Clear value</option><option value="add_terms">Add taxonomy item(s)</option><option value="remove_terms">Remove taxonomy item(s)</option></select></label>';
                echo '<label class="ninecode-span-2">New value<textarea name="bulk_value" rows="3" placeholder="Enter the value. For taxonomy changes, separate items with commas. JSON is accepted for structured plugin fields."></textarea></label>';
                echo '<label class="ninecode-check"><input type="checkbox" name="create_missing_terms" value="1"> Create missing taxonomy items when needed</label>';
                echo '<label class="ninecode-check"><input type="checkbox" name="confirm_bulk" value="1" required> I checked this group and want to change ' . esc_html( $matching_count ) . ' matching record' . ( 1 === $matching_count ? '' : 's' ) . '.</label>';
                echo '<div class="ninecode-bulk-summary ninecode-span-2"><strong>' . esc_html( $group_name ) . '</strong><span>' . esc_html( $matching_count ) . ' records will be validated before anything is written.</span></div><button class="button button-primary button-hero ninecode-span-2">Apply Bulk Data Change</button></form>';
            } else { echo '<div class="ninecode-empty">No matching records in this group.</div>'; }
            echo '</section>';
        }

        echo '<div class="ninecode-bulk-head"><strong>' . esc_html( count( $records ) ) . ' records on this page</strong><span>Edit a row, then press Save Data inside that row.</span></div>';
        echo '<div class="ninecode-bulk-list">';
        foreach ( $records as $record ) {
            $acf_id = 'term' === $kind ? 'term_' . $record->term_id : $record->ID;
            $id = 'term' === $kind ? $record->term_id : $record->ID;
            $label = 'term' === $kind ? $record->name : ( get_the_title( $record ) ?: 'Untitled record' );
            echo '<details class="ninecode-bulk-row"><summary><div class="ninecode-bulk-title"><strong>' . esc_html( $label ) . '</strong><span>Open to edit</span></div><div class="ninecode-bulk-preview">';
            foreach ( $selected as $field_key ) {
                if ( empty( $available[ $field_key ] ) ) { continue; }
                $field = $available[ $field_key ];
                if ( 'meta' === ( $field['source'] ?? 'acf' ) && 'post' === $kind ) {
                    $meta_key = (string) ( $field['meta_key'] ?? '' );
                    if ( 'multi' === ( $field['storage'] ?? '' ) ) {
                        $value = array_map( 'maybe_unserialize', (array) get_post_meta( $id, $meta_key, false ) );
                    } else {
                        $value = maybe_unserialize( get_post_meta( $id, $meta_key, true ) );
                    }
                } else {
                    $value = function_exists( 'get_field' ) ? get_field( $field_key, $acf_id ) : '';
                }
                $text = self::generic_value_to_text( $value );
                if ( '' === trim( $text ) ) { $text = '—'; }
                if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > 80 ) { $text = mb_substr( $text, 0, 77 ) . '…'; }
                elseif ( strlen( $text ) > 80 ) { $text = substr( $text, 0, 77 ) . '…'; }
                echo '<span><small>' . esc_html( $field['label'] ?: $field['name'] ) . '</small>' . esc_html( $text ) . '</span>';
            }
            echo '</div></summary><div class="ninecode-bulk-edit">';

            if ( 'term' === $kind ) {
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ninecode-bulk-tax-core">';
                wp_nonce_field( 'ninecode_save_term_data_' . $record->term_id );
                echo '<input type="hidden" name="action" value="ninecode_save_term_data"><input type="hidden" name="id" value="' . esc_attr( $record->term_id ) . '"><input type="hidden" name="taxonomy" value="' . esc_attr( $record->taxonomy ) . '"><input type="hidden" name="return_to" value="' . esc_attr( $return_url ) . '">';
                echo '<label>Name<input type="text" name="name" value="' . esc_attr( $record->name ) . '" required></label><label>Description<textarea name="description" rows="3">' . esc_textarea( $record->description ) . '</textarea></label><button class="button">Save Taxonomy Data</button></form>';
            }

            $selected_acf = array();
            $selected_meta = array();
            foreach ( $selected as $selected_key ) {
                if ( empty( $available[ $selected_key ] ) ) { continue; }
                if ( 'meta' === ( $available[ $selected_key ]['source'] ?? 'acf' ) ) { $selected_meta[] = $selected_key; }
                else { $selected_acf[] = $selected_key; }
            }
            if ( $selected_acf && function_exists( 'acf_form' ) ) {
                acf_form( array(
                    'post_id' => $acf_id,
                    'form_attributes' => array( 'id' => 'ninecode-row-' . $kind . '-' . $id, 'class' => 'acf-form ninecode-row-form', 'autocomplete' => 'off' ),
                    'fields' => $selected_acf,
                    'form' => true,
                    'return' => $return_url,
                    'submit_value' => 'Save ACF Data',
                    'updated_message' => 'Data saved.',
                    'html_before_fields' => '<input type="hidden" name="ninecode_data_engine_save" value="1">',
                    'html_submit_button' => '<button type="submit" class="button button-primary">%s</button>',
                ) );
            }
            if ( 'post' === $kind && $selected_meta ) {
                $this->render_bulk_plugin_meta_form( $record, $selected_meta, $available, $return_url );
            }
            if ( 'post' === $kind ) {
                $this->render_bulk_taxonomy_form( $record, $return_url );
            }
            echo '</div></details>';
        }
        if ( ! $records ) { echo '<div class="ninecode-empty">No records found.</div>'; }
        echo '</div>';
        echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%', $this->data_table_url( array( 'kind' => $kind, 'post_type' => $post_type, 'taxonomy' => $taxonomy, 'fields' => $selected, 'filter_taxonomy' => $filter_taxonomy, 'filter_term' => $filter_term ) ) ), 'format' => '', 'total' => $total_pages, 'current' => $paged ) ) ) . '</div></div>';
        $this->page_footer();
    }

    private function get_workspace_fields( $kind, $post_type, $taxonomy ) {
        $fields = array();

        if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
            $groups = array();
            if ( 'term' === $kind ) {
                $groups = (array) acf_get_field_groups( array( 'taxonomy' => $taxonomy ) );
                if ( ! $groups ) {
                    $sample = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => 1 ) );
                    if ( ! is_wp_error( $sample ) && ! empty( $sample[0] ) ) { $groups = (array) acf_get_field_groups( array( 'post_id' => 'term_' . $sample[0]->term_id ) ); }
                }
            } else {
                $groups = (array) acf_get_field_groups( array( 'post_type' => $post_type ) );
                if ( ! $groups ) {
                    $ids = get_posts( array( 'post_type' => $post_type, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ) );
                    if ( $ids ) { $groups = (array) acf_get_field_groups( array( 'post_id' => $ids[0] ) ); }
                }
            }

            $by_key = array();
            foreach ( $groups as $group ) { if ( ! empty( $group['key'] ) ) { $by_key[ $group['key'] ] = $group; } }
            $alloc = (array) get_option( 'ninecode_acf_allocations', array() );
            foreach ( (array) acf_get_field_groups() as $group ) {
                $key = $group['key'] ?? '';
                if ( ! $key ) { continue; }
                $direct_match = false;
                foreach ( (array) ( $group['location'] ?? array() ) as $or_rules ) {
                    foreach ( (array) $or_rules as $rule ) {
                        if ( '==' !== ( $rule['operator'] ?? '==' ) ) { continue; }
                        if ( 'post' === $kind && 'post_type' === ( $rule['param'] ?? '' ) && $post_type === ( $rule['value'] ?? '' ) ) { $direct_match = true; }
                        if ( 'term' === $kind && 'taxonomy' === ( $rule['param'] ?? '' ) && $taxonomy === ( $rule['value'] ?? '' ) ) { $direct_match = true; }
                    }
                }
                $virtual_match = ! empty( $alloc[ $key ] ) && ( 'term' === $kind ? in_array( $taxonomy, (array) ( $alloc[ $key ]['taxonomies'] ?? array() ), true ) : in_array( $post_type, (array) ( $alloc[ $key ]['post_types'] ?? array() ), true ) );
                if ( $direct_match || $virtual_match ) { $by_key[ $key ] = $group; }
            }
            foreach ( $by_key as $group ) {
                foreach ( (array) acf_get_fields( $group ) as $field ) {
                    if ( ! empty( $field['key'] ) ) { $field['source'] = 'acf'; $fields[ $field['key'] ] = $field; }
                }
            }
        }

        if ( 'post' === $kind && post_type_exists( $post_type ) ) {
            $exporter = new NCU_Data_Data_Exporter();
            $sample_ids = get_posts( array(
                'post_type' => $post_type,
                'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
                'posts_per_page' => 50,
                'fields' => 'ids',
                'orderby' => 'modified',
                'order' => 'DESC',
            ) );

            // Do not rely only on recent records: older plugin data may contain valid meta keys
            // that still need to be editable and exportable. Add one representative record
            // for every meta key used by this post type, then let the exporter apply the same
            // ACF/system-meta safety rules used by normal exports.
            global $wpdb;
            if ( isset( $wpdb->postmeta, $wpdb->posts ) ) {
                $representatives = $wpdb->get_col( $wpdb->prepare(
                    "SELECT MIN(pm.post_id)
                     FROM {$wpdb->postmeta} pm
                     INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                     WHERE p.post_type = %s
                       AND p.post_status IN ('publish','draft','pending','private','future')
                     GROUP BY pm.meta_key",
                    $post_type
                ) );
                $sample_ids = array_merge( (array) $sample_ids, (array) $representatives );
            }
            $sample_ids = array_values( array_unique( array_map( 'absint', (array) $sample_ids ) ) );

            foreach ( $sample_ids as $sample_id ) {
                foreach ( $exporter->export_meta_for_post( $sample_id ) as $meta ) {
                    $meta_key = (string) ( $meta['key'] ?? '' );
                    if ( ! $meta_key ) { continue; }
                    $token = 'meta_' . substr( md5( $meta_key ), 0, 16 );
                    if ( isset( $fields[ $token ] ) ) { continue; }
                    $fields[ $token ] = array(
                        'key' => $token,
                        'name' => $meta_key,
                        'label' => $meta['label'] ?? $meta_key,
                        'type' => 'plugin_meta',
                        'source' => 'meta',
                        'meta_key' => $meta_key,
                        'storage' => 'multi' === ( $meta['storage'] ?? '' ) ? 'multi' : 'single',
                        'instructions' => $meta['instructions'] ?? '',
                    );
                }
            }
        }
        return $fields;
    }

    private function friendly_field_type( $type ) {
        $map = array(
            'text' => 'Short text', 'textarea' => 'Paragraph / long text', 'wysiwyg' => 'Rich text / paragraphs', 'number' => 'Number', 'range' => 'Number slider',
            'email' => 'Email', 'url' => 'Link', 'password' => 'Protected text', 'image' => 'Image', 'file' => 'File', 'gallery' => 'Gallery',
            'select' => 'Choice', 'checkbox' => 'Multiple choices', 'radio' => 'Choice', 'button_group' => 'Choice', 'true_false' => 'Yes / No',
            'date_picker' => 'Date', 'date_time_picker' => 'Date and time', 'time_picker' => 'Time', 'color_picker' => 'Colour',
            'post_object' => 'Related record', 'relationship' => 'Related records', 'taxonomy' => 'Taxonomy', 'user' => 'User',
            'repeater' => 'Repeating data', 'group' => 'Grouped data', 'flexible_content' => 'Flexible content', 'clone' => 'Reusable fields',
            'plugin_meta' => 'Plugin field',
        );
        return $map[ $type ] ?? ucwords( str_replace( '_', ' ', (string) $type ) );
    }

    private function render_bulk_plugin_meta_form( $post, $selected_meta, $available, $return_url ) {
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ninecode-bulk-plugin-meta">';
        wp_nonce_field( 'ninecode_save_plugin_meta_' . $post->ID );
        echo '<input type="hidden" name="action" value="ninecode_save_plugin_meta"><input type="hidden" name="id" value="' . esc_attr( $post->ID ) . '"><input type="hidden" name="return_to" value="' . esc_attr( $return_url ) . '"><input type="hidden" name="workspace_meta" value="1"><h3>Plugin fields</h3>';
        foreach ( $selected_meta as $token ) {
            $field = $available[ $token ] ?? array();
            $key = (string) ( $field['meta_key'] ?? '' );
            if ( ! $key ) { continue; }
            $storage = 'multi' === ( $field['storage'] ?? '' ) ? 'multi' : 'single';
            $value = 'multi' === $storage ? array_map( 'maybe_unserialize', (array) get_post_meta( $post->ID, $key, false ) ) : maybe_unserialize( get_post_meta( $post->ID, $key, true ) );
            $structured = 'multi' === $storage || is_array( $value ) || is_object( $value );
            $encoding = $structured ? 'json' : 'plain';
            $display = $structured ? wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : ( is_bool( $value ) ? ( $value ? 'true' : 'false' ) : (string) $value );
            echo '<label><strong>' . esc_html( $field['label'] ?? $key ) . '</strong><small>' . esc_html( $key ) . '</small>';
            echo '<input type="hidden" name="meta_keys[]" value="' . esc_attr( $key ) . '"><input type="hidden" name="meta_storage[]" value="' . esc_attr( $storage ) . '"><input type="hidden" name="meta_encoding[]" value="' . esc_attr( $encoding ) . '">';
            if ( $structured || false !== strpos( $display, "\n" ) || strlen( $display ) > 120 ) { echo '<textarea name="meta_values[]" rows="4">' . esc_textarea( $display ) . '</textarea>'; }
            else { echo '<input type="text" name="meta_values[]" value="' . esc_attr( $display ) . '">'; }
            echo '</label>';
        }
        echo '<button class="button button-primary">Save Plugin Data</button></form>';
    }

    private function render_bulk_taxonomy_form( $post, $return_url ) {
        $taxonomies = get_object_taxonomies( $post->post_type, 'objects' );
        $visible = array_filter( $taxonomies, function( $tax ) { return ! empty( $tax->show_ui ); } );
        if ( ! $visible ) { return; }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ninecode-bulk-taxonomies">';
        wp_nonce_field( 'ninecode_save_terms_' . $post->ID );
        echo '<input type="hidden" name="action" value="ninecode_save_terms"><input type="hidden" name="id" value="' . esc_attr( $post->ID ) . '"><input type="hidden" name="return_to" value="' . esc_attr( $return_url ) . '"><h3>Taxonomies</h3>';
        foreach ( $visible as $tax ) {
            $assigned = wp_get_object_terms( $post->ID, $tax->name, array( 'fields' => 'names' ) );
            $assigned = is_wp_error( $assigned ) ? array() : $assigned;
            echo '<label>' . esc_html( $tax->labels->name ) . '<input type="text" name="terms[' . esc_attr( $tax->name ) . ']" value="' . esc_attr( implode( ', ', $assigned ) ) . '" placeholder="Names separated by commas"></label>';
        }
        echo '<button class="button">Save Taxonomies</button></form>';
    }

    private function data_table_url( $args = array() ) {
        $base = array( 'page' => 'ninecode-acf-table' );
        return add_query_arg( array_merge( $base, $args ), admin_url( 'admin.php' ) );
    }

    private function get_saved_data_views() {
        $views = get_user_meta( get_current_user_id(), 'ninecode_acf_saved_views', true );
        return is_array( $views ) ? $views : array();
    }

    public function handle_save_data_view() {
        $this->require_cap();
        check_admin_referer( 'ninecode_save_data_view' );
        $name = isset( $_POST['view_name'] ) ? sanitize_text_field( wp_unslash( $_POST['view_name'] ) ) : '';
        if ( ! $name ) { wp_die( 'Give the view a name.' ); }
        $views = $this->get_saved_data_views();
        $key = sanitize_key( sanitize_title( $name ) );
        if ( ! $key ) { $key = 'view_' . wp_generate_password( 6, false, false ); }
        $views[ $key ] = array(
            'name' => $name,
            'kind' => isset( $_POST['kind'] ) && 'term' === sanitize_key( wp_unslash( $_POST['kind'] ) ) ? 'term' : 'post',
            'post_type' => isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : 'post',
            'taxonomy' => isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : 'category',
            'filter_taxonomy' => isset( $_POST['filter_taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['filter_taxonomy'] ) ) : '',
            'filter_term' => isset( $_POST['filter_term'] ) ? absint( $_POST['filter_term'] ) : 0,
            'fields' => array_values( array_filter( array_map( 'sanitize_key', (array) ( isset( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : array() ) ) ) ),
        );
        update_user_meta( get_current_user_id(), 'ninecode_acf_saved_views', $views );
        wp_safe_redirect( $this->data_table_url( array( 'kind' => $views[ $key ]['kind'], 'post_type' => $views[ $key ]['post_type'], 'taxonomy' => $views[ $key ]['taxonomy'], 'fields' => $views[ $key ]['fields'], 'filter_taxonomy' => $views[ $key ]['filter_taxonomy'], 'filter_term' => $views[ $key ]['filter_term'] ) ) );
        exit;
    }

    public function handle_delete_data_view() {
        $this->require_cap();
        $key = isset( $_GET['view_key'] ) ? sanitize_key( wp_unslash( $_GET['view_key'] ) ) : '';
        check_admin_referer( 'ninecode_delete_data_view_' . $key );
        $views = $this->get_saved_data_views();
        unset( $views[ $key ] );
        update_user_meta( get_current_user_id(), 'ninecode_acf_saved_views', $views );
        wp_safe_redirect( $this->data_table_url() );
        exit;
    }

    public function render_fields() {
        $this->require_cap();
        $this->page_header( 'Data Dictionary', 'See every data field available to a post type, where it comes from, and how much of your data is populated.' );

        $post_types = get_post_types( array( 'show_ui' => true ), 'objects' );
        unset( $post_types['attachment'] );
        $post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
        if ( ! isset( $post_types[ $post_type ] ) ) {
            $keys = array_keys( $post_types );
            $post_type = $keys ? reset( $keys ) : 'post';
        }
        $field_q = isset( $_GET['field_q'] ) ? sanitize_text_field( wp_unslash( $_GET['field_q'] ) ) : '';

        echo '<div class="ninecode-card"><form method="get" class="ninecode-filter ninecode-dictionary-filter">';
        echo '<input type="hidden" name="page" value="ninecode-acf-fields">';
        echo '<label>Data type<select name="post_type" data-ninecode-auto-submit>';
        foreach ( $post_types as $type ) {
            echo '<option value="' . esc_attr( $type->name ) . '"' . selected( $post_type, $type->name, false ) . '>' . esc_html( $type->labels->singular_name ) . '</option>';
        }
        echo '</select></label>';
        echo '<label>Find a field<input type="search" name="field_q" value="' . esc_attr( $field_q ) . '" placeholder="Bio, price, WhatsApp, image..."></label>';
        echo '<button class="button button-primary">Show Fields</button></form></div>';

        $fields = $this->get_workspace_fields( 'post', $post_type, '' );
        $acf_count = 0;
        $plugin_count = 0;
        $storage_keys = array();
        foreach ( $fields as $field_key => $field ) {
            if ( 'meta' === ( $field['source'] ?? '' ) ) { $plugin_count++; $storage_keys[] = (string) ( $field['meta_key'] ?? '' ); }
            else { $acf_count++; $storage_keys[] = (string) ( $field['name'] ?? '' ); }
        }
        $storage_keys = array_values( array_filter( array_unique( $storage_keys ) ) );
        $usage = $this->get_field_usage_counts( $post_type, $storage_keys );
        $total_records = $this->get_post_type_data_record_count( $post_type );
        $taxonomies = array_filter( (array) get_object_taxonomies( $post_type, 'objects' ), function( $tax ) { return ! empty( $tax->show_ui ); } );

        echo '<div class="ninecode-dictionary-stats">';
        echo '<div class="ninecode-stat"><strong>' . esc_html( count( $fields ) ) . '</strong><span>Data fields</span></div>';
        echo '<div class="ninecode-stat"><strong>' . esc_html( $acf_count ) . '</strong><span>ACF fields</span></div>';
        echo '<div class="ninecode-stat"><strong>' . esc_html( $plugin_count ) . '</strong><span>Plugin fields</span></div>';
        echo '<div class="ninecode-stat"><strong>' . esc_html( count( $taxonomies ) ) . '</strong><span>Taxonomies</span></div>';
        echo '</div>';

        $edit_many_all = $this->data_table_url( array( 'kind' => 'post', 'post_type' => $post_type ) );
        $schema_url = wp_nonce_url( add_query_arg( array( 'action' => 'ninecode_acf_export_schema' ), admin_url( 'admin-post.php' ) ), 'ninecode_export_schema' );
        echo '<div class="ninecode-actions"><a class="button button-primary" href="' . esc_url( $edit_many_all ) . '">Open Edit Many</a><a class="button" href="' . esc_url( $schema_url ) . '">Export Complete Schema JSON</a><span class="ninecode-count">' . esc_html( $total_records ) . ' records</span></div>';

        echo '<section class="ninecode-card"><div class="ninecode-section-head"><div><span class="ninecode-kicker">FIELD INVENTORY</span><h2>' . esc_html( $post_types[ $post_type ]->labels->singular_name ?? $post_type ) . ' data fields</h2><p>Coverage shows how many records already contain a value. Empty fields remain editable in Edit Many.</p></div></div>';
        echo '<div class="ninecode-dictionary-list">';
        $shown = 0;
        foreach ( $fields as $field_key => $field ) {
            $source = 'meta' === ( $field['source'] ?? '' ) ? 'Plugin field' : 'ACF';
            $storage_key = 'meta' === ( $field['source'] ?? '' ) ? (string) ( $field['meta_key'] ?? '' ) : (string) ( $field['name'] ?? '' );
            $label = (string) ( $field['label'] ?? $field['name'] ?? $storage_key );
            $haystack = strtolower( $label . ' ' . $storage_key . ' ' . ( $field['type'] ?? '' ) . ' ' . $source );
            if ( $field_q && false === strpos( $haystack, strtolower( $field_q ) ) ) { continue; }
            $populated = intval( $usage[ $storage_key ] ?? 0 );
            $percent = $total_records ? min( 100, round( ( $populated / $total_records ) * 100 ) ) : 0;
            $edit_many = $this->data_table_url( array( 'kind' => 'post', 'post_type' => $post_type, 'fields' => array( $field_key ) ) );
            echo '<article class="ninecode-dictionary-row">';
            echo '<div class="ninecode-dictionary-main"><strong>' . esc_html( $label ?: $storage_key ) . '</strong><span>' . esc_html( $source ) . ' · ' . esc_html( $this->friendly_field_type( $field['type'] ?? '' ) ) . '</span><code>' . esc_html( $storage_key ) . '</code></div>';
            echo '<div class="ninecode-dictionary-coverage"><span>Coverage</span><strong>' . esc_html( $populated ) . ' / ' . esc_html( $total_records ) . '</strong><div class="ninecode-coverage-bar"><i style="width:' . esc_attr( $percent ) . '%"></i></div><small>' . esc_html( $percent ) . '% populated</small></div>';
            echo '<div class="ninecode-dictionary-action"><a class="button" href="' . esc_url( $edit_many ) . '">Edit Many</a><a class="button" href="' . esc_url( add_query_arg( 'group_mode', 1, $edit_many ) ) . '">Edit by Category</a></div>';
            echo '</article>';
            $shown++;
        }
        if ( ! $shown ) { echo '<div class="ninecode-empty">No matching data fields found.</div>'; }
        echo '</div></section>';

        echo '<details class="ninecode-card ninecode-details"><summary><strong>Taxonomies</strong><span>' . esc_html( count( $taxonomies ) ) . ' available</span></summary><div class="ninecode-details-body"><div class="ninecode-dictionary-list">';
        foreach ( $taxonomies as $tax ) {
            $term_count = wp_count_terms( array( 'taxonomy' => $tax->name, 'hide_empty' => false ) );
            if ( is_wp_error( $term_count ) ) { $term_count = 0; }
            echo '<article class="ninecode-dictionary-row"><div class="ninecode-dictionary-main"><strong>' . esc_html( $tax->labels->name ) . '</strong><span>Taxonomy</span><code>' . esc_html( $tax->name ) . '</code></div><div class="ninecode-dictionary-coverage"><span>Terms</span><strong>' . esc_html( intval( $term_count ) ) . '</strong></div></article>';
        }
        if ( ! $taxonomies ) { echo '<div class="ninecode-empty">No editable taxonomies are attached to this post type.</div>'; }
        echo '</div></div></details>';

        if ( function_exists( 'acf_get_field_groups' ) ) {
            $groups = acf_get_field_groups();
            echo '<details class="ninecode-card ninecode-details"><summary><strong>Technical ACF schema</strong><span>' . esc_html( count( $groups ) ) . ' groups · closed by default</span></summary><div class="ninecode-details-body">';
            foreach ( $groups as $group ) {
                $group_fields = function_exists( 'acf_get_fields' ) ? (array) acf_get_fields( $group ) : array();
                echo '<details class="ninecode-field-group"><summary><div><strong>' . esc_html( $group['title'] ?? $group['key'] ) . '</strong><span><code>' . esc_html( $group['key'] ?? '' ) . '</code> · ' . esc_html( count( $group_fields ) ) . ' fields</span></div><span>Open</span></summary><div class="ninecode-details-body">';
                $this->render_field_tree( $group_fields );
                echo '</div></details>';
            }
            echo '</div></details>';
        }
        $this->page_footer();
    }

    private function get_post_type_data_record_count( $post_type ) {
        $counts = wp_count_posts( $post_type );
        if ( ! $counts ) { return 0; }
        $total = 0;
        foreach ( array( 'publish', 'draft', 'pending', 'private', 'future' ) as $status ) {
            $total += intval( $counts->{$status} ?? 0 );
        }
        return $total;
    }

    private function get_field_usage_counts( $post_type, $meta_keys ) {
        global $wpdb;
        $meta_keys = array_values( array_filter( array_unique( array_map( 'strval', (array) $meta_keys ) ) ) );
        if ( ! $meta_keys || ! isset( $wpdb->postmeta, $wpdb->posts ) ) { return array(); }
        $placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
        $sql = "SELECT pm.meta_key, COUNT(DISTINCT pm.post_id) AS used_count
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE p.post_type = %s
                  AND p.post_status IN ('publish','draft','pending','private','future')
                  AND pm.meta_key IN ($placeholders)
                  AND pm.meta_value <> ''
                GROUP BY pm.meta_key";
        $args = array_merge( array( $post_type ), $meta_keys );
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
        $out = array();
        foreach ( (array) $rows as $row ) { $out[ (string) $row['meta_key'] ] = intval( $row['used_count'] ); }
        return $out;
    }

    private function render_field_tree( $fields, $depth = 0 ) {
        if ( ! $fields ) { echo '<p class="description">No fields.</p>'; return; }
        echo '<div class="ninecode-field-tree depth-' . esc_attr( $depth ) . '">';
        foreach ( $fields as $field ) {
            echo '<div class="ninecode-field-row"><div><strong>' . esc_html( $field['label'] ?? $field['name'] ?? 'Field' ) . '</strong><span><code>' . esc_html( $field['name'] ?? '' ) . '</code> · <code>' . esc_html( $field['key'] ?? '' ) . '</code></span></div><span class="ninecode-badge">' . esc_html( $field['type'] ?? '' ) . '</span></div>';
            if ( ! empty( $field['sub_fields'] ) ) {
                $this->render_field_tree( $field['sub_fields'], $depth + 1 );
            }
            if ( ! empty( $field['layouts'] ) ) {
                foreach ( $field['layouts'] as $layout ) {
                    echo '<div class="ninecode-layout-label">Layout: ' . esc_html( $layout['label'] ?? $layout['name'] ?? 'layout' ) . '</div>';
                    $this->render_field_tree( $layout['sub_fields'] ?? array(), $depth + 1 );
                }
            }
        }
        echo '</div>';
    }

    public function render_allocator() {
        $this->require_cap();
        $this->page_header( 'Field Allocator', 'Attach ACF field groups to post types or taxonomies without destructively replacing the group’s original ACF location rules.' );
        if ( ! function_exists( 'acf_get_field_groups' ) ) {
            echo '<div class="ninecode-card"><p>Activate ACF to allocate field groups.</p></div>'; $this->page_footer(); return;
        }
        $alloc = (array) get_option( 'ninecode_acf_allocations', array() );
        $post_types = get_post_types( array( 'show_ui' => true ), 'objects' );
        $taxonomies = get_taxonomies( array( 'show_ui' => true ), 'objects' );
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'ninecode_save_allocations' );
        echo '<input type="hidden" name="action" value="ninecode_save_allocations">';
        foreach ( acf_get_field_groups() as $group ) {
            $key = $group['key'];
            $current = $alloc[ $key ] ?? array( 'post_types' => array(), 'taxonomies' => array() );
            echo '<details class="ninecode-card ninecode-details"><summary><strong>' . esc_html( $group['title'] ) . '</strong><span>' . esc_html( count( $current['post_types'] ?? array() ) + count( $current['taxonomies'] ?? array() ) ) . ' 9Code allocations</span></summary><div class="ninecode-details-body"><div class="ninecode-two-col"><div><h3>Post types</h3><div class="ninecode-check-grid">';
            foreach ( $post_types as $type ) {
                if ( 'attachment' === $type->name ) { continue; }
                $checked = in_array( $type->name, (array) ( $current['post_types'] ?? array() ), true );
                echo '<label><input type="checkbox" name="alloc[' . esc_attr( $key ) . '][post_types][]" value="' . esc_attr( $type->name ) . '"' . checked( $checked, true, false ) . '> ' . esc_html( $type->labels->singular_name ) . ' <small>' . esc_html( $type->name ) . '</small></label>';
            }
            echo '</div></div><div><h3>Taxonomies</h3><div class="ninecode-check-grid">';
            foreach ( $taxonomies as $tax ) {
                $checked = in_array( $tax->name, (array) ( $current['taxonomies'] ?? array() ), true );
                echo '<label><input type="checkbox" name="alloc[' . esc_attr( $key ) . '][taxonomies][]" value="' . esc_attr( $tax->name ) . '"' . checked( $checked, true, false ) . '> ' . esc_html( $tax->labels->singular_name ) . ' <small>' . esc_html( $tax->name ) . '</small></label>';
            }
            echo '</div></div></div></div></details>';
        }
        echo '<div class="ninecode-sticky-save"><button class="button button-primary button-hero">Save all allocations</button></div></form>';
        $this->page_footer();
    }

    public function handle_save_allocations() {
        $this->require_cap();
        check_admin_referer( 'ninecode_save_allocations' );
        $raw = isset( $_POST['alloc'] ) && is_array( $_POST['alloc'] ) ? wp_unslash( $_POST['alloc'] ) : array();
        $clean = array();
        foreach ( $raw as $key => $value ) {
            $field_key = sanitize_key( $key );
            if ( 0 !== strpos( $field_key, 'group_' ) ) { continue; }
            $clean[ $field_key ] = array(
                'post_types' => array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) ( $value['post_types'] ?? array() ) ) ) ) ),
                'taxonomies' => array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) ( $value['taxonomies'] ?? array() ) ) ) ) ),
            );
        }
        update_option( 'ninecode_acf_allocations', $clean, false );
        if ( function_exists( 'acf_get_store' ) ) {
            acf_get_store( 'field-groups' )->reset();
        }
        wp_safe_redirect( add_query_arg( array( 'page' => 'ninecode-acf-allocator', 'ninecode_saved' => 1 ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function apply_virtual_allocations( $group ) {
        if ( empty( $group['key'] ) ) { return $group; }
        $alloc = (array) get_option( 'ninecode_acf_allocations', array() );
        if ( empty( $alloc[ $group['key'] ] ) ) { return $group; }
        $location = isset( $group['location'] ) && is_array( $group['location'] ) ? $group['location'] : array();
        $seen = array();
        foreach ( $location as $or_group ) {
            foreach ( (array) $or_group as $rule ) {
                if ( ! empty( $rule['param'] ) && ! empty( $rule['value'] ) ) {
                    $seen[ $rule['param'] . '|' . $rule['value'] ] = true;
                }
            }
        }
        foreach ( (array) ( $alloc[ $group['key'] ]['post_types'] ?? array() ) as $post_type ) {
            if ( empty( $seen[ 'post_type|' . $post_type ] ) ) {
                $location[] = array( array( 'param' => 'post_type', 'operator' => '==', 'value' => $post_type ) );
            }
        }
        foreach ( (array) ( $alloc[ $group['key'] ]['taxonomies'] ?? array() ) as $taxonomy ) {
            if ( empty( $seen[ 'taxonomy|' . $taxonomy ] ) ) {
                $location[] = array( array( 'param' => 'taxonomy', 'operator' => '==', 'value' => $taxonomy ) );
            }
        }
        $group['location'] = $location;
        return $group;
    }

    public function render_taxonomies() {
        $this->require_cap();
        $this->page_header( 'Taxonomies', 'Open categories, tags and custom taxonomy items and edit their data fields.' );
        echo '<div class="ninecode-card"><div class="ninecode-tax-grid">';
        foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $tax ) {
            $count = wp_count_terms( array( 'taxonomy' => $tax->name, 'hide_empty' => false ) );
            $url = add_query_arg( array( 'page' => 'nine-code-ultra-data-engine', 'kind' => 'term', 'taxonomy' => $tax->name ), admin_url( 'admin.php' ) );
            echo '<a class="ninecode-tax-card" href="' . esc_url( $url ) . '"><strong>' . esc_html( $tax->labels->name ) . '</strong><span><code>' . esc_html( $tax->name ) . '</code> · ' . esc_html( intval( $count ) ) . ' terms</span></a>';
        }
        echo '</div></div>';
        $this->page_footer();
    }

    public function render_registry() {
        $this->require_cap();
        $this->page_header( 'Data Registry', 'See every registered post type/taxonomy and optionally create simple 9Code-owned data types without editing PHP.' );
        $registry = (array) get_option( 'ninecode_acf_managed_registry', array( 'post_types' => array(), 'taxonomies' => array() ) );

        echo '<div class="ninecode-two-col">';
        echo '<div class="ninecode-card"><h2>Create 9Code post type</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ninecode-form-grid">';
        wp_nonce_field( 'ninecode_save_registry' );
        echo '<input type="hidden" name="action" value="ninecode_save_registry"><input type="hidden" name="registry_kind" value="post_type">';
        echo '<label>Singular label<input name="label" required placeholder="Course"></label><label>Post type key<input name="key" required maxlength="20" placeholder="nine_course"></label><label>Plural label<input name="plural" placeholder="Courses"></label><label>Supports<input name="supports" value="title,thumbnail" placeholder="title,thumbnail"></label><label class="ninecode-span-2"><input type="checkbox" name="hierarchical" value="1"> Hierarchical data type</label><button class="button button-primary">Save post type</button></form></div>';

        echo '<div class="ninecode-card"><h2>Create 9Code taxonomy</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ninecode-form-grid">';
        wp_nonce_field( 'ninecode_save_registry' );
        echo '<input type="hidden" name="action" value="ninecode_save_registry"><input type="hidden" name="registry_kind" value="taxonomy">';
        echo '<label>Singular label<input name="label" required placeholder="Topic"></label><label>Taxonomy key<input name="key" required maxlength="32" placeholder="nine_topic"></label><label>Plural label<input name="plural" placeholder="Topics"></label><label>Attach to post types<input name="object_types" placeholder="post,nine_course"></label><label class="ninecode-span-2"><input type="checkbox" name="hierarchical" value="1" checked> Hierarchical (category-like)</label><button class="button button-primary">Save taxonomy</button></form></div>';
        echo '</div>';

        echo '<div class="ninecode-card"><h2>Registered post types</h2><div class="ninecode-registry-list">';
        foreach ( get_post_types( array(), 'objects' ) as $type ) {
            if ( in_array( $type->name, array( 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation', 'wp_font_family', 'wp_font_face' ), true ) ) { continue; }
            $owned = isset( $registry['post_types'][ $type->name ] );
            echo '<div class="ninecode-registry-row"><div><strong>' . esc_html( $type->labels->singular_name ) . '</strong><span><code>' . esc_html( $type->name ) . '</code> · ' . ( $owned ? '9Code-owned' : 'registered externally' ) . '</span></div>';
            if ( $owned ) {
                $del = wp_nonce_url( add_query_arg( array( 'action' => 'ninecode_delete_registry', 'registry_kind' => 'post_type', 'key' => $type->name ), admin_url( 'admin-post.php' ) ), 'ninecode_delete_registry_' . $type->name );
                echo '<a class="button button-link-delete" href="' . esc_url( $del ) . '">Remove definition</a>';
            }
            echo '</div>';
        }
        echo '</div></div>';

        echo '<div class="ninecode-card"><h2>Registered taxonomies</h2><div class="ninecode-registry-list">';
        foreach ( get_taxonomies( array(), 'objects' ) as $tax ) {
            $owned = isset( $registry['taxonomies'][ $tax->name ] );
            echo '<div class="ninecode-registry-row"><div><strong>' . esc_html( $tax->labels->singular_name ) . '</strong><span><code>' . esc_html( $tax->name ) . '</code> · attached to ' . esc_html( implode( ', ', $tax->object_type ) ) . ' · ' . ( $owned ? '9Code-owned' : 'registered externally' ) . '</span></div>';
            if ( $owned ) {
                $del = wp_nonce_url( add_query_arg( array( 'action' => 'ninecode_delete_registry', 'registry_kind' => 'taxonomy', 'key' => $tax->name ), admin_url( 'admin-post.php' ) ), 'ninecode_delete_registry_' . $tax->name );
                echo '<a class="button button-link-delete" href="' . esc_url( $del ) . '">Remove definition</a>';
            }
            echo '</div>';
        }
        echo '</div></div>';
        $this->page_footer();
    }

    public function handle_save_registry() {
        $this->require_cap(); check_admin_referer( 'ninecode_save_registry' );
        $kind = isset( $_POST['registry_kind'] ) ? sanitize_key( wp_unslash( $_POST['registry_kind'] ) ) : '';
        $key  = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
        $label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
        $plural = isset( $_POST['plural'] ) ? sanitize_text_field( wp_unslash( $_POST['plural'] ) ) : '';
        if ( ! $key || ! $label ) { wp_die( 'Key and label are required.' ); }
        $registry = (array) get_option( 'ninecode_acf_managed_registry', array( 'post_types' => array(), 'taxonomies' => array() ) );
        if ( 'post_type' === $kind && post_type_exists( $key ) && empty( $registry['post_types'][ $key ] ) ) { wp_die( 'That post type key is already owned by another WordPress component.' ); }
        if ( 'taxonomy' === $kind && taxonomy_exists( $key ) && empty( $registry['taxonomies'][ $key ] ) ) { wp_die( 'That taxonomy key is already owned by another WordPress component.' ); }
        if ( 'post_type' === $kind ) {
            if ( strlen( $key ) > 20 ) { wp_die( 'Post type keys must be 20 characters or fewer.' ); }
            $supports = isset( $_POST['supports'] ) ? array_filter( array_map( 'sanitize_key', explode( ',', wp_unslash( $_POST['supports'] ) ) ) ) : array( 'title' );
            $registry['post_types'][ $key ] = array( 'label' => $label, 'plural' => $plural ?: $label . 's', 'supports' => $supports, 'hierarchical' => ! empty( $_POST['hierarchical'] ) );
        } elseif ( 'taxonomy' === $kind ) {
            if ( strlen( $key ) > 32 ) { wp_die( 'Taxonomy keys must be 32 characters or fewer.' ); }
            $objects = isset( $_POST['object_types'] ) ? array_filter( array_map( 'sanitize_key', explode( ',', wp_unslash( $_POST['object_types'] ) ) ) ) : array( 'post' );
            $registry['taxonomies'][ $key ] = array( 'label' => $label, 'plural' => $plural ?: $label . 's', 'object_types' => $objects, 'hierarchical' => ! empty( $_POST['hierarchical'] ) );
        }
        update_option( 'ninecode_acf_managed_registry', $registry, false );
        wp_safe_redirect( add_query_arg( array( 'page' => 'ninecode-acf-registry', 'ninecode_saved' => 1 ), admin_url( 'admin.php' ) ) ); exit;
    }

    public function handle_delete_registry() {
        $this->require_cap();
        $kind = isset( $_GET['registry_kind'] ) ? sanitize_key( wp_unslash( $_GET['registry_kind'] ) ) : '';
        $key = isset( $_GET['key'] ) ? sanitize_key( wp_unslash( $_GET['key'] ) ) : '';
        check_admin_referer( 'ninecode_delete_registry_' . $key );
        $registry = (array) get_option( 'ninecode_acf_managed_registry', array( 'post_types' => array(), 'taxonomies' => array() ) );
        if ( 'post_type' === $kind ) { unset( $registry['post_types'][ $key ] ); }
        if ( 'taxonomy' === $kind ) { unset( $registry['taxonomies'][ $key ] ); }
        update_option( 'ninecode_acf_managed_registry', $registry, false );
        wp_safe_redirect( add_query_arg( array( 'page' => 'ninecode-acf-registry', 'ninecode_message' => 'Registry definition removed. Existing WordPress data was not deleted.' ), admin_url( 'admin.php' ) ) ); exit;
    }

    public function register_managed_registry() {
        $registry = (array) get_option( 'ninecode_acf_managed_registry', array( 'post_types' => array(), 'taxonomies' => array() ) );
        foreach ( (array) ( $registry['post_types'] ?? array() ) as $key => $def ) {
            if ( post_type_exists( $key ) ) { continue; }
            if ( ! apply_filters( 'ncu_data_registry_can_register', true, 'post_type', $key, $def ) ) { continue; }
            register_post_type( $key, array(
                'labels' => array( 'name' => $def['plural'], 'singular_name' => $def['label'], 'add_new_item' => 'Add ' . $def['label'], 'edit_item' => 'Edit ' . $def['label'] ),
                'public' => true, 'show_ui' => true, 'show_in_rest' => true,
                'hierarchical' => ! empty( $def['hierarchical'] ),
                'supports' => ! empty( $def['supports'] ) ? $def['supports'] : array( 'title' ),
                'has_archive' => true,
            ) );
        }
        foreach ( (array) ( $registry['taxonomies'] ?? array() ) as $key => $def ) {
            if ( taxonomy_exists( $key ) ) { continue; }
            if ( ! apply_filters( 'ncu_data_registry_can_register', true, 'taxonomy', $key, $def ) ) { continue; }
            $objects = array_values( array_filter( (array) ( $def['object_types'] ?? array( 'post' ) ), 'post_type_exists' ) );
            if ( ! $objects ) { $objects = array( 'post' ); }
            register_taxonomy( $key, $objects, array(
                'labels' => array( 'name' => $def['plural'], 'singular_name' => $def['label'] ),
                'public' => true, 'show_ui' => true, 'show_in_rest' => true,
                'hierarchical' => ! empty( $def['hierarchical'] ),
                'rewrite' => array( 'slug' => $key ),
            ) );
        }
    }

    public function render_ai() {
        $this->require_cap();
        $this->page_header( 'AI Data Manager — Import Review', 'Review Excel, CSV or AI-package changes before they are applied to the same unified data engine.' );
        $last_history = NCU_Data_Data_Importer::get_history();
        if ( $last_history ) {
            echo '<div class="ninecode-card ninecode-recovery"><div><strong>Recovery available</strong><span>Latest import: ' . esc_html( $last_history[0]['time'] ?? '' ) . ' · ' . esc_html( intval( $last_history[0]['change_count'] ?? 0 ) ) . ' recorded changes</span></div><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-ninecode-confirm="undo"><input type="hidden" name="action" value="ninecode_acf_undo_import">'; wp_nonce_field( 'ninecode_acf_undo_import' ); echo '<button class="button">Undo latest import</button></form></div>';
        }

        echo '<div class="ninecode-card"><div class="ninecode-section-head"><div><span class="ninecode-kicker">SIMPLE WORKFLOW</span><h2>Excel / AI round-trip review</h2><p>1. Choose a data type and download Excel. 2. Edit the white data cells. 3. Upload the file and Preview. 4. Review Before → After, then Apply that exact reviewed copy. If the file or live data changes, 9Code stops and asks for a new preview. 5. Use Versions if you ever need older data back.</p></div></div></div>';

        echo '<div class="ninecode-two-col">';
        echo '<div class="ninecode-card"><h2>Export Data</h2><p>Export the whole data type or narrow it to one category/taxonomy group. AI JSON, CSV and Excel use the same record IDs and field keys, so they can round-trip through the same importer.</p><form method="get" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ninecode-form-grid"><input type="hidden" name="action" value="ninecode_acf_export_collection">'; wp_nonce_field( 'ninecode_export_collection', '_wpnonce', false );
        echo '<label>Data source<select name="kind" data-ninecode-kind><option value="post">Records</option><option value="term">Categories, tags and other taxonomy items</option></select></label><label>Data type<select name="post_type">';
        $review_types = function_exists( 'nce_ai_editor_post_types' ) ? nce_ai_editor_post_types() : array();
        foreach ( $review_types as $type ) { echo '<option value="' . esc_attr( $type['name'] ) . '">' . esc_html( $type['label'] ) . '</option>'; }
        echo '</select></label><label>Taxonomy<select name="taxonomy">';
        foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $tax ) { echo '<option value="' . esc_attr( $tax->name ) . '">' . esc_html( $tax->labels->singular_name ) . '</option>'; }
        echo '</select></label><label>Category / term filter<input type="text" name="term" placeholder="Optional term slug or exact name"><small>For record exports only. Leave blank for every record.</small></label><label>Search records<input type="search" name="s" placeholder="Optional title/name search"></label><label>Format<select name="format"><option value="xlsx">Excel (.xlsx)</option><option value="json">AI Package (.json)</option><option value="csv">CSV</option></select></label><button class="button button-primary">Download data</button></form></div>';

        echo '<div class="ninecode-card"><h2>Import Edited Data</h2><p>Import ACF values, plugin meta and taxonomy assignments. New Excel exports also protect newer live data from being overwritten by an older spreadsheet. Post title, slug, excerpt, content, featured image and status can now round-trip with the same conflict protection as structured fields.</p><form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ninecode-form-grid" data-ninecode-import-form>'; wp_nonce_field( 'ninecode_acf_import' ); echo '<input type="hidden" name="action" value="ninecode_acf_import"><label class="ninecode-span-2">Excel, JSON or CSV file<input type="file" name="data_file" accept=".xlsx,.json,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/json,text/csv" required></label><label><input type="checkbox" name="create_missing_terms" value="1" checked> Create missing categories, tags or taxonomy items</label><label><input type="checkbox" name="import_terms" value="1" checked> Import taxonomy values</label><div class="ninecode-form-actions ninecode-span-2"><button class="button button-primary" name="import_mode" value="preview">Validate / Preview</button><small>Nothing is written during Preview. Apply appears only after the reviewed copy passes validation.</small></div></form></div>';
        echo '</div>';

        echo '<div class="ninecode-card"><h2>AI editing contract</h2><p>The AI Package contains its own machine-readable instructions, field types, allowed choices, taxonomy context and protected identity rules. Give the JSON file to your AI, describe the data changes you want, and tell it to return the complete JSON package without changing record IDs or field/meta keys.</p><pre class="ninecode-code">Edit only the requested object data, fields[].value, meta[].value and taxonomies. Preserve object.id, object.post_type, fields[].key and meta[].key. Return valid JSON using the same package structure.</pre></div>';
        $this->page_footer();
    }

    public function render_versions() {
        $this->require_cap();
        $this->page_header( 'Data Versions', 'Automatic saved copies of data from before edits, imports and restores. Use Restore when you need an earlier value back.' );
        if ( ! class_exists( 'NCU_Data_Data_Version_Manager' ) ) {
            echo '<div class="ninecode-card"><p>Data versioning is not available.</p></div>';
            $this->page_footer();
            return;
        }
        NCU_Data_Data_Version_Manager::maybe_install();
        $versions = NCU_Data_Data_Version_Manager::list_versions( 80 );
        echo '<div class="ninecode-card"><div class="ninecode-section-head"><div><span class="ninecode-kicker">RECOVERY</span><h2>Saved data versions</h2><p>A version is made before a Data Engine save or applied import. Restoring a version makes another safety version first.</p></div><span class="ninecode-badge">' . esc_html( NCU_Data_Data_Version_Manager::count_versions() ) . ' saved</span></div>';
        if ( ! $versions ) {
            echo '<div class="ninecode-empty">No data versions yet. They will appear automatically when you edit or import data.</div></div>';
            $this->page_footer();
            return;
        }
        echo '<div class="ninecode-record-grid">';
        foreach ( $versions as $version ) {
            $source = ucwords( str_replace( '_', ' ', $version['source'] ?? 'manual' ) );
            echo '<article class="ninecode-record"><div class="ninecode-record-main"><h3>' . esc_html( $version['object_label'] ?: ( '#' . $version['object_id'] ) ) . '</h3><p>' . esc_html( $version['object_type'] ) . ' · ' . esc_html( $version['created_at'] ) . '</p><small>' . esc_html( $source ) . ' — ' . esc_html( $version['version_label'] ) . '</small></div><div class="ninecode-record-actions"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-ninecode-confirm="restore-version"><input type="hidden" name="action" value="ninecode_acf_restore_version"><input type="hidden" name="version_id" value="' . esc_attr( $version['id'] ) . '">';
            wp_nonce_field( 'ninecode_restore_version_' . $version['id'] );
            echo '<button class="button">Restore</button></form></div></article>';
        }
        echo '</div></div>';
        $this->page_footer();
    }

    public function render_backups() {
        $this->require_cap();
        $this->page_header( 'Data Backups', 'Portable full data packs for off-site recovery. Day-to-day edits and imports are also protected by automatic Data Versions.' );
        echo '<div class="ninecode-two-col">';
        echo '<div class="ninecode-card"><h2>Create Data Pack</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ninecode-form-grid">'; wp_nonce_field( 'ninecode_acf_export_backup' ); echo '<input type="hidden" name="action" value="ninecode_acf_export_backup"><label class="ninecode-span-2"><input type="checkbox" name="include_media" value="1" checked> Include files referenced by ACF image, file and gallery fields</label><label class="ninecode-span-2"><input type="checkbox" name="include_builtin" value="1"> Include built-in post types (Posts/Pages) in addition to custom post types</label><button class="button button-primary">Download ZIP Data Pack</button></form></div>';
        echo '<div class="ninecode-card"><h2>Restore Data Pack</h2><p>Restores the complete saved data snapshot for existing WordPress records, including core record data, featured image, ACF, safe metadata and taxonomies. Missing posts are not created. A recovery snapshot is created first and newer live changes remain protected by the import safeguards.</p><form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ninecode-form-grid" data-ninecode-import-form>'; wp_nonce_field( 'ninecode_acf_restore_backup' ); echo '<input type="hidden" name="action" value="ninecode_acf_restore_backup"><label class="ninecode-span-2">ZIP Data Pack<input type="file" name="backup_file" accept=".zip,application/zip" required></label><label><input type="checkbox" name="create_missing" value="1" checked> Create missing taxonomy items only</label><label><input type="checkbox" name="restore_registry" value="1" checked> Restore 9Code registry + allocations</label><button class="button button-primary" data-ninecode-import>Restore Data Pack</button></form></div>';
        echo '</div>';
        $this->page_footer();
    }

    public function render_health() {
        $this->require_cap();
        $this->page_header( 'Data Engine Health', 'Compatibility and data-layer diagnostics.' );
        $acf_active = function_exists( 'acf_get_field_groups' );
        $acf_version = defined( 'ACF_VERSION' ) ? ACF_VERSION : 'Not active';
        $elementor = defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : 'Not active';
        $elementor_pro = defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : 'Not active';
        $groups = $acf_active ? acf_get_field_groups() : array();
        $field_count = 0;
        if ( $acf_active ) { foreach ( $groups as $g ) { $field_count += count( (array) acf_get_fields( $g ) ); } }
        $alloc = (array) get_option( 'ninecode_acf_allocations', array() );
        $registry = (array) get_option( 'ninecode_acf_managed_registry', array() );
        $rows = array(
            array( 'ACF field adapter', $acf_version, $acf_active ? 'Ready' : 'Optional — plugin metadata and taxonomies remain available' ),
            array( 'Elementor', $elementor, defined( 'ELEMENTOR_VERSION' ) ? 'Detected' : 'Optional' ),
            array( 'Elementor Pro', $elementor_pro, defined( 'ELEMENTOR_PRO_VERSION' ) ? 'Native ACF Dynamic Tags available' : 'Optional; dynamic tags require Pro' ),
            array( 'ACF field groups', count( $groups ), 'Inventory loaded' ),
            array( 'Top-level ACF fields', $field_count, 'Nested fields are additionally supported by ACF forms' ),
            array( 'Virtual group allocations', count( $alloc ), 'Non-destructive overlay' ),
            array( 'Managed post types', count( (array) ( $registry['post_types'] ?? array() ) ), '9Code-owned registry only' ),
            array( 'Managed taxonomies', count( (array) ( $registry['taxonomies'] ?? array() ) ), '9Code-owned registry only' ),
            array( 'Excel round-trip', class_exists( 'NCU_Data_Excel' ) ? 'Ready' : 'Unavailable', 'Export .xlsx, edit values, preview and import' ),
            array( 'Saved data versions', class_exists( 'NCU_Data_Data_Version_Manager' ) ? NCU_Data_Data_Version_Manager::count_versions() : 0, 'Automatic recovery copies before data changes' ),
        );
        echo '<div class="ninecode-card"><div class="ninecode-health-grid">';
        foreach ( $rows as $row ) { echo '<div><strong>' . esc_html( $row[0] ) . '</strong><span>' . esc_html( $row[1] ) . '</span><small>' . esc_html( $row[2] ) . '</small></div>'; }
        echo '</div></div>';
        echo '<div class="ninecode-card"><h2>Elementor note</h2><p>Elementor Pro can select many ACF field types through its native ACF Dynamic Tag. Repeater fields are not natively supported by Elementor’s standard ACF integration, so this plugin also supplies a generic <strong>9Code ACF Field</strong> text dynamic tag and the <code>[ninecode_acf]</code> shortcode for fallback rendering of complex data.</p></div>';
        $this->page_footer();
    }

    public function handle_export_record() {
        $this->require_cap();
        $kind = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : 'post';
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        check_admin_referer( 'ninecode_export_record_' . $id );
        $taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
        $exporter = new NCU_Data_Data_Exporter();
        $record = 'term' === $kind ? $exporter->export_term_record( $id, $taxonomy ) : $exporter->export_post_record( $id );
        $this->send_json_download( array( 'format' => 'ninecode-acf-ai-record', 'version' => 1, 'generated_at' => current_time( 'c' ), 'record' => $record ), 'ninecode-' . $kind . '-' . $id . '-acf.json' );
    }

    public function handle_export_collection() {
        $this->require_cap(); check_admin_referer( 'ninecode_export_collection' );
        $kind = isset( $_REQUEST['kind'] ) ? sanitize_key( wp_unslash( $_REQUEST['kind'] ) ) : 'post';
        $format = isset( $_REQUEST['format'] ) ? sanitize_key( wp_unslash( $_REQUEST['format'] ) ) : 'json';
        if ( ! in_array( $format, array( 'json', 'csv', 'xlsx' ), true ) ) { $format = 'json'; }
        $search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
        $exporter = new NCU_Data_Data_Exporter();
        if ( 'term' === $kind ) {
            $taxonomy = isset( $_REQUEST['taxonomy'] ) ? sanitize_key( wp_unslash( $_REQUEST['taxonomy'] ) ) : 'category';
            $pack = $exporter->export_term_collection( $taxonomy, $search );
            $ext = 'xlsx' === $format ? 'xlsx' : ( 'csv' === $format ? 'csv' : 'json' );
            $filename = 'ninecode-' . $taxonomy . '-data.' . $ext;
            $scope_name = $taxonomy;
        } else {
            $post_type = isset( $_REQUEST['post_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_type'] ) ) : 'post';
            $post_type_object = get_post_type_object( $post_type );
            $is_viewable = $post_type_object && ( function_exists( 'is_post_type_viewable' ) ? is_post_type_viewable( $post_type_object ) : ! empty( $post_type_object->public ) );
            $edit_cap = $post_type_object && isset( $post_type_object->cap->edit_posts ) ? $post_type_object->cap->edit_posts : 'edit_posts';
            if ( ! $is_viewable || 'attachment' === $post_type || ! current_user_can( $edit_cap ) ) { wp_die( esc_html__( 'This content type is not available in the AI Data Manager.', 'nine-code-ultra' ) ); }
            $filter_taxonomy = isset( $_REQUEST['taxonomy'] ) ? sanitize_key( wp_unslash( $_REQUEST['taxonomy'] ) ) : '';
            $filter_term = isset( $_REQUEST['term'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['term'] ) ) : '';
            $pack = $exporter->export_post_collection( $post_type, $search, false, $filter_taxonomy, $filter_term );
            $ext = 'xlsx' === $format ? 'xlsx' : ( 'csv' === $format ? 'csv' : 'json' );
            $filename = 'ninecode-' . $post_type . '-data.' . $ext;
            $scope_name = $post_type;
        }
        $selected_fields = isset( $_REQUEST['fields'] ) ? array_values( array_filter( array_map( 'sanitize_key', (array) wp_unslash( $_REQUEST['fields'] ) ) ) ) : array();
        if ( $selected_fields ) {
            $pack = $this->filter_pack_to_workspace_fields( $pack, $kind, isset( $post_type ) ? $post_type : '', isset( $taxonomy ) ? $taxonomy : '', $selected_fields );
            $pack['scope']['selected_fields'] = $selected_fields;
            $pack['scope']['workspace_export'] = true;
            if ( class_exists( 'NCU_Data_Scope_Lock' ) ) { $pack['scope_guard'] = NCU_Data_Scope_Lock::build( $pack['records'], $kind, $scope_name, $pack['scope'] ); }
        }
        if ( 'csv' === $format ) { $exporter->send_csv( $pack['records'], $filename, $pack['scope_guard'] ?? array() ); }
        if ( 'xlsx' === $format ) {
            $excel = new NCU_Data_Excel();
            $path = $excel->create_workbook( $pack['records'], $kind, $scope_name, $filename, $pack['scope_guard'] ?? array() );
            if ( is_wp_error( $path ) ) { wp_die( esc_html( $path->get_error_message() ) ); }
            $this->send_file_download( $path, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', true );
        }
        $this->send_json_download( $pack, $filename );
    }

    private function filter_pack_to_workspace_fields( $pack, $kind, $post_type, $taxonomy, $selected_fields ) {
        $available = $this->get_workspace_fields( $kind, $post_type, $taxonomy );
        $allowed_acf = array();
        $allowed_meta = array();
        foreach ( (array) $selected_fields as $token ) {
            if ( empty( $available[ $token ] ) ) { continue; }
            $field = $available[ $token ];
            if ( 'meta' === ( $field['source'] ?? '' ) && ! empty( $field['meta_key'] ) ) {
                $allowed_meta[ (string) $field['meta_key'] ] = true;
            } elseif ( ! empty( $field['key'] ) && 0 === strpos( (string) $field['key'], 'field_' ) ) {
                $allowed_acf[ (string) $field['key'] ] = true;
            }
        }
        foreach ( (array) ( $pack['records'] ?? array() ) as $i => $record ) {
            $pack['records'][ $i ]['fields'] = array_values( array_filter( (array) ( $record['fields'] ?? array() ), function( $row ) use ( $allowed_acf ) {
                return ! empty( $row['key'] ) && isset( $allowed_acf[ (string) $row['key'] ] );
            } ) );
            if ( 'post' === $kind ) {
                $pack['records'][ $i ]['meta'] = array_values( array_filter( (array) ( $record['meta'] ?? array() ), function( $row ) use ( $allowed_meta ) {
                    return ! empty( $row['key'] ) && isset( $allowed_meta[ (string) $row['key'] ] );
                } ) );
            }
        }
        return $pack;
    }

    public function handle_export_schema() {
        $this->require_cap(); check_admin_referer( 'ninecode_export_schema' );
        $exporter = new NCU_Data_Data_Exporter();
        $this->send_json_download( $exporter->export_schema_pack(), 'ninecode-acf-field-schema.json' );
    }

    public function handle_export_backup() {
        $this->require_cap(); check_admin_referer( 'ninecode_acf_export_backup' );
        $exporter = new NCU_Data_Data_Exporter();
        $path = $exporter->create_backup_zip( ! empty( $_POST['include_media'] ), ! empty( $_POST['include_builtin'] ) );
        if ( is_wp_error( $path ) ) { wp_die( esc_html( $path->get_error_message() ) ); }
        $this->send_file_download( $path, basename( $path ), 'application/zip', true );
    }

    private function cleanup_old_import_stages() {
        $files = glob( trailingslashit( get_temp_dir() ) . 'ninecode-acf-stage-*' );
        if ( ! is_array( $files ) ) { return; }
        $cutoff = time() - HOUR_IN_SECONDS;
        foreach ( $files as $path ) {
            if ( is_file( $path ) && @filemtime( $path ) < $cutoff ) { @unlink( $path ); }
        }
    }

    private function stage_import_file( $file, $options, $change_fingerprint, $change_ids = array() ) {
        $this->cleanup_old_import_stages();
        if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            return new WP_Error( 'stage_source_missing', 'The uploaded file could not be secured for Apply. Upload it again and Preview.' );
        }
        $ext = strtolower( pathinfo( $file['name'] ?? '', PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, array( 'xlsx', 'json', 'csv' ), true ) ) { return new WP_Error( 'stage_type', 'This file type cannot be staged.' ); }
        $token = strtolower( wp_generate_password( 24, false, false ) );
        $path = tempnam( get_temp_dir(), 'ninecode-acf-stage-' );
        if ( ! $path || ! @copy( $file['tmp_name'], $path ) ) {
            if ( $path && is_file( $path ) ) { @unlink( $path ); }
            return new WP_Error( 'stage_copy_failed', '9Code could not secure the reviewed copy. Upload the file again and Preview.' );
        }
        @chmod( $path, 0600 );
        $hash = hash_file( 'sha256', $path );
        if ( ! $hash ) { @unlink( $path ); return new WP_Error( 'stage_hash_failed', '9Code could not verify the reviewed copy.' ); }
        $stage = array(
            'path' => $path,
            'hash' => $hash,
            'name' => sanitize_file_name( $file['name'] ),
            'size' => filesize( $path ),
            'options' => array(
                'create_missing' => false,
                'create_missing_records' => false,
                'create_missing_terms' => ! empty( $options['create_missing_terms'] ),
                'import_identity' => true,
                'import_term_identity' => true,
                'import_terms' => ! empty( $options['import_terms'] ),
                'capture_versions' => true,
                'atomic' => true,
            ),
            'change_fingerprint' => (string) $change_fingerprint,
            'allowed_change_ids' => array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $change_ids ) ) ) ),
            'created' => time(),
        );
        set_transient( 'ninecode_stage_' . get_current_user_id() . '_' . $token, $stage, 30 * MINUTE_IN_SECONDS );
        return array( 'token' => $token, 'hash' => $hash );
    }

    public function handle_import() {
        $this->require_cap(); check_admin_referer( 'ninecode_acf_import' );
        if ( empty( $_FILES['data_file']['tmp_name'] ) ) { wp_die( 'No import file uploaded.' ); }
        $options = array(
            'dry_run' => true,
            'create_missing' => false,
            'create_missing_records' => false,
            'create_missing_terms' => ! empty( $_POST['create_missing_terms'] ),
            'import_identity' => true,
            'import_term_identity' => true,
            'import_terms' => ! empty( $_POST['import_terms'] ),
            'capture_versions' => false,
        );
        $importer = new NCU_Data_Data_Importer();
        $report = $importer->import_uploaded_file( $_FILES['data_file'], $options );
        if ( is_wp_error( $report ) ) { wp_die( esc_html( $report->get_error_message() ) ); }
        $report['mode'] = 'preview';
        $actual_changes = intval( $report['change_details_total'] ?? ( $report['changed'] ?? 0 ) );
        if ( empty( $report['errors_count'] ) && $actual_changes > 0 ) {
            $stage = $this->stage_import_file( $_FILES['data_file'], $options, $report['change_fingerprint'] ?? '', $report['change_ids'] ?? array() );
            if ( is_wp_error( $stage ) ) {
                $report['messages'][] = $stage->get_error_message();
            } else {
                $report['stage_token'] = $stage['token'];
                $report['stage_hash_short'] = strtoupper( substr( $stage['hash'], 0, 12 ) );
                $report['messages'][] = 'Reviewed copy secured for 30 minutes. Apply uses this exact file; no second upload is needed.';
            }
        }
        $key = wp_generate_password( 8, false, false );
        set_transient( 'ninecode_report_' . get_current_user_id() . '_' . $key, $report, 5 * MINUTE_IN_SECONDS );
        wp_safe_redirect( add_query_arg( array( 'page' => 'ninecode-acf-ai', 'ninecode_report' => $key ), admin_url( 'admin.php' ) ) ); exit;
    }

    public function handle_apply_stage() {
        $this->require_cap();
        $token = isset( $_POST['stage_token'] ) ? sanitize_key( wp_unslash( $_POST['stage_token'] ) ) : '';
        if ( ! $token ) { wp_die( 'Reviewed import copy not found. Preview the file again.' ); }
        check_admin_referer( 'ninecode_acf_apply_stage_' . $token );
        $transient_key = 'ninecode_stage_' . get_current_user_id() . '_' . $token;
        $stage = get_transient( $transient_key );
        if ( ! is_array( $stage ) || empty( $stage['path'] ) || empty( $stage['hash'] ) ) { wp_die( 'This reviewed copy expired or was already used. Preview the file again.' ); }
        $path = $stage['path'];
        if ( ! is_readable( $path ) || ! hash_equals( (string) $stage['hash'], (string) hash_file( 'sha256', $path ) ) ) {
            delete_transient( $transient_key );
            if ( is_file( $path ) ) { @unlink( $path ); }
            wp_die( 'The reviewed copy changed or is unavailable. Nothing was changed. Preview the file again.' );
        }
        delete_transient( $transient_key ); // one-time Apply token; prevents accidental replay/double-submit.
        $options = (array) ( $stage['options'] ?? array() );
        $options['dry_run'] = false;
        $options['capture_versions'] = true;
        $options['atomic'] = true;
        $options['expected_change_fingerprint'] = (string) ( $stage['change_fingerprint'] ?? '' );
        $allowed_change_ids = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) ( $stage['allowed_change_ids'] ?? array() ) ) ) ) );
        $requested_change_ids = isset( $_POST['approved_change_ids'] ) ? (array) wp_unslash( $_POST['approved_change_ids'] ) : array();
        $requested_change_ids = array_values( array_unique( array_filter( array_map( 'sanitize_key', $requested_change_ids ) ) ) );
        $approved_change_ids = array_values( array_intersect( $requested_change_ids, $allowed_change_ids ) );
        if ( ! $approved_change_ids ) { @unlink( $path ); wp_die( 'No changes were selected. Nothing was changed. Preview the file again when you are ready.' ); }
        $options['approved_change_ids'] = $approved_change_ids;
        $importer = new NCU_Data_Data_Importer();
        $report = $importer->import_staged_file( $path, $stage['name'] ?? 'ninecode-import.json', $options );
        @unlink( $path );
        if ( is_wp_error( $report ) ) { wp_die( esc_html( $report->get_error_message() ) ); }
        $report['mode'] = 'apply';
        $report['messages'][] = 'Applied only the changes you selected from the exact reviewed file copy. The full preview fingerprint was rechecked immediately before the recovery snapshot and write.';
        $key = wp_generate_password( 8, false, false );
        set_transient( 'ninecode_report_' . get_current_user_id() . '_' . $key, $report, 5 * MINUTE_IN_SECONDS );
        wp_safe_redirect( add_query_arg( array( 'page' => 'ninecode-acf-ai', 'ninecode_report' => $key ), admin_url( 'admin.php' ) ) ); exit;
    }

    public function handle_restore_version() {
        $this->require_cap();
        $id = isset( $_POST['version_id'] ) ? absint( $_POST['version_id'] ) : 0;
        check_admin_referer( 'ninecode_restore_version_' . $id );
        if ( ! $id || ! class_exists( 'NCU_Data_Data_Version_Manager' ) ) { wp_die( 'Saved data version not found.' ); }
        $report = NCU_Data_Data_Version_Manager::restore_version( $id );
        if ( is_wp_error( $report ) ) { wp_die( esc_html( $report->get_error_message() ) ); }
        $report['mode'] = 'version restore';
        $key = wp_generate_password( 8, false, false );
        set_transient( 'ninecode_report_' . get_current_user_id() . '_' . $key, $report, 5 * MINUTE_IN_SECONDS );
        wp_safe_redirect( add_query_arg( array( 'page' => 'ninecode-acf-versions', 'ninecode_report' => $key ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_restore_backup() {
        $this->require_cap(); check_admin_referer( 'ninecode_acf_restore_backup' );
        if ( empty( $_FILES['backup_file']['tmp_name'] ) ) { wp_die( 'No backup file uploaded.' ); }
        $importer = new NCU_Data_Data_Importer();
        $report = $importer->restore_backup_zip( $_FILES['backup_file'], array( 'create_missing' => ! empty( $_POST['create_missing'] ), 'restore_registry' => ! empty( $_POST['restore_registry'] ) ) );
        if ( is_wp_error( $report ) ) { wp_die( esc_html( $report->get_error_message() ) ); }
        $report['mode'] = 'restore';
        $key = wp_generate_password( 8, false, false );
        set_transient( 'ninecode_report_' . get_current_user_id() . '_' . $key, $report, 5 * MINUTE_IN_SECONDS );
        wp_safe_redirect( add_query_arg( array( 'page' => 'ninecode-acf-backups', 'ninecode_report' => $key ), admin_url( 'admin.php' ) ) ); exit;
    }

    public function handle_undo_import() {
        $this->require_cap(); check_admin_referer( 'ninecode_acf_undo_import' );
        $importer = new NCU_Data_Data_Importer();
        $report = $importer->undo_latest();
        if ( is_wp_error( $report ) ) { wp_die( esc_html( $report->get_error_message() ) ); }
        $report['mode'] = 'undo';
        $key = wp_generate_password( 8, false, false );
        set_transient( 'ninecode_report_' . get_current_user_id() . '_' . $key, $report, 5 * MINUTE_IN_SECONDS );
        wp_safe_redirect( add_query_arg( array( 'page' => 'ninecode-acf-ai', 'ninecode_report' => $key ), admin_url( 'admin.php' ) ) ); exit;
    }

    private function send_json_download( $data, $filename ) {
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
        echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        exit;
    }

    private function send_file_download( $path, $filename, $mime = 'application/octet-stream', $delete_after = false ) {
        if ( ! file_exists( $path ) ) { wp_die( 'Download file not found.' ); }
        nocache_headers(); header( 'Content-Type: ' . $mime ); header( 'Content-Length: ' . filesize( $path ) ); header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
        readfile( $path );
        if ( $delete_after ) { @unlink( $path ); }
        exit;
    }

    public function shortcode_acf( $atts ) {
        if ( ! function_exists( 'get_field' ) ) { return ''; }
        $atts = shortcode_atts( array( 'field' => '', 'post_id' => '' ), $atts, 'ninecode_acf' );
        $field = sanitize_key( $atts['field'] );
        if ( ! $field ) { return ''; }
        $post_id = $atts['post_id'] ? sanitize_text_field( $atts['post_id'] ) : get_the_ID();
        $value = get_field( $field, $post_id );
        return $this->render_generic_value( $value );
    }

    public function register_elementor_dynamic_tag( $dynamic_tags_manager ) {
        if ( ! class_exists( '\\Elementor\\Core\\DynamicTags\\Tag' ) || ! function_exists( 'acf_get_field_groups' ) ) { return; }
        require_once NCU_DATA_ENGINE_DIR . 'class-ninecode-elementor-dynamic-tag.php';
        if ( class_exists( 'NCU_Data_Elementor_ACF_Field_Tag' ) ) {
            $dynamic_tags_manager->register( new NCU_Data_Elementor_ACF_Field_Tag() );
        }
    }

    public static function generic_value_to_text( $value ) {
        if ( null === $value || false === $value ) { return ''; }
        if ( is_scalar( $value ) ) { return (string) $value; }
        if ( is_object( $value ) ) {
            if ( isset( $value->post_title ) ) { return (string) $value->post_title; }
            if ( isset( $value->name ) ) { return (string) $value->name; }
            $value = get_object_vars( $value );
        }
        if ( is_array( $value ) ) {
            $parts = array();
            foreach ( $value as $item ) {
                $text = self::generic_value_to_text( $item );
                if ( '' !== $text ) { $parts[] = $text; }
            }
            return implode( ', ', $parts );
        }
        return '';
    }

    private function render_generic_value( $value ) {
        return esc_html( self::generic_value_to_text( $value ) );
    }
}
