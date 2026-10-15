<?php
/**
 * Admin list-table columns: price for menu items, start date for events,
 * order for menu categories.
 *
 * @package TwistedOakCore
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inserts a column after "title".
 *
 * @param array<string,string> $columns Columns.
 * @param string               $key     New key.
 * @param string               $label   New label.
 * @return array<string,string>
 */
function tobbq_core_insert_column( array $columns, string $key, string $label ): array {
	$out = array();
	foreach ( $columns as $k => $v ) {
		$out[ $k ] = $v;
		if ( 'title' === $k || 'name' === $k ) {
			$out[ $key ] = $label;
		}
	}
	return $out;
}

add_filter( 'manage_tobbq_menu_item_posts_columns', static fn( array $c ): array => tobbq_core_insert_column( $c, 'tobbq_price', __( 'Price', 'twisted-oak-core' ) ) );
add_filter( 'manage_tobbq_event_posts_columns', static fn( array $c ): array => tobbq_core_insert_column( $c, 'tobbq_event_start', __( 'Starts', 'twisted-oak-core' ) ) );
add_filter( 'manage_edit-tobbq_menu_category_columns', static fn( array $c ): array => tobbq_core_insert_column( $c, 'tobbq_order', __( 'Order', 'twisted-oak-core' ) ) );

add_action(
	'manage_tobbq_menu_item_posts_custom_column',
	static function ( string $column, int $post_id ): void {
		if ( 'tobbq_price' !== $column ) {
			return;
		}
		$price = (string) get_post_meta( $post_id, '_tobbq_price', true );
		$unit  = (string) get_post_meta( $post_id, '_tobbq_unit', true );
		echo '' === $price ? '—' : esc_html( trim( ( is_numeric( $price ) ? '$' : '' ) . $price . ' ' . $unit ) );
		if ( get_post_meta( $post_id, '_tobbq_featured', true ) ) {
			echo ' <span class="dashicons dashicons-star-filled" title="' . esc_attr__( 'Featured', 'twisted-oak-core' ) . '"></span>';
		}
		if ( get_post_meta( $post_id, '_tobbq_sold_out', true ) ) {
			echo ' <strong style="color:#b32d2e">' . esc_html__( 'Sold out', 'twisted-oak-core' ) . '</strong>';
		}
	},
	10,
	2
);

add_action(
	'manage_tobbq_event_posts_custom_column',
	static function ( string $column, int $post_id ): void {
		if ( 'tobbq_event_start' === $column ) {
			$start = (string) get_post_meta( $post_id, '_tobbq_event_start', true );
			echo $start ? esc_html( $start ) : '—';
		}
	},
	10,
	2
);

add_filter(
	'manage_tobbq_menu_category_custom_column',
	static function ( string $out, string $column, int $term_id ): string {
		return 'tobbq_order' === $column ? esc_html( (string) (int) get_term_meta( $term_id, 'order', true ) ) : $out;
	},
	10,
	3
);

add_filter(
	'manage_edit-tobbq_event_sortable_columns',
	static function ( array $columns ): array {
		$columns['tobbq_event_start'] = 'tobbq_event_start';
		return $columns;
	}
);

add_action(
	'pre_get_posts',
	static function ( WP_Query $query ): void {
		if ( is_admin() && $query->is_main_query() && 'tobbq_event_start' === $query->get( 'orderby' ) ) {
			$query->set( 'meta_key', '_tobbq_event_start' );
			$query->set( 'orderby', 'meta_value' );
		}
	}
);
