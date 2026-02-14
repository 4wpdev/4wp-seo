<?php
/**
 * Admin page output.
 */

namespace Forwp\Seo\Admin;

use Forwp\Seo\CrossPosting\Module as CrossPostingModule;
use Forwp\Seo\Gsc\Admin as GscAdmin;

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
		?>
		<div class="wrap">
			<h1><?php esc_html_e( '4wp SEO', '4wp-seo' ); ?></h1>
			<p><?php esc_html_e( 'Internal plugin settings are minimal in v0.1.', '4wp-seo' ); ?></p>
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
					echo esc_html(
						$crossposting_enabled
							? __( 'Cross posting: enabled', '4wp-seo' )
							: __( 'Cross posting: disabled', '4wp-seo' )
					);
					?>
				</li>
			</ul>
			<?php self::render_notices(); ?>
			<?php self::render_settings( $crossposting_enabled ); ?>
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
	}

	private static function render_settings( bool $crossposting_enabled ): void {
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
			</table>
			<?php submit_button( __( 'Save settings', '4wp-seo' ) ); ?>
		</form>
		<?php
	}

	private static function render_notices(): void {
		if ( ! empty( $_GET['gsc_error'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $_GET['gsc_error'] ) . '</p></div>';
		}
		if ( ! empty( $_GET['gsc_connected'] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Google Search Console connected.', '4wp-seo' ) . '</p></div>';
		}
	}
}

