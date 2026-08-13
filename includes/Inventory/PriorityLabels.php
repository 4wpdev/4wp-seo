<?php
/**
 * Configurable labels for inventory priority tiers (P1 / P2 / P3).
 */

namespace Forwp\SeoHelper\Inventory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PriorityLabels {
	public const OPTION_KEY = 'forwp_seo_helper_inventory_priority_labels';

	/**
	 * @return array{'1': string, '2': string, '3': string}
	 */
	public static function get_defaults(): array {
		return [
			'1' => __( 'Top priority', '4wp-seo-helper' ),
			'2' => __( 'Secondary', '4wp-seo-helper' ),
			'3' => __( 'Backlog', '4wp-seo-helper' ),
		];
	}

	/**
	 * @return array{'1': string, '2': string, '3': string}
	 */
	public static function get_all(): array {
		$saved = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}

		$defaults = self::get_defaults();
		$labels   = [];

		foreach ( PriorityQueue::LANE_IDS as $lane_id ) {
			$name = isset( $saved[ $lane_id ] ) ? sanitize_text_field( (string) $saved[ $lane_id ] ) : '';
			$labels[ $lane_id ] = '' !== $name ? $name : $defaults[ $lane_id ];
		}

		return $labels;
	}

	public static function get( int $priority ): string {
		$key = (string) $priority;
		$all = self::get_all();

		return $all[ $key ] ?? self::get_defaults()[ $key ] ?? '';
	}

	public static function get_formatted( int $priority ): string {
		return sprintf(
			/* translators: 1: priority tier number (1–3), 2: custom tier label */
			__( 'P%1$d — %2$s', '4wp-seo-helper' ),
			$priority,
			self::get( $priority )
		);
	}

	public static function get_badge( int $priority ): string {
		return 'P' . $priority;
	}

	/**
	 * @return array<int, string>
	 */
	public static function get_group_labels(): array {
		return [
			1 => self::get_formatted( 1 ),
			2 => self::get_formatted( 2 ),
			3 => self::get_formatted( 3 ),
			0 => __( 'Other', '4wp-seo-helper' ),
		];
	}

	/**
	 * @param array<string|int, mixed> $labels
	 */
	public static function save( array $labels ): void {
		$normalized = [];

		foreach ( PriorityQueue::LANE_IDS as $lane_id ) {
			if ( ! isset( $labels[ $lane_id ] ) ) {
				continue;
			}

			$name = sanitize_text_field( (string) $labels[ $lane_id ] );
			if ( '' !== $name ) {
				$normalized[ $lane_id ] = $name;
			}
		}

		update_option( self::OPTION_KEY, $normalized, false );
	}
}
