<?php
/**
 * Editor sidebar assets.
 */

namespace Forwp\Seo\Admin;

use Forwp\Seo\CrossPosting\Module as CrossPostingModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Editor {
	private static $instance = null;
	private static $enqueued = false;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets_fallback' ] );
	}

	public function enqueue_assets(): void {
		if ( self::$enqueued ) {
			return;
		}
		self::$enqueued = true;

		$settings = [
			'nonce'              => wp_create_nonce( 'wp_rest' ),
			'baseUrl'            => esc_url_raw( rest_url( 'forwp-seo/v1' ) ),
			'crosspostingEnabled' => CrossPostingModule::get_instance()->is_enabled(),
		];

		$deps = [ 'wp-plugins', 'wp-edit-post', 'wp-data', 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch', 'wp-blocks' ];
		$handle = 'forwp-seo-editor-sidebar';

		wp_register_script( $handle, '', $deps, FORWP_SEO_VERSION, true );
		wp_enqueue_script( $handle );

		wp_add_inline_script(
			$handle,
			'window.forwpSeoSidebar = ' . wp_json_encode( $settings ) . ';',
			'before'
		);

		$script_path = FORWP_SEO_PATH . 'assets/js/seo-sidebar.js';
		if ( file_exists( $script_path ) ) {
			$inline = file_get_contents( $script_path );
			if ( $inline ) {
				wp_add_inline_script( $handle, $inline, 'after' );
				return;
			}
		}

		wp_add_inline_script( $handle, "console.warn('[forwp-seo] seo-sidebar.js missing');", 'after' );
	}

	public function enqueue_assets_fallback( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || empty( $screen->is_block_editor ) ) {
			return;
		}

		$this->enqueue_assets();
	}
}


