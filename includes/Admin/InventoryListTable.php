<?php
/**
 * SEO inventory admin list table.
 */

namespace Forwp\SeoHelper\Admin;

use Forwp\SeoHelper\Inventory\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class InventoryListTable extends \WP_List_Table {
	private Repository $repository;

	/** @var array<string, mixed> */
	private array $filters = [];

	private bool $show_language = false;

	public function __construct( Repository $repository ) {
		$this->repository = $repository;

		parent::__construct(
			[
				'singular' => 'seo_inventory_item',
				'plural'   => 'seo_inventory_items',
				'ajax'     => false,
			]
		);
	}

	/**
	 * @param array<string, mixed> $filters
	 */
	public function set_filters( array $filters ): void {
		$this->filters = $filters;
	}

	public function set_show_language( bool $show ): void {
		$this->show_language = $show;
	}

	public function get_columns(): array {
		$columns = [
			'post_id'          => __( 'ID', '4wp-seo' ),
			'wp_title'         => __( 'Title', '4wp-seo' ),
			'post_type'        => __( 'Type', '4wp-seo' ),
			'seo_title'        => __( 'SEO title', '4wp-seo' ),
			'meta_description' => __( 'Meta description', '4wp-seo' ),
			'focus_keyword'    => __( 'Focus keyword', '4wp-seo' ),
			'og_image'         => __( 'OG image', '4wp-seo' ),
			'completeness'     => __( 'Score', '4wp-seo' ),
			'missing'          => __( 'Missing', '4wp-seo' ),
		];

		if ( $this->show_language ) {
			$columns = array_merge(
				array_slice( $columns, 0, 2, true ),
				[ 'lang' => __( 'Lang', '4wp-seo' ) ],
				array_slice( $columns, 2, null, true )
			);
		}

		return $columns;
	}

	protected function get_sortable_columns(): array {
		return [];
	}

	protected function get_primary_column_name(): string {
		return 'wp_title';
	}

	public function prepare_items(): void {
		$per_page = $this->get_items_per_page( Menu::INVENTORY_PER_PAGE_OPTION, 20 );
		$page     = max( 1, (int) ( $this->filters['page'] ?? 1 ) );

		$columns  = $this->get_columns();
		$hidden   = [];
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = [ $columns, $hidden, $sortable ];

		$result = $this->repository->query(
			[
				'page'      => $page,
				'per_page'  => $per_page,
				'post_type' => (string) ( $this->filters['post_type'] ?? '' ),
				'lang'      => (string) ( $this->filters['lang'] ?? '' ),
				'status'    => (string) ( $this->filters['status'] ?? 'publish' ),
				'missing'   => (string) ( $this->filters['missing'] ?? '' ),
				'search'    => (string) ( $this->filters['search'] ?? '' ),
			]
		);

		$this->items = $result['items'];
		$this->set_pagination_args(
			[
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $result['total'] / $per_page ),
			]
		);
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public function column_wp_title( $item ): string {
		$title = (string) $item['wp_title'];
		$url   = (string) $item['url'];

		if ( '' !== $url ) {
			$output = '<strong><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $title ) . '</a></strong>';
		} else {
			$output = '<strong>' . esc_html( $title ) . '</strong>';
		}

		$post_id = (int) $item['post_id'];
		$actions = [
			'inline' => sprintf(
				'<button type="button" class="button-link forwp-seo-quick-edit-trigger" aria-label="%1$s" aria-expanded="false">%2$s</button>',
				esc_attr(
					sprintf(
						/* translators: %s: post title */
						__( 'Quick edit “%s” inline', '4wp-seo' ),
						$title
					)
				),
				esc_html__( 'Quick Edit', '4wp-seo' )
			),
		];

		$edit_link = get_edit_post_link( $post_id );
		if ( is_string( $edit_link ) ) {
			$actions['edit'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $edit_link ),
				esc_html__( 'Edit', '4wp-seo' )
			);
		}

		if ( '' !== $url ) {
			$actions['view'] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( $url ),
				esc_html__( 'View', '4wp-seo' )
			);
		}

		return $output . $this->row_actions( $actions, true );
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public function single_row( $item ): void {
		$data = wp_json_encode(
			[
				'post_id'          => (int) $item['post_id'],
				'seo_title'        => (string) ( $item['seo_title'] ?? '' ),
				'meta_description' => (string) ( $item['meta_description'] ?? '' ),
				'focus_keyword'    => (string) ( $item['focus_keyword'] ?? '' ),
				'og_image'         => (string) ( $item['og_image'] ?? '' ),
			]
		);

		echo '<tr class="forwp-seo-inventory-row" data-post-id="' . esc_attr( (string) (int) $item['post_id'] ) . '"';
		if ( is_string( $data ) ) {
			echo ' data-item="' . esc_attr( $data ) . '"';
		}
		echo '>';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public function column_og_image( $item ): string {
		$url = (string) ( $item['og_image'] ?? '' );

		if ( '' === $url ) {
			return '<span class="forwp-seo-og-thumb forwp-seo-og-thumb--empty" aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'No OG image', '4wp-seo' ) . '</span>';
		}

		return sprintf(
			'<a href="%1$s" class="forwp-seo-og-thumb" target="_blank" rel="noopener noreferrer">' .
			'<img src="%1$s" alt="" width="48" height="48" loading="lazy" decoding="async" />' .
			'<span class="screen-reader-text">%2$s</span>' .
			'</a>',
			esc_url( $url ),
			esc_html__( 'View OG image', '4wp-seo' )
		);
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'post_id':
				$edit_link = get_edit_post_link( (int) $item['post_id'] );
				if ( is_string( $edit_link ) ) {
					return '<a href="' . esc_url( $edit_link ) . '">' . esc_html( (string) $item['post_id'] ) . '</a>';
				}
				return esc_html( (string) $item['post_id'] );

			case 'completeness':
				$score = (int) $item['completeness'];
				$class = $score >= 75 ? 'good' : ( $score >= 50 ? 'medium' : 'low' );
				return '<span class="forwp-seo-score forwp-seo-score--' . esc_attr( $class ) . '">' . esc_html( (string) $score ) . '%</span>';

			case 'missing':
				$missing = is_array( $item['missing'] ?? null ) ? $item['missing'] : [];
				return esc_html( implode( ', ', $missing ) );

			case 'seo_title':
			case 'meta_description':
			case 'focus_keyword':
				$value = (string) ( $item[ $column_name ] ?? '' );
				if ( '' === $value ) {
					return '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'Empty', '4wp-seo' ) . '</span>';
				}
				return esc_html( wp_html_excerpt( $value, 80, '…' ) );

			default:
				return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
		}
	}

	public function no_items(): void {
		esc_html_e( 'No posts found for the current filters.', '4wp-seo' );
	}
}
