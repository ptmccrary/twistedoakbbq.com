<?php
/**
 * Menu source resolution + shared DTO helpers.
 *
 *   TwistedOak\Core\Menu\source(): Source        — the active source (filter: twistedoak_menu_source)
 *   TwistedOak\Core\Menu\groups(): list<{category, items}>
 *   TwistedOak\Core\Menu\featured( int $limit ): list<item>
 *
 * @package TwistedOakCore
 */

declare(strict_types=1);

namespace TwistedOak\Core\Menu;

defined( 'ABSPATH' ) || exit;

/**
 * The active menu source (memoised per request).
 *
 * Default comes from Food Menu → Square → "Menu source" (local | square).
 * Filter `twistedoak_menu_source` may return a Source instance or 'local' / 'square'.
 */
function source( bool $reset = false ): Source {
	static $instance = null;
	if ( null !== $instance && ! $reset ) {
		return $instance;
	}
	$opt    = get_option( 'twistedoak_square_settings', array() );
	$chosen = is_array( $opt ) && 'square' === ( $opt['source'] ?? '' ) ? 'square' : 'local';
	$chosen = apply_filters( 'twistedoak_menu_source', $chosen );

	if ( $chosen instanceof Source ) {
		$instance = $chosen;
	} elseif ( 'square' === $chosen ) {
		$instance = new SquareSource( new LocalSource() );
	} else {
		$instance = new LocalSource();
	}
	return $instance;
}

/**
 * Categories with their items, in display order. Empty categories are skipped.
 *
 * @return list<array{category: array<string, mixed>, items: list<array<string, mixed>>}>
 */
function groups( ?Source $src = null ): array {
	$src     = $src ?? source();
	$buckets = array();
	foreach ( $src->items() as $item ) {
		$slug = $item['category']['slug'] ?? '';
		if ( '' !== $slug ) {
			$buckets[ $slug ][] = $item;
		}
	}
	$out = array();
	foreach ( $src->categories() as $cat ) {
		if ( ! empty( $buckets[ $cat['slug'] ] ) ) {
			$out[] = array(
				'category' => $cat,
				'items'    => $buckets[ $cat['slug'] ],
			);
		}
	}
	return $out;
}

/**
 * Featured items (available ones first), up to $limit (-1 = all).
 *
 * @return list<array<string, mixed>>
 */
function featured( int $limit = 6, ?Source $src = null ): array {
	$items = array_values( array_filter( ( $src ?? source() )->items(), static fn( array $i ): bool => ! empty( $i['featured'] ) ) );
	usort( $items, static fn( array $a, array $b ): int => (int) $b['available'] <=> (int) $a['available'] );
	return $limit < 0 ? $items : array_slice( $items, 0, $limit );
}

/**
 * "$14.50" from cents.
 */
function format_price( int $cents, string $currency = 'USD' ): string {
	$symbol = 'USD' === $currency ? '$' : $currency . ' ';
	$amount = $cents / 100;
	$str    = function_exists( 'number_format_i18n' ) ? number_format_i18n( $amount, 2 ) : number_format( $amount, 2 );
	return (string) apply_filters( 'twistedoak_menu_format_price', $symbol . $str, $cents, $currency );
}

/**
 * Image DTO for an attachment, or null.
 *
 * @return array{id: ?int, url: string, full: string, width: int, height: int, alt: string, srcset: string, sizes: string}|null
 */
function image_dto( int $attachment_id, ?string $size = null ): ?array {
	if ( $attachment_id <= 0 ) {
		return null;
	}
	$size = $size ?? ( has_image_size( 'twistedoak-card' ) ? 'twistedoak-card' : 'large' );
	$size = (string) apply_filters( 'twistedoak_menu_image_size', $size );
	$src  = wp_get_attachment_image_src( $attachment_id, $size );
	if ( ! $src ) {
		return null;
	}
	$full = wp_get_attachment_image_src( $attachment_id, 'full' );
	return array(
		'id'     => $attachment_id,
		'url'    => (string) $src[0],
		'full'   => $full ? (string) $full[0] : (string) $src[0],
		'width'  => (int) $src[1],
		'height' => (int) $src[2],
		'alt'    => trim( wp_strip_all_tags( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) ),
		'srcset' => (string) wp_get_attachment_image_srcset( $attachment_id, $size ),
		'sizes'  => (string) wp_get_attachment_image_sizes( $attachment_id, $size ),
	);
}
