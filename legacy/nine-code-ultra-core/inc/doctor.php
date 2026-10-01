<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'rest_api_init', 'ncu_register_doctor_routes' );
function ncu_register_doctor_routes() {
    register_rest_route( 'ncu/v2', '/diagnose/(?P<id>\d+)', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'ncu_rest_doctor_diagnose',
        'permission_callback' => 'ncu_rest_can_edit_post',
        'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
    ) );
    register_rest_route( 'ncu/v2', '/repair/(?P<id>\d+)', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'ncu_rest_doctor_repair',
        'permission_callback' => 'ncu_rest_can_edit_post',
        'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
    ) );
}

function ncu_rest_doctor_diagnose( $request ) {
    return rest_ensure_response( ncu_doctor_build_package( absint( $request['id'] ) ) );
}

function ncu_rest_doctor_repair( $request ) {
    $post_id = absint( $request['id'] );
    $payload = $request->get_json_params();
    if ( ! is_array( $payload ) || '9-code-ultra-repair' !== ( isset( $payload['format'] ) ? $payload['format'] : '' ) ) {
        return new WP_Error( 'ncu_invalid_repair', 'Invalid 9Core 15 repair package.', array( 'status' => 400 ) );
    }
    if ( strlen( (string) wp_json_encode( $payload ) ) > 1048576 ) {
        return new WP_Error( 'ncu_repair_too_large', 'Repair packages must be 1 MB or smaller.', array( 'status' => 413 ) );
    }
    $result = ncu_doctor_apply_repair( $post_id, $payload );
    if ( is_wp_error( $result ) ) { return $result; }
    return rest_ensure_response( array( 'ok' => true, 'updated' => $result ) );
}

function ncu_doctor_build_package( $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post ) { return array( 'error' => 'Post not found.' ); }
    $theme = wp_get_theme();
    $blocks = parse_blocks( $post->post_content );
    $shortcodes = ncu_doctor_shortcode_map( $post->post_content );
    $block_map = ncu_doctor_block_map( $blocks );
    $hooks = ncu_doctor_hook_map( 'the_content' );
    $sensitive = current_user_can( 'manage_options' );
    if ( ! $sensitive ) {
        $shortcodes = ncu_doctor_redact_runtime_details( $shortcodes );
        $block_map  = ncu_doctor_redact_runtime_details( $block_map );
        $hooks      = ncu_doctor_redact_runtime_details( $hooks );
    }
    $plugins = $sensitive ? ncu_doctor_active_plugins() : array();
    $owners = array();
    foreach ( array_merge( $shortcodes, $block_map, $hooks ) as $item ) {
        if ( ! empty( $item['owner'] ) && ! in_array( $item['owner'], $owners, true ) ) { $owners[] = $item['owner']; }
    }
    return array(
        'format'       => '9-code-ultra-diagnosis',
        'schema'       => 2,
        'generated_at' => gmdate( 'c' ),
        'scope'        => 'page',
        'page'         => array(
            'id'                => $post_id,
            'type'              => $post->post_type,
            'status'            => $post->post_status,
            'title'             => get_the_title( $post_id ),
            'url'               => get_permalink( $post_id ),
            'template'          => get_page_template_slug( $post_id ),
            'renderer'          => function_exists( 'ncu_get_post_render_mode' ) ? ncu_get_post_render_mode( $post_id ) : 'auto',
            'elementor_mode'    => (string) get_post_meta( $post_id, '_elementor_edit_mode', true ),
            'has_blocks'        => has_blocks( $post->post_content ),
            'content_length'    => strlen( (string) $post->post_content ),
            'content_modified'  => $post->post_modified_gmt,
        ),
        'source_content' => substr( (string) $post->post_content, 0, 200000 ),
        'source_content_truncated' => strlen( (string) $post->post_content ) > 200000,
        'ai_builder'    => function_exists( 'ncu_get_ai_config' ) ? ncu_get_ai_config( $post_id ) : array(),
        'structure'     => function_exists( 'ncu_builder_block_outline' ) ? ncu_builder_block_outline( $blocks ) : array(),
        'block_runtime' => $block_map,
        'shortcodes'    => $shortcodes,
        'content_hooks' => $hooks,
        'owners_seen'   => $owners,
        'active_plugins'=> $plugins,
        'recent_error_log' => $sensitive ? ncu_doctor_recent_error_log() : array(),
        'diagnostic_access' => $sensitive ? 'administrator-detail' : 'editor-safe',
        'repair_contract' => array(
            'format' => '9-code-ultra-repair',
            'schema' => 2,
            'allowed_actions' => array( 'set_renderer', 'set_ai_builder', 'set_page_css', 'clear_page_css', 'clear_ai_overrides' ),
            'renderer_values' => array( 'auto', 'ai', 'gutenberg', 'elementor' ),
            'boundary' => 'Presentation-only repair. Do not return PHP, JavaScript, shell commands, remote CSS imports or arbitrary WordPress option changes.',
        ),
        'stack'         => array(
            'wordpress' => get_bloginfo( 'version' ),
            'php'       => PHP_VERSION,
            'theme'     => array( 'name' => $theme->get( 'Name' ), 'version' => $theme->get( 'Version' ), 'template' => $theme->get_template() ),
            'core'      => array( 'version' => NCU_CORE_VERSION, 'api' => NCU_CORE_API_VERSION ),
            'theme_api' => defined( 'NCU_THEME_API_VERSION' ) ? NCU_THEME_API_VERSION : 0,
            'memory_limit' => ini_get( 'memory_limit' ),
            'wp_debug'  => defined( 'WP_DEBUG' ) && WP_DEBUG,
        ),
        'diagnostic_notes' => array(
            'This package maps blocks, shortcodes and callbacks touching the_content to their callable/file owner when reflection can resolve them.',
            'An owner appearing here is evidence of participation in the page runtime, not automatic proof that it caused the fault.',
            'Use a PHP fatal/error log entry when available to prove an exact crashing line. Administrators receive recent debug-log evidence when WordPress logging is enabled and readable.',
            'Repair import is intentionally limited to renderer/presentation controls and page-scoped CSS; executable code is rejected.',
        ),
    );
}

