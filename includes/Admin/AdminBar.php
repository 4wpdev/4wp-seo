<?php
/**
 * Frontend toolbar: short page checklist (title, last indexed, GSC).
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

		$post_id = self::current_post_id();
		if ( $post_id <= 0 || ! GscModule::get_instance()->is_enabled() || ! GscAdmin::get_instance()->is_connected() ) {
			return;
		}

		wp_enqueue_script(
			'forwp-seo-admin-bar-gsc',
			FORWP_SEO_HELPER_URL . 'assets/js/admin-bar-gsc.js',
			[],
			FORWP_SEO_HELPER_VERSION,
			true
		);

		$stored_link = '';
		$raw         = get_post_meta( $post_id, Indexing::META_LAST_STATUS, true );
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$stored_link = esc_url_raw( (string) ( $decoded['inspectLink'] ?? '' ) );
			}
		}

		wp_localize_script(
			'forwp-seo-admin-bar-gsc',
			'forwpSeoAdminBarGsc',
			[
				'postId'      => $post_id,
				'restRoot'    => rest_url( 'forwp-seo/v1/' ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'storedUrl'   => PropertyResolver::is_valid_inspection_result_link( $stored_link ) ? $stored_link : '',
				'propertyUrl' => PropertyResolver::search_console_property_url( GscAdmin::get_site_property() ),
				'i18n'        => [
					'opening' => __( 'Opening Search Console…', '4wp-seo-helper' ),
					'error'   => __( 'Search Console inspect failed. Open your property in GSC and paste the URL manually.', '4wp-seo-helper' ),
				],
			]
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
				'href'  => $post_id > 0 ? InventoryPage::url_for_post( $post_id ) : admin_url( 'admin.php?page=' . Menu::PAGE_SLUG ),
				'title' => self::root_title( $seo ),
				'meta'  => [
					'class' => 'forwp-seo-admin-bar-root',
					'title' => __( '4WP SEO', '4wp-seo-helper' ),
				],
			]
		);

		if ( $post_id > 0 && is_array( $seo ) ) {
			self::add_post_items( $wp_admin_bar, $post_id, $seo );
			return;
		}

		$wp_admin_bar->add_node(
			[
				'parent' => self::NODE_ID,
				'id'     => self::NODE_ID . '-inventory',
				'title'  => __( 'SEO Inventory', '4wp-seo-helper' ),
				'href'   => admin_url( 'admin.php?page=' . Menu::INVENTORY_PAGE_SLUG ),
			]
		);
	}

	/**
	 * @param array<string, mixed> $seo SEO payload.
	 */
	private static function add_post_items( \WP_Admin_Bar $wp_admin_bar, int $post_id, array $seo ): void {
		$title = get_the_title( $post_id );
		$url   = (string) get_permalink( $post_id );
		if ( '' === $title ) {
			$title = '#' . $post_id;
		}

		$wp_admin_bar->add_node(
			[
				'parent' => self::NODE_ID,
				'id'     => self::NODE_ID . '-page',
				'title'  => self::row_label( __( 'Page', '4wp-seo-helper' ), $title ),
				'href'   => '' !== $url ? $url : '',
				'meta'   => [
					'title' => __( 'Open this page', '4wp-seo-helper' ),
				],
			]
		);

		$indexed = self::last_indexed_label( $post_id );
		$wp_admin_bar->add_node(
			[
				'parent' => self::NODE_ID,
				'id'     => self::NODE_ID . '-indexed',
				'title'  => self::row_label( __( 'Last indexed', '4wp-seo-helper' ), $indexed['label'] ),
				'href'   => false,
				'meta'   => [
					'title' => $indexed['title'],
				],
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
					'meta'   => [
						'class'  => 'forwp-seo-ab-gsc-link',
						'target' => '_blank',
						'rel'    => 'noopener noreferrer',
						'title'  => __( 'Open URL Inspection in Google Search Console', '4wp-seo-helper' ),
					],
				]
			);
		}

		$wp_admin_bar->add_node(
			[
				'parent' => self::NODE_ID,
				'id'     => self::NODE_ID . '-inventory',
				'title'  => __( 'This URL in Inventory', '4wp-seo-helper' ),
				'href'   => InventoryPage::url_for_post( $post_id ),
			]
		);

		$edit = get_edit_post_link( $post_id, 'raw' );
		if ( is_string( $edit ) && '' !== $edit ) {
			$wp_admin_bar->add_node(
				[
					'parent' => self::NODE_ID,
					'id'     => self::NODE_ID . '-edit',
					'title'  => __( 'Edit', '4wp-seo-helper' ),
					'href'   => $edit,
				]
			);
		}

		unset( $seo );
	}

	/**
	 * @param array<string, mixed>|null $seo SEO payload.
	 */
	private static function root_title( ?array $seo ): string {
		$icon = '<span class="ab-icon dashicons dashicons-chart-line" aria-hidden="true"></span>';
		$dots = '';

		if ( is_array( $seo ) ) {
			$dots = self::dot( self::tone( $seo['seo'] ?? null, ! empty( $seo['noFocus'] ) ), __( 'SEO score', '4wp-seo-helper' ) );
		}

		return $icon . $dots . '<span class="screen-reader-text">' . esc_html__( '4WP SEO', '4wp-seo-helper' ) . '</span>';
	}

	private static function row_label( string $label, string $value ): string {
		return '<span class="forwp-seo-ab-label">' . esc_html( $label ) . '</span> '
			. '<span class="forwp-seo-ab-value">' . esc_html( $value ) . '</span>';
	}

	/**
	 * @return array{label:string,title:string}
	 */
	private static function last_indexed_label( int $post_id ): array {
		$fields = Indexing::inventory_fields( $post_id );
		$crawl  = (string) ( $fields['gsc_last_crawl'] ?? '' );
		if ( '' !== $crawl ) {
			$formatted = self::format_time( $crawl );
			return [
				'label' => $formatted,
				'title' => sprintf(
					/* translators: %s: last crawl datetime */
					__( 'Google last crawled this URL on %s', '4wp-seo-helper' ),
					$formatted
				),
			];
		}

		$requested = (int) ( $fields['gsc_index_requested_at'] ?? 0 );
		if ( $requested > 0 ) {
			$formatted = wp_date( 'd.m.Y H:i', $requested );
			return [
				'label' => sprintf(
					/* translators: %s: datetime when indexing was requested */
					__( 'Requested %s', '4wp-seo-helper' ),
					is_string( $formatted ) ? $formatted : ''
				),
				'title' => __( 'Indexing requested, waiting for crawl', '4wp-seo-helper' ),
			];
		}

		return [
			'label' => __( 'Not yet', '4wp-seo-helper' ),
			'title' => __( 'No crawl or index request recorded', '4wp-seo-helper' ),
		];
	}

	/**
	 * @return array{title:string,href:string}|null
	 */
	private static function gsc_row( int $post_id ): ?array {
		if ( ! GscModule::get_instance()->is_enabled() || ! GscAdmin::get_instance()->is_connected() ) {
			return null;
		}

		$raw      = get_post_meta( $post_id, Indexing::META_LAST_STATUS, true );
		$coverage = '';
		$link     = '';
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$coverage = sanitize_text_field( (string) ( $decoded['coverage'] ?? '' ) );
				$link     = esc_url_raw( (string) ( $decoded['inspectLink'] ?? '' ) );
			}
		}

		$site = GscAdmin::get_site_property();
		$url  = (string) get_permalink( $post_id );
		if ( '' !== $site ) {
			$url = PropertyResolver::rewrite_url_for_property( $url, $site );
		}
		unset( $url );

		$href = PropertyResolver::is_valid_inspection_result_link( $link )
			? $link
			: '#forwp-seo-gsc-inspect';
		if ( '#forwp-seo-gsc-inspect' === $href && '' === PropertyResolver::search_console_property_url( $site ) ) {
			$href = admin_url( 'admin.php?page=' . Menu::SETTINGS_PAGE_SLUG . '&tab=gsc' );
		}

		$status = '' !== $coverage ? $coverage : __( 'Inspect URL', '4wp-seo-helper' );

		return [
			'title' => self::dot( self::gsc_tone( $coverage ), __( 'Search Console', '4wp-seo-helper' ) )
				. '<span class="forwp-seo-ab-label">' . esc_html__( 'Search Console', '4wp-seo-helper' ) . '</span>'
				. '<span class="forwp-seo-ab-value">' . esc_html( $status ) . '</span>',
			'href'  => $href,
		];
	}

	private static function format_time( string $iso ): string {
		$ts = strtotime( $iso );
		if ( false === $ts ) {
			return $iso;
		}

		$formatted = wp_date( 'd.m.Y H:i', $ts );
		return is_string( $formatted ) ? $formatted : $iso;
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

		return [
			'seo'     => $scores['seo'],
			'noFocus' => (bool) $scores['no_focus'],
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
