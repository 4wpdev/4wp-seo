<?php
/**
 * Multilingual plugin contract.
 */

namespace Forwp\SeoHelper\Multilingual;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface ProviderInterface {
	public function get_id(): string;

	public function get_label(): string;

	public function is_available(): bool;

	/**
	 * @return list<array{code: string, name: string, is_default: bool}>
	 */
	public function get_languages(): array;

	public function get_default_language(): string;

	public function get_post_language( int $post_id ): string;

	/**
	 * @return list<array{post_id: int, lang: string}>
	 */
	public function get_translation_group( int $post_id ): array;
}
