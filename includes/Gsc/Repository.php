<?php
/**
 * GSC metrics persistence.
 */

namespace Forwp\SeoHelper\Gsc;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Repository {
	/**
	 * @param list<array{metric_date:string,clicks:int,impressions:int,ctr:float,position:float}> $rows
	 */
	public function replace_daily_rows( string $property, string $search_type, array $rows ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'forwp_gsc_daily';
		$now   = gmdate( 'Y-m-d H:i:s' );
		$count = 0;

		foreach ( $rows as $row ) {
			$date = $row['metric_date'] ?? '';
			if ( '' === $date ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->replace(
				$table,
				[
					'property'    => $property,
					'search_type' => $search_type,
					'metric_date' => $date,
					'clicks'      => (int) ( $row['clicks'] ?? 0 ),
					'impressions' => (int) ( $row['impressions'] ?? 0 ),
					'ctr'         => (float) ( $row['ctr'] ?? 0 ),
					'position'    => (float) ( $row['position'] ?? 0 ),
					'synced_at'   => $now,
				],
				[ '%s', '%s', '%s', '%d', '%d', '%f', '%f', '%s' ]
			);
			++$count;
		}

		return $count;
	}

	/**
	 * @param list<array{query_key:string,page_key:string,clicks:int,impressions:int,ctr:float,position:float}> $rows
	 */
	public function replace_query_page_rows(
		string $property,
		string $search_type,
		string $period_key,
		string $period_start,
		string $period_end,
		array $rows
	): int {
		global $wpdb;

		$table = $wpdb->prefix . 'forwp_gsc_query_page';
		$now   = gmdate( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$table,
			[
				'property'    => $property,
				'search_type' => $search_type,
				'period_key'  => $period_key,
			],
			[ '%s', '%s', '%s' ]
		);

		$count = 0;
		foreach ( $rows as $row ) {
			$query = (string) ( $row['query_key'] ?? '' );
			$page  = (string) ( $row['page_key'] ?? '' );
			if ( '' === $query || '' === $page ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$table,
				[
					'property'     => $property,
					'search_type'  => $search_type,
					'query_key'      => mb_substr( $query, 0, 512 ),
					'page_key'       => mb_substr( $page, 0, 512 ),
					'period_key'     => $period_key,
					'period_start'   => $period_start,
					'period_end'     => $period_end,
					'clicks'         => (int) ( $row['clicks'] ?? 0 ),
					'impressions'    => (int) ( $row['impressions'] ?? 0 ),
					'ctr'            => (float) ( $row['ctr'] ?? 0 ),
					'position'       => (float) ( $row['position'] ?? 0 ),
					'synced_at'      => $now,
				],
				[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%f', '%f', '%s' ]
			);
			++$count;
		}

		return $count;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function get_query_page_rows( string $property, string $search_type, string $period_key ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'forwp_gsc_query_page';
		if ( ! $this->query_page_table_exists() ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT query_key, page_key, clicks, impressions, ctr, position
				FROM {$table}
				WHERE property = %s AND search_type = %s AND period_key = %s
				ORDER BY clicks DESC",
				$property,
				$search_type,
				$period_key
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	public function query_page_table_exists(): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'forwp_gsc_query_page';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * @param list<array{dim_key:string,clicks:int,impressions:int,ctr:float,position:float}> $rows
	 */
	public function replace_fact_rows(
		string $property,
		string $search_type,
		string $dimension,
		string $period_key,
		string $period_start,
		string $period_end,
		array $rows
	): int {
		global $wpdb;

		$table = $wpdb->prefix . 'forwp_gsc_facts';
		$now   = gmdate( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$table,
			[
				'property'    => $property,
				'search_type' => $search_type,
				'dimension'   => $dimension,
				'period_key'  => $period_key,
			],
			[ '%s', '%s', '%s', '%s' ]
		);

		$count = 0;
		foreach ( $rows as $row ) {
			$key = (string) ( $row['dim_key'] ?? '' );
			if ( '' === $key ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$table,
				[
					'property'     => $property,
					'search_type'  => $search_type,
					'dimension'    => $dimension,
					'dim_key'      => mb_substr( $key, 0, 512 ),
					'period_key'   => $period_key,
					'period_start' => $period_start,
					'period_end'   => $period_end,
					'clicks'       => (int) ( $row['clicks'] ?? 0 ),
					'impressions'  => (int) ( $row['impressions'] ?? 0 ),
					'ctr'          => (float) ( $row['ctr'] ?? 0 ),
					'position'     => (float) ( $row['position'] ?? 0 ),
					'synced_at'    => $now,
				],
				[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%f', '%f', '%s' ]
			);
			++$count;
		}

		return $count;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function get_daily_range( string $property, string $search_type, string $start, string $end ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'forwp_gsc_daily';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT metric_date, clicks, impressions, ctr, position
				FROM {$table}
				WHERE property = %s AND search_type = %s AND metric_date BETWEEN %s AND %s
				ORDER BY metric_date ASC",
				$property,
				$search_type,
				$start,
				$end
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * @return array{clicks:int,impressions:int,ctr:float,position:float}
	 */
	public function sum_daily_range( string $property, string $search_type, string $start, string $end ): array {
		$rows = $this->get_daily_range( $property, $search_type, $start, $end );

		$clicks      = 0;
		$impressions = 0;
		$weighted    = 0.0;

		foreach ( $rows as $row ) {
			$clicks      += (int) $row['clicks'];
			$impressions += (int) $row['impressions'];
			$weighted    += (float) $row['position'] * (int) $row['impressions'];
		}

		return [
			'clicks'      => $clicks,
			'impressions' => $impressions,
			'ctr'         => $impressions > 0 ? round( $clicks / $impressions, 4 ) : 0.0,
			'position'    => $impressions > 0 ? round( $weighted / $impressions, 2 ) : 0.0,
		];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function get_facts(
		string $property,
		string $search_type,
		string $dimension,
		string $period_key,
		int $limit = 20,
		string $order_by = 'clicks'
	): array {
		global $wpdb;

		$table   = $wpdb->prefix . 'forwp_gsc_facts';
		$allowed = [ 'clicks', 'impressions', 'position' ];
		$column  = in_array( $order_by, $allowed, true ) ? $order_by : 'clicks';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT dim_key, clicks, impressions, ctr, position, period_start, period_end
				FROM {$table}
				WHERE property = %s AND search_type = %s AND dimension = %s AND period_key = %s
				ORDER BY {$column} DESC
				LIMIT %d",
				$property,
				$search_type,
				$dimension,
				$period_key,
				max( 1, min( 25000, $limit ) )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function get_facts_map(
		string $property,
		string $search_type,
		string $dimension,
		string $period_key
	): array {
		$rows = $this->get_facts( $property, $search_type, $dimension, $period_key, 25000, 'clicks' );
		$map  = [];

		foreach ( $rows as $row ) {
			$map[ (string) $row['dim_key'] ] = $row;
		}

		return $map;
	}

	public function has_running_sync( string $property ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'forwp_gsc_sync_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table}
				WHERE property = %s AND status = 'running'
				LIMIT 1",
				$property
			)
		);

		return null !== $found && '' !== (string) $found;
	}

	public function expire_stale_running_syncs( string $property, int $max_age_seconds = 7200 ): void {
		global $wpdb;

		$table    = $wpdb->prefix . 'forwp_gsc_sync_log';
		$cutoff   = gmdate( 'Y-m-d H:i:s', time() - max( 300, $max_age_seconds ) );
		$message  = __( 'Marked as failed — sync timed out or was interrupted.', '4wp-seo-helper' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET status = 'error', message = %s, finished_at = %s
				WHERE property = %s AND status = 'running' AND started_at < %s",
				$message,
				gmdate( 'Y-m-d H:i:s' ),
				$property,
				$cutoff
			)
		);
	}

	public function has_fact_period( string $property, string $search_type, string $period_key ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'forwp_gsc_facts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table}
				WHERE property = %s AND search_type = %s AND period_key = %s
				LIMIT 1",
				$property,
				$search_type,
				$period_key
			)
		);

		return null !== $found && '' !== (string) $found;
	}

	public function log_sync_start( string $property, string $job_key ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'forwp_gsc_sync_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			[
				'property'   => $property,
				'job_key'    => $job_key,
				'status'     => 'running',
				'started_at' => gmdate( 'Y-m-d H:i:s' ),
			],
			[ '%s', '%s', '%s', '%s' ]
		);

		return (int) $wpdb->insert_id;
	}

	public function log_sync_finish( int $log_id, string $status, int $rows, string $message = '' ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'forwp_gsc_sync_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			[
				'status'      => $status,
				'rows_count'  => $rows,
				'message'     => $message,
				'finished_at' => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $log_id ],
			[ '%s', '%d', '%s', '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function get_recent_sync_logs( string $property, int $limit = 20 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'forwp_gsc_sync_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT job_key, status, message, rows_count, started_at, finished_at
				FROM {$table}
				WHERE property = %s
				ORDER BY id DESC
				LIMIT %d",
				$property,
				max( 1, min( 100, $limit ) )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	public function get_last_successful_sync( string $property ): ?string {
		global $wpdb;

		$table = $wpdb->prefix . 'forwp_gsc_sync_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT finished_at FROM {$table}
				WHERE property = %s AND status = 'ok' AND job_key IN ('full_sync', 'manual_sync')
				ORDER BY id DESC LIMIT 1",
				$property
			)
		);

		return is_string( $value ) && '' !== $value ? $value : null;
	}

	/**
	 * @return array{daily:int,facts:int,sync_log:int}
	 */
	public function get_storage_counts(): array {
		global $wpdb;

		if ( ! Schema::tables_exist() ) {
			return [
				'daily'       => 0,
				'facts'       => 0,
				'query_page'  => 0,
				'sync_log'    => 0,
			];
		}

		$daily      = $wpdb->prefix . 'forwp_gsc_daily';
		$facts      = $wpdb->prefix . 'forwp_gsc_facts';
		$query_page = $wpdb->prefix . 'forwp_gsc_query_page';
		$sync       = $wpdb->prefix . 'forwp_gsc_sync_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$daily_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$daily}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$facts_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$facts}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query_page_count = $this->query_page_table_exists()
			? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$query_page}" )
			: 0;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sync_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sync}" );

		return [
			'daily'      => $daily_count,
			'facts'      => $facts_count,
			'query_page' => $query_page_count,
			'sync_log'   => $sync_count,
		];
	}

	public function clear_all_data(): void {
		global $wpdb;

		if ( ! Schema::tables_exist() ) {
			return;
		}

		$tables = [
			$wpdb->prefix . 'forwp_gsc_daily',
			$wpdb->prefix . 'forwp_gsc_facts',
			$wpdb->prefix . 'forwp_gsc_query_page',
			$wpdb->prefix . 'forwp_gsc_sync_log',
		];

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "TRUNCATE TABLE {$table}" );
		}

		delete_option( 'forwp_seo_gsc_last_sync_at' );
	}
}
