<?php
/**
 * Admin CSV export for SEO inventory.
 */

namespace Forwp\SeoHelper\Admin;

use Forwp\SeoHelper\Inventory\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class InventoryExport {
	public static function init(): void {
		add_action( 'admin_post_forwp_seo_inventory_export', [ self::class, 'handle' ] );
	}

	public static function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', '4wp-seo' ) );
		}

		check_admin_referer( 'forwp_seo_inventory_export' );

		$repository = new Repository();
		$args       = [
			'page'      => 1,
			'per_page'  => 200,
			'post_type' => sanitize_key( (string) ( $_GET['post_type'] ?? '' ) ),
			'lang'      => sanitize_key( (string) ( $_GET['lang'] ?? '' ) ),
			'status'    => sanitize_key( (string) ( $_GET['status'] ?? 'publish' ) ),
			'missing'   => sanitize_key( (string) ( $_GET['missing'] ?? '' ) ),
			'search'    => sanitize_text_field( (string) ( $_GET['s'] ?? '' ) ),
		];

		$all_items = [];
		do {
			$result    = $repository->query( $args );
			$all_items = array_merge( $all_items, $result['items'] );
			++$args['page'];
		} while ( ! empty( $result['items'] ) && $args['page'] <= 50 );

		$columns = [
			'post_id',
			'lang',
			'post_type',
			'status',
			'wp_title',
			'url',
			'seo_title',
			'meta_description',
			'focus_keyword',
			'completeness',
			'missing',
		];

		$lines = [ implode( ',', $columns ) ];
		foreach ( $all_items as $item ) {
			$row = [];
			foreach ( $columns as $column ) {
				$value = $item[ $column ] ?? '';
				if ( 'missing' === $column && is_array( $value ) ) {
					$value = implode( '|', $value );
				}
				$row[] = self::csv_escape( (string) $value );
			}
			$lines[] = implode( ',', $row );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="seo-inventory.csv"' );
		echo implode( "\n", $lines );
		exit;
	}

	private static function csv_escape( string $value ): string {
		$value = str_replace( '"', '""', $value );
		return '"' . $value . '"';
	}
}
