<?php
/**
 * Match Search Console properties to the current WordPress site.
 */

namespace Forwp\SeoHelper\Gsc;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PropertyResolver {
	public static function allows_manual_property_selection(): bool {
		/**
		 * Whether admins can pick any GSC property manually (local/staging mode).
		 *
		 * @param bool $allowed Value from Settings → Search Console → Local/staging mode.
		 */
		return (bool) apply_filters(
			'forwp_seo_gsc_manual_property_selection',
			Module::get_instance()->is_local_dev_mode()
		);
	}

	/**
	 * @param list<string> $properties GSC siteUrl values.
	 */
	public static function match_site_property( array $properties, ?string $site_url = null ): ?string {
		if ( empty( $properties ) ) {
			return null;
		}

		$site_url  = $site_url ?? home_url( '/' );
		$site_host = self::normalize_host( (string) wp_parse_url( $site_url, PHP_URL_HOST ) );

		if ( '' === $site_host ) {
			return null;
		}

		$best       = null;
		$best_score = 0;

		foreach ( $properties as $property ) {
			$property = (string) $property;
			$score    = self::score_property_match( $property, $site_url, $site_host );

			if ( $score > $best_score ) {
				$best_score = $score;
				$best       = $property;
			}
		}

		return $best_score > 0 ? $best : null;
	}

	/**
	 * Rewrite a local/staging permalink onto the selected GSC property host.
	 */
	public static function rewrite_url_for_property( string $url, string $site ): string {
		$url  = trim( $url );
		$site = trim( $site );
		if ( '' === $url || '' === $site || str_starts_with( $site, 'sc-domain:' ) ) {
			return $url;
		}

		$parts = wp_parse_url( $url );
		$prop  = wp_parse_url( $site );
		if ( ! is_array( $parts ) || ! is_array( $prop ) || empty( $prop['host'] ) ) {
			return $url;
		}

		$scheme = isset( $prop['scheme'] ) ? (string) $prop['scheme'] : 'https';
		$host   = (string) $prop['host'];
		$port   = isset( $prop['port'] ) ? ':' . (int) $prop['port'] : '';
		$path   = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		$query  = isset( $parts['query'] ) ? '?' . $parts['query'] : '';

		return $scheme . '://' . $host . $port . $path . $query;
	}

	/**
	 * Google Search Console URL Inspection UI for this property + page.
	 *
	 * Do not use add_query_arg() here: it does not encode values, so nested
	 * https:// URLs + `&id=` get mangled by esc_url() / HTML / the browser.
	 */
	public static function search_console_inspect_url( string $property, string $url ): string {
		$property = trim( $property );
		$url      = trim( $url );
		if ( '' === $property || '' === $url ) {
			return '';
		}

		return 'https://search.google.com/search-console/inspect?resource_id=' . rawurlencode( $property ) . '&id=' . rawurlencode( $url );
	}

	public static function url_belongs_to_property( string $url, string $site ): bool {
		if ( '' === $url || '' === $site ) {
			return false;
		}

		if ( str_starts_with( $site, 'sc-domain:' ) ) {
			$domain = substr( $site, 10 );
			$host   = wp_parse_url( $url, PHP_URL_HOST );

			return is_string( $host ) && ( $host === $domain || str_ends_with( $host, '.' . $domain ) );
		}

		$prefix = untrailingslashit( $site );

		return str_starts_with( $url, $prefix . '/' ) || $url === $prefix;
	}

	public static function url_path_key( string $url ): string {
		$parts = wp_parse_url( $url );
		$path  = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		$path  = strtolower( untrailingslashit( $path ) );

		if ( '' === $path ) {
			return '/';
		}

		return $path;
	}

	private static function score_property_match( string $property, string $site_url, string $site_host ): int {
		if ( str_starts_with( $property, 'sc-domain:' ) ) {
			$domain = self::normalize_host( substr( $property, 10 ) );

			if ( $site_host === $domain ) {
				return 100;
			}

			if ( self::hosts_match_with_www( $site_host, $domain ) ) {
				return 90;
			}

			return 0;
		}

		$property_host = self::normalize_host( (string) wp_parse_url( $property, PHP_URL_HOST ) );
		$site_prefix   = untrailingslashit( $site_url );
		$prop_prefix   = untrailingslashit( $property );

		if ( $site_prefix === $prop_prefix ) {
			return 100;
		}

		if ( '' !== $property_host && self::hosts_match_with_www( $site_host, $property_host ) ) {
			return 80;
		}

		return 0;
	}

	private static function normalize_host( string $host ): string {
		$host = strtolower( trim( $host ) );

		if ( str_starts_with( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}

		return $host;
	}

	private static function hosts_match_with_www( string $left, string $right ): bool {
		$left  = self::normalize_host( $left );
		$right = self::normalize_host( $right );

		return $left === $right;
	}
}
