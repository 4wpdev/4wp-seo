<?php
/**
 * Join synced GSC page facts with inventory URLs.
 */

namespace Forwp\SeoHelper\Gsc;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PageMetrics {
	private Repository $repository;

	public function __construct( ?Repository $repository = null ) {
		$this->repository = $repository ?? new Repository();
	}

	/**
	 * @param list<array<string, mixed>> $records
	 * @return list<array<string, mixed>>
	 */
	public function enrich_records( array $records, string $property, ?int $days = null ): array {
		if ( '' === $property || empty( $records ) ) {
			return $records;
		}

		$days       = ReportPeriod::sanitize_days( $days ?? ReportPeriod::get_days() );
		$period_key = ReportPeriod::period_key_current( $days );
		$facts      = $this->repository->get_facts_map( $property, 'web', 'page', $period_key );
		$by_path    = [];

		foreach ( $facts as $dim_key => $row ) {
			$by_path[ PropertyResolver::url_path_key( (string) $dim_key ) ] = $row;
		}

		$query_rows   = $this->repository->get_query_page_rows( $property, 'web', $period_key );
		$queries_path = $this->index_queries_by_path( $query_rows );

		foreach ( $records as $index => $record ) {
			$path = PropertyResolver::url_path_key( (string) ( $record['url'] ?? '' ) );
			$row  = $by_path[ $path ] ?? null;

			$clicks      = (int) ( $row['clicks'] ?? 0 );
			$impressions = (int) ( $row['impressions'] ?? 0 );
			$position    = (float) ( $row['position'] ?? 0 );
			$ctr         = (float) ( $row['ctr'] ?? 0 );

			$records[ $index ]['gsc_clicks']       = $clicks;
			$records[ $index ]['gsc_impressions']  = $impressions;
			$records[ $index ]['gsc_position']     = $position;
			$records[ $index ]['gsc_ctr']          = $ctr;
			$records[ $index ]['gsc_top_queries']  = array_slice( $queries_path[ $path ] ?? [], 0, 3 );
		}

		return $records;
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @return array<string, list<array{query:string,clicks:int,impressions:int,position:float,ctr:float}>>
	 */
	private function index_queries_by_path( array $rows ): array {
		$indexed = [];

		foreach ( $rows as $row ) {
			$path = PropertyResolver::url_path_key( (string) ( $row['page_key'] ?? '' ) );
			if ( '' === $path ) {
				continue;
			}

			$indexed[ $path ][] = [
				'query'       => (string) ( $row['query_key'] ?? '' ),
				'clicks'      => (int) ( $row['clicks'] ?? 0 ),
				'impressions' => (int) ( $row['impressions'] ?? 0 ),
				'position'    => (float) ( $row['position'] ?? 0 ),
				'ctr'         => (float) ( $row['ctr'] ?? 0 ),
			];
		}

		foreach ( $indexed as $path => $queries ) {
			usort(
				$queries,
				static function ( array $left, array $right ): int {
					return ( $right['clicks'] ?? 0 ) <=> ( $left['clicks'] ?? 0 );
				}
			);
			$indexed[ $path ] = $queries;
		}

		return $indexed;
	}
}
