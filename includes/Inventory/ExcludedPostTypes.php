<?php
/**
 * User-saved post types hidden from SEO inventory.
 */

namespace Forwp\SeoHelper\Inventory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ExcludedPostTypes {
	public const OPTION_KEY = 'forwp_seo_helper_inventory_excluded_post_types';

	/**
	 * @return list<string>
	 */
	public static function get(): array {
		$saved = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $saved ) ) {
			return [];
		}

		return array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $saved )
				)
			)
		);
	}

	/**
	 * @param list<string> $slugs
	 * @return list<string>
	 */
	public static function set( array $slugs ): array {
		$allowed = PostTypeDiscovery::get_discovered_slugs();
		$clean   = [];

		foreach ( $slugs as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' === $slug || ! in_array( $slug, $allowed, true ) ) {
				continue;
			}
			$clean[] = $slug;
		}

		$clean = array_values( array_unique( $clean ) );

		if ( [] !== $allowed && [] === array_diff( $allowed, $clean ) ) {
			$keep  = array_values( array_intersect( [ 'post', 'page' ], $allowed ) );
			$clean = array_values( array_diff( $clean, [] !== $keep ? $keep : [ $allowed[0] ] ) );
		}

		update_option( self::OPTION_KEY, $clean, false );
		PostTypeDiscovery::reset();

		return $clean;
	}

	public static function add( string $slug ): bool {
		$slug = sanitize_key( $slug );
		if ( '' === $slug ) {
			return false;
		}

		$current = self::get();
		if ( in_array( $slug, $current, true ) ) {
			return true;
		}

		self::set( array_merge( $current, [ $slug ] ) );

		return in_array( $slug, self::get(), true );
	}

	public static function remove( string $slug ): void {
		$slug = sanitize_key( $slug );
		self::set(
			array_values(
				array_filter(
					self::get(),
					static function ( string $item ) use ( $slug ): bool {
						return $item !== $slug;
					}
				)
			)
		);
	}
}
