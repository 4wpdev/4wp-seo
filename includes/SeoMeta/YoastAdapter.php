<?php
/**
 * Yoast SEO meta adapter.
 */

namespace Forwp\SeoHelper\SeoMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class YoastAdapter implements AdapterInterface {
	public function get_id(): string {
		return 'yoast';
	}

	public function get_label(): string {
		return 'Yoast SEO';
	}

	public function is_available(): bool {
		return defined( 'WPSEO_VERSION' );
	}

	public function read( int $post_id ): array {
		return [
			'title'          => (string) get_post_meta( $post_id, '_yoast_wpseo_title', true ),
			'description'    => (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ),
			'focus_keyword' => (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ),
			'canonical'      => (string) get_post_meta( $post_id, '_yoast_wpseo_canonical', true ),
			'noindex'        => '1' === (string) get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ),
			'og_title'       => (string) get_post_meta( $post_id, '_yoast_wpseo_opengraph-title', true ),
			'og_description' => (string) get_post_meta( $post_id, '_yoast_wpseo_opengraph-description', true ),
			'og_image'       => (string) get_post_meta( $post_id, '_yoast_wpseo_opengraph-image', true ),
		];
	}

	public function write( int $post_id, array $fields ): bool {
		$map = [
			'title'           => '_yoast_wpseo_title',
			'description'     => '_yoast_wpseo_metadesc',
			'focus_keyword'   => '_yoast_wpseo_focuskw',
			'canonical'       => '_yoast_wpseo_canonical',
			'og_title'        => '_yoast_wpseo_opengraph-title',
			'og_description'  => '_yoast_wpseo_opengraph-description',
			'og_image'        => '_yoast_wpseo_opengraph-image',
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
				'_yoast_wpseo_meta-robots-noindex',
				! empty( $fields['noindex'] ) ? '1' : '0'
			);
		}

		return true;
	}
}
