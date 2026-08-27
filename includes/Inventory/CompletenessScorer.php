<?php
/**
 * SEO field completeness scoring.
 */

namespace Forwp\SeoHelper\Inventory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CompletenessScorer {
	/**
	 * @param array<string, mixed> $meta
	 * @return array{score: int, missing: list<string>}
	 */
	public function score( array $meta ): array {
		$checks = [
			'title'         => ! empty( $meta['title'] ),
			'description'   => ! empty( $meta['description'] ),
			'focus_keyword' => ! empty( $meta['focus_keyphrases'] ) || ! empty( $meta['focus_keyword'] ),
			'og_image'      => ! empty( $meta['og_image'] ),
		];

		$missing = [];
		$filled  = 0;

		foreach ( $checks as $field => $is_filled ) {
			if ( $is_filled ) {
				++$filled;
			} else {
				$missing[] = $field;
			}
		}

		$total = count( $checks );
		$score = $total > 0 ? (int) round( ( $filled / $total ) * 100 ) : 0;

		return [
			'score'   => $score,
			'missing' => $missing,
		];
	}

	public function matches_missing_filter( array $missing, string $filter ): bool {
		if ( '' === $filter || 'any' === $filter ) {
			return ! empty( $missing );
		}

		if ( 'all' === $filter ) {
			return count( $missing ) >= 4;
		}

		return in_array( $filter, $missing, true );
	}
}
