<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Structure Drift Guard.
 *
 * Stores a deliberately small baseline of registered site infrastructure so an
 * administrator can see when a plugin/theme update, deactivation or registration
 * change removes or rewires a content type, taxonomy or explicit relationship
 * provider. It does not snapshot content and never mutates third-party data.
 */
class NineCM_Drift {
    const OPTION = 'ninecm_infrastructure_baseline';
    const SCHEMA = 1;

    public function __construct() {
        add_action( 'admin_post_ninecm_save_infrastructure_baseline', array( $this, 'save_baseline' ) );
        add_action( 'admin_post_ninecm_clear_infrastructure_baseline', array( $this, 'clear_baseline' ) );
    }

    private static function supports_for( $post_type ) {
        return array(
            'title'      => post_type_supports( $post_type, 'title' ),
            'excerpt'    => post_type_supports( $post_type, 'excerpt' ),
            'thumbnail'  => post_type_supports( $post_type, 'thumbnail' ),
            'author'     => post_type_supports( $post_type, 'author' ),
            'page_attrs' => post_type_supports( $post_type, 'page-attributes' ),
        );
    }

    /**
     * Read provider keys without invoking provider permission callbacks. The
     * baseline records only structural identity (key/label/type), never values.
     */
    private static function raw_provider_descriptors( $post_type ) {
        $providers = apply_filters( 'ninecm_relationship_providers', array(), sanitize_key( $post_type ), 0 );
        if ( ! is_array( $providers ) ) { return array(); }
        $out = array();
        foreach ( $providers as $provider ) {
            if ( ! is_array( $provider ) || empty( $provider['key'] ) || empty( $provider['label'] ) ) { continue; }
            if ( empty( $provider['get_callback'] ) || ! is_callable( $provider['get_callback'] ) ) { continue; }
            if ( empty( $provider['update_callback'] ) || ! is_callable( $provider['update_callback'] ) ) { continue; }
            $key = sanitize_key( $provider['key'] );
            if ( ! $key ) { continue; }
            $out[ $key ] = array(
                'label' => sanitize_text_field( $provider['label'] ),
                'type'  => in_array( $provider['type'] ?? 'text', array( 'select', 'number', 'text' ), true ) ? $provider['type'] : 'text',
            );
        }
        ksort( $out, SORT_STRING );
        return $out;
    }

    public static function structure_snapshot() {
        $post_types = array();
        foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $pto ) {
            if ( in_array( $pto->name, array( 'attachment', 'revision', 'nav_menu_item' ), true ) ) { continue; }
            $taxonomies = array_keys( get_object_taxonomies( $pto->name, 'objects' ) );
            sort( $taxonomies, SORT_STRING );
            $post_types[ $pto->name ] = array(
                'hierarchical' => ! empty( $pto->hierarchical ),
                'supports'     => self::supports_for( $pto->name ),
                'taxonomies'   => $taxonomies,
                'providers'    => self::raw_provider_descriptors( $pto->name ),
            );
        }
        ksort( $post_types, SORT_STRING );

