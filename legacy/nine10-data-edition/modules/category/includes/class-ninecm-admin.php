<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class NineCM_Admin {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
        add_action( 'admin_init', array( $this, 'settings' ) );
    }

    public function menu() {
        $menu_cap = 'edit_posts';
        if ( current_user_can( 'edit_pages' ) ) {
            $menu_cap = 'edit_pages';
        } elseif ( ! current_user_can( 'edit_posts' ) ) {
            foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $pto ) {
                if ( ! empty( $pto->cap->edit_posts ) && current_user_can( $pto->cap->edit_posts ) ) {
                    $menu_cap = $pto->cap->edit_posts;
                    break;
                }
            }
        }
        add_menu_page(
            '9 Category Manager',
            '9 Category Manager',
            $menu_cap,
            'nine-category-manager',
            array( $this, 'page' ),
            'dashicons-networking',
            26
        );
    }

    public function settings() {
        register_setting( 'ninecm_settings_group', 'ninecm_settings', array( $this, 'sanitize_settings' ) );
    }

    public function sanitize_settings( $in ) {
        $defaults = NineCM_Core::default_settings();
        $in = is_array( $in ) ? $in : array();
        $positions = array( 'bottom-right', 'bottom-left', 'top-right', 'top-left' );
        $position = isset( $in['launcher_position'] ) && in_array( $in['launcher_position'], $positions, true ) ? $in['launcher_position'] : $defaults['launcher_position'];

        $taxonomy = sanitize_key( $in['default_taxonomy'] ?? $defaults['default_taxonomy'] );
        $tax_obj = get_taxonomy( $taxonomy );
        if ( ! $tax_obj || empty( $tax_obj->show_ui ) ) { $taxonomy = 'category'; }

        $post_type = sanitize_key( $in['default_post_type'] ?? $defaults['default_post_type'] );
        $pto = get_post_type_object( $post_type );
        if ( ! $pto || empty( $pto->show_ui ) ) { $post_type = 'page'; }

        return array(
            'frontend_button'        => ! empty( $in['frontend_button'] ) ? 1 : 0,
            'enable_page_categories' => ! empty( $in['enable_page_categories'] ) ? 1 : 0,
            'enable_page_tags'       => ! empty( $in['enable_page_tags'] ) ? 1 : 0,
            'enable_page_excerpt'    => ! empty( $in['enable_page_excerpt'] ) ? 1 : 0,
            'default_taxonomy'       => $taxonomy,
            'default_post_type'      => $post_type,
            'cache_minutes'          => max( 1, min( 1440, absint( $in['cache_minutes'] ?? $defaults['cache_minutes'] ) ) ),
            'manager_per_page'       => max( 10, min( 200, absint( $in['manager_per_page'] ?? $defaults['manager_per_page'] ) ) ),
            'public_post_limit'       => max( 100, min( 50000, absint( $in['public_post_limit'] ?? $defaults['public_post_limit'] ) ) ),
            'launcher_position'      => $position,
            'launcher_offset_x'      => max( 0, min( 300, absint( $in['launcher_offset_x'] ?? $defaults['launcher_offset_x'] ) ) ),
            'launcher_offset_y'      => max( 0, min( 500, absint( $in['launcher_offset_y'] ?? $defaults['launcher_offset_y'] ) ) ),
            'launcher_size'          => max( 36, min( 80, absint( $in['launcher_size'] ?? $defaults['launcher_size'] ) ) ),
            'audit_depth_warning'    => max( 3, min( 20, absint( $in['audit_depth_warning'] ?? $defaults['audit_depth_warning'] ) ) ),
            'audit_child_warning'    => max( 10, min( 500, absint( $in['audit_child_warning'] ?? $defaults['audit_child_warning'] ) ) ),
            'shell_stale_days'       => max( 7, min( 365, absint( $in['shell_stale_days'] ?? $defaults['shell_stale_days'] ) ) ),
        );
    }

    public function assets( $hook ) {
        if ( 'toplevel_page_nine-category-manager' !== $hook ) { return; }
        wp_enqueue_style( 'ninecm-admin', NINECM_URL . 'assets/admin.css', array(), NINECM_VERSION );
        wp_enqueue_script( 'ninecm-admin', NINECM_URL . 'assets/admin.js', array(), NINECM_VERSION, true );
        if ( current_user_can( 'upload_files' ) ) { wp_enqueue_media(); }
        $settings = wp_parse_args( (array) get_option( 'ninecm_settings', array() ), NineCM_Core::default_settings() );

        $tax_caps = array();
        foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $tax ) {
            $tax_caps[ $tax->name ] = array(
                'manage' => ! empty( $tax->cap->manage_terms ) && current_user_can( $tax->cap->manage_terms ),
                'edit'   => ! empty( $tax->cap->edit_terms ) && current_user_can( $tax->cap->edit_terms ),
                'delete' => ! empty( $tax->cap->delete_terms ) && current_user_can( $tax->cap->delete_terms ),
                'assign' => ! empty( $tax->cap->assign_terms ) && current_user_can( $tax->cap->assign_terms ),
            );
        }

        wp_localize_script( 'ninecm-admin', 'NineCMAdmin', array(
            'root'      => esc_url_raw( rest_url( 'ninecm/v1/' ) ),
            'nonce'     => wp_create_nonce( 'wp_rest' ),
            'taxonomy'  => $settings['default_taxonomy'],
            'postType'  => $settings['default_post_type'],
            'perPage'   => $settings['manager_per_page'],
            'taxCaps'   => $tax_caps,
            'version'   => NINECM_VERSION,
            'userId'    => get_current_user_id(),
            'canUpload' => current_user_can( 'upload_files' ),
        ) );
    }

    private function export_url( $type, $taxonomy, $post_type = '' ) {
        $args = array(
            'action'   => 'ninecm_export',
            'type'     => $type,
            'taxonomy' => $taxonomy,
        );
        if ( $post_type ) { $args['post_type'] = $post_type; }
        return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'ninecm_export' );
    }

    public function page() {
        if ( ! NineCM_Core::can_access_planner() ) { wp_die( esc_html__( 'You do not have permission to use 9 Category Manager.', 'nine-category-manager' ) ); }
        $settings = wp_parse_args( (array) get_option( 'ninecm_settings', array() ), NineCM_Core::default_settings() );
        $taxonomies = get_taxonomies( array( 'show_ui' => true ), 'objects' );
        $post_types = get_post_types( array( 'show_ui' => true ), 'objects' );
        ?>
        <div class="wrap ninecm-admin">
            <?php if ( isset( $_GET['ninecm_notice'] ) && 'baseline_saved' === sanitize_key( wp_unslash( $_GET['ninecm_notice'] ) ) ) : ?><div class="notice notice-success is-dismissible"><p>Infrastructure baseline saved. Structure Drift Guard is now watching for registration changes.</p></div><?php endif; ?>
            <?php if ( isset( $_GET['ninecm_notice'] ) && 'baseline_cleared' === sanitize_key( wp_unslash( $_GET['ninecm_notice'] ) ) ) : ?><div class="notice notice-info is-dismissible"><p>Infrastructure baseline cleared. Site content was not changed.</p></div><?php endif; ?>
            <h1>9 Category Manager — Site Infrastructure Planner <span class="ninecm-version">v<?php echo esc_html( NINECM_VERSION ); ?></span></h1>
            <p class="description"><strong>Infrastructure/planning layer:</strong> create content shells and define taxonomies, tags, authors, parent/child structure, menu order, featured image, title and excerpt. Populate detailed fields/content later in 9 Post Editor.</p>

            <nav class="nav-tab-wrapper">
                <button class="nav-tab nav-tab-active" type="button" data-tab="infrastructure">Site Infrastructure</button>
                <button class="nav-tab" type="button" data-tab="categories">Taxonomies & Tags</button>
                <button class="nav-tab" type="button" data-tab="planning">Content Allocation</button>
                <button class="nav-tab" type="button" data-tab="bulk">Bulk Builder</button>
                <button class="nav-tab" type="button" data-tab="health">Health Audit</button>
                <button class="nav-tab" type="button" data-tab="exports">Exports</button>
                <button class="nav-tab" type="button" data-tab="settings">Settings</button>
            </nav>

            <section data-panel="infrastructure">
                <div class="ninecm-card">
                    <h2>Site Infrastructure Map</h2>
                    <p>Live discovery of the structural systems registered on this WordPress installation. This is the planning boundary: content types, authors, featured images, excerpts, native parent/order, taxonomies/tags and explicit relationship providers. Body content and detailed fields remain in 9 Post Editor.</p>
                    <div class="ninecm-infra-grid">
                    <?php foreach ( NineCM_Infrastructure::editable_post_types() as $infra_type ) : ?>
                        <article class="ninecm-infra-card">
                            <div class="ninecm-infra-head"><h3><?php echo esc_html( $infra_type['label'] ); ?></h3><code><?php echo esc_html( $infra_type['name'] ); ?></code></div>
                            <p><strong>Planning controls:</strong>
                                <?php
                                $controls = array( 'Title' );
                                if ( ! empty( $infra_type['supports']['excerpt'] ) ) { $controls[] = 'Excerpt'; }
                                if ( ! empty( $infra_type['supports']['thumbnail'] ) ) { $controls[] = 'Featured image'; }
                                if ( ! empty( $infra_type['supports']['author'] ) ) { $controls[] = 'Author'; }
                                if ( ! empty( $infra_type['supportsParent'] ) ) { $controls[] = 'Parent/child'; }
                                if ( ! empty( $infra_type['supportsOrder'] ) ) { $controls[] = 'Manual order'; }
                                echo esc_html( implode( ' · ', $controls ) );
                                ?>
                            </p>
                            <p><strong>Taxonomies / tags:</strong>
                                <?php
                                $names = array_map( function( $t ) { return $t['label'] . ( $t['hierarchical'] ? ' (hierarchy)' : ' (tags)' ); }, (array) $infra_type['taxonomies'] );
                                echo $names ? esc_html( implode( ' · ', $names ) ) : '<span class="ninecm-subtle">None registered</span>';
                                ?>
                            </p>
                            <?php if ( ! empty( $infra_type['providers'] ) ) : ?>
                                <p><strong>Plugin relationships:</strong> <?php echo esc_html( implode( ' · ', array_map( function( $p ) { return $p['label']; }, $infra_type['providers'] ) ) ); ?></p>
                            <?php endif; ?>
                            <p class="ninecm-subtle"><?php echo ! empty( $infra_type['hierarchical'] ) ? 'Hierarchical content type' : 'Flat content type'; ?> · <?php echo ! empty( $infra_type['canCreate'] ) ? 'Can create shells' : 'Read/edit only for this role'; ?></p>
                        </article>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php NineCM_Drift::render_admin_card(); ?>
                <div class="ninecm-card">
                    <h2>Relationship-provider API</h2>
                    <p>Course → Module, Programme → Course, Author → Course and similar relationships that another plugin stores in custom data are not guessed. They can register an explicit <code>ninecm_relationship_providers</code> provider and immediately appear in both the backend Structure editor and the full-screen front-end planner. This prevents 9 Category Manager from corrupting unknown plugin metadata.</p>
                </div>
            </section>

            <section data-panel="categories" hidden>
                <div class="ninecm-controls">
                    <select data-ninecm-taxonomy aria-label="Taxonomy"></select>
                    <input type="search" data-ninecm-term-search placeholder="Search term name, slug or hierarchy path">
                    <select data-ninecm-parent-filter>
                        <option value="">Any parent</option>
                        <option value="0">Top level only</option>
                    </select>
                    <select data-ninecm-term-visibility>
                        <option value="all">All terms</option>
                        <option value="used">With published content</option>
                        <option value="active">Active only</option>
                        <option value="archived">Archived only</option>
                    </select>
                    <button class="button button-primary" type="button" data-ninecm-new-term>+ New term</button>
                </div>
                <div class="ninecm-card">
                    <table class="widefat striped ninecm-responsive-table">
                        <thead><tr><th>Name / hierarchy path</th><th>Slug</th><th>Published</th><th>Order</th><th>State</th><th>Actions</th></tr></thead>
                        <tbody data-ninecm-term-rows></tbody>
                    </table>
                    <div class="ninecm-pager" data-ninecm-term-pager></div>
                </div>
            </section>

            <section data-panel="planning" hidden>
                <div class="ninecm-planning-note">
                    <strong>Structure first, content second.</strong> Bulk-allocate the selected taxonomy here, or open an item’s Structure editor to manage all attached taxonomies/tags, author, parent, order, title, excerpt and featured image. Detailed content stays in 9 Post Editor.
                </div>
                <div class="ninecm-controls">
                    <select data-ninecm-post-type aria-label="Content type"></select>
                    <input type="search" data-ninecm-post-search placeholder="Search content title">
                    <select data-ninecm-post-status aria-label="Content status">
                        <option value="">All statuses</option><option value="draft">Draft</option><option value="publish">Published</option><option value="pending">Pending</option><option value="future">Scheduled</option><option value="private">Private</option>
                    </select>
                    <select data-ninecm-post-rel-scope aria-label="Selected taxonomy relationship filter">
                        <option value="all">All relationships</option><option value="assigned">Has selected-taxonomy relationship</option><option value="unassigned">No selected-taxonomy relationship</option>
                    </select>
                    <button class="button button-primary" type="button" data-ninecm-create-shell>+ Create shell</button>
                    <button class="button" type="button" data-ninecm-undo-assignment hidden>Undo last allocation</button>
                </div>

                <div class="ninecm-planning-grid">
                    <div class="ninecm-card ninecm-rel-picker">
                        <h2>Selected taxonomy relationship</h2>
                        <div class="ninecm-picker-search">
                            <input type="search" data-ninecm-assign-term-search placeholder="Search terms by name/path">
                            <button class="button" type="button" data-ninecm-assign-term-load>Search</button>
                        </div>
                        <div class="ninecm-term-results" data-ninecm-assign-term-results></div>
                        <h3>Selected relationships</h3>
                        <div class="ninecm-selected-terms" data-ninecm-selected-terms><span class="ninecm-subtle">No terms selected.</span></div>
                        <div class="ninecm-rel-actions">
                            <select data-ninecm-assign-mode>
                                <option value="add">Add selected relationships</option>
                                <option value="remove">Remove selected relationships</option>
                                <option value="replace">Replace all relationships</option>
                            </select>
                            <button class="button button-primary" type="button" data-ninecm-apply>Apply to selected content</button>
                        </div>
                    </div>

                    <div class="ninecm-card ninecm-content-list-card">
                        <table class="widefat striped ninecm-responsive-table">
                            <thead><tr><th><input type="checkbox" data-ninecm-select-all></th><th>Page / post</th><th>Status</th><th>Relationships</th></tr></thead>
                            <tbody data-ninecm-post-rows></tbody>
                        </table>
                        <div class="ninecm-pager" data-ninecm-post-pager></div>
                    </div>
                </div>

                <div class="ninecm-card ninecm-bulk-structure-card">
                    <div class="ninecm-section-head">
                        <div><h2>Bulk structural allocation</h2><p>Uses the same selected content checkboxes above. Apply native site-structure fields in one operation, with a separate 30-minute undo checkpoint.</p></div>
                        <button class="button" type="button" data-ninecm-undo-structure hidden>Undo structural change</button>
                    </div>
                    <div class="ninecm-bulk-structure-grid">
                        <div class="ninecm-bulk-structure-tool" data-ninecm-bulk-author-box>
                            <h3>Author</h3>
                            <input type="search" data-ninecm-bulk-author-search placeholder="Search author">
                            <input type="hidden" data-ninecm-bulk-author value="0">
                            <div class="ninecm-mini-results" data-ninecm-bulk-author-results></div>
                            <button class="button button-primary" type="button" data-ninecm-bulk-author-apply>Assign author</button>
                        </div>
                        <div class="ninecm-bulk-structure-tool" data-ninecm-bulk-parent-box>
                            <h3>Parent / hierarchy</h3>
                            <input type="search" data-ninecm-bulk-parent-search placeholder="Search parent item">
                            <input type="hidden" data-ninecm-bulk-parent value="0">
                            <div class="ninecm-mini-results" data-ninecm-bulk-parent-results></div>
                            <p><button class="button" type="button" data-ninecm-bulk-parent-clear>Use top level</button> <button class="button button-primary" type="button" data-ninecm-bulk-parent-apply>Assign parent</button></p>
                        </div>
                        <div class="ninecm-bulk-structure-tool" data-ninecm-bulk-order-box>
                            <h3>Native order</h3>
                            <label>Start <input type="number" data-ninecm-bulk-order-start value="0"></label>
                            <label>Step <input type="number" data-ninecm-bulk-order-step value="10"></label>
                            <p class="description">Selected items are ordered in their current on-screen sequence.</p>
                            <button class="button button-primary" type="button" data-ninecm-bulk-order-apply>Apply sequence</button>
                        </div>
                    </div>
                    <div class="ninecm-subtle" data-ninecm-bulk-structure-note>Select content rows above first.</div>
                </div>
            </section>

            <section data-panel="bulk" hidden>
                <div class="ninecm-card">
                    <h2>Taxonomy / Tag Bulk Builder</h2>
                    <p data-ninecm-bulk-help>For hierarchical systems, use <code>&gt;</code> for a child and <code>&gt;&gt;</code> for a grandchild. For flat tag-like systems, enter one term per line. Large plans run in resumable batches.</p>
                    <textarea data-ninecm-bulk rows="14" class="large-text code" placeholder="Faculty of Management Sciences&#10;>Entrepreneurship&#10;>>Undergraduate&#10;>>>100 Level&#10;>>>200 Level&#10;>>Postgraduate&#10;>Finance"></textarea>
                    <div class="ninecm-bulk-tools">
                        <button class="button button-primary" type="button" data-ninecm-bulk-run>Create hierarchy</button>
                        <label class="button">Load exported structure JSON<input type="file" accept="application/json,.json" data-ninecm-import-structure hidden></label>
                    </div>
                    <div data-ninecm-bulk-result></div>
                </div>
                <div class="ninecm-card">
                    <h2>Bulk Content-Shell Planner</h2>
                    <p>Create many blank draft shells after the structure exists. Hierarchical systems use <code>Parent &gt; Child :: Content title :: Optional excerpt</code>; flat systems use <code>Tag :: Content title :: Optional excerpt</code>. Re-running the same unchanged plan reuses the existing shell instead of duplicating it.</p>
                    <textarea data-ninecm-bulk-shells rows="12" class="large-text code" placeholder="Research > Methods > Quantitative :: Quantitative Research Services :: Methods and support available&#10;Research > Methods > Qualitative :: Qualitative Research Services"></textarea>
                    <p><button class="button button-primary" type="button" data-ninecm-bulk-shell-run>Create page shells</button></p>
                    <div data-ninecm-bulk-shell-result></div>
                </div>
            </section>

            <section data-panel="health" hidden>
                <div class="ninecm-card">
                    <div class="ninecm-health-head"><div><h2>Taxonomy & Planning Health</h2><p>Read-only audit of the currently selected taxonomy/content type. It looks for unassigned content, content stranded only in archived branches, redundant ancestor assignments, stale blank shells, empty leaves, excessive depth, very wide branches and repeated category names.</p></div><button class="button button-primary" type="button" data-ninecm-health-run>Run audit</button></div>
                    <div data-ninecm-health-result><p class="description">Run the audit after major imports, bulk allocation, or restructuring.</p></div>
                </div>
            </section>

            <section data-panel="exports" hidden>
                <div class="ninecm-card">
                    <h2>Portable planning exports</h2>
                    <p>Use these for backups, Excel filtering, handoff, auditing, or AI-assisted analysis. CSV downloads include a UTF-8 marker for better Microsoft Excel compatibility.</p>
                    <div class="ninecm-export-grid">
                        <div class="ninecm-export-box">
                            <h3>Taxonomy structure</h3>
                            <p>ID, name, slug, parent/path when hierarchical, published count, 9 order/protection/archive state, description and term URL.</p>
                            <a class="button button-primary" data-ninecm-export-categories href="<?php echo esc_url( $this->export_url( 'categories', $settings['default_taxonomy'] ) ); ?>">Download terms CSV</a>
                            <a class="button" data-ninecm-export-json href="<?php echo esc_url( $this->export_url( 'structure', $settings['default_taxonomy'] ) ); ?>">Download structure JSON</a>
                            <a class="button" data-ninecm-export-blueprint href="<?php echo esc_url( $this->export_url( 'blueprint', $settings['default_taxonomy'], $settings['default_post_type'] ) ); ?>">Download full site-infrastructure blueprint JSON</a>
                        </div>
                        <div class="ninecm-export-box">
                            <h3>Content infrastructure planning</h3>
                            <p>Title, excerpt, featured image, author, native parent/order, selected taxonomy, every attached taxonomy/tag allocation, registered relationship providers, status and URLs.</p>
                            <a class="button button-primary" data-ninecm-export-planning href="<?php echo esc_url( $this->export_url( 'planning', $settings['default_taxonomy'], $settings['default_post_type'] ) ); ?>">Download planning CSV</a>
                            <p class="description">The export buttons follow the taxonomy/content type currently selected in the manager.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section data-panel="settings" hidden>
                <form method="post" action="options.php" class="ninecm-card">
                    <?php settings_fields( 'ninecm_settings_group' ); ?>
                    <table class="form-table">
                        <tr>
                            <th>Pages use Categories</th>
                            <td><label><input type="checkbox" name="ninecm_settings[enable_page_categories]" value="1" <?php checked( $settings['enable_page_categories'] ); ?>> Enable the standard WordPress category taxonomy for Pages</label><p class="description">Required for the default page-planning workflow. Disable only if another taxonomy system already handles Pages.</p></td>
                        </tr>
                        <tr>
                            <th>Pages use Tags</th>
                            <td><label><input type="checkbox" name="ninecm_settings[enable_page_tags]" value="1" <?php checked( $settings['enable_page_tags'] ); ?>> Enable the standard WordPress Tags taxonomy for Pages</label><p class="description">Keeps Pages available to the same category/tag planning workflow. Custom taxonomies are never force-attached automatically.</p></td>
                        </tr>
                        <tr>
                            <th>Pages expose Excerpts</th>
                            <td><label><input type="checkbox" name="ninecm_settings[enable_page_excerpt]" value="1" <?php checked( $settings['enable_page_excerpt'] ); ?>> Enable native excerpt support for Pages</label><p class="description">Recommended. 9 Category Manager uses the excerpt as the planning summary; this also makes that summary visible to compatible WordPress editors and tools.</p></td>
                        </tr>
                        <tr>
                            <th>Front-end manager launcher</th>
                            <td><label><input type="checkbox" name="ninecm_settings[frontend_button]" value="1" <?php checked( $settings['frontend_button'] ); ?>> Show the floating “9” launcher to authorised editors</label></td>
                        </tr>
                        <tr>
                            <th>Default launcher position</th>
                            <td>
                                <select name="ninecm_settings[launcher_position]">
                                    <option value="bottom-right" <?php selected( $settings['launcher_position'], 'bottom-right' ); ?>>Bottom right</option>
                                    <option value="bottom-left" <?php selected( $settings['launcher_position'], 'bottom-left' ); ?>>Bottom left</option>
                                    <option value="top-right" <?php selected( $settings['launcher_position'], 'top-right' ); ?>>Top right</option>
                                    <option value="top-left" <?php selected( $settings['launcher_position'], 'top-left' ); ?>>Top left</option>
                                </select>
                                <label class="ninecm-inline-field">Horizontal offset <input type="number" min="0" max="300" name="ninecm_settings[launcher_offset_x]" value="<?php echo esc_attr( $settings['launcher_offset_x'] ); ?>"> px</label>
                                <label class="ninecm-inline-field">Vertical offset <input type="number" min="0" max="500" name="ninecm_settings[launcher_offset_y]" value="<?php echo esc_attr( $settings['launcher_offset_y'] ); ?>"> px</label>
                                <label class="ninecm-inline-field">Size <input type="number" min="36" max="80" name="ninecm_settings[launcher_size]" value="<?php echo esc_attr( $settings['launcher_size'] ); ?>"> px</label>
                                <p class="description">This default can also be overridden per administrator/device from the front-end popup, useful when 9 Post Editor occupies the same corner.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Default taxonomy/tag system</th>
                            <td><select name="ninecm_settings[default_taxonomy]">
                                <?php foreach ( $taxonomies as $tax ) : ?>
                                    <option value="<?php echo esc_attr( $tax->name ); ?>" <?php selected( $settings['default_taxonomy'], $tax->name ); ?>><?php echo esc_html( $tax->labels->name ); ?></option>
                                <?php endforeach; ?>
                            </select></td>
                        </tr>
                        <tr>
                            <th>Default planning content type</th>
                            <td><select name="ninecm_settings[default_post_type]">
                                <?php foreach ( $post_types as $pto ) : if ( empty( $pto->cap->edit_posts ) || ! current_user_can( $pto->cap->edit_posts ) ) { continue; } ?>
                                    <option value="<?php echo esc_attr( $pto->name ); ?>" <?php selected( $settings['default_post_type'], $pto->name ); ?>><?php echo esc_html( $pto->labels->name ); ?></option>
                                <?php endforeach; ?>
                            </select></td>
                        </tr>
                        <tr>
                            <th>Public directory cache</th>
                            <td><input type="number" min="1" max="1440" name="ninecm_settings[cache_minutes]" value="<?php echo esc_attr( $settings['cache_minutes'] ); ?>"> minutes</td>
                        </tr>
                        <tr>
                            <th>Public directory safety limit</th>
                            <td><input type="number" min="100" max="50000" name="ninecm_settings[public_post_limit]" value="<?php echo esc_attr( $settings['public_post_limit'] ); ?>"> published items<p class="description">Prevents a single Post of Contents from exhausting memory on extremely large sites. If the limit is reached, the public directory shows a visible warning instead of silently omitting content.</p></td>
                        </tr>
                        <tr>
                            <th>Manager page size</th>
                            <td><input type="number" min="10" max="200" name="ninecm_settings[manager_per_page]" value="<?php echo esc_attr( $settings['manager_per_page'] ); ?>"></td>
                        </tr>
                        <tr>
                            <th>Health audit thresholds</th>
                            <td>
                                <label class="ninecm-inline-field">Warn after depth <input type="number" min="3" max="20" name="ninecm_settings[audit_depth_warning]" value="<?php echo esc_attr( $settings['audit_depth_warning'] ); ?>"></label>
                                <label class="ninecm-inline-field">Warn after direct children <input type="number" min="10" max="500" name="ninecm_settings[audit_child_warning]" value="<?php echo esc_attr( $settings['audit_child_warning'] ); ?>"></label>
                                <label class="ninecm-inline-field">Stale shell after <input type="number" min="7" max="365" name="ninecm_settings[shell_stale_days]" value="<?php echo esc_attr( $settings['shell_stale_days'] ); ?>"> days</label>
                                <p class="description">These are warnings, not hard limits. They help catch taxonomy structures that become difficult to maintain.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            </section>

            <div class="ninecm-dialog" data-ninecm-dialog hidden>
                <div class="ninecm-dialog__box" role="dialog" aria-modal="true" aria-label="9 Category Manager dialog">
                    <button type="button" data-ninecm-dialog-close class="ninecm-x" aria-label="Close">×</button>
                    <div data-ninecm-dialog-content></div>
                </div>
            </div>
        </div>
        <?php
    }
}
