<?php
/**
 * Google Search Console admin screen.
 */

namespace Forwp\SeoHelper\Gsc;

use Forwp\SeoHelper\Admin\Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {
	private static $instance = null;

	public const TAB_OVERVIEW    = 'overview';
	public const TAB_INSIGHTS    = 'insights';
	public const TAB_PERFORMANCE = 'performance';
	public const TAB_INSPECTION  = 'inspection';
	public const TAB_SYNC        = 'sync';

	private const OPTION_CLIENT_ID = 'forwp_seo_gsc_client_id';
	private const OPTION_CLIENT_SECRET = 'forwp_seo_gsc_client_secret';
	private const OPTION_REDIRECT_URI = 'forwp_seo_gsc_redirect_uri';
	private const OPTION_ACCESS_TOKEN = 'forwp_seo_gsc_access_token';
	private const OPTION_REFRESH_TOKEN = 'forwp_seo_gsc_refresh_token';
	private const OPTION_TOKEN_EXPIRES = 'forwp_seo_gsc_token_expires';
	private const OPTION_SITE = 'forwp_seo_gsc_site';

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', [ $this, 'maybe_handle_callback' ], 0 );
		add_action( 'init', [ $this, 'maybe_handle_callback' ], 5 );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	public function is_connected(): bool {
		return $this->get_access_token() !== '';
	}

	public function has_property(): bool {
		return '' !== get_option( self::OPTION_SITE, '' );
	}

	public function is_menu_visible(): bool {
		return Module::get_instance()->is_enabled() && $this->is_connected() && $this->has_property();
	}

	public function get_settings_url(): string {
		return add_query_arg(
			[
				'page' => Menu::SETTINGS_PAGE_SLUG,
				'tab'  => 'gsc',
			],
			admin_url( 'admin.php' )
		);
	}

	public function get_page_url( string $tab = self::TAB_OVERVIEW, array $extra = [] ): string {
		$query = array_merge(
			[
				'page' => Menu::GSC_PAGE_SLUG,
				'tab'  => $tab,
			],
			$extra
		);

		if ( ! in_array( $tab, [ self::TAB_INSPECTION, self::TAB_SYNC ], true ) ) {
			$query['range'] = ReportPeriod::get_days();
		}

		return add_query_arg( $query, admin_url( 'admin.php' ) );
	}

	public static function get_site_property(): string {
		return (string) get_option( self::OPTION_SITE, '' );
	}

	public function get_access_token_for_sync(): string {
		return $this->get_access_token();
	}

	/**
	 * Handle GSC POST and redirects before any admin HTML is sent.
	 */
	public function handle_page_load(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! Module::get_instance()->is_enabled() ) {
			wp_safe_redirect(
				add_query_arg(
					[
						'page' => Menu::SETTINGS_PAGE_SLUG,
						'tab'  => 'settings',
					],
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$redirect = $this->handle_data_post();
		if ( ! is_string( $redirect ) ) {
			$redirect = $this->handle_sync_post();
		}
		if ( is_string( $redirect ) ) {
			wp_safe_redirect( $redirect );
			exit;
		}
	}

	public function render_page(): void {
		$site         = get_option( self::OPTION_SITE, '' );
		$is_connected = $this->is_connected();
		$tab          = $this->get_active_tab();
		$report_days  = ReportPeriod::resolve_from_request( $tab );
		$is_ready     = $this->is_menu_visible();
		$insights     = new Insights();
		$repository   = new Repository();
		$sync         = Sync::get_instance();
		$sync_running = $is_ready && $sync->is_sync_in_progress( $site );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only performance dimension.
		$perf_dimension = PageRenderer::resolve_performance_dimension(
			isset( $_GET['dimension'] ) ? wp_unslash( (string) $_GET['dimension'] ) : 'query'
		);

		$heading_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28" fill="none" focusable="false" aria-hidden="true"><path d="M12 2 3 7v10l9 5 9-5V7l-9-5Z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/><path d="M3 7l9 5 9-5M12 12v10" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/></svg>';
		?>
		<div class="wrap forwp-seo-admin-shell forwp-seo-gsc-shell">
			<h1 class="forwp-seo-admin-heading">
				<span class="forwp-seo-admin-heading__icon" aria-hidden="true">
					<?php echo wp_kses( $heading_svg, \Forwp\SeoHelper\Admin\Page::svg_allowed_html() ); ?>
				</span>
				<span class="forwp-seo-admin-heading__text"><?php esc_html_e( 'Search Console', '4wp-seo-helper' ); ?></span>
			</h1>
			<p class="forwp-seo-admin-lead">
				<?php
				if ( $is_ready && $site ) {
					printf(
						/* translators: %s: Search Console property URL */
						esc_html__( 'Property: %s', '4wp-seo-helper' ),
						esc_html( $site )
					);
				} else {
					esc_html_e( 'Overview, insights, and performance from synced Search Analytics. Configure connection under Settings → Search Console.', '4wp-seo-helper' );
				}
				?>
			</p>

			<?php if ( ! $is_ready ) : ?>
				<div class="forwp-seo-panel">
					<p class="forwp-seo-admin-muted">
						<?php esc_html_e( 'Connect Google Search Console and choose a property under Settings → Search Console.', '4wp-seo-helper' ); ?>
					</p>
					<a class="button button-primary" href="<?php echo esc_url( $this->get_settings_url() ); ?>">
						<?php esc_html_e( 'Open Search Console settings', '4wp-seo-helper' ); ?>
					</a>
				</div>
			<?php else : ?>
				<div class="forwp-seo-gsc-app">
					<div class="forwp-seo-gsc-layout forwp-seo-tab-panel forwp-seo-tab-panel--vertical">
						<nav class="forwp-seo-gsc-sidebar components-tab-panel__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Search Console sections', '4wp-seo-helper' ); ?>">
							<?php $this->render_tab_button( self::TAB_OVERVIEW, __( 'Overview', '4wp-seo-helper' ), $tab ); ?>
							<?php $this->render_tab_button( self::TAB_INSIGHTS, __( 'Insights', '4wp-seo-helper' ), $tab ); ?>
							<?php $this->render_tab_button( self::TAB_PERFORMANCE, __( 'Performance', '4wp-seo-helper' ), $tab ); ?>
							<?php $this->render_tab_button( self::TAB_INSPECTION, __( 'URL Inspection', '4wp-seo-helper' ), $tab ); ?>
							<?php $this->render_tab_button( self::TAB_SYNC, __( 'Data sync', '4wp-seo-helper' ), $tab ); ?>
						</nav>

						<div class="forwp-seo-gsc-content">
							<?php
							PageRenderer::render_period_selector(
								$tab,
								$report_days,
								self::TAB_PERFORMANCE === $tab ? [ 'dimension' => $perf_dimension ] : []
							);
							?>
							<div id="forwp-seo-panel-overview" role="tabpanel" class="components-tab-panel__tab-content" aria-labelledby="forwp-seo-tab-overview" <?php echo self::TAB_OVERVIEW !== $tab ? 'hidden' : ''; ?>>
								<?php PageRenderer::render_overview_tab( $insights->build_overview( $site, $report_days ) ); ?>
							</div>
							<div id="forwp-seo-panel-insights" role="tabpanel" class="components-tab-panel__tab-content" aria-labelledby="forwp-seo-tab-insights" <?php echo self::TAB_INSIGHTS !== $tab ? 'hidden' : ''; ?>>
								<?php PageRenderer::render_insights_tab( $insights->build_cards( $site, $report_days ) ); ?>
							</div>
							<div id="forwp-seo-panel-performance" role="tabpanel" class="components-tab-panel__tab-content" aria-labelledby="forwp-seo-tab-performance" <?php echo self::TAB_PERFORMANCE !== $tab ? 'hidden' : ''; ?>>
								<?php PageRenderer::render_performance_tab( $site, $perf_dimension, $report_days ); ?>
							</div>
							<div id="forwp-seo-panel-inspection" role="tabpanel" class="components-tab-panel__tab-content" aria-labelledby="forwp-seo-tab-inspection" <?php echo self::TAB_INSPECTION !== $tab ? 'hidden' : ''; ?>>
								<?php $this->render_inspection_tab( $site ); ?>
							</div>
							<div id="forwp-seo-panel-sync" role="tabpanel" class="components-tab-panel__tab-content" aria-labelledby="forwp-seo-tab-sync" <?php echo self::TAB_SYNC !== $tab ? 'hidden' : ''; ?>>
								<?php
								PageRenderer::render_sync_tab(
									$site,
									$sync_running,
									$repository->get_recent_sync_logs( $site, 15 ),
									$repository->get_last_successful_sync( $site )
								);
								?>
							</div>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_tab_button( string $id, string $label, string $active_tab ): void {
		$active = $id === $active_tab;
		printf(
			'<button type="button" role="tab" id="forwp-seo-tab-%1$s" class="components-button components-tab-panel__tabs-item forwp-seo-tab forwp-seo-gsc-tab%2$s" data-tab="%1$s" aria-selected="%3$s" aria-controls="forwp-seo-panel-%1$s" tabindex="%4$d">%5$s</button>',
			esc_attr( $id ),
			$active ? ' is-active' : '',
			$active ? 'true' : 'false',
			$active ? 0 : -1,
			esc_html( $label )
		);
	}

	private function get_active_tab(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only tab navigation.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : self::TAB_OVERVIEW;

		$allowed = [
			self::TAB_OVERVIEW,
			self::TAB_INSIGHTS,
			self::TAB_PERFORMANCE,
			self::TAB_INSPECTION,
			self::TAB_SYNC,
		];

		if ( ! in_array( $tab, $allowed, true ) ) {
			$tab = self::TAB_OVERVIEW;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $tab;
	}

	public function render_connect_section( bool $module_enabled = true ): void {
		$client_id         = get_option( self::OPTION_CLIENT_ID, '' );
		$client_secret     = get_option( self::OPTION_CLIENT_SECRET, '' );
		$site              = get_option( self::OPTION_SITE, '' );
		$is_connected      = $this->is_connected();
		$redirect_uri      = $this->get_redirect_uri();
		$redirect_override = (string) get_option( self::OPTION_REDIRECT_URI, '' );
		$suggested_uri     = $this->get_suggested_oauth_redirect_uri();
		$needs_loopback    = $this->needs_localhost_redirect_help();
		$has_creds         = ( '' !== $client_id && '' !== $client_secret );
		$redirect_locked   = defined( 'FORWP_SEO_GSC_OAUTH_REDIRECT_URI' );
		$can_edit_redirect = $needs_loopback && $has_creds && ! $is_connected && ! $redirect_locked;
		$override_value    = '' !== $redirect_override ? $redirect_override : $suggested_uri;
		?>
		<div class="forwp-seo-intro-card">
			<h2 class="forwp-seo-intro-card__title"><?php esc_html_e( 'Google Search Console', '4wp-seo-helper' ); ?></h2>
			<p class="forwp-seo-intro-card__text">
				<?php esc_html_e( 'OAuth credentials, Google account connection, and Search Console property selection.', '4wp-seo-helper' ); ?>
			</p>
			<?php if ( $module_enabled && $this->is_menu_visible() ) : ?>
				<div class="forwp-seo-intro-card__actions">
					<a class="button button-primary" href="<?php echo esc_url( $this->get_page_url( self::TAB_OVERVIEW ) ); ?>">
						<?php esc_html_e( 'Open GSC tools', '4wp-seo-helper' ); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( ! $module_enabled ) : ?>
			<div class="forwp-seo-panel">
				<p class="forwp-seo-admin-muted">
					<?php esc_html_e( 'Enable Search Console in Settings to configure OAuth and pick a property.', '4wp-seo-helper' ); ?>
				</p>
			</div>
			<?php
			return;
		endif;
		?>

		<?php if ( $needs_loopback ) : ?>
			<div class="forwp-seo-panel forwp-seo-panel--nested<?php echo $can_edit_redirect ? '' : ' is-inactive'; ?>">
				<h2><?php esc_html_e( 'Development mode — OAuth redirect', '4wp-seo-helper' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Local sites only. Google usually rejects this host for OAuth. Use the loopback redirect below, register the same URI in Google Cloud, then connect.', '4wp-seo-helper' ); ?>
				</p>
				<?php if ( $redirect_locked ) : ?>
					<p class="description">
						<?php esc_html_e( 'Redirect URI is locked in wp-config.php (FORWP_SEO_GSC_OAUTH_REDIRECT_URI). Register that exact value in Google Cloud.', '4wp-seo-helper' ); ?>
					</p>
				<?php elseif ( ! $has_creds ) : ?>
					<p class="description">
						<?php esc_html_e( 'Save API credentials below first, then set the loopback redirect and register it in Google Cloud before Connect.', '4wp-seo-helper' ); ?>
					</p>
				<?php elseif ( $is_connected ) : ?>
					<p class="description">
						<?php esc_html_e( 'OAuth redirect is locked while your Google account is connected. Disconnect to change it.', '4wp-seo-helper' ); ?>
					</p>
				<?php endif; ?>
				<form method="post">
					<?php wp_nonce_field( 'forwp_seo_gsc_settings', 'forwp_seo_gsc_nonce' ); ?>
					<input type="hidden" name="forwp_seo_gsc_context" value="gsc" />
					<div class="forwp-seo-oauth-inline">
						<input
							type="url"
							class="large-text code"
							name="forwp_seo_gsc_redirect_uri"
							id="forwp_seo_gsc_redirect_uri"
							value="<?php echo esc_attr( $override_value ); ?>"
							placeholder="<?php echo esc_attr( $this->get_default_redirect_uri() ); ?>"
							autocomplete="off"
							<?php disabled( ! $can_edit_redirect ); ?>
						/>
						<span class="forwp-seo-oauth-inline__btns">
							<?php if ( '' !== $suggested_uri ) : ?>
								<button
									type="button"
									class="button"
									id="forwp-seo-gsc-use-suggested-redirect"
									data-uri="<?php echo esc_attr( $suggested_uri ); ?>"
									<?php disabled( ! $can_edit_redirect ); ?>
								>
									<?php esc_html_e( 'Use suggested', '4wp-seo-helper' ); ?>
								</button>
							<?php endif; ?>
							<?php
							submit_button(
								__( 'Save redirect', '4wp-seo-helper' ),
								'primary',
								'forwp_seo_gsc_save_redirect',
								false,
								$can_edit_redirect ? [] : [ 'disabled' => 'disabled' ]
							);
							?>
						</span>
					</div>
					<?php if ( '' !== $suggested_uri ) : ?>
						<p class="description">
							<code class="forwp-seo-code"><?php echo esc_html( $suggested_uri ); ?></code>
						</p>
					<?php endif; ?>
				</form>
			</div>
		<?php endif; ?>

		<div class="forwp-seo-row-two">
			<div class="forwp-seo-panel forwp-seo-panel--nested">
				<h2><?php esc_html_e( 'API credentials', '4wp-seo-helper' ); ?></h2>
				<ol class="forwp-seo-setup-steps">
					<li>
						<?php
						printf(
							/* translators: %s: URL to enable Search Console API in Google Cloud */
							wp_kses(
								__( 'Enable the <a href="%s" target="_blank" rel="noopener noreferrer">Search Console API</a>.', '4wp-seo-helper' ),
								[
									'a' => [
										'href'   => true,
										'target' => true,
										'rel'    => true,
									],
								]
							),
							esc_url( 'https://console.cloud.google.com/apis/library/searchconsole.googleapis.com' )
						);
						?>
					</li>
					<li>
						<?php
						printf(
							/* translators: %s: URL to Google Cloud OAuth consent screen */
							wp_kses(
								__( 'If prompted, complete the <a href="%s" target="_blank" rel="noopener noreferrer">OAuth consent screen</a>.', '4wp-seo-helper' ),
								[
									'a' => [
										'href'   => true,
										'target' => true,
										'rel'    => true,
									],
								]
							),
							esc_url( 'https://console.cloud.google.com/apis/credentials/consent' )
						);
						?>
					</li>
					<li>
						<?php
						printf(
							/* translators: %s: URL to Google Cloud credentials */
							wp_kses(
								__( 'Create an OAuth client (type <strong>Web application</strong>) in <a href="%s" target="_blank" rel="noopener noreferrer">Credentials</a>, add the Redirect URI below, then paste Client ID and Client Secret here.', '4wp-seo-helper' ),
								[
									'a'      => [
										'href'   => true,
										'target' => true,
										'rel'    => true,
									],
									'strong' => [],
								]
							),
							esc_url( 'https://console.cloud.google.com/apis/credentials' )
						);
						?>
					</li>
				</ol>
				<form method="post">
					<?php wp_nonce_field( 'forwp_seo_gsc_settings', 'forwp_seo_gsc_nonce' ); ?>
					<input type="hidden" name="forwp_seo_gsc_context" value="gsc" />
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="forwp_seo_gsc_client_id"><?php esc_html_e( 'Client ID', '4wp-seo-helper' ); ?></label></th>
							<td><input type="text" class="large-text code" name="forwp_seo_gsc_client_id" id="forwp_seo_gsc_client_id" value="<?php echo esc_attr( $client_id ); ?>" autocomplete="off" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="forwp_seo_gsc_client_secret"><?php esc_html_e( 'Client Secret', '4wp-seo-helper' ); ?></label></th>
							<td><input type="password" class="large-text code" name="forwp_seo_gsc_client_secret" id="forwp_seo_gsc_client_secret" value="<?php echo esc_attr( $client_secret ); ?>" autocomplete="new-password" /></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Redirect URI', '4wp-seo-helper' ); ?></th>
							<td>
								<code class="forwp-seo-code"><?php echo esc_html( $redirect_uri ); ?></code>
								<p class="description">
									<?php esc_html_e( 'Copy this URI into Authorized redirect URIs on the OAuth client.', '4wp-seo-helper' ); ?>
								</p>
							</td>
						</tr>
					</table>
					<div class="forwp-seo-form-actions">
						<?php submit_button( __( 'Save credentials', '4wp-seo-helper' ), 'primary', 'forwp_seo_gsc_save', false ); ?>
					</div>
				</form>
			</div>

			<div class="forwp-seo-panel forwp-seo-panel--nested">
				<h2><?php esc_html_e( 'Google account', '4wp-seo-helper' ); ?></h2>
				<p class="forwp-seo-connection-line <?php echo $is_connected ? 'forwp-seo-connection-line--ok' : ( $has_creds ? '' : 'forwp-seo-connection-line--warn' ); ?>">
					<?php
					if ( $is_connected ) {
						esc_html_e( 'Connected to Google.', '4wp-seo-helper' );
					} elseif ( $has_creds ) {
						esc_html_e( 'Credentials saved — connect your Google account.', '4wp-seo-helper' );
					} else {
						esc_html_e( 'Save Client ID and Secret first.', '4wp-seo-helper' );
					}
					?>
				</p>
				<div class="forwp-seo-inline-actions">
					<?php if ( ! $is_connected && $has_creds ) : ?>
						<a class="button button-primary" href="<?php echo esc_url( $this->get_connect_url( $client_id ) ); ?>">
							<?php esc_html_e( 'Connect to Google', '4wp-seo-helper' ); ?>
						</a>
					<?php elseif ( $is_connected ) : ?>
						<span class="forwp-seo-badge forwp-seo-badge--live"><?php esc_html_e( 'Connected', '4wp-seo-helper' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<?php if ( $is_connected ) : ?>
			<?php
			$properties    = $this->get_properties();
			$manual_mode   = PropertyResolver::allows_manual_property_selection();
			$matched_site  = ! empty( $properties ) ? PropertyResolver::match_site_property( $properties ) : null;
			$sync_running = Sync::get_instance()->is_sync_in_progress( $site );
			$storage      = ( new Repository() )->get_storage_counts();

			if ( ! $manual_mode && $matched_site && $matched_site !== $site ) {
				update_option( self::OPTION_SITE, $matched_site );
				$site = $matched_site;
			}
			?>
			<div class="forwp-seo-panel">
				<h2><?php esc_html_e( 'Property', '4wp-seo-helper' ); ?></h2>
				<form method="post">
					<?php wp_nonce_field( 'forwp_seo_gsc_settings', 'forwp_seo_gsc_nonce' ); ?>
					<input type="hidden" name="forwp_seo_gsc_context" value="gsc" />
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Local / staging mode', '4wp-seo-helper' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="forwp_seo_gsc_local_dev_mode" value="1" <?php checked( $manual_mode ); ?> />
									<?php esc_html_e( 'Pick a Search Console property manually (local, staging, or when this site domain differs from GSC).', '4wp-seo-helper' ); ?>
								</label>
								<p class="forwp-seo-admin-muted">
									<?php esc_html_e( 'When unchecked, the property that matches this site domain is selected automatically from your Webmaster list.', '4wp-seo-helper' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Search Console property', '4wp-seo-helper' ); ?></th>
							<td>
								<?php if ( empty( $properties ) ) : ?>
									<p class="forwp-seo-admin-muted"><?php esc_html_e( 'No properties found or API error.', '4wp-seo-helper' ); ?></p>
								<?php elseif ( $manual_mode ) : ?>
									<select name="forwp_seo_gsc_site" class="regular-text">
										<?php foreach ( $properties as $property ) : ?>
											<option value="<?php echo esc_attr( $property ); ?>" <?php selected( $site, $property ); ?>>
												<?php echo esc_html( $property ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="forwp-seo-admin-muted"><?php esc_html_e( 'Choose any property from your connected Google account.', '4wp-seo-helper' ); ?></p>
								<?php elseif ( $matched_site ) : ?>
									<code class="forwp-seo-code"><?php echo esc_html( $matched_site ); ?></code>
									<p class="forwp-seo-admin-muted">
										<?php
										printf(
											/* translators: %s: site home URL */
											esc_html__( 'Auto-matched for %s.', '4wp-seo-helper' ),
											esc_html( home_url( '/' ) )
										);
										?>
									</p>
								<?php else : ?>
									<p class="forwp-seo-connection-line forwp-seo-connection-line--warn">
										<?php esc_html_e( 'No Search Console property matches this site domain.', '4wp-seo-helper' ); ?>
									</p>
									<ul class="forwp-seo-gsc-property-list">
										<?php foreach ( $properties as $property ) : ?>
											<li><code><?php echo esc_html( $property ); ?></code></li>
										<?php endforeach; ?>
									</ul>
									<p class="forwp-seo-admin-muted"><?php esc_html_e( 'Enable Local / staging mode above to pick a property manually.', '4wp-seo-helper' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					</table>
					<div class="forwp-seo-form-actions">
						<?php submit_button( __( 'Save property settings', '4wp-seo-helper' ), 'secondary', 'forwp_seo_gsc_save_property', false ); ?>
					</div>
				</form>
			</div>

			<?php
			PageRenderer::render_clear_data_panel(
				$sync_running,
				$storage,
				'forwp_seo_gsc_settings',
				'forwp_seo_gsc_nonce'
			);
			?>
		<?php endif; ?>
		<?php
	}

	private function render_inspection_tab( string $site ): void {
		?>
		<div class="forwp-seo-intro-card">
			<h2 class="forwp-seo-intro-card__title"><?php esc_html_e( 'URL Inspection', '4wp-seo-helper' ); ?></h2>
			<p class="forwp-seo-intro-card__text">
				<?php esc_html_e( 'Index status for a single URL on the selected property.', '4wp-seo-helper' ); ?>
			</p>
		</div>

		<div class="forwp-seo-panel">
			<form method="post">
				<?php wp_nonce_field( 'forwp_seo_gsc_settings', 'forwp_seo_gsc_nonce' ); ?>
				<input type="hidden" name="forwp_seo_gsc_tab" value="<?php echo esc_attr( self::TAB_INSPECTION ); ?>" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="forwp_seo_gsc_inspect_url"><?php esc_html_e( 'URL', '4wp-seo-helper' ); ?></label></th>
						<td><input type="url" class="large-text" name="forwp_seo_gsc_inspect_url" id="forwp_seo_gsc_inspect_url" placeholder="<?php echo esc_attr( trailingslashit( $site ) ); ?>" /></td>
					</tr>
				</table>
				<div class="forwp-seo-form-actions">
					<?php submit_button( __( 'Inspect URL', '4wp-seo-helper' ), 'primary', 'forwp_seo_gsc_inspect', false ); ?>
				</div>
				<p class="forwp-seo-admin-muted">
					<?php esc_html_e( 'Use a URL from the selected property. Inspection can take up to a minute while Google checks the page.', '4wp-seo-helper' ); ?>
				</p>
			</form>
			<?php $this->render_inspection_results(); ?>
		</div>

		<div class="forwp-seo-panel">
			<h2><?php esc_html_e( 'Page metrics (28 days)', '4wp-seo-helper' ); ?></h2>
			<p class="forwp-seo-admin-muted"><?php esc_html_e( 'Live Search Analytics for one URL — not stored in the database.', '4wp-seo-helper' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'forwp_seo_gsc_settings', 'forwp_seo_gsc_nonce' ); ?>
				<input type="hidden" name="forwp_seo_gsc_tab" value="<?php echo esc_attr( self::TAB_INSPECTION ); ?>" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="forwp_seo_gsc_analytics_url"><?php esc_html_e( 'URL', '4wp-seo-helper' ); ?></label></th>
						<td><input type="url" class="large-text" name="forwp_seo_gsc_analytics_url" id="forwp_seo_gsc_analytics_url" placeholder="<?php echo esc_attr( trailingslashit( $site ) ); ?>" /></td>
					</tr>
				</table>
				<div class="forwp-seo-form-actions">
					<?php submit_button( __( 'Load metrics', '4wp-seo-helper' ), 'secondary', 'forwp_seo_gsc_analytics', false ); ?>
				</div>
			</form>
			<?php $this->render_analytics_results(); ?>
		</div>
		<?php
	}

	/**
	 * Handle connect/credentials forms submitted from Settings.
	 */
	public function handle_connect_post(): ?string {
		if ( ! current_user_can( 'manage_options' ) ) {
			return null;
		}

		if ( empty( $_POST['forwp_seo_gsc_nonce'] ) ) {
			return null;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST['forwp_seo_gsc_nonce'] ) );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'forwp_seo_gsc_settings' ) ) {
			return null;
		}

		$context = isset( $_POST['forwp_seo_gsc_context'] )
			? sanitize_key( wp_unslash( (string) $_POST['forwp_seo_gsc_context'] ) )
			: '';

		if ( 'settings' !== $context && 'gsc' !== $context ) {
			return null;
		}

		$redirect_url = $this->get_settings_url();

		if ( isset( $_POST['forwp_seo_gsc_save'] ) ) {
			update_option( self::OPTION_CLIENT_ID, sanitize_text_field( wp_unslash( (string) ( $_POST['forwp_seo_gsc_client_id'] ?? '' ) ) ) );
			update_option( self::OPTION_CLIENT_SECRET, sanitize_text_field( wp_unslash( (string) ( $_POST['forwp_seo_gsc_client_secret'] ?? '' ) ) ) );
			return add_query_arg( 'gsc_saved', 'credentials', $redirect_url );
		}

		if ( isset( $_POST['forwp_seo_gsc_save_redirect'] ) ) {
			$uri = trim( wp_unslash( (string) ( $_POST['forwp_seo_gsc_redirect_uri'] ?? '' ) ) );
			if ( '' === $uri ) {
				delete_option( self::OPTION_REDIRECT_URI );
			} else {
				update_option( self::OPTION_REDIRECT_URI, esc_url_raw( $uri, [ 'http', 'https' ] ) );
			}
			return add_query_arg( 'gsc_saved', 'redirect', $redirect_url );
		}

		if ( isset( $_POST['forwp_seo_gsc_save_property'] ) ) {
			$local_dev = ! empty( $_POST['forwp_seo_gsc_local_dev_mode'] );
			update_option( Module::OPTION_LOCAL_DEV_MODE, $local_dev ? '1' : '0' );

			if ( $local_dev ) {
				update_option( self::OPTION_SITE, esc_url_raw( wp_unslash( (string) ( $_POST['forwp_seo_gsc_site'] ?? '' ) ) ) );
			} else {
				$this->sync_property_for_site();
			}

			return add_query_arg( 'gsc_saved', 'property', $redirect_url );
		}

		$clear_redirect = $this->process_clear_data_request(
			$redirect_url,
			'forwp_seo_gsc_settings',
			'forwp_seo_gsc_nonce'
		);
		if ( is_string( $clear_redirect ) ) {
			return $clear_redirect;
		}

		return null;
	}

	private function handle_data_post(): ?string {
		if ( ! current_user_can( 'manage_options' ) ) {
			return null;
		}

		if ( empty( $_POST['forwp_seo_gsc_nonce'] ) ) {
			return null;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST['forwp_seo_gsc_nonce'] ) );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'forwp_seo_gsc_settings' ) ) {
			return null;
		}

		if ( isset( $_POST['forwp_seo_gsc_save'] ) || isset( $_POST['forwp_seo_gsc_save_property'] ) ) {
			return null;
		}

		if ( isset( $_POST['forwp_seo_gsc_inspect'] ) ) {
			$url = esc_url_raw( wp_unslash( (string) ( $_POST['forwp_seo_gsc_inspect_url'] ?? '' ) ) );
			$this->set_last_inspection( $url );
			return $this->get_page_url( self::TAB_INSPECTION );
		}

		if ( isset( $_POST['forwp_seo_gsc_analytics'] ) ) {
			$url = esc_url_raw( wp_unslash( (string) ( $_POST['forwp_seo_gsc_analytics_url'] ?? '' ) ) );
			$this->set_last_analytics( $url );
			return $this->get_page_url( self::TAB_INSPECTION );
		}

		return null;
	}

	/**
	 * @return string|null Redirect URL after sync POST.
	 */
	private function handle_sync_post(): ?string {
		if ( ! current_user_can( 'manage_options' ) ) {
			return null;
		}

		if ( empty( $_POST['forwp_seo_gsc_sync_nonce'] ) ) {
			return null;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST['forwp_seo_gsc_sync_nonce'] ) );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'forwp_seo_gsc_sync' ) ) {
			return null;
		}

		if ( isset( $_POST['forwp_seo_gsc_save_brand_terms'] ) ) {
			$terms = sanitize_textarea_field( wp_unslash( (string) ( $_POST['forwp_seo_gsc_brand_terms'] ?? '' ) ) );
			update_option( 'forwp_seo_gsc_brand_terms', $terms );
			return add_query_arg( 'brand_saved', '1', $this->get_page_url( self::TAB_SYNC ) );
		}

		if ( isset( $_POST['forwp_seo_gsc_save_cron'] ) ) {
			$enabled = ! empty( $_POST['forwp_seo_gsc_cron_enabled'] );
			update_option( Module::OPTION_CRON_ENABLED, $enabled ? '1' : '0' );
			Sync::sync_cron_state( $enabled );
			return add_query_arg( 'cron_saved', '1', $this->get_page_url( self::TAB_SYNC ) );
		}

		if ( empty( $_POST['forwp_seo_gsc_run_sync'] ) ) {
			return null;
		}

		$result = Sync::get_instance()->queue_manual_sync( self::get_site_property() );
		if ( $result['ok'] ) {
			return add_query_arg( 'sync_started', '1', $this->get_page_url( self::TAB_SYNC ) );
		}

		return add_query_arg( 'sync_error', rawurlencode( $result['message'] ), $this->get_page_url( self::TAB_SYNC ) );
	}

	public function maybe_handle_callback(): void {
		if ( ! $this->is_oauth_callback_request() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- OAuth callback from Google; state transient verified below.
		$error = isset( $_GET['error'] ) ? sanitize_key( wp_unslash( (string) $_GET['error'] ) ) : '';
		if ( '' !== $error ) {
			$args = [
				'gsc_error' => $error,
			];
			if ( ! empty( $_GET['error_description'] ) ) {
				$args['gsc_error'] = sanitize_text_field( wp_unslash( (string) $_GET['error_description'] ) );
			}
			$this->redirect_after_oauth( $this->consume_pending_return_url(), $args );
		}

		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['code'] ) ) : '';
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['state'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '' === $code || '' === $state || ! preg_match( '/^[a-zA-Z0-9]{32}$/', $state ) ) {
			return;
		}

		$pending = get_transient( $this->pending_state_key( $state ) );
		delete_transient( $this->pending_state_key( $state ) );

		$return_url = is_array( $pending ) && ! empty( $pending['return_url'] )
			? (string) $pending['return_url']
			: null;

		if ( ! is_array( $pending ) || empty( $pending['return_url'] ) ) {
			$this->redirect_after_oauth( null, [ 'gsc_error' => 'invalid_state' ] );
		}

		$client_id     = get_option( self::OPTION_CLIENT_ID, '' );
		$client_secret = get_option( self::OPTION_CLIENT_SECRET, '' );
		$client        = new Client();
		$token         = $client->exchange_code( $client_id, $client_secret, $this->get_redirect_uri(), $code );

		if ( isset( $token['error'] ) ) {
			$this->redirect_after_oauth(
				$return_url,
				[ 'gsc_error' => (string) $token['error'] ]
			);
		}

		update_option( self::OPTION_ACCESS_TOKEN, sanitize_text_field( $token['access_token'] ?? '' ) );
		if ( ! empty( $token['refresh_token'] ) ) {
			update_option( self::OPTION_REFRESH_TOKEN, sanitize_text_field( $token['refresh_token'] ) );
		}
		update_option( self::OPTION_TOKEN_EXPIRES, time() + (int) ( $token['expires_in'] ?? 0 ) );

		$this->sync_property_for_site();

		$this->redirect_after_oauth( $return_url, [ 'gsc_connected' => '1' ] );
	}

	private function get_connect_url( string $client_id ): string {
		$state = wp_generate_password( 32, false );
		set_transient(
			$this->pending_state_key( $state ),
			[
				'user_id'    => get_current_user_id(),
				'return_url' => $this->get_settings_url(),
			],
			10 * MINUTE_IN_SECONDS
		);

		$client = new Client();
		return $client->get_authorization_url( $client_id, $this->get_redirect_uri(), $state );
	}

	private function get_default_redirect_uri(): string {
		return admin_url( 'admin.php?page=' . Menu::OAUTH_PAGE_SLUG );
	}

	private function get_redirect_uri(): string {
		if ( defined( 'FORWP_SEO_GSC_OAUTH_REDIRECT_URI' ) && is_string( FORWP_SEO_GSC_OAUTH_REDIRECT_URI ) && '' !== FORWP_SEO_GSC_OAUTH_REDIRECT_URI ) {
			return $this->normalize_loopback_redirect_uri( FORWP_SEO_GSC_OAUTH_REDIRECT_URI );
		}

		if ( $this->needs_localhost_redirect_help() ) {
			$suggested = $this->get_suggested_oauth_redirect_uri();
			if ( '' !== $suggested ) {
				return $suggested;
			}
		}

		$override = (string) get_option( self::OPTION_REDIRECT_URI, '' );
		if ( '' !== $override ) {
			return $this->normalize_loopback_redirect_uri( $override );
		}

		return $this->get_default_redirect_uri();
	}

	private function normalize_loopback_redirect_uri( string $uri ): string {
		$host = strtolower( (string) wp_parse_url( $uri, PHP_URL_HOST ) );
		if ( ! in_array( $host, [ '127.0.0.1', 'localhost', '[::1]' ], true ) ) {
			return $uri;
		}

		$parts = wp_parse_url( $uri );
		if ( ! is_array( $parts ) ) {
			return $uri;
		}

		$port  = isset( $parts['port'] ) ? (int) $parts['port'] : 0;
		$path  = isset( $parts['path'] ) ? $parts['path'] : '';
		$query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
		$suffix = $port > 0 && ! in_array( $port, [ 80, 443 ], true ) ? ':' . $port : '';

		// Local nginx on this port is HTTP-only. Match the URI already in Google Cloud.
		return 'http://127.0.0.1' . $suffix . $path . $query;
	}

	private function sanitize_redirect_uri_override( string $uri ): string {
		$uri = trim( $uri );
		if ( '' === $uri ) {
			return '';
		}

		return esc_url_raw( $uri, [ 'http', 'https' ] );
	}

	private function needs_localhost_redirect_help(): bool {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}

		$host = strtolower( $host );
		if ( in_array( $host, [ 'localhost', '127.0.0.1', '[::1]' ], true ) ) {
			return true;
		}

		if ( ! str_contains( $host, '.' ) ) {
			return true;
		}

		return (bool) preg_match( '/\.(local|localhost|test|invalid)$/i', $host );
	}

	private function get_suggested_oauth_redirect_uri(): string {
		if ( ! $this->needs_localhost_redirect_help() ) {
			return '';
		}

		$parts = wp_parse_url( $this->get_default_redirect_uri() );
		if ( ! is_array( $parts ) ) {
			$parts = [
				'path'  => '/wp-admin/admin.php',
				'query' => 'page=' . Menu::OAUTH_PAGE_SLUG,
			];
		}

		$port = isset( $parts['port'] ) ? (int) $parts['port'] : 0;
		if ( $port <= 0 ) {
			$port = $this->detect_local_http_port();
		}

		$port_suffix = $port > 0 && ! in_array( $port, [ 80, 443 ], true ) ? ':' . $port : '';
		$path        = isset( $parts['path'] ) ? $parts['path'] : '/wp-admin/admin.php';
		$query       = isset( $parts['query'] ) ? '?' . $parts['query'] : '';

		return 'http://127.0.0.1' . $port_suffix . $path . $query;
	}

	private function detect_local_http_port(): int {
		if ( ! empty( $_SERVER['SERVER_PORT'] ) ) {
			$port = (int) $_SERVER['SERVER_PORT'];
			if ( $port > 0 && ! in_array( $port, [ 80, 443 ], true ) ) {
				return $port;
			}
		}

		$host = isset( $_SERVER['HTTP_HOST'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_HOST'] ) )
			: '';
		if ( false !== strpos( $host, ':' ) ) {
			$port = (int) substr( $host, (int) strrpos( $host, ':' ) + 1 );
			if ( $port > 0 ) {
				return $port;
			}
		}

		$from_app = $this->detect_local_app_http_port();
		if ( $from_app > 0 ) {
			return $from_app;
		}

		return (int) apply_filters( 'forwp_seo_gsc_loopback_http_port', 0 );
	}

	private function detect_local_app_http_port(): int {
		$candidates = [];
		$home       = getenv( 'HOME' );
		$home       = is_string( $home ) ? $home : '';
		if ( '' !== $home ) {
			$candidates[] = $home . '/Library/Application Support/Local/sites.json';
		}
		$appdata = getenv( 'APPDATA' );
		if ( is_string( $appdata ) && '' !== $appdata ) {
			$candidates[] = $appdata . '/Local/sites.json';
		}

		$abspath = wp_normalize_path( untrailingslashit( ABSPATH ) );

		foreach ( $candidates as $file ) {
			if ( ! is_readable( $file ) ) {
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local app config, not a remote request.
			$json = file_get_contents( $file );
			if ( ! is_string( $json ) || '' === $json ) {
				continue;
			}

			$data = json_decode( $json, true );
			if ( ! is_array( $data ) ) {
				continue;
			}

			foreach ( $data as $site ) {
				if ( ! is_array( $site ) ) {
					continue;
				}

				$path = isset( $site['path'] ) ? (string) $site['path'] : '';
				if ( '' !== $home ) {
					$path = str_replace( '~', $home, $path );
				}
				$path = wp_normalize_path( untrailingslashit( $path ) );
				if ( '' === $path || ! str_starts_with( $abspath, $path ) ) {
					continue;
				}

				$port = (int) ( $site['services']['nginx']['ports']['HTTP'][0] ?? 0 );
				if ( $port > 0 ) {
					return $port;
				}
			}
		}

		return 0;
	}

	private function is_oauth_callback_request(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback query flag.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( Menu::OAUTH_PAGE_SLUG !== $page ) {
			return false;
		}

		$uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: '';

		return false !== strpos( $uri, 'wp-admin/admin.php' );
	}

	private function pending_state_key( string $state ): string {
		return 'forwp_seo_gsc_oauth_' . $state;
	}

	private function consume_pending( string $state ): ?array {
		if ( '' === $state || ! preg_match( '/^[a-zA-Z0-9]{32}$/', $state ) ) {
			return null;
		}

		$key     = $this->pending_state_key( $state );
		$pending = get_transient( $key );
		delete_transient( $key );

		return is_array( $pending ) ? $pending : null;
	}

	private function process_oauth_callback( string $state, string $code, string $error ): void {
		$pending    = $this->consume_pending( $state );
		$return_url = is_array( $pending ) && ! empty( $pending['return_url'] )
			? (string) $pending['return_url']
			: $this->get_settings_url();

		if ( ! is_array( $pending ) ) {
			$this->redirect_after_oauth( $return_url, [ 'gsc_error' => 'invalid_state' ] );
		}

		if ( '' !== $error ) {
			$this->redirect_after_oauth( $return_url, [ 'gsc_error' => $error ] );
		}

		if ( '' === $code ) {
			$this->redirect_after_oauth( $return_url, [ 'gsc_error' => 'missing_code' ] );
		}

		$client_id     = get_option( self::OPTION_CLIENT_ID, '' );
		$client_secret = get_option( self::OPTION_CLIENT_SECRET, '' );
		$client        = new Client();
		$token         = $client->exchange_code( $client_id, $client_secret, $this->get_redirect_uri(), $code );

		if ( isset( $token['error'] ) ) {
			$this->redirect_after_oauth( $return_url, [ 'gsc_error' => (string) $token['error'] ] );
		}

		update_option( self::OPTION_ACCESS_TOKEN, sanitize_text_field( $token['access_token'] ?? '' ) );
		if ( ! empty( $token['refresh_token'] ) ) {
			update_option( self::OPTION_REFRESH_TOKEN, sanitize_text_field( $token['refresh_token'] ) );
		}
		update_option( self::OPTION_TOKEN_EXPIRES, time() + (int) ( $token['expires_in'] ?? 0 ) );

		$this->sync_property_for_site();

		$this->redirect_after_oauth( $return_url, [ 'gsc_connected' => '1' ] );
	}

	private function consume_pending_return_url(): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth state from Google callback.
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['state'] ) ) : '';
		if ( '' === $state ) {
			return null;
		}

		$pending = $this->consume_pending( $state );
		if ( ! is_array( $pending ) || empty( $pending['return_url'] ) ) {
			return null;
		}

		return (string) $pending['return_url'];
	}

	/**
	 * @param array<string, string> $args Query args.
	 */
	private function redirect_after_oauth( ?string $return_url, array $args ): void {
		$base = is_string( $return_url ) && '' !== $return_url
			? remove_query_arg( [ 'gsc_connected', 'gsc_error', 'gsc_saved' ], $return_url )
			: $this->get_settings_url();

		wp_safe_redirect( add_query_arg( $args, $base ) );
		exit;
	}

	public function register_rest_routes(): void {
		register_rest_route(
			'forwp-seo/v1',
			'/gsc/callback',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_rest_callback' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function handle_rest_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$state = sanitize_text_field( (string) ( $request->get_param( 'state' ) ?? '' ) );
		$code  = sanitize_text_field( (string) ( $request->get_param( 'code' ) ?? '' ) );
		$error = sanitize_text_field( (string) ( $request->get_param( 'error' ) ?? '' ) );

		$this->process_oauth_callback( $state, $code, $error );

		return new \WP_REST_Response( null, 302 );
	}

	private function get_access_token(): string {
		$token   = get_option( self::OPTION_ACCESS_TOKEN, '' );
		$expires = (int) get_option( self::OPTION_TOKEN_EXPIRES, 0 );

		if ( $token && time() < $expires - 60 ) {
			return $token;
		}

		$refresh_token = get_option( self::OPTION_REFRESH_TOKEN, '' );
		if ( empty( $refresh_token ) ) {
			return '';
		}

		$client_id     = get_option( self::OPTION_CLIENT_ID, '' );
		$client_secret = get_option( self::OPTION_CLIENT_SECRET, '' );

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return '';
		}

		$client = new Client();
		$token_data = $client->refresh_token( $client_id, $client_secret, $refresh_token );

		if ( isset( $token_data['error'] ) ) {
			return '';
		}

		update_option( self::OPTION_ACCESS_TOKEN, sanitize_text_field( $token_data['access_token'] ?? '' ) );
		update_option( self::OPTION_TOKEN_EXPIRES, time() + (int) ( $token_data['expires_in'] ?? 0 ) );

		return (string) ( $token_data['access_token'] ?? '' );
	}

	private function get_properties(): array {
		$token = $this->get_access_token();
		if ( '' === $token ) {
			return [];
		}

		$client = new Client();
		$response = $client->list_sites( $token );

		if ( isset( $response['error'] ) ) {
			return [];
		}

		$properties = [];
		foreach ( $response['siteEntry'] ?? [] as $entry ) {
			if ( ! empty( $entry['siteUrl'] ) ) {
				$properties[] = $entry['siteUrl'];
			}
		}

		return $properties;
	}

	private function set_last_inspection( string $url ): void {
		if ( empty( $url ) ) {
			return;
		}

		$site  = get_option( self::OPTION_SITE, '' );
		$token = $this->get_access_token();
		if ( empty( $site ) || empty( $token ) ) {
			return;
		}

		if ( ! $this->url_belongs_to_property( $url, $site ) ) {
			set_transient(
				'forwp_seo_gsc_last_inspection',
				[
					'error' => __( 'That URL is not part of the selected Search Console property.', '4wp-seo-helper' ),
				],
				300
			);
			return;
		}

		$client = new Client();
		$result = $client->inspect_url( $token, $site, $url );
		set_transient( 'forwp_seo_gsc_last_inspection', $result, 300 );
	}

	private function set_last_analytics( string $url ): void {
		if ( empty( $url ) ) {
			return;
		}

		$site  = get_option( self::OPTION_SITE, '' );
		$token = $this->get_access_token();
		if ( empty( $site ) || empty( $token ) ) {
			return;
		}

		$end   = gmdate( 'Y-m-d' );
		$start = gmdate( 'Y-m-d', strtotime( '-28 days' ) );

		$client = new Client();
		$result = $client->search_analytics( $token, $site, $url, $start, $end );
		set_transient( 'forwp_seo_gsc_last_analytics', $result, 300 );
	}

	private function url_belongs_to_property( string $url, string $site ): bool {
		if ( '' === $url || '' === $site ) {
			return false;
		}

		return PropertyResolver::url_belongs_to_property( $url, $site );
	}

	private function sync_property_for_site(): bool {
		if ( PropertyResolver::allows_manual_property_selection() ) {
			return false;
		}

		$properties = $this->get_properties();
		$matched    = PropertyResolver::match_site_property( $properties );

		if ( ! $matched ) {
			return false;
		}

		update_option( self::OPTION_SITE, $matched );

		return true;
	}

	/**
	 * @return string|null Redirect URL after clear-data POST.
	 */
	private function process_clear_data_request( string $redirect_url, string $nonce_action, string $nonce_field ): ?string {
		if ( empty( $_POST['forwp_seo_gsc_clear_data'] ) ) {
			return null;
		}

		if ( empty( $_POST[ $nonce_field ] ) ) {
			return null;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST[ $nonce_field ] ) );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			return null;
		}

		if ( empty( $_POST['forwp_seo_gsc_clear_confirm'] ) ) {
			return add_query_arg(
				'clear_error',
				rawurlencode( __( 'Please confirm before clearing synced data.', '4wp-seo-helper' ) ),
				$redirect_url
			);
		}

		$property = self::get_site_property();
		$sync     = Sync::get_instance();

		if ( $sync->is_sync_in_progress( $property ) ) {
			return add_query_arg(
				'clear_error',
				rawurlencode( __( 'Cannot clear data while a sync is running.', '4wp-seo-helper' ) ),
				$redirect_url
			);
		}

		Sync::unschedule_manual_syncs();
		$sync->release_sync_lock( $property );
		( new Repository() )->clear_all_data();

		return add_query_arg( 'data_cleared', '1', $redirect_url );
	}

	private function render_inspection_results(): void {
		$result = get_transient( 'forwp_seo_gsc_last_inspection' );
		if ( empty( $result ) || ! is_array( $result ) ) {
			return;
		}

		if ( isset( $result['error'] ) ) {
			echo '<p class="forwp-seo-connection-line forwp-seo-connection-line--warn">' . esc_html( $result['error'] ) . '</p>';
			return;
		}

		$inspection = $result['inspectionResult']['indexStatusResult'] ?? [];
		?>
		<table class="forwp-seo-ref-table">
			<thead>
				<tr><th><?php esc_html_e( 'Field', '4wp-seo-helper' ); ?></th><th><?php esc_html_e( 'Value', '4wp-seo-helper' ); ?></th></tr>
			</thead>
			<tbody>
				<tr><td><?php esc_html_e( 'Index status', '4wp-seo-helper' ); ?></td><td><?php echo esc_html( $inspection['verdict'] ?? '' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Coverage state', '4wp-seo-helper' ); ?></td><td><?php echo esc_html( $inspection['coverageState'] ?? '' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Last crawl', '4wp-seo-helper' ); ?></td><td><?php echo esc_html( $inspection['lastCrawlTime'] ?? '' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Canonical (user)', '4wp-seo-helper' ); ?></td><td><?php echo esc_html( $inspection['userCanonical'] ?? '' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Canonical (google)', '4wp-seo-helper' ); ?></td><td><?php echo esc_html( $inspection['googleCanonical'] ?? '' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Robots state', '4wp-seo-helper' ); ?></td><td><?php echo esc_html( $inspection['robotsTxtState'] ?? '' ); ?></td></tr>
			</tbody>
		</table>
		<?php
		delete_transient( 'forwp_seo_gsc_last_inspection' );
	}

	private function render_analytics_results(): void {
		$result = get_transient( 'forwp_seo_gsc_last_analytics' );
		if ( empty( $result ) || ! is_array( $result ) ) {
			return;
		}

		if ( isset( $result['error'] ) ) {
			echo '<p class="forwp-seo-connection-line forwp-seo-connection-line--warn">' . esc_html( $result['error'] ) . '</p>';
			return;
		}

		$row = $result['rows'][0] ?? [];
		?>
		<table class="forwp-seo-ref-table">
			<thead>
				<tr><th><?php esc_html_e( 'Metric', '4wp-seo-helper' ); ?></th><th><?php esc_html_e( 'Value', '4wp-seo-helper' ); ?></th></tr>
			</thead>
			<tbody>
				<tr><td><?php esc_html_e( 'Clicks', '4wp-seo-helper' ); ?></td><td><?php echo esc_html( $row['clicks'] ?? 0 ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Impressions', '4wp-seo-helper' ); ?></td><td><?php echo esc_html( $row['impressions'] ?? 0 ); ?></td></tr>
				<tr><td><?php esc_html_e( 'CTR', '4wp-seo-helper' ); ?></td><td><?php echo esc_html( $row['ctr'] ?? 0 ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Avg position', '4wp-seo-helper' ); ?></td><td><?php echo esc_html( $row['position'] ?? 0 ); ?></td></tr>
			</tbody>
		</table>
		<?php
		delete_transient( 'forwp_seo_gsc_last_analytics' );
	}
}