function ncu_doctor_recent_error_log() {
    if ( ! current_user_can( 'manage_options' ) ) { return array(); }
    $path = '';
    if ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) && WP_DEBUG_LOG ) {
        $path = WP_DEBUG_LOG;
    } elseif ( defined( 'WP_CONTENT_DIR' ) ) {
        $path = trailingslashit( WP_CONTENT_DIR ) . 'debug.log';
    }
    if ( ! $path || ! is_readable( $path ) || ! is_file( $path ) ) { return array(); }
    $size = @filesize( $path );
    if ( false === $size || $size <= 0 ) { return array(); }
    $read = min( 262144, (int) $size );
    $fh = @fopen( $path, 'rb' );
    if ( ! $fh ) { return array(); }
    if ( $size > $read ) { @fseek( $fh, -$read, SEEK_END ); }
    $chunk = (string) @fread( $fh, $read );
    @fclose( $fh );
    if ( '' === $chunk ) { return array(); }
    $lines = preg_split( '/\r\n|\r|\n/', $chunk );
    $matches = array();
    foreach ( $lines as $line ) {
        if ( ! preg_match( '/(?:PHP\s+(?:Fatal error|Parse error|Warning|Notice|Deprecated)|Uncaught\s+|Stack trace:|#[0-9]+\s+)/i', $line ) ) { continue; }
        $line = wp_strip_all_tags( $line );
        $line = str_replace( wp_normalize_path( ABSPATH ), '[ABSPATH]/', wp_normalize_path( $line ) );
        $matches[] = substr( $line, 0, 2000 );
    }
    return array_slice( $matches, -40 );
}

function ncu_doctor_redact_runtime_details( $items ) {
    if ( ! is_array( $items ) ) { return array(); }
    foreach ( $items as &$item ) {
        if ( ! is_array( $item ) ) { continue; }
        unset( $item['file'], $item['line'], $item['reflection_error'] );
    }
    unset( $item );
    return $items;
}

