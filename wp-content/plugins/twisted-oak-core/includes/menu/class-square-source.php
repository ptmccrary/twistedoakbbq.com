<?php
/**
 * Square Catalog menu source — STUB.
 *
 * Status: mapping + caching + settings are implemented; live HTTP calls are
 * DISABLED unless the `twistedoak_square_live_requests` filter returns true.
 * Until then (or when not configured / a request fails) every method falls
 * back to LocalSource, so the site keeps working.
 *
 * Recommended production path is NOT to render from this source directly but
 * to run SquareSync (Square → tobbq_menu_item CPT) and keep LocalSource as the
 * render source — WP then keeps permalinks, SEO, featured images and editorial
 * copy, and pages never wait on Square. See SCHEDULING.md "Switching to Square".
 *
 * ---------------------------------------------------------------------------
 * Square Catalog API → DTO mapping (Square-Version 2026-09-16)
 * ---------------------------------------------------------------------------
 * GET  {base}/v2/catalog/list?types=ITEM,CATEGORY,IMAGE   (cursor-paginated; follow `cursor`)
 *   base = https://connect.squareup.com (production) | https://connect.squareupsandbox.com (sandbox)
 *   headers: Authorization: Bearer {token}, Square-Version: 2026-09-16
 *
 * CATEGORY object  → category DTO
 *   id                          → id, square_id
 *   category_data.name          → name, slug = sanitize_title(name)
 *   (no sort in Square)         → sort: ordinal from `twistedoak_square_category_order` option/filter, else alpha
 *
 * ITEM object      → item DTO
 *   id                          → id, square_id
 *   item_data.name              → name, slug
 *   item_data.description_plaintext (fallback description) → description
 *   item_data.description_html  → description_html (wp_kses_post)
 *   item_data.categories[0].id  (fallback item_data.reporting_category.id, legacy item_data.category_id) → category
 *   item_data.image_ids[0]      (legacy top-level image_id) → IMAGE.image_data.url → image.url/full (no WP attachment id)
 *   item_data.variations[]      (ITEM_VARIATION):
 *       item_variation_data.price_money.amount (int, cents) → price_cents (lowest FIXED_PRICING variation)
 *       item_variation_data.price_money.currency            → currency
 *       pricing_type VARIABLE_PRICING                       → price_cents null, price "Market"
 *       item_variation_data.name (when not "Regular")       → unit (e.g. "Half pound", "Pint")
 *       item_variation_data.location_overrides[{location_id, sold_out: true}] → sold_out (all variations sold out at our location)
 *   item_data.is_archived / present_at_location_ids        → excluded / available
 *   dietary, featured, premium, catering_only               → not native to Square: use Custom Attributes
 *       (TODO: define custom attribute definitions "tobbq_dietary", "tobbq_featured" and read
 *        item.custom_attribute_values) — or keep them as WP-side meta via SquareSync.
 *
 * Inventory (optional, for tracked items)
 *   POST {base}/v2/inventory/counts/batch-retrieve
 *     {"catalog_object_ids": [variation ids], "location_ids": [location_id], "states": ["IN_STOCK"]}
 *   quantity <= 0 for every variation → sold_out = true
 *
 * @package TwistedOakCore
 */

declare(strict_types=1);

namespace TwistedOak\Core\Menu;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class SquareSource implements Source {

	public const API_VERSION = '2026-09-16';

	private Source $fallback;

	/** @var array{categories: list<array<string, mixed>>, items: list<array<string, mixed>>}|null */
	private ?array $catalog = null;

	public function __construct( ?Source $fallback = null ) {
		$this->fallback = $fallback ?? new LocalSource();
	}

	public function id(): string {
		return 'square';
	}

	/**
	 * Settings + constant overrides (TWISTEDOAK_SQUARE_ACCESS_TOKEN / _LOCATION_ID / _ENVIRONMENT in wp-config.php win).
	 *
	 * @return array{access_token: string, location_id: string, environment: string, cache_minutes: int}
	 */
	public static function settings(): array {
		$opt = get_option( 'twistedoak_square_settings', array() );
		$opt = is_array( $opt ) ? $opt : array();
		return array(
			'access_token'  => defined( 'TWISTEDOAK_SQUARE_ACCESS_TOKEN' ) ? (string) TWISTEDOAK_SQUARE_ACCESS_TOKEN : (string) ( $opt['access_token'] ?? '' ),
			'location_id'   => defined( 'TWISTEDOAK_SQUARE_LOCATION_ID' ) ? (string) TWISTEDOAK_SQUARE_LOCATION_ID : (string) ( $opt['location_id'] ?? '' ),
			'environment'   => defined( 'TWISTEDOAK_SQUARE_ENVIRONMENT' ) ? (string) TWISTEDOAK_SQUARE_ENVIRONMENT : ( 'production' === ( $opt['environment'] ?? '' ) ? 'production' : 'sandbox' ),
			'cache_minutes' => max( 1, (int) ( $opt['cache_minutes'] ?? 15 ) ),
		);
	}

