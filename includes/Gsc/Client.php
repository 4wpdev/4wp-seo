<?php
/**
 * Google Search Console API client.
 */

namespace Forwp\Seo\Gsc;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Client {
	private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
	private const SITES_ENDPOINT = 'https://searchconsole.googleapis.com/webmasters/v3/sites';
	private const INSPECTION_ENDPOINT = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';

	public function get_authorization_url( string $client_id, string $redirect_uri, string $state ): string {
		$params = [
			'client_id'     => $client_id,
			'redirect_uri'  => $redirect_uri,
			'response_type' => 'code',
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'scope'         => implode(
				' ',
				[
					'https://www.googleapis.com/auth/webmasters',
					'https://www.googleapis.com/auth/webmasters.readonly',
				]
			),
			'state'         => $state,
		];

		return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params );
	}

	public function exchange_code( string $client_id, string $client_secret, string $redirect_uri, string $code ): array {
		$response = wp_remote_post(
			self::TOKEN_ENDPOINT,
			[
				'body' => [
					'code'          => $code,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'redirect_uri'  => $redirect_uri,
					'grant_type'    => 'authorization_code',
				],
			]
		);

		return $this->parse_token_response( $response );
	}

	public function refresh_token( string $client_id, string $client_secret, string $refresh_token ): array {
		$response = wp_remote_post(
			self::TOKEN_ENDPOINT,
			[
				'body' => [
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'refresh_token' => $refresh_token,
					'grant_type'    => 'refresh_token',
				],
			]
		);

		return $this->parse_token_response( $response );
	}

	public function list_sites( string $access_token ): array {
		$response = wp_remote_get(
			self::SITES_ENDPOINT,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token,
				],
			]
		);

		return $this->parse_json_response( $response );
	}

	public function inspect_url( string $access_token, string $site_url, string $url ): array {
		$body = [
			'inspectionUrl' => $url,
			'siteUrl'       => $site_url,
		];

		$response = wp_remote_post(
			self::INSPECTION_ENDPOINT,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $body ),
			]
		);

		return $this->parse_json_response( $response );
	}

	public function search_analytics( string $access_token, string $site_url, string $url, string $start_date, string $end_date ): array {
		$endpoint = 'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode( $site_url ) . '/searchAnalytics/query';
		$body = [
			'startDate' => $start_date,
			'endDate'   => $end_date,
			'dimensions' => [ 'page' ],
			'dimensionFilterGroups' => [
				[
					'filters' => [
						[
							'dimension'  => 'page',
							'operator'   => 'equals',
							'expression' => $url,
						],
					],
				],
			],
		];

		$response = wp_remote_post(
			$endpoint,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $body ),
			]
		);

		return $this->parse_json_response( $response );
	}

	private function parse_token_response( $response ): array {
		if ( is_wp_error( $response ) ) {
			return [
				'error' => $response->get_error_message(),
			];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return [
				'error' => 'Invalid token response.',
			];
		}

		if ( isset( $body['error'] ) ) {
			return [
				'error' => $body['error_description'] ?? $body['error'],
			];
		}

		return $body;
	}

	private function parse_json_response( $response ): array {
		if ( is_wp_error( $response ) ) {
			return [
				'error' => $response->get_error_message(),
			];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return [
				'error' => 'Invalid API response.',
			];
		}

		if ( isset( $body['error'] ) ) {
			$message = is_array( $body['error'] ) ? ( $body['error']['message'] ?? 'API error' ) : $body['error'];
			return [
				'error' => $message,
			];
		}

		return $body;
	}
}



















