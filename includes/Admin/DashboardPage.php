<?php
/**
 * Dashboard admin screen (placeholder).
 */

namespace Forwp\SeoHelper\Admin;

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
		<div class="wrap forwp-seo-admin-shell">
			<h1 class="forwp-seo-admin-heading">
				<span class="forwp-seo-admin-heading__icon" aria-hidden="true">
					<?php echo wp_kses( $heading_svg, Page::svg_allowed_html() ); ?>
				</span>
				<span class="forwp-seo-admin-heading__text"><?php esc_html_e( 'Dashboard', '4wp-seo-helper' ); ?></span>
			</h1>
			<p class="forwp-seo-admin-lead">
				<?php esc_html_e( 'Site-wide SEO overview and KPIs will live here.', '4wp-seo-helper' ); ?>
			</p>

			<div class="forwp-seo-panel forwp-seo-panel--soon">
				<span class="forwp-seo-badge forwp-seo-badge--soon"><?php esc_html_e( 'Coming soon', '4wp-seo-helper' ); ?></span>
				<p class="forwp-seo-admin-muted">
					<?php esc_html_e( 'Use SEO Inventory and Search Console in the sidebar while the dashboard is being built.', '4wp-seo-helper' ); ?>
				</p>
				<div class="forwp-seo-intro-card__actions">
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::INVENTORY_PAGE_SLUG ) ); ?>">
						<?php esc_html_e( 'Open SEO Inventory', '4wp-seo-helper' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}
}
