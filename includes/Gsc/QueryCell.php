<?php
/**
 * GSC query dimension cell: label, Google search link, optional landing page.
 */

namespace Forwp\SeoHelper\Gsc;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class QueryCell {
	/**
	 * @param array{page?:string,clicks?:int,position?:float}|null $landing
	 */
	public static function render( string $query, ?array $landing = null ): string {
		$query = trim( $query );
		if ( '' === $query ) {
			return '';
		}

		$search_url = self::search_url( $query );
		$html       = sprintf(
			'<span class="forwp-seo-gsc-query"><span class="forwp-seo-gsc-query__text">%1$s</span><a href="%2$s" class="forwp-seo-gsc-query__search" target="_blank" rel="noopener noreferrer" aria-label="%3$s"><span class="dashicons dashicons-external" aria-hidden="true"></span></a>',
			esc_html( $query ),
			esc_url( $search_url ),
			esc_attr(
				sprintf(
					/* translators: %s: search query */
					__( 'Search Google for “%s”', '4wp-seo-helper' ),
					$query
				)
			)
		);

		if ( ! empty( $landing['page'] ) ) {
			$page_label = self::page_label( (string) $landing['page'] );
			$meta       = [];
			if ( isset( $landing['clicks'] ) && (int) $landing['clicks'] > 0 ) {
				$meta[] = sprintf(
					/* translators: %d: click count */
					_n( '%d click', '%d clicks', (int) $landing['clicks'], '4wp-seo-helper' ),
					(int) $landing['clicks']
				);
			}
			if ( isset( $landing['position'] ) && (float) $landing['position'] > 0 ) {
				$meta[] = sprintf(
					/* translators: %s: average position */
					__( 'pos. %s', '4wp-seo-helper' ),
					number_format_i18n( (float) $landing['position'], 1 )
				);
			}

			$html .= sprintf(
				'<a href="%1$s" class="forwp-seo-gsc-query__page" target="_blank" rel="noopener noreferrer" title="%2$s"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span><span class="forwp-seo-gsc-query__page-label">%3$s</span></a>',
				esc_url( (string) $landing['page'] ),
				esc_attr( implode( ' · ', $meta ) ),
				esc_html( $page_label )
			);
		}

		$html .= '</span>';

		return wp_kses( $html, self::allowed_html() );
	}

	public static function search_url( string $query ): string {
		return 'https://www.google.com/search?q=' . rawurlencode( trim( $query ) );
	}

	private static function page_label( string $url ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$path = untrailingslashit( $path );

		if ( '' === $path || '/' === $path ) {
			return '/';
		}

		return $path;
	}

	/**
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_html(): array {
		return [
			'span' => [
				'class' => true,
				'title' => true,
			],
			'a'    => [
				'href'   => true,
				'class'  => true,
				'target' => true,
				'rel'    => true,
				'title'  => true,
				'aria-label' => true,
			],
		];
	}
}
