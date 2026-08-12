<?php
/**
 * SEO plugin meta adapter contract.
 */

namespace Forwp\SeoHelper\SeoMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface AdapterInterface {
	public function get_id(): string;

	public function get_label(): string;

	public function is_available(): bool;

	/**
	 * @return array{
	 *     title: string,
	 *     description: string,
	 *     focus_keyword: string,
	 *     canonical: string,
	 *     noindex: bool,
	 *     og_title: string,
	 *     og_description: string,
	 *     og_image: string
	 * }
	 */
	public function read( int $post_id ): array;

	/**
	 * @param array<string, mixed> $fields Allowed keys match read() output.
	 */
	public function write( int $post_id, array $fields ): bool;
}
