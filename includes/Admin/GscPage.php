<?php
/**
 * Google Search Console admin page shell.
 */

namespace Forwp\SeoHelper\Admin;

use Forwp\SeoHelper\Gsc\Admin as GscAdmin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GscPage {
	/**
	 * Handle GSC POST and redirects before any admin HTML is sent.
	 */
	public static function handle_load(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		GscAdmin::get_instance()->handle_page_load();
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		GscAdmin::get_instance()->render_page();
	}
}
