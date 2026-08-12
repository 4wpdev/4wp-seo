<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	function ( $class ) {
		$prefix = 'Forwp\\SeoHelper\\';
		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$relative = str_replace( '\\', '/', $relative );
		$path     = FORWP_SEO_HELPER_PATH . 'includes/' . $relative . '.php';

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
);
