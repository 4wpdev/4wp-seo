<?php
/**
 * GSC metrics database schema.
 */

namespace Forwp\SeoHelper\Gsc;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Schema {
	public const DB_VERSION = '1.1.0';

	public const OPTION_DB_VERSION = 'forwp_seo_gsc_db_version';

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$daily   = $wpdb->prefix . 'forwp_gsc_daily';
		$facts   = $wpdb->prefix . 'forwp_gsc_facts';
		$sync    = $wpdb->prefix . 'forwp_gsc_sync_log';

		dbDelta(
			"CREATE TABLE {$daily} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				property varchar(255) NOT NULL,
				search_type varchar(20) NOT NULL DEFAULT 'web',
				metric_date date NOT NULL,
				clicks int(11) unsigned NOT NULL DEFAULT 0,
				impressions int(11) unsigned NOT NULL DEFAULT 0,
				ctr float NOT NULL DEFAULT 0,
				position float NOT NULL DEFAULT 0,
				synced_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY property_type_date (property(191), search_type, metric_date),
				KEY metric_date (metric_date)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$facts} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				property varchar(255) NOT NULL,
				search_type varchar(20) NOT NULL DEFAULT 'web',
				dimension varchar(32) NOT NULL,
				dim_key varchar(512) NOT NULL,
				period_key varchar(32) NOT NULL,
				period_start date NOT NULL,
				period_end date NOT NULL,
				clicks int(11) unsigned NOT NULL DEFAULT 0,
				impressions int(11) unsigned NOT NULL DEFAULT 0,
				ctr float NOT NULL DEFAULT 0,
				position float NOT NULL DEFAULT 0,
				synced_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY row_identity (property(100), search_type, dimension, dim_key(191), period_key),
				KEY dimension_period (property(100), dimension, period_key),
				KEY clicks (clicks)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$sync} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				property varchar(255) NOT NULL,
				job_key varchar(64) NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				message text NULL,
				rows_count int(11) unsigned NOT NULL DEFAULT 0,
				started_at datetime NULL,
				finished_at datetime NULL,
				PRIMARY KEY  (id),
				KEY property_job (property(191), job_key)
			) {$charset};"
		);

		$query_page = $wpdb->prefix . 'forwp_gsc_query_page';

		dbDelta(
			"CREATE TABLE {$query_page} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				property varchar(255) NOT NULL,
				search_type varchar(20) NOT NULL DEFAULT 'web',
				query_key varchar(512) NOT NULL,
				page_key varchar(512) NOT NULL,
				period_key varchar(32) NOT NULL,
				period_start date NOT NULL,
				period_end date NOT NULL,
				clicks int(11) unsigned NOT NULL DEFAULT 0,
				impressions int(11) unsigned NOT NULL DEFAULT 0,
				ctr float NOT NULL DEFAULT 0,
				position float NOT NULL DEFAULT 0,
				synced_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY row_identity (property(100), search_type, query_key(191), page_key(191), period_key),
				KEY query_period (property(100), query_key(191), period_key),
				KEY page_period (property(100), page_key(191), period_key)
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

		$table = $wpdb->prefix . 'forwp_gsc_daily';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}
}