	public static function is_configured(): bool {
		$s = self::settings();
		return '' !== $s['access_token'] && '' !== $s['location_id'];
	}

	public static function base_url(): string {
		return 'production' === self::settings()['environment'] ? 'https://connect.squareup.com' : 'https://connect.squareupsandbox.com';
	}

	public function categories(): array {
		$catalog = $this->catalog();
		return $catalog ? $catalog['categories'] : $this->fallback->categories();
	}

	public function items( ?string $category = null ): array {
		$catalog = $this->catalog();
		if ( ! $catalog ) {
			return $this->fallback->items( $category );
		}
		if ( null === $category || '' === $category ) {
			return $catalog['items'];
		}
		return array_values( array_filter( $catalog['items'], static fn( array $i ): bool => ( $i['category']['slug'] ?? '' ) === $category ) );
	}

	public function item( int|string $id_or_slug ): ?array {
		$catalog = $this->catalog();
		if ( ! $catalog ) {
			return $this->fallback->item( $id_or_slug );
		}
		foreach ( $catalog['items'] as $item ) {
			if ( (string) $id_or_slug === $item['id'] || (string) $id_or_slug === $item['slug'] || (string) $id_or_slug === (string) $item['post_id'] ) {
				return $item;
			}
		}
		return null;
	}

	/**
	 * Mapped catalog from cache or Square; null → use the fallback.
	 *
	 * @return array{categories: list<array<string, mixed>>, items: list<array<string, mixed>>}|null
	 */
	public function catalog( bool $refresh = false ): ?array {
		if ( null !== $this->catalog && ! $refresh ) {
			return $this->catalog;
		}
		if ( ! self::is_configured() ) {
			return null;
		}
		$key = 'tobbq_square_catalog_' . md5( self::settings()['location_id'] . self::settings()['environment'] );
		if ( ! $refresh ) {
			$cached = get_transient( $key );
			if ( is_array( $cached ) ) {
				$this->catalog = $cached;
				return $cached;
			}
		}

		$raw = $this->fetch_catalog();
		if ( is_wp_error( $raw ) ) {
			// Serve stale data if we have it; otherwise fall back to Local.
			$stale = get_option( $key . '_stale' );
			return is_array( $stale ) ? $stale : null;
		}
		$mapped = self::map_catalog( $raw['objects'], $raw['inventory'], self::settings()['location_id'] );
		set_transient( $key, $mapped, self::settings()['cache_minutes'] * MINUTE_IN_SECONDS );
		update_option( $key . '_stale', $mapped, false );
		$this->catalog = $mapped;
		return $mapped;
	}

	/**
	 * Fetch raw catalog objects (+ inventory counts) from Square.
	 *
	 * TODO(square): enable by returning true from `twistedoak_square_live_requests` once a sandbox
	 * token is in place; verify pagination, then add a webhook (catalog.version.updated,
	 * inventory.count.updated) that deletes the transient / triggers SquareSync.
	 *
	 * @return array{objects: list<array<string, mixed>>, inventory: array<string, float>}|WP_Error
	 */
	public function fetch_catalog(): array|WP_Error {
		if ( ! apply_filters( 'twistedoak_square_live_requests', false ) ) {
			return new WP_Error( 'square_disabled', 'Live Square requests are disabled (twistedoak_square_live_requests filter).' );
		}
		$s       = self::settings();
		$headers = array(
			'Authorization'  => 'Bearer ' . $s['access_token'],
			'Square-Version' => self::API_VERSION,
			'Content-Type'   => 'application/json',
		);

		$objects = array();
		$cursor  = '';
		$pages   = 0;
		do {
			$url = add_query_arg( array_filter( array( 'types' => 'ITEM,CATEGORY,IMAGE', 'cursor' => $cursor ) ), self::base_url() . '/v2/catalog/list' );
			$res = wp_remote_get( $url, array( 'headers' => $headers, 'timeout' => 15 ) );
			if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
				return is_wp_error( $res ) ? $res : new WP_Error( 'square_http', 'Square catalog request failed: HTTP ' . wp_remote_retrieve_response_code( $res ) );
			}
			$body    = json_decode( wp_remote_retrieve_body( $res ), true );
			$objects = array_merge( $objects, (array) ( $body['objects'] ?? array() ) );
			$cursor  = (string) ( $body['cursor'] ?? '' );
		} while ( '' !== $cursor && ++$pages < 20 );

