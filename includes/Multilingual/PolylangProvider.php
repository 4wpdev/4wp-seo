<?php
/**
 * Polylang multilingual provider.
 */

namespace Forwp\SeoHelper\Multilingual;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PolylangProvider implements ProviderInterface {
	public function get_id(): string {
		return 'polylang';
	}

	public function get_label(): string {
		return 'Polylang';
	}

	public function is_available(): bool {
		return function_exists( 'pll_get_post_language' ) && function_exists( 'pll_languages_list' );
	}

	public function get_languages(): array {
		if ( ! $this->is_available() ) {
			return [];
		}

		$codes    = pll_languages_list( [ 'fields' => 'slug' ] );
		$names    = pll_languages_list( [ 'fields' => 'name' ] );
		$default  = (string) pll_default_language( 'slug' );
		$languages = [];

		foreach ( $codes as $index => $code ) {
			$languages[] = [
				'code'       => (string) $code,
				'name'       => (string) ( $names[ $index ] ?? $code ),
				'is_default' => $code === $default,
			];
		}

		return $languages;
	}

	public function get_default_language(): string {
		if ( ! $this->is_available() ) {
			return 'en';
		}

		return (string) pll_default_language( 'slug' );
	}

	public function get_post_language( int $post_id ): string {
		if ( ! $this->is_available() ) {
			return 'en';
		}

		$lang = pll_get_post_language( $post_id, 'slug' );
		if ( is_string( $lang ) && '' !== $lang ) {
			return $lang;
		}

		return $this->get_default_language();
	}

	public function get_translation_group( int $post_id ): array {
		if ( ! $this->is_available() ) {
			return [];
		}

		$translations = pll_get_post_translations( $post_id );
		if ( ! is_array( $translations ) ) {
			return [
				[
					'post_id' => $post_id,
					'lang'    => $this->get_post_language( $post_id ),
				],
			];
		}

		$group = [];
		foreach ( $translations as $lang => $translation_id ) {
			$group[] = [
				'post_id' => (int) $translation_id,
				'lang'    => (string) $lang,
			];
		}

		return $group;
	}
}
