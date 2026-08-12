<?php
/**
 * Detect and expose the active multilingual provider.
 */

namespace Forwp\SeoHelper\Multilingual;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Registry {
	/** @var ProviderInterface|null */
	private static $active = null;

	/**
	 * @return list<ProviderInterface>
	 */
	public static function get_providers(): array {
		return [
			new PolylangProvider(),
			new WpmlProvider(),
		];
	}

	public static function get_active(): ProviderInterface {
		if ( null !== self::$active ) {
			return self::$active;
		}

		foreach ( self::get_providers() as $provider ) {
			if ( $provider->is_available() ) {
				self::$active = $provider;
				return self::$active;
			}
		}

		self::$active = new SingleSiteProvider();
		return self::$active;
	}

	public static function reset(): void {
		self::$active = null;
	}
}
