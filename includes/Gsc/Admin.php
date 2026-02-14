<?php
/**
 * Google Search Console admin screen.
 */

namespace Forwp\Seo\Gsc;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {
	private static $instance = null;

	private const OPTION_CLIENT_ID = 'forwp_seo_gsc_client_id';
	private const OPTION_CLIENT_SECRET = 'forwp_seo_gsc_client_secret';
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
		add_action( 'admin_post_forwp_seo_gsc_callback', [ $this, 'handle_callback' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	public function render_section(): void {
		$this->handle_post();

		$client_id     = get_option( self::OPTION_CLIENT_ID, '' );
		$client_secret = get_option( self::OPTION_CLIENT_SECRET, '' );
		$site          = get_option( self::OPTION_SITE, '' );

		$is_connected = $this->is_connected();
		$redirect_uri = $this->get_redirect_uri();

		?>
		<h2><?php esc_html_e( 'Google Search Console', '4wp-seo' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'forwp_seo_gsc_settings', 'forwp_seo_gsc_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="forwp_seo_gsc_client_id"><?php esc_html_e( 'Client ID', '4wp-seo' ); ?></label></th>
					<td><input type="text" class="regular-text" name="forwp_seo_gsc_client_id" id="forwp_seo_gsc_client_id" value="<?php echo esc_attr( $client_id ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="forwp_seo_gsc_client_secret"><?php esc_html_e( 'Client Secret', '4wp-seo' ); ?></label></th>
					<td><input type="password" class="regular-text" name="forwp_seo_gsc_client_secret" id="forwp_seo_gsc_client_secret" value="<?php echo esc_attr( $client_secret ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Redirect URI', '4wp-seo' ); ?></th>
					<td><code><?php echo esc_html( $redirect_uri ); ?></code></td>
				</tr>
			</table>
			<?php submit_button( __( 'Save credentials', '4wp-seo' ), 'primary', 'forwp_seo_gsc_save' ); ?>
		</form>

		<p>
			<?php if ( ! $is_connected && $client_id && $client_secret ) : ?>
				<a class="button button-secondary" href="<?php echo esc_url( $this->get_connect_url( $client_id ) ); ?>">
					<?php esc_html_e( 'Connect to Google', '4wp-seo' ); ?>
				</a>
			<?php elseif ( $is_connected ) : ?>
				<span style="color: #2e7d32;"><?php esc_html_e( 'Connected', '4wp-seo' ); ?></span>
			<?php else : ?>
				<?php esc_html_e( 'Save Client ID/Secret to enable connect.', '4wp-seo' ); ?>
			<?php endif; ?>
		</p>

		<?php
		if ( $is_connected ) :
			$properties = $this->get_properties();
			?>
			<h3><?php esc_html_e( 'Properties', '4wp-seo' ); ?></h3>
			<form method="post">
				<?php wp_nonce_field( 'forwp_seo_gsc_settings', 'forwp_seo_gsc_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Choose property', '4wp-seo' ); ?></th>
						<td>
							<?php if ( empty( $properties ) ) : ?>
								<?php esc_html_e( 'No properties found or API error.', '4wp-seo' ); ?>
							<?php else : ?>
								<select name="forwp_seo_gsc_site">
									<?php foreach ( $properties as $property ) : ?>
										<option value="<?php echo esc_attr( $property ); ?>" <?php selected( $site, $property ); ?>>
											<?php echo esc_html( $property ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save property', '4wp-seo' ), 'secondary', 'forwp_seo_gsc_save_property' ); ?>
			</form>
		<?php endif; ?>

		<?php if ( $is_connected && $site ) : ?>
			<h3><?php esc_html_e( 'URL Inspection', '4wp-seo' ); ?></h3>
			<form method="post">
				<?php wp_nonce_field( 'forwp_seo_gsc_settings', 'forwp_seo_gsc_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="forwp_seo_gsc_inspect_url"><?php esc_html_e( 'URL', '4wp-seo' ); ?></label></th>
						<td><input type="url" class="regular-text" name="forwp_seo_gsc_inspect_url" id="forwp_seo_gsc_inspect_url" placeholder="https://example.com/page"></td>
					</tr>
				</table>
				<?php submit_button( __( 'Inspect URL', '4wp-seo' ), 'secondary', 'forwp_seo_gsc_inspect' ); ?>
			</form>
			<?php $this->render_inspection_results(); ?>

			<h3><?php esc_html_e( 'Search Analytics (last 28 days)', '4wp-seo' ); ?></h3>
			<form method="post">
				<?php wp_nonce_field( 'forwp_seo_gsc_settings', 'forwp_seo_gsc_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="forwp_seo_gsc_analytics_url"><?php esc_html_e( 'URL', '4wp-seo' ); ?></label></th>
						<td><input type="url" class="regular-text" name="forwp_seo_gsc_analytics_url" id="forwp_seo_gsc_analytics_url" placeholder="https://example.com/page"></td>
					</tr>
				</table>
				<?php submit_button( __( 'Load metrics', '4wp-seo' ), 'secondary', 'forwp_seo_gsc_analytics' ); ?>
			</form>
			<?php $this->render_analytics_results(); ?>
		<?php endif; ?>
		<?php
	}

	public function is_connected(): bool {
		return $this->get_access_token() !== '';
	}

	private function handle_post(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( empty( $_POST['forwp_seo_gsc_nonce'] ) || ! wp_verify_nonce( $_POST['forwp_seo_gsc_nonce'], 'forwp_seo_gsc_settings' ) ) {
			return;
		}

		if ( isset( $_POST['forwp_seo_gsc_save'] ) ) {
			update_option( self::OPTION_CLIENT_ID, sanitize_text_field( $_POST['forwp_seo_gsc_client_id'] ?? '' ) );
			update_option( self::OPTION_CLIENT_SECRET, sanitize_text_field( $_POST['forwp_seo_gsc_client_secret'] ?? '' ) );
		}

		if ( isset( $_POST['forwp_seo_gsc_save_property'] ) ) {
			update_option( self::OPTION_SITE, esc_url_raw( $_POST['forwp_seo_gsc_site'] ?? '' ) );
		}

		if ( isset( $_POST['forwp_seo_gsc_inspect'] ) ) {
			$url = esc_url_raw( $_POST['forwp_seo_gsc_inspect_url'] ?? '' );
			$this->set_last_inspection( $url );
		}

		if ( isset( $_POST['forwp_seo_gsc_analytics'] ) ) {
			$url = esc_url_raw( $_POST['forwp_seo_gsc_analytics_url'] ?? '' );
			$this->set_last_analytics( $url );
		}
	}

	public function handle_callback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=4wp-seo' ) );
			exit;
		}

		$state = sanitize_text_field( $_GET['state'] ?? '' );
		$code  = sanitize_text_field( $_GET['code'] ?? '' );
		$error = sanitize_text_field( $_GET['error'] ?? '' );

		$stored_state = get_transient( 'forwp_seo_gsc_state' );
		if ( empty( $state ) || $state !== $stored_state ) {
			wp_safe_redirect( admin_url( 'admin.php?page=4wp-seo' ) );
			exit;
		}

		delete_transient( 'forwp_seo_gsc_state' );

		if ( $error ) {
			wp_safe_redirect( admin_url( 'admin.php?page=4wp-seo&gsc_error=' . rawurlencode( $error ) ) );
			exit;
		}

		if ( empty( $code ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=4wp-seo&gsc_error=missing_code' ) );
			exit;
		}

		$client_id     = get_option( self::OPTION_CLIENT_ID, '' );
		$client_secret = get_option( self::OPTION_CLIENT_SECRET, '' );
		$redirect_uri  = $this->get_redirect_uri();

		$client = new Client();
		$token  = $client->exchange_code( $client_id, $client_secret, $redirect_uri, $code );

		if ( isset( $token['error'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=4wp-seo&gsc_error=' . rawurlencode( $token['error'] ) ) );
			exit;
		}

		update_option( self::OPTION_ACCESS_TOKEN, sanitize_text_field( $token['access_token'] ?? '' ) );
		if ( ! empty( $token['refresh_token'] ) ) {
			update_option( self::OPTION_REFRESH_TOKEN, sanitize_text_field( $token['refresh_token'] ) );
		}
		update_option( self::OPTION_TOKEN_EXPIRES, time() + (int) ( $token['expires_in'] ?? 0 ) );

		wp_safe_redirect( admin_url( 'admin.php?page=4wp-seo&gsc_connected=1' ) );
		exit;
	}

	private function get_connect_url( string $client_id ): string {
		$state = wp_generate_password( 24, false );
		set_transient( 'forwp_seo_gsc_state', $state, 600 );

		$client = new Client();
		return $client->get_authorization_url( $client_id, $this->get_redirect_uri(), $state );
	}

	private function get_redirect_uri(): string {
		return rest_url( 'forwp-seo/v1/gsc/callback' );
	}

	public function register_rest_routes(): void {
		register_rest_route(
			'forwp-seo/v1',
			'/gsc/callback',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_rest_callback' ],
				'permission_callback' => '__return_true', // Public endpoint, but secured by state parameter
			]
		);
	}

	public function handle_rest_callback( \WP_REST_Request $request ): \WP_REST_Response {
		$state = sanitize_text_field( $request->get_param( 'state' ) ?? '' );
		$code  = sanitize_text_field( $request->get_param( 'code' ) ?? '' );
		$error = sanitize_text_field( $request->get_param( 'error' ) ?? '' );

		$stored_state = get_transient( 'forwp_seo_gsc_state' );
		if ( empty( $state ) || $state !== $stored_state ) {
			wp_safe_redirect( admin_url( 'admin.php?page=4wp-seo&gsc_error=invalid_state' ) );
			exit;
		}

		delete_transient( 'forwp_seo_gsc_state' );

		if ( $error ) {
			wp_safe_redirect( admin_url( 'admin.php?page=4wp-seo&gsc_error=' . rawurlencode( $error ) ) );
			exit;
		}

		if ( empty( $code ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=4wp-seo&gsc_error=missing_code' ) );
			exit;
		}

		$client_id     = get_option( self::OPTION_CLIENT_ID, '' );
		$client_secret = get_option( self::OPTION_CLIENT_SECRET, '' );
		$redirect_uri  = $this->get_redirect_uri();

		$client = new Client();
		$token  = $client->exchange_code( $client_id, $client_secret, $redirect_uri, $code );

		if ( isset( $token['error'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=4wp-seo&gsc_error=' . rawurlencode( $token['error'] ) ) );
			exit;
		}

		update_option( self::OPTION_ACCESS_TOKEN, sanitize_text_field( $token['access_token'] ?? '' ) );
		if ( ! empty( $token['refresh_token'] ) ) {
			update_option( self::OPTION_REFRESH_TOKEN, sanitize_text_field( $token['refresh_token'] ) );
		}
		update_option( self::OPTION_TOKEN_EXPIRES, time() + (int) ( $token['expires_in'] ?? 0 ) );

		wp_safe_redirect( admin_url( 'admin.php?page=4wp-seo&gsc_connected=1' ) );
		exit;
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

	private function render_inspection_results(): void {
		$result = get_transient( 'forwp_seo_gsc_last_inspection' );
		if ( empty( $result ) || ! is_array( $result ) ) {
			return;
		}

		if ( isset( $result['error'] ) ) {
			echo '<p style="color:#b32d2e;">' . esc_html( $result['error'] ) . '</p>';
			return;
		}

		$inspection = $result['inspectionResult']['indexStatusResult'] ?? [];
		?>
		<table class="widefat striped" style="max-width: 900px;">
			<thead>
				<tr><th><?php esc_html_e( 'Field', '4wp-seo' ); ?></th><th><?php esc_html_e( 'Value', '4wp-seo' ); ?></th></tr>
			</thead>
			<tbody>
				<tr><td><?php esc_html_e( 'Index status', '4wp-seo' ); ?></td><td><?php echo esc_html( $inspection['verdict'] ?? '' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Coverage state', '4wp-seo' ); ?></td><td><?php echo esc_html( $inspection['coverageState'] ?? '' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Last crawl', '4wp-seo' ); ?></td><td><?php echo esc_html( $inspection['lastCrawlTime'] ?? '' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Canonical (user)', '4wp-seo' ); ?></td><td><?php echo esc_html( $inspection['userCanonical'] ?? '' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Canonical (google)', '4wp-seo' ); ?></td><td><?php echo esc_html( $inspection['googleCanonical'] ?? '' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Robots state', '4wp-seo' ); ?></td><td><?php echo esc_html( $inspection['robotsTxtState'] ?? '' ); ?></td></tr>
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
			echo '<p style="color:#b32d2e;">' . esc_html( $result['error'] ) . '</p>';
			return;
		}

		$row = $result['rows'][0] ?? [];
		?>
		<table class="widefat striped" style="max-width: 600px;">
			<thead>
				<tr><th><?php esc_html_e( 'Metric', '4wp-seo' ); ?></th><th><?php esc_html_e( 'Value', '4wp-seo' ); ?></th></tr>
			</thead>
			<tbody>
				<tr><td><?php esc_html_e( 'Clicks', '4wp-seo' ); ?></td><td><?php echo esc_html( $row['clicks'] ?? 0 ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Impressions', '4wp-seo' ); ?></td><td><?php echo esc_html( $row['impressions'] ?? 0 ); ?></td></tr>
				<tr><td><?php esc_html_e( 'CTR', '4wp-seo' ); ?></td><td><?php echo esc_html( $row['ctr'] ?? 0 ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Avg position', '4wp-seo' ); ?></td><td><?php echo esc_html( $row['position'] ?? 0 ); ?></td></tr>
			</tbody>
		</table>
		<?php
		delete_transient( 'forwp_seo_gsc_last_analytics' );
	}
}

