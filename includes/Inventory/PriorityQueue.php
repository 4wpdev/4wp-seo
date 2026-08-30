<?php
/**
 * Site-wide SEO inventory priority lanes (P1 / P2 / P3).
 */

namespace Forwp\SeoHelper\Inventory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PriorityQueue {
	public const OPTION_KEY = 'forwp_seo_helper_inventory_priority_queue';

	/** @var list<string> */
	public const LANE_IDS = [ '1', '2', '3' ];

	/** @var array<int, array{priority: int, queue_position: int}>|null */
	private static ?array $slot_map = null;

	private Repository $repository;

	public function __construct( ?Repository $repository = null ) {
		$this->repository = $repository ?? new Repository();
	}

	public static function reset_cache(): void {
		self::$slot_map = null;
	}

	/**
	 * @return array{1: list<int>, 2: list<int>, 3: list<int>}
	 */
	public function get_lanes(): array {
		return $this->sanitize_lanes( $this->get_lanes_raw() );
	}

	/**
	 * @param array<string|int, list<int>> $lanes
	 * @return array{1: list<int>, 2: list<int>, 3: list<int>}
	 */
	public function set_lanes( array $lanes ): array {
		$normalized = [
			'1' => [],
			'2' => [],
			'3' => [],
		];

		foreach ( self::LANE_IDS as $lane_id ) {
			$ids = $lanes[ $lane_id ] ?? [];
			if ( ! is_array( $ids ) ) {
				continue;
			}

			foreach ( $ids as $id ) {
				$normalized[ $lane_id ][] = (int) $id;
			}
		}

		$normalized = $this->sanitize_lanes( $normalized );
		update_option( self::OPTION_KEY, $normalized, false );
		self::reset_cache();

		return $normalized;
	}

