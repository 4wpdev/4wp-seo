<?php
/**
 * Public module scope per release (wp.org 1.0.0 = SEO Inventory only).
 *
 * Code for other modules stays in the plugin; flip {@see WPORG_1_0_0_MODULES}
 * or use the `forwp_seo_public_modules` filter on internal sites.
 */

namespace Forwp\SeoHelper\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Release {
	public const MODULE_INVENTORY     = 'inventory';
	public const MODULE_INVENTORY_API = 'inventory_api';
	public const MODULE_TECHARTICLE   = 'techarticle';
	public const MODULE_GSC           = 'gsc';
	public const MODULE_LLMS          = 'llms';
	public const MODULE_CROSSPOSTING  = 'crossposting';

	/**
	 * Modules exposed in the WordPress.org 1.0.0 release.
	 *
	 * @var list<string>
	 */
	private const WPORG_1_0_0_MODULES = [
		self::MODULE_INVENTORY,
		self::MODULE_INVENTORY_API,
		self::MODULE_GSC,
	];

	/**
	 * @return list<string>
	 */
	public static function get_public_modules(): array {
		/**
		 * Override public modules on internal / staging sites.
		 *
		 * Example — enable everything:
		 * add_filter( 'forwp_seo_public_modules', fn () => [
		 *     Release::MODULE_INVENTORY,
		 *     Release::MODULE_INVENTORY_API,
		 *     Release::MODULE_TECHARTICLE,
		 *     Release::MODULE_GSC,
		 *     Release::MODULE_LLMS,
		 *     Release::MODULE_CROSSPOSTING,
		 * ] );
		 *
		 * @param list<string> $modules Module slugs from MODULE_* constants.
		 */
		return apply_filters( 'forwp_seo_public_modules', self::WPORG_1_0_0_MODULES );
	}

	public static function is_module_public( string $module ): bool {
		return in_array( $module, self::get_public_modules(), true );
	}

	/**
	 * Gutenberg block supports when a module is not public (keep render, hide inserter).
	 *
	 * @return array<string, bool>
	 */
	public static function block_inserter_supports(): array {
		return [
			'inserter' => self::is_module_public( self::MODULE_TECHARTICLE ),
		];
	}
}
