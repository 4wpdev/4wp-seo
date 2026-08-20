<?php
/**
 * GSC reporting date range (UI + sync windows).
 */

namespace Forwp\SeoHelper\Gsc;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReportPeriod {
	public const OPTION_DAYS = 'forwp_seo_gsc_report_days';

	public const DEFAULT_DAYS = 28;

	public const LAG_DAYS = 3;

	/**
	 * @return array<int, string>
	 */
	public static function allowed_ranges(): array {
		return [
			7  => __( 'Last 7 days', '4wp-seo-helper' ),
			28 => __( 'Last 28 days', '4wp-seo-helper' ),
			90 => __( 'Last 3 months', '4wp-seo-helper' ),
		];
	}

	public static function sanitize_days( int $days ): int {
		$allowed = self::allowed_ranges();

		return array_key_exists( $days, $allowed ) ? $days : self::DEFAULT_DAYS;
	}

	public static function get_days(): int {
		return self::sanitize_days( (int) get_option( self::OPTION_DAYS, (string) self::DEFAULT_DAYS ) );
	}

	public static function save_days( int $days ): void {
		update_option( self::OPTION_DAYS, (string) self::sanitize_days( $days ) );
	}

	public static function resolve_from_request( ?string $tab = null ): int {
		if ( null !== $tab && in_array( $tab, [ Admin::TAB_INSPECTION, Admin::TAB_SYNC ], true ) ) {
			return self::get_days();
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only range picker.
		if ( isset( $_GET['range'] ) ) {
			$days = self::sanitize_days( (int) wp_unslash( $_GET['range'] ) );
			if ( self::get_days() !== $days ) {
				self::save_days( $days );
			}

			return $days;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return self::get_days();
	}

	public static function period_key_current( ?int $days = null ): string {
		$days = null === $days ? self::get_days() : self::sanitize_days( $days );

		return 'current_' . $days . 'd';
	}

	public static function period_key_previous( ?int $days = null ): string {
		$days = null === $days ? self::get_days() : self::sanitize_days( $days );

		return 'previous_' . $days . 'd';
	}

	/**
	 * @return array{0:string,1:string,2:string,3:string}
	 */
	public static function ranges( ?int $days = null ): array {
		$days        = null === $days ? self::get_days() : self::sanitize_days( $days );
		$span        = max( 1, $days - 1 );
		$current_end = gmdate( 'Y-m-d', strtotime( '-' . self::LAG_DAYS . ' days' ) );
		$current_start = gmdate( 'Y-m-d', strtotime( $current_end . ' -' . $span . ' days' ) );
		$previous_end   = gmdate( 'Y-m-d', strtotime( $current_start . ' -1 day' ) );
		$previous_start = gmdate( 'Y-m-d', strtotime( $previous_end . ' -' . $span . ' days' ) );

		return [ $current_start, $current_end, $previous_start, $previous_end ];
	}

	public static function label( ?int $days = null ): string {
		$days    = null === $days ? self::get_days() : self::sanitize_days( $days );
		$allowed = self::allowed_ranges();

		return $allowed[ $days ] ?? (string) $days;
	}

	public static function max_days(): int {
		return max( array_keys( self::allowed_ranges() ) );
	}
}
