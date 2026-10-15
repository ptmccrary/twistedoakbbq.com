<?php
/**
 * Private `tobbq_catering_lead` post type: admin list columns + read-only meta box.
 *
 * @package TwistedOakCatering
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const TWISTEDOAK_CATERING_CPT = 'tobbq_catering_lead';

/**
 * Lead fields stored as `_tobbq_lead_{key}` post meta, with admin labels.
 *
 * @return array<string, string>
 */
function twistedoak_catering_lead_fields(): array {
	return array(
		'reference'     => __( 'Reference', 'twisted-oak-catering' ),
		'status'        => __( 'Status', 'twisted-oak-catering' ),
		'name'          => __( 'Name', 'twisted-oak-catering' ),
		'email'         => __( 'Email', 'twisted-oak-catering' ),
		'phone'         => __( 'Phone', 'twisted-oak-catering' ),
		'event_date'    => __( 'Event date', 'twisted-oak-catering' ),
		'start_time'    => __( 'Start time', 'twisted-oak-catering' ),
		'duration'      => __( 'Service window', 'twisted-oak-catering' ),
		'guests'        => __( 'Guests', 'twisted-oak-catering' ),
		'service_style' => __( 'Service style', 'twisted-oak-catering' ),
		'location'      => __( 'Event location', 'twisted-oak-catering' ),
		'event_type'    => __( 'Event type', 'twisted-oak-catering' ),
		'package'       => __( 'Package', 'twisted-oak-catering' ),
		'meats'         => __( 'Proteins', 'twisted-oak-catering' ),
		'sides'         => __( 'Sides', 'twisted-oak-catering' ),
		'desserts'      => __( 'Desserts', 'twisted-oak-catering' ),
		'message'       => __( 'Message', 'twisted-oak-catering' ),
		'source_url'    => __( 'Submitted from', 'twisted-oak-catering' ),
		'mail_sent'     => __( 'Email notification', 'twisted-oak-catering' ),
	);
}

