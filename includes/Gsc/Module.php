<?php
/**
 * Google Search Console module toggle and bootstrap.
 */

namespace Forwp\SeoHelper\Gsc;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Module {
	public const OPTION_ENABLED      = 'forwp_seo_gsc_enabled';
	public const OPTION_CRON_ENABLED = 'forwp_seo_gsc_cron_enabled';
	public const OPTION_LOCAL_DEV_MODE = 'forwp_seo_gsc_local_dev_mode';

	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate(): void {
		Schema::install();
	}

	public static function deactivate(): void {
		Sync::unschedule_cron();
		Sync::unschedule_manual_syncs();
	}

	private function __construct() {
		Sync::get_instance();

		if ( ! Schema::tables_exist() ) {
			Schema::install();
		} else {
			Schema::maybe_upgrade();
		}

		Sync::sync_cron_state( $this->is_cron_enabled() );
	}

	public function is_enabled(): bool {
		return get_option( self::OPTION_ENABLED, '0' ) === '1';
	}

	public function is_cron_enabled(): bool {
		return get_option( self::OPTION_CRON_ENABLED, '0' ) === '1';
	}

	public function is_local_dev_mode(): bool {
		return get_option( self::OPTION_LOCAL_DEV_MODE, '0' ) === '1';
	}
}
