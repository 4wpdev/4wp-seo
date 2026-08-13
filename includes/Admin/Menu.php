<?php
/**
 * Admin menu registration.
 */

namespace Forwp\SeoHelper\Admin;

use Forwp\SeoHelper\Inventory\PriorityLabels;
use Forwp\SeoHelper\Inventory\Repository;
use Forwp\SeoHelper\Multilingual\Registry as MultilingualRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Menu {
	public const INVENTORY_PER_PAGE_OPTION = 'forwp_seo_inventory_per_page';

	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'load-4wp-seo_page_4wp-seo-inventory', [ $this, 'inventory_screen_options' ] );
		add_filter(
			'set_screen_option_' . self::INVENTORY_PER_PAGE_OPTION,
			static function ( $screen_option, $option, $value ) {
				unset( $screen_option, $option );
				return max( 1, min( 999, (int) $value ) );
			},
			10,
			3
		);
	}

	public function register_menu(): void {
		add_menu_page(
			__( '4WP SEO Helper', '4wp-seo' ),
			__( '4WP SEO', '4wp-seo' ),
			'manage_options',
			'4wp-seo',
			[ Page::class, 'render' ],
			'dashicons-chart-line',
			30
		);

		add_submenu_page(
			'4wp-seo',
			__( 'SEO Inventory', '4wp-seo' ),
			__( 'SEO Inventory', '4wp-seo' ),
			'manage_options',
			'4wp-seo-inventory',
			[ InventoryPage::class, 'render' ]
		);
	}

	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_4wp-seo' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style(
			'forwp-seo-admin-settings',
			FORWP_SEO_HELPER_URL . 'assets/css/admin-settings.css',
			[ 'wp-components' ],
			FORWP_SEO_HELPER_VERSION
		);

		wp_enqueue_script(
			'forwp-seo-admin-tabs',
			FORWP_SEO_HELPER_URL . 'assets/js/admin-tabs.js',
			[],
			FORWP_SEO_HELPER_VERSION,
			true
		);
	}

	public function inventory_screen_options(): void {
		add_screen_option(
			'per_page',
			[
				'label'   => __( 'Inventory items per page', '4wp-seo' ),
				'default' => 20,
				'option'  => self::INVENTORY_PER_PAGE_OPTION,
			]
		);

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_inventory_assets' ] );
	}

	public function enqueue_inventory_assets(): void {
		$table = new InventoryListTable( new Repository() );
		$table->set_show_language( count( MultilingualRegistry::get_active()->get_languages() ) > 1 );
		$columns = $table->get_columns();
		$screen  = get_current_screen();
		$hidden  = $screen instanceof \WP_Screen ? get_hidden_columns( $screen ) : [];
		$colspan = count( $columns ) + 1;

		wp_enqueue_style(
			'forwp-seo-inventory-priority',
			FORWP_SEO_HELPER_URL . 'assets/css/inventory-priority.css',
			[],
			FORWP_SEO_HELPER_VERSION
		);

		$view = sanitize_key( (string) ( $_GET['view'] ?? 'inventory' ) );

		wp_enqueue_script(
			'forwp-seo-inventory-priority',
			FORWP_SEO_HELPER_URL . 'assets/js/inventory-priority.js',
			[],
			FORWP_SEO_HELPER_VERSION,
			true
		);

		wp_localize_script(
			'forwp-seo-inventory-priority',
			'forwpSeoInventoryPriority',
			[
				'mode'    => 'queue' === $view ? 'queue' : 'inventory',
				'restUrl' => rest_url( 'forwp-seo-helper/v1/seo-inventory/priority-queue' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => [
					'saving'     => __( 'Saving…', '4wp-seo' ),
					'saved'      => __( 'Priority saved.', '4wp-seo' ),
					'error'      => __( 'Could not save priority.', '4wp-seo' ),
					'emptyGroup' => __( 'No items — drop here', '4wp-seo' ),
					'emptyLane'  => __( 'Empty', '4wp-seo' ),
					'oneItem'    => __( '1 item', '4wp-seo' ),
					'items'      => __( 'items', '4wp-seo' ),
					'more'       => __( 'more', '4wp-seo' ),
					'avgScore'   => __( 'Avg %d%%', '4wp-seo' ),
					'withGaps'   => __( '%d with gaps', '4wp-seo' ),
				],
				'priorityLabels' => [
					'1' => PriorityLabels::get_formatted( 1 ),
					'2' => PriorityLabels::get_formatted( 2 ),
					'3' => PriorityLabels::get_formatted( 3 ),
				],
			]
		);

		if ( 'inventory' !== $view && 'queue' !== $view ) {
			return;
		}

		if ( 'inventory' === $view ) {
			wp_enqueue_media();
		}

		wp_enqueue_script(
			'forwp-seo-inventory-quick-edit',
			FORWP_SEO_HELPER_URL . 'assets/js/inventory-quick-edit.js',
			[ 'jquery' ],
			FORWP_SEO_HELPER_VERSION,
			true
		);

		wp_localize_script(
			'forwp-seo-inventory-quick-edit',
			'forwpSeoInventoryQuickEdit',
			[
				'restUrl' => rest_url( 'forwp-seo-helper/v1/seo-inventory/' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'colspan' => max( 1, $colspan ),
				'i18n'    => [
					'quickEdit'    => __( 'Quick Edit', '4wp-seo' ),
					'save'         => __( 'Update', '4wp-seo' ),
					'cancel'       => __( 'Cancel', '4wp-seo' ),
					'saving'       => __( 'Saving…', '4wp-seo' ),
					'error'        => __( 'Could not save changes.', '4wp-seo' ),
					'empty'        => __( 'Empty', '4wp-seo' ),
					'seoTitle'     => __( 'SEO title', '4wp-seo' ),
					'metaDesc'     => __( 'Meta description', '4wp-seo' ),
					'focusKw'      => __( 'Focus keyword', '4wp-seo' ),
					'ogImage'      => __( 'OG image', '4wp-seo' ),
					'selectOgImage'=> __( 'Select image', '4wp-seo' ),
					'useImage'     => __( 'Use image', '4wp-seo' ),
					'removeImage'  => __( 'Remove image', '4wp-seo' ),
					'noImage'      => __( 'No image selected', '4wp-seo' ),
					'noOgImage'    => __( 'No OG image', '4wp-seo' ),
					'viewOgImage'  => __( 'View OG image', '4wp-seo' ),
				],
			]
		);
	}
}


















