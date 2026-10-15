<?php
/**
 * Square → tobbq_menu_item CPT sync (RECOMMENDED integration path) — STUB.
 *
 * Why sync instead of rendering from Square directly:
 *   + WP keeps permalinks (/menu/brisket/), SEO, featured images, editorial descriptions, dietary tags.
 *   + Pages render from the local DB — no Square latency/outage on page views.
 *   + Templates and LocalSource stay unchanged.
 *   − Data can lag Square until the next sync (mitigate: cron every 15 min + Square webhooks
 *     catalog.version.updated / inventory.count.updated → schedule a sync).
 *   − Two places to edit: Square owns name/price/sold-out/category; WP owns copy, photos, dietary,
 *     featured, premium. The sync only writes Square-owned fields.
 *
 * Field ownership when syncing:
 *   Square → WP: _tobbq_square_id, post_title (new items only, unless --overwrite-titles),
 *                _tobbq_price (from price_cents), _tobbq_unit (variation name), _tobbq_sold_out,
 *                tobbq_menu_category (term matched by term meta `square_id`, then slug; created if missing),
 *                post_status (archived/deleted in Square → draft).
 *   WP only:     content/excerpt, featured image, tobbq_dietary, _tobbq_featured, _tobbq_premium, menu_order.
 *
 * Run: `wp twisted-oak-menu sync-square [--dry-run]` (no-op until SquareSource can fetch).
 * TODO(square): register a 15-minute cron event once live requests are enabled.
 *
 * @package TwistedOakCore
 */

declare(strict_types=1);

namespace TwistedOak\Core\Menu;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class SquareSync {

	/**
	 * @return array{created: int, updated: int, drafted: int}|WP_Error
	 */
	public static function run( bool $dry_run = false ): array|WP_Error {
		if ( ! SquareSource::is_configured() ) {
			return new WP_Error( 'square_not_configured', 'Square is not configured (Food Menu → Square).' );
		}
		$source  = new SquareSource();
		$catalog = $source->catalog( true );
		if ( null === $catalog ) {
			return new WP_Error( 'square_unavailable', 'Could not load the Square catalog (live requests disabled or request failed).' );
		}

		$stats = array(
			'created' => 0,
			'updated' => 0,
			'drafted' => 0,
		);
		$seen  = array();

		foreach ( $catalog['items'] as $item ) {
			$seen[] = $item['square_id'];
			if ( $dry_run ) {
				++$stats[ $item['post_id'] ? 'updated' : 'created' ];
				continue;
			}

			$postarr = array(
				'post_type'   => 'tobbq_menu_item',
				'post_status' => 'publish',
			);
			if ( $item['post_id'] ) {
				$postarr['ID'] = $item['post_id'];
			} else {
				$postarr['post_title']   = $item['name'];
				$postarr['post_content'] = $item['description'] ? '<!-- wp:paragraph --><p>' . esc_html( $item['description'] ) . '</p><!-- /wp:paragraph -->' : '';
			}
			$id = wp_insert_post( $postarr, true );
			if ( is_wp_error( $id ) ) {
				continue;
			}
			update_post_meta( $id, '_tobbq_square_id', $item['square_id'] );
			update_post_meta( $id, '_tobbq_price', null !== $item['price_cents'] ? number_format( $item['price_cents'] / 100, 2, '.', '' ) : 'Market' );
			update_post_meta( $id, '_tobbq_unit', $item['unit'] );
			update_post_meta( $id, '_tobbq_sold_out', (bool) $item['sold_out'] );

			if ( $item['category'] ) {
				$term_id = self::term_for( $item['category'] );
				if ( $term_id ) {
					wp_set_object_terms( $id, array( $term_id ), 'tobbq_menu_category' );
				}
			}
			++$stats[ $item['post_id'] ? 'updated' : 'created' ];
		}

		// Items removed/archived in Square → draft (never delete WP content).
		$orphans = get_posts(
			array(
				'post_type'   => 'tobbq_menu_item',
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_tobbq_square_id',
						'value'   => $seen ? $seen : array( '' ),
						'compare' => 'NOT IN',
					),
				),
			)
		);
		foreach ( $orphans as $orphan ) {
			if ( ! $dry_run ) {
				wp_update_post(
					array(
						'ID'          => (int) $orphan,
						'post_status' => 'draft',
					)
				);
			}
			++$stats['drafted'];
		}

		do_action( 'twistedoak_square_synced', $stats, $dry_run );
		return $stats;
	}

	/**
	 * Find or create the menu category for a Square category.
	 *
	 * @param array{id: string, slug: string, name: string} $cat Category ref.
	 */
	private static function term_for( array $cat ): int {
		$terms = get_terms(
			array(
				'taxonomy'   => 'tobbq_menu_category',
				'hide_empty' => false,
				'number'     => 1,
				'fields'     => 'ids',
				'meta_key'   => 'square_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $cat['id'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( ! is_wp_error( $terms ) && $terms ) {
			return (int) $terms[0];
		}
		$term = get_term_by( 'slug', $cat['slug'], 'tobbq_menu_category' );
		if ( ! $term ) {
			$res = wp_insert_term( $cat['name'], 'tobbq_menu_category', array( 'slug' => $cat['slug'] ) );
			if ( is_wp_error( $res ) ) {
				return 0;
			}
			$term_id = (int) $res['term_id'];
		} else {
			$term_id = (int) $term->term_id;
		}
		update_term_meta( $term_id, 'square_id', $cat['id'] );
		return $term_id;
	}
}