function ncu_doctor_active_plugins() {
    if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
    $all = function_exists( 'get_plugins' ) ? get_plugins() : array();
    $active = (array) get_option( 'active_plugins', array() );
    if ( is_multisite() ) {
        $network = get_site_option( 'active_sitewide_plugins', array() );
        if ( is_array( $network ) ) { $active = array_unique( array_merge( $active, array_keys( $network ) ) ); }
    }
    $out = array();
    foreach ( $active as $file ) {
        $data = isset( $all[ $file ] ) ? $all[ $file ] : array();
        $out[] = array(
            'file'    => sanitize_text_field( $file ),
            'name'    => isset( $data['Name'] ) ? sanitize_text_field( $data['Name'] ) : basename( dirname( $file ) ),
            'version' => isset( $data['Version'] ) ? sanitize_text_field( $data['Version'] ) : '',
        );
    }
    return $out;
}

function ncu_doctor_shortcode_map( $content ) {
    global $shortcode_tags;
    $out = array();
    if ( ! is_array( $shortcode_tags ) || ! $shortcode_tags || ! is_string( $content ) ) { return $out; }
    preg_match_all( '/' . get_shortcode_regex() . '/', $content, $matches, PREG_SET_ORDER );
    $seen = array();
    foreach ( $matches as $match ) {
        $tag = isset( $match[2] ) ? $match[2] : '';
        if ( ! $tag || isset( $seen[ $tag ] ) ) { continue; }
        $seen[ $tag ] = true;
        $callback = isset( $shortcode_tags[ $tag ] ) ? $shortcode_tags[ $tag ] : null;
        $info = ncu_doctor_callback_info( $callback );
        $out[] = array_merge( array( 'tag' => $tag ), $info );
    }
    return $out;
}

function ncu_doctor_block_map( $blocks, &$seen = array(), $depth = 0 ) {
    $out = array();
    if ( ! is_array( $blocks ) || $depth > 10 ) { return $out; }
    foreach ( $blocks as $block ) {
        $name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
        if ( $name && empty( $seen[ $name ] ) ) {
            $seen[ $name ] = true;
            $item = array( 'block' => $name, 'dynamic' => false, 'owner' => ncu_doctor_owner_from_namespace( $name ) );
            if ( class_exists( 'WP_Block_Type_Registry' ) ) {
                $type = WP_Block_Type_Registry::get_instance()->get_registered( $name );
                if ( $type && ! empty( $type->render_callback ) ) {
                    $item['dynamic'] = true;
                    $item = array_merge( $item, ncu_doctor_callback_info( $type->render_callback ) );
                }
            }
            $out[] = $item;
        }
        if ( ! empty( $block['innerBlocks'] ) ) { $out = array_merge( $out, ncu_doctor_block_map( $block['innerBlocks'], $seen, $depth + 1 ) ); }
    }
    return $out;
}

function ncu_doctor_owner_from_namespace( $block_name ) {
    $parts = explode( '/', (string) $block_name, 2 );
    if ( empty( $parts[0] ) || 'core' === $parts[0] ) { return 'WordPress Core'; }
    return $parts[0];
}

function ncu_doctor_hook_map( $hook_name ) {
    global $wp_filter;
    $out = array();
    if ( empty( $wp_filter[ $hook_name ] ) || ! is_object( $wp_filter[ $hook_name ] ) || empty( $wp_filter[ $hook_name ]->callbacks ) ) { return $out; }
    foreach ( $wp_filter[ $hook_name ]->callbacks as $priority => $callbacks ) {
        foreach ( $callbacks as $entry ) {
            if ( empty( $entry['function'] ) ) { continue; }
            $info = ncu_doctor_callback_info( $entry['function'] );
            $info['priority'] = (int) $priority;
            $out[] = $info;
        }
    }
    return $out;
}