		// Inventory for tracked variations (100 ids per request).
		$variation_ids = array();
		foreach ( $objects as $o ) {
			foreach ( (array) ( $o['item_data']['variations'] ?? array() ) as $v ) {
				$variation_ids[] = (string) $v['id'];
			}
		}
		$inventory = array();
		foreach ( array_chunk( $variation_ids, 100 ) as $chunk ) {
			$res = wp_remote_post(
				self::base_url() . '/v2/inventory/counts/batch-retrieve',
				array(
					'headers' => $headers,
					'timeout' => 15,
					'body'    => wp_json_encode(
						array(
							'catalog_object_ids' => $chunk,
							'location_ids'       => array( $s['location_id'] ),
							'states'             => array( 'IN_STOCK' ),
						)
					),
				)
			);
			if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
				break; // Inventory is optional; sold_out still comes from location_overrides.
			}
			$body = json_decode( wp_remote_retrieve_body( $res ), true );
			foreach ( (array) ( $body['counts'] ?? array() ) as $c ) {
				$inventory[ (string) $c['catalog_object_id'] ] = (float) $c['quantity'];
			}
		}

		return array(
			'objects'   => $objects,
			'inventory' => $inventory,
		);
	}

	/**
	 * Pure mapper: Square catalog objects → DTOs (testable without HTTP).
	 *
	 * @param list<array<string, mixed>> $objects     Catalog objects (ITEM, CATEGORY, IMAGE).
	 * @param array<string, float>       $inventory   variation id => IN_STOCK quantity (tracked only).
	 * @return array{categories: list<array<string, mixed>>, items: list<array<string, mixed>>}
	 */
	public static function map_catalog( array $objects, array $inventory, string $location_id ): array {
		$cats   = array();
		$images = array();
		foreach ( $objects as $o ) {
			if ( ! empty( $o['is_deleted'] ) ) {
				continue;
			}
			if ( 'CATEGORY' === ( $o['type'] ?? '' ) ) {
				$name                     = (string) ( $o['category_data']['name'] ?? '' );
				$cats[ (string) $o['id'] ] = array(
					'id'          => (string) $o['id'],
					'slug'        => sanitize_title( $name ),
					'name'        => $name,
					'description' => '',
					'sort'        => (int) apply_filters( 'twistedoak_square_category_sort', 0, $o ),
					'url'         => '',
					'square_id'   => (string) $o['id'],
					'count'       => 0,
				);
			} elseif ( 'IMAGE' === ( $o['type'] ?? '' ) ) {
				$images[ (string) $o['id'] ] = array(
					'url' => (string) ( $o['image_data']['url'] ?? '' ),
					'alt' => (string) ( $o['image_data']['caption'] ?? ( $o['image_data']['name'] ?? '' ) ),
				);
			}
		}

		$items = array();
		foreach ( $objects as $o ) {
			if ( 'ITEM' !== ( $o['type'] ?? '' ) || ! empty( $o['is_deleted'] ) || ! empty( $o['item_data']['is_archived'] ) ) {
				continue;
			}
			if ( empty( $o['present_at_all_locations'] ) && ! in_array( $location_id, (array) ( $o['present_at_location_ids'] ?? array() ), true ) ) {
				continue;
			}
			$d      = (array) $o['item_data'];
			$cat_id = (string) ( $d['categories'][0]['id'] ?? ( $d['reporting_category']['id'] ?? ( $d['category_id'] ?? '' ) ) );
			$cat    = $cats[ $cat_id ] ?? null;

			$cents    = null;
			$currency = 'USD';
			$unit     = '';
			$sold     = array();
			foreach ( (array) ( $d['variations'] ?? array() ) as $v ) {
				$vd = (array) ( $v['item_variation_data'] ?? array() );
				if ( 'FIXED_PRICING' === ( $vd['pricing_type'] ?? 'FIXED_PRICING' ) && isset( $vd['price_money']['amount'] ) ) {
					$amount = (int) $vd['price_money']['amount'];
					if ( null === $cents || $amount < $cents ) {
						$cents    = $amount;
						$currency = (string) ( $vd['price_money']['currency'] ?? 'USD' );
						$unit     = 'Regular' === ( $vd['name'] ?? 'Regular' ) ? '' : (string) $vd['name'];
					}
				}
				$is_sold = false;
				foreach ( (array) ( $vd['location_overrides'] ?? array() ) as $lo ) {
					if ( ( $lo['location_id'] ?? '' ) === $location_id && ! empty( $lo['sold_out'] ) ) {
						$is_sold = true;
					}
				}
				if ( isset( $inventory[ (string) $v['id'] ] ) && $inventory[ (string) $v['id'] ] <= 0 ) {
					$is_sold = true;
				}
				$sold[] = $is_sold;
			}
			$sold_out = $sold && ! in_array( false, $sold, true );

			$image_id = (string) ( $d['image_ids'][0] ?? ( $o['image_id'] ?? '' ) );
			$image    = isset( $images[ $image_id ] ) && '' !== $images[ $image_id ]['url'] ? array(
				'id'     => null,
				'url'    => $images[ $image_id ]['url'],
				'full'   => $images[ $image_id ]['url'],
				'width'  => 0,
				'height' => 0,
				'alt'    => $images[ $image_id ]['alt'],
				'srcset' => '',
				'sizes'  => '',
			) : null;

			$price = null !== $cents ? format_price( $cents, $currency ) : ( $d['variations'] ?? false ? __( 'Market', 'twisted-oak-core' ) : '' );
			$slug  = sanitize_title( (string) ( $d['name'] ?? '' ) );

			// Link to the synced CPT post when one exists (keeps permalinks/SEO).
			$post_ids = get_posts(
				array(
					'post_type'   => 'tobbq_menu_item',
					'numberposts' => 1,
					'fields'      => 'ids',
					'meta_key'    => '_tobbq_square_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'  => (string) $o['id'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			$post_id  = $post_ids ? (int) $post_ids[0] : null;
			$archive  = get_post_type_archive_link( 'tobbq_menu_item' );

			$items[] = array(
				'id'               => (string) $o['id'],
				'post_id'          => $post_id,
				'slug'             => $slug,
				'name'             => (string) ( $d['name'] ?? '' ),
				'description'      => (string) ( $d['description_plaintext'] ?? wp_strip_all_tags( (string) ( $d['description'] ?? '' ) ) ),
				'description_html' => wp_kses_post( (string) ( $d['description_html'] ?? '' ) ),
				'price_cents'      => $cents,
				'price'            => $price,
				'unit'             => $unit,
				'price_label'      => trim( $price . ' ' . $unit ),
				'currency'         => $currency,
				'category'         => $cat ? array(
					'id'   => $cat['id'],
					'slug' => $cat['slug'],
					'name' => $cat['name'],
				) : null,
				'dietary'          => array(), // TODO(square): custom attributes.
				'image'            => $image,
				'available'        => ! $sold_out,
				'sold_out'         => $sold_out,
				'featured'         => false, // TODO(square): custom attribute or WP-side meta.
				'premium'          => false,
				'catering_only'    => false,
				'seasonal'         => false,
				'sort'             => (int) ( $d['categories'][0]['ordinal'] ?? 0 ),
				'square_id'        => (string) $o['id'],
				'url'              => $post_id ? (string) get_permalink( $post_id ) : ( $archive ? $archive . '#item-' . $slug : '' ),
			);
			if ( $cat ) {
				++$cats[ $cat_id ]['count'];
			}
		}

		$cats = array_values( $cats );
		usort( $cats, static fn( array $a, array $b ): int => ( $a['sort'] <=> $b['sort'] ) ?: strnatcasecmp( $a['name'], $b['name'] ) );
		usort( $items, static fn( array $a, array $b ): int => ( $a['sort'] <=> $b['sort'] ) ?: strnatcasecmp( $a['name'], $b['name'] ) );

		return array(
			'categories' => $cats,
			'items'      => $items,
		);
	}
}
