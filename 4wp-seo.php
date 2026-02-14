<?php
/**
 * Plugin Name: 4wp SEO
 * Description: Internal SEO plugin with Schema.org, GSC, and LLMS.txt modules.
 * Version: 0.1.0
 * Author: 4wp.dev
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FORWP_SEO_VERSION', '0.1.0' );
define( 'FORWP_SEO_FILE', __FILE__ );
define( 'FORWP_SEO_PATH', plugin_dir_path( __FILE__ ) );
define( 'FORWP_SEO_URL', plugin_dir_url( __FILE__ ) );

require_once FORWP_SEO_PATH . 'includes/autoload.php';

if ( class_exists( '\Forwp\Seo\Core\Extension' ) ) {
	\Forwp\Seo\Core\Extension::get_instance();
}

register_activation_hook(
	__FILE__,
	function () {
		if ( class_exists( '\Forwp\Seo\Llms\Generator' ) ) {
			\Forwp\Seo\Llms\Generator::activate();
		}
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		if ( class_exists( '\Forwp\Seo\Llms\Generator' ) ) {
			\Forwp\Seo\Llms\Generator::deactivate();
		}
	}
);

