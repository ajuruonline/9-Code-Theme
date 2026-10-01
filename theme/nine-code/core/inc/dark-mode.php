<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Dark-mode palette registry used by the back-end control plane.
 */
function ncu_dark_palette_registry() {
    return array(
        'auto'     => array( 'label' => 'Auto / Accent-derived', 'bg' => '#141618', 'surface' => '#1d2023', 'raised' => '#262a2e', 'accent' => '#9fa6ad' ),
        'charcoal' => array( 'label' => 'Soft Charcoal',        'bg' => '#151719', 'surface' => '#1e2124', 'raised' => '#292d31', 'accent' => '#c7cbd0' ),
        'forest'   => array( 'label' => 'Deep Forest',          'bg' => '#101713', 'surface' => '#17211b', 'raised' => '#203027', 'accent' => '#79d39a' ),
        'emerald'  => array( 'label' => 'Dark Emerald',         'bg' => '#0e1715', 'surface' => '#15221f', 'raised' => '#1d302b', 'accent' => '#62d6b1' ),
        'navy'     => array( 'label' => 'Midnight Graphite',    'bg' => '#141414', 'surface' => '#1d1d1d', 'raised' => '#292929', 'accent' => '#d4d4d4' ),
        'indigo'   => array( 'label' => 'Deep Graphite',        'bg' => '#111111', 'surface' => '#1b1b1b', 'raised' => '#2a2a2a', 'accent' => '#c7c7c7' ),
        'purple'   => array( 'label' => 'Velvet Purple',        'bg' => '#17131d', 'surface' => '#211a2a', 'raised' => '#30243d', 'accent' => '#c99af5' ),
        'burgundy' => array( 'label' => 'Dark Burgundy',        'bg' => '#1a1215', 'surface' => '#25191e', 'raised' => '#35232a', 'accent' => '#ef9eb5' ),
        'brown'    => array( 'label' => 'Warm Espresso',        'bg' => '#181411', 'surface' => '#221c18', 'raised' => '#312822', 'accent' => '#deb287' ),
        'slate'    => array( 'label' => 'Cool Slate',           'bg' => '#151719', 'surface' => '#1e2022', 'raised' => '#2a2d30', 'accent' => '#c3c8cc' ),
    );
}

function ncu_dark_palette_is_valid( $key ) {
    return isset( ncu_dark_palette_registry()[ $key ] );
}

add_action( 'admin_post_ncu_save_dark_mode', 'ncu_save_dark_mode_settings' );
function ncu_save_dark_mode_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to change dark mode.', 'nine-code-ultra-core' ) );
    }
    check_admin_referer( 'ncu_save_dark_mode' );
    $raw = isset( $_POST['ncu'] ) && is_array( $_POST['ncu'] ) ? wp_unslash( $_POST['ncu'] ) : array();
    $keys = array(
        'dark_mode_enabled', 'dark_toggle_enabled', 'dark_floating_toggle', 'dark_use_site_accent',
        'dark_mode_default', 'dark_palette', 'dark_accent_override', 'dark_toggle_position',
    );
    $booleans = array( 'dark_mode_enabled', 'dark_toggle_enabled', 'dark_floating_toggle', 'dark_use_site_accent' );
    $result = ncu_save_settings_subset( $raw, $keys, $booleans );
    ncu_redirect_after_settings_save( 'nine-code-ultra-dark-mode', $result, '9Core 15 dark mode saved and verified.' );
}

