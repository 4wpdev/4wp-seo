<?php
/**
 * All in One SEO meta adapter.
 */

namespace Forwp\SeoHelper\SeoMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AioseoAdapter implements AdapterInterface {
	public function get_id(): string {
		return 'aioseo';
	}

	public function get_label(): string {
		return 'All in One SEO';
	}

	public function is_available(): bool {
		return defined( 'AIOSEO_VERSION' );
	}

	public function read( int $post_id ): array {
		$raw = get_post_meta( $post_id, '_aioseo_title', true );
		if ( is_array( $raw ) ) {
			return $this->read_from_array( $raw );
		}

		return [
			'title'           => (string) get_post_meta( $post_id, '_aioseo_title', true ),
			'description'     => (string) get_post_meta( $post_id, '_aioseo_description', true ),
			'focus_keyword'   => (string) get_post_meta( $post_id, '_aioseo_keywords', true ),
			'canonical'       => (string) get_post_meta( $post_id, '_aioseo_canonical_url', true ),
			'noindex'         => '1' === (string) get_post_meta( $post_id, '_aioseo_robots_noindex', true ),
			'og_title'        => (string) get_post_meta( $post_id, '_aioseo_og_title', true ),
			'og_description'  => (string) get_post_meta( $post_id, '_aioseo_og_description', true ),
			'og_image'        => (string) get_post_meta( $post_id, '_aioseo_og_image_custom_url', true ),
		];
	}

	public function write( int $post_id, array $fields ): bool {
		$map = [
			'title'          => '_aioseo_title',
			'description'    => '_aioseo_description',
			'focus_keyword'  => '_aioseo_keywords',
			'canonical'      => '_aioseo_canonical_url',
			'og_title'       => '_aioseo_og_title',
			'og_description' => '_aioseo_og_description',
			'og_image'       => '_aioseo_og_image_custom_url',
		];

		foreach ( $map as $field => $meta_key ) {
			if ( ! array_key_exists( $field, $fields ) ) {
				continue;
			}

			update_post_meta( $post_id, $meta_key, sanitize_text_field( (string) $fields[ $field ] ) );
		}

		if ( array_key_exists( 'noindex', $fields ) ) {
			update_post_meta(
				$post_id,
				'_aioseo_robots_noindex',
				! empty( $fields['noindex'] ) ? '1' : '0'
			);
		}

		return true;
	}

	public function read_scores( int $post_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'aioseo_posts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return [
				'seo'         => null,
				'readability' => null,
				'label'       => '',
				'no_focus'    => false,
			];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT seo_score, keyphrases FROM {$table} WHERE post_id = %d LIMIT 1",
				$post_id
			),
			ARRAY_A
		);

		$seo      = null;
		$no_focus = true;
		$label    = __( 'No focus keyphrase', '4wp-seo-helper' );

		if ( is_array( $row ) ) {
			if ( isset( $row['seo_score'] ) && is_numeric( $row['seo_score'] ) ) {
				$seo = (int) $row['seo_score'];
			}

			$keyphrases = json_decode( (string) ( $row['keyphrases'] ?? '' ), true );
			if ( is_array( $keyphrases ) ) {
				$focus = trim( (string) ( $keyphrases['focus']['keyphrase'] ?? '' ) );
				if ( '' !== $focus ) {
					$no_focus = false;
				}
			}
		}

		if ( ! $no_focus && null !== $seo ) {
			if ( $seo >= 80 ) {
				$label = __( 'Good', '4wp-seo-helper' );
			} elseif ( $seo >= 50 ) {
				$label = __( 'OK', '4wp-seo-helper' );
			} else {
				$label = __( 'Needs improvement', '4wp-seo-helper' );
			}
		}

		return [
			'seo'           => $seo,
			'readability'   => null,
			'label'         => $label,
			'no_focus'      => $no_focus,
		];
	}

	/**
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	private function read_from_array( array $raw ): array {
		return [
			'title'           => (string) ( $raw['title'] ?? '' ),
			'description'     => (string) ( $raw['description'] ?? '' ),
			'focus_keyword'   => (string) ( $raw['keywords'] ?? '' ),
			'canonical'       => (string) ( $raw['canonicalUrl'] ?? '' ),
			'noindex'         => ! empty( $raw['robots']['noindex'] ),
			'og_title'        => (string) ( $raw['og_title'] ?? '' ),
			'og_description'  => (string) ( $raw['og_description'] ?? '' ),
			'og_image'        => (string) ( $raw['og_image_custom_url'] ?? '' ),
		];
	}
}
