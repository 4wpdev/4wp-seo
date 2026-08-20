<?php
/**
 * Derived SEO insights from stored GSC metrics.
 */

namespace Forwp\SeoHelper\Gsc;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Insights {
	private Repository $repository;

	public function __construct() {
		$this->repository = new Repository();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function build_overview( string $property, ?int $days = null ): array {
		$days = null === $days ? ReportPeriod::get_days() : ReportPeriod::sanitize_days( $days );
		[ $current_start, $current_end, $previous_start, $previous_end ] = ReportPeriod::ranges( $days );

		$current  = $this->repository->sum_daily_range( $property, 'web', $current_start, $current_end );
		$previous = $this->repository->sum_daily_range( $property, 'web', $previous_start, $previous_end );
		$daily    = $this->repository->get_daily_range( $property, 'web', $current_start, $current_end );

		return [
			'period'   => [
				'current_start'  => $current_start,
				'current_end'    => $current_end,
				'previous_start' => $previous_start,
				'previous_end'   => $previous_end,
				'days'           => $days,
				'label'          => ReportPeriod::label( $days ),
			],
			'current'  => $current,
			'previous' => $previous,
			'daily'    => $daily,
			'change'   => [
				'clicks'      => $this->delta( (int) $current['clicks'], (int) $previous['clicks'] ),
				'impressions' => $this->delta( (int) $current['impressions'], (int) $previous['impressions'] ),
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function build_cards( string $property, ?int $days = null ): array {
		$days       = null === $days ? ReportPeriod::get_days() : ReportPeriod::sanitize_days( $days );
		$period_key = ReportPeriod::period_key_current( $days );

		return [
			'top_pages'        => $this->repository->get_facts( $property, 'web', 'page', $period_key, 10 ),
			'trending_pages'   => $this->trending( $property, 'page', 'clicks', 10, $days ),
			'top_queries'      => $this->repository->get_facts( $property, 'web', 'query', $period_key, 10 ),
			'trending_queries' => $this->trending( $property, 'query', 'clicks', 10, $days ),
			'top_countries'    => $this->repository->get_facts( $property, 'web', 'country', $period_key, 10 ),
			'traffic_sources'  => $this->traffic_by_type( $property, $days ),
			'brand_split'      => $this->brand_split( $property, $days ),
			'period_label'     => ReportPeriod::label( $days ),
			'period_key'       => $period_key,
			'property'         => $property,
			'needs_sync'       => ! $this->repository->has_fact_period( $property, 'web', $period_key ),
		];
	}

	/**
	 * @return array{up:list<array<string,mixed>>,down:list<array<string,mixed>>}
	 */
	public function trending( string $property, string $dimension, string $metric = 'clicks', int $limit = 10, ?int $days = null ): array {
		$days     = null === $days ? ReportPeriod::get_days() : ReportPeriod::sanitize_days( $days );
		$current  = $this->repository->get_facts_map( $property, 'web', $dimension, ReportPeriod::period_key_current( $days ) );
		$previous = $this->repository->get_facts_map( $property, 'web', $dimension, ReportPeriod::period_key_previous( $days ) );
		$changes  = [];

		foreach ( $current as $key => $row ) {
			$cur_val  = (int) ( $row[ $metric ] ?? 0 );
			$prev_val = (int) ( $previous[ $key ][ $metric ] ?? 0 );
			if ( 0 === $cur_val && 0 === $prev_val ) {
				continue;
			}

			$changes[] = [
				'dim_key'     => $key,
				'current'     => $cur_val,
				'previous'    => $prev_val,
				'change'      => $cur_val - $prev_val,
				'change_pct'  => $this->percent_change( $cur_val, $prev_val ),
				'clicks'      => (int) ( $row['clicks'] ?? 0 ),
				'impressions' => (int) ( $row['impressions'] ?? 0 ),
				'position'    => (float) ( $row['position'] ?? 0 ),
			];
		}

		usort(
			$changes,
			static function ( array $a, array $b ): int {
				return ( $b['change'] ?? 0 ) <=> ( $a['change'] ?? 0 );
			}
		);

		$up = array_values( array_filter( $changes, static fn( array $row ): bool => ( $row['change'] ?? 0 ) > 0 ) );
		$down = $changes;
		usort(
			$down,
			static function ( array $a, array $b ): int {
				return ( $a['change'] ?? 0 ) <=> ( $b['change'] ?? 0 );
			}
		);
		$down = array_values( array_filter( $down, static fn( array $row ): bool => ( $row['change'] ?? 0 ) < 0 ) );

		return [
			'up'   => array_slice( $up, 0, $limit ),
			'down' => array_slice( $down, 0, $limit ),
		];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function traffic_by_type( string $property, ?int $days = null ): array {
		$days = null === $days ? ReportPeriod::get_days() : ReportPeriod::sanitize_days( $days );
		[ $current_start, $current_end ] = ReportPeriod::ranges( $days );
		$out = [];

		foreach ( Sync::search_types() as $type ) {
			$sum = $this->repository->sum_daily_range( $property, $type, $current_start, $current_end );
			if ( 0 === (int) $sum['clicks'] && 0 === (int) $sum['impressions'] ) {
				continue;
			}
			$out[] = array_merge( [ 'search_type' => $type ], $sum );
		}

		usort(
			$out,
			static function ( array $a, array $b ): int {
				return ( $b['clicks'] ?? 0 ) <=> ( $a['clicks'] ?? 0 );
			}
		);

		return $out;
	}

	/**
	 * @return array{branded:array<string,int>,non_branded:array<string,int>,terms:list<string>}
	 */
	public function brand_split( string $property, ?int $days = null ): array {
		$days  = null === $days ? ReportPeriod::get_days() : ReportPeriod::sanitize_days( $days );
		$terms = $this->get_brand_terms();
		$rows  = $this->repository->get_facts( $property, 'web', 'query', ReportPeriod::period_key_current( $days ), 25000 );

		$branded = [ 'clicks' => 0, 'impressions' => 0 ];
		$other   = [ 'clicks' => 0, 'impressions' => 0 ];

		foreach ( $rows as $row ) {
			$query = strtolower( (string) ( $row['dim_key'] ?? '' ) );
			$is_brand = false;
			foreach ( $terms as $term ) {
				if ( '' !== $term && str_contains( $query, $term ) ) {
					$is_brand = true;
					break;
				}
			}

			if ( $is_brand ) {
				$branded['clicks']      += (int) $row['clicks'];
				$branded['impressions'] += (int) $row['impressions'];
			} else {
				$other['clicks']      += (int) $row['clicks'];
				$other['impressions'] += (int) $row['impressions'];
			}
		}

		return [
			'branded'     => $branded,
			'non_branded' => $other,
			'terms'       => $terms,
		];
	}

	/**
	 * @return list<string>
	 */
	public function get_brand_terms(): array {
		$raw = (string) get_option( 'forwp_seo_gsc_brand_terms', '' );
		if ( '' === trim( $raw ) ) {
			$host = wp_parse_url( home_url(), PHP_URL_HOST );
			if ( is_string( $host ) && '' !== $host ) {
				$host = preg_replace( '/^www\./', '', $host );
				$parts = explode( '.', (string) $host );
				if ( ! empty( $parts[0] ) ) {
					return [ strtolower( $parts[0] ) ];
				}
			}
			return [];
		}

		$terms = preg_split( '/[\r\n,]+/', strtolower( $raw ) ) ?: [];

		return array_values(
			array_filter(
				array_map( 'trim', $terms ),
				static fn( string $term ): bool => '' !== $term
			)
		);
	}

	/**
	 * @return array{value:int,pct:float|null}
	 */
	private function delta( int $current, int $previous ): array {
		return [
			'value' => $current - $previous,
			'pct'   => $this->percent_change( $current, $previous ),
		];
	}

	private function percent_change( int $current, int $previous ): ?float {
		if ( 0 === $previous ) {
			return null;
		}

		return round( ( ( $current - $previous ) / $previous ) * 100, 1 );
	}
}
