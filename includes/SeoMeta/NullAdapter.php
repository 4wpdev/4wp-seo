<?php
/**
 * Fallback when no SEO plugin is active.
 */

namespace Forwp\SeoHelper\SeoMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NullAdapter implements AdapterInterface {
	public function get_id(): string {
		return 'none';
	}

	public function get_label(): string {
		return __( 'No SEO plugin detected', '4wp-seo' );
	}

	public function is_available(): bool {
		return true;
	}

	public function read( int $post_id ): array {
		return $this->empty_fields();
	}

	public function write( int $post_id, array $fields ): bool {
		return false;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function empty_fields(): array {
		return [
			'title'           => '',
			'description'     => '',
			'focus_keyword'   => '',
			'canonical'       => '',
			'noindex'         => false,
			'og_title'        => '',
			'og_description'  => '',
			'og_image'        => '',
		];
	}
}
