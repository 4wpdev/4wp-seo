<?php
/**
 * Optional CORS for external analytics dashboard clients.
 */

namespace Forwp\SeoHelper\Inventory\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cors {
	public static function register(): void {
		add_filter( 'rest_pre_serve_request', [ self::class, 'maybe_send_headers' ], 15, 4 );
	}

	public static function maybe_send_headers( bool $served, $result, $request, $server ): bool {
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_route' ) ) {
			return $served;
		}

		$route = (string) $request->get_route();
		if ( ! str_starts_with( $route, '/forwp-seo-helper/v1/seo-inventory' ) ) {
			return $served;
		}

		$origins = apply_filters( 'forwp_seo_inventory_cors_origins', [] );
		if ( empty( $origins ) || ! is_array( $origins ) ) {
			return $served;
		}

		$origin = isset( $_SERVER['HTTP_ORIGIN'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_ORIGIN'] ) )
			: '';
		if ( '' === $origin || ! in_array( $origin, $origins, true ) ) {
			return $served;
		}

		header( 'Access-Control-Allow-Origin: ' . $origin );
		header( 'Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Authorization, Content-Type' );
		header( 'Vary: Origin' );

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
			status_header( 204 );
			exit;
		}

		return $served;
	}
}
