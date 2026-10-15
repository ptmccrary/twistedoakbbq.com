<?php
/**
 * Settings access, package list and product (menu item) lookup.
 *
 * @package TwistedOakCatering
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const TWISTEDOAK_CATERING_OPTION = 'twistedoak_catering_settings';

/**
 * Default settings. Mirrors the Drupal module's config/install yml (admin_email)
 * plus the values that were hard-coded in CateringController.
 *
 * @return array<string, mixed>
 */
function twistedoak_catering_default_settings(): array {
	return array(
		'admin_email'      => '',
		// "Custom" is a booking-form choice only (custom quote); the theme shows it as no package card.
		'packages'         => "Belmont Classic\nMonticello Smokehouse\nJefferson Reserve\nCustom",
		'premium_packages' => 'Jefferson Reserve',
		'premium_proteins' => "Smoked Salmon\nTexas Style Brisket\nTexas Style Pork Belly",
		'success_message'  => "Your catering inquiry has been received. We'll be in touch shortly.",
		'rate_limit'       => 5,
	);
}

/**
 * All settings merged over defaults.
 *
 * @return array<string, mixed>
 */
function twistedoak_catering_settings(): array {
	$saved = get_option( TWISTEDOAK_CATERING_OPTION, array() );
	return array_merge( twistedoak_catering_default_settings(), is_array( $saved ) ? $saved : array() );
}

/**
 * Single setting.
 */
function twistedoak_catering_setting( string $key ): mixed {
	return twistedoak_catering_settings()[ $key ] ?? null;
}

/**
 * Split a newline-separated setting into a clean list.
 *
 * @return string[]
 */
function twistedoak_catering_lines( string $value ): array {
	$lines = array_map( 'trim', preg_split( '/\R/', $value ) ?: array() );
	return array_values( array_unique( array_filter( $lines, static fn( string $l ): bool => '' !== $l ) ) );
}

/**
 * Recipient for inquiry emails; falls back to the site admin email.
 */
function twistedoak_catering_recipient(): string {
	$email = sanitize_email( (string) twistedoak_catering_setting( 'admin_email' ) );
	return '' !== $email ? $email : (string) get_option( 'admin_email' );
}

/**
 * @return string[]
 */
function twistedoak_catering_packages(): array {
	return (array) apply_filters( 'twistedoak_catering_packages', twistedoak_catering_lines( (string) twistedoak_catering_setting( 'packages' ) ) );
}

/**
 * @return string[]
 */
function twistedoak_catering_premium_packages(): array {
	return (array) apply_filters( 'twistedoak_catering_premium_packages', twistedoak_catering_lines( (string) twistedoak_catering_setting( 'premium_packages' ) ) );
}

/**
 * Smoked-meat and side caps per package (keyed by sanitised package name). Mirrors the theme's
 * twistedoak_package_details() picks; unknown packages are uncapped (0).
 *
 * @return array{meats:int,sides:int}
 */
function twistedoak_catering_pick_limits( string $package ): array {
	$map    = array(
		'belmont-classic'       => array( 'meats' => 1, 'sides' => 2 ),
		'monticello-smokehouse' => array( 'meats' => 2, 'sides' => 3 ),
		'jefferson-reserve'     => array( 'meats' => 3, 'sides' => 3 ),
		'custom'                => array( 'meats' => 5, 'sides' => 5 ),
	);
	$limits = $map[ sanitize_title( $package ) ] ?? array( 'meats' => 0, 'sides' => 0 );
	return (array) apply_filters( 'twistedoak_catering_pick_limits', $limits, $package );
}

function twistedoak_catering_is_premium_package( string $package ): bool {
	return in_array( $package, twistedoak_catering_premium_packages(), true );
}

/**
 * Fallback product lists (the published Drupal products at port time), used
 * only when no tobbq_menu_item posts exist in the matching categories.
 *
 * @return array{meats: string[], premiumMeats: string[], sides: string[], desserts: string[]}
 */
function twistedoak_catering_fallback_products(): array {
	return array(
		'meats'        => array( 'Pulled Pork', 'Smoked Pulled BBQ Chicken', 'Smoked Tofu Burnt Ends', 'Smoked Turkey Breast', 'St Louis Cut Ribs' ),
		'premiumMeats' => array( 'Smoked Salmon', 'Texas Style Brisket', 'Texas Style Pork Belly' ),
		'sides'        => array( 'Barbecue Pit Beans', 'Esquites - Mexican Street Corn Salad', 'Loaded Potato Salad', 'Southern Sweet Slaw', 'Sweet Cornbread', 'Watermelon and Feta Salad', 'White Cheddar Mac and Cheese' ),
		'desserts'     => array( 'Banana Pudding', 'Southern Apple Crisp' ),
	);
}

