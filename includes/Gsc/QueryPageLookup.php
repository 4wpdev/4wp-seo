<?php
/**
 * Read query ↔ page rows synced from GSC (query + page dimensions).
 */

namespace Forwp\SeoHelper\Gsc;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class QueryPageLookup {
	private Repository $repository;

	public function __construct( ?Repository $repository = null ) {
		$this->repository = $repository ?? new Repository();
	}

	/**
	 * Top landing page per query (by clicks).
	 *
	 * @param list<string> $queries
	 * @return array<string, array{page:string,clicks:int,impressions:int,position:float,ctr:float}>
	 */
	public function top_pages_for_queries( string $property, string $period_key, array $queries ): array {
		$rows = $this->repository->get_query_page_rows( $property, 'web', $period_key );
		if ( empty( $rows ) || empty( $queries ) ) {
			return [];
		}

		$wanted = array_fill_keys( array_map( 'strval', $queries ), true );
		$best   = [];

		foreach ( $rows as $row ) {
			$query = (string) ( $row['query_key'] ?? '' );
			if ( ! isset( $wanted[ $query ] ) ) {
				continue;
			}

			$clicks = (int) ( $row['clicks'] ?? 0 );
			if ( isset( $best[ $query ] ) && $clicks <= (int) $best[ $query ]['clicks'] ) {
				continue;
			}

			$best[ $query ] = [
				'page'        => (string) ( $row['page_key'] ?? '' ),
				'clicks'      => $clicks,
				'impressions' => (int) ( $row['impressions'] ?? 0 ),
				'position'    => (float) ( $row['position'] ?? 0 ),
				'ctr'         => (float) ( $row['ctr'] ?? 0 ),
			];
		}

		return $best;
	}

	/**
	 * Top queries for a page path (by clicks).
	 *
	 * @return list<array{query:string,clicks:int,impressions:int,position:float,ctr:float}>
	 */
	public function top_queries_for_page( string $property, string $period_key, string $page_url, int $limit = 3 ): array {
		$path  = PropertyResolver::url_path_key( $page_url );
		$rows  = $this->repository->get_query_page_rows( $property, 'web', $period_key );
		$match = [];

		foreach ( $rows as $row ) {
			if ( PropertyResolver::url_path_key( (string) ( $row['page_key'] ?? '' ) ) !== $path ) {
				continue;
			}

			$match[] = [
				'query'       => (string) ( $row['query_key'] ?? '' ),
				'clicks'      => (int) ( $row['clicks'] ?? 0 ),
				'impressions' => (int) ( $row['impressions'] ?? 0 ),
				'position'    => (float) ( $row['position'] ?? 0 ),
				'ctr'         => (float) ( $row['ctr'] ?? 0 ),
			];
		}

		usort(
			$match,
			static function ( array $left, array $right ): int {
				return ( $right['clicks'] ?? 0 ) <=> ( $left['clicks'] ?? 0 );
			}
		);

		return array_slice( $match, 0, max( 1, $limit ) );
	}
}
