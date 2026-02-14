<?php
/**
 * Cross posting content formatter.
 */

namespace Forwp\Seo\CrossPosting;

use Forwp\Seo\Schema\TechArticle;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Formatter {
	private const PLATFORM_DEVTO   = 'devto';
	private const PLATFORM_MEDIUM  = 'medium';
	private const PLATFORM_LINKEDIN = 'linkedin';
	private const PLATFORM_X       = 'x';
	private const PLATFORM_BSKY    = 'bsky';

	public function format( string $platform, WP_Post $post ): string {
		$platform = strtolower( $platform );

		switch ( $platform ) {
			case self::PLATFORM_DEVTO:
				return $this->format_markdown( $post );
			case self::PLATFORM_MEDIUM:
				return $this->format_markdown( $post );
			case self::PLATFORM_LINKEDIN:
				return $this->format_linkedin( $post );
			case self::PLATFORM_X:
				return $this->format_short( $post, $this->get_limit( 'x', 280 ) );
			case self::PLATFORM_BSKY:
				return $this->format_short( $post, $this->get_limit( 'bsky', 300 ) );
		}

		return '';
	}

	private function format_markdown( WP_Post $post ): string {
		$title = get_the_title( $post->ID );
		$body  = $this->blocks_to_markdown( $post->post_content );
		$url   = get_permalink( $post->ID );

		$parts = [
			'# ' . $title,
			'',
			$body,
			'',
			'Source: ' . $url,
		];

		return trim( implode( "\n", array_filter( $parts ) ) );
	}

	private function format_linkedin( WP_Post $post ): string {
		$title = get_the_title( $post->ID );
		$summary = $this->build_summary( $post );
		$url = get_permalink( $post->ID );

		$text = $title . "\n\n" . $summary . "\n\n" . $url;
		return $this->trim_to_limit( $text, $this->get_limit( 'linkedin', 400 ) );
	}

	private function format_short( WP_Post $post, int $limit ): string {
		$summary = $this->build_summary( $post );
		$url     = get_permalink( $post->ID );
		$text    = $summary . ' ' . $url;

		return $this->trim_to_limit( $text, $limit );
	}

	private function build_summary( WP_Post $post ): string {
		$excerpt = trim( wp_strip_all_tags( get_the_excerpt( $post ) ) );
		if ( $excerpt !== '' ) {
			return $excerpt;
		}

		$blocks = parse_blocks( $post->post_content );
		foreach ( $blocks as $block ) {
			if ( ( $block['blockName'] ?? '' ) === 'core/paragraph' ) {
				$text = trim( wp_strip_all_tags( $block['innerHTML'] ?? '' ) );
				if ( $text !== '' ) {
					return $text;
				}
			}
		}

		return get_the_title( $post->ID );
	}

	private function blocks_to_markdown( string $content ): string {
		$blocks = parse_blocks( $content );
		$lines  = [];

		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? '';
			$attrs = $block['attrs'] ?? [];
			$html = $block['innerHTML'] ?? '';

			if ( 'core/paragraph' === $name ) {
				$lines[] = trim( wp_strip_all_tags( $html ) );
				$lines[] = '';
				continue;
			}

			if ( 'core/heading' === $name ) {
				$level = isset( $attrs['level'] ) ? (int) $attrs['level'] : 2;
				$prefix = str_repeat( '#', max( 1, min( 6, $level ) ) );
				$lines[] = $prefix . ' ' . trim( wp_strip_all_tags( $html ) );
				$lines[] = '';
				continue;
			}

			if ( 'core/list' === $name ) {
				$items = $block['innerBlocks'] ?? [];
				foreach ( $items as $item ) {
					$text = trim( wp_strip_all_tags( $item['innerHTML'] ?? '' ) );
					if ( $text !== '' ) {
						$lines[] = '- ' . $text;
					}
				}
				$lines[] = '';
				continue;
			}

			if ( 'core/code' === $name ) {
				$lang = $attrs['language'] ?? '';
				$code = trim( wp_strip_all_tags( $html ) );
				$lines[] = '```' . $lang;
				$lines[] = $code;
				$lines[] = '```';
				$lines[] = '';
				continue;
			}

			if ( TechArticle::STEPS_BLOCK === $name ) {
				$steps = $attrs['steps'] ?? [];
				$index = 1;
				foreach ( $steps as $step ) {
					$text = is_array( $step ) ? ( $step['text'] ?? '' ) : '';
					$text = trim( wp_strip_all_tags( $text ) );
					if ( $text !== '' ) {
						$lines[] = $index . '. ' . $text;
						$index++;
					}
				}
				$lines[] = '';
				continue;
			}

			if ( TechArticle::STEP_BLOCK === $name ) {
				$lines = array_merge( $lines, $this->step_block_to_markdown( $block ) );
				$lines[] = '';
				continue;
			}
		}

		return trim( implode( "\n", array_filter( $lines, static function ( $line ) {
			return $line !== null;
		} ) ) );
	}

	private function step_block_to_markdown( array $block ): array {
		$lines = [];
		$inner = $block['innerBlocks'] ?? [];

		foreach ( $inner as $child ) {
			$name = $child['blockName'] ?? '';
			$html = $child['innerHTML'] ?? '';
			$attrs = $child['attrs'] ?? [];

			if ( 'core/heading' === $name ) {
				$level = isset( $attrs['level'] ) ? (int) $attrs['level'] : 3;
				$prefix = str_repeat( '#', max( 1, min( 6, $level ) ) );
				$lines[] = $prefix . ' ' . trim( wp_strip_all_tags( $html ) );
				$lines[] = '';
			} elseif ( 'core/paragraph' === $name ) {
				$lines[] = trim( wp_strip_all_tags( $html ) );
				$lines[] = '';
			} elseif ( 'core/code' === $name ) {
				$lines[] = '```';
				$lines[] = trim( wp_strip_all_tags( $html ) );
				$lines[] = '```';
				$lines[] = '';
			} elseif ( 'core/list' === $name ) {
				$list_items = $child['innerBlocks'] ?? [];
				foreach ( $list_items as $item ) {
					$text = trim( wp_strip_all_tags( $item['innerHTML'] ?? '' ) );
					if ( $text !== '' ) {
						$lines[] = '- ' . $text;
					}
				}
				$lines[] = '';
			}
		}

		return $lines;
	}

	private function trim_to_limit( string $text, int $limit ): string {
		if ( mb_strlen( $text ) <= $limit ) {
			return $text;
		}

		return rtrim( mb_substr( $text, 0, $limit - 1 ) ) . '…';
	}

	private function get_limit( string $platform, int $default ): int {
		/**
		 * Filter platform character limits for cross posting.
		 *
		 * @param int    $default Default limit.
		 * @param string $platform Platform key.
		 */
		return (int) apply_filters( 'forwp_seo_crosspost_limit', $default, $platform );
	}
}


