<?php
/**
 * Frontend/admin toolbar summary (Yoast-style scores + GSC shortcuts).
 *
 * Isolated like 4WP Weather Admin_Bar_Weather: small feature, separate from settings screens.
 */

namespace Forwp\SeoHelper\Admin;

use Forwp\SeoHelper\Gsc\Admin as GscAdmin;
use Forwp\SeoHelper\Gsc\Indexing;
use Forwp\SeoHelper\Gsc\Module as GscModule;
use Forwp\SeoHelper\Gsc\PropertyResolver;
use Forwp\SeoHelper\SeoMeta\Registry as SeoMetaRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AdminBar {
	public const NODE_ID = 'forwp-seo-admin-bar';

	public static function boot(): void {
		add_action( 'admin_bar_menu', [ self::class, 'register' ], 95 );
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}

	public static function enqueue_assets(): void {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_style(
			'forwp-seo-admin-bar',
			FORWP_SEO_HELPER_URL . 'assets/css/admin-bar.css',
			[],
			FORWP_SEO_HELPER_VERSION
		);
	}

	public static function register( \WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$post_id = self::current_post_id();
		$seo     = $post_id > 0 ? self::seo_payload( $post_id ) : null;

		$wp_admin_bar->add_node(
			[
				'id'    => self::NODE_ID,
				'href'  => admin_url( 'admin.php?page=' . Menu::PAGE_SLUG ),
				'title' => self::root_title( $seo ),
				'meta'  => [
					'class' => 'forwp-seo-admin-bar-root',
					'title' => __( '4WP SEO Helper', '4wp-seo-helper' ),
				],
			]
		);

		if ( $post_id > 0 && is_array( $seo ) ) {
			self::add_post_items( $wp_admin_bar, $post_id, $seo );
		}

		$wp_admin_bar->add_node(
			[
				'parent' => self::NODE_ID,
				'id'     => self::NODE_ID . '-inventory',
				'title'  => __( 'SEO Inventory', '4wp-seo-helper' ),
				'href'   => admin_url( 'admin.php?page=' . Menu::INVENTORY_PAGE_SLUG ),
			]
		);

		$wp_admin_bar->add_node(
			[
				'parent' => self::NODE_ID,
				'id'     => self::NODE_ID . '-settings',
				'title'  => __( 'Settings', '4wp-seo-helper' ),
				'href'   => admin_url( 'admin.php?page=' . Menu::SETTINGS_PAGE_SLUG ),
			]
		);
	}

	/**
	 * @param array<string, mixed> $seo SEO payload.
	 */
	private static function add_post_items( \WP_Admin_Bar $wp_admin_bar, int $post_id, array $seo ): void {
		$keyword = trim( (string) ( $seo['focusKeyword'] ?? '' ) );
		$wp_admin_bar->add_node(
			[
				'parent' => self::NODE_ID,
				'id'     => self::NODE_ID . '-keyword',
				'title'  => sprintf(
					/* translators: %s: focus keyphrase or em dash */
					__( 'Focus keyphrase: %s', '4wp-seo-helper' ),
					'' !== $keyword ? $keyword : '—'
				),
				'href'   => get_edit_post_link( $post_id, 'raw' ),
			]
		);

		$wp_admin_bar->add_node(
			[
				'parent' => self::NODE_ID,
				'id'     => self::NODE_ID . '-seo',
				'title'  => self::score_row(
					__( 'SEO score', '4wp-seo-helper' ),
					$seo['seo'] ?? null,
					! empty( $seo['noFocus'] ),
					(string) ( $seo['label'] ?? '' )
				),
				'href'   => get_edit_post_link( $post_id, 'raw' ),
			]
		);

		$wp_admin_bar->add_node(
			[
				'parent' => self::NODE_ID,
				'id'     => self::NODE_ID . '-read',
				'title'  => self::score_row(
					__( 'Readability', '4wp-seo-helper' ),
					$seo['readability'] ?? null,
					false,
					''
				),
				'href'   => get_edit_post_link( $post_id, 'raw' ),
			]
		);

		$gsc = self::gsc_row( $post_id );
		if ( is_array( $gsc ) ) {
			$wp_admin_bar->add_node(
				[
					'parent' => self::NODE_ID,
					'id'     => self::NODE_ID . '-gsc',
					'title'  => $gsc['title'],
					'href'   => $gsc['href'],
					'meta'   => str_starts_with( $gsc['href'], 'https://search.google.com/' )
						? [
							'target' => '_blank',
							'rel'    => 'noopener noreferrer',
						]
						: [],
				]
			);
		}

		$edit = get_edit_post_link( $post_id, 'raw' );
		if ( is_string( $edit ) && '' !== $edit ) {
			$wp_admin_bar->add_node(
				[
					'parent' => self::NODE_ID,
					'id'     => self::NODE_ID . '-edit',
					'title'  => __( 'Edit in 4WP SEO Helper', '4wp-seo-helper' ),
					'href'   => $edit,
				]
			);
		}
	}

	/**
	 * @param array<string, mixed>|null $seo SEO payload.
	 */
	private static function root_title( ?array $seo ): string {
		$icon = '<span class="ab-icon dashicons dashicons-chart-line" aria-hidden="true"></span>';
		$dots = '';

		if ( is_array( $seo ) ) {
			$dots  = self::dot( self::tone( $seo['seo'] ?? null, ! empty( $seo['noFocus'] ) ), __( 'SEO score', '4wp-seo-helper' ) );
			$dots .= self::dot( self::tone( $seo['readability'] ?? null, false ), __( 'Readability', '4wp-seo-helper' ) );
		}

		return $icon . $dots . '<span class="screen-reader-text">' . esc_html__( '4WP SEO Helper', '4wp-seo-helper' ) . '</span>';
	}

	private static function score_row( string $label, $score, bool $no_focus, string $title ): string {
		$tone  = self::tone( $score, $no_focus );
		$value = $no_focus || null === $score || '' === $score
			? '—'
			: (string) (int) $score;

		$hint = '' !== $title ? $title : $label;

		return self::dot( $tone, $hint ) . '<span class="forwp-seo-ab-label">' . esc_html( $label ) . '</span>'
			. '<span class="forwp-seo-ab-value">' . esc_html( $value ) . '</span>';
	}

	/**
	 * @return array{title:string,href:string}|null
	 */
	private static function gsc_row( int $post_id ): ?array {
		if ( ! GscModule::get_instance()->is_enabled() || ! GscAdmin::get_instance()->is_connected() ) {
			return null;
		}

		$raw = get_post_meta( $post_id, Indexing::META_LAST_STATUS, true );
		$coverage = '';
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$coverage = sanitize_text_field( (string) ( $decoded['coverage'] ?? '' ) );
			}
		}

		$site = GscAdmin::get_site_property();
		$url  = (string) get_permalink( $post_id );
		if ( '' !== $site ) {
			$url = PropertyResolver::rewrite_url_for_property( $url, $site );
		}

		$href = PropertyResolver::search_console_inspect_url( $site, $url );
		if ( '' === $href ) {
			$href = admin_url( 'admin.php?page=' . Menu::SETTINGS_PAGE_SLUG . '&tab=gsc' );
		}

		$status = '' !== $coverage ? $coverage : __( 'Not inspected yet', '4wp-seo-helper' );

		return [
			'title' => self::dot( self::gsc_tone( $coverage ), __( 'Search Console', '4wp-seo-helper' ) )
				. '<span class="forwp-seo-ab-label">' . esc_html__( 'Search Console', '4wp-seo-helper' ) . '</span>'
				. '<span class="forwp-seo-ab-value">' . esc_html( $status ) . '</span>',
			'href'  => $href,
		];
	}

	private static function dot( string $tone, string $label ): string {
		return '<span class="forwp-seo-ab-dot forwp-seo-ab-dot--' . esc_attr( $tone ) . '" title="' . esc_attr( $label ) . '" aria-hidden="true"></span>';
	}

	private static function tone( $score, bool $no_focus ): string {
		if ( $no_focus || null === $score || '' === $score ) {
			return 'na';
		}

		$n = (int) $score;
		if ( $n >= 71 ) {
			return 'good';
		}
		if ( $n >= 41 ) {
			return 'ok';
		}

		return 'bad';
	}

	private static function gsc_tone( string $coverage ): string {
		if ( '' === $coverage ) {
			return 'na';
		}
		if ( preg_match( '/indexed/i', $coverage ) && ! preg_match( '/not indexed|excluded/i', $coverage ) ) {
			return 'good';
		}
		if ( preg_match( '/not indexed|excluded|error/i', $coverage ) ) {
			return 'bad';
		}

		return 'ok';
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function seo_payload( int $post_id ): array {
		$adapter = SeoMetaRegistry::get_active();
		$scores  = $adapter->read_scores( $post_id );
		$meta    = $adapter->read( $post_id );

		return [
			'seo'           => $scores['seo'],
			'readability'   => $scores['readability'],
			'label'         => (string) $scores['label'],
			'noFocus'       => (bool) $scores['no_focus'],
			'focusKeyword'  => (string) ( $meta['focus_keyword'] ?? '' ),
		];
	}

	private static function current_post_id(): int {
		if ( is_admin() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin bar identifies the post being edited.
			$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
			return $post_id > 0 ? $post_id : 0;
		}

		if ( is_singular() ) {
			return (int) get_queried_object_id();
		}

		return 0;
	}
}
