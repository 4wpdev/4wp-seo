<?php
/**
 * Dashboard: compact SEO change checklist.
 */

namespace Forwp\SeoHelper\Admin;

use Forwp\SeoHelper\Gsc\Indexing;
use Forwp\SeoHelper\Inventory\DashboardStats;
use Forwp\SeoHelper\Inventory\History;
use Forwp\SeoHelper\Inventory\HistoryLogger;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DashboardPage {
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$heading_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28" fill="none" focusable="false" aria-hidden="true"><path d="M3 3v18h18" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><path d="m7 14 4-4 3 3 5-6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only dashboard post filter.
		$post_id = absint( wp_unslash( (string) ( $_GET['history_post'] ?? '' ) ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap forwp-seo-admin-shell forwp-seo-admin-shell--wide">
			<h1 class="forwp-seo-admin-heading">
				<span class="forwp-seo-admin-heading__icon" aria-hidden="true">
					<?php echo wp_kses( $heading_svg, Page::svg_allowed_html() ); ?>
				</span>
				<span class="forwp-seo-admin-heading__text"><?php esc_html_e( 'Dashboard', '4wp-seo-helper' ); ?></span>
			</h1>
			<p class="forwp-seo-admin-lead">
				<?php esc_html_e( 'Site health at a glance: weak URLs, new pages vs last week, and a per-URL change checklist.', '4wp-seo-helper' ); ?>
			</p>
			<?php
			if ( $post_id > 0 ) {
				self::render_post_history( $post_id );
			} else {
				self::render_overview();
			}
			?>
		</div>
		<?php
	}

	private static function render_overview(): void {
		$stats    = DashboardStats::collect();
		$waiting  = History::waiting_for_crawl( 8 );
		$recent   = History::recent( 12 );
		$gsc      = is_array( $stats['gsc'] ?? null ) ? $stats['gsc'] : null;
		$growth   = is_array( $stats['growth'] ?? null ) ? $stats['growth'] : [];
		$missing  = is_array( $stats['missing'] ?? null ) ? $stats['missing'] : [];
		$weakest  = is_array( $stats['weakest'] ?? null ) ? $stats['weakest'] : [];
		$types    = is_array( $stats['types'] ?? null ) ? $stats['types'] : [];
		?>
		<div class="forwp-seo-status-grid">
			<?php
			self::render_kpi_card(
				number_format_i18n( (int) $stats['posts'] ),
				__( 'Indexed inventory URLs', '4wp-seo-helper' ),
				self::format_delta( (array) ( $stats['delta']['posts'] ?? [] ), true ),
				''
			);
			self::render_kpi_card(
				number_format_i18n( (int) $stats['weak'] ),
				__( 'Weak pages (score under 50)', '4wp-seo-helper' ),
				self::format_delta( (array) ( $stats['delta']['weak'] ?? [] ), false ),
				(int) $stats['weak'] > 0 ? 'is-alert' : 'is-ok',
				(string) $stats['weak_url']
			);
			self::render_kpi_card(
				number_format_i18n( (int) ( $growth['week'] ?? 0 ) ),
				__( 'New pages this week', '4wp-seo-helper' ),
				sprintf(
					/* translators: 1: signed week-over-week change, 2: previous week count */
					__( '%1$s vs last week (%2$s)', '4wp-seo-helper' ),
					self::format_delta( (array) ( $growth['week_delta'] ?? [] ), true ),
					number_format_i18n( (int) ( $growth['prev_week'] ?? 0 ) )
				),
				(int) ( $growth['week'] ?? 0 ) > 0 ? 'is-up' : ''
			);
			self::render_kpi_card(
				(int) $stats['avg'] . '%',
				__( 'Average SEO score', '4wp-seo-helper' ),
				self::format_delta( (array) ( $stats['delta']['avg'] ?? [] ), true ),
				''
			);
			self::render_kpi_card(
				number_format_i18n( (int) $stats['gaps'] ),
				__( 'Pages with SEO gaps', '4wp-seo-helper' ),
				self::format_delta( (array) ( $stats['delta']['gaps'] ?? [] ), false ),
				(int) $stats['gaps'] > 0 ? 'is-warn' : '',
				(string) $stats['gaps_url']
			);
			if ( is_array( $gsc ) ) {
				$click_delta = is_array( $gsc['change']['clicks'] ?? null ) ? $gsc['change']['clicks'] : [];
				self::render_kpi_card(
					number_format_i18n( (int) $gsc['clicks'] ),
					sprintf(
						/* translators: %s: GSC date range label */
						__( 'Search clicks (%s)', '4wp-seo-helper' ),
						(string) $gsc['label']
					),
					self::format_delta( $click_delta, true ),
					''
				);
			}
			?>
		</div>

		<div class="forwp-seo-dash-split">
			<div class="forwp-seo-panel">
				<h2><?php esc_html_e( 'Weakest pages', '4wp-seo-helper' ); ?></h2>
				<?php if ( [] === $weakest ) : ?>
					<p class="forwp-seo-admin-muted"><?php esc_html_e( 'No inventory URLs yet.', '4wp-seo-helper' ); ?></p>
				<?php else : ?>
					<table class="widefat striped forwp-seo-dash-weak">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Page', '4wp-seo-helper' ); ?></th>
								<th><?php esc_html_e( 'Score', '4wp-seo-helper' ); ?></th>
								<th><?php esc_html_e( 'Gaps', '4wp-seo-helper' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $weakest as $row ) : ?>
								<?php
								$score   = (int) ( $row['score'] ?? 0 );
								$is_weak = $score < DashboardStats::WEAK_SCORE;
								$gaps    = is_array( $row['missing'] ?? null ) ? $row['missing'] : [];
								?>
								<tr class="<?php echo $is_weak ? 'is-weak' : ''; ?>">
									<td>
										<a href="<?php echo esc_url( self::post_history_url( (int) $row['post_id'] ) ); ?>">
											<?php echo esc_html( (string) ( $row['title'] ?? '#' . $row['post_id'] ) ); ?>
										</a>
									</td>
									<td>
										<span class="forwp-seo-dash-score<?php echo $is_weak ? ' is-weak' : ''; ?>">
											<?php echo esc_html( (string) $score ); ?>%
										</span>
									</td>
									<td><?php echo esc_html( [] === $gaps ? '—' : implode( ', ', $gaps ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="forwp-seo-dash-more">
						<a href="<?php echo esc_url( (string) $stats['weak_url'] ); ?>">
							<?php esc_html_e( 'Open all weak pages in Inventory', '4wp-seo-helper' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>

			<div class="forwp-seo-panel">
				<h2><?php esc_html_e( 'Page growth', '4wp-seo-helper' ); ?></h2>
				<ul class="forwp-seo-dash-legend">
					<li>
						<strong><?php echo esc_html( number_format_i18n( (int) ( $growth['week'] ?? 0 ) ) ); ?></strong>
						<?php esc_html_e( 'published in the last 7 days', '4wp-seo-helper' ); ?>
						<span class="forwp-seo-admin-muted">
							<?php echo esc_html( self::format_delta( (array) ( $growth['week_delta'] ?? [] ), true ) ); ?>
							<?php esc_html_e( 'vs previous 7 days', '4wp-seo-helper' ); ?>
						</span>
					</li>
					<li>
						<strong><?php echo esc_html( number_format_i18n( (int) ( $growth['month'] ?? 0 ) ) ); ?></strong>
						<?php esc_html_e( 'published in the last 30 days', '4wp-seo-helper' ); ?>
						<span class="forwp-seo-admin-muted">
							<?php echo esc_html( self::format_delta( (array) ( $growth['month_delta'] ?? [] ), true ) ); ?>
							<?php esc_html_e( 'vs previous 30 days', '4wp-seo-helper' ); ?>
						</span>
					</li>
				</ul>
				<?php if ( [] !== $types ) : ?>
					<h3><?php esc_html_e( 'By type', '4wp-seo-helper' ); ?></h3>
					<ul class="forwp-seo-dash-legend">
						<?php foreach ( $types as $slug => $type ) : ?>
							<li>
								<code><?php echo esc_html( (string) $slug ); ?></code>
								<?php echo esc_html( number_format_i18n( (int) ( $type['count'] ?? 0 ) ) ); ?>
								<span class="forwp-seo-admin-muted"><?php echo esc_html( (int) ( $type['avg_completeness'] ?? 0 ) . '%' ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( [] !== $missing ) : ?>
					<h3><?php esc_html_e( 'Missing fields', '4wp-seo-helper' ); ?></h3>
					<ul class="forwp-seo-dash-legend">
						<?php
						$field_labels = [
							'title'         => __( 'SEO title', '4wp-seo-helper' ),
							'description'   => __( 'Meta description', '4wp-seo-helper' ),
							'focus_keyword' => __( 'Focus keyphrases', '4wp-seo-helper' ),
							'og_image'      => __( 'OG image', '4wp-seo-helper' ),
						];
						foreach ( $field_labels as $field => $label ) :
							$count = (int) ( $missing[ $field ] ?? 0 );
							?>
							<li<?php echo $count > 0 ? ' class="is-warn"' : ''; ?>>
								<?php echo esc_html( $label ); ?>
								<strong><?php echo esc_html( number_format_i18n( $count ) ); ?></strong>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( [] !== $waiting ) : ?>
			<div class="forwp-seo-panel">
				<h2><?php esc_html_e( 'Waiting for Googlebot', '4wp-seo-helper' ); ?></h2>
				<ul class="forwp-seo-dash-list">
					<?php foreach ( $waiting as $row ) : ?>
						<li>
							<a href="<?php echo esc_url( self::post_history_url( (int) $row['post_id'] ) ); ?>">
								<?php echo esc_html( self::post_label( (int) $row['post_id'] ) ); ?>
							</a>
							<span class="forwp-seo-admin-muted"><?php echo esc_html( self::format_gmt( (string) $row['occurred_at'] ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<div class="forwp-seo-panel">
			<h2><?php esc_html_e( 'Recent changes', '4wp-seo-helper' ); ?></h2>
			<?php if ( [] === $recent ) : ?>
				<p class="forwp-seo-admin-muted">
					<?php esc_html_e( 'Empty until you request indexing, inspect a URL, save a post, or Search Console syncs.', '4wp-seo-helper' ); ?>
				</p>
			<?php else : ?>
				<ol class="forwp-seo-dash-timeline">
					<?php foreach ( $recent as $row ) : ?>
						<?php self::render_event_item( $row, true ); ?>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_kpi_card( string $value, string $label, string $meta, string $modifier = '', string $href = '' ): void {
		$classes = 'forwp-seo-status-card';
		if ( '' !== $modifier ) {
			$classes .= ' ' . $modifier;
		}
		?>
		<div class="<?php echo esc_attr( $classes ); ?>">
			<div class="forwp-seo-status-card__head">
				<h2 class="forwp-seo-status-card__title"><?php echo esc_html( $value ); ?></h2>
			</div>
			<p class="forwp-seo-status-card__desc">
				<?php if ( '' !== $href ) : ?>
					<a href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $label ); ?>
				<?php endif; ?>
			</p>
			<?php if ( '' !== $meta ) : ?>
				<p class="forwp-seo-status-card__meta"><?php echo esc_html( $meta ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param array{value?:int,pct?:float|null} $delta
	 */
	private static function format_delta( array $delta, bool $up_is_good ): string {
		unset( $up_is_good );
		$value = (int) ( $delta['value'] ?? 0 );
		$pct   = $delta['pct'] ?? null;
		if ( 0 === $value && null === $pct ) {
			return '';
		}

		$signed = sprintf( '%+d', $value );
		if ( is_numeric( $pct ) ) {
			return sprintf( '%s · %+s%%', $signed, (string) $pct );
		}

		return $signed;
	}

	private static function render_post_history( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Post not found.', '4wp-seo-helper' ) . '</p></div>';
			return;
		}

		$events  = History::for_post( $post_id );
		if ( [] === $events ) {
			HistoryLogger::seed_existing( $post );
			$events = History::for_post( $post_id );
		}
		$latest  = History::latest_by_type( $post_id );
		$inspect = Indexing::inventory_fields( $post_id );
		$chars   = HistoryLogger::character_count( $post );
		$back    = admin_url( 'admin.php?page=' . Menu::PAGE_SLUG );
		$edit    = get_edit_post_link( $post_id, 'raw' );
		?>
		<p>
			<a href="<?php echo esc_url( $back ); ?>">&larr; <?php esc_html_e( 'All changes', '4wp-seo-helper' ); ?></a>
			<?php if ( is_string( $edit ) && '' !== $edit ) : ?>
				<span class="forwp-seo-admin-muted"> · </span>
				<a href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'Edit', '4wp-seo-helper' ); ?></a>
			<?php endif; ?>
			<span class="forwp-seo-admin-muted"> · </span>
			<a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php esc_html_e( 'View', '4wp-seo-helper' ); ?></a>
		</p>
		<h2 class="forwp-seo-dash-post-title"><?php echo esc_html( self::post_label( $post_id ) ); ?></h2>

		<div class="forwp-seo-dash-check">
			<?php
			self::render_check_item(
				__( 'Index requested', '4wp-seo-helper' ),
				$latest[ History::TYPE_INDEX_REQUEST ],
				$inspect['gsc_index_requested_at'] > 0
					? gmdate( 'Y-m-d H:i:s', $inspect['gsc_index_requested_at'] )
					: ''
			);
			self::render_check_item(
				__( 'Robot crawled', '4wp-seo-helper' ),
				$latest[ History::TYPE_CRAWL ],
				(string) $inspect['gsc_last_crawl']
			);
			self::render_check_item(
				__( 'Content length', '4wp-seo-helper' ),
				$latest[ History::TYPE_CONTENT ],
				'',
				sprintf(
					/* translators: %s: character count */
					__( 'Now: %s characters', '4wp-seo-helper' ),
					number_format_i18n( $chars )
				)
			);
			self::render_check_item(
				__( 'SEO fields', '4wp-seo-helper' ),
				$latest[ History::TYPE_SEO ]
			);
			self::render_check_item(
				__( 'Search metrics', '4wp-seo-helper' ),
				$latest[ History::TYPE_GSC ]
			);
			?>
		</div>

		<div class="forwp-seo-panel">
			<h2><?php esc_html_e( 'Timeline', '4wp-seo-helper' ); ?></h2>
			<?php if ( [] === $events ) : ?>
				<p class="forwp-seo-admin-muted">
					<?php esc_html_e( 'No history yet for this URL. Save the post, inspect it, or request indexing to start the checklist.', '4wp-seo-helper' ); ?>
				</p>
			<?php else : ?>
				<ol class="forwp-seo-dash-timeline">
					<?php
					$previous = [];
					$items    = [];
					foreach ( array_reverse( $events ) as $row ) {
						$type           = (string) $row['event_type'];
						$items[]        = [ $row, $previous[ $type ] ?? null ];
						$previous[ $type ] = $row;
					}
					foreach ( array_reverse( $items ) as $pair ) {
						self::render_event_item( $pair[0], false, $pair[1] );
					}
					?>
				</ol>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed>|null $event
	 */
	private static function render_check_item( string $label, ?array $event, string $fallback_date = '', string $extra = '' ): void {
		$done = null !== $event || '' !== $fallback_date;
		$when = '';
		if ( is_array( $event ) ) {
			$when = self::format_gmt( (string) $event['occurred_at'] );
		} elseif ( '' !== $fallback_date ) {
			$when = self::format_gmt( $fallback_date );
		}
		?>
		<div class="forwp-seo-dash-check__item<?php echo $done ? ' is-done' : ''; ?>">
			<span class="forwp-seo-dash-check__mark" aria-hidden="true"><?php echo $done ? '✓' : '○'; ?></span>
			<div>
				<strong><?php echo esc_html( $label ); ?></strong>
				<p class="forwp-seo-admin-muted">
					<?php
					echo esc_html( $done ? $when : __( 'Not recorded yet', '4wp-seo-helper' ) );
					if ( '' !== $extra ) {
						echo ' · ' . esc_html( $extra );
					}
					?>
				</p>
				<?php if ( is_array( $event ) ) : ?>
					<p class="forwp-seo-dash-snap"><?php echo esc_html( self::snapshot_summary( (array) $event['snapshot'] ) ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed>      $row
	 * @param array<string, mixed>|null $previous
	 */
	private static function render_event_item( array $row, bool $with_title, ?array $previous = null ): void {
		$post_id = (int) $row['post_id'];
		$type    = (string) $row['event_type'];
		$snap    = (array) ( $row['snapshot'] ?? [] );
		$delta   = '';
		if ( is_array( $previous ) ) {
			$delta = self::snapshot_delta( (array) ( $previous['snapshot'] ?? [] ), $snap, $type );
		}
		?>
		<li>
			<div class="forwp-seo-dash-timeline__head">
				<span class="forwp-seo-badge"><?php echo esc_html( self::event_label( $type ) ); ?></span>
				<time datetime="<?php echo esc_attr( (string) $row['occurred_at'] ); ?>">
					<?php echo esc_html( self::format_gmt( (string) $row['occurred_at'] ) ); ?>
				</time>
				<?php if ( $with_title ) : ?>
					<a href="<?php echo esc_url( self::post_history_url( $post_id ) ); ?>">
						<?php echo esc_html( self::post_label( $post_id ) ); ?>
					</a>
				<?php endif; ?>
			</div>
			<p class="forwp-seo-dash-snap"><?php echo esc_html( self::snapshot_summary( $snap ) ); ?></p>
			<?php if ( '' !== $delta ) : ?>
				<p class="forwp-seo-dash-delta"><?php echo esc_html( $delta ); ?></p>
			<?php endif; ?>
		</li>
		<?php
	}

	/**
	 * @param array<string, mixed> $snapshot
	 */
	private static function snapshot_summary( array $snapshot ): string {
		$parts = [];

		if ( isset( $snapshot['chars'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: character count */
				__( '%s chars', '4wp-seo-helper' ),
				number_format_i18n( (int) $snapshot['chars'] )
			);
		}
		if ( isset( $snapshot['score'] ) ) {
			$parts[] = sprintf(
				/* translators: %d: SEO score */
				__( 'score %d', '4wp-seo-helper' ),
				(int) $snapshot['score']
			);
		}
		if ( ! empty( $snapshot['keys'] ) && is_array( $snapshot['keys'] ) ) {
			$parts[] = implode( ', ', array_map( 'strval', array_slice( $snapshot['keys'], 0, 5 ) ) );
		}
		if ( isset( $snapshot['clicks'] ) || isset( $snapshot['impr'] ) ) {
			$parts[] = sprintf(
				/* translators: 1: clicks, 2: impressions, 3: average position */
				__( '%1$s clicks / %2$s impr / pos %3$s', '4wp-seo-helper' ),
				number_format_i18n( (int) ( $snapshot['clicks'] ?? 0 ) ),
				number_format_i18n( (int) ( $snapshot['impr'] ?? 0 ) ),
				isset( $snapshot['pos'] ) ? (string) $snapshot['pos'] : '—'
			);
		}
		if ( ! empty( $snapshot['sugg'] ) && is_array( $snapshot['sugg'] ) ) {
			$queries = [];
			foreach ( array_slice( $snapshot['sugg'], 0, 3 ) as $row ) {
				if ( is_array( $row ) && ! empty( $row['q'] ) ) {
					$queries[] = (string) $row['q'];
				}
			}
			if ( [] !== $queries ) {
				$parts[] = sprintf(
					/* translators: %s: comma-separated search queries */
					__( 'suggest: %s', '4wp-seo-helper' ),
					implode( ', ', $queries )
				);
			}
		}
		if ( ! empty( $snapshot['cov'] ) ) {
			$parts[] = (string) $snapshot['cov'];
		}

		return implode( ' · ', $parts );
	}

	/**
	 * @param array<string, mixed> $from
	 * @param array<string, mixed> $to
	 */
	private static function snapshot_delta( array $from, array $to, string $type ): string {
		if ( History::TYPE_CONTENT === $type && isset( $from['chars'], $to['chars'] ) && (int) $from['chars'] !== (int) $to['chars'] ) {
			return sprintf(
				/* translators: 1: previous character count, 2: new character count */
				__( '%1$s → %2$s characters', '4wp-seo-helper' ),
				number_format_i18n( (int) $from['chars'] ),
				number_format_i18n( (int) $to['chars'] )
			);
		}

		if ( History::TYPE_GSC === $type && ( isset( $from['clicks'] ) || isset( $to['clicks'] ) ) ) {
			return sprintf(
				/* translators: 1: previous clicks, 2: new clicks, 3: previous position, 4: new position */
				__( 'clicks %1$s → %2$s · pos %3$s → %4$s', '4wp-seo-helper' ),
				number_format_i18n( (int) ( $from['clicks'] ?? 0 ) ),
				number_format_i18n( (int) ( $to['clicks'] ?? 0 ) ),
				isset( $from['pos'] ) ? (string) $from['pos'] : '—',
				isset( $to['pos'] ) ? (string) $to['pos'] : '—'
			);
		}

		if ( History::TYPE_CRAWL === $type && ! empty( $from['crawl'] ) && ! empty( $to['crawl'] ) && $from['crawl'] !== $to['crawl'] ) {
			return sprintf(
				/* translators: 1: previous crawl datetime, 2: new crawl datetime */
				__( 'previous crawl %1$s → %2$s', '4wp-seo-helper' ),
				self::format_gmt( (string) $from['crawl'] ),
				self::format_gmt( (string) $to['crawl'] )
			);
		}

		return '';
	}

	private static function event_label( string $type ): string {
		$labels = [
			History::TYPE_INDEX_REQUEST => __( 'Index request', '4wp-seo-helper' ),
			History::TYPE_INSPECT       => __( 'Inspect', '4wp-seo-helper' ),
			History::TYPE_CRAWL         => __( 'Crawl', '4wp-seo-helper' ),
			History::TYPE_CONTENT       => __( 'Content', '4wp-seo-helper' ),
			History::TYPE_SEO           => __( 'SEO', '4wp-seo-helper' ),
			History::TYPE_GSC           => __( 'Search', '4wp-seo-helper' ),
		];

		return $labels[ $type ] ?? $type;
	}

	public static function post_history_url( int $post_id ): string {
		return add_query_arg(
			[
				'page'         => Menu::PAGE_SLUG,
				'history_post' => $post_id,
			],
			admin_url( 'admin.php' )
		);
	}

	private static function post_label( int $post_id ): string {
		$title = get_the_title( $post_id );

		return '' !== $title ? $title : '#' . $post_id;
	}

	private static function format_gmt( string $gmt ): string {
		if ( '' === $gmt ) {
			return '—';
		}

		$local = get_date_from_gmt( $gmt );
		if ( ! is_string( $local ) || '' === $local || '0000-00-00 00:00:00' === $local ) {
			$ts = strtotime( $gmt );
			if ( false === $ts ) {
				return $gmt;
			}
			$local = wp_date( 'Y-m-d H:i:s', $ts );
		}

		$formatted = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $local, true );

		return is_string( $formatted ) && '' !== $formatted ? $formatted : $gmt;
	}
}
