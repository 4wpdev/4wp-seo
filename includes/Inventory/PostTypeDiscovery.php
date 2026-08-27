<?php
/**
 * Discover public CPTs for SEO inventory.
 */

namespace Forwp\SeoHelper\Inventory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PostTypeDiscovery {
	/** @var list<string>|null */
	private static ?array $cached = null;

	/**
	 * @return list<string>
	 */
	public static function get_slugs(): array {
		if ( null !== self::$cached ) {
			return self::$cached;
		}

		$types = get_post_types(
			[
				'public'  => true,
				'show_ui' => true,
			],
			'names'
		);

		$exclude = apply_filters(
			'forwp_seo_inventory_exclude_post_types',
			[
				'attachment',
				'elementor_library',
				'elementor_snippet',
				'wp_navigation',
				'wp_template',
				'wp_template_part',
				'wp_global_styles',
				'wp_block',
				'custom_css',
				'customize_changeset',
				'nav_menu_item',
				'oembed_cache',
				'revision',
				'user_request',
			]
		);

		$types = array_values(
			array_diff(
				array_map( 'sanitize_key', array_values( (array) $types ) ),
				array_map( 'sanitize_key', (array) $exclude )
			)
		);

		$types = apply_filters( 'forwp_seo_inventory_post_types', $types );

		self::$cached = array_values(
			array_filter(
				array_map( 'sanitize_key', (array) $types ),
				static function ( string $type ): bool {
					if ( '' === $type || ! post_type_exists( $type ) ) {
						return false;
					}

					$object = get_post_type_object( $type );

					return $object instanceof \WP_Post_Type && self::is_publicly_viewable( $object );
				}
			)
		);

		return self::$cached;
	}

	/**
	 * Post types that belong in SEO inventory (expected public front-end URL).
	 */
	private static function is_publicly_viewable( \WP_Post_Type $object ): bool {
		// Core content types — always inventory candidates when public in admin.
		if ( in_array( $object->name, [ 'post', 'page' ], true ) ) {
			return true;
		}

		if ( ! $object->public || ! $object->show_ui ) {
			return false;
		}

		$viewable = (bool) $object->publicly_queryable;

		// Some themes/plugins register CPTs with rewrite slugs but publicly_queryable=false.
		if ( ! $viewable && is_array( $object->rewrite ) && ! empty( $object->rewrite['slug'] ) ) {
			$viewable = true;
		}

		/**
		 * Whether a post type belongs in SEO inventory.
		 *
		 * @param bool          $viewable Default heuristic above.
		 * @param \WP_Post_Type $object   Post type object.
		 */
		return (bool) apply_filters(
			'forwp_seo_inventory_post_type_is_viewable',
			$viewable,
			$object
		);
	}

	/**
	 * @return list<array{slug: string, label: string}>
	 */
	public static function get_labeled(): array {
		$labeled = [];

		foreach ( self::get_slugs() as $slug ) {
			$object = get_post_type_object( $slug );
			$labeled[] = [
				'slug'  => $slug,
				'label' => $object instanceof \WP_Post_Type ? $object->labels->singular_name : $slug,
			];
		}

		return $labeled;
	}

	public static function reset(): void {
		self::$cached = null;
	}
}
