<?php
/**
 * Cross posting REST endpoints.
 */

namespace Forwp\Seo\CrossPosting;

use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Rest {
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
			'/crosspost',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_request' ],
				'permission_callback' => [ $this, 'can_access' ],
				'args'                => [
					'post_id' => [
						'required' => true,
						'type'     => 'integer',
					],
					'platform' => [
						'required' => true,
						'type'     => 'string',
					],
				],
			]
		);
	}

	public function can_access( WP_REST_Request $request ): bool {
		$post_id = (int) $request->get_param( 'post_id' );
		return $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}

	public function handle_request( WP_REST_Request $request ) {
		$module_enabled = get_option( Module::OPTION_ENABLED, '0' ) === '1';
		if ( ! $module_enabled ) {
			return new \WP_Error( 'crossposting_disabled', __( 'Cross posting is disabled.', '4wp-seo' ), [ 'status' => 403 ] );
		}

		$post_id  = (int) $request->get_param( 'post_id' );
		$platform = sanitize_key( $request->get_param( 'platform' ) );

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'not_found', __( 'Post not found.', '4wp-seo' ), [ 'status' => 404 ] );
		}

		$formatter = new Formatter();
		$content   = $formatter->format( $platform, $post );

		if ( '' === $content ) {
			return new \WP_Error( 'invalid_platform', __( 'Unsupported platform.', '4wp-seo' ), [ 'status' => 400 ] );
		}

		return [
			'content' => $content,
		];
	}
}


