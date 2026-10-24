<?php
/**
 * Field protection policy: what Data Manager may show, what it may write, and what it must hide.
 *
 * Rules (in order):
 *  1. Secrets (passwords, tokens, hashes, keys, webhooks, session data) are never written and are
 *     redacted in every read, export and AI package.
 *  2. Structural keys owned by WordPress or another plugin (locks, attachment files, capabilities,
 *     builder data, SEO plugin internals, ACF reference keys…) are visible for diagnosis but read-only.
 *  3. Other private (underscore) keys are read-only unless their owner authorizes editing, through
 *     register_meta()'s auth_callback (checked with current_user_can( 'edit_{type}_meta' )) or a
 *     provider field definition.
 *  4. Public (non-underscore) custom fields are editable by anyone who can edit the object, exactly
 *     as in the native Custom Fields box.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class NCD_Policy {
	const PRIVATE_REASON = 'Private plugin data: read-only unless its owner authorizes editing';
	const REDACTED = '[hidden: protected value]';

	/** Keys that are always read-only regardless of object type. */
	private static function structural_patterns() {
		global $wpdb;
		$p = preg_quote( $wpdb->prefix, '/' );
		return array(
			'/^_edit_(lock|last)$/'                     => 'WordPress editing lock',
			'/^_wp_old_(slug|date)$/'                   => 'WordPress redirect history',
			'/^_wp_trash_meta_/'                        => 'WordPress trash bookkeeping',
			'/^_wp_desired_post_slug$/'                 => 'WordPress trash bookkeeping',
			'/^_wp_attached_file$/'                     => 'Media file path (managed by the Media Library)',
			'/^_wp_attachment_(metadata|backup_sizes)$/' => 'Media file metadata (managed by the Media Library)',
			'/^_wp_page_template$/'                     => 'Edit through the Page template field',
			'/^_thumbnail_id$/'                         => 'Edit through the Featured image field',
			'/^_(encloseme|pingme|trackbackme)$/'       => 'WordPress ping queue',
			'/^_menu_item_/'                            => 'Navigation menu internals',
			'/^_oembed_/'                               => 'oEmbed cache',
			'/^_elementor_/'                            => 'Owned by Elementor',
			'/^_yoast_|^_wpseo_|^rank_math_/'           => 'Owned by the SEO plugin',
			'/^_acf_changed$/'                          => 'ACF bookkeeping',
			'/^_npm9_|^_ncd_|^_ncu_render/'             => 'Nine Code internal state',
			'/^' . $p . '(capabilities|user_level|user-settings|user-settings-time|dashboard_quick_press_last_post_id)$/' => 'User role and permission data (privilege escalation guard)',
			'/^(wp_)?(capabilities|user_level)$/'       => 'User role and permission data (privilege escalation guard)',
			'/^(session_tokens|default_password_nag|dismissed_wp_pointers|community-events-location|use_ssl|admin_color|show_admin_bar_front|rich_editing|syntax_highlighting|comment_shortcuts|locale)$/' => 'WordPress account settings',
		);
	}

	public static function is_secret_key( $key ) {
		return (bool) preg_match( '/(pass(word)?|passwd|secret|token|api[_-]?key|private[_-]?key|access[_-]?key|_hash$|^_?hash_|nonce|salt|webhook|auth_code|otp|session_tokens|application_passwords)/i', (string) $key );
	}

	/**
	 * Classify a meta key.
	 *
	 * @param string $key       Meta key.
	 * @param string $meta_type post|user|term.
	 * @param array  $acf_names ACF field names that own their storage (handled as ACF fields).
	 * @return array{protected:bool,secret:bool,reason:string,acf:bool}
	 */
	public static function classify( $key, $meta_type = 'post', array $acf_names = array() ) {
		$key = (string) $key;
		$out = array( 'protected' => false, 'secret' => false, 'reason' => '', 'acf' => false );
		if ( self::is_secret_key( $key ) ) {
			return array( 'protected' => true, 'secret' => true, 'reason' => 'Secret value: never shown or exported', 'acf' => false );
		}
		// ACF storage first: a field's own name may start with any prefix, including protected ones.
		foreach ( $acf_names as $name ) {
			if ( $key === '_' . $name || 0 === strpos( $key, '_' . $name . '_' ) || preg_match( '/^_?' . preg_quote( $name, '/' ) . '_\d+_/', $key ) ) {
				return array( 'protected' => true, 'secret' => false, 'reason' => 'ACF storage (edit through the ACF field)', 'acf' => true );
			}
		}
		foreach ( self::structural_patterns() as $pattern => $reason ) {
			if ( preg_match( $pattern, $key ) ) { return array( 'protected' => true, 'secret' => false, 'reason' => $reason, 'acf' => false ); }
		}
		if ( 0 === strpos( $key, '_' ) ) {
			$out['protected'] = true;
			$out['reason']    = self::PRIVATE_REASON;
		}
		$filtered = apply_filters( 'ninecode_data_classify_meta', $out, $key, $meta_type );
		// Legacy (11.0 draft) filter: may only grant write access to non-secret, non-structural keys.
		if ( $filtered['protected'] && ! $filtered['secret'] && self::PRIVATE_REASON === $filtered['reason']
			&& apply_filters( 'ninecode_data_manager_writable_meta', false, $key ) ) {
			$filtered['protected'] = false;
			$filtered['reason']    = 'Authorized by its provider';
		}
		return array_merge( $out, (array) $filtered );
	}

	/** May the current user write this meta key on this object? Uses WordPress' own meta capability mapping. */
	public static function can_write_meta( $meta_type, $object_id, $key ) {
		$cap = array( 'post' => 'edit_post_meta', 'user' => 'edit_user_meta', 'term' => 'edit_term_meta' )[ $meta_type ] ?? '';
		return $cap && current_user_can( $cap, (int) $object_id, $key );
	}

	/** Redact a value for display/export when the field is secret. */
	public static function redact( array $field, $value ) {
		if ( ! empty( $field['secret'] ) ) {
			return ( '' === $value || null === $value || array() === $value ) ? '' : self::REDACTED;
		}
		return $value;
	}
}