function ncu_dark_mode_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $s = ncu_get_settings();
    $palettes = ncu_dark_palette_registry();
    $style_taking_over = ! empty( $s['aggressive_style_takeover'] );
    $style_tokens = function_exists( 'ncu_get_effective_design_tokens' ) ? ncu_get_effective_design_tokens( $s ) : array();
    ?>
    <div class="wrap ncu-admin">
        <?php ncu_admin_header( 'Admin Dark Mode', 'A WordPress back-end workspace preference. It never changes the public site.' ); ?>
        <?php if ( $style_taking_over ) : ?>
            <div class="notice notice-info inline ncu-style-dark-notice"><p><strong>Matched dark companion active.</strong> Aggressive Style Takeover is using the dark companion generated for <strong><?php echo esc_html( isset( $style_tokens['family_label'], $style_tokens['label'] ) ? $style_tokens['family_label'] . ' · ' . $style_tokens['label'] : 'the selected style' ); ?></strong>. The generic palette below remains saved and becomes active again when Style Takeover is switched off.</p></div>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="ncu_save_dark_mode">
            <?php wp_nonce_field( 'ncu_save_dark_mode' ); ?>

            <section class="ncu-panel ncu-panel--padded">
                <h2>Behaviour</h2>
                <div class="ncu-checks">
                    <?php ncu_checkbox( 'dark_mode_enabled', 'Enable 9Core 15 admin dark mode', $s ); ?>
                    <?php ncu_checkbox( 'dark_toggle_enabled', 'Show the dark-mode button in the WordPress top bar', $s ); ?>
                    <?php ncu_checkbox( 'dark_use_site_accent', 'Inherit the site/plugin accent automatically', $s ); ?>
                </div>
                <div class="ncu-grid">
                    <label><span>Initial admin appearance</span><select name="ncu[dark_mode_default]"><option value="dark" <?php selected( $s['dark_mode_default'], 'dark' ); ?>>Dark / dim</option><option value="light" <?php selected( $s['dark_mode_default'], 'light' ); ?>>Light</option><option value="system" <?php selected( $s['dark_mode_default'], 'system' ); ?>>Follow device setting</option></select></label>
                    <label><span>Optional dark accent override</span><input type="color" name="ncu[dark_accent_override]" value="<?php echo esc_attr( $s['dark_accent_override'] ? $s['dark_accent_override'] : $s['accent_color'] ); ?>"><small>Leave Auto selected below to derive the dark family from your normal site/plugin accent. This override is useful when the dark accent needs a different hue.</small></label>
                </div>
                <p class="description">The top-bar button saves its choice only in this browser. Public pages, visitor preferences and front-end plugin styles are not affected.</p>
            </section>

            <section class="ncu-panel ncu-panel--padded">
                <h2>Dark palette</h2>
                <p>Each palette contains several coordinated dark surfaces. None uses pitch black as the normal page background.</p>
                <div class="ncu-dark-palette-grid">
                    <?php foreach ( $palettes as $key => $palette ) : ?>
                        <label class="ncu-dark-palette-card <?php echo $s['dark_palette'] === $key ? 'is-selected' : ''; ?>">
                            <span class="ncu-dark-palette-radio"><input type="radio" name="ncu[dark_palette]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $s['dark_palette'], $key ); ?>> <strong><?php echo esc_html( $palette['label'] ); ?></strong></span>
                            <span class="ncu-dark-swatches" aria-hidden="true">
                                <i style="background:<?php echo esc_attr( $palette['bg'] ); ?>"></i>
                                <i style="background:<?php echo esc_attr( $palette['surface'] ); ?>"></i>
                                <i style="background:<?php echo esc_attr( $palette['raised'] ); ?>"></i>
                                <i style="background:<?php echo esc_attr( $palette['accent'] ); ?>"></i>
                            </span>
                            <span class="ncu-dark-preview" style="background:<?php echo esc_attr( $palette['bg'] ); ?>;color:#eef1f4;border-color:<?php echo esc_attr( $palette['raised'] ); ?>"><b style="color:<?php echo esc_attr( $palette['accent'] ); ?>">Heading</b><span>Soft readable body text</span><em style="background:<?php echo esc_attr( $palette['surface'] ); ?>">Raised surface</em></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="ncu-panel ncu-panel--padded">
                <h2>Design protection</h2>
                <div class="ncu-audit-list">
                    <p><strong>Surface hierarchy:</strong> page, card, raised and interactive surfaces use different tonal depths.</p>
                    <p><strong>Plugin accent bridge:</strong> plugins can provide <code>ncu_dark_mode_accent_color</code> or the CSS variable <code>--ncu-plugin-accent</code>; otherwise the site accent is used.</p>
                    <p><strong>Media safety:</strong> photos, diagrams, video and iframe embeds retain their original colour.</p>
                    <p><strong>Accessibility:</strong> primary text remains near-white rather than pure white; muted text and borders stay distinguishable without glare.</p>
                    <p><strong>Flash prevention:</strong> the preferred mode is applied before page paint.</p>
                </div>
            </section>
            <p class="submit"><button class="button button-primary button-hero">Save dark mode</button></p>
        </form>
    </div>
    <?php
}
