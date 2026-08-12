<?php
/**
 * TechArticle schema REST preview.
 */

namespace Forwp\Seo\Schema;

use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TechArticleRest {
	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route(
			'forwp-seo/v1',
			'/techarticle',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_request' ],
				'permission_callback' => [ $this, 'can_access' ],
				'args'                => [
					'post_id' => [
						'required' => true,
						'type'     => 'integer',
					],
				],
			]
		);
	}

	public function can_access( WP_REST_Request $request ): bool {
		$post_id = (int) $request->get_param( 'post_id' );

		return $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function handle_request( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$post    = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return new \WP_Error( 'not_found', __( 'Post not found.', '4wp-seo' ), [ 'status' => 404 ] );
		}

		$tech    = TechArticle::get_instance();
		$enabled = $tech->is_enabled_for_post( $post_id );
		$valid   = $tech->is_post_valid( $post );
		$schema  = $tech->get_schema( $post );
		$source  = $tech->get_schema_source( $post );

		return [
			'enabled' => $enabled,
			'valid'   => $valid,
			'source'  => $source,
			'schema'  => $schema,
		];
	}
}
