<?php
/**
 * Pull Search Console metrics into local tables.
 */

namespace Forwp\SeoHelper\Gsc;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Sync {
	public const CRON_HOOK        = 'forwp_seo_gsc_daily_sync';
	public const MANUAL_SYNC_HOOK = 'forwp_seo_gsc_manual_sync';

	public const JOB_FULL_SYNC   = 'full_sync';
	public const JOB_MANUAL_SYNC = 'manual_sync';

	/**
	 * @return list<string>
	 */
	public static function search_types(): array {
		return [ 'web', 'image', 'video', 'news', 'discover' ];
	}

	/**
	 * @return list<string>
	 */
	public static function fact_dimensions(): array {
		return [ 'page', 'query', 'country', 'device', 'searchAppearance' ];
	}

	private static $instance = null;

	private Repository $repository;

	private function __construct() {
		$this->repository = new Repository();
		add_action( self::CRON_HOOK, [ $this, 'run_scheduled_sync' ] );
		add_action( self::MANUAL_SYNC_HOOK, [ $this, 'run_manual_sync' ], 10, 1 );
	}

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function schedule_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function unschedule_cron(): void {
		while ( false !== ( $timestamp = wp_next_scheduled( self::CRON_HOOK ) ) ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	public static function unschedule_manual_syncs(): void {
		while ( false !== ( $timestamp = wp_next_scheduled( self::MANUAL_SYNC_HOOK ) ) ) {
			wp_unschedule_event( $timestamp, self::MANUAL_SYNC_HOOK );
		}
	}

	public static function sync_cron_state( bool $enabled ): void {
		if ( $enabled ) {
			self::schedule_cron();
			return;
		}

		self::unschedule_cron();
	}

	public function run_scheduled_sync(): void {
		if ( ! Module::get_instance()->is_enabled() || ! Module::get_instance()->is_cron_enabled() ) {
			return;
		}

		$admin = Admin::get_instance();
		if ( ! $admin->is_connected() || ! $admin->has_property() ) {
			return;
		}

		$this->run_full_sync( Admin::get_site_property(), self::JOB_FULL_SYNC );
	}

	/**
	 * Queue a background manual sync (non-blocking for the browser).
	 *
	 * @return array{ok:bool,message:string}
	 */
	public function queue_manual_sync( string $property ): array {
		if ( '' === $property ) {
			return [
				'ok'      => false,
				'message' => __( 'No Search Console property selected.', '4wp-seo-helper' ),
			];
		}

		$admin = Admin::get_instance();
		if ( ! $admin->is_connected() || ! $admin->has_property() ) {
			return [
				'ok'      => false,
				'message' => __( 'Not connected to Google Search Console.', '4wp-seo-helper' ),
			];
		}

		$this->repository->expire_stale_running_syncs( $property );

		if ( $this->is_sync_in_progress( $property ) ) {
			return [
				'ok'      => false,
				'message' => __( 'A sync is already running or queued.', '4wp-seo-helper' ),
			];
		}

		wp_schedule_single_event( time(), self::MANUAL_SYNC_HOOK, [ $property ] );
		set_transient( $this->lock_key( $property ), '1', 2 * HOUR_IN_SECONDS );

		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}

		return [
			'ok'      => true,
			'message' => __( 'Sync started in the background. You can leave this page.', '4wp-seo-helper' ),
		];
	}

	public function run_manual_sync( string $property ): void {
		if ( '' === $property || $property !== Admin::get_site_property() ) {
			delete_transient( $this->lock_key( $property ) );
			return;
		}

		$this->run_full_sync( $property, self::JOB_MANUAL_SYNC );
		delete_transient( $this->lock_key( $property ) );
	}

	public function is_sync_in_progress( string $property ): bool {
		if ( '' === $property ) {
			return false;
		}

		if ( get_transient( $this->lock_key( $property ) ) ) {
			return true;
		}

		if ( false !== wp_next_scheduled( self::MANUAL_SYNC_HOOK, [ $property ] ) ) {
			return true;
		}

		return $this->repository->has_running_sync( $property );
	}

	public function release_sync_lock( string $property ): void {
		if ( '' === $property ) {
			return;
		}

		delete_transient( $this->lock_key( $property ) );
	}

	private function lock_key( string $property ): string {
		return 'forwp_seo_gsc_sync_lock_' . md5( $property );
	}

	/**
	 * @return array{ok:bool,message:string,rows:int}
	 */
	public function run_full_sync( string $property, string $job_key = self::JOB_FULL_SYNC ): array {
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 );
		}

