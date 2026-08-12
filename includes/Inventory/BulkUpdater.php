<?php
/**
 * Bulk SEO meta updates.
 */

namespace Forwp\SeoHelper\Inventory;

use Forwp\SeoHelper\Inventory\Rest\Auth;
use Forwp\SeoHelper\SeoMeta\Registry as SeoMetaRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BulkUpdater {
	private Repository $repository;

	public function __construct( ?Repository $repository = null ) {
		$this->repository = $repository ?? new Repository();
	}

	/**
	 * @param list<array{post_id: int, fields: array<string, mixed>}> $items
	 * @return array{updated: int, errors: list<array{post_id: int, message: string}>}
	 */
	public function update( array $items ): array {
		$adapter = SeoMetaRegistry::get_active();
		$updated = 0;
		$errors  = [];

		if ( 'none' === $adapter->get_id() ) {
			return [
				'updated' => 0,
				'errors'  => [
					[
						'post_id' => 0,
						'message' => __( 'No SEO plugin adapter is active.', '4wp-seo' ),
					],
				],
			];
		}

		foreach ( $items as $item ) {
			$post_id = (int) ( $item['post_id'] ?? 0 );
			$fields  = is_array( $item['fields'] ?? null ) ? $item['fields'] : [];

			if ( $post_id <= 0 ) {
				$errors[] = [
					'post_id' => $post_id,
					'message' => __( 'Invalid post ID.', '4wp-seo' ),
				];
				continue;
			}

			if ( ! Auth::can_edit_inventory() && ! current_user_can( 'edit_post', $post_id ) ) {
				$errors[] = [
					'post_id' => $post_id,
					'message' => __( 'Insufficient permissions.', '4wp-seo' ),
				];
				continue;
			}

			if ( null === $this->repository->get_record( $post_id ) ) {
				$errors[] = [
					'post_id' => $post_id,
					'message' => __( 'Post is not in the SEO inventory scope.', '4wp-seo' ),
				];
				continue;
			}

			$normalized = $this->normalize_fields( $fields );
			if ( empty( $normalized ) ) {
				$errors[] = [
					'post_id' => $post_id,
					'message' => __( 'No supported fields provided.', '4wp-seo' ),
				];
				continue;
			}

			if ( ! $adapter->write( $post_id, $normalized ) ) {
				$errors[] = [
					'post_id' => $post_id,
					'message' => __( 'Failed to write SEO meta.', '4wp-seo' ),
				];
				continue;
			}

			++$updated;
		}

		return [
			'updated' => $updated,
			'errors'  => $errors,
		];
	}

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	private function normalize_fields( array $fields ): array {
		$aliases = [
			'seo_title'        => 'title',
			'meta_description' => 'description',
		];

		$normalized = [];

		foreach ( $fields as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( isset( $aliases[ $key ] ) ) {
				$key = $aliases[ $key ];
			}

			$allowed = [
				'title',
				'description',
				'focus_keyword',
				'canonical',
				'noindex',
				'og_title',
				'og_description',
				'og_image',
			];

			if ( ! in_array( $key, $allowed, true ) ) {
				continue;
			}

			if ( 'noindex' === $key ) {
				$normalized[ $key ] = (bool) $value;
				continue;
			}

			$normalized[ $key ] = sanitize_text_field( (string) $value );
		}

		return $normalized;
	}
}
