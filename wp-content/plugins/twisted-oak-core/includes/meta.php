<?php
/**
 * Post and term meta registration (the data contract).
 *
 * Menu item: _tobbq_price, _tobbq_unit, _tobbq_featured, _tobbq_premium, _tobbq_catering_only, _tobbq_seasonal,
 *            _tobbq_sold_out (bool), _tobbq_square_id (Square catalog ITEM id).
 * Event:     _tobbq_event_start, _tobbq_event_end (Y-m-d H:i), _tobbq_event_location, _tobbq_event_link.
 * Menu category term: order (int), square_id (Square CATEGORY id).
 *
 * @package TwistedOakCore
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field definitions shared by meta registration, meta boxes and the importer.
 *
 * @return array<string, array<string, array{type: string, label: string, sanitize: callable}>>
 */
function tobbq_core_meta_fields(): array {
	return array(
		'tobbq_menu_item' => array(
			'_tobbq_price'         => array(
				'type'     => 'string',
				'label'    => __( 'Price', 'twisted-oak-core' ),
				'sanitize' => 'tobbq_core_sanitize_price',
			),
			'_tobbq_unit'          => array(
				'type'     => 'string',
				'label'    => __( 'Unit', 'twisted-oak-core' ),
				'sanitize' => 'sanitize_text_field',
			),
			'_tobbq_featured'      => array(
				'type'     => 'boolean',
				'label'    => __( 'Featured', 'twisted-oak-core' ),
				'sanitize' => 'rest_sanitize_boolean',
			),
			'_tobbq_premium'       => array(
				'type'     => 'boolean',
				'label'    => __( 'Premium (extra charge for catering)', 'twisted-oak-core' ),
				'sanitize' => 'rest_sanitize_boolean',
			),
			'_tobbq_catering_only' => array(
				'type'     => 'boolean',
				'label'    => __( 'Catering only', 'twisted-oak-core' ),
				'sanitize' => 'rest_sanitize_boolean',
			),
			'_tobbq_seasonal'      => array(
				'type'     => 'boolean',
				'label'    => __( 'Seasonal (only available in season)', 'twisted-oak-core' ),
				'sanitize' => 'rest_sanitize_boolean',
			),
			'_tobbq_sold_out'      => array(
				'type'     => 'boolean',
				'label'    => __( 'Sold out', 'twisted-oak-core' ),
				'sanitize' => 'rest_sanitize_boolean',
			),
			'_tobbq_square_id'     => array(
				'type'     => 'string',
				'label'    => __( 'Square item ID', 'twisted-oak-core' ),
				'sanitize' => 'tobbq_core_sanitize_square_id',
			),
		),
		'tobbq_event'     => array(
			'_tobbq_event_start'    => array(
				'type'     => 'string',
				'label'    => __( 'Starts', 'twisted-oak-core' ),
				'sanitize' => 'tobbq_core_sanitize_datetime',
			),
			'_tobbq_event_end'      => array(
				'type'     => 'string',
				'label'    => __( 'Ends', 'twisted-oak-core' ),
				'sanitize' => 'tobbq_core_sanitize_datetime',
			),
			'_tobbq_event_location' => array(
				'type'     => 'string',
				'label'    => __( 'Location', 'twisted-oak-core' ),
				'sanitize' => 'sanitize_text_field',
			),
			'_tobbq_event_link'     => array(
				'type'     => 'string',
				'label'    => __( 'External link (tickets, info)', 'twisted-oak-core' ),
				'sanitize' => 'esc_url_raw',
			),
		),
	);
}

/**
 * Registers post meta and term meta with REST exposure and edit auth.
 */
function tobbq_core_register_meta(): void {
	foreach ( tobbq_core_meta_fields() as $post_type => $fields ) {
		foreach ( $fields as $key => $field ) {
			register_post_meta(
				$post_type,
				$key,
				array(
					'type'              => $field['type'],
					'description'       => $field['label'],
					'single'            => true,
					'default'           => 'boolean' === $field['type'] ? false : '',
					'show_in_rest'      => true,
					'sanitize_callback' => $field['sanitize'],
					'auth_callback'     => static function ( $allowed, $meta_key, $post_id ): bool {
						return current_user_can( 'edit_post', (int) $post_id );
					},
				)
			);
		}
	}

	register_term_meta(
		'tobbq_menu_category',
		'square_id',
		array(
			'type'              => 'string',
			'description'       => __( 'Square catalog CATEGORY id.', 'twisted-oak-core' ),
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'tobbq_core_sanitize_square_id',
			'auth_callback'     => static fn(): bool => current_user_can( 'manage_categories' ),
		)
	);

	register_term_meta(
		'tobbq_menu_category',
		'order',
		array(
			'type'              => 'integer',
			'description'       => __( 'Sort order on the menu page (lower first).', 'twisted-oak-core' ),
			'single'            => true,
			'default'           => 0,
			'show_in_rest'      => true,
			'sanitize_callback' => static fn( $value ): int => (int) $value,
			'auth_callback'     => static fn(): bool => current_user_can( 'manage_categories' ),
		)
	);
}

/**
 * Price: a decimal ("14.50") or free text such as "Market".
 *
 * @param mixed $value Raw value.
 */
function tobbq_core_sanitize_price( $value ): string {
	$value = trim( sanitize_text_field( (string) $value ) );
	$value = ltrim( $value, '$' );
	if ( is_numeric( $value ) ) {
		return number_format( (float) $value, 2, '.', '' );
	}
	return $value;
}

/**
 * Normalises to "Y-m-d H:i" (accepts datetime-local "Y-m-dTH:i" and other strtotime input).
 *
 * @param mixed $value Raw value.
 */
function tobbq_core_sanitize_datetime( $value ): string {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	$date = date_create_immutable( str_replace( 'T', ' ', $value ), wp_timezone() );
	return $date ? $date->format( 'Y-m-d H:i' ) : '';
}

/**
 * Square catalog ids are uppercase alphanumerics (plus - and _ for safety).
 *
 * @param mixed $value Raw value.
 */
function tobbq_core_sanitize_square_id( $value ): string {
	return (string) preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $value );
}
