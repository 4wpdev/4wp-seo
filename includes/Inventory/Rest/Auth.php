<?php
/**
 * REST auth for SEO inventory endpoints.
 */

namespace Forwp\SeoHelper\Inventory\Rest;

use Forwp\SeoHelper\Inventory\Module;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Auth {
	private static bool $token_authenticated = false;

	public static function can_access( WP_REST_Request $request ): bool {
		self::$token_authenticated = false;

		if ( ! Module::get_instance()->is_enabled() ) {
			return false;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		if ( self::has_valid_token( $request ) ) {
			self::$token_authenticated = true;
			return true;
		}

		return false;
	}

	public static function can_edit_inventory(): bool {
		return current_user_can( 'manage_options' ) || self::$token_authenticated;
	}

	private static function has_valid_token( WP_REST_Request $request ): bool {
		$token = self::extract_token( $request );
		if ( '' === $token ) {
			return false;
		}

		$stored = Module::get_instance()->get_api_token();
		return '' !== $stored && hash_equals( $stored, $token );
	}

	private static function extract_token( WP_REST_Request $request ): string {
		$header = $request->get_header( 'authorization' );
		if ( is_string( $header ) && stripos( $header, 'Bearer ' ) === 0 ) {
			return trim( substr( $header, 7 ) );
		}

		$query_token = $request->get_param( 'token' );
		if ( is_string( $query_token ) && '' !== $query_token ) {
			return $query_token;
		}

		return '';
	}
}
