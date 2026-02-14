<?php
/**
 * TechArticle schema handling.
 */

namespace Forwp\Seo\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TechArticle {
	public const META_KEY = '_forwp_seo_techarticle_enabled';
	private const LEGACY_META_KEY = '_4wp_seo_techarticle_enabled';
	public const STEPS_BLOCK = 'forwp-seo/techarticle-steps';
	public const STEP_BLOCK = 'forwp-seo/techarticle-step';

	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp', [ $this, 'maybe_disable_advanced_code_schema' ], 1 );
		add_action( 'wp_head', [ $this, 'output_schema' ], 1 );
	}

	public function maybe_disable_advanced_code_schema(): void {
		$post = get_post();
		if ( ! $post || ! $this->should_output_schema( $post ) ) {
			return;
		}

		if ( class_exists( '\ForWP\Bundle\SeoHandler' ) ) {
			remove_action( 'wp_head', [ '\ForWP\Bundle\SeoHandler', 'outputJsonLd' ], 1 );
		}
	}

	public function output_schema(): void {
		$post = get_post();
		if ( ! $post || ! $this->should_output_schema( $post ) ) {
			return;
		}

		$schema = $this->build_schema( $post );
		if ( empty( $schema ) ) {
			return;
		}

		echo '<script type="application/ld+json">';
		echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		echo '</script>' . PHP_EOL;
	}

	private function should_output_schema( \WP_Post $post ): bool {
		if ( ! is_singular() ) {
			return false;
		}

		return $this->is_post_valid( $post );
	}

	public function is_post_valid( \WP_Post $post ): bool {
		$enabled = $this->is_enabled_for_post( $post->ID );
		if ( ! $enabled ) {
			return false;
		}

		$blocks = parse_blocks( $post->post_content );
		$flat   = $this->flatten_blocks( $blocks );

		return $this->has_required_blocks( $flat );
	}

	public function is_enabled_for_post( int $post_id ): bool {
		$value = get_post_meta( $post_id, self::META_KEY, true );
		if ( $value !== '' ) {
			return (bool) $value;
		}

		return (bool) get_post_meta( $post_id, self::LEGACY_META_KEY, true );
	}

	private function has_required_blocks( array $blocks ): bool {
		$has_code  = false;
		$has_steps = false;

		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? '';
			if ( 'core/code' === $name ) {
				$has_code = true;
			}
			if ( self::STEPS_BLOCK === $name || self::STEP_BLOCK === $name ) {
				$has_steps = true;
			}
		}

		return $has_code && $has_steps;
	}

	private function build_schema( \WP_Post $post ): array {
		$blocks = parse_blocks( $post->post_content );
		$flat   = $this->flatten_blocks( $blocks );

		$software_code = $this->extract_software_code( $flat );
		$steps         = $this->extract_steps( $flat );

		if ( empty( $software_code ) || empty( $steps ) ) {
			return [];
		}

		$author = get_userdata( $post->post_author );
		$schema = [
			'@context' => 'https://schema.org',
			'@type'    => 'TechArticle',
			'headline' => get_the_title( $post->ID ),
			'author'   => $author
				? [
					'@type' => 'Person',
					'name'  => $author->display_name,
				]
				: null,
			'softwareCode' => $software_code,
			'hasPart'      => [
				[
					'@type' => 'HowTo',
					'step'  => $steps,
				],
			],
			'about' => $this->build_about( $post ),
		];

		if ( empty( $schema['author'] ) ) {
			unset( $schema['author'] );
		}
		if ( empty( $schema['about'] ) ) {
			unset( $schema['about'] );
		}

		return $schema;
	}

	private function extract_software_code( array $blocks ): array {
		$output = [];
		foreach ( $blocks as $block ) {
			if ( ( $block['blockName'] ?? '' ) !== 'core/code' ) {
				continue;
			}

			$code = $this->extract_code_text( $block );
			if ( '' === $code ) {
				continue;
			}

			$language = $block['attrs']['language'] ?? 'auto';

			$item = [
				'@type'            => 'SoftwareSourceCode',
				'codeSampleType'   => 'full',
				'programmingLanguage' => $language,
				'text'             => $code,
			];

			$output[] = $item;
		}

		return $output;
	}

	private function extract_steps( array $blocks ): array {
		$steps = [];
		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? '';

			if ( $name === self::STEPS_BLOCK && ! empty( $block['attrs']['steps'] ) ) {
				foreach ( (array) $block['attrs']['steps'] as $step ) {
					$text = is_array( $step ) ? ( $step['text'] ?? '' ) : '';
					$text = trim( wp_strip_all_tags( $text ) );
					if ( '' !== $text ) {
						$steps[] = [
							'@type' => 'HowToStep',
							'text'  => $text,
						];
					}
				}
				continue;
			}

			if ( $name !== self::STEP_BLOCK ) {
				continue;
			}

			$text = $this->extract_step_text_from_inner_blocks( $block['innerBlocks'] ?? [] );
			if ( '' !== $text ) {
				$steps[] = [
					'@type' => 'HowToStep',
					'text'  => $text,
				];
			}
		}

		return $steps;
	}

	private function extract_step_text_from_inner_blocks( array $blocks ): string {
		$parts = [];

		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? '';
			$html = $block['innerHTML'] ?? '';

			if ( in_array( $name, [ 'core/heading', 'core/paragraph', 'core/list', 'core/code' ], true ) ) {
				$text = trim( wp_strip_all_tags( $html ) );
				if ( '' !== $text ) {
					$parts[] = $text;
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$child = $this->extract_step_text_from_inner_blocks( $block['innerBlocks'] );
				if ( '' !== $child ) {
					$parts[] = $child;
				}
			}
		}

		return trim( implode( "\n", $parts ) );
	}

	private function extract_code_text( array $block ): string {
		$html = $block['innerHTML'] ?? '';
		if ( '' === $html ) {
			return '';
		}
		return trim( wp_strip_all_tags( $html ) );
	}

	private function build_about( \WP_Post $post ): array {
		$items = [];

		$tags = get_the_tags( $post->ID );
		if ( ! empty( $tags ) ) {
			foreach ( $tags as $tag ) {
				$items[] = $tag->name;
			}
		}

		$items = array_unique( array_filter( $items ) );
		$about = [];
		foreach ( $items as $name ) {
			$about[] = [
				'@type' => 'Thing',
				'name'  => $name,
			];
		}

		return apply_filters( 'forwp_seo_techarticle_about', $about, $post );
	}

	private function flatten_blocks( array $blocks ): array {
		$flat = [];
		foreach ( $blocks as $block ) {
			$flat[] = $block;
			if ( ! empty( $block['innerBlocks'] ) ) {
				$flat = array_merge( $flat, $this->flatten_blocks( $block['innerBlocks'] ) );
			}
		}
		return $flat;
	}
}

