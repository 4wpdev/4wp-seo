<?php
/**
 * Plugin Name: 4WP SEO Helper
 * Description: Internal SEO toolkit: TechArticle schema, SEO inventory API, GSC, LLMS.txt, cross posting.
 * Version: 0.6.0
 * Author: 4wp.dev
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FORWP_SEO_HELPER_VERSION', '0.6.0' );
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