function ncu_doctor_callback_info( $callback ) {
    $info = array( 'callback' => ncu_doctor_callback_name( $callback ), 'file' => '', 'line' => 0, 'owner' => '' );
    try {
        if ( is_string( $callback ) && function_exists( $callback ) ) {
            $ref = new ReflectionFunction( $callback );
        } elseif ( is_array( $callback ) && 2 === count( $callback ) ) {
            $ref = new ReflectionMethod( $callback[0], $callback[1] );
        } elseif ( $callback instanceof Closure ) {
            $ref = new ReflectionFunction( $callback );
        } elseif ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
            $ref = new ReflectionMethod( $callback, '__invoke' );
        } else {
            return $info;
        }
        $file = $ref->getFileName();
        if ( $file ) {
            $info['file'] = ncu_doctor_relative_path( $file );
            $info['line'] = (int) $ref->getStartLine();
            $info['owner'] = ncu_doctor_owner_from_file( $file );
        } else {
            $info['owner'] = 'PHP/WordPress internal';
        }
    } catch ( Throwable $e ) {
        $info['reflection_error'] = sanitize_text_field( $e->getMessage() );
    }
    return $info;
}

function ncu_doctor_callback_name( $callback ) {
    if ( is_string( $callback ) ) { return $callback; }
    if ( is_array( $callback ) && 2 === count( $callback ) ) {
        $class = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
        return $class . '::' . (string) $callback[1];
    }
    if ( $callback instanceof Closure ) { return 'Closure'; }
    if ( is_object( $callback ) ) { return get_class( $callback ) . '::__invoke'; }
    return 'Unknown callback';
}

function ncu_doctor_relative_path( $file ) {
    $file = wp_normalize_path( (string) $file );
    $root = wp_normalize_path( ABSPATH );
    if ( 0 === strpos( $file, $root ) ) { return ltrim( substr( $file, strlen( $root ) ), '/' ); }
    return basename( $file );
}

function ncu_doctor_owner_from_file( $file ) {
    $file = wp_normalize_path( (string) $file );
    $plugins = defined( 'WP_PLUGIN_DIR' ) ? wp_normalize_path( WP_PLUGIN_DIR ) . '/' : '';
    $themes = defined( 'WP_CONTENT_DIR' ) ? wp_normalize_path( WP_CONTENT_DIR ) . '/themes/' : '';
    if ( $plugins && 0 === strpos( $file, $plugins ) ) {
        $relative = substr( $file, strlen( $plugins ) );
        $parts = explode( '/', $relative );
        return 'Plugin: ' . ( isset( $parts[0] ) ? $parts[0] : 'unknown' );
    }
    if ( $themes && 0 === strpos( $file, $themes ) ) {
        $relative = substr( $file, strlen( $themes ) );
        $parts = explode( '/', $relative );
        return 'Theme: ' . ( isset( $parts[0] ) ? $parts[0] : 'unknown' );
    }
    if ( 0 === strpos( $file, wp_normalize_path( ABSPATH ) ) ) { return 'WordPress Core'; }
    return 'Unknown runtime owner';
}

function ncu_doctor_apply_repair( $post_id, $payload ) {
    if ( ! current_user_can( 'edit_post', $post_id ) ) { return new WP_Error( 'ncu_forbidden', 'Permission denied.', array( 'status' => 403 ) ); }
    $updated = array();
    $actions = isset( $payload['actions'] ) && is_array( $payload['actions'] ) ? $payload['actions'] : array();
    foreach ( $actions as $action ) {
        if ( ! is_array( $action ) || empty( $action['type'] ) ) { continue; }
        switch ( $action['type'] ) {
            case 'set_renderer':
                $mode = ncu_sanitize_builder_meta( isset( $action['value'] ) ? $action['value'] : '', 'ncu_render_mode' );
                if ( $mode ) { update_post_meta( $post_id, 'ncu_render_mode', $mode ); $updated[] = 'renderer'; }
                break;
            case 'set_ai_builder':
                $ai_payload = array( 'format' => '9-code-ultra-ai-page', 'renderer' => ncu_get_post_render_mode( $post_id ), 'ai_builder' => isset( $action['value'] ) && is_array( $action['value'] ) ? $action['value'] : array() );
                $updated = array_merge( $updated, ncu_apply_ai_package( $post_id, $ai_payload ) );
                break;
            case 'set_page_css':
                $css = ncu_sanitize_page_css( isset( $action['value'] ) ? $action['value'] : '' );
                update_post_meta( $post_id, 'ncu_page_css', $css );
                $updated[] = 'page_css';
                break;
            case 'clear_page_css':
                delete_post_meta( $post_id, 'ncu_page_css' );
                $updated[] = 'page_css_cleared';
                break;
            case 'clear_ai_overrides':
                foreach ( array( 'ncu_ai_preset', 'ncu_ai_content_width', 'ncu_ai_radius', 'ncu_ai_spacing_scale', 'ncu_ai_font_scale', 'ncu_ai_background', 'ncu_ai_text_color', 'ncu_ai_accent_color', 'ncu_ai_hidden_sections', 'ncu_ai_section_order', 'ncu_page_css' ) as $meta_key ) { delete_post_meta( $post_id, $meta_key ); }
                $updated[] = 'ai_overrides_cleared';
                break;
        }
    }
    return array_values( array_unique( $updated ) );
}

