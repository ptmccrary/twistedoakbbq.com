<?php
/**
 * Post types and taxonomies.
 *
 * @package TwistedOakCore
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers CPTs `tobbq_menu_item`, `tobbq_event` and their taxonomies.
 */
function tobbq_core_register_content_types(): void {
	// Taxonomies first so their rewrite rules (menu/category/...) take precedence
	// over the CPT's single/attachment rules under the same /menu/ base.
	register_taxonomy(
		'tobbq_menu_category',
		array( 'tobbq_menu_item' ),
		array(
			'labels'            => array(
				'name'          => __( 'Menu Categories', 'twisted-oak-core' ),
				'singular_name' => __( 'Menu Category', 'twisted-oak-core' ),
				'add_new_item'  => __( 'Add New Menu Category', 'twisted-oak-core' ),
				'edit_item'     => __( 'Edit Menu Category', 'twisted-oak-core' ),
				'all_items'     => __( 'All Menu Categories', 'twisted-oak-core' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array(
				'slug'       => 'menu/category',
				'with_front' => false,
			),
		)
	);

	register_taxonomy(
		'tobbq_dietary',
		array( 'tobbq_menu_item' ),
		array(
			'labels'            => array(
				'name'          => __( 'Dietary Tags', 'twisted-oak-core' ),
				'singular_name' => __( 'Dietary Tag', 'twisted-oak-core' ),
				'add_new_item'  => __( 'Add New Dietary Tag', 'twisted-oak-core' ),
				'edit_item'     => __( 'Edit Dietary Tag', 'twisted-oak-core' ),
			),
			'hierarchical'      => false,
			'public'            => true,
			'publicly_queryable' => false,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => false,
		)
	);

	register_taxonomy(
		'tobbq_event_type',
		array( 'tobbq_event' ),
		array(
			'labels'            => array(
				'name'          => __( 'Event Types', 'twisted-oak-core' ),
				'singular_name' => __( 'Event Type', 'twisted-oak-core' ),
				'add_new_item'  => __( 'Add New Event Type', 'twisted-oak-core' ),
				'edit_item'     => __( 'Edit Event Type', 'twisted-oak-core' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array(
				'slug'       => 'events/type',
				'with_front' => false,
			),
		)
	);

	register_post_type(
		'tobbq_menu_item',
		array(
			'labels'        => array(
				'name'               => __( 'Menu', 'twisted-oak-core' ),
				'singular_name'      => __( 'Menu Item', 'twisted-oak-core' ),
				'add_new_item'       => __( 'Add New Menu Item', 'twisted-oak-core' ),
				'edit_item'          => __( 'Edit Menu Item', 'twisted-oak-core' ),
				'new_item'           => __( 'New Menu Item', 'twisted-oak-core' ),
				'view_item'          => __( 'View Menu Item', 'twisted-oak-core' ),
				'search_items'       => __( 'Search Menu Items', 'twisted-oak-core' ),
				'not_found'          => __( 'No menu items found.', 'twisted-oak-core' ),
				'not_found_in_trash' => __( 'No menu items found in Trash.', 'twisted-oak-core' ),
				'all_items'          => __( 'All Menu Items', 'twisted-oak-core' ),
				'archives'           => __( 'Menu', 'twisted-oak-core' ),
				'menu_name'          => __( 'Food Menu', 'twisted-oak-core' ),
			),
			'public'        => true,
			'show_in_rest'  => true,
			'has_archive'   => 'menu',
			'rewrite'       => array(
				'slug'       => 'menu',
				'with_front' => false,
			),
			'menu_icon'     => 'dashicons-food',
			'menu_position' => 20,
			'hierarchical'  => false,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'page-attributes', 'custom-fields', 'revisions' ),
		)
	);

	register_post_type(
		'tobbq_event',
		array(
			'labels'        => array(
				'name'               => __( 'Events', 'twisted-oak-core' ),
				'singular_name'      => __( 'Event', 'twisted-oak-core' ),
				'add_new_item'       => __( 'Add New Event', 'twisted-oak-core' ),
				'edit_item'          => __( 'Edit Event', 'twisted-oak-core' ),
				'new_item'           => __( 'New Event', 'twisted-oak-core' ),
				'view_item'          => __( 'View Event', 'twisted-oak-core' ),
				'search_items'       => __( 'Search Events', 'twisted-oak-core' ),
				'not_found'          => __( 'No events found.', 'twisted-oak-core' ),
				'not_found_in_trash' => __( 'No events found in Trash.', 'twisted-oak-core' ),
				'all_items'          => __( 'All Events', 'twisted-oak-core' ),
				'archives'           => __( 'Events', 'twisted-oak-core' ),
			),
			'public'        => true,
			'show_in_rest'  => true,
			'has_archive'   => 'events',
			'rewrite'       => array(
				'slug'       => 'events',
				'with_front' => false,
			),
			'menu_icon'     => 'dashicons-calendar-alt',
			'menu_position' => 21,
			'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
		)
	);
}
