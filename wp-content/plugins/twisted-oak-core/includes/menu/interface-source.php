<?php
/**
 * Menu source contract. Every source returns plain arrays ("DTOs") so the
 * theme never touches WP_Post / Square objects directly.
 *
 * Category DTO:
 *   array{
 *     id: string, slug: string, name: string, description: string,
 *     sort: int, url: string, square_id: ?string, count: int
 *   }
 *
 * Item DTO:
 *   array{
 *     id: string,               // source-native id (post ID as string for Local, catalog object id for Square)
 *     post_id: ?int,            // WP post backing the item (null when a source has no CPT mirror)
 *     slug: string, name: string,
 *     description: string,      // plain text
 *     description_html: string, // sanitized HTML (wp_kses_post); '' when the source only has text
 *     price_cents: ?int,        // null for "Market"/unpriced
 *     price: string,            // formatted, e.g. "$14.50", "Market", or ''
 *     unit: string,             // e.g. "each", "half pan"
 *     price_label: string,      // price + unit, e.g. "$14.50 each"; '' when unpriced
 *     currency: string,         // ISO 4217, "USD"
 *     category: ?array{id: string, slug: string, name: string},
 *     dietary: list<array{slug: string, name: string}>,
 *     image: ?array{id: ?int, url: string, full: string, width: int, height: int, alt: string, srcset: string, sizes: string},
 *     available: bool,          // false when sold out (or otherwise not orderable)
 *     sold_out: bool,
 *     featured: bool, premium: bool, catering_only: bool, seasonal: bool,
 *     sort: int, square_id: ?string, url: string
 *   }
 *
 * @package TwistedOakCore
 */

declare(strict_types=1);

namespace TwistedOak\Core\Menu;

defined( 'ABSPATH' ) || exit;

interface Source {

	/**
	 * Short identifier, e.g. "local" or "square".
	 */
	public function id(): string;

	/**
	 * Categories ordered by sort then name. Empty categories may be included (count = 0).
	 *
	 * @return list<array<string, mixed>>
	 */
	public function categories(): array;

	/**
	 * Items ordered by sort then name, optionally limited to one category slug.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function items( ?string $category = null ): array;

	/**
	 * One item by id (int/numeric = post ID or source id) or slug; null when missing.
	 *
	 * @return array<string, mixed>|null
	 */
	public function item( int|string $id_or_slug ): ?array;
}
