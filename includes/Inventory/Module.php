<?php
/**
 * SEO inventory module bootstrap.
 */

namespace Forwp\SeoHelper\Inventory;

use Forwp\SeoHelper\Admin\InventoryExport;
use Forwp\SeoHelper\Admin\InventoryPage;
use Forwp\SeoHelper\Inventory\Rest\Cors;
use Forwp\SeoHelper\Inventory\Rest\InventoryRest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Module {
	public const OPTION_API_TOKEN = 'forwp_seo_helper_inventory_api_token';
	public const OPTION_ENABLED   = 'forwp_seo_helper_inventory_enabled';

	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate(): void {
		if ( '' === get_option( self::OPTION_API_TOKEN, '' ) ) {
			$token = wp_generate_password( 32, false, false );
			update_option( self::OPTION_API_TOKEN, $token );
		}

		if ( false === get_option( self::OPTION_ENABLED, false ) ) {
			update_option( self::OPTION_ENABLED, '1' );
		}

		HistorySchema::install();
	}

	private function __construct() {
		InventoryRest::get_instance();
		Cors::register();
		add_action( 'registered_post_type', [ PostTypeDiscovery::class, 'reset' ] );

		if ( ! HistorySchema::tables_exist() ) {
			HistorySchema::install();
		} else {
			HistorySchema::maybe_upgrade();
		}

		HistoryLogger::init();

		if ( is_admin() ) {
			InventoryExport::init();
			InventoryPage::init();
		}
	}

	public function is_enabled(): bool {
		return get_option( self::OPTION_ENABLED, '1' ) === '1';
	}

	public function get_api_token(): string {
		return (string) get_option( self::OPTION_API_TOKEN, '' );
	}

	public function regenerate_api_token(): string {
		$token = wp_generate_password( 32, false, false );
		update_option( self::OPTION_API_TOKEN, $token );
		return $token;
	}
}