        $taxonomies = array();
        foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $tax ) {
            $object_types = array_values( array_unique( array_map( 'sanitize_key', (array) $tax->object_type ) ) );
            sort( $object_types, SORT_STRING );
            $taxonomies[ $tax->name ] = array(
                'hierarchical' => ! empty( $tax->hierarchical ),
                'public'       => ! empty( $tax->public ),
                'object_types' => $object_types,
            );
        }
        ksort( $taxonomies, SORT_STRING );

        return array(
            'schema'      => self::SCHEMA,
            'post_types'  => $post_types,
            'taxonomies'  => $taxonomies,
        );
    }

    public static function capture_payload() {
        global $wp_version;
        return array(
            'schema'           => self::SCHEMA,
            'captured_at'      => time(),
            'captured_by'      => get_current_user_id(),
            'wordpress'        => isset( $wp_version ) ? (string) $wp_version : get_bloginfo( 'version' ),
            'plugin_version'   => NINECM_VERSION,
            'structure'        => self::structure_snapshot(),
        );
    }

    private static function list_diff( $before, $after ) {
        return array_values( array_diff( array_values( (array) $before ), array_values( (array) $after ) ) );
    }

    private static function add_issue( &$issues, $severity, $code, $message ) {
        $issues[] = array( 'severity' => $severity, 'code' => $code, 'message' => $message );
    }

    public static function compare( $baseline_structure, $current_structure ) {
        $issues = array();
        $base_pts = isset( $baseline_structure['post_types'] ) && is_array( $baseline_structure['post_types'] ) ? $baseline_structure['post_types'] : array();
        $now_pts  = isset( $current_structure['post_types'] ) && is_array( $current_structure['post_types'] ) ? $current_structure['post_types'] : array();
        $base_tax = isset( $baseline_structure['taxonomies'] ) && is_array( $baseline_structure['taxonomies'] ) ? $baseline_structure['taxonomies'] : array();
        $now_tax  = isset( $current_structure['taxonomies'] ) && is_array( $current_structure['taxonomies'] ) ? $current_structure['taxonomies'] : array();

        foreach ( array_diff( array_keys( $base_pts ), array_keys( $now_pts ) ) as $name ) {
            self::add_issue( $issues, 'critical', 'post_type_missing', sprintf( 'Content type "%s" is no longer registered.', $name ) );
        }
        foreach ( array_diff( array_keys( $now_pts ), array_keys( $base_pts ) ) as $name ) {
            self::add_issue( $issues, 'info', 'post_type_added', sprintf( 'New content type "%s" is now registered.', $name ) );
        }
        foreach ( array_intersect( array_keys( $base_pts ), array_keys( $now_pts ) ) as $name ) {
            $before = $base_pts[ $name ]; $after = $now_pts[ $name ];
            if ( ! empty( $before['hierarchical'] ) !== ! empty( $after['hierarchical'] ) ) {
                self::add_issue( $issues, 'critical', 'post_type_hierarchy_changed', sprintf( 'Content type "%s" changed hierarchical mode.', $name ) );
            }
            foreach ( array( 'title', 'excerpt', 'thumbnail', 'author', 'page_attrs' ) as $support ) {
                $b = ! empty( $before['supports'][ $support ] ); $a = ! empty( $after['supports'][ $support ] );
                if ( $b !== $a ) {
                    self::add_issue( $issues, 'warning', 'support_changed', sprintf( 'Content type "%s" changed support for %s (%s → %s).', $name, $support, $b ? 'on' : 'off', $a ? 'on' : 'off' ) );
                }
            }
            foreach ( self::list_diff( $before['taxonomies'] ?? array(), $after['taxonomies'] ?? array() ) as $tax ) {
                self::add_issue( $issues, 'critical', 'taxonomy_detached', sprintf( 'Taxonomy "%s" is no longer attached to content type "%s".', $tax, $name ) );
            }
            foreach ( self::list_diff( $after['taxonomies'] ?? array(), $before['taxonomies'] ?? array() ) as $tax ) {
                self::add_issue( $issues, 'info', 'taxonomy_attached', sprintf( 'Taxonomy "%s" is newly attached to content type "%s".', $tax, $name ) );
            }
            $before_providers = array_keys( is_array( $before['providers'] ?? null ) ? $before['providers'] : array() );
            $after_providers  = array_keys( is_array( $after['providers'] ?? null ) ? $after['providers'] : array() );
            foreach ( self::list_diff( $before_providers, $after_providers ) as $provider ) {
                self::add_issue( $issues, 'critical', 'provider_missing', sprintf( 'Relationship provider "%s" disappeared from content type "%s".', $provider, $name ) );
            }
            foreach ( self::list_diff( $after_providers, $before_providers ) as $provider ) {
                self::add_issue( $issues, 'info', 'provider_added', sprintf( 'Relationship provider "%s" is newly available for content type "%s".', $provider, $name ) );
            }
        }

        foreach ( array_diff( array_keys( $base_tax ), array_keys( $now_tax ) ) as $name ) {
            self::add_issue( $issues, 'critical', 'taxonomy_missing', sprintf( 'Taxonomy "%s" is no longer registered.', $name ) );
        }
        foreach ( array_diff( array_keys( $now_tax ), array_keys( $base_tax ) ) as $name ) {
            self::add_issue( $issues, 'info', 'taxonomy_added', sprintf( 'New taxonomy "%s" is now registered.', $name ) );
        }
        foreach ( array_intersect( array_keys( $base_tax ), array_keys( $now_tax ) ) as $name ) {
            $before = $base_tax[ $name ]; $after = $now_tax[ $name ];
            if ( ! empty( $before['hierarchical'] ) !== ! empty( $after['hierarchical'] ) ) {
                self::add_issue( $issues, 'critical', 'taxonomy_hierarchy_changed', sprintf( 'Taxonomy "%s" changed between hierarchical and flat.', $name ) );
            }
            if ( ! empty( $before['public'] ) !== ! empty( $after['public'] ) ) {
                self::add_issue( $issues, 'warning', 'taxonomy_visibility_changed', sprintf( 'Taxonomy "%s" changed public visibility.', $name ) );
            }
            foreach ( self::list_diff( $before['object_types'] ?? array(), $after['object_types'] ?? array() ) as $type ) {
                self::add_issue( $issues, 'critical', 'taxonomy_object_removed', sprintf( 'Taxonomy "%s" no longer applies to content type "%s".', $name, $type ) );
            }
            foreach ( self::list_diff( $after['object_types'] ?? array(), $before['object_types'] ?? array() ) as $type ) {
                self::add_issue( $issues, 'info', 'taxonomy_object_added', sprintf( 'Taxonomy "%s" now also applies to content type "%s".', $name, $type ) );
            }
        }

        $rank = array( 'critical' => 0, 'warning' => 1, 'info' => 2 );
        usort( $issues, function( $a, $b ) use ( $rank ) {
            $ra = isset( $rank[ $a['severity'] ] ) ? $rank[ $a['severity'] ] : 9;
            $rb = isset( $rank[ $b['severity'] ] ) ? $rank[ $b['severity'] ] : 9;
            if ( $ra === $rb ) { return strcasecmp( $a['message'], $b['message'] ); }
            return $ra < $rb ? -1 : 1;
        } );
        return $issues;
    }

    public static function status() {
        $baseline = get_option( self::OPTION, array() );
        $current  = self::structure_snapshot();
        if ( ! is_array( $baseline ) || empty( $baseline['structure'] ) ) {
            return array( 'has_baseline' => false, 'changed' => false, 'issues' => array(), 'current' => $current );
        }
        $issues = self::compare( $baseline['structure'], $current );
        $critical = 0; $warning = 0; $info = 0;
        foreach ( $issues as $issue ) {
            if ( 'critical' === $issue['severity'] ) { $critical++; }
            elseif ( 'warning' === $issue['severity'] ) { $warning++; }
            else { $info++; }
        }
        return array(
            'has_baseline' => true,
            'changed'      => ! empty( $issues ),
            'issues'       => $issues,
            'critical'     => $critical,
            'warning'      => $warning,
            'info'         => $info,
            'captured_at'  => absint( $baseline['captured_at'] ?? 0 ),
            'wordpress'    => sanitize_text_field( $baseline['wordpress'] ?? '' ),
            'plugin_version' => sanitize_text_field( $baseline['plugin_version'] ?? '' ),
            'current'      => $current,
        );
    }

    private function redirect( $notice ) {
        wp_safe_redirect( add_query_arg( 'ninecm_notice', sanitize_key( $notice ), admin_url( 'admin.php?page=nine-category-manager' ) ) );
        exit;
    }

    public function save_baseline() {
        if ( ! NineCM_Core::can_access_planner() || ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have permission to save the infrastructure baseline.', 'nine-category-manager' ), 403 ); }
        check_admin_referer( 'ninecm_save_infrastructure_baseline' );
        update_option( self::OPTION, self::capture_payload(), false );
        $this->redirect( 'baseline_saved' );
    }

    public function clear_baseline() {
        if ( ! NineCM_Core::can_access_planner() || ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You do not have permission to clear the infrastructure baseline.', 'nine-category-manager' ), 403 ); }
        check_admin_referer( 'ninecm_clear_infrastructure_baseline' );
        delete_option( self::OPTION );
        $this->redirect( 'baseline_cleared' );
    }

    public static function render_admin_card() {
        // A site-wide infrastructure baseline may reveal plugin/theme registration
        // details outside a limited editor's normal scope. Keep baseline inspection
        // and mutation administrator-only; ordinary editors still receive the
        // permission-scoped Site Infrastructure Map.
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $status = self::status();
        $can_manage = true;
        ?>
        <div class="ninecm-card ninecm-drift-card">
            <div class="ninecm-drift-head">
                <div>
                    <h2>Structure Drift Guard</h2>
                    <p>Detects silent infrastructure changes after plugin/theme updates or deactivation. It compares registered content types, taxonomy attachments, native planning supports and explicit relationship-provider keys. It stores no post content.</p>
                </div>
                <?php if ( empty( $status['has_baseline'] ) ) : ?>
                    <span class="ninecm-drift-badge is-neutral">No baseline</span>
                <?php elseif ( empty( $status['changed'] ) ) : ?>
                    <span class="ninecm-drift-badge is-good">No drift</span>
                <?php elseif ( ! empty( $status['critical'] ) ) : ?>
                    <span class="ninecm-drift-badge is-critical"><?php echo esc_html( $status['critical'] ); ?> critical</span>
                <?php else : ?>
                    <span class="ninecm-drift-badge is-warning">Changes found</span>
                <?php endif; ?>
            </div>

            <?php if ( empty( $status['has_baseline'] ) ) : ?>
                <p><strong>Recommended before heavy use:</strong> save the current site structure as your baseline. Future visits will flag missing or rewired infrastructure before you continue planning against an incomplete site.</p>
            <?php else : ?>
                <p class="ninecm-subtle">Baseline saved <?php echo esc_html( wp_date( 'Y-m-d H:i', $status['captured_at'] ) ); ?> · WordPress <?php echo esc_html( $status['wordpress'] ?: 'unknown' ); ?> · 9 Category Manager <?php echo esc_html( $status['plugin_version'] ?: 'unknown' ); ?></p>
                <?php if ( empty( $status['issues'] ) ) : ?>
                    <p><strong>Current infrastructure matches the saved baseline.</strong></p>
                <?php else : ?>
                    <div class="ninecm-drift-summary"><strong><?php echo esc_html( count( $status['issues'] ) ); ?> structural change(s)</strong> · <?php echo esc_html( $status['critical'] ); ?> critical · <?php echo esc_html( $status['warning'] ); ?> warning · <?php echo esc_html( $status['info'] ); ?> informational</div>
                    <ul class="ninecm-drift-list">
                    <?php foreach ( array_slice( $status['issues'], 0, 50 ) as $issue ) : ?>
                        <li class="is-<?php echo esc_attr( $issue['severity'] ); ?>"><strong><?php echo esc_html( strtoupper( $issue['severity'] ) ); ?></strong> <?php echo esc_html( $issue['message'] ); ?></li>
                    <?php endforeach; ?>
                    </ul>
                    <?php if ( count( $status['issues'] ) > 50 ) : ?><p class="ninecm-subtle">Only the first 50 changes are shown.</p><?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ( $can_manage ) : ?>
                <div class="ninecm-drift-actions">
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="ninecm_save_infrastructure_baseline">
                        <?php wp_nonce_field( 'ninecm_save_infrastructure_baseline' ); ?>
                        <button class="button button-primary" type="submit"><?php echo ! empty( $status['has_baseline'] ) ? 'Accept current structure as new baseline' : 'Save current structure as baseline'; ?></button>
                    </form>
                    <?php if ( ! empty( $status['has_baseline'] ) ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Clear the saved infrastructure baseline? This does not change site content.');">
                        <input type="hidden" name="action" value="ninecm_clear_infrastructure_baseline">
                        <?php wp_nonce_field( 'ninecm_clear_infrastructure_baseline' ); ?>
                        <button class="button" type="submit">Clear baseline</button>
                    </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
