<?php
/**
 * Admin page output.
 */

namespace Forwp\SeoHelper\Admin;

use Forwp\SeoHelper\CrossPosting\Module as CrossPostingModule;
use Forwp\SeoHelper\Gsc\Admin as GscAdmin;
use Forwp\SeoHelper\Inventory\Module as InventoryModule;
use Forwp\SeoHelper\Inventory\Repository;
use Forwp\SeoHelper\Multilingual\Registry as MultilingualRegistry;
use Forwp\SeoHelper\SeoMeta\Registry as SeoMetaRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Page {
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		self::handle_settings_post();

		$is_connected = GscAdmin::get_instance()->is_connected();
		$crossposting_enabled = CrossPostingModule::get_instance()->is_enabled();
		$inventory_enabled    = InventoryModule::get_instance()->is_enabled();
		$seo_adapter          = SeoMetaRegistry::get_active();
		$multilingual         = MultilingualRegistry::get_active();
		$inventory_stats      = $inventory_enabled ? ( new Repository() )->get_stats() : null;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( '4wp SEO', '4wp-seo' ); ?></h1>
			<p>
				<?php esc_html_e( 'Internal SEO toolkit: schema, inventory API, GSC, LLMS.txt.', '4wp-seo' ); ?>
				<?php if ( $inventory_enabled ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=4wp-seo-inventory' ) ); ?>"><?php esc_html_e( 'Open SEO Inventory', '4wp-seo' ); ?></a>
				<?php endif; ?>
			</p>
			<ul>
				<li><?php esc_html_e( 'Schema.org: TechArticle', '4wp-seo' ); ?></li>
				<li>
					<?php
					echo esc_html(
						$is_connected
							? __( 'Google Search Console: connected', '4wp-seo' )
							: __( 'Google Search Console: not connected', '4wp-seo' )
					);
					?>
				</li>
				<li><?php esc_html_e( 'LLMS.txt: /llms.txt', '4wp-seo' ); ?></li>
				<li>
					<?php
					if ( $inventory_enabled && is_array( $inventory_stats ) ) {
						printf(
							/* translators: 1: post count, 2: average completeness percent */
							esc_html__( 'SEO inventory: %1$d posts, %2$d%% avg completeness', '4wp-seo' ),
							(int) $inventory_stats['posts'],
							(int) $inventory_stats['avg_completeness']
						);
					} else {
						echo esc_html(
							$inventory_enabled
								? __( 'SEO inventory API: enabled', '4wp-seo' )
								: __( 'SEO inventory API: disabled', '4wp-seo' )
						);
					}
					?>
				</li>
				<li>
					<?php
					echo esc_html(
						$crossposting_enabled
							? __( 'Cross posting: enabled', '4wp-seo' )
							: __( 'Cross posting: disabled', '4wp-seo' )
					);
					?>
				</li>
				<li>
					<?php
					printf(
						/* translators: 1: SEO adapter label, 2: multilingual provider label */
						esc_html__( 'SEO adapter: %1$s | Multilingual: %2$s', '4wp-seo' ),
						esc_html( $seo_adapter->get_label() ),
						esc_html( $multilingual->get_label() )
					);
					?>
				</li>
			</ul>
			<?php self::render_notices(); ?>
			<?php self::render_settings( $crossposting_enabled, $inventory_enabled ); ?>
			<?php self::render_inventory_section( $inventory_enabled ); ?>
			<?php GscAdmin::get_instance()->render_section(); ?>
		</div>
		<?php
	}

	private static function handle_settings_post(): void {
		if ( empty( $_POST['forwp_seo_settings_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['forwp_seo_settings_nonce'], 'forwp_seo_settings' ) ) {
			return;
		}

		$enabled = isset( $_POST['forwp_seo_crossposting_enabled'] ) ? '1' : '0';
		update_option( CrossPostingModule::OPTION_ENABLED, $enabled );

		$inventory_enabled = isset( $_POST['forwp_seo_inventory_enabled'] ) ? '1' : '0';
		update_option( InventoryModule::OPTION_ENABLED, $inventory_enabled );

		if ( ! empty( $_POST['forwp_seo_regenerate_inventory_token'] ) ) {
			InventoryModule::get_instance()->regenerate_api_token();
			add_settings_error( 'forwp_seo', 'token_regenerated', __( 'Sync token regenerated.', '4wp-seo' ), 'success' );
		}

		if ( isset( $_POST['forwp_seo_crossposting_enabled'] ) || isset( $_POST['forwp_seo_inventory_enabled'] ) ) {
			add_settings_error( 'forwp_seo', 'settings_saved', __( 'Settings saved.', '4wp-seo' ), 'success' );
		}
	}

	private static function render_settings( bool $crossposting_enabled, bool $inventory_enabled ): void {
		?>
		<h2><?php esc_html_e( 'Settings', '4wp-seo' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'forwp_seo_settings', 'forwp_seo_settings_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Cross posting module', '4wp-seo' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="forwp_seo_crossposting_enabled" value="1" <?php checked( $crossposting_enabled ); ?> />
							<?php esc_html_e( 'Enable cross posting tools in editor', '4wp-seo' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'SEO inventory API', '4wp-seo' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="forwp_seo_inventory_enabled" value="1" <?php checked( $inventory_enabled ); ?> />
							<?php esc_html_e( 'Enable REST inventory endpoints for dashboard and Google Sheets', '4wp-seo' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save settings', '4wp-seo' ) ); ?>
		</form>
		<?php
	}

	private static function render_inventory_section( bool $inventory_enabled ): void {
		$token    = InventoryModule::get_instance()->get_api_token();
		$base_url = rest_url( 'forwp-seo-helper/v1/seo-inventory' );

		if ( '' === $token ) {
			$token = InventoryModule::get_instance()->regenerate_api_token();
		}
		?>
		<h2><?php esc_html_e( 'SEO Inventory API', '4wp-seo' ); ?></h2>
		<p><?php esc_html_e( 'Use these endpoints from 4wp-analytics-dashboard or Google Apps Script.', '4wp-seo' ); ?></p>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Base URL', '4wp-seo' ); ?></th>
				<td><code><?php echo esc_html( $base_url ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Sync token', '4wp-seo' ); ?></th>
				<td>
					<code><?php echo esc_html( $token ); ?></code>
					<form method="post" style="margin-top: 8px;">
						<?php wp_nonce_field( 'forwp_seo_settings', 'forwp_seo_settings_nonce' ); ?>
						<input type="hidden" name="forwp_seo_regenerate_inventory_token" value="1" />
						<?php submit_button( __( 'Regenerate token', '4wp-seo' ), 'secondary', 'submit', false ); ?>
					</form>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Auth header', '4wp-seo' ); ?></th>
				<td><code>Authorization: Bearer &lt;token&gt;</code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'API contract', '4wp-seo' ); ?></th>
				<td><code><?php echo esc_html( rest_url( 'forwp-seo-helper/v1/seo-inventory/meta' ) ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', '4wp-seo' ); ?></th>
				<td>
					<?php
					echo esc_html(
						$inventory_enabled
							? __( 'Enabled', '4wp-seo' )
							: __( 'Disabled — enable in settings above', '4wp-seo' )
					);
					?>
				</td>
			</tr>
		</table>
		<p><?php esc_html_e( 'Sample Google Apps Script: docs/google-sheets-sync.gs in the plugin folder.', '4wp-seo' ); ?></p>
		<?php
	}

	private static function render_notices(): void {
		settings_errors( 'forwp_seo' );
		if ( ! empty( $_GET['gsc_error'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $_GET['gsc_error'] ) . '</p></div>';
		}
		if ( ! empty( $_GET['gsc_connected'] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Google Search Console connected.', '4wp-seo' ) . '</p></div>';
		}
	}
}