/**
 * Product names grouped like the Drupal controller's loadProducts().
 *
 * Reads published `tobbq_menu_item` posts in the `tobbq_menu_category` terms
 * proteins / sides / desserts (slug or name). A protein is premium when it has
 * truthy `_tobbq_premium` meta or its title is listed in the "Premium proteins"
 * setting. Each group falls back to the hard-coded list when empty.
 *
 * @return array{meats: string[], premiumMeats: string[], sides: string[], desserts: string[]}
 */
function twistedoak_catering_products(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$fallback = twistedoak_catering_fallback_products();
	$products = array(
		'meats'        => array(),
		'premiumMeats' => array(),
		'sides'        => array(),
		'desserts'     => array(),
	);

	/**
	 * Menu category slug => catering group. Filter to remap.
	 *
	 * @var array<string, string> $category_map
	 */
	$category_map = (array) apply_filters(
		'twistedoak_catering_product_categories',
		array(
			'proteins' => 'meats',
			'sides'    => 'sides',
			'desserts' => 'desserts',
		)
	);

	if ( function_exists( 'TwistedOak\Core\Menu\source' ) ) {
		// Preferred: go through the menu source abstraction (Local CPT today, Square later).
		$premium_titles = twistedoak_catering_lines( (string) twistedoak_catering_setting( 'premium_proteins' ) );
		foreach ( $category_map as $slug => $key ) {
			foreach ( \TwistedOak\Core\Menu\source()->items( (string) $slug ) as $item ) {
				$title = html_entity_decode( (string) $item['name'], ENT_QUOTES, 'UTF-8' );
				if ( 'meats' === $key && ( ! empty( $item['premium'] ) || in_array( $title, $premium_titles, true ) ) ) {
					$products['premiumMeats'][] = $title;
				} else {
					$products[ $key ][] = $title;
				}
			}
		}
	} elseif ( post_type_exists( 'tobbq_menu_item' ) && taxonomy_exists( 'tobbq_menu_category' ) ) {
		$premium_titles = twistedoak_catering_lines( (string) twistedoak_catering_setting( 'premium_proteins' ) );
		$groups         = array(
			'proteins' => 'meats',
			'sides'    => 'sides',
			'desserts' => 'desserts',
		);

		foreach ( $groups as $slug => $key ) {
			$term = get_term_by( 'slug', $slug, 'tobbq_menu_category' );
			if ( ! $term ) {
				$term = get_term_by( 'name', ucfirst( $slug ), 'tobbq_menu_category' );
			}
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$posts = get_posts(
				array(
					'post_type'        => 'tobbq_menu_item',
					'post_status'      => 'publish',
					'numberposts'      => 200,
					'orderby'          => 'title',
					'order'            => 'ASC',
					'suppress_filters' => false,
					'tax_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => 'tobbq_menu_category',
							'terms'    => array( $term->term_id ),
						),
					),
				)
			);

			foreach ( $posts as $post ) {
				$title = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
				if ( 'meats' === $key && ( get_post_meta( $post->ID, '_tobbq_premium', true ) || in_array( $title, $premium_titles, true ) ) ) {
					$products['premiumMeats'][] = $title;
				} else {
					$products[ $key ][] = $title;
				}
			}
		}
	}

	// If proteins came from the CPT at all, don't mix in fallback premium meats.
	if ( empty( $products['meats'] ) && empty( $products['premiumMeats'] ) ) {
		$products['meats']        = $fallback['meats'];
		$products['premiumMeats'] = $fallback['premiumMeats'];
	}
	foreach ( array( 'sides', 'desserts' ) as $key ) {
		if ( empty( $products[ $key ] ) ) {
			$products[ $key ] = $fallback[ $key ];
		}
	}

	foreach ( $products as &$list ) {
		$list = array_values( array_unique( $list ) );
		sort( $list );
	}
	unset( $list );

	$cache = (array) apply_filters( 'twistedoak_catering_products', $products );
	return $cache;
}
