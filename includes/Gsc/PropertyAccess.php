<?php
/**
 * Verify the connected Google account can access this site's Search Console property.
 */

namespace Forwp\SeoHelper\Gsc;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PropertyAccess {
	public const STATUS_OK            = 'ok';
	public const STATUS_API_ERROR     = 'api_error';
	public const STATUS_NO_PROPERTIES = 'no_properties';
	public const STATUS_NO_MATCH      = 'no_match';
	public const STATUS_INVALID_SAVED = 'invalid_saved';
	public const STATUS_PICK_PROPERTY = 'pick_property';

	/**
	 * @return array{ready:bool,status:string,matched:?string,properties:list<string>,error:string,users_url:string,site_label:string,message:string}
	 */
	public static function disconnected_state(): array {
		return [
			'ready'       => false,
			'status'      => 'not_connected',
			'matched'     => null,
			'properties'  => [],
			'error'       => '',
			'users_url'   => self::users_url(),
			'site_label'  => self::site_label(),
			'message'     => '',
		];
	}

	public static function site_label(): string {
		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

		return is_string( $host ) && '' !== $host ? $host : (string) home_url( '/' );
	}

	public static function users_url( ?string $property = null ): string {
		if ( null === $property || '' === $property ) {
			$property = trailingslashit( home_url( '/' ) );
		}

		return 'https://search.google.com/search-console/users?resource_id=' . rawurlencode(
			PropertyResolver::normalize_site_url_for_api( $property )
		);
	}

	/**
	 * @param list<string> $properties
	 * @return array{ready:bool,status:string,matched:?string,properties:list<string>,error:string,users_url:string,site_label:string,message:string}
	 */
	public static function evaluate( array $properties, string $api_error = '', ?string $saved_property = null ): array {
		$site_label = self::site_label();
		$users_url  = self::users_url();
		$manual     = PropertyResolver::allows_manual_property_selection();
		$saved      = is_string( $saved_property ) ? trim( $saved_property ) : '';

		if ( '' !== $api_error ) {
			return [
				'ready'      => false,
				'status'     => self::STATUS_API_ERROR,
				'matched'    => null,
				'properties' => $properties,
				'error'      => $api_error,
				'users_url'  => $users_url,
				'site_label' => $site_label,
				'message'    => $api_error,
			];
		}

		if ( empty( $properties ) ) {
			return [
				'ready'      => false,
				'status'     => self::STATUS_NO_PROPERTIES,
				'matched'    => null,
				'properties' => [],
				'error'      => '',
				'users_url'  => $users_url,
				'site_label' => $site_label,
				'message'    => sprintf(
					/* translators: %s: site domain or home URL */
					__( 'This Google account has no Search Console properties. Add %s in Search Console or ask the property owner for access.', '4wp-seo-helper' ),
					$site_label
				),
			];
		}

		if ( $manual ) {
			if ( '' !== $saved && ! in_array( $saved, $properties, true ) ) {
				return [
					'ready'      => false,
					'status'     => self::STATUS_INVALID_SAVED,
					'matched'    => null,
					'properties' => $properties,
					'error'      => '',
					'users_url'  => self::users_url( $saved ),
					'site_label' => $site_label,
					'message'    => __( 'The saved Search Console property is no longer available for this Google account.', '4wp-seo-helper' ),
				];
			}

			if ( '' !== $saved ) {
				return [
					'ready'      => true,
					'status'     => self::STATUS_OK,
					'matched'    => $saved,
					'properties' => $properties,
					'error'      => '',
					'users_url'  => self::users_url( $saved ),
					'site_label' => $site_label,
					'message'    => '',
				];
			}

			return [
				'ready'      => false,
				'status'     => self::STATUS_PICK_PROPERTY,
				'matched'    => null,
				'properties' => $properties,
				'error'      => '',
				'users_url'  => $users_url,
				'site_label' => $site_label,
				'message'    => __( 'Choose a Search Console property and save.', '4wp-seo-helper' ),
			];
		}

		$matched = PropertyResolver::match_site_property( $properties );
		if ( null === $matched ) {
			return [
				'ready'      => false,
				'status'     => self::STATUS_NO_MATCH,
				'matched'    => null,
				'properties' => $properties,
				'error'      => '',
				'users_url'  => $users_url,
				'site_label' => $site_label,
				'message'    => sprintf(
					/* translators: %s: site domain or home URL */
					__( 'This Google account does not have access to %s in Search Console.', '4wp-seo-helper' ),
					$site_label
				),
			];
		}

		return [
			'ready'      => true,
			'status'     => self::STATUS_OK,
			'matched'    => $matched,
			'properties' => $properties,
			'error'      => '',
			'users_url'  => self::users_url( $matched ),
			'site_label' => $site_label,
			'message'    => '',
		];
	}
}
