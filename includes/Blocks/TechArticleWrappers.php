<?php
/**
 * TechArticle section wrappers — any inner blocks, Schema.org semantics (4wp-faq pattern).
 */

namespace Forwp\Seo\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TechArticleWrappers {
	public const GOAL_BLOCK    = 'forwp-seo/techarticle-goal';
	public const CONTEXT_BLOCK = 'forwp-seo/techarticle-context';
	public const ISSUES_BLOCK  = 'forwp-seo/techarticle-issues';
	public const STEPS_BLOCK   = 'forwp-seo/techarticle-steps';

	/** @deprecated Use STEPS_BLOCK — kept for schema/sidebar compatibility. */
	public const STEP_BLOCK = 'forwp-seo/techarticle-step';

	private const SCRIPT_HANDLE = 'forwp-seo-techarticle-wrappers';

	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', [ $this, 'register_blocks' ], 9 );
	}

	public function register_blocks(): void {
		wp_register_script(
			self::SCRIPT_HANDLE,
			FORWP_SEO_URL . 'assets/js/techarticle-wrappers.js',
			[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n' ],
			FORWP_SEO_VERSION,
			true
		);

		$wrappers = [
			self::GOAL_BLOCK    => 'forwp-seo-techarticle-goal',
			self::CONTEXT_BLOCK => 'forwp-seo-techarticle-context',
			self::ISSUES_BLOCK  => 'forwp-seo-techarticle-issues',
			self::STEPS_BLOCK   => 'forwp-seo-techarticle-steps',
		];

		foreach ( $wrappers as $block_name => $class_name ) {
			register_block_type(
				$block_name,
				[
					'api_version'     => 3,
					'editor_script'   => self::SCRIPT_HANDLE,
					'render_callback' => function ( array $attributes, string $content, \WP_Block $block ) use ( $class_name ): string {
						return $this->render_wrapper( $class_name, $content, $attributes, 'section', $block );
					},
				]
			);
		}

		// Legacy inner step wrapper — optional inside Steps; still parsed for schema.
		register_block_type(
			self::STEP_BLOCK,
			[
				'api_version'     => 3,
				'editor_script'   => self::SCRIPT_HANDLE,
				'attributes'      => [
					'stepNumber' => [
						'type' => 'number',
					],
				],
				'render_callback' => function ( array $attributes, string $content, \WP_Block $block ): string {
					return $this->render_wrapper( 'forwp-seo-techarticle-step', $content, $attributes, 'div', $block );
				},
			]
		);
	}

	private function render_wrapper( string $class_name, string $content, array $attributes, string $tag, ?\WP_Block $block = null ): string {
		if ( '' === trim( $content ) && $block instanceof \WP_Block && ! empty( $block->inner_blocks ) ) {
			$content = $block->inner_blocks->render();
		}

		if ( '' === trim( $content ) ) {
			return '';
		}

		$extra = '';
		if ( ! empty( $attributes['className'] ) && is_string( $attributes['className'] ) ) {
			$extra = ' ' . trim( $attributes['className'] );
		}

		$data_attrs = '';
		if ( isset( $attributes['stepNumber'] ) && is_numeric( $attributes['stepNumber'] ) ) {
			$data_attrs = sprintf( ' data-step="%d"', (int) $attributes['stepNumber'] );
		}

		return sprintf(
			'<%1$s class="%2$s%3$s"%4$s>%5$s</%1$s>',
			$tag,
			esc_attr( $class_name ),
			esc_attr( $extra ),
			$data_attrs,
			$content
		);
	}
}
