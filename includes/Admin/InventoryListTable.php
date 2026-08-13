<?php
/**
 * SEO inventory admin list table.
 */

namespace Forwp\SeoHelper\Admin;

use Forwp\SeoHelper\Inventory\PriorityLabels;
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

	/** @var string */
	private string $redirect_base = '';

	/** @var int|null|string null = all groups, 1-3 = single, 'queued' = P1-P3 only */
	private $priority_filter = null;

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

	public function set_redirect_base( string $url ): void {
		$this->redirect_base = $url;
	}

	/**
	 * @param int|null|string $priority null, 1–3, or 'queued'.
	 */
	public function set_priority_filter( $priority ): void {
		$this->priority_filter = $priority;
	}

	protected function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="post_ids[]" value="%s" />',
			esc_attr( (string) (int) $item['post_id'] )
		);
	}

	protected function get_bulk_actions(): array {
		return [
			'priority_p1'    => sprintf(
				/* translators: %s: formatted priority tier label */
				__( 'Set %s', '4wp-seo' ),
				PriorityLabels::get_formatted( 1 )
			),
			'priority_p2'    => sprintf(
				__( 'Set %s', '4wp-seo' ),
				PriorityLabels::get_formatted( 2 )
			),
			'priority_p3'    => sprintf(
				__( 'Set %s', '4wp-seo' ),
				PriorityLabels::get_formatted( 3 )
			),
			'priority_clear' => __( 'Clear priority', '4wp-seo' ),
		];
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
		$this->process_bulk_action();

		$per_page = 999;
		$page     = 1;

		$columns  = $this->get_columns();
		$hidden   = get_hidden_columns( $this->screen );
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = [ $columns, $hidden, $sortable ];

		$result = $this->repository->query(
			[
				'page'             => $page,
				'per_page'         => $per_page,
				'post_type'        => (string) ( $this->filters['post_type'] ?? '' ),
				'lang'             => (string) ( $this->filters['lang'] ?? '' ),
				'status'           => (string) ( $this->filters['status'] ?? 'publish' ),
				'missing'          => (string) ( $this->filters['missing'] ?? '' ),
				'search'           => (string) ( $this->filters['search'] ?? '' ),
				'sort_by_priority' => true,
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

	public function display_rows(): void {
		$groups = $this->bucket_items_by_priority();

		if ( empty( $groups ) && empty( $this->items ) ) {
			return;
		}

		$labels = PriorityLabels::get_group_labels();

		$order = $this->get_group_order();

		foreach ( $order as $priority ) {
			if ( ! array_key_exists( $priority, $groups ) ) {
				continue;
			}

			$this->render_priority_group_header( $priority, $labels[ $priority ], $groups[ $priority ] );

			if ( empty( $groups[ $priority ] ) ) {
				$this->render_priority_group_empty( $priority );
				continue;
			}

			foreach ( $groups[ $priority ] as $item ) {
				$this->single_row( $item );
			}
		}
	}

	/**
	 * @return list<int>
	 */
	private function get_group_order(): array {
		if ( is_int( $this->priority_filter ) && $this->priority_filter >= 1 && $this->priority_filter <= 3 ) {
			return [ $this->priority_filter ];
		}

		if ( 'queued' === $this->priority_filter ) {
			return [ 1, 2, 3 ];
		}

		return [ 1, 2, 3, 0 ];
	}

	/**
	 * @return array<int, list<array<string, mixed>>>
	 */
	private function bucket_items_by_priority(): array {
		$groups = [
			1 => [],
			2 => [],
			3 => [],
			0 => [],
		];

		foreach ( $this->items as $item ) {
			$priority = isset( $item['priority'] ) ? (int) $item['priority'] : 0;
			if ( $priority < 1 || $priority > 3 ) {
				$priority = 0;
			}

			if ( is_int( $this->priority_filter ) && $this->priority_filter >= 1 && $this->priority_filter <= 3 ) {
				if ( $priority !== $this->priority_filter ) {
					continue;
				}
			} elseif ( 'queued' === $this->priority_filter && 0 === $priority ) {
				continue;
			}

			$groups[ $priority ][] = $item;
		}

		if ( is_int( $this->priority_filter ) && $this->priority_filter >= 1 && $this->priority_filter <= 3 ) {
			return [ $this->priority_filter => $groups[ $this->priority_filter ] ];
		}

		if ( 'queued' === $this->priority_filter ) {
			return [
				1 => $groups[1],
				2 => $groups[2],
				3 => $groups[3],
			];
		}

		return $groups;
	}

	/**
	 * @param list<array<string, mixed>> $items
	 */
	private function render_priority_group_header( int $priority, string $label, array $items ): void {
		$collapsible = $priority >= 0 && $priority <= 3;
		$count       = count( $items );
		$stats       = $this->compute_group_stats( $items );
		$classes     = 'forwp-seo-priority-group forwp-seo-priority-group--p' . $priority;
		if ( $collapsible ) {
			$classes .= ' forwp-seo-priority-group--collapsible';
		}

		echo '<tr class="' . esc_attr( $classes ) . '" data-priority="' . esc_attr( (string) $priority ) . '" data-dropzone="1"';
		if ( $collapsible ) {
			echo ' aria-expanded="true" role="button" tabindex="0"';
		}
		echo '>';
		echo '<td colspan="' . esc_attr( (string) $this->get_table_column_count() ) . '">';
		echo '<div class="forwp-seo-priority-group__bar">';
		echo '<div class="forwp-seo-priority-group__main">';
		if ( $collapsible ) {
			echo '<span class="forwp-seo-priority-group__chevron" aria-hidden="true">▾</span>';
		}
		echo '<span class="forwp-seo-priority-group__label">' . esc_html( $label ) . '</span>';
		echo '<span class="forwp-seo-priority-group__count">' . esc_html( (string) $count ) . '</span>';
		if ( $priority >= 1 && $priority <= 3 ) {
			echo '<span class="forwp-seo-priority-group__hint">' . esc_html__( 'Drop rows here', '4wp-seo' ) . '</span>';
		}
		echo '</div>';
		echo '<div class="forwp-seo-priority-group__stats">';
		if ( $count > 0 ) {
			printf(
				'<span class="forwp-seo-priority-group__stat forwp-seo-priority-group__avg">%s</span>',
				esc_html(
					sprintf(
						/* translators: %d: average completeness percent */
						__( 'Avg %d%%', '4wp-seo' ),
						$stats['avg']
					)
				)
			);
			echo '<span class="forwp-seo-priority-group__stat forwp-seo-priority-group__gaps"' . ( $stats['gaps'] > 0 ? '' : ' hidden' ) . '>';
			printf(
				esc_html(
					_n(
						'%d with gaps',
						'%d with gaps',
						$stats['gaps'],
						'4wp-seo'
					)
				),
				$stats['gaps']
			);
			echo '</span>';
		}
		echo '</div>';
		echo '</div>';
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * @param list<array<string, mixed>> $items
	 *
	 * @return array{avg: int, gaps: int}
	 */
	private function compute_group_stats( array $items ): array {
		if ( empty( $items ) ) {
			return [
				'avg'  => 0,
				'gaps' => 0,
			];
		}

		$sum  = 0;
		$gaps = 0;

		foreach ( $items as $item ) {
			$sum += (int) ( $item['completeness'] ?? 0 );
			$missing = is_array( $item['missing'] ?? null ) ? $item['missing'] : [];
			if ( ! empty( $missing ) ) {
				++$gaps;
			}
		}

		return [
			'avg'  => (int) round( $sum / count( $items ) ),
			'gaps' => $gaps,
		];
	}

	private function render_priority_group_empty( int $priority ): void {
		echo '<tr class="forwp-seo-priority-group-empty" data-priority-group="' . esc_attr( (string) $priority ) . '" data-dropzone="1">';
		echo '<td colspan="' . esc_attr( (string) $this->get_table_column_count() ) . '">';
		echo esc_html__( 'No items — drop here', '4wp-seo' );
		echo '</td>';
		echo '</tr>';
	}

	private function get_table_column_count(): int {
		$columns = $this->get_columns();
		$count   = count( $columns ) + 1;

		return max( 1, $count );
	}

	private function get_priority_redirect_url(): string {
		if ( '' !== $this->redirect_base ) {
			return $this->redirect_base;
		}

		return admin_url( 'admin.php?page=4wp-seo-inventory' );
	}

	protected function process_bulk_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = $this->current_action();
		if ( ! $action ) {
			return;
		}

		check_admin_referer( 'bulk-' . $this->_args['plural'] );

		$priority_map = [
			'priority_p1'    => 1,
			'priority_p2'    => 2,
			'priority_p3'    => 3,
			'priority_clear' => null,
		];

		if ( ! array_key_exists( $action, $priority_map ) ) {
			return;
		}

		$post_ids = array_map( 'intval', (array) wp_unslash( $_REQUEST['post_ids'] ?? [] ) );
		$post_ids = array_values( array_filter( $post_ids ) );
		if ( empty( $post_ids ) ) {
			return;
		}

		$queue    = new \Forwp\SeoHelper\Inventory\PriorityQueue( $this->repository );
		$priority = $priority_map[ $action ];

		foreach ( $post_ids as $post_id ) {
			if ( null === $this->repository->get_record( $post_id ) ) {
				continue;
			}
			$queue->assign_post( $post_id, $priority );
		}

		\Forwp\SeoHelper\Inventory\PriorityQueue::reset_cache();

		$redirect = add_query_arg( 'forwp_priority_updated', count( $post_ids ), $this->get_priority_redirect_url() );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public function column_wp_title( $item ): string {
		$title = (string) $item['wp_title'];
		$url   = (string) $item['url'];
		$priority = isset( $item['priority'] ) ? (int) $item['priority'] : 0;

		$output = '<span class="forwp-seo-row-drag" aria-hidden="true" title="' . esc_attr__( 'Drag to reorder or change priority', '4wp-seo' ) . '">⠿</span> ';

		if ( $priority >= 1 && $priority <= 3 ) {
			$output .= sprintf(
				'<span class="forwp-seo-priority-badge forwp-seo-priority-badge--p%d" title="%s">%s</span> ',
				$priority,
				esc_attr( PriorityLabels::get_formatted( $priority ) ),
				esc_html( PriorityLabels::get_badge( $priority ) )
			);
		}

		if ( '' !== $url ) {
			$output .= '<strong><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $title ) . '</a></strong>';
		} else {
			$output .= '<strong>' . esc_html( $title ) . '</strong>';
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
		$priority = isset( $item['priority'] ) ? (int) $item['priority'] : 0;
		if ( $priority < 1 || $priority > 3 ) {
			$priority = 0;
		}

		$data = wp_json_encode(
			[
				'post_id'          => (int) $item['post_id'],
				'seo_title'        => (string) ( $item['seo_title'] ?? '' ),
				'meta_description' => (string) ( $item['meta_description'] ?? '' ),
				'focus_keyword'    => (string) ( $item['focus_keyword'] ?? '' ),
				'og_image'         => (string) ( $item['og_image'] ?? '' ),
			]
		);

		echo '<tr class="forwp-seo-inventory-row forwp-seo-inventory-row--draggable';
		if ( ! empty( $item['priority'] ) ) {
			echo ' forwp-seo-inventory-row--queued';
		}
		echo '" data-post-id="' . esc_attr( (string) (int) $item['post_id'] ) . '"';
		echo ' data-priority="' . esc_attr( (string) $priority ) . '"';
		echo ' data-priority-group="' . esc_attr( (string) $priority ) . '"';
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
