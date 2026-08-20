<?php
/**
 * GSC country codes (ISO 3166-1 alpha-3) to labels and flat flag icons.
 */

namespace Forwp\SeoHelper\Gsc;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CountryCatalog {
	/** @var array<string, string>|null */
	private static ?array $alpha3_to_alpha2 = null;

	/**
	 * @return array{code:string,alpha2:string,name:string,flag_url:string}
	 */
	public static function resolve( string $code ): array {
		$code   = strtolower( trim( $code ) );
		$alpha2 = self::alpha2_for( $code );
		$name   = self::name_for( $alpha2, $code );

		return [
			'code'      => $code,
			'alpha2'    => $alpha2,
			'name'      => $name,
			'flag_url'  => self::flag_url_for( $alpha2 ),
		];
	}

	public static function flag_url_for( string $alpha2 ): string {
		if ( '' === $alpha2 ) {
			return '';
		}

		$alpha2 = strtolower( $alpha2 );

		/**
		 * Flat SVG flag URL (16px wide CDN).
		 *
		 * @param string $url      Default flag CDN URL.
		 * @param string $alpha2   ISO 3166-1 alpha-2 code.
		 */
		return (string) apply_filters(
			'forwp_seo_gsc_country_flag_url',
			'https://flagcdn.com/' . rawurlencode( $alpha2 ) . '.svg',
			strtoupper( $alpha2 )
		);
	}

	public static function render( string $code ): string {
		$country = self::resolve( $code );

		if ( '' === $country['flag_url'] ) {
			return '<span class="forwp-seo-gsc-country forwp-seo-gsc-country--unknown">' . esc_html( strtoupper( $country['code'] ) ) . '</span>';
		}

		$html = sprintf(
			'<span class="forwp-seo-gsc-country"><img src="%1$s" alt="" class="forwp-seo-gsc-country__flag" width="20" height="15" loading="lazy" decoding="async" /><span class="forwp-seo-gsc-country__name">%2$s</span><span class="forwp-seo-gsc-country__code screen-reader-text">%3$s</span></span>',
			esc_url( $country['flag_url'] ),
			esc_html( $country['name'] ),
			esc_html( strtoupper( $country['code'] ) )
		);

		return wp_kses( $html, self::allowed_html() );
	}

	/**
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_html(): array {
		return [
			'span' => [
				'class' => true,
			],
			'img'  => [
				'src'      => true,
				'alt'      => true,
				'class'    => true,
				'width'    => true,
				'height'   => true,
				'loading'  => true,
				'decoding' => true,
			],
		];
	}

	private static function alpha2_for( string $code ): string {
		$map = self::map();

		return $map[ $code ] ?? '';
	}

	private static function name_for( string $alpha2, string $fallback_code ): string {
		if ( '' !== $alpha2 && class_exists( 'Locale' ) ) {
			$locale = function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
			$name   = \Locale::getDisplayRegion( 'en_' . $alpha2, $locale );
			if ( is_string( $name ) && '' !== $name && strtoupper( $name ) !== $alpha2 ) {
				return $name;
			}
		}

		if ( '' !== $alpha2 && class_exists( 'Locale' ) ) {
			$name = \Locale::getDisplayRegion( 'en_' . $alpha2, 'en' );
			if ( is_string( $name ) && '' !== $name && strtoupper( $name ) !== $alpha2 ) {
				return $name;
			}
		}

		return strtoupper( $fallback_code );
	}

	/**
	 * @return array<string, string>
	 */
	private static function map(): array {
		if ( null === self::$alpha3_to_alpha2 ) {
			$path = __DIR__ . '/country-data.php';
			/** @var array<string, string> $data */
			$data                    = file_exists( $path ) ? require $path : [];
			self::$alpha3_to_alpha2 = $data;
		}

		return self::$alpha3_to_alpha2;
	}
}
