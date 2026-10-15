<?php
/**
 * Booking status workflow + public config/DTO helpers.
 *
 * @package TwistedOakCatering
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Change a booking's status, log it, fire `twistedoak_catering_status_changed`,
 * and (optionally) email the customer for confirmed / declined / cancelled.
 *
 * @return bool True when the status changed.
 */
function twistedoak_catering_set_status( int $id, string $status, bool $notify = true, string $note = '' ): bool {
	$status = 'tentative' === $status ? 'held' : $status; // v2.0 alias.
	if ( TWISTEDOAK_CATERING_CPT !== get_post_type( $id ) || ! array_key_exists( $status, twistedoak_catering_statuses() ) ) {
		return false;
	}
	$old = (string) get_post_meta( $id, '_tobbq_lead_status', true );
	$old = '' !== $old ? $old : 'pending';
	if ( $old === $status ) {
		return false;
	}
	update_post_meta( $id, '_tobbq_lead_status', $status );

	$log   = get_post_meta( $id, '_tobbq_lead_status_log', true );
	$log   = is_array( $log ) ? $log : array();
	$log[] = array(
		'time' => time(),
		'from' => $old,
		'to'   => $status,
		'user' => get_current_user_id(),
	);
	update_post_meta( $id, '_tobbq_lead_status_log', $log );

	do_action( 'twistedoak_catering_status_changed', $id, $status, $old, $note );

	$emails = (array) apply_filters(
		'twistedoak_catering_status_emails',
		array(
			'confirmed' => 'customer_confirmed',
			'declined'  => 'customer_declined',
			'cancelled' => 'customer_cancelled',
		)
	);
	if ( $notify && isset( $emails[ $status ] ) ) {
		twistedoak_catering_send_email( (string) $emails[ $status ], $id, array( 'note' => $note ) );
	}
	return true;
}

/**
 * Would this booking exceed capacity if it consumed its slot? Used to warn
 * admins (they may still overbook on purpose).
 */
function twistedoak_catering_booking_conflicts( int $id ): bool {
	$b = twistedoak_catering_booking( $id );
	if ( ! twistedoak_catering_is_date( $b['event_date'] ) ) {
		return false;
	}
	$day = twistedoak_catering_day_availability(
		(string) $b['event_date'],
		(int) $b['guests'],
		array(
			'admin'   => true,
			'exclude' => $id,
			'fresh'   => true,
		)
	);
	if ( ! $day['bookable'] ) {
		return true;
	}
	$time = twistedoak_catering_normalise_time( (string) $b['start_time'] );
	if ( '' === $time ) {
		return false;
	}
	$slot = twistedoak_catering_find_slot( $day, $time );
	return $slot ? 'available' !== $slot['status'] : false; // Custom (off-grid) times only use day capacity.
}

/**
 * Public booking summary returned by POST /bookings.
 *
 * @return array<string, mixed>
 */
function twistedoak_catering_public_booking( int $id ): array {
	$b     = twistedoak_catering_booking( $id );
	$times = twistedoak_catering_booking_times( $b );
	return array(
		'reference'     => (string) $b['reference'],
		'status'          => twistedoak_catering_public_status( (string) $b['status'] ),
		'public_status'   => twistedoak_catering_public_status( (string) $b['status'] ),
		'status_internal' => (string) $b['status'],
		'status_label'    => twistedoak_catering_lead_value( $id, 'status' ),
		'event_date'    => (string) $b['event_date'],
		'start_time'    => (string) $b['start_time'],
		'end_time'      => $times && ! $times[2] ? $times[1]->format( 'H:i' ) : '',
		'label'         => trim( twistedoak_catering_lead_value( $id, 'event_date' ) . ( '' !== (string) $b['start_time'] ? ', ' . twistedoak_catering_lead_value( $id, 'start_time' ) : '' ) ),
		'guests'        => (int) $b['guests'],
		'package'       => (string) $b['package'],
		'service_style' => (string) $b['service_style'],
		'ics_url'       => $times ? twistedoak_catering_ics_url( $id ) : '',
	);
}

