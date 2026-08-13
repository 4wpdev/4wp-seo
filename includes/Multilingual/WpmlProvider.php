<?php
/**
 * WPML multilingual provider.
 */

namespace Forwp\SeoHelper\Multilingual;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML third-party hooks.
final class WpmlProvider implements ProviderInterface {
	public function get_id(): string {
		return 'wpml';
	}

	public function get_label(): string {
		return 'WPML';
	}

	public function is_available(): bool {
		return defined( 'ICL_SITEPRESS_VERSION' ) && function_exists( 'apply_filters' );
	}

	public function get_languages(): array {
		if ( ! $this->is_available() ) {
			return [];
		}

		$active = apply_filters( 'wpml_active_languages', null, [ 'skip_missing' => 0 ] );
		if ( ! is_array( $active ) ) {
			return [];
		}

		$default = apply_filters( 'wpml_default_language', '' );
		$languages = [];

		foreach ( $active as $code => $data ) {
			$languages[] = [
				'code'       => (string) $code,
				'name'       => (string) ( $data['native_name'] ?? $code ),
				'is_default' => $code === $default,
			];
		}

		return $languages;
	}

	public function get_default_language(): string {
		if ( ! $this->is_available() ) {
			return 'en';
		}

		return (string) apply_filters( 'wpml_default_language', 'en' );
	}

	public function get_post_language( int $post_id ): string {
		if ( ! $this->is_available() ) {
			return 'en';
		}

		$details = apply_filters( 'wpml_post_language_details', null, $post_id );
		if ( is_array( $details ) && ! empty( $details['language_code'] ) ) {
			return (string) $details['language_code'];
		}

		return $this->get_default_language();
	}

	public function get_translation_group( int $post_id ): array {
		if ( ! $this->is_available() ) {
			return [];
		}

		$type = get_post_type( $post_id );
		if ( ! is_string( $type ) ) {
			return [];
		}

		$trid         = apply_filters( 'wpml_element_trid', null, $post_id, 'post_' . $type );
		$translations = apply_filters( 'wpml_get_element_translations', null, $trid, 'post_' . $type );

		if ( ! is_array( $translations ) ) {
			return [
				[
					'post_id' => $post_id,
					'lang'    => $this->get_post_language( $post_id ),
				],
			];
		}

		$group = [];
		foreach ( $translations as $translation ) {
			if ( ! is_object( $translation ) || empty( $translation->element_id ) ) {
				continue;
			}

			$group[] = [
				'post_id' => (int) $translation->element_id,
				'lang'    => (string) ( $translation->language_code ?? $this->get_default_language() ),
			];
		}

		return $group;
	}
}
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
