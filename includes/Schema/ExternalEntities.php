<?php
/**
 * External schema entities integration.
 */

namespace Forwp\Seo\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ExternalEntities {
	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_head', [ $this, 'output_entities' ], 2 );
	}

	public function output_entities(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$entities = apply_filters( 'forwp_seo_schema_entities', [], $post );
		if ( empty( $entities ) ) {
			return;
		}

		$output = is_array( $entities ) ? array_values( $entities ) : [];
		if ( empty( $output ) ) {
			return;
		}

		echo '<script type="application/ld+json">';
		echo wp_json_encode( $output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		echo '</script>' . PHP_EOL;
	}
}






