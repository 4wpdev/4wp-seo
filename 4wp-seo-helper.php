<?php
/**
 * Plugin Name: 4WP SEO Helper
 * Description: SEO Inventory for WordPress — audit and prioritize SEO fields site-wide. Works with Yoast SEO and All in One SEO.
 * Version: 1.0.0
 * Author: 4wp.dev
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * Tested up to:      7.0
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       4wp-seo-helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FORWP_SEO_HELPER_VERSION', '1.0.0' );
define( 'FORWP_SEO_HELPER_FILE', __FILE__ );
define( 'FORWP_SEO_HELPER_PATH', plugin_dir_path( __FILE__ ) );
define( 'FORWP_SEO_HELPER_URL', plugin_dir_url( __FILE__ ) );

require_once FORWP_SEO_HELPER_PATH . 'includes/autoload.php';

if ( class_exists( '\Forwp\SeoHelper\Core\Extension' ) ) {
	\Forwp\SeoHelper\Core\Extension::get_instance();
}

register_activation_hook(
	__FILE__,
	function () {
		if ( class_exists( '\Forwp\SeoHelper\Llms\Generator' ) ) {
			\Forwp\SeoHelper\Llms\Generator::activate();
		}
		if ( class_exists( '\Forwp\SeoHelper\Inventory\Module' ) ) {
			\Forwp\SeoHelper\Inventory\Module::activate();
		}
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		if ( class_exists( '\Forwp\SeoHelper\Llms\Generator' ) ) {
			\Forwp\SeoHelper\Llms\Generator::deactivate();
		}
	}
);
