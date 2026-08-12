<?php
/**
 * Detect and expose the active SEO meta adapter.
 */

namespace Forwp\SeoHelper\SeoMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Registry {
	/** @var AdapterInterface|null */
	private static $active = null;

	/**
	 * @return list<AdapterInterface>
	 */
	public static function get_adapters(): array {
		return [
			new YoastAdapter(),
			new AioseoAdapter(),
		];
	}

	public static function get_active(): AdapterInterface {
		if ( null !== self::$active ) {
			return self::$active;
		}

		foreach ( self::get_adapters() as $adapter ) {
			if ( $adapter->is_available() ) {
				self::$active = $adapter;
				return self::$active;
			}
		}

		self::$active = new NullAdapter();
		return self::$active;
	}

	public static function reset(): void {
		self::$active = null;
	}
}
