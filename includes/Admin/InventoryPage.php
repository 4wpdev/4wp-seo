<?php
/**
 * Admin SEO inventory page.
 */

namespace Forwp\SeoHelper\Admin;

use Forwp\SeoHelper\Inventory\PostTypeDiscovery;
use Forwp\SeoHelper\Inventory\Repository;
use Forwp\SeoHelper\Multilingual\Registry as MultilingualRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class InventoryPage {
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$repository = new Repository();
		$provider   = MultilingualRegistry::get_active();
		$languages  = $provider->get_languages();
		$show_lang  = count( $languages ) > 1;
		$filters    = [
			'page'      => isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1,
			'post_type' => sanitize_key( (string) ( $_GET['post_type'] ?? '' ) ),
			'lang'      => sanitize_key( (string) ( $_GET['lang'] ?? '' ) ),
			'status'    => sanitize_key( (string) ( $_GET['status'] ?? 'publish' ) ),
			'missing'   => sanitize_key( (string) ( $_GET['missing'] ?? '' ) ),
			'search'    => sanitize_text_field( (string) ( $_GET['s'] ?? '' ) ),
		];

		$stats = $repository->get_stats(
			[
				'post_type' => $filters['post_type'],
				'status'    => $filters['status'],
			]
		);

		$table = new InventoryListTable( $repository );
		$table->set_show_language( $show_lang );
		$table->set_filters( $filters );
		$table->prepare_items();

		$export_url = wp_nonce_url(
			add_query_arg(
				array_filter(
					[
						'action'    => 'forwp_seo_inventory_export',
						'post_type' => $filters['post_type'],
						'lang'      => $filters['lang'],
						'status'    => $filters['status'],
						'missing'   => $filters['missing'],
						's'         => $filters['search'],
					]
				),
				admin_url( 'admin-post.php' )
			),
			'forwp_seo_inventory_export'
		);

		?>
		<div class="wrap forwp-seo-inventory">
			<h1><?php esc_html_e( 'SEO Inventory', '4wp-seo' ); ?></h1>
			<p>
				<?php esc_html_e( 'Translation-aware SEO meta overview. Same data as REST API for analytics dashboard and Google Sheets.', '4wp-seo' ); ?>
				<a class="page-title-action" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', '4wp-seo' ); ?></a>
			</p>

			<div class="forwp-seo-inventory__stats">
				<p>
					<strong><?php esc_html_e( 'Posts:', '4wp-seo' ); ?></strong>
					<?php echo esc_html( (string) $stats['posts'] ); ?>
					&nbsp;|&nbsp;
					<strong><?php esc_html_e( 'Avg completeness:', '4wp-seo' ); ?></strong>
					<?php echo esc_html( (string) $stats['avg_completeness'] ); ?>%
				</p>
				<?php if ( $show_lang && ! empty( $stats['by_language'] ) ) : ?>
					<p><strong><?php esc_html_e( 'By language:', '4wp-seo' ); ?></strong>
					<?php
					$parts = [];
					foreach ( $stats['by_language'] as $code => $data ) {
						$parts[] = $code . ': ' . (int) $data['count'] . ' (' . (int) $data['avg_completeness'] . '%)';
					}
					echo esc_html( implode( ' · ', $parts ) );
					?>
					</p>
				<?php endif; ?>
				<?php if ( ! empty( $stats['missing_counts'] ) ) : ?>
					<p><strong><?php esc_html_e( 'Missing fields:', '4wp-seo' ); ?></strong>
					<?php
					$missing_parts = [];
					foreach ( $stats['missing_counts'] as $field => $count ) {
						$missing_parts[] = sprintf( '%s: %d', $field, (int) $count );
					}
					echo esc_html( implode( ' · ', $missing_parts ) );
					?>
					</p>
				<?php endif; ?>
			</div>

			<form method="get">
				<input type="hidden" name="page" value="4wp-seo-inventory" />
				<div class="tablenav top forwp-seo-inventory__toolbar">
					<div class="alignleft actions">
						<select name="post_type">
							<option value=""><?php esc_html_e( 'All post types', '4wp-seo' ); ?></option>
							<?php foreach ( PostTypeDiscovery::get_labeled() as $type ) : ?>
								<option value="<?php echo esc_attr( $type['slug'] ); ?>" <?php selected( $filters['post_type'], $type['slug'] ); ?>>
									<?php echo esc_html( $type['label'] . ' (' . $type['slug'] . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php if ( $show_lang ) : ?>
						<select name="lang">
							<option value=""><?php esc_html_e( 'All languages', '4wp-seo' ); ?></option>
							<?php foreach ( $languages as $language ) : ?>
								<option value="<?php echo esc_attr( $language['code'] ); ?>" <?php selected( $filters['lang'], $language['code'] ); ?>>
									<?php echo esc_html( $language['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php endif; ?>
						<select name="missing">
							<option value=""><?php esc_html_e( 'Any completeness', '4wp-seo' ); ?></option>
							<option value="any" <?php selected( $filters['missing'], 'any' ); ?>><?php esc_html_e( 'Has missing fields', '4wp-seo' ); ?></option>
							<option value="title" <?php selected( $filters['missing'], 'title' ); ?>><?php esc_html_e( 'Missing title', '4wp-seo' ); ?></option>
							<option value="description" <?php selected( $filters['missing'], 'description' ); ?>><?php esc_html_e( 'Missing description', '4wp-seo' ); ?></option>
							<option value="focus_keyword" <?php selected( $filters['missing'], 'focus_keyword' ); ?>><?php esc_html_e( 'Missing focus keyword', '4wp-seo' ); ?></option>
							<option value="og_image" <?php selected( $filters['missing'], 'og_image' ); ?>><?php esc_html_e( 'Missing OG image', '4wp-seo' ); ?></option>
						</select>
						<?php submit_button( __( 'Filter', '4wp-seo' ), '', 'forwp_seo_inventory_filter', false ); ?>
					</div>
					<p class="search-box">
						<label class="screen-reader-text" for="seo-inventory-search"><?php esc_html_e( 'Search', '4wp-seo' ); ?></label>
						<input type="search" id="seo-inventory-search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" />
						<?php submit_button( __( 'Search', '4wp-seo' ), '', '', false ); ?>
					</p>
				</div>
				<?php $table->display(); ?>
			</form>
		</div>
		<style>
			.forwp-seo-score { font-weight: 600; }
			.forwp-seo-score--good { color: #2e7d32; }
			.forwp-seo-score--medium { color: #ed6c02; }
			.forwp-seo-score--low { color: #c62828; }
			.forwp-seo-inventory__stats {
				background: #fff;
				border: 1px solid #c3c4c7;
				padding: 12px 16px;
				margin: 16px 0;
			}
			.forwp-seo-inventory .forwp-seo-inventory__toolbar {
				display: flex;
				flex-wrap: wrap;
				align-items: center;
				justify-content: space-between;
				gap: 8px 16px;
				margin: 12px 0 8px;
				height: auto;
				min-height: 32px;
			}
			.forwp-seo-inventory .forwp-seo-inventory__toolbar .alignleft.actions {
				float: none;
				display: flex;
				flex-wrap: wrap;
				align-items: center;
				gap: 6px;
				margin: 0;
			}
			.forwp-seo-inventory .forwp-seo-inventory__toolbar .search-box {
				float: none;
				margin: 0;
				flex: 0 1 auto;
			}
			.forwp-seo-inventory .column-og_image {
				width: 64px;
			}
			.forwp-seo-inventory .forwp-seo-og-thumb {
				display: inline-block;
				width: 48px;
				height: 48px;
				border: 1px solid #c3c4c7;
				border-radius: 2px;
				overflow: hidden;
				background: #fff;
				line-height: 0;
				vertical-align: middle;
			}
			.forwp-seo-inventory .forwp-seo-og-thumb img {
				display: block;
				width: 100%;
				height: 100%;
				object-fit: cover;
			}
			.forwp-seo-inventory .forwp-seo-og-thumb--empty {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				color: #a7aaad;
				font-size: 16px;
				line-height: 1;
				border-style: dashed;
				background: #f6f7f7;
			}
			.forwp-seo-inventory .column-wp_title .row-actions {
				position: static;
				left: auto;
				padding-top: 4px;
			}
			.forwp-seo-inventory .column-wp_title .row-actions span {
				display: inline;
				float: none;
				font-size: 13px;
			}
			.forwp-seo-inventory .column-wp_title .row-actions .button-link {
				padding: 0;
				margin: 0;
				vertical-align: baseline;
			}
			.forwp-seo-inventory .column-wp_title .toggle-row {
				display: none !important;
			}
			.forwp-seo-inventory .forwp-seo-inline-edit-row td {
				padding: 0;
				background: #f6f7f7;
				box-shadow: inset 0 0 0 1px #c3c4c7;
			}
			.forwp-seo-inventory .forwp-seo-quick-edit-panel {
				padding: 12px 16px 14px;
			}
			.forwp-seo-inventory .forwp-seo-quick-edit-panel__header {
				margin-bottom: 10px;
			}
			.forwp-seo-inventory .forwp-seo-quick-edit-panel__legend {
				display: inline-block;
				font-size: 11px;
				font-weight: 600;
				line-height: 1.4;
				letter-spacing: 0.04em;
				text-transform: uppercase;
				color: #1d2327;
			}
			.forwp-seo-inventory .forwp-seo-quick-edit-panel__layout {
				display: grid;
				grid-template-columns: minmax(0, 1fr) minmax(220px, 280px);
				gap: 20px 24px;
				align-items: start;
			}
			.forwp-seo-inventory .forwp-seo-quick-edit-panel__main-grid {
				display: grid;
				grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
				gap: 12px 16px;
			}
			.forwp-seo-inventory .forwp-seo-quick-edit-panel__aside {
				border-left: 1px solid #dcdcde;
				padding-left: 24px;
			}
			.forwp-seo-inventory .forwp-seo-quick-edit-panel__field {
				display: flex;
				flex-direction: column;
				gap: 6px;
				margin: 0;
			}
			.forwp-seo-inventory .forwp-seo-quick-edit-panel__field--wide {
				grid-column: 1 / -1;
			}
			.forwp-seo-inventory .forwp-seo-quick-edit-panel__field--og {
				height: 100%;
			}
			.forwp-seo-inventory .forwp-seo-quick-edit-panel__label {
				font-size: 13px;
				font-weight: 600;
				line-height: 1.3;
				color: #1d2327;
			}
			.forwp-seo-inventory .forwp-seo-quick-edit-panel__field input[type="text"],
			.forwp-seo-inventory .forwp-seo-quick-edit-panel__field textarea {
				width: 100%;
				max-width: none;
				margin: 0;
				box-sizing: border-box;
			}
			.forwp-seo-inventory .forwp-seo-quick-edit-panel__field textarea {
				min-height: 96px;
				resize: vertical;
			}
			.forwp-seo-inventory .forwp-seo-og-image-control {
				display: flex;
				flex-direction: column;
				align-items: stretch;
				gap: 12px;
			}
			.forwp-seo-inventory .forwp-seo-og-image-preview {
				width: 100%;
				height: 160px;
				flex: none;
				border: 1px solid #c3c4c7;
				border-radius: 2px;
				background: #fff;
				display: flex;
				align-items: center;
				justify-content: center;
				text-align: center;
				overflow: hidden;
			}
			.forwp-seo-inventory .forwp-seo-og-image-preview img {
				display: block;
				width: 100%;
				height: 100%;
				object-fit: cover;
			}
			.forwp-seo-inventory .forwp-seo-og-image-preview.has-image .forwp-seo-og-image-preview__placeholder {
				display: none;
			}
			.forwp-seo-inventory .forwp-seo-og-image-preview__placeholder {
				padding: 8px;
				font-size: 12px;
				line-height: 1.4;
				color: #646970;
			}
			.forwp-seo-inventory .forwp-seo-og-image-actions {
				display: flex;
				flex-direction: column;
				align-items: stretch;
				gap: 6px;
			}
			.forwp-seo-inventory .forwp-seo-og-image-actions .button {
				width: 100%;
				text-align: center;
			}
			.forwp-seo-inventory .forwp-seo-quick-edit-panel__actions {
				display: flex;
				flex-wrap: wrap;
				align-items: center;
				gap: 8px;
				margin-top: 14px;
				padding-top: 12px;
				border-top: 1px solid #dcdcde;
			}
			.forwp-seo-inventory .forwp-seo-quick-edit-panel__actions .spinner {
				float: none;
				margin: 0;
			}
			.forwp-seo-inventory .forwp-seo-inline-notice {
				font-size: 13px;
			}
			.forwp-seo-inventory .forwp-seo-inline-notice--success { color: #2e7d32; }
			.forwp-seo-inventory .forwp-seo-inline-notice--error { color: #c62828; }
			.forwp-seo-inventory tr.forwp-seo-row-editing td {
				box-shadow: inset 0 0 0 1px #2271b1;
			}
			@media screen and (max-width: 782px) {
				.forwp-seo-inventory .forwp-seo-inventory__toolbar {
					flex-direction: column;
					align-items: stretch;
				}
				.forwp-seo-inventory .forwp-seo-inventory__toolbar .search-box {
					width: 100%;
				}
				.forwp-seo-inventory .forwp-seo-quick-edit-panel__layout {
					grid-template-columns: 1fr;
				}
				.forwp-seo-inventory .forwp-seo-quick-edit-panel__aside {
					border-left: 0;
					padding-left: 0;
					padding-top: 16px;
					border-top: 1px solid #dcdcde;
				}
				.forwp-seo-inventory .forwp-seo-quick-edit-panel__main-grid {
					grid-template-columns: 1fr;
				}
			}
		</style>
		<?php
	}
}
