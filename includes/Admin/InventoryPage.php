<?php
/**
 * Admin SEO inventory page.
 */

namespace Forwp\SeoHelper\Admin;

use Forwp\SeoHelper\Inventory\ExcludedPostTypes;
use Forwp\SeoHelper\Inventory\PostTypeDiscovery;
use Forwp\SeoHelper\Inventory\PriorityLabels;
use Forwp\SeoHelper\Inventory\PriorityQueue;
use Forwp\SeoHelper\Inventory\Repository;
use Forwp\SeoHelper\Multilingual\Registry as MultilingualRegistry;
use Forwp\SeoHelper\Core\Release;
use Forwp\SeoHelper\Gsc\Admin as GscAdmin;
use Forwp\SeoHelper\Gsc\Module as GscModule;
use Forwp\SeoHelper\Gsc\ReportPeriod;
use Forwp\SeoHelper\Gsc\Repository as GscRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class InventoryPage {
	public static function init(): void {
		add_action( 'admin_post_forwp_seo_inventory_exclude_type', [ self::class, 'handle_exclude_type' ] );
		add_action( 'admin_post_forwp_seo_inventory_include_type', [ self::class, 'handle_include_type' ] );
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin inventory view switch.
		$view = sanitize_key( wp_unslash( (string) ( $_GET['view'] ?? 'inventory' ) ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $view, [ 'inventory', 'queue' ], true ) ) {
			$view = 'inventory';
		}

		self::render_notices();

		if ( 'queue' === $view ) {
			self::render_queue_view();
			return;
		}

		self::render_inventory_view();
	}

	private static function render_notices(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only PRG success/error flags.
		if ( isset( $_GET['forwp_priority_updated'] ) ) {
			$count = max( 0, absint( wp_unslash( $_GET['forwp_priority_updated'] ) ) );
			if ( $count > 0 ) {
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: %d: number of updated items */
							_n( 'Priority updated for %d item.', 'Priority updated for %d items.', $count, '4wp-seo-helper' ),
							$count
						)
					)
				);
			}
		}

		if ( isset( $_GET['forwp_priority_error'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Could not update priority.', '4wp-seo-helper' ) . '</p></div>';
		}

		if ( isset( $_GET['forwp_type_excluded'] ) ) {
			$slug  = sanitize_key( wp_unslash( (string) $_GET['forwp_type_excluded'] ) );
			$label = self::post_type_label( $slug );
			$undo  = self::include_type_url( $slug );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s %s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: post type label */
						__( '“%s” is excluded from SEO Inventory. This is saved for next time.', '4wp-seo-helper' ),
						$label
					)
				),
				'<a href="' . esc_url( $undo ) . '">' . esc_html__( 'Undo', '4wp-seo-helper' ) . '</a>'
			);
		}

		if ( isset( $_GET['forwp_type_included'] ) ) {
			$slug  = sanitize_key( wp_unslash( (string) $_GET['forwp_type_included'] ) );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: post type label */
						__( '“%s” is shown in SEO Inventory again.', '4wp-seo-helper' ),
						self::post_type_label( $slug )
					)
				)
			);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	private static function render_tabs( string $active ): void {
		$tabs = [
			'inventory' => __( 'Inventory', '4wp-seo-helper' ),
			'queue'     => __( 'Priority queue', '4wp-seo-helper' ),
		];
		?>
		<h2 class="nav-tab-wrapper forwp-seo-inventory__tabs">
			<?php foreach ( $tabs as $slug => $label ) : ?>
				<a
					href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::INVENTORY_PAGE_SLUG . '&view=' . $slug ) ); ?>"
					class="nav-tab<?php echo $active === $slug ? ' nav-tab-active' : ''; ?>"
				><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</h2>
		<?php
	}

	private static function render_inventory_view(): void {
		$repository = new Repository();
		$provider   = MultilingualRegistry::get_active();
		$languages  = $provider->get_languages();
		$show_lang  = count( $languages ) > 1;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin inventory filters.
		$filters    = [
			'page'      => isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1,
			'post_type' => self::get_requested_post_type(),
			'lang'      => sanitize_key( wp_unslash( (string) ( $_GET['lang'] ?? '' ) ) ),
			'status'    => sanitize_key( wp_unslash( (string) ( $_GET['status'] ?? 'publish' ) ) ),
			'missing'   => sanitize_key( wp_unslash( (string) ( $_GET['missing'] ?? '' ) ) ),
			'max_score' => isset( $_GET['max_score'] ) ? absint( wp_unslash( $_GET['max_score'] ) ) : 0,
			'search'    => sanitize_text_field( wp_unslash( (string) ( $_GET['s'] ?? '' ) ) ),
			'post_id'   => isset( $_GET['forwp_post'] ) ? absint( wp_unslash( $_GET['forwp_post'] ) ) : 0,
		];
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$stats = $repository->get_stats(
			[
				'post_type' => $filters['post_type'],
				'status'    => $filters['status'],
			]
		);

		$table = new InventoryListTable( $repository );
		$table->set_show_language( $show_lang );
		$table->set_show_gsc_metrics( self::should_show_gsc_metrics() );
		$table->set_show_gsc_indexing( self::should_show_gsc_indexing() );
		$table->set_filters( $filters );
		$table->set_redirect_base(
			add_query_arg(
				array_filter(
					[
						'page'                       => Menu::INVENTORY_PAGE_SLUG,
						'view'                       => 'inventory',
						Menu::INVENTORY_POST_TYPE_ARG => $filters['post_type'],
						'lang'                       => $filters['lang'],
						'status'                     => $filters['status'],
						'missing'                    => $filters['missing'],
						'max_score'                  => ! empty( $filters['max_score'] ) ? (string) $filters['max_score'] : '',
						's'                          => $filters['search'],
						'paged'                      => $filters['page'] > 1 ? (string) $filters['page'] : '',
					]
				),
				admin_url( 'admin.php' )
			)
		);
		$table->prepare_items();

		$export_url = wp_nonce_url(
			add_query_arg(
				array_filter(
					[
						'action'                     => 'forwp_seo_inventory_export',
						Menu::INVENTORY_POST_TYPE_ARG => $filters['post_type'],
						'lang'                       => $filters['lang'],
						'status'                     => $filters['status'],
						'missing'                    => $filters['missing'],
						'max_score'                  => ! empty( $filters['max_score'] ) ? (string) $filters['max_score'] : '',
						's'                          => $filters['search'],
					]
				),
				admin_url( 'admin-post.php' )
			),
			'forwp_seo_inventory_export'
		);

		?>
		<div class="wrap forwp-seo-inventory">
			<h1><?php esc_html_e( 'SEO Inventory', '4wp-seo-helper' ); ?></h1>
			<p>
				<?php esc_html_e( 'Full list grouped by priority tier (P1 → P2 → P3 → Other). Drag rows to reorder or move between groups. Priority reflects business importance, not SEO score.', '4wp-seo-helper' ); ?>
				<?php if ( self::should_show_gsc_metrics() ) : ?>
					<?php
					printf(
						' %s',
						esc_html(
							sprintf(
								/* translators: %s: date range label */
								__( 'GSC clicks/impressions are joined by URL path from synced data (%s).', '4wp-seo-helper' ),
								ReportPeriod::label()
							)
						)
					);
					?>
				<?php endif; ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::SETTINGS_PAGE_SLUG . '&tab=settings' ) ); ?>"><?php esc_html_e( 'Configure tier names', '4wp-seo-helper' ); ?></a>
				<a class="page-title-action" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', '4wp-seo-helper' ); ?></a>
			</p>

			<?php self::render_tabs( 'inventory' ); ?>

			<?php if ( $filters['post_id'] > 0 ) : ?>
				<div class="notice notice-info">
					<p>
						<?php
						printf(
							/* translators: %s: post title */
							esc_html__( 'Showing “%s” from the toolbar. Pagination is skipped for this jump.', '4wp-seo-helper' ),
							esc_html( get_the_title( $filters['post_id'] ) )
						);
						?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::INVENTORY_PAGE_SLUG . '&view=inventory' ) ); ?>">
							<?php esc_html_e( 'Full inventory', '4wp-seo-helper' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>

			<?php self::render_compact_panels( 'inventory', null ); ?>
			<p class="forwp-seo-inventory__drag-status" aria-live="polite"></p>

			<div class="forwp-seo-inventory__stats">
				<p>
					<strong><?php esc_html_e( 'Posts:', '4wp-seo-helper' ); ?></strong>
					<?php echo esc_html( (string) $stats['posts'] ); ?>
					&nbsp;|&nbsp;
					<strong><?php esc_html_e( 'Avg completeness:', '4wp-seo-helper' ); ?></strong>
					<?php echo esc_html( (string) $stats['avg_completeness'] ); ?>%
				</p>
				<?php if ( $show_lang && ! empty( $stats['by_language'] ) ) : ?>
					<p><strong><?php esc_html_e( 'By language:', '4wp-seo-helper' ); ?></strong>
					<?php
					$parts = [];
					foreach ( $stats['by_language'] as $code => $data ) {
						$parts[] = $code . ': ' . (int) $data['count'] . ' (' . (int) $data['avg_completeness'] . '%)';
					}
					echo esc_html( implode( ' · ', $parts ) );
					?>
					</p>
				<?php endif; ?>
				<?php if ( ! empty( $stats['missing_counts'] ) ) : ?>
					<p><strong><?php esc_html_e( 'Missing fields:', '4wp-seo-helper' ); ?></strong>
					<?php
					$missing_parts = [];
					foreach ( $stats['missing_counts'] as $field => $count ) {
						$missing_parts[] = sprintf( '%s: %d', $field, (int) $count );
					}
					echo esc_html( implode( ' · ', $missing_parts ) );
					?>
					</p>
				<?php endif; ?>
			</div>

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( Menu::INVENTORY_PAGE_SLUG ); ?>" />
				<input type="hidden" name="view" value="inventory" />
				<?php if ( ! empty( $filters['max_score'] ) ) : ?>
					<input type="hidden" name="max_score" value="<?php echo esc_attr( (string) $filters['max_score'] ); ?>" />
				<?php endif; ?>
				<div class="tablenav top forwp-seo-inventory__toolbar">
					<div class="alignleft actions">
						<select name="<?php echo esc_attr( Menu::INVENTORY_POST_TYPE_ARG ); ?>">
							<option value=""><?php esc_html_e( 'All post types', '4wp-seo-helper' ); ?></option>
							<?php foreach ( PostTypeDiscovery::get_labeled() as $type ) : ?>
								<option value="<?php echo esc_attr( $type['slug'] ); ?>" <?php selected( $filters['post_type'], $type['slug'] ); ?>>
									<?php echo esc_html( $type['label'] . ' (' . $type['slug'] . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php if ( $show_lang ) : ?>
						<select name="lang">
							<option value=""><?php esc_html_e( 'All languages', '4wp-seo-helper' ); ?></option>
							<?php foreach ( $languages as $language ) : ?>
								<option value="<?php echo esc_attr( $language['code'] ); ?>" <?php selected( $filters['lang'], $language['code'] ); ?>>
									<?php echo esc_html( $language['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php endif; ?>
						<select name="missing">
							<option value=""><?php esc_html_e( 'Any completeness', '4wp-seo-helper' ); ?></option>
							<option value="any" <?php selected( $filters['missing'], 'any' ); ?>><?php esc_html_e( 'Has missing fields', '4wp-seo-helper' ); ?></option>
							<option value="title" <?php selected( $filters['missing'], 'title' ); ?>><?php esc_html_e( 'Missing title', '4wp-seo-helper' ); ?></option>
							<option value="description" <?php selected( $filters['missing'], 'description' ); ?>><?php esc_html_e( 'Missing description', '4wp-seo-helper' ); ?></option>
							<option value="focus_keyword" <?php selected( $filters['missing'], 'focus_keyword' ); ?>><?php esc_html_e( 'Missing focus keyphrases', '4wp-seo-helper' ); ?></option>
							<option value="og_image" <?php selected( $filters['missing'], 'og_image' ); ?>><?php esc_html_e( 'Missing OG image', '4wp-seo-helper' ); ?></option>
						</select>
						<?php submit_button( __( 'Filter', '4wp-seo-helper' ), '', 'forwp_seo_inventory_filter', false ); ?>
						<?php if ( '' !== $filters['post_type'] ) : ?>
							<a class="button" href="<?php echo esc_url( self::exclude_type_url( $filters['post_type'] ) ); ?>">
								<?php esc_html_e( 'Exclude this type', '4wp-seo-helper' ); ?>
							</a>
						<?php endif; ?>
					</div>
					<p class="search-box">
						<label class="screen-reader-text" for="seo-inventory-search"><?php esc_html_e( 'Search', '4wp-seo-helper' ); ?></label>
						<input type="search" id="seo-inventory-search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" />
						<?php submit_button( __( 'Search', '4wp-seo-helper' ), '', '', false ); ?>
					</p>
				</div>
				<?php self::render_excluded_types_note(); ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	private static function render_compact_panels( string $view, ?int $active_priority ): void {
		$queue = new PriorityQueue();
		$lanes = $queue->get_lanes_with_items()['lanes'];
		?>
		<div class="forwp-seo-priority-compact" id="forwp-seo-priority-board">
			<p class="forwp-seo-priority-compact__hint">
				<?php esc_html_e( 'Business priority lanes — independent of SEO score. Drag rows into groups below, or onto a lane.', '4wp-seo-helper' ); ?>
			</p>
			<div class="forwp-seo-priority-lanes forwp-seo-priority-lanes--compact">
				<?php foreach ( PriorityQueue::LANE_IDS as $lane_id ) : ?>
					<?php
					$lane_int   = (int) $lane_id;
					$is_active  = 'queue' === $view && $active_priority === $lane_int;
					$panel_url  = add_query_arg(
						[
							'page'     => Menu::INVENTORY_PAGE_SLUG,
							'view'     => 'queue',
							'priority' => $lane_id,
						],
						admin_url( 'admin.php' )
					);
					?>
					<div
						class="forwp-seo-priority-lane forwp-seo-priority-lane--compact forwp-seo-priority-lane--strip forwp-seo-priority-lane--p<?php echo esc_attr( $lane_id ); ?><?php echo $is_active ? ' is-active' : ''; ?>"
						data-priority="<?php echo esc_attr( $lane_id ); ?>"
						data-panel-url="<?php echo esc_url( $panel_url ); ?>"
					>
						<div class="forwp-seo-priority-lane__head forwp-seo-priority-lane__head--compact">
							<a class="forwp-seo-priority-lane__label forwp-seo-priority-lane__link" href="<?php echo esc_url( $panel_url ); ?>">
								<?php echo esc_html( PriorityLabels::get_formatted( $lane_int ) ); ?>
							</a>
							<span class="forwp-seo-priority-lane__meta">
								<?php
								printf(
									/* translators: %d: number of queued items */
									esc_html( _n( '%d item', '%d items', count( $lanes[ $lane_id ] ), '4wp-seo-helper' ) ),
									count( $lanes[ $lane_id ] )
								);
								?>
							</span>
						</div>
						<div class="forwp-seo-priority-lane__mini-list">
							<?php if ( empty( $lanes[ $lane_id ] ) ) : ?>
								<span class="forwp-seo-priority-lane__placeholder"><?php esc_html_e( 'Empty', '4wp-seo-helper' ); ?></span>
							<?php else : ?>
								<?php foreach ( array_slice( $lanes[ $lane_id ], 0, 3 ) as $item ) : ?>
									<?php self::render_compact_lane_item( $item ); ?>
								<?php endforeach; ?>
								<?php if ( count( $lanes[ $lane_id ] ) > 3 ) : ?>
									<a class="forwp-seo-priority-lane__more" href="<?php echo esc_url( $panel_url ); ?>">
										<?php
										printf(
											/* translators: %d: additional item count */
											esc_html__( '+%d more', '4wp-seo-helper' ),
											count( $lanes[ $lane_id ] ) - 3
										);
										?>
									</a>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function render_compact_lane_item( array $item ): void {
		$no_focus = ! empty( $item['seo_no_focus'] );
		$seo      = $item['seo_score'] ?? null;
		if ( $no_focus && ( null === $seo || 0 === (int) $seo ) ) {
			$score_label = (string) ( $item['seo_score_label'] ?? __( 'No focus', '4wp-seo-helper' ) );
			$class       = 'na';
		} else {
			$score_label = (string) ( null !== $seo ? (int) $seo : (int) ( $item['completeness'] ?? 0 ) );
			$class       = (int) $score_label >= 71 ? 'good' : ( (int) $score_label >= 41 ? 'medium' : 'low' );
		}
		$title = (string) ( $item['wp_title'] ?? '' );
		?>
		<div
			class="forwp-seo-priority-chip forwp-seo-priority-chip--preview"
			data-post-id="<?php echo esc_attr( (string) (int) ( $item['post_id'] ?? 0 ) ); ?>"
		>
			<span class="forwp-seo-priority-chip__title" title="<?php echo esc_attr( $title ); ?>">
				<?php echo esc_html( wp_html_excerpt( $title, 24, '…' ) ); ?>
			</span>
			<span class="forwp-seo-priority-chip__score forwp-seo-priority-chip__score--<?php echo esc_attr( $class ); ?>">
				<?php echo esc_html( $score_label ); ?>
			</span>
		</div>
		<?php
	}

	private static function render_priority_subnav( ?int $active_priority ): void {
		$items = [
			'all' => __( 'All queued', '4wp-seo-helper' ),
			'1'   => PriorityLabels::get_formatted( 1 ),
			'2'   => PriorityLabels::get_formatted( 2 ),
			'3'   => PriorityLabels::get_formatted( 3 ),
		];
		?>
		<ul class="subsubsub forwp-seo-priority-subnav">
			<?php
			$links = [];
			foreach ( $items as $key => $label ) {
				$args = [
					'page' => Menu::INVENTORY_PAGE_SLUG,
					'view' => 'queue',
				];
				if ( 'all' !== $key ) {
					$args['priority'] = $key;
				}
				$url       = add_query_arg( $args, admin_url( 'admin.php' ) );
				$is_active = ( null === $active_priority && 'all' === $key )
					|| ( is_int( $active_priority ) && (string) $active_priority === $key );
				$links[]   = sprintf(
					'<li><a href="%s"%s>%s</a></li>',
					esc_url( $url ),
					$is_active ? ' class="current"' : '',
					esc_html( $label )
				);
			}
			echo wp_kses_post( implode( ' | ', $links ) );
			?>
		</ul>
		<?php
	}

	private static function render_queue_view(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin queue filter.
		$priority_param = sanitize_key( wp_unslash( (string) ( $_GET['priority'] ?? '' ) ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$active_priority = null;
		if ( in_array( $priority_param, [ '1', '2', '3' ], true ) ) {
			$active_priority = (int) $priority_param;
		}

		$repository = new Repository();
		$provider   = MultilingualRegistry::get_active();
		$show_lang  = count( $provider->get_languages() ) > 1;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin queue page.
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$table = new InventoryListTable( $repository );
		$table->set_show_language( $show_lang );
		$table->set_show_gsc_metrics( self::should_show_gsc_metrics() );
		$table->set_show_gsc_indexing( self::should_show_gsc_indexing() );
		$table->set_filters( [] );
		$table->set_redirect_base(
			add_query_arg(
				array_filter(
					[
						'page'     => Menu::INVENTORY_PAGE_SLUG,
						'view'     => 'queue',
						'priority' => $active_priority ? (string) $active_priority : '',
						'paged'    => $paged > 1 ? (string) $paged : '',
					]
				),
				admin_url( 'admin.php' )
			)
		);
		$table->set_priority_filter( $active_priority ?? 'queued' );
		$table->prepare_items();
		?>
		<div class="wrap forwp-seo-inventory">
			<h1><?php esc_html_e( 'SEO Inventory', '4wp-seo-helper' ); ?></h1>
			<p><?php esc_html_e( 'Priority queue — same table as Inventory, filtered by lane. Drag rows to reorder or reassign.', '4wp-seo-helper' ); ?></p>

			<?php self::render_tabs( 'queue' ); ?>
			<?php self::render_compact_panels( 'queue', $active_priority ); ?>
			<?php self::render_priority_subnav( $active_priority ); ?>
			<p class="forwp-seo-inventory__drag-status" aria-live="polite"></p>

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( Menu::INVENTORY_PAGE_SLUG ); ?>" />
				<input type="hidden" name="view" value="queue" />
				<?php if ( $active_priority ) : ?>
					<input type="hidden" name="priority" value="<?php echo esc_attr( (string) $active_priority ); ?>" />
				<?php endif; ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_exclude_type(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', '4wp-seo-helper' ) );
		}

		check_admin_referer( 'forwp_seo_inventory_exclude_type' );

		$slug = self::get_requested_post_type();
		if ( '' !== $slug ) {
			ExcludedPostTypes::add( $slug );
		}

		wp_safe_redirect(
			add_query_arg(
				array_filter(
					[
						'page'                 => Menu::INVENTORY_PAGE_SLUG,
						'view'                 => 'inventory',
						'forwp_type_excluded'  => $slug,
					]
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function handle_include_type(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', '4wp-seo-helper' ) );
		}

		check_admin_referer( 'forwp_seo_inventory_include_type' );

		$slug = self::get_requested_post_type();
		if ( '' !== $slug ) {
			ExcludedPostTypes::remove( $slug );
		}

		wp_safe_redirect(
			add_query_arg(
				array_filter(
					[
						'page'                 => Menu::INVENTORY_PAGE_SLUG,
						'view'                 => 'inventory',
						'forwp_type_included'  => $slug,
						Menu::INVENTORY_POST_TYPE_ARG => $slug,
					]
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private static function exclude_type_url( string $slug ): string {
		return wp_nonce_url(
			add_query_arg(
				[
					'action'                  => 'forwp_seo_inventory_exclude_type',
					Menu::INVENTORY_POST_TYPE_ARG => $slug,
				],
				admin_url( 'admin-post.php' )
			),
			'forwp_seo_inventory_exclude_type'
		);
	}

	private static function include_type_url( string $slug ): string {
		return wp_nonce_url(
			add_query_arg(
				[
					'action'                  => 'forwp_seo_inventory_include_type',
					Menu::INVENTORY_POST_TYPE_ARG => $slug,
				],
				admin_url( 'admin-post.php' )
			),
			'forwp_seo_inventory_include_type'
		);
	}

	private static function post_type_label( string $slug ): string {
		$object = get_post_type_object( $slug );
		if ( $object instanceof \WP_Post_Type ) {
			return (string) $object->labels->singular_name;
		}

		return $slug;
	}

	private static function render_excluded_types_note(): void {
		$excluded = ExcludedPostTypes::get();
		if ( [] === $excluded ) {
			return;
		}

		$parts = [];
		foreach ( $excluded as $slug ) {
			$parts[] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( self::include_type_url( $slug ) ),
				esc_html( self::post_type_label( $slug ) )
			);
		}

		echo '<p class="forwp-seo-inventory__excluded">';
		echo esc_html__( 'Excluded post types:', '4wp-seo-helper' );
		echo ' ';
		echo wp_kses_post( implode( ', ', $parts ) );
		echo ' — ';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . Menu::SETTINGS_PAGE_SLUG . '&tab=settings' ) ) . '">';
		echo esc_html__( 'manage in Settings', '4wp-seo-helper' );
		echo '</a>';
		echo '</p>';
	}

	public static function url_for_post( int $post_id ): string {
		$post_id = absint( $post_id );
		$url     = add_query_arg(
			[
				'page'       => Menu::INVENTORY_PAGE_SLUG,
				'view'       => 'inventory',
				'forwp_post' => $post_id,
			],
			admin_url( 'admin.php' )
		);

		return $url . '#forwp-seo-row-' . $post_id;
	}

	public static function get_requested_post_type(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only inventory type filter.
		if ( isset( $_GET[ Menu::INVENTORY_POST_TYPE_ARG ] ) ) {
			$raw = (string) wp_unslash( $_GET[ Menu::INVENTORY_POST_TYPE_ARG ] );
		} elseif ( isset( $_GET['post_type'] ) ) {
			$raw = (string) wp_unslash( $_GET['post_type'] );
		} else {
			$raw = '';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return sanitize_key( $raw );
	}

	public static function should_show_gsc_metrics(): bool {
		if ( ! Release::is_module_public( Release::MODULE_GSC ) || ! GscModule::get_instance()->is_enabled() ) {
			return false;
		}

		$admin = GscAdmin::get_instance();
		if ( ! $admin->is_menu_visible() ) {
			return false;
		}

		$property = GscAdmin::get_site_property();
		if ( '' === $property ) {
			return false;
		}

		return ( new GscRepository() )->has_fact_period(
			$property,
			'web',
			ReportPeriod::period_key_current()
		);
	}

	public static function should_show_gsc_indexing(): bool {
		if ( ! Release::is_module_public( Release::MODULE_GSC ) || ! GscModule::get_instance()->is_enabled() ) {
			return false;
		}

		$admin = GscAdmin::get_instance();
		if ( ! $admin->is_connected() ) {
			return false;
		}

		return '' !== GscAdmin::get_site_property();
	}
}
