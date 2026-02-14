<?php
/**
 * TechArticle Steps block registration.
 */

namespace Forwp\Seo\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TechArticleSteps {
	public const BLOCK_NAME = 'forwp-seo/techarticle-steps';
	public const STEP_BLOCK_NAME = 'forwp-seo/techarticle-step';

	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', [ $this, 'register_block' ] );
	}

	public function register_block(): void {
		wp_register_script(
			'forwp-seo-techarticle-steps',
			FORWP_SEO_URL . 'assets/js/techarticle-steps.js',
			[ 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n' ],
			FORWP_SEO_VERSION,
			true
		);

		register_block_type(
			self::BLOCK_NAME,
			[
				'editor_script'   => 'forwp-seo-techarticle-steps',
				'render_callback' => [ $this, 'render_steps_block' ],
			]
		);

		register_block_type(
			self::STEP_BLOCK_NAME,
			[
				'editor_script'   => 'forwp-seo-techarticle-steps',
				'render_callback' => [ $this, 'render_step_block' ],
			]
		);
	}

	public function render_steps_block( array $attributes, string $content ): string {
		if ( '' === trim( $content ) ) {
			return '';
		}

		return '<div class="forwp-seo-techarticle-steps">' . $content . '</div>';
	}

	public function render_step_block( array $attributes, string $content ): string {
		if ( '' === trim( $content ) ) {
			return '';
		}

		return '<div class="forwp-seo-techarticle-step">' . $content . '</div>';
	}
}


