<?php
/**
 * SEO inventory query and record assembly.
 */

namespace Forwp\SeoHelper\Inventory;

use Forwp\SeoHelper\Gsc\Indexing;
use Forwp\SeoHelper\Multilingual\Registry as MultilingualRegistry;
use Forwp\SeoHelper\SeoMeta\FocusKeyphrases;
use Forwp\SeoHelper\SeoMeta\Registry as SeoMetaRegistry;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Repository {
	private CompletenessScorer $scorer;

	public function __construct( ?CompletenessScorer $scorer = null ) {
		$this->scorer = $scorer ?? new CompletenessScorer();
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int}
	 */
	public function query( array $args = [] ): array {
		$page      = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page  = min( 999, max( 1, (int) ( $args['per_page'] ?? 50 ) ) );
		$post_type = sanitize_key( (string) ( $args['post_type'] ?? '' ) );
		$lang      = sanitize_key( (string) ( $args['lang'] ?? '' ) );
		$status    = sanitize_key( (string) ( $args['status'] ?? 'publish' ) );
		$missing   = sanitize_key( (string) ( $args['missing'] ?? '' ) );
		$search    = sanitize_text_field( (string) ( $args['search'] ?? '' ) );
		$min_score = isset( $args['min_score'] ) && '' !== (string) $args['min_score'] ? (int) $args['min_score'] : null;
		$max_score = isset( $args['max_score'] ) && '' !== (string) $args['max_score'] ? (int) $args['max_score'] : null;
		$sort_by_priority = ! empty( $args['sort_by_priority'] );

		$has_post_filters = '' !== $missing || null !== $min_score || null !== $max_score || $sort_by_priority;

		$query_args = [
			'post_type'              => $this->resolve_post_types( $post_type ),
			'post_status'            => $status ?: 'publish',
			'posts_per_page'         => $has_post_filters ? -1 : $per_page,
			'paged'                  => $has_post_filters ? 1 : $page,
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => $has_post_filters,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];

		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}

		$this->prepare_language_query( $query_args, $lang );

		$query   = $this->run_query( $query_args, $lang );
		$records = [];

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$record = $this->build_record( $post );
			if ( null === $record ) {
				continue;
			}

			if ( '' !== $missing && ! $this->scorer->matches_missing_filter( $record['missing'], $missing ) ) {
				continue;
			}

			if ( null !== $min_score && $record['completeness'] < $min_score ) {
				continue;
			}

			if ( null !== $max_score && $record['completeness'] > $max_score ) {
				continue;
			}

			$records[] = $record;
		}

		if ( $sort_by_priority ) {
			$records = ( new PriorityQueue() )->sort_records( $records );
		}

		if ( $has_post_filters ) {
			$total = count( $records );
			$offset = ( $page - 1 ) * $per_page;
			$items  = array_slice( $records, $offset, $per_page );
		} else {
			$total = (int) $query->found_posts;
			$items = $records;
		}

		return [
			'items'       => $items,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_record( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		return $this->build_record( $post );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_stats( array $args = [] ): array {
		$post_type = sanitize_key( (string) ( $args['post_type'] ?? '' ) );
		$status    = sanitize_key( (string) ( $args['status'] ?? 'publish' ) );

		$query_args = [
			'post_type'              => $this->resolve_post_types( $post_type ),
			'post_status'            => $status ?: 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];

		$ids = get_posts( $query_args );

		$totals = [
			'posts'             => 0,
			'avg_completeness'  => 0,
			'by_language'       => [],
			'by_post_type'      => [],
			'missing_counts'    => [
				'title'         => 0,
				'description'   => 0,
				'focus_keyword' => 0,
				'og_image'      => 0,
			],
		];

		$score_sum = 0;

		foreach ( $ids as $post_id ) {
			$post = get_post( (int) $post_id );
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$record = $this->build_record( $post );
			if ( null === $record ) {
				continue;
			}

			++$totals['posts'];
			$score_sum += (int) $record['completeness'];

			$lang = (string) $record['lang'];
			if ( ! isset( $totals['by_language'][ $lang ] ) ) {
				$totals['by_language'][ $lang ] = [
					'count'            => 0,
					'avg_completeness' => 0,
					'score_sum'        => 0,
				];
			}
			++$totals['by_language'][ $lang ]['count'];
			$totals['by_language'][ $lang ]['score_sum'] += (int) $record['completeness'];

			$type = (string) $record['post_type'];
			if ( ! isset( $totals['by_post_type'][ $type ] ) ) {
				$totals['by_post_type'][ $type ] = [
					'count'            => 0,
					'avg_completeness' => 0,
					'score_sum'        => 0,
				];
			}
			++$totals['by_post_type'][ $type ]['count'];
			$totals['by_post_type'][ $type ]['score_sum'] += (int) $record['completeness'];

			foreach ( $record['missing'] as $field ) {
				if ( isset( $totals['missing_counts'][ $field ] ) ) {
					++$totals['missing_counts'][ $field ];
				}
			}
		}

		if ( $totals['posts'] > 0 ) {
			$totals['avg_completeness'] = (int) round( $score_sum / $totals['posts'] );
		}

		foreach ( $totals['by_language'] as $lang => $data ) {
			if ( $data['count'] > 0 ) {
				$totals['by_language'][ $lang ]['avg_completeness'] = (int) round( $data['score_sum'] / $data['count'] );
			}
			unset( $totals['by_language'][ $lang ]['score_sum'] );
		}

		foreach ( $totals['by_post_type'] as $type => $data ) {
			if ( $data['count'] > 0 ) {
				$totals['by_post_type'][ $type ]['avg_completeness'] = (int) round( $data['score_sum'] / $data['count'] );
			}
			unset( $totals['by_post_type'][ $type ]['score_sum'] );
		}

		return $totals;
	}

	/**
	 * All public CPTs with admin UI — discovered from WordPress, not hardcoded.
	 *
	 * @return list<string>
	 */
	public function get_supported_post_types(): array {
		return PostTypeDiscovery::get_slugs();
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function build_record( WP_Post $post ): ?array {
		if ( ! in_array( $post->post_type, $this->get_supported_post_types(), true ) ) {
			return null;
		}

		$adapter  = SeoMetaRegistry::get_active();
		$provider = MultilingualRegistry::get_active();
		$meta     = $adapter->read( $post->ID );
		$phrases  = FocusKeyphrases::read_for_post( $post->ID, (string) ( $meta['focus_keyword'] ?? '' ) );
		$meta['focus_keyphrases'] = $phrases;
		$meta['focus_keyword']    = FocusKeyphrases::primary( $phrases );

		if ( '' === $meta['og_image'] && has_post_thumbnail( $post ) ) {
			$thumb = wp_get_attachment_image_url( get_post_thumbnail_id( $post ), 'full' );
			if ( is_string( $thumb ) ) {
				$meta['og_image'] = $thumb;
			}
		}

		$completeness = $this->scorer->score( $meta );
		$seo_scores   = $adapter->read_scores( $post->ID );
		$group        = $provider->get_translation_group( $post->ID );
		$group_ids    = array_map(
			static function ( array $item ): int {
				return (int) $item['post_id'];
			},
			$group
		);

		$slot = ( new PriorityQueue() )->get_post_slot( $post->ID );

		$record = [
			'post_id'            => $post->ID,
			'lang'               => $provider->get_post_language( $post->ID ),
			'post_type'          => $post->post_type,
			'status'             => $post->post_status,
			'wp_title'           => get_the_title( $post ),
			'url'                => get_permalink( $post ) ?: '',
			'modified_gmt'       => $post->post_modified_gmt,
			'seo_title'          => $meta['title'],
			'meta_description'   => $meta['description'],
			'focus_keyword'      => $meta['focus_keyword'],
			'focus_keyphrases'   => $phrases,
			'focus_keyphrases_text' => FocusKeyphrases::format( $phrases ),
			'canonical'          => $meta['canonical'],
			'noindex'            => (bool) $meta['noindex'],
			'og_title'           => $meta['og_title'],
			'og_description'     => $meta['og_description'],
			'og_image'           => $meta['og_image'],
			'completeness'       => null !== $seo_scores['seo'] ? (int) $seo_scores['seo'] : $completeness['score'],
			'seo_score'          => $seo_scores['seo'],
			'seo_score_label'    => (string) $seo_scores['label'],
			'readability_score'  => $seo_scores['readability'],
			'seo_no_focus'       => (bool) $seo_scores['no_focus'],
			'missing'            => $completeness['missing'],
			'translation_group'  => $group_ids,
			'seo_adapter'        => $adapter->get_id(),
			'multilingual'       => $provider->get_id(),
			'priority'           => $slot['priority'] ?? null,
			'queue_position'     => isset( $slot['queue_position'] ) ? (int) $slot['queue_position'] : null,
		];

		return array_merge( $record, Indexing::inventory_fields( $post->ID ) );
	}

	/**
	 * @return list<string>
	 */
	private function resolve_post_types( string $post_type ): array {
		$supported = $this->get_supported_post_types();

		if ( '' !== $post_type && in_array( $post_type, $supported, true ) ) {
			return [ $post_type ];
		}

		return $supported;
	}

	/**
	 * @param array<string, mixed> $query_args
	 */
	private function prepare_language_query( array &$query_args, string $lang ): void {
		if ( '' === $lang ) {
			return;
		}

		$provider = MultilingualRegistry::get_active();

		if ( 'polylang' === $provider->get_id() ) {
			$query_args['lang'] = $lang;
		}

		if ( 'wpml' === $provider->get_id() ) {
			$query_args['suppress_filters'] = false;
		}
	}

	/**
	 * @param array<string, mixed> $query_args
	 */
	private function run_query( array $query_args, string $lang ): \WP_Query {
		$provider = MultilingualRegistry::get_active();
		$previous = null;

		if ( '' !== $lang && 'wpml' === $provider->get_id() && has_action( 'wpml_switch_language' ) ) {
			// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML third-party hooks.
			$previous = apply_filters( 'wpml_current_language', null );
			do_action( 'wpml_switch_language', $lang );
			// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		}

		$query = new \WP_Query( $query_args );

		if ( is_string( $previous ) && '' !== $previous && has_action( 'wpml_switch_language' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML third-party hook.
			do_action( 'wpml_switch_language', $previous );
		}

		return $query;
	}
}