add_action( 'admin_post_ncu_export_page_diagnosis', 'ncu_admin_export_page_diagnosis' );
function ncu_admin_export_page_diagnosis() {
    $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
    if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) { wp_die( 'Permission denied.' ); }
    check_admin_referer( 'ncu_export_page_diagnosis_' . $post_id );
    $payload = ncu_doctor_build_package( $post_id );
    ncu_send_json_download( '9code-doctor-page-' . $post_id . '-' . gmdate( 'Ymd-His' ) . '.json', $payload );
}

add_action( 'admin_post_ncu_import_page_repair', 'ncu_admin_import_page_repair' );
function ncu_admin_import_page_repair() {
    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) { wp_die( esc_html__( 'Permission denied.', 'nine-code-ultra-core' ) ); }
    check_admin_referer( 'ncu_import_page_repair_' . $post_id );
    if ( empty( $_FILES['ncu_repair']['tmp_name'] ) || ! isset( $_FILES['ncu_repair']['error'] ) || UPLOAD_ERR_OK !== (int) $_FILES['ncu_repair']['error'] ) {
        wp_die( esc_html__( 'The repair file could not be uploaded.', 'nine-code-ultra-core' ) );
    }
    if ( ! empty( $_FILES['ncu_repair']['size'] ) && (int) $_FILES['ncu_repair']['size'] > 1048576 ) {
        wp_die( esc_html__( 'Repair packages must be 1 MB or smaller.', 'nine-code-ultra-core' ) );
    }
    $raw = file_get_contents( $_FILES['ncu_repair']['tmp_name'] );
    $payload = json_decode( (string) $raw, true );
    if ( ! is_array( $payload ) || '9-code-ultra-repair' !== ( isset( $payload['format'] ) ? $payload['format'] : '' ) ) {
        wp_die( esc_html__( 'This is not a valid 9Core 15 repair package.', 'nine-code-ultra-core' ) );
    }
    $result = ncu_doctor_apply_repair( $post_id, $payload );
    if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
    if ( function_exists( 'ncu_notice_push' ) ) { ncu_notice_push( 'Safe repair applied. Updated ' . count( $result ) . ' presentation setting(s).', 'success', 'doctor-repair' ); }
    wp_safe_redirect( add_query_arg( array( 'page' => 'nine-code-ultra-doctor', 'post_id' => $post_id ), admin_url( 'admin.php' ) ) );
    exit;
}

