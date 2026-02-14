<?php
/**
 * LLMS.txt generator.
 */

namespace Forwp\Seo\Llms;

use Forwp\Seo\Schema\TechArticle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Generator {
	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', [ $this, 'register_rewrite' ] );
		add_filter( 'query_vars', [ $this, 'register_query_var' ] );
		add_action( 'template_redirect', [ $this, 'maybe_output' ] );
	}

	public static function activate(): void {
		$self = self::get_instance();
		$self->register_rewrite();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	public function register_rewrite(): void {
		add_rewrite_rule( '^llms\.txt$', 'index.php?forwp_seo_llms=1', 'top' );
	}

	public function register_query_var( array $vars ): array {
		$vars[] = 'forwp_seo_llms';
		return $vars;
	}

	public function maybe_output(): void {
		if ( (int) get_query_var( 'forwp_seo_llms' ) !== 1 ) {
			return;
		}

		$content = $this->build_llms_txt();
		if ( '' === $content ) {
			status_header( 404 );
			exit;
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		echo $content;
		exit;
	}

	private function build_llms_txt(): string {
		$posts = get_posts(
			[
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'meta_key'       => TechArticle::META_KEY,
				'meta_value'     => '1',
			]
		);

		if ( empty( $posts ) ) {
			return '';
		}

		$tech = TechArticle::get_instance();
		$items = [];
		foreach ( $posts as $post ) {
			if ( ! $tech->is_post_valid( $post ) ) {
				continue;
			}
			$items[] = [
				'title' => get_the_title( $post->ID ),
				'url'   => get_permalink( $post->ID ),
			];
		}

		if ( empty( $items ) ) {
			return '';
		}

		$lines = [];
		$lines[] = '# 4wp SEO TechArticle';
		$lines[] = '';
		$lines[] = '## Source';
		$lines[] = home_url( '/' );
		$lines[] = '';
		$lines[] = '## TechArticle Pages';
		foreach ( $items as $item ) {
			$lines[] = '- ' . $item['title'] . ' — ' . $item['url'];
		}
		$lines[] = '';
		$lines[] = '## Updated';
		$lines[] = gmdate( 'Y-m-d H:i:s' ) . ' UTC';

		return implode( "\n", $lines );
	}
}



