	/**
	 * Merge a paginated table snapshot into stored lanes without dropping off-page items.
	 *
	 * @param array<string|int, list<int|string>> $visible_lanes
	 * @param list<int|string>                    $visible_ids
	 * @return array{1: list<int>, 2: list<int>, 3: list<int>}
	 */
	public function merge_visible_lanes( array $visible_lanes, array $visible_ids ): array {
		$stored      = $this->get_lanes();
		$visible_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $visible_ids )
				)
			)
		);
		$visible_set = array_fill_keys( $visible_ids, true );

		$incoming_by_lane = [
			'1' => [],
			'2' => [],
			'3' => [],
		];
		$seen_incoming = [];

		foreach ( self::LANE_IDS as $lane_id ) {
			foreach ( (array) ( $visible_lanes[ $lane_id ] ?? [] ) as $id ) {
				$id = (int) $id;
				if ( $id <= 0 || ! isset( $visible_set[ $id ] ) || isset( $seen_incoming[ $id ] ) ) {
					continue;
				}
				$incoming_by_lane[ $lane_id ][] = $id;
				$seen_incoming[ $id ]           = true;
			}
		}

		$merged = [
			'1' => [],
			'2' => [],
			'3' => [],
		];

		foreach ( self::LANE_IDS as $lane_id ) {
			$stripped  = [];
			$insert_at = null;

			foreach ( $stored[ $lane_id ] as $id ) {
				if ( isset( $visible_set[ $id ] ) ) {
					if ( null === $insert_at ) {
						$insert_at = count( $stripped );
					}
					continue;
				}
				$stripped[] = $id;
			}

			$incoming = $incoming_by_lane[ $lane_id ];
			if ( [] === $incoming ) {
				$merged[ $lane_id ] = $stripped;
				continue;
			}

			if ( null === $insert_at ) {
				$insert_at = 0;
			}

			array_splice( $stripped, $insert_at, 0, $incoming );
			$merged[ $lane_id ] = $stripped;
		}

		return $this->set_lanes( $merged );
	}

	/**
	 * @param list<array<string, mixed>> $records
	 * @return list<array<string, mixed>>
	 */
	public function sort_records( array $records ): array {
		$slot_map = $this->get_slot_map();

		usort(
			$records,
			static function ( array $a, array $b ) use ( $slot_map ): int {
				$id_a   = (int) ( $a['post_id'] ?? 0 );
				$id_b   = (int) ( $b['post_id'] ?? 0 );
				$slot_a = $slot_map[ $id_a ] ?? null;
				$slot_b = $slot_map[ $id_b ] ?? null;

				if ( null !== $slot_a && null === $slot_b ) {
					return -1;
				}
				if ( null === $slot_a && null !== $slot_b ) {
					return 1;
				}
				if ( null !== $slot_a && null !== $slot_b ) {
					if ( $slot_a['priority'] !== $slot_b['priority'] ) {
						return $slot_a['priority'] <=> $slot_b['priority'];
					}

					return $slot_a['queue_position'] <=> $slot_b['queue_position'];
				}

				return strcmp( (string) ( $b['modified_gmt'] ?? '' ), (string) ( $a['modified_gmt'] ?? '' ) );
			}
		);

		return $records;
	}

	/**
	 * @param int      $post_id
	 * @param int|null $priority 1–3, or null to remove from queue.
	 */
	public function assign_post( int $post_id, ?int $priority ): array {
		$lanes = $this->get_lanes_raw();

		foreach ( self::LANE_IDS as $lane_id ) {
			$lanes[ $lane_id ] = array_values(
				array_filter(
					$lanes[ $lane_id ],
					static function ( int $id ) use ( $post_id ): bool {
						return $id !== $post_id;
					}
				)
			);
		}

		if ( null !== $priority && $priority >= 1 && $priority <= 3 ) {
			$lanes[ (string) $priority ][] = $post_id;
		}

		return $this->set_lanes( $lanes );
	}

	/**
	 * @return array{1: list<int>, 2: list<int>, 3: list<int>}
	 */
	public function get_lane_counts(): array {
		$lanes = $this->get_lanes();

		return [
			'1' => count( $lanes['1'] ),
			'2' => count( $lanes['2'] ),
			'3' => count( $lanes['3'] ),
		];
	}

	/**
	 * @return array{priority: int, queue_position: int}|null
	 */
	public function get_post_slot( int $post_id ): ?array {
		if ( $post_id <= 0 ) {
			return null;
		}

		$map = $this->get_slot_map();

		return $map[ $post_id ] ?? null;
	}

	/**
	 * @return array{
	 *     lanes: array{1: list<array<string, mixed>>, 2: list<array<string, mixed>>, 3: list<array<string, mixed>>}
	 * }
	 */
	public function get_lanes_with_items(): array {
		$lanes   = $this->get_lanes();
		$payload = [
			'lanes' => [
				'1' => [],
				'2' => [],
				'3' => [],
			],
		];

		foreach ( self::LANE_IDS as $lane_id ) {
			foreach ( $lanes[ $lane_id ] as $position => $post_id ) {
				$chip = $this->build_chip( $post_id, (int) $lane_id, (int) $position );
				if ( null !== $chip ) {
					$payload['lanes'][ $lane_id ][] = $chip;
				}
			}
		}

		return $payload;
	}

	/**
	 * @return array{1: list<int>, 2: list<int>, 3: list<int>}
	 */
	private function get_lanes_raw(): array {
		$raw = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $raw ) ) {
			$raw = [];
		}

		$lanes = [
			'1' => [],
			'2' => [],
			'3' => [],
		];

		foreach ( self::LANE_IDS as $lane_id ) {
			$ids = $raw[ $lane_id ] ?? [];
			if ( ! is_array( $ids ) ) {
				continue;
			}

			foreach ( $ids as $id ) {
				$post_id = (int) $id;
				if ( $post_id > 0 ) {
					$lanes[ $lane_id ][] = $post_id;
				}
			}
		}

		return $this->dedupe_lanes( $lanes );
	}

	/**
	 * @return array<int, array{priority: int, queue_position: int}>
	 */
	private function get_slot_map(): array {
		if ( null !== self::$slot_map ) {
			return self::$slot_map;
		}

		self::$slot_map = [];
		$lanes          = $this->dedupe_lanes( $this->get_lanes_raw() );

		foreach ( self::LANE_IDS as $lane_id ) {
			foreach ( $lanes[ $lane_id ] as $position => $post_id ) {
				self::$slot_map[ $post_id ] = [
					'priority'       => (int) $lane_id,
					'queue_position' => (int) $position,
				];
			}
		}

		return self::$slot_map;
	}

	/**
	 * @param array{1: list<int>, 2: list<int>, 3: list<int>} $lanes
	 * @return array{1: list<int>, 2: list<int>, 3: list<int>}
	 */
	private function dedupe_lanes( array $lanes ): array {
		$seen    = [];
		$cleaned = [
			'1' => [],
			'2' => [],
			'3' => [],
		];

		foreach ( self::LANE_IDS as $lane_id ) {
			foreach ( $lanes[ $lane_id ] as $post_id ) {
				$post_id = (int) $post_id;
				if ( $post_id <= 0 || isset( $seen[ $post_id ] ) ) {
					continue;
				}

				$cleaned[ $lane_id ][] = $post_id;
				$seen[ $post_id ]        = true;
			}
		}

		return $cleaned;
	}

	/**
	 * @param array{1: list<int>, 2: list<int>, 3: list<int>} $lanes
	 * @return array{1: list<int>, 2: list<int>, 3: list<int>}
	 */
	private function sanitize_lanes( array $lanes ): array {
		$deduped = $this->dedupe_lanes( $lanes );
		$cleaned = [
			'1' => [],
			'2' => [],
			'3' => [],
		];

		foreach ( self::LANE_IDS as $lane_id ) {
			foreach ( $deduped[ $lane_id ] as $post_id ) {
				if ( null === $this->repository->get_record( $post_id ) ) {
					continue;
				}

				$cleaned[ $lane_id ][] = $post_id;
			}
		}

		return $cleaned;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function build_chip( int $post_id, int $priority, int $position ): ?array {
		$record = $this->repository->get_record( $post_id );
		if ( null === $record ) {
			return null;
		}

		return [
			'post_id'        => $post_id,
			'wp_title'       => (string) $record['wp_title'],
			'post_type'      => (string) $record['post_type'],
			'completeness'   => (int) $record['completeness'],
			'priority'       => $priority,
			'queue_position' => $position,
			'url'            => (string) $record['url'],
		];
	}
}
