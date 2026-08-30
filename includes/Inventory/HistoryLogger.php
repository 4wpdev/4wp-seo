<?php
/**
 * Capture SEO checklist snapshots on real events only.
 */

namespace Forwp\SeoHelper\Inventory;

use Forwp\SeoHelper\Gsc\Admin as GscAdmin;
use Forwp\SeoHelper\Gsc\Indexing;
use Forwp\SeoHelper\Gsc\PageMetrics;
use Forwp\SeoHelper\SeoMeta\FocusKeyphrases;
use Forwp\SeoHelper\SeoMeta\Registry as SeoMetaRegistry;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HistoryLogger {
	public static function init(): void {
		add_action( 'wp_after_insert_post', [ self::class, 'on_save_post' ], 30, 2 );
	}

	/**
	 * @param int          $post_id
	 * @param WP_Post|null $post
	 */
	public static function on_save_post( $post_id, $post = null ): void {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! $post instanceof WP_Post ) {
			$post = get_post( $post_id );
		}

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( ! in_array( $post->post_status, [ 'publish', 'private' ], true ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, PostTypeDiscovery::get_slugs(), true ) ) {
			return;
		}

		self::record_content( $post );
		self::record_seo( $post_id );
	}

	public static function record_content( WP_Post $post ): void {
		History::record(
			(int) $post->ID,
			History::TYPE_CONTENT,
			[ 'chars' => self::character_count( $post ) ]
		);
	}

	public static function record_seo( int $post_id ): void {
		$adapter = SeoMetaRegistry::get_active();
		$meta    = $adapter->read( $post_id );
		$phrases = FocusKeyphrases::read_for_post( $post_id, (string) ( $meta['focus_keyword'] ?? '' ) );
		$scores  = $adapter->read_scores( $post_id );
		$scorer  = new CompletenessScorer();
		$meta['focus_keyphrases'] = $phrases;
		$complete = $scorer->score( $meta );

		History::record(
			$post_id,
			History::TYPE_SEO,
			[
				'score'    => null !== $scores['seo'] ? (int) $scores['seo'] : $complete['score'],
				'title'    => (string) ( $meta['title'] ?? '' ),
				'desc_len' => self::text_length( (string) ( $meta['description'] ?? '' ) ),
				'keys'     => $phrases,
				'og'       => '' !== (string) ( $meta['og_image'] ?? '' ) ? 1 : 0,
			]
		);
	}

	/**
	 * @param array<string, mixed> $inspect
	 */
	public static function on_inspect( int $post_id, array $inspect, bool $index_request = false ): void {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$snapshot = self::full_snapshot( $post, $inspect );

		if ( $index_request ) {
			History::record( $post_id, History::TYPE_INDEX_REQUEST, $snapshot, null, true );
		}

		$crawl = (string) ( $inspect['lastCrawl'] ?? '' );
		if ( '' !== $crawl ) {
			History::record( $post_id, History::TYPE_CRAWL, $snapshot, $crawl );
		}

		History::record( $post_id, History::TYPE_INSPECT, $snapshot );
	}

	public static function on_gsc_sync(): void {
		$property = GscAdmin::get_site_property();
		$ids      = History::tracked_post_ids();
		if ( '' === $property || [] === $ids ) {
			return;
		}

		$repository = new Repository();
		$records    = [];

		foreach ( $ids as $post_id ) {
			$record = $repository->get_record( $post_id );
			if ( is_array( $record ) ) {
				$records[] = $record;
			}
		}

		if ( [] === $records ) {
			return;
		}

		$records = ( new PageMetrics() )->enrich_records( $records, $property );

		foreach ( $records as $record ) {
			$clicks = (int) ( $record['gsc_clicks'] ?? 0 );
			$impr   = (int) ( $record['gsc_impressions'] ?? 0 );
			if ( $clicks <= 0 && $impr <= 0 ) {
				continue;
			}

			History::record(
				(int) $record['post_id'],
				History::TYPE_GSC,
				[
					'clicks' => $clicks,
					'impr'   => $impr,
					'pos'    => (float) ( $record['gsc_position'] ?? 0 ),
					'ctr'    => (float) ( $record['gsc_ctr'] ?? 0 ),
					'sugg'   => self::suggestions_from_record( $record ),
				]
			);
		}
	}

	/**
	 * @param array<string, mixed> $inspect
	 * @return array<string, mixed>
	 */
	private static function full_snapshot( WP_Post $post, array $inspect ): array {
		$adapter = SeoMetaRegistry::get_active();
		$meta    = $adapter->read( (int) $post->ID );
		$phrases = FocusKeyphrases::read_for_post( (int) $post->ID, (string) ( $meta['focus_keyword'] ?? '' ) );
		$scores  = $adapter->read_scores( (int) $post->ID );
		$scorer  = new CompletenessScorer();
		$meta['focus_keyphrases'] = $phrases;
		$complete = $scorer->score( $meta );

		$record = [
			'url'              => get_permalink( $post ) ?: '',
			'gsc_clicks'       => 0,
			'gsc_impressions'  => 0,
			'gsc_position'     => 0,
			'gsc_ctr'          => 0,
			'gsc_top_queries'  => [],
		];

		$property = GscAdmin::get_site_property();
		if ( '' !== $property ) {
			$enriched = ( new PageMetrics() )->enrich_records( [ $record ], $property );
			$record   = $enriched[0] ?? $record;
		}

		return [
			'chars'    => self::character_count( $post ),
			'score'    => null !== $scores['seo'] ? (int) $scores['seo'] : $complete['score'],
			'title'    => (string) ( $meta['title'] ?? '' ),
			'desc_len' => self::text_length( (string) ( $meta['description'] ?? '' ) ),
			'keys'     => $phrases,
			'sugg'     => self::suggestions_from_record( $record ),
			'clicks'   => (int) ( $record['gsc_clicks'] ?? 0 ),
			'impr'     => (int) ( $record['gsc_impressions'] ?? 0 ),
			'pos'      => (float) ( $record['gsc_position'] ?? 0 ),
			'ctr'      => (float) ( $record['gsc_ctr'] ?? 0 ),
			'cov'      => (string) ( $inspect['coverage'] ?? '' ),
			'crawl'    => (string) ( $inspect['lastCrawl'] ?? '' ),
			'og'       => '' !== (string) ( $meta['og_image'] ?? '' ) ? 1 : 0,
		];
	}

	/**
	 * @param array<string, mixed> $record
	 * @return list<array{q: string, c: int, i: int, p: float}>
	 */
	private static function suggestions_from_record( array $record ): array {
		$sugg = [];
		foreach ( (array) ( $record['gsc_top_queries'] ?? [] ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$query = (string) ( $row['query'] ?? '' );
			if ( '' === $query ) {
				continue;
			}
			$sugg[] = [
				'q' => $query,
				'c' => (int) ( $row['clicks'] ?? 0 ),
				'i' => (int) ( $row['impressions'] ?? 0 ),
				'p' => (float) ( $row['position'] ?? 0 ),
			];
		}

		return $sugg;
	}

	public static function seed_existing( WP_Post $post ): void {
		$post_id = (int) $post->ID;
		self::record_content( $post );
		self::record_seo( $post_id );

		$inspect = Indexing::inventory_fields( $post_id );
		$payload = [
			'coverage'  => (string) ( $inspect['gsc_coverage'] ?? '' ),
			'lastCrawl' => (string) ( $inspect['gsc_last_crawl'] ?? '' ),
		];

		if ( '' !== $payload['lastCrawl'] ) {
			History::record( $post_id, History::TYPE_CRAWL, self::full_snapshot( $post, $payload ), $payload['lastCrawl'] );
		}

		$requested = (int) ( $inspect['gsc_index_requested_at'] ?? 0 );
		if ( $requested > 0 ) {
			History::record(
				$post_id,
				History::TYPE_INDEX_REQUEST,
				self::full_snapshot( $post, $payload ),
				gmdate( 'Y-m-d H:i:s', $requested ),
				true
			);
		}
	}

	public static function character_count( WP_Post $post ): int {
		$text = wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
		$text = preg_replace( '/\s+/u', ' ', $text ) ?? $text;
		$text = trim( $text );

		return self::text_length( $text );
	}

	private static function text_length( string $text ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
	}
}
