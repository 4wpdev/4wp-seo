<?php
/**
 * Single-language site fallback.
 */

namespace Forwp\SeoHelper\Multilingual;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SingleSiteProvider implements ProviderInterface {
	public function get_id(): string {
		return 'single';
	}

	public function get_label(): string {
		return __( 'Single language', '4wp-seo' );
	}

	public function is_available(): bool {
		return true;
	}

	public function get_languages(): array {
		$locale = determine_locale();
		$code   = substr( $locale, 0, 2 );

		return [
			[
				'code'        => $code,
				'name'        => $locale,
				'is_default'  => true,
			],
		];
	}

	public function get_default_language(): string {
		$languages = $this->get_languages();
		return $languages[0]['code'] ?? 'en';
	}

	public function get_post_language( int $post_id ): string {
		return $this->get_default_language();
	}

	public function get_translation_group( int $post_id ): array {
		return [
			[
				'post_id' => $post_id,
				'lang'    => $this->get_post_language( $post_id ),
			],
		];
	}
}
