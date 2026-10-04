<?php
/**
 * Generic WordPress discovery ("wordpress" provider).
 *
 * Makes every compatible plugin visible before it writes a dedicated adapter:
 * - every post type with an admin UI or public URL (minus WordPress internals);
 * - every taxonomy with a UI, as its own term entity;
 * - users;
 * - core fields, taxonomies, featured image, registered meta, ACF field groups (nested) and
 *   unregistered meta found in the database (private keys read-only, secrets redacted).
 *
 * Post types/taxonomies claimed by a dedicated provider are skipped here so each object has one owner.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class NCD_Generic_Provider {
	const ID = 'wordpress';

	/** WordPress internals that are not content. */
	const SKIP_POST_TYPES = array( 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation', 'wp_font_family', 'wp_font_face', 'acf-field-group', 'acf-field', 'acf-post-type', 'acf-taxonomy', 'acf-ui-options-page' );
	const SKIP_TAXONOMIES = array( 'nav_menu', 'link_category', 'post_format', 'wp_theme', 'wp_template_part_area', 'wp_pattern_category' );

	public static function register() {
		$claimed_types = NCD_Registry::claimed_objects( 'post' );
		$claimed_taxes = NCD_Registry::claimed_objects( 'term' );
		$entities      = array();

		foreach ( get_post_types( array(), 'objects' ) as $name => $object ) {
			if ( in_array( $name, self::SKIP_POST_TYPES, true ) || isset( $claimed_types[ $name ] ) ) { continue; }
			if ( empty( $object->show_ui ) && empty( $object->public ) ) { continue; }
			$entities[ 'type-' . $name ] = self::post_entity_definition( $name );
		}
		foreach ( get_taxonomies( array(), 'objects' ) as $name => $tax ) {
			if ( in_array( $name, self::SKIP_TAXONOMIES, true ) || isset( $claimed_taxes[ $name ] ) || empty( $tax->show_ui ) ) { continue; }
			$entities[ 'tax-' . $name ] = self::term_entity_definition( $name );
		}
		$entities['users'] = self::user_entity_definition();

		NCD_Registry::register( self::ID, array(
			'label'       => 'WordPress & plugins (auto-discovered)',
			'version'     => get_bloginfo( 'version' ),
			'owner'       => 'wordpress',
			'adapter'     => 'generic',
			'description' => 'Every registered post type, taxonomy and user field, discovered automatically.',
			'entities'    => $entities,
		) );
	}

	/* ----------------------------------------------------------------- posts */

	public static function post_entity_definition( $post_type ) {
		$object = get_post_type_object( $post_type );
		$fields = array();
		$fields['post_title'] = array( 'label' => 'Title', 'type' => 'text', 'storage' => 'post_field', 'group' => 'core', 'origin' => 'core' );
		if ( post_type_supports( $post_type, 'editor' ) ) {
			$fields['post_content'] = array( 'label' => 'Content', 'type' => 'html', 'storage' => 'post_field', 'group' => 'core', 'origin' => 'core' );
		}
		if ( post_type_supports( $post_type, 'excerpt' ) || 'attachment' === $post_type ) {
			$fields['post_excerpt'] = array( 'label' => 'attachment' === $post_type ? 'Caption' : 'Excerpt', 'type' => 'textarea', 'storage' => 'post_field', 'group' => 'core', 'origin' => 'core' );
		}
		if ( 'attachment' !== $post_type ) {
			$fields['post_status'] = array( 'label' => 'Status', 'type' => 'status', 'storage' => 'post_field', 'group' => 'publishing', 'origin' => 'core',
				'choices' => array( 'draft' => 'Draft', 'pending' => 'Pending review', 'private' => 'Private', 'publish' => 'Published', 'future' => 'Scheduled' ),
				'help' => 'Changing the status requires the explicit "Allow status / publishing changes" permission.' );
			$fields['post_name'] = array( 'label' => 'Slug', 'type' => 'text', 'storage' => 'post_field', 'group' => 'publishing', 'origin' => 'core', 'sanitize_callback' => static function ( $v ) { return array( sanitize_title( (string) $v ), '' ); } );
			$fields['post_date'] = array( 'label' => 'Publish date', 'type' => 'datetime', 'storage' => 'post_field', 'group' => 'publishing', 'origin' => 'core' );
		}
		if ( post_type_supports( $post_type, 'author' ) ) {
			$fields['post_author'] = array( 'label' => 'Author', 'type' => 'user', 'storage' => 'post_field', 'group' => 'publishing', 'origin' => 'core' );
		}
		if ( $object && $object->hierarchical ) {
			$fields['post_parent'] = array( 'label' => 'Parent', 'type' => 'post', 'storage' => 'post_field', 'target' => $post_type, 'group' => 'publishing', 'origin' => 'core' );
		}
		if ( post_type_supports( $post_type, 'page-attributes' ) ) {
			$fields['menu_order'] = array( 'label' => 'Order', 'type' => 'integer', 'storage' => 'post_field', 'group' => 'publishing', 'origin' => 'core' );
			$templates = wp_get_theme()->get_page_templates( null, $post_type );
			if ( $templates ) {
				$fields['page_template'] = array( 'label' => 'Template', 'type' => 'select', 'storage' => 'post_field', 'group' => 'publishing', 'origin' => 'core', 'choices' => array( '' => 'Default' ) + $templates );
			}
		}
		if ( post_type_supports( $post_type, 'thumbnail' ) ) {
			$fields['featured_image'] = array( 'label' => 'Featured image', 'type' => 'media', 'storage' => 'thumbnail', 'source' => '_thumbnail_id', 'group' => 'media', 'origin' => 'core' );
		}
		if ( post_type_supports( $post_type, 'comments' ) ) {
			$fields['comment_status'] = array( 'label' => 'Comments', 'type' => 'select', 'storage' => 'post_field', 'group' => 'publishing', 'origin' => 'core', 'choices' => array( 'open' => 'Open', 'closed' => 'Closed' ) );
		}
		if ( 'attachment' === $post_type ) {
			$fields['alt_text'] = array( 'label' => 'Alternative text', 'type' => 'text', 'storage' => 'meta', 'source' => '_wp_attachment_image_alt', 'group' => 'core', 'origin' => 'core' );
		}
		foreach ( get_object_taxonomies( $post_type, 'objects' ) as $tax_name => $tax ) {
			if ( in_array( $tax_name, self::SKIP_TAXONOMIES, true ) ) { continue; }
			$fields[ 'tax_' . $tax_name ] = array( 'label' => $tax->labels->name, 'type' => 'term_list', 'storage' => 'taxonomy', 'source' => $tax_name, 'target' => $tax_name, 'group' => 'taxonomies', 'origin' => 'taxonomy',
				'writable' => ! empty( $tax->show_ui ), 'reason' => empty( $tax->show_ui ) ? 'Managed by its plugin (no admin UI)' : '' );
		}

		$acf_fields = self::acf_fields( array( 'post_type' => $post_type ) );
		$fields     = array_merge( $fields, $acf_fields['fields'] );
		$fields     = array_merge( $fields, self::meta_fields( 'post', $post_type, $acf_fields['names'], array_keys( $fields ) ) );

		return array(
			'label'       => $object ? $object->labels->name : $post_type,
			'singular'    => $object ? $object->labels->singular_name : $post_type,
			'kind'        => 'post',
			'object_type' => $post_type,
			'description' => $object && $object->description ? $object->description : '',
			'fields'      => $fields,
			'statuses'    => 'attachment' === $post_type ? array( 'inherit' ) : array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'allow_create'=> 'attachment' !== $post_type,
			'allow_trash' => 'attachment' !== $post_type,
			'columns'     => array_values( array_intersect( array( 'post_title', 'post_status', 'post_date', 'post_author' ), array_keys( $fields ) ) ),
		);
	}

	/* ----------------------------------------------------------------- terms */

	public static function term_entity_definition( $taxonomy ) {
		$tax    = get_taxonomy( $taxonomy );
		$fields = array(
			'name'        => array( 'label' => 'Name', 'type' => 'text', 'storage' => 'term_field', 'group' => 'core', 'origin' => 'core', 'required' => true ),
			'slug'        => array( 'label' => 'Slug', 'type' => 'text', 'storage' => 'term_field', 'group' => 'core', 'origin' => 'core' ),
			'description' => array( 'label' => 'Description', 'type' => 'textarea', 'storage' => 'term_field', 'group' => 'core', 'origin' => 'core' ),
		);
		if ( $tax && $tax->hierarchical ) {
			$fields['parent'] = array( 'label' => 'Parent', 'type' => 'term', 'target' => $taxonomy, 'storage' => 'term_field', 'group' => 'core', 'origin' => 'core' );
		}
		$acf    = self::acf_fields( array( 'taxonomy' => $taxonomy ) );
		$fields = array_merge( $fields, $acf['fields'], self::meta_fields( 'term', $taxonomy, $acf['names'], array() ) );
		return array(
			'label'        => $tax ? $tax->labels->name : $taxonomy,
			'singular'     => $tax ? $tax->labels->singular_name : $taxonomy,
			'kind'         => 'term',
			'object_type'  => $taxonomy,
			'fields'       => $fields,
			'statuses'     => array(),
			'allow_create' => true,
			'allow_trash'  => false,
			'columns'      => array( 'name', 'slug' ),
		);
	}

	/* ----------------------------------------------------------------- users */

	public static function user_entity_definition() {
		$fields = array(
			'display_name' => array( 'label' => 'Display name', 'type' => 'text', 'storage' => 'user_field', 'group' => 'core', 'origin' => 'core' ),
			'first_name'   => array( 'label' => 'First name', 'type' => 'text', 'storage' => 'user_field', 'group' => 'core', 'origin' => 'core' ),
			'last_name'    => array( 'label' => 'Last name', 'type' => 'text', 'storage' => 'user_field', 'group' => 'core', 'origin' => 'core' ),
			'nickname'     => array( 'label' => 'Nickname', 'type' => 'text', 'storage' => 'user_field', 'group' => 'core', 'origin' => 'core' ),
			'user_email'   => array( 'label' => 'Email', 'type' => 'email', 'storage' => 'user_field', 'group' => 'core', 'origin' => 'core' ),
			'user_url'     => array( 'label' => 'Website', 'type' => 'url', 'storage' => 'user_field', 'group' => 'core', 'origin' => 'core' ),
			'description'  => array( 'label' => 'Biographical info', 'type' => 'textarea', 'storage' => 'user_field', 'group' => 'core', 'origin' => 'core' ),
			'roles'        => array( 'label' => 'Roles', 'type' => 'readonly', 'storage' => 'user_field', 'group' => 'core', 'origin' => 'core', 'protected' => true, 'reason' => 'Change roles on the Users screen (privilege escalation guard)' ),
		);
		$acf    = self::acf_fields( array( 'user_form' => 'all' ) );
		$fields = array_merge( $fields, $acf['fields'], self::meta_fields( 'user', '', $acf['names'], array( 'first_name', 'last_name', 'nickname', 'description' ) ) );
		return array(
			'label'        => 'Users',
			'singular'     => 'User',
			'kind'         => 'user',
			'object_type'  => 'user',
			'fields'       => $fields,
			'statuses'     => array(),
			'allow_create' => false,
			'allow_trash'  => false,
			'columns'      => array( 'display_name', 'user_email', 'roles' ),
		);
	}

	/* ----------------------------------------------------------------- meta */

	/**
	 * Registered meta + meta keys actually present in the database for this object subtype.
	 *
	 * @param string $meta_type post|term|user
	 * @param string $subtype   post type / taxonomy / '' for users.
	 */
	public static function meta_fields( $meta_type, $subtype, array $acf_names, array $skip ) {
		$fields     = array();
		$registered = array_merge( get_registered_meta_keys( $meta_type ), $subtype ? get_registered_meta_keys( $meta_type, $subtype ) : array() );
		$keys       = array_unique( array_merge( array_keys( $registered ), self::discovered_meta_keys( $meta_type, $subtype ) ) );
		sort( $keys );
		foreach ( $keys as $key ) {
			if ( in_array( $key, $skip, true ) || in_array( $key, $acf_names, true ) ) { continue; }
			if ( '_wp_attachment_image_alt' === $key || '_thumbnail_id' === $key ) { continue; }
			$class = NCD_Policy::classify( $key, $meta_type, $acf_names );
			if ( $class['acf'] ) { continue; } // ACF storage is edited through the ACF field itself.
			$def      = $registered[ $key ] ?? null;
			$protected = $class['protected'];
			// A private key its owner registered with an auth_callback and exposed to the REST API is editable:
			// WordPress routes every write through edit_{type}_meta, which runs that auth_callback per record.
			// Structural and secret keys stay locked whatever the registration says.
			if ( $protected && ! $class['secret'] && NCD_Policy::PRIVATE_REASON === $class['reason'] && $def && ! empty( $def['auth_callback'] ) && ! empty( $def['show_in_rest'] ) ) {
				$protected       = false;
				$class['reason'] = '';
			}
			$type = 'text';
			if ( $def ) {
				$type = array( 'integer' => 'integer', 'number' => 'number', 'boolean' => 'boolean', 'array' => 'json', 'object' => 'json' )[ $def['type'] ?? 'string' ] ?? 'text';
			}
			$fields[ 'meta:' . $key ] = array(
				'label'    => self::humanize( $key ),
				'type'     => $type,
				'storage'  => 'meta',
				'source'   => $key,
				'single'   => $def ? ! empty( $def['single'] ) : true,
				'group'    => $protected ? 'protected' : 'meta',
				'origin'   => $def ? 'registered-meta' : 'discovered-meta',
				'help'     => $def && ! empty( $def['description'] ) ? (string) $def['description'] : '',
				'protected'=> $protected,
				'secret'   => $class['secret'],
				'reason'   => $protected ? $class['reason'] : '',
			);
		}
		return $fields;
	}

	/** Meta keys present for this subtype (sampled, cached for 10 minutes). */
	public static function discovered_meta_keys( $meta_type, $subtype ) {
		global $wpdb;
		$cache_key = 'ncd_meta_keys_' . md5( $meta_type . '|' . $subtype . '|' . wp_cache_get_last_changed( 'posts' ) . wp_cache_get_last_changed( 'terms' ) . wp_cache_get_last_changed( 'users' ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) { return $cached; }
		$limit = (int) apply_filters( 'ninecode_data_meta_discovery_limit', 400 );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- discovery scan, cached in a transient above.
		if ( 'post' === $meta_type ) {
			$keys = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pm.meta_key FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = %s LIMIT %d", $subtype, $limit ) );
		} elseif ( 'term' === $meta_type ) {
			$keys = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT tm.meta_key FROM {$wpdb->termmeta} tm INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id WHERE tt.taxonomy = %s LIMIT %d", $subtype, $limit ) );
		} else {
			$keys = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_key FROM {$wpdb->usermeta} LIMIT %d", $limit ) );
		}
		// phpcs:enable
		$keys = array_values( array_filter( array_map( 'strval', (array) $keys ) ) );
		set_transient( $cache_key, $keys, 10 * MINUTE_IN_SECONDS );
		return $keys;
	}

	public static function humanize( $key ) {
		return ucwords( trim( str_replace( array( '_', '-' ), ' ', $key ) ) );
	}

	/* ----------------------------------------------------------------- ACF */

	/**
	 * ACF/SCF field groups matching a location, converted to contract fields (recursively).
	 *
	 * @return array{fields:array,names:string[]}
	 */
	public static function acf_fields( array $location ) {
		$out = array( 'fields' => array(), 'names' => array() );
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) { return $out; }
		foreach ( (array) acf_get_field_groups( $location ) as $group ) {
			foreach ( (array) acf_get_fields( $group ) as $field ) {
				if ( empty( $field['name'] ) || in_array( $field['type'], array( 'tab', 'message', 'accordion' ), true ) ) { continue; }
				$out['fields'][ 'acf:' . $field['name'] ] = self::acf_to_field( $field, $group['title'] ?? 'ACF' );
				$out['names'][] = $field['name'];
			}
		}
		return $out;
	}

	public static function acf_to_field( array $field, $group_title = 'ACF' ) {
		$map = array(
			'text' => 'text', 'textarea' => 'textarea', 'wysiwyg' => 'html', 'number' => 'number', 'range' => 'number', 'email' => 'email', 'url' => 'url', 'password' => 'readonly',
			'date_picker' => 'date', 'date_time_picker' => 'datetime', 'time_picker' => 'text', 'color_picker' => 'text', 'oembed' => 'url',
			'select' => 'select', 'radio' => 'select', 'button_group' => 'select', 'checkbox' => 'multiselect', 'true_false' => 'boolean',
			'image' => 'media', 'file' => 'media', 'gallery' => 'media_list',
			'post_object' => 'post', 'page_link' => 'post', 'relationship' => 'post_list', 'taxonomy' => 'term_list', 'user' => 'user',
			'group' => 'group', 'repeater' => 'repeater', 'flexible_content' => 'flexible', 'link' => 'json', 'google_map' => 'json',
		);
		$type = $map[ $field['type'] ] ?? 'text';
		if ( 'select' === $type && ! empty( $field['multiple'] ) ) { $type = 'multiselect'; }
		if ( 'post' === $type && ! empty( $field['multiple'] ) ) { $type = 'post_list'; }
		if ( 'user' === $type && ! empty( $field['multiple'] ) ) { $type = 'user_list'; }
		if ( 'term_list' === $type && in_array( $field['field_type'] ?? '', array( 'select', 'radio' ), true ) ) { $type = 'term'; }
		$def = array(
			'label'    => $field['label'] ?: $field['name'],
			'type'     => $type,
			'storage'  => 'acf',
			'source'   => $field['key'],
			'group'    => 'acf: ' . $group_title,
			'origin'   => 'acf',
			'help'     => (string) ( $field['instructions'] ?? '' ),
			'required' => ! empty( $field['required'] ),
			'choices'  => isset( $field['choices'] ) && is_array( $field['choices'] ) ? $field['choices'] : array(),
			'target'   => is_array( $field['post_type'] ?? null ) && 1 === count( $field['post_type'] ) ? (string) reset( $field['post_type'] ) : (string) ( $field['taxonomy'] ?? '' ),
			'min'      => isset( $field['min'] ) && '' !== $field['min'] ? (int) $field['min'] : null,
			'max'      => isset( $field['max'] ) && '' !== $field['max'] ? (int) $field['max'] : null,
		);
		if ( 'password' === $field['type'] ) { $def['secret'] = true; $def['protected'] = true; $def['reason'] = 'Password field'; }
		if ( 'readonly' === $type ) { $def['protected'] = true; }
		if ( in_array( $type, array( 'group', 'repeater' ), true ) ) {
			$def['sub_fields'] = array();
			foreach ( (array) ( $field['sub_fields'] ?? array() ) as $sub ) {
				if ( empty( $sub['name'] ) ) { continue; }
				$def['sub_fields'][ $sub['name'] ] = array( 'key' => $sub['name'] ) + self::acf_to_field( $sub, $group_title );
			}
		}
		if ( 'flexible' === $type ) {
			$def['layouts'] = array();
			foreach ( (array) ( $field['layouts'] ?? array() ) as $layout ) {
				$subs = array();
				foreach ( (array) ( $layout['sub_fields'] ?? array() ) as $sub ) { if ( ! empty( $sub['name'] ) ) { $subs[ $sub['name'] ] = array( 'key' => $sub['name'] ) + self::acf_to_field( $sub, $group_title ); } }
				$def['layouts'][ $layout['name'] ] = array( 'name' => $layout['name'], 'label' => $layout['label'] ?? $layout['name'], 'sub_fields' => $subs );
			}
		}
		return $def;
	}
}