function twistedoak_catering_register_post_type(): void {
	register_post_type(
		TWISTEDOAK_CATERING_CPT,
		array(
			'labels'              => array(
				'name'               => __( 'Catering Bookings', 'twisted-oak-catering' ),
				'singular_name'      => __( 'Catering Booking', 'twisted-oak-catering' ),
				'menu_name'          => __( 'Catering', 'twisted-oak-catering' ),
				'all_items'          => __( 'Bookings', 'twisted-oak-catering' ),
				'edit_item'          => __( 'Booking', 'twisted-oak-catering' ),
				'view_item'          => __( 'View Booking', 'twisted-oak-catering' ),
				'search_items'       => __( 'Search Bookings', 'twisted-oak-catering' ),
				'not_found'          => __( 'No bookings yet.', 'twisted-oak-catering' ),
				'not_found_in_trash' => __( 'No bookings in Trash.', 'twisted-oak-catering' ),
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'menu_position'       => 26,
			'menu_icon'           => 'dashicons-food',
			'supports'            => array( 'title' ),
			'map_meta_cap'        => true,
			'capability_type'     => 'post',
			'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
		)
	);
}
add_action( 'init', 'twistedoak_catering_register_post_type' );

/**
 * Read a lead meta value as display text.
 */
function twistedoak_catering_lead_value( int $post_id, string $key ): string {
	$value = get_post_meta( $post_id, '_tobbq_lead_' . $key, true );
	if ( is_array( $value ) ) {
		return implode( ', ', array_map( 'strval', $value ) );
	}
	if ( 'event_date' === $key && is_string( $value ) && '' !== $value ) {
		$ts = strtotime( $value );
		return $ts ? wp_date( (string) get_option( 'date_format' ), $ts, new DateTimeZone( 'UTC' ) ) : $value;
	}
	if ( 'status' === $key ) {
		$value = '' === (string) $value ? 'pending' : (string) $value;
		return twistedoak_catering_statuses()[ $value ] ?? $value;
	}
	if ( 'start_time' === $key && is_string( $value ) && '' !== $value ) {
		$duration = (int) get_post_meta( $post_id, '_tobbq_lead_duration', true );
		return $duration ? twistedoak_catering_window_label( $value, $duration ) : twistedoak_catering_format_time( $value );
	}
	if ( 'duration' === $key ) {
		$minutes = (int) $value;
		/* translators: %s: hours, e.g. 2.5 */
		return $minutes ? sprintf( __( '%s hours', 'twisted-oak-catering' ), rtrim( rtrim( number_format( $minutes / 60, 2, '.', '' ), '0' ), '.' ) ) : '';
	}
	if ( 'mail_sent' === $key ) {
		return '1' === (string) $value ? __( 'Sent', 'twisted-oak-catering' ) : __( 'Failed', 'twisted-oak-catering' );
	}
	return (string) $value;
}

/* ---------- List table ---------- */

add_filter(
	'manage_' . TWISTEDOAK_CATERING_CPT . '_posts_columns',
	static function ( array $columns ): array {
		return array(
			'cb'                => $columns['cb'] ?? '',
			'title'             => __( 'Booking', 'twisted-oak-catering' ),
			'tobbq_status'      => __( 'Status', 'twisted-oak-catering' ),
			'tobbq_reference'   => __( 'Ref', 'twisted-oak-catering' ),
			'tobbq_email'       => __( 'Email', 'twisted-oak-catering' ),
			'tobbq_event_date'  => __( 'Event date', 'twisted-oak-catering' ),
			'tobbq_start_time'  => __( 'Time', 'twisted-oak-catering' ),
			'tobbq_guests'      => __( 'Guests', 'twisted-oak-catering' ),
			'tobbq_package'     => __( 'Package', 'twisted-oak-catering' ),
			'date'              => __( 'Received', 'twisted-oak-catering' ),
		);
	}
);

add_action(
	'manage_' . TWISTEDOAK_CATERING_CPT . '_posts_custom_column',
	static function ( string $column, int $post_id ): void {
		if ( ! str_starts_with( $column, 'tobbq_' ) ) {
			return;
		}
		$key   = substr( $column, 6 );
		$value = twistedoak_catering_lead_value( $post_id, $key );
		if ( 'email' === $key && is_email( $value ) ) {
			printf( '<a href="%s">%s</a>', esc_url( 'mailto:' . $value ), esc_html( $value ) );
			return;
		}
		if ( 'status' === $key ) {
			$slug = (string) get_post_meta( $post_id, '_tobbq_lead_status', true );
			printf( '<span class="tobbq-status tobbq-status--%s">%s</span>', esc_attr( '' !== $slug ? $slug : 'pending' ), esc_html( $value ) );
			return;
		}
		echo esc_html( '' !== $value ? $value : '—' );
	},
	10,
	2
);

add_filter(
	'manage_edit-' . TWISTEDOAK_CATERING_CPT . '_sortable_columns',
	static function ( array $columns ): array {
		$columns['tobbq_event_date'] = 'tobbq_event_date';
		$columns['tobbq_guests']     = 'tobbq_guests';
		return $columns;
	}
);

add_action(
	'pre_get_posts',
	static function ( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || TWISTEDOAK_CATERING_CPT !== $query->get( 'post_type' ) ) {
			return;
		}
		$orderby = $query->get( 'orderby' );
		if ( 'tobbq_event_date' === $orderby ) {
			$query->set( 'meta_key', '_tobbq_lead_event_date' );
			$query->set( 'orderby', 'meta_value' );
		} elseif ( 'tobbq_guests' === $orderby ) {
			$query->set( 'meta_key', '_tobbq_lead_guests' );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}
);

// Leads are read-only: drop "Quick Edit", keep Trash.
add_filter(
	'post_row_actions',
	static function ( array $actions, WP_Post $post ): array {
		if ( TWISTEDOAK_CATERING_CPT === $post->post_type ) {
			unset( $actions['inline hide-if-no-js'] );
			if ( isset( $actions['edit'] ) ) {
				$actions['edit'] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( (string) get_edit_post_link( $post->ID ) ),
					esc_html__( 'View', 'twisted-oak-catering' )
				);
			}
		}
		return $actions;
	},
	10,
	2
);

