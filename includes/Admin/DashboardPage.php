<?php
/**
 * Dashboard: site SEO health at a glance.
 */

namespace Forwp\SeoHelper\Admin;

use Forwp\SeoHelper\Inventory\DashboardStats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DashboardPage {
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$heading_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28" fill="none" focusable="false" aria-hidden="true"><path d="M3 3v18h18" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><path d="m7 14 4-4 3 3 5-6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		?>
		<div class="wrap forwp-seo-admin-shell forwp-seo-admin-shell--wide">
			<h1 class="forwp-seo-admin-heading">
				<span class="forwp-seo-admin-heading__icon" aria-hidden="true">
					<?php echo wp_kses( $heading_svg, Page::svg_allowed_html() ); ?>
				</span>
				<span class="forwp-seo-admin-heading__text"><?php esc_html_e( 'Dashboard', '4wp-seo-helper' ); ?></span>
			</h1>
			<p class="forwp-seo-admin-lead">
				<?php esc_html_e( 'Site health at a glance: weak URLs, new pages vs last week, and SEO gaps.', '4wp-seo-helper' ); ?>
			</p>
			<?php self::render_overview(); ?>
		</div>
		<?php
	}

	private static function render_overview(): void {
		$stats   = DashboardStats::collect();
		$gsc     = is_array( $stats['gsc'] ?? null ) ? $stats['gsc'] : null;
		$growth  = is_array( $stats['growth'] ?? null ) ? $stats['growth'] : [];
		$missing = is_array( $stats['missing'] ?? null ) ? $stats['missing'] : [];
		$weakest = is_array( $stats['weakest'] ?? null ) ? $stats['weakest'] : [];
		$types   = is_array( $stats['types'] ?? null ) ? $stats['types'] : [];
		?>
		<div class="forwp-seo-status-grid">
			<?php
			self::render_kpi_card(
				number_format_i18n( (int) $stats['posts'] ),
				__( 'Indexed inventory URLs', '4wp-seo-helper' ),
				self::format_delta( (array) ( $stats['delta']['posts'] ?? [] ) ),
				''
			);
			self::render_kpi_card(
				number_format_i18n( (int) $stats['weak'] ),
				__( 'Weak pages (score under 50)', '4wp-seo-helper' ),
				self::format_delta( (array) ( $stats['delta']['weak'] ?? [] ) ),
				(int) $stats['weak'] > 0 ? 'is-alert' : 'is-ok',
				(string) $stats['weak_url']
			);
			self::render_kpi_card(
				number_format_i18n( (int) ( $growth['week'] ?? 0 ) ),
				__( 'New pages this week', '4wp-seo-helper' ),
				sprintf(
					/* translators: 1: signed week-over-week change, 2: previous week count */
					__( '%1$s vs last week (%2$s)', '4wp-seo-helper' ),
					self::format_delta( (array) ( $growth['week_delta'] ?? [] ) ),
					number_format_i18n( (int) ( $growth['prev_week'] ?? 0 ) )
				),
				(int) ( $growth['week'] ?? 0 ) > 0 ? 'is-up' : ''
			);
			self::render_kpi_card(
				(int) $stats['avg'] . '%',
				__( 'Average SEO score', '4wp-seo-helper' ),
				self::format_delta( (array) ( $stats['delta']['avg'] ?? [] ) ),
				''
			);
			self::render_kpi_card(
				number_format_i18n( (int) $stats['gaps'] ),
				__( 'Pages with SEO gaps', '4wp-seo-helper' ),
				self::format_delta( (array) ( $stats['delta']['gaps'] ?? [] ) ),
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
					self::format_delta( $click_delta ),
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
								$post_id = (int) ( $row['post_id'] ?? 0 );
								?>
								<tr class="<?php echo $is_weak ? 'is-weak' : ''; ?>">
									<td>
										<a href="<?php echo esc_url( InventoryPage::url_for_post( $post_id ) ); ?>">
											<?php echo esc_html( (string) ( $row['title'] ?? '#' . $post_id ) ); ?>
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
							<?php echo esc_html( self::format_delta( (array) ( $growth['week_delta'] ?? [] ) ) ); ?>
							<?php esc_html_e( 'vs previous 7 days', '4wp-seo-helper' ); ?>
						</span>
					</li>
					<li>
						<strong><?php echo esc_html( number_format_i18n( (int) ( $growth['month'] ?? 0 ) ) ); ?></strong>
						<?php esc_html_e( 'published in the last 30 days', '4wp-seo-helper' ); ?>
						<span class="forwp-seo-admin-muted">
							<?php echo esc_html( self::format_delta( (array) ( $growth['month_delta'] ?? [] ) ) ); ?>
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
	private static function format_delta( array $delta ): string {
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
}
