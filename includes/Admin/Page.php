<?php
/**
 * Admin page output.
 */

namespace Forwp\SeoHelper\Admin;

use Forwp\SeoHelper\Core\Release;
use Forwp\SeoHelper\CrossPosting\Module as CrossPostingModule;
use Forwp\SeoHelper\Gsc\Admin as GscAdmin;
use Forwp\SeoHelper\Inventory\Module as InventoryModule;
use Forwp\SeoHelper\Inventory\PriorityLabels;
use Forwp\SeoHelper\Inventory\Repository;
use Forwp\SeoHelper\Multilingual\Registry as MultilingualRegistry;
use Forwp\SeoHelper\SeoMeta\Registry as SeoMetaRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Page {
	private const TAB_OVERVIEW  = 'overview';
	private const TAB_SETTINGS  = 'settings';
	private const TAB_API       = 'api';
	private const TAB_GSC       = 'gsc';

	/**
	 * @return list<string>
	 */
	private static function get_tabs(): array {
		$tabs = [
			self::TAB_OVERVIEW,
			self::TAB_SETTINGS,
		];

		if ( Release::is_module_public( Release::MODULE_INVENTORY_API ) ) {
			$tabs[] = self::TAB_API;
		}

		if ( Release::is_module_public( Release::MODULE_GSC ) ) {
			$tabs[] = self::TAB_GSC;
		}

		return $tabs;
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		self::handle_settings_post();

		$tab = self::get_active_tab();

		$is_connected         = GscAdmin::get_instance()->is_connected();
		$crossposting_enabled = CrossPostingModule::get_instance()->is_enabled();
		$inventory_enabled    = InventoryModule::get_instance()->is_enabled();
		$seo_adapter          = SeoMetaRegistry::get_active();
		$multilingual         = MultilingualRegistry::get_active();
		$inventory_stats      = $inventory_enabled ? ( new Repository() )->get_stats() : null;

		$heading_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28" fill="none" focusable="false" aria-hidden="true"><path d="M3 3v18h18" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><path d="m7 14 4-4 3 3 5-6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		?>
		<div class="wrap forwp-seo-admin-shell">
			<?php self::render_notices(); ?>

			<h1 class="forwp-seo-admin-heading">
				<span class="forwp-seo-admin-heading__icon" aria-hidden="true">
					<?php echo wp_kses( $heading_svg, self::svg_allowed_html() ); ?>
				</span>
				<span class="forwp-seo-admin-heading__text"><?php esc_html_e( '4WP SEO Helper', '4wp-seo' ); ?></span>
			</h1>
			<p class="forwp-seo-admin-lead">
				<?php esc_html_e( 'SEO Inventory for WordPress — audit titles, meta descriptions, and SEO completeness across your site. More modules are on the roadmap.', '4wp-seo' ); ?>
			</p>

			<div class="forwp-seo-admin-app">
				<div class="forwp-seo-tab-panel components-tab-panel">
					<div class="components-tab-panel__tabs" role="tablist" aria-label="<?php esc_attr_e( '4WP SEO Helper', '4wp-seo' ); ?>">
						<?php self::render_tab_button( self::TAB_OVERVIEW, __( 'Overview', '4wp-seo' ), $tab ); ?>
						<?php self::render_tab_button( self::TAB_SETTINGS, __( 'Settings', '4wp-seo' ), $tab ); ?>
						<?php if ( Release::is_module_public( Release::MODULE_INVENTORY_API ) ) : ?>
							<?php self::render_tab_button( self::TAB_API, __( 'Inventory API', '4wp-seo' ), $tab ); ?>
						<?php endif; ?>
						<?php if ( Release::is_module_public( Release::MODULE_GSC ) ) : ?>
							<?php self::render_tab_button( self::TAB_GSC, __( 'Search Console', '4wp-seo' ), $tab ); ?>
						<?php endif; ?>
					</div>

					<div id="forwp-seo-panel-overview" role="tabpanel" class="components-tab-panel__tab-content" aria-labelledby="forwp-seo-tab-overview" <?php echo self::TAB_OVERVIEW !== $tab ? 'hidden' : ''; ?>>
						<?php
						self::render_overview_tab(
							$is_connected,
							$crossposting_enabled,
							$inventory_enabled,
							$inventory_stats,
							$seo_adapter,
							$multilingual
						);
						?>
					</div>

					<div id="forwp-seo-panel-settings" role="tabpanel" class="components-tab-panel__tab-content" aria-labelledby="forwp-seo-tab-settings" <?php echo self::TAB_SETTINGS !== $tab ? 'hidden' : ''; ?>>
						<?php self::render_settings_tab( $crossposting_enabled, $inventory_enabled ); ?>
					</div>

					<?php if ( Release::is_module_public( Release::MODULE_INVENTORY_API ) ) : ?>
					<div id="forwp-seo-panel-api" role="tabpanel" class="components-tab-panel__tab-content" aria-labelledby="forwp-seo-tab-api" <?php echo self::TAB_API !== $tab ? 'hidden' : ''; ?>>
						<?php self::render_api_tab( $inventory_enabled ); ?>
					</div>
					<?php endif; ?>

					<?php if ( Release::is_module_public( Release::MODULE_GSC ) ) : ?>
					<div id="forwp-seo-panel-gsc" role="tabpanel" class="components-tab-panel__tab-content" aria-labelledby="forwp-seo-tab-gsc" <?php echo self::TAB_GSC !== $tab ? 'hidden' : ''; ?>>
						<?php GscAdmin::get_instance()->render_section(); ?>
					</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	private static function get_active_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab navigation.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : self::TAB_OVERVIEW;

		if ( ! in_array( $tab, self::get_tabs(), true ) ) {
			return self::TAB_OVERVIEW;
		}

		if ( Release::is_module_public( Release::MODULE_GSC ) && ( ! empty( $_GET['gsc_connected'] ) || ! empty( $_GET['gsc_error'] ) ) ) {
			return self::TAB_GSC;
		}

		return $tab;
	}

	private static function render_tab_button( string $id, string $label, string $active_tab ): void {
		$active = $id === $active_tab;
		printf(
			'<button type="button" role="tab" id="forwp-seo-tab-%1$s" class="components-button components-tab-panel__tabs-item forwp-seo-tab%2$s" data-tab="%1$s" aria-selected="%3$s" aria-controls="forwp-seo-panel-%1$s" tabindex="%4$d">%5$s</button>',
			esc_attr( $id ),
			$active ? ' is-active' : '',
			$active ? 'true' : 'false',
			$active ? 0 : -1,
			esc_html( $label )
		);
	}

	/**
	 * @param array<string, mixed>|null $inventory_stats
	 */
	private static function render_overview_tab(
		bool $is_connected,
		bool $crossposting_enabled,
		bool $inventory_enabled,
		?array $inventory_stats,
		$seo_adapter,
		$multilingual
	): void {
		?>
		<div class="forwp-seo-intro-card">
			<h2 class="forwp-seo-intro-card__title"><?php esc_html_e( 'What this plugin does', '4wp-seo' ); ?></h2>
			<p class="forwp-seo-intro-card__text">
				<?php esc_html_e( '4WP SEO Helper gives you a site-wide SEO Inventory: see missing titles and meta fields, set business priorities (P1–P3), and export or sync via REST. Built for Yoast SEO and All in One SEO.', '4wp-seo' ); ?>
			</p>
			<?php if ( $inventory_enabled ) : ?>
				<div class="forwp-seo-intro-card__actions">
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=4wp-seo-inventory' ) ); ?>">
						<?php esc_html_e( 'Open SEO Inventory', '4wp-seo' ); ?>
					</a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=4wp-seo-inventory&missing=any' ) ); ?>">
						<?php esc_html_e( 'Show missing fields', '4wp-seo' ); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>

		<h2 class="forwp-seo-admin-section-title"><?php esc_html_e( 'Module status', '4wp-seo' ); ?></h2>
		<div class="forwp-seo-status-grid">
			<?php
			self::render_status_card(
				__( 'SEO inventory', '4wp-seo' ),
				$inventory_enabled
					? __( 'Admin table + REST API for bulk SEO ops.', '4wp-seo' )
					: __( 'Disabled — enable in Settings.', '4wp-seo' ),
				$inventory_enabled ? 'live' : 'off',
				$inventory_enabled && is_array( $inventory_stats )
					? sprintf(
						/* translators: 1: post count, 2: average completeness percent */
						__( '%1$d posts · %2$d%% avg completeness', '4wp-seo' ),
						(int) $inventory_stats['posts'],
						(int) $inventory_stats['avg_completeness']
					)
					: ( $inventory_enabled ? __( 'API enabled', '4wp-seo' ) : __( 'Off', '4wp-seo' ) )
			);
			self::render_status_card(
				__( 'Integrations', '4wp-seo' ),
				sprintf(
					/* translators: 1: SEO adapter label, 2: multilingual provider label */
					__( 'SEO: %1$s · Multilingual: %2$s', '4wp-seo' ),
					$seo_adapter->get_label(),
					$multilingual->get_label()
				),
				'none' !== $seo_adapter->get_id() ? 'live' : 'warn',
				'none' !== $seo_adapter->get_id()
					? __( 'Adapter active', '4wp-seo' )
					: __( 'No SEO plugin detected', '4wp-seo' )
			);
			self::render_status_card(
				__( 'TechArticle schema', '4wp-seo' ),
				__( 'JSON-LD and Gutenberg blocks for technical articles.', '4wp-seo' ),
				Release::is_module_public( Release::MODULE_TECHARTICLE ) ? 'live' : 'soon',
				Release::is_module_public( Release::MODULE_TECHARTICLE )
					? __( 'Active in editor & frontend', '4wp-seo' )
					: __( 'Planned for a future release', '4wp-seo' )
			);
			self::render_status_card(
				__( 'Google Search Console', '4wp-seo' ),
				__( 'OAuth, property picker, URL inspection, analytics.', '4wp-seo' ),
				Release::is_module_public( Release::MODULE_GSC ) ? ( $is_connected ? 'live' : 'warn' ) : 'soon',
				Release::is_module_public( Release::MODULE_GSC )
					? ( $is_connected ? __( 'Connected', '4wp-seo' ) : __( 'Not connected', '4wp-seo' ) )
					: __( 'Planned for a future release', '4wp-seo' )
			);
			self::render_status_card(
				__( 'LLMS.txt', '4wp-seo' ),
				__( 'Machine-readable site index for AI crawlers.', '4wp-seo' ),
				Release::is_module_public( Release::MODULE_LLMS ) ? 'live' : 'soon',
				Release::is_module_public( Release::MODULE_LLMS )
					? (string) home_url( '/llms.txt' )
					: __( 'Planned for a future release', '4wp-seo' )
			);
			self::render_status_card(
				__( 'Cross posting', '4wp-seo' ),
				__( 'Editor tools for social / syndication drafts.', '4wp-seo' ),
				Release::is_module_public( Release::MODULE_CROSSPOSTING ) ? ( $crossposting_enabled ? 'live' : 'off' ) : 'soon',
				Release::is_module_public( Release::MODULE_CROSSPOSTING )
					? ( $crossposting_enabled ? __( 'Enabled', '4wp-seo' ) : __( 'Disabled', '4wp-seo' ) )
					: __( 'Planned for a future release', '4wp-seo' )
			);
			?>
		</div>
		<?php
	}

	private static function render_status_card( string $title, string $desc, string $badge, string $meta ): void {
		$badge_class = 'forwp-seo-badge--off';
		$badge_label = __( 'Off', '4wp-seo' );

		if ( 'live' === $badge ) {
			$badge_class = 'forwp-seo-badge--live';
			$badge_label = __( 'Active', '4wp-seo' );
		} elseif ( 'soon' === $badge ) {
			$badge_class = 'forwp-seo-badge--soon';
			$badge_label = __( 'Coming soon', '4wp-seo' );
		} elseif ( 'warn' === $badge ) {
			$badge_class = 'forwp-seo-badge--warn';
			$badge_label = __( 'Setup', '4wp-seo' );
		}

		?>
		<article class="forwp-seo-status-card">
			<div class="forwp-seo-status-card__head">
				<h3 class="forwp-seo-status-card__title"><?php echo esc_html( $title ); ?></h3>
				<span class="forwp-seo-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_label ); ?></span>
			</div>
			<p class="forwp-seo-status-card__desc"><?php echo esc_html( $desc ); ?></p>
			<p class="forwp-seo-status-card__meta"><?php echo esc_html( $meta ); ?></p>
		</article>
		<?php
	}

	private static function render_settings_tab( bool $crossposting_enabled, bool $inventory_enabled ): void {
		$priority_labels = PriorityLabels::get_all();
		?>
		<div class="forwp-seo-intro-card">
			<h2 class="forwp-seo-intro-card__title"><?php esc_html_e( 'SEO Inventory settings', '4wp-seo' ); ?></h2>
			<p class="forwp-seo-intro-card__text">
				<?php esc_html_e( 'Configure the inventory API and priority tier names used in the admin table.', '4wp-seo' ); ?>
			</p>
		</div>

		<div class="forwp-seo-panel">
			<form method="post">
				<?php wp_nonce_field( 'forwp_seo_settings', 'forwp_seo_settings_nonce' ); ?>
				<?php if ( Release::is_module_public( Release::MODULE_INVENTORY_API ) ) : ?>
				<label class="forwp-seo-toggle-row">
					<input type="checkbox" name="forwp_seo_inventory_enabled" value="1" <?php checked( $inventory_enabled ); ?> />
					<span>
						<strong><?php esc_html_e( 'SEO inventory API', '4wp-seo' ); ?></strong><br />
						<span class="forwp-seo-admin-muted"><?php esc_html_e( 'Enable REST endpoints for the admin inventory, analytics dashboard, and Google Sheets sync.', '4wp-seo' ); ?></span>
					</span>
				</label>
				<?php endif; ?>

				<h2 class="forwp-seo-admin-section-title"><?php esc_html_e( 'Inventory priority tiers', '4wp-seo' ); ?></h2>
				<p class="forwp-seo-admin-muted">
					<?php esc_html_e( 'Name the three priority lanes used in SEO Inventory. Priority reflects business importance (e.g. main service pages), not SEO score — a page can stay in P1 even at 100%. Search Console signals such as clicks or indexing may feed into ranking later.', '4wp-seo' ); ?>
				</p>
				<div class="forwp-seo-priority-labels">
					<?php foreach ( PriorityLabels::get_defaults() as $lane_id => $default_label ) : ?>
						<label class="forwp-seo-priority-labels__row">
							<span class="forwp-seo-priority-labels__key"><?php echo esc_html( 'P' . $lane_id ); ?></span>
							<input
								type="text"
								class="regular-text"
								name="forwp_seo_priority_label_<?php echo esc_attr( $lane_id ); ?>"
								value="<?php echo esc_attr( $priority_labels[ $lane_id ] ); ?>"
								placeholder="<?php echo esc_attr( $default_label ); ?>"
							/>
						</label>
					<?php endforeach; ?>
				</div>

				<div class="forwp-seo-form-actions">
					<?php submit_button( __( 'Save settings', '4wp-seo' ), 'primary', 'submit', false ); ?>
				</div>
			</form>
		</div>

		<?php self::render_coming_soon_modules_panel(); ?>
		<?php
	}

	private static function render_coming_soon_modules_panel(): void {
		$modules = [
			Release::MODULE_TECHARTICLE  => __( 'TechArticle schema & blocks', '4wp-seo' ),
			Release::MODULE_GSC          => __( 'Google Search Console', '4wp-seo' ),
			Release::MODULE_LLMS         => __( 'LLMS.txt', '4wp-seo' ),
			Release::MODULE_CROSSPOSTING => __( 'Cross posting', '4wp-seo' ),
		];

		$upcoming = array_filter(
			$modules,
			static function ( string $label, string $slug ): bool {
				unset( $label );
				return ! Release::is_module_public( $slug );
			},
			ARRAY_FILTER_USE_BOTH
		);

		if ( empty( $upcoming ) ) {
			return;
		}
		?>
		<div class="forwp-seo-panel forwp-seo-panel--soon">
			<h2 class="forwp-seo-admin-section-title"><?php esc_html_e( 'Coming soon', '4wp-seo' ); ?></h2>
			<p class="forwp-seo-admin-muted">
				<?php esc_html_e( 'These modules are already in the codebase and will ship in upcoming releases.', '4wp-seo' ); ?>
			</p>
			<ul class="forwp-seo-soon-list">
				<?php foreach ( $upcoming as $label ) : ?>
					<li>
						<span class="forwp-seo-badge forwp-seo-badge--soon"><?php esc_html_e( 'Coming soon', '4wp-seo' ); ?></span>
						<?php echo esc_html( $label ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	private static function render_api_tab( bool $inventory_enabled ): void {
		$token    = InventoryModule::get_instance()->get_api_token();
		$base_url = rest_url( 'forwp-seo-helper/v1/seo-inventory' );

		if ( '' === $token ) {
			$token = InventoryModule::get_instance()->regenerate_api_token();
		}
		?>
		<div class="forwp-seo-intro-card">
			<h2 class="forwp-seo-intro-card__title"><?php esc_html_e( 'REST sync for external tools', '4wp-seo' ); ?></h2>
			<p class="forwp-seo-intro-card__text">
				<?php esc_html_e( 'Use these endpoints from 4wp-analytics-dashboard or Google Apps Script. Sample script: docs/google-sheets-sync.gs in the plugin folder.', '4wp-seo' ); ?>
			</p>
		</div>

		<div class="forwp-seo-panel">
			<h2><?php esc_html_e( 'Connection details', '4wp-seo' ); ?></h2>
			<table class="forwp-seo-ref-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', '4wp-seo' ); ?></th>
						<td>
							<span class="forwp-seo-badge <?php echo $inventory_enabled ? 'forwp-seo-badge--live' : 'forwp-seo-badge--off'; ?>">
								<?php echo esc_html( $inventory_enabled ? __( 'Enabled', '4wp-seo' ) : __( 'Disabled', '4wp-seo' ) ); ?>
							</span>
							<?php if ( ! $inventory_enabled ) : ?>
								<p class="forwp-seo-admin-muted"><?php esc_html_e( 'Enable the inventory API in Settings to accept requests.', '4wp-seo' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Base URL', '4wp-seo' ); ?></th>
						<td><code class="forwp-seo-code"><?php echo esc_html( $base_url ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'API contract', '4wp-seo' ); ?></th>
						<td><code class="forwp-seo-code"><?php echo esc_html( rest_url( 'forwp-seo-helper/v1/seo-inventory/meta' ) ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auth header', '4wp-seo' ); ?></th>
						<td><code class="forwp-seo-code">Authorization: Bearer &lt;token&gt;</code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Sync token', '4wp-seo' ); ?></th>
						<td>
							<code class="forwp-seo-code"><?php echo esc_html( $token ); ?></code>
							<form method="post" class="forwp-seo-inline-actions">
								<?php wp_nonce_field( 'forwp_seo_settings', 'forwp_seo_settings_nonce' ); ?>
								<input type="hidden" name="forwp_seo_regenerate_inventory_token" value="1" />
								<?php submit_button( __( 'Regenerate token', '4wp-seo' ), 'secondary', 'submit', false ); ?>
							</form>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function handle_settings_post(): void {
		if ( empty( $_POST['forwp_seo_settings_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['forwp_seo_settings_nonce'] ) ), 'forwp_seo_settings' ) ) {
			return;
		}

		$tab   = self::TAB_SETTINGS;
		$saved = false;

		if ( ! empty( $_POST['forwp_seo_regenerate_inventory_token'] ) ) {
			InventoryModule::get_instance()->regenerate_api_token();
			$tab   = self::TAB_API;
			$saved = 'token';
		}

		if ( isset( $_POST['forwp_seo_priority_label_1'] ) ) {
			if ( Release::is_module_public( Release::MODULE_CROSSPOSTING ) ) {
				$enabled = isset( $_POST['forwp_seo_crossposting_enabled'] ) ? '1' : '0';
				update_option( CrossPostingModule::OPTION_ENABLED, $enabled );
			}

			if ( Release::is_module_public( Release::MODULE_INVENTORY_API ) ) {
				$inventory_enabled = isset( $_POST['forwp_seo_inventory_enabled'] ) ? '1' : '0';
				update_option( InventoryModule::OPTION_ENABLED, $inventory_enabled );
			}

			PriorityLabels::save(
				[
					'1' => sanitize_text_field( wp_unslash( (string) ( $_POST['forwp_seo_priority_label_1'] ?? '' ) ) ),
					'2' => sanitize_text_field( wp_unslash( (string) ( $_POST['forwp_seo_priority_label_2'] ?? '' ) ) ),
					'3' => sanitize_text_field( wp_unslash( (string) ( $_POST['forwp_seo_priority_label_3'] ?? '' ) ) ),
				]
			);

			$saved = 'settings';
		}

		if ( false !== $saved ) {
			wp_safe_redirect(
				add_query_arg(
					[
						'page'  => '4wp-seo',
						'tab'   => $tab,
						'saved' => $saved,
					],
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
	}

	private static function render_notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success flag after PRG redirect.
		if ( isset( $_GET['saved'] ) ) {
			$saved = sanitize_key( wp_unslash( $_GET['saved'] ) );
			if ( 'token' === $saved ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Sync token regenerated.', '4wp-seo' ) . '</p></div>';
			} elseif ( 'settings' === $saved ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', '4wp-seo' ) . '</p></div>';
			}
		}

		if ( ! empty( $_GET['gsc_error'] ) && Release::is_module_public( Release::MODULE_GSC ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['gsc_error'] ) ) ) . '</p></div>';
		}
		if ( ! empty( $_GET['gsc_connected'] ) && Release::is_module_public( Release::MODULE_GSC ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Google Search Console connected.', '4wp-seo' ) . '</p></div>';
		}
	}

	/**
	 * @return array<string, array<string, bool>>
	 */
	private static function svg_allowed_html(): array {
		return [
			'svg'  => [
				'xmlns'       => true,
				'viewbox'     => true,
				'width'       => true,
				'height'      => true,
				'fill'        => true,
				'focusable'   => true,
				'aria-hidden' => true,
			],
			'path' => [
				'd'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
			],
		];
	}
}
