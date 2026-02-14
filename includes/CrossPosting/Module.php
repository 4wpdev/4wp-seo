<?php
/**
 * Cross posting module toggle and hooks.
 */

namespace Forwp\Seo\CrossPosting;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Module {
	public const OPTION_ENABLED = 'forwp_seo_crossposting_enabled';

	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', [ $this, 'register_rest' ] );
	}

	public function is_enabled(): bool {
		return get_option( self::OPTION_ENABLED, '0' ) === '1';
	}

	public function register_rest(): void {
		Rest::get_instance();
	}
}