add_filter(
	'bulk_actions-edit-' . TWISTEDOAK_CATERING_CPT,
	static function ( array $actions ): array {
		unset( $actions['edit'] );
		return $actions;
	}
);

/* ---------- Edit screen (read-only) ---------- */

add_action(
	'add_meta_boxes_' . TWISTEDOAK_CATERING_CPT,
	static function (): void {
		remove_meta_box( 'submitdiv', TWISTEDOAK_CATERING_CPT, 'side' );
		remove_meta_box( 'slugdiv', TWISTEDOAK_CATERING_CPT, 'normal' );
		add_meta_box(
			'tobbq-catering-lead-details',
			__( 'Booking details', 'twisted-oak-catering' ),
			'twistedoak_catering_render_lead_meta_box',
			TWISTEDOAK_CATERING_CPT,
			'normal',
			'high'
		);
		add_meta_box(
			'tobbq-catering-lead-actions',
			__( 'Customer', 'twisted-oak-catering' ),
			'twistedoak_catering_render_lead_actions_box',
			TWISTEDOAK_CATERING_CPT,
			'side',
			'default'
		);
	}
);

function twistedoak_catering_render_lead_meta_box( WP_Post $post ): void {
	echo '<table class="widefat striped" role="presentation"><tbody>';
	foreach ( twistedoak_catering_lead_fields() as $key => $label ) {
		$value = twistedoak_catering_lead_value( $post->ID, $key );
		echo '<tr><th scope="row" style="width:12rem">' . esc_html( $label ) . '</th><td>';
		if ( '' === $value ) {
			echo '—';
		} elseif ( 'email' === $key && is_email( $value ) ) {
			printf( '<a href="%s">%s</a>', esc_url( 'mailto:' . $value ), esc_html( $value ) );
		} elseif ( 'source_url' === $key ) {
			printf( '<a href="%s">%s</a>', esc_url( $value ), esc_html( $value ) );
		} else {
			echo nl2br( esc_html( $value ) );
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}

function twistedoak_catering_render_lead_actions_box( WP_Post $post ): void {
	printf(
		'<p>%s<br><strong>%s</strong></p>',
		esc_html__( 'Received', 'twisted-oak-catering' ),
		esc_html( get_the_date( (string) get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $post ) )
	);
	$email = twistedoak_catering_lead_value( $post->ID, 'email' );
	if ( is_email( $email ) ) {
		printf(
			'<p><a class="button button-primary" href="%s">%s</a></p>',
			esc_url( 'mailto:' . $email . '?subject=' . rawurlencode( __( 'Your Twisted Oak BBQ catering inquiry', 'twisted-oak-catering' ) ) ),
			esc_html__( 'Reply by email', 'twisted-oak-catering' )
		);
	}
	if ( current_user_can( 'delete_post', $post->ID ) ) {
		printf(
			'<p><a class="submitdelete deletion" href="%s">%s</a></p>',
			esc_url( (string) get_delete_post_link( $post->ID ) ),
			esc_html__( 'Move to Trash', 'twisted-oak-catering' )
		);
	}
}

// Title is generated; make the title field read-only on the edit screen.
add_action(
	'edit_form_after_title',
	static function ( WP_Post $post ): void {
		if ( TWISTEDOAK_CATERING_CPT === $post->post_type ) {
			echo '<script>document.getElementById("title")?.setAttribute("readonly","readonly");</script>';
		}
	}
);