		if ( '' === $property ) {
			return [
				'ok'      => false,
				'message' => __( 'No Search Console property selected.', '4wp-seo-helper' ),
				'rows'    => 0,
			];
		}

		if ( ! Schema::tables_exist() ) {
			Schema::install();
		}

		$admin = Admin::get_instance();
		$token = $admin->get_access_token_for_sync();
		if ( '' === $token ) {
			return [
				'ok'      => false,
				'message' => __( 'Not connected to Google Search Console.', '4wp-seo-helper' ),
				'rows'    => 0,
			];
		}

		$this->repository->expire_stale_running_syncs( $property );

		if ( $this->repository->has_running_sync( $property ) ) {
			return [
				'ok'      => false,
				'message' => __( 'Another sync is already in progress.', '4wp-seo-helper' ),
				'rows'    => 0,
			];
		}

		$log_id     = $this->repository->log_sync_start( $property, $job_key );
		$total_rows = 0;
		$errors     = [];

		$max_ranges  = ReportPeriod::ranges( ReportPeriod::max_days() );
		$daily_start = $max_ranges[2];
		$daily_end   = $max_ranges[1];

		foreach ( self::search_types() as $search_type ) {
			$daily = $this->sync_daily( $token, $property, $search_type, $daily_start, $daily_end );
			if ( isset( $daily['error'] ) ) {
				$errors[] = $search_type . ' daily: ' . $daily['error'];
			} else {
				$total_rows += (int) $daily['rows'];
			}

			if ( 'web' !== $search_type ) {
				continue;
			}

			foreach ( array_keys( ReportPeriod::allowed_ranges() ) as $period_days ) {
				[ $current_start, $current_end, $previous_start, $previous_end ] = ReportPeriod::ranges( (int) $period_days );

				foreach ( self::fact_dimensions() as $dimension ) {
					$current = $this->sync_facts(
						$token,
						$property,
						$search_type,
						$dimension,
						ReportPeriod::period_key_current( (int) $period_days ),
						$current_start,
						$current_end
					);
					if ( isset( $current['error'] ) ) {
						$errors[] = $period_days . 'd ' . $dimension . ' current: ' . $current['error'];
					} else {
						$total_rows += (int) $current['rows'];
					}

					$previous = $this->sync_facts(
						$token,
						$property,
						$search_type,
						$dimension,
						ReportPeriod::period_key_previous( (int) $period_days ),
						$previous_start,
						$previous_end
					);
					if ( isset( $previous['error'] ) ) {
						$errors[] = $period_days . 'd ' . $dimension . ' previous: ' . $previous['error'];
					} else {
						$total_rows += (int) $previous['rows'];
					}
				}

				$current_qp = $this->sync_query_page(
					$token,
					$property,
					$search_type,
					ReportPeriod::period_key_current( (int) $period_days ),
					$current_start,
					$current_end
				);
				if ( isset( $current_qp['error'] ) ) {
					$errors[] = $period_days . 'd query_page current: ' . $current_qp['error'];
				} else {
					$total_rows += (int) $current_qp['rows'];
				}

				$previous_qp = $this->sync_query_page(
					$token,
					$property,
					$search_type,
					ReportPeriod::period_key_previous( (int) $period_days ),
					$previous_start,
					$previous_end
				);
				if ( isset( $previous_qp['error'] ) ) {
					$errors[] = $period_days . 'd query_page previous: ' . $previous_qp['error'];
				} else {
					$total_rows += (int) $previous_qp['rows'];
				}
			}
		}

		$ok      = empty( $errors );
		$message = $ok
			? __( 'Sync completed.', '4wp-seo-helper' )
			: implode( '; ', array_slice( $errors, 0, 5 ) );

		$this->repository->log_sync_finish( $log_id, $ok ? 'ok' : 'error', $total_rows, $message );

		update_option( 'forwp_seo_gsc_last_sync_at', gmdate( 'Y-m-d H:i:s' ) );