/**
 * Config the front-end needs (localized as window.twistedoakCatering and
 * served by GET /twisted-oak/v1/catering/config).
 *
 * @param bool $with_nonces Include nonces (only when printing into a page).
 * @return array<string, mixed>
 */
function twistedoak_catering_public_config( bool $with_nonces = true ): array {
	$s      = twistedoak_catering_schedule();
	$config = array(
		'restRoot'        => esc_url_raw( rest_url( 'twisted-oak/v1/' ) ),
		'endpoints'       => array(
			'availability' => esc_url_raw( rest_url( 'twisted-oak/v1/availability' ) ),
			'bookings'     => esc_url_raw( rest_url( 'twisted-oak/v1/bookings' ) ),
			'config'       => esc_url_raw( rest_url( 'twisted-oak/v1/catering/config' ) ),
			'legacy'       => esc_url_raw( rest_url( 'twisted-oak/v1/catering' ) ),
			'fallback'     => esc_url_raw( admin_url( 'admin-post.php' ) ),
		),
		'nonceField'      => 'tobbq_catering_nonce',
		'honeypotField'   => 'website',
		'timezone'        => wp_timezone_string(),
		'today'           => twistedoak_catering_today()->format( 'Y-m-d' ),
		'earliest'        => twistedoak_catering_earliest_date()->format( 'Y-m-d' ),
		'latest'          => twistedoak_catering_latest_date()->format( 'Y-m-d' ),
		'weekStartsOn'    => (int) get_option( 'start_of_week', 0 ),
		'weekdays'        => $s['weekdays'],
		'leadDays'        => (int) $s['lead_days'],
		'horizonMonths'   => (int) $s['horizon_months'],
		'minGuests'       => (int) $s['min_guests'],
		'maxGuests'       => (int) $s['max_guests'],
		'packages'        => twistedoak_catering_packages(),
		'premiumPackages' => twistedoak_catering_premium_packages(),
		'serviceStyles'   => twistedoak_catering_service_styles(),
		'bookingStatuses' => array(
			'pending'   => __( 'Requested', 'twisted-oak-catering' ),
			'held'      => __( 'On hold', 'twisted-oak-catering' ),
			'confirmed' => __( 'Confirmed', 'twisted-oak-catering' ),
			'declined'  => __( 'Declined', 'twisted-oak-catering' ),
		),
		'products'        => twistedoak_catering_products(),
		'dayStatuses'     => array(
			'available'      => __( 'Available', 'twisted-oak-catering' ),
			'limited'        => __( 'Limited availability', 'twisted-oak-catering' ),
			'full'           => __( 'Fully booked', 'twisted-oak-catering' ),
			'blackout'       => __( 'Unavailable', 'twisted-oak-catering' ),
			'closed'         => __( 'Closed', 'twisted-oak-catering' ),
			'past'           => __( 'Past', 'twisted-oak-catering' ),
			'too-soon'       => __( 'Too soon to book', 'twisted-oak-catering' ),
			'beyond-horizon' => __( 'Not yet open for booking', 'twisted-oak-catering' ),
		),
		'successMessage'  => (string) twistedoak_catering_setting( 'success_message' ),
	);
	if ( $with_nonces ) {
		$config['nonce']     = wp_create_nonce( 'twistedoak_catering_submit' );
		$config['restNonce'] = wp_create_nonce( 'wp_rest' );
	}
	return (array) apply_filters( 'twistedoak_catering_public_config', $config, $with_nonces );
}

/**
 * Theme-facing alias: everything the booking UI needs (rest root + endpoints, nonces, rules,
 * packages, guest limits, lead time, horizon). Filter: `twistedoak_catering_client_config`.
 *
 * @return array<string, mixed>
 */
function twistedoak_catering_client_config(): array {
	return (array) apply_filters( 'twistedoak_catering_client_config', twistedoak_catering_public_config( true ) );
}
