<?php
/**
 * Imports Twisted Oak content into WordPress. Idempotent: re-running updates
 * existing posts (matched by _tobbq_source_id) instead of duplicating them.
 *
 * - Menu items: the real Drupal `product` nodes, exported to data/drupal-products.json via
 *     docker exec twisted-oak-bbq vendor/bin/drush php:eval '...'   (see README note in the plugin)
 * - Menu categories: Drupal `product_category` terms with their weights → term meta `order`.
 * - Dietary tags + event types: Drupal vocabularies (fixed lists below).
 * - Events: not created (the live site lists none; events are client-managed).
 *
 * Usage (from the WP root inside the container):
 *   wp eval-file wp-content/plugins/twisted-oak-core/tools/import.php
 *
 * @package TwistedOakCore
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) || ! function_exists( 'tobbq_core_register_content_types' ) ) {
	echo "Run with `wp eval-file` with the twisted-oak-core plugin active.\n";
	return;
}

$tobbq_log = static function ( string $msg ): void {
	if ( class_exists( 'WP_CLI' ) ) {
		WP_CLI::log( $msg );
	} else {
		echo $msg . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
};

/**
 * Ensures a term exists; returns its ID.
 */
$tobbq_term = static function ( string $name, string $taxonomy, ?string $slug = null ): int {
	$slug     = $slug ?? sanitize_title( $name );
	$existing = get_term_by( 'slug', $slug, $taxonomy );
	if ( $existing instanceof WP_Term ) {
		return (int) $existing->term_id;
	}
	$result = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
	if ( is_wp_error( $result ) ) {
		throw new RuntimeException( esc_html( $result->get_error_message() ) );
	}
	return (int) $result['term_id'];
};

/**
 * Creates or updates a post keyed by a stable source ID.
 *
 * @param array<string,mixed> $postarr  wp_insert_post args.
 * @param array<string,mixed> $meta     Meta to set.
 * @param array<string,int[]> $terms    taxonomy => term IDs.
 */
$tobbq_upsert = static function ( string $source_id, array $postarr, array $meta, array $terms ): array {
	$found = get_posts(
		array(
			'post_type'      => $postarr['post_type'],
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_tobbq_source_id', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $source_id, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	$action = 'created';
	if ( $found ) {
		$postarr['ID'] = (int) $found[0];
		$action        = 'updated';
	}
	$post_id = wp_insert_post( wp_slash( $postarr ), true );
	if ( is_wp_error( $post_id ) ) {
		throw new RuntimeException( esc_html( $post_id->get_error_message() ) );
	}
	update_post_meta( $post_id, '_tobbq_source_id', $source_id );
	foreach ( $meta as $key => $value ) {
		update_post_meta( $post_id, $key, $value );
	}
	foreach ( $terms as $taxonomy => $ids ) {
		wp_set_object_terms( $post_id, $ids, $taxonomy );
	}
	return array( (int) $post_id, $action );
};

// Dietary tags (slugs per the data contract).
$tobbq_dietary = array();
foreach ( array( 'Vegan', 'Vegetarian', 'Gluten-Free', 'Dairy-Free', 'Contains Pork', 'Contains Nuts' ) as $tobbq_name ) {
	$tobbq_dietary[ $tobbq_name ] = $tobbq_term( $tobbq_name, 'tobbq_dietary' );
}

// Menu categories with Drupal weights.
$tobbq_categories = array();
foreach ( array( 'Proteins' => 0, 'Sides' => 1, 'Desserts' => 2, 'Sauces' => 3, 'Extras' => 4 ) as $tobbq_name => $tobbq_weight ) {
	$tobbq_id = $tobbq_term( $tobbq_name, 'tobbq_menu_category' );
	update_term_meta( $tobbq_id, 'order', $tobbq_weight );
	$tobbq_categories[ $tobbq_name ] = $tobbq_id;
}

// Event types.
$tobbq_event_types = array();
foreach ( array( 'Corporate', 'Festival', 'Pop-up', 'Private Booking', 'Special', 'Wedding' ) as $tobbq_name ) {
	$tobbq_event_types[ $tobbq_name ] = $tobbq_term( $tobbq_name, 'tobbq_event_type' );
}

// Menu items from Drupal.
$tobbq_json     = (string) file_get_contents( __DIR__ . '/data/drupal-products.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
$tobbq_products = json_decode( $tobbq_json, true, 512, JSON_THROW_ON_ERROR );
$tobbq_featured = array( 'Texas Style Brisket', 'Pulled Pork', 'St Louis Cut Ribs', 'White Cheddar Mac and Cheese' );
$tobbq_counts   = array(
	'created' => 0,
	'updated' => 0,
);

// Drupal weights are all 0 (so Drupal fell back to A–Z); keep the editor's entry
// order (nid order) within each weight instead, so Brisket leads Proteins.
foreach ( $tobbq_products as $tobbq_pos => $tobbq_p ) {
	$tobbq_terms = array(
		'tobbq_menu_category' => isset( $tobbq_categories[ $tobbq_p['category'] ] ) ? array( $tobbq_categories[ $tobbq_p['category'] ] ) : array(),
		'tobbq_dietary'       => array_values( array_intersect_key( $tobbq_dietary, array_flip( $tobbq_p['dietary'] ) ) ),
	);
	[ $tobbq_id, $tobbq_action ] = $tobbq_upsert(
		'drupal:node:' . $tobbq_p['nid'],
		array(
			'post_type'    => 'tobbq_menu_item',
			'post_status'  => $tobbq_p['status'] ? 'publish' : 'draft',
			'post_title'   => $tobbq_p['title'],
			'post_name'    => sanitize_title( $tobbq_p['title'] ),
			'post_content' => '<!-- wp:paragraph --><p>' . esc_html( trim( (string) $tobbq_p['description'] ) ) . '</p><!-- /wp:paragraph -->',
			'post_excerpt' => trim( (string) $tobbq_p['description'] ),
			'menu_order'   => (int) $tobbq_p['weight'] * 100 + (int) $tobbq_pos,
		),
		array(
			'_tobbq_price'         => (string) ( $tobbq_p['price'] ?? '' ),
			'_tobbq_unit'          => '',
			'_tobbq_featured'      => in_array( $tobbq_p['title'], $tobbq_featured, true ),
			'_tobbq_premium'       => (bool) $tobbq_p['premium'],
			'_tobbq_catering_only' => (bool) $tobbq_p['catering_only'],
		),
		$tobbq_terms
	);
	++$tobbq_counts[ $tobbq_action ];
}
$tobbq_log( sprintf( 'Menu items: %d created, %d updated.', $tobbq_counts['created'], $tobbq_counts['updated'] ) );

// Events: deliberately not seeded (the live site lists none; events are client-managed content).
