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
					return '' !== $type && post_type_exists( $type );
				}
			)
		);

		return self::$cached;
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
