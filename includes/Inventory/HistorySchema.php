<?php
/**
 * Compact SEO change-history table.
 */

namespace Forwp\SeoHelper\Inventory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HistorySchema {
	public const DB_VERSION        = '1.0.0';
	public const OPTION_DB_VERSION = 'forwp_seo_history_db_version';

	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'forwp_seo_history';
	}

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				post_id bigint(20) unsigned NOT NULL,
				event_type varchar(24) NOT NULL,
				occurred_at datetime NOT NULL,
				fingerprint char(32) NOT NULL,
				snapshot longtext NOT NULL,
				PRIMARY KEY  (id),
				KEY post_time (post_id, occurred_at),
				KEY post_type_time (post_id, event_type, occurred_at),
				KEY occurred (occurred_at)
			) {$charset};"
		);

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION );
	}

	public static function maybe_upgrade(): void {
		$installed = get_option( self::OPTION_DB_VERSION, '' );
		if ( self::DB_VERSION !== $installed ) {
			self::install();
		}
	}

	public static function tables_exist(): bool {
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}
}
