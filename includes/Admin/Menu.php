<?php
/**
 * Admin menu registration.
 */

namespace Forwp\Seo\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Menu {
	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
	}

	public function register_menu(): void {
		add_menu_page(
			'4wp SEO',
			'4wp SEO',
			'manage_options',
			'4wp-seo',
			[ Page::class, 'render' ],
			'dashicons-chart-line',
			30
		);
	}
}



