		return [
			'ok'      => $ok,
			'message' => $message,
			'rows'    => $total_rows,
		];
	}

	/**
	 * @return array{rows?:int,error?:string}
	 */
	private function sync_daily(
		string $token,
		string $property,
		string $search_type,
		string $start,
		string $end
	): array {
		$client   = new Client();
		$response = $client->search_analytics_fetch_all(
			$token,
			$property,
			[
				'startDate'  => $start,
				'endDate'    => $end,
				'dimensions' => [ 'date' ],
				'type'       => $search_type,
			]
		);

		if ( isset( $response['error'] ) ) {
			return [ 'error' => $response['error'] ];
		}

		$normalized = [];
		foreach ( $response['rows'] as $row ) {
			$keys = $row['keys'] ?? [];
			if ( ! is_array( $keys ) || empty( $keys[0] ) ) {
				continue;
			}
			$normalized[] = [
				'metric_date' => (string) $keys[0],
				'clicks'      => (int) ( $row['clicks'] ?? 0 ),
				'impressions' => (int) ( $row['impressions'] ?? 0 ),
				'ctr'         => (float) ( $row['ctr'] ?? 0 ),
				'position'    => (float) ( $row['position'] ?? 0 ),
			];
		}

		$count = $this->repository->replace_daily_rows( $property, $search_type, $normalized );

		return [ 'rows' => $count ];
	}

	/**
	 * @return array{rows?:int,error?:string}
	 */
	private function sync_facts(
		string $token,
		string $property,
		string $search_type,
		string $dimension,
		string $period_key,
		string $start,
		string $end
	): array {
		$client   = new Client();
		$response = $client->search_analytics_fetch_all(
			$token,
			$property,
			[
				'startDate'  => $start,
				'endDate'    => $end,
				'dimensions' => [ $dimension ],
				'type'       => $search_type,
			]
		);

		if ( isset( $response['error'] ) ) {
			return [ 'error' => $response['error'] ];
		}

		$normalized = [];
		foreach ( $response['rows'] as $row ) {
			$keys = $row['keys'] ?? [];
			if ( ! is_array( $keys ) || ! isset( $keys[0] ) ) {
				continue;
			}
			$normalized[] = [
				'dim_key'     => (string) $keys[0],
				'clicks'      => (int) ( $row['clicks'] ?? 0 ),
				'impressions' => (int) ( $row['impressions'] ?? 0 ),
				'ctr'         => (float) ( $row['ctr'] ?? 0 ),
				'position'    => (float) ( $row['position'] ?? 0 ),
			];
		}

		$count = $this->repository->replace_fact_rows(
			$property,
			$search_type,
			$dimension,
			$period_key,
			$start,
			$end,
			$normalized
		);

		return [ 'rows' => $count ];
	}

	/**
	 * @return array{rows?:int,error?:string}
	 */
	private function sync_query_page(
		string $token,
		string $property,
		string $search_type,
		string $period_key,
		string $start,
		string $end
	): array {
		$client   = new Client();
		$response = $client->search_analytics_fetch_all(
			$token,
			$property,
			[
				'startDate'  => $start,
				'endDate'    => $end,
				'dimensions' => [ 'query', 'page' ],
				'type'       => $search_type,
			]
		);

		if ( isset( $response['error'] ) ) {
			return [ 'error' => $response['error'] ];
		}

		$normalized = [];
		foreach ( $response['rows'] as $row ) {
			$keys = $row['keys'] ?? [];
			if ( ! is_array( $keys ) || ! isset( $keys[0], $keys[1] ) ) {
				continue;
			}

			$normalized[] = [
				'query_key'   => (string) $keys[0],
				'page_key'    => (string) $keys[1],
				'clicks'      => (int) ( $row['clicks'] ?? 0 ),
				'impressions' => (int) ( $row['impressions'] ?? 0 ),
				'ctr'         => (float) ( $row['ctr'] ?? 0 ),
				'position'    => (float) ( $row['position'] ?? 0 ),
			];
		}

		$count = $this->repository->replace_query_page_rows(
			$property,
			$search_type,
			$period_key,
			$start,
			$end,
			$normalized
		);

		return [ 'rows' => $count ];
	}

	/**
	 * @return array{0:string,1:string,2:string,3:string}
	 */
	public function period_ranges(): array {
		return ReportPeriod::ranges();
	}
}
