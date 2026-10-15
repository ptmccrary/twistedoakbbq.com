<?php
/**
 * Food menu helpers on top of the twisted-oak-core menu source (local posts or Square).
 *
 * @package TwistedOak
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Menu groups (category + items), or [] when the core plugin is inactive.
 *
 * @return list<array{category: array<string, mixed>, items: list<array<string, mixed>>}>
 */
function twistedoak_menu_groups(): array {
	return function_exists( 'TwistedOak\Core\Menu\groups' ) ? \TwistedOak\Core\Menu\groups() : array();
}

/**
 * Featured items for the homepage.
 *
 * @return list<array<string, mixed>>
 */
function twistedoak_menu_featured( int $limit = 4 ): array {
	return function_exists( 'TwistedOak\Core\Menu\featured' ) ? \TwistedOak\Core\Menu\featured( $limit ) : array();
}

/**
 * Anchor id for a category on /menu/ ("proteins" is "meats" in the design).
 */
function twistedoak_menu_anchor( string $slug ): string {
	return 'proteins' === $slug ? 'meats' : sanitize_title( $slug );
}

/**
 * Presentation for a category: display title, intro line and photo slot.
 *
 * @param array<string, mixed> $category Category DTO.
 * @return array{title: string, intro: string, photo: string}
 */
function twistedoak_menu_category_meta( array $category ): array {
	$slug    = (string) ( $category['slug'] ?? '' );
	$presets = array(
		'proteins' => array(
			'title' => 'Smoked meats',
			'intro' => 'Smoked low and slow over real hardwood. Sold by the pound until we sell out.',
		),
		'sides'    => array(
			'title' => 'Southern sides',
			'intro' => 'Made in house, from scratch.',
		),
		'desserts' => array(
			'title' => 'Desserts',
			'intro' => '',
		),
	);
	$name   = (string) ( $category['name'] ?? '' );
	$preset = $presets[ $slug ] ?? array(
		'title' => ucfirst( strtolower( $name ) ),
		'intro' => (string) ( $category['description'] ?? '' ),
	);
	$preset['photo'] = 'menu_' . $slug;
	return (array) apply_filters( 'twistedoak_menu_category_meta', $preset, $category );
}

/**
 * Badges for a menu item, most important first.
 *
 * @param array<string, mixed> $item Item DTO.
 */
function twistedoak_menu_badges( array $item ): string {
	$out     = '';
	$dietary = array_column( (array) ( $item['dietary'] ?? array() ), 'slug' );
	if ( ! empty( $item['sold_out'] ) ) {
		$out .= twistedoak_badge( __( 'Sold out', 'twistedoak' ), 'neutral' );
	}
	if ( ! empty( $item['premium'] ) ) {
		$out .= twistedoak_badge( __( 'Premium', 'twistedoak' ), 'brand', 'flame' );
	}
	if ( in_array( 'vegan', $dietary, true ) ) {
		$out .= twistedoak_badge( __( 'Vegan', 'twistedoak' ), 'accent' );
	} elseif ( in_array( 'vegetarian', $dietary, true ) ) {
		$out .= twistedoak_badge( __( 'Vegetarian', 'twistedoak' ), 'accent' );
	}
	if ( ! empty( $item['seasonal'] ) ) {
		$out .= twistedoak_badge( __( 'Seasonal', 'twistedoak' ), 'neutral' );
	}
	if ( ! empty( $item['catering_only'] ) ) {
		$out .= twistedoak_badge( __( 'Catering only', 'twistedoak' ), 'neutral' );
	}
	return $out;
}

/**
 * Item photo: its featured image, else a placeholder captioned with its name.
 *
 * @param array<string, mixed> $item Item DTO.
 */
function twistedoak_menu_photo( array $item, string $class = '' ): string {
	$name = (string) ( $item['name'] ?? '' );
	return twistedoak_photo(
		'menu_item',
		array(
			'class'      => $class,
			'attachment' => (int) ( $item['image']['id'] ?? 0 ),
			'size'       => 'twistedoak-card',
			'alt'        => $name,
			'caption'    => strtolower( $name ),
			'sizes'      => '(min-width: 1200px) 280px, (min-width: 640px) 45vw, 80vw',
		)
	);
}
