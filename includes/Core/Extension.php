<?php
/**
 * Plugin bootstrap.
 */

namespace Forwp\Seo\Core;

use Forwp\Seo\Admin\Editor;
use Forwp\Seo\Admin\Menu;
use Forwp\Seo\Blocks\TechArticleSteps;
use Forwp\Seo\CrossPosting\Module as CrossPostingModule;
use Forwp\Seo\Gsc\Admin as GscAdmin;
use Forwp\Seo\Llms\Generator;
use Forwp\Seo\Schema\TechArticle;
use Forwp\Seo\Schema\ExternalEntities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Extension {
	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init();
	}

	private function init(): void {
		add_action( 'init', [ $this, 'load_textdomain' ] );
		add_action( 'init', [ $this, 'register_post_meta' ] );
		add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_post_meta' ] );

		TechArticle::get_instance();
		ExternalEntities::get_instance();
		TechArticleSteps::get_instance();
		Generator::get_instance();
		CrossPostingModule::get_instance();
		GscAdmin::get_instance(); // Must be initialized for REST API callback

		if ( is_admin() ) {
			Menu::get_instance();
			Editor::get_instance();
		}
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( '4wp-seo', false, dirname( plugin_basename( FORWP_SEO_FILE ) ) . '/languages' );
	}

	public function register_post_meta(): void {
		register_post_meta(
			'',
			TechArticle::META_KEY,
			[
				'single'       => true,
				'type'         => 'boolean',
				'show_in_rest' => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			]
		);
	}

	public function register_meta_boxes(): void {
		$post_types = apply_filters( 'forwp_seo_supported_post_types', [ 'post' ] );
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'forwp-seo-techarticle',
				'4wp SEO',
				[ $this, 'render_meta_box' ],
				$post_type,
				'side',
				'default'
			);
		}
	}

	public function render_meta_box( \WP_Post $post ): void {
		$enabled = TechArticle::get_instance()->is_enabled_for_post( $post->ID );
		wp_nonce_field( 'forwp_seo_meta_box', 'forwp_seo_meta_nonce' );
		?>
		<p>
			<label>
				<input type="checkbox" name="forwp_seo_techarticle_enabled" value="1" <?php checked( $enabled ); ?> />
				<?php esc_html_e( 'Enable TechArticle schema for this post', '4wp-seo' ); ?>
			</label>
		</p>
		<?php
	}

	public function save_post_meta( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['forwp_seo_meta_nonce'] ) || ! wp_verify_nonce( $_POST['forwp_seo_meta_nonce'], 'forwp_seo_meta_box' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$enabled = isset( $_POST['forwp_seo_techarticle_enabled'] ) ? '1' : '0';
		update_post_meta( $post_id, TechArticle::META_KEY, $enabled );
		delete_post_meta( $post_id, '_4wp_seo_techarticle_enabled' );
	}
}