function ncu_core_doctor_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
    ?>
    <div class="wrap ncu-admin"><?php ncu_admin_header( 'Doctor', 'Diagnose one page, one section or the whole 9Core 15 stack without installing another diagnostics plugin.' ); ?>
    <div class="ncu-doctor-grid">
        <section class="ncu-panel ncu-panel--padded"><h2>Page Doctor</h2><p>Enter a page/post ID. The diagnosis maps the page renderer, block callbacks, shortcodes, <code>the_content</code> callbacks, active plugins and probable runtime owners.</p>
        <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"><input type="hidden" name="page" value="nine-code-ultra-doctor"><label><strong>Post/Page ID</strong><input type="number" name="post_id" min="1" value="<?php echo esc_attr( $post_id ); ?>" required></label> <button class="button button-primary">Inspect</button></form>
        <?php if ( $post_id && current_user_can( 'edit_post', $post_id ) ) : $pkg = ncu_doctor_build_package( $post_id ); ?>
            <div class="ncu-doctor-summary"><p><strong><?php echo esc_html( get_the_title( $post_id ) ); ?></strong></p><p>Renderer: <code><?php echo esc_html( $pkg['page']['renderer'] ); ?></code> · Type: <code><?php echo esc_html( $pkg['page']['type'] ); ?></code></p><p>Runtime owners detected: <?php echo esc_html( $pkg['owners_seen'] ? implode( ', ', $pkg['owners_seen'] ) : 'No non-core owner resolved from content callbacks.' ); ?></p></div>
            <p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ncu_export_page_diagnosis&post_id=' . $post_id ), 'ncu_export_page_diagnosis_' . $post_id ) ); ?>">Download page diagnosis</a> <a class="button" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" target="_blank" rel="noopener">Open front-end Doctor</a></p>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Apply this safe 9Core 15 repair package to this page?');">
                <input type="hidden" name="action" value="ncu_import_page_repair"><input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>"><?php wp_nonce_field( 'ncu_import_page_repair_' . $post_id ); ?>
                <label><strong>Upload AI repair JSON</strong><br><input type="file" name="ncu_repair" accept="application/json,.json" required></label> <button class="button button-secondary">Validate & apply safe repair</button>
            </form>
        <?php endif; ?>
        </section>
        <section class="ncu-panel ncu-panel--padded"><h2>System Doctor</h2><p>Environment-level export for theme/core versions and server basics.</p><p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ncu_export_diagnostics' ), 'ncu_export_diagnostics' ) ); ?>">Download system diagnostics</a></p><h3>How section diagnosis works</h3><p>When logged in as an editor/admin, open the front end. The black <strong>Doctor</strong> tab appears at the top-left. Choose <strong>Select section</strong>, click the problem section, then download a section package containing its DOM, computed layout data and loaded page resources.</p></section>
    </div>
    <section class="ncu-panel ncu-panel--padded"><h2>Safe AI repair package</h2><p>AI can return a <code>9-code-ultra-repair</code> JSON package. The importer accepts only renderer changes, AI Builder presentation controls, page-scoped CSS, and clearing of those overrides. It cannot execute PHP, JavaScript, shell commands or arbitrary WordPress option changes.</p><pre>{
  "format": "9-code-ultra-repair",
  "schema": 2,
  "actions": [
    {"type":"set_renderer","value":"ai"},
    {"type":"set_page_css","value":".example { margin-top: 0; }"}
  ]
}</pre></section>
    </div>
    <?php
}

add_action( 'wp_enqueue_scripts', 'ncu_enqueue_front_doctor', 99 );
function ncu_enqueue_front_doctor() {
    if ( is_admin() || ! is_singular() ) { return; }
    $settings = ncu_get_settings();
    if ( empty( $settings['doctor_frontend_enabled'] ) ) { return; }
    $post_id = get_queried_object_id();
    if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) { return; }
    /* Public pages are content surfaces, not diagnostics surfaces. An
     * intentional developer integration may opt in, but the Doctor launcher
     * never appears merely because an administrator is logged in. */
    if ( ! apply_filters( 'ncu_frontend_doctor_enabled', false, $post_id, $settings ) ) { return; }
    wp_enqueue_style( 'ncu-front-doctor', NCU_CORE_URL . 'assets/css/front-doctor.css', array(), NCU_CORE_VERSION );
    wp_enqueue_script( 'ncu-front-doctor', NCU_CORE_URL . 'assets/js/front-doctor.js', array(), NCU_CORE_VERSION, true );
    wp_localize_script( 'ncu-front-doctor', 'NCU_DOCTOR', array(
        'postId'      => $post_id,
        'fileBase'    => '9code-' . ( sanitize_title( get_the_title( $post_id ) ) ? sanitize_title( get_the_title( $post_id ) ) : get_post_type( $post_id ) ) . '-' . $post_id,
        'nonce'       => wp_create_nonce( 'wp_rest' ),
        'diagnoseUrl' => rest_url( 'ncu/v2/diagnose/' . $post_id ),
        'repairUrl'   => rest_url( 'ncu/v2/repair/' . $post_id ),
        'adminUrl'    => admin_url( 'admin.php?page=nine-code-ultra-doctor&post_id=' . $post_id ),
        'renderer'    => ncu_get_post_render_mode( $post_id ),
    ) );
}
