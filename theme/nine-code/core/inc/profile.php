<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'show_user_profile', 'ncu_profile_contact_fields' );
add_action( 'edit_user_profile', 'ncu_profile_contact_fields' );
function ncu_profile_contact_fields( $user ) {
    ?>
    <h2><?php esc_html_e( 'Nine Code public contact', 'nine-code' ); ?></h2>
    <table class="form-table" role="presentation">
        <tr><th><label for="ncu_public_email">Public email</label></th><td><input class="regular-text" type="email" id="ncu_public_email" name="ncu_public_email" value="<?php echo esc_attr( get_user_meta( $user->ID, 'ncu_public_email', true ) ); ?>"><p class="description">Shown only when the theme's author contact action is enabled.</p></td></tr>
        <tr><th><label for="ncu_whatsapp">WhatsApp</label></th><td><input class="regular-text" type="text" id="ncu_whatsapp" name="ncu_whatsapp" value="<?php echo esc_attr( get_user_meta( $user->ID, 'ncu_whatsapp', true ) ); ?>" placeholder="2348012345678"></td></tr>
        <tr><th><label for="ncu_phone">Phone</label></th><td><input class="regular-text" type="text" id="ncu_phone" name="ncu_phone" value="<?php echo esc_attr( get_user_meta( $user->ID, 'ncu_phone', true ) ); ?>"></td></tr>
    </table>
    <?php
}

add_action( 'personal_options_update', 'ncu_save_profile_contact_fields' );
add_action( 'edit_user_profile_update', 'ncu_save_profile_contact_fields' );
function ncu_save_profile_contact_fields( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return false;
    }
    if ( isset( $_POST['ncu_public_email'] ) ) {
        update_user_meta( $user_id, 'ncu_public_email', sanitize_email( wp_unslash( $_POST['ncu_public_email'] ) ) );
    }
    if ( isset( $_POST['ncu_whatsapp'] ) ) {
        update_user_meta( $user_id, 'ncu_whatsapp', preg_replace( '/[^0-9+]/', '', sanitize_text_field( wp_unslash( $_POST['ncu_whatsapp'] ) ) ) );
    }
    if ( isset( $_POST['ncu_phone'] ) ) {
        update_user_meta( $user_id, 'ncu_phone', sanitize_text_field( wp_unslash( $_POST['ncu_phone'] ) ) );
    }
    return true;
}
