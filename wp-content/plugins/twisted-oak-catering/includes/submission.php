<?php
/**
 * Submission pipeline shared by the REST routes (POST /bookings, legacy
 * POST /catering) and the admin-post.php no-JS fallback.
 *
 * Nonce + honeypot + per-IP rate limit, field validation (collects per-field
 * errors), then availability re-validation inside a per-date lock so two
 * requests can't take the last slot at once.
 *
 * @package TwistedOakCatering
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * User-facing error messages keyed by error code.
 */
function twistedoak_catering_error_message( string $code ): string {
	$s        = function_exists( 'twistedoak_catering_schedule' ) ? twistedoak_catering_schedule() : array(
		'min_guests' => 1,
		'max_guests' => 10000,
	);
	$messages = array(
		'required'         => __( 'Missing required fields: package, name, email.', 'twisted-oak-catering' ),
		'name'             => __( 'Please enter your name.', 'twisted-oak-catering' ),
		'package'          => __( 'Please select a package to continue.', 'twisted-oak-catering' ),
		'meats'            => __( 'You picked more smoked meats than this package includes.', 'twisted-oak-catering' ),
		'sides'            => __( 'You picked more sides than this package includes.', 'twisted-oak-catering' ),
		'premium'          => __( 'Premium proteins are only available with a premium package.', 'twisted-oak-catering' ),
		'email'            => __( 'Invalid email address.', 'twisted-oak-catering' ),
		'event_date'       => __( 'Please choose a valid event date.', 'twisted-oak-catering' ),
		'start_time'       => __( 'Please choose a start time.', 'twisted-oak-catering' ),
		'guests'           => __( 'Please enter a valid number of guests.', 'twisted-oak-catering' ),
		/* translators: %d: minimum guests */
		'guests_min'       => sprintf( __( 'Catering starts at %d guests.', 'twisted-oak-catering' ), (int) $s['min_guests'] ),
		/* translators: %d: maximum guests */
		'guests_max'       => sprintf( __( 'For more than %d guests please call us.', 'twisted-oak-catering' ), (int) $s['max_guests'] ),
		'service_style'    => __( 'Please choose a service style.', 'twisted-oak-catering' ),
		'date_unavailable' => __( 'Sorry, we are not taking bookings on that date. Please choose another day.', 'twisted-oak-catering' ),
		'unavailable'      => __( 'Sorry, that time was just booked or is no longer available. Please choose another.', 'twisted-oak-catering' ),
		'busy'             => __( 'We are handling a lot of requests right now. Please try again in a moment.', 'twisted-oak-catering' ),
		'nonce'            => __( 'Your session expired. Please reload the page and try again.', 'twisted-oak-catering' ),
		'rate_limited'     => __( 'Too many inquiries from your connection. Please try again later or give us a call.', 'twisted-oak-catering' ),
		'save_failed'      => __( 'We could not save your inquiry. Please try again.', 'twisted-oak-catering' ),
		'generic'          => __( 'Something went wrong. Please try again.', 'twisted-oak-catering' ),
	);
	$messages = (array) apply_filters( 'twistedoak_catering_error_messages', $messages );
	return $messages[ $code ] ?? $messages['generic'];
}

/**
 * Normalise raw request params into the booking fields.
 *
 * @param array<string, mixed> $raw Unslashed request params.
 * @return array<string, mixed>
 */
function twistedoak_catering_normalise( array $raw ): array {
	// DESIGN-SPEC slot radio: event_slot = "Y-m-dTH:i" (or "H:i" with a separate event_date).
	if ( isset( $raw['event_slot'] ) && is_string( $raw['event_slot'] ) && '' !== $raw['event_slot'] && empty( $raw['start_time'] ) ) {
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})T(\d{1,2}:\d{2})$/', trim( $raw['event_slot'] ), $m ) ) {
			$raw['event_date'] = empty( $raw['event_date'] ) ? $m[1] : $raw['event_date'];
			$raw['start_time'] = $m[2];
		} else {
			$raw['start_time'] = $raw['event_slot'];
		}
	}
	$text = static fn( string $k, int $max = 200 ): string => mb_substr( sanitize_text_field( is_scalar( $raw[ $k ] ?? null ) ? (string) $raw[ $k ] : '' ), 0, $max );
	$list = static function ( string $k ) use ( $raw ): array {
		$v = $raw[ $k ] ?? array();
		$v = is_array( $v ) ? $v : array( $v );
		return array_values( array_unique( array_filter( array_map( static fn( $i ): string => is_scalar( $i ) ? sanitize_text_field( (string) $i ) : '', $v ) ) ) );
	};

	return array(
		'package'       => $text( 'package' ),
		'meats'         => $list( 'meats' ),
		'sides'         => $list( 'sides' ),
		'desserts'      => $list( 'desserts' ),
		'name'          => $text( 'name' ),
		'email'         => $text( 'email' ),
		'phone'         => $text( 'phone', 40 ),
		'event_date'    => $text( 'event_date', 10 ),
		'start_time'    => $text( 'start_time', 5 ),
		'guests'        => $text( 'guests', 6 ),
		'service_style' => $text( 'service_style', 80 ),
		'location'      => $text( 'location', 300 ),
		'event_type'    => $text( 'event_type', 80 ),
		'message'       => mb_substr( sanitize_textarea_field( is_scalar( $raw['message'] ?? null ) ? (string) $raw['message'] : '' ), 0, 5000 ),
	);
}

/**
 * Validate normalised data. Unknown menu choices are dropped silently.
 *
 * @param array<string, mixed> $data          Normalised data (modified in place).
 * @param bool                 $require_slot  Booking mode: date, start time and guests are required.
 * @return array<string, string> field => error code (empty when valid). Ordered by form position.
 */
function twistedoak_catering_validate_fields( array &$data, bool $require_slot = false ): array {
	$errors = array();
	$s      = twistedoak_catering_schedule();

	if ( '' === $data['package'] ) {
		$errors['package'] = 'package';
	} elseif ( ! in_array( $data['package'], twistedoak_catering_packages(), true ) ) {
		$errors['package'] = 'package';
	}

	$products = twistedoak_catering_products();
	// Premium meats are offered on every package at an upcharge per person (quoted by the team), so they are always allowed.
	$allowed  = array_merge( $products['meats'], $products['premiumMeats'] );
	$data['meats']    = array_values( array_intersect( $data['meats'], $allowed ) );
	$data['sides']    = array_values( array_intersect( $data['sides'], $products['sides'] ) );
	$data['desserts'] = array_values( array_intersect( $data['desserts'], $products['desserts'] ) );

	if ( ! isset( $errors['package'] ) ) {
		$limits = twistedoak_catering_pick_limits( (string) $data['package'] );
		foreach ( array( 'meats', 'sides' ) as $kind ) {
			if ( $limits[ $kind ] > 0 && count( $data[ $kind ] ) > $limits[ $kind ] ) {
				$errors[ $kind ] = $kind;
			}
		}
	}

	if ( '' === $data['event_date'] ) {
		if ( $require_slot ) {
			$errors['event_date'] = 'event_date';
		}
	} elseif ( ! twistedoak_catering_is_date( $data['event_date'] ) ) {
		$errors['event_date'] = 'event_date';
	}

	if ( '' !== $data['start_time'] ) {
		$data['start_time'] = twistedoak_catering_normalise_time( $data['start_time'] );
		if ( '' === $data['start_time'] ) {
			$errors['start_time'] = 'start_time';
		}
	} elseif ( $require_slot ) {
		$errors['start_time'] = 'start_time';
	}
	if ( '' !== $data['start_time'] && '' === $data['event_date'] && ! isset( $errors['event_date'] ) ) {
		$errors['event_date'] = 'event_date';
	}

	if ( '' !== $data['guests'] ) {
		if ( ! ctype_digit( $data['guests'] ) || (int) $data['guests'] < 1 ) {
			$errors['guests'] = 'guests';
		} elseif ( (int) $data['guests'] < (int) $s['min_guests'] ) {
			$errors['guests'] = 'guests_min';
		} elseif ( (int) $data['guests'] > (int) $s['max_guests'] ) {
			$errors['guests'] = 'guests_max';
		} else {
			$data['guests'] = (string) (int) $data['guests'];
		}
	} elseif ( $require_slot ) {
		$errors['guests'] = 'guests';
	}

	$styles = twistedoak_catering_service_styles();
	if ( '' !== $data['service_style'] && ! in_array( $data['service_style'], $styles, true ) ) {
		$errors['service_style'] = 'service_style';
	} elseif ( '' === $data['service_style'] && $require_slot && $styles && apply_filters( 'twistedoak_catering_require_service_style', false ) ) {
		$errors['service_style'] = 'service_style';
	}

	if ( '' === $data['name'] ) {
		$errors['name'] = 'name';
	}
	if ( '' === $data['email'] || ! is_email( $data['email'] ) ) {
		$errors['email'] = 'email';
	}

	return (array) apply_filters( 'twistedoak_catering_validation_errors', $errors, $data, $require_slot );
}

/**
 * Back-compat wrapper: first error code or ''.
 *
 * @param array<string, mixed> $data Normalised data (modified in place).
 */
function twistedoak_catering_validate( array &$data ): string {
	$errors = twistedoak_catering_validate_fields( $data );
	return $errors ? (string) reset( $errors ) : '';
}

function twistedoak_catering_client_ip(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return (string) apply_filters( 'twistedoak_catering_client_ip', $ip );
}

function twistedoak_catering_rate_key(): string {
	return 'tobbq_catering_rl_' . md5( twistedoak_catering_client_ip() . wp_salt( 'nonce' ) );
}

function twistedoak_catering_is_rate_limited(): bool {
	$hits = get_transient( twistedoak_catering_rate_key() );
	return is_array( $hits ) && count( array_filter( $hits, static fn( int $t ): bool => $t > time() - HOUR_IN_SECONDS ) ) >= (int) twistedoak_catering_setting( 'rate_limit' );
}

function twistedoak_catering_record_hit(): void {
	$key    = twistedoak_catering_rate_key();
	$hits   = get_transient( $key );
	$hits   = is_array( $hits ) ? array_filter( $hits, static fn( int $t ): bool => $t > time() - HOUR_IN_SECONDS ) : array();
	$hits[] = time();
	set_transient( $key, array_values( $hits ), HOUR_IN_SECONDS );
}

/**
 * Turn field => code into field => message.
 *
 * @param array<string, string> $errors Field error codes.
 * @return array<string, string>
 */
function twistedoak_catering_error_messages_for( array $errors ): array {
	return array_map( 'twistedoak_catering_error_message', $errors );
}

/**
 * Full pipeline shared by every entry point.
 *
 * @param array<string, mixed> $raw          Unslashed request params.
 * @param bool                 $require_slot Booking mode (POST /bookings): date + start time + guests required.
 * @return array{ok: bool, code: string, data: array<string, mixed>, errors: array<string, string>, lead_id?: int, reference?: string, status?: string, day?: array<string, mixed>}
 */
function twistedoak_catering_process( array $raw, bool $require_slot = false ): array {
	$data = twistedoak_catering_normalise( $raw );
	$fail = static fn( string $code, array $errors = array(), array $extra = array() ): array => array_merge(
		array(
			'ok'     => false,
			'code'   => $code,
			'data'   => $data,
			'errors' => $errors,
		),
		$extra
	);

	$nonce = is_string( $raw['tobbq_catering_nonce'] ?? null ) ? $raw['tobbq_catering_nonce'] : '';
	if ( ! wp_verify_nonce( $nonce, 'twistedoak_catering_submit' ) ) {
		return $fail( 'nonce' );
	}

	if ( twistedoak_catering_is_rate_limited() ) {
		return $fail( 'rate_limited' );
	}

	// Honeypot: pretend success, store nothing.
	if ( ! empty( $raw['website'] ) ) {
		twistedoak_catering_record_hit();
		return array(
			'ok'        => true,
			'code'      => 'spam',
			'data'      => $data,
			'errors'    => array(),
			'reference' => twistedoak_catering_generate_reference( false ),
			'status'    => 'pending',
		);
	}

	$errors = twistedoak_catering_validate_fields( $data, $require_slot );
	if ( $errors ) {
		$codes = array_values( $errors );
		// Legacy single code: "required" when any of the original required trio is missing.
		$code = ( '' === $data['package'] || '' === $data['name'] || '' === $data['email'] ) && ! $require_slot ? 'required' : (string) $codes[0];
		return $fail( $code, twistedoak_catering_error_messages_for( $errors ) );
	}

	twistedoak_catering_record_hit();

	$data['source_url'] = isset( $raw['return_url'] ) && is_string( $raw['return_url'] ) ? esc_url_raw( wp_validate_redirect( $raw['return_url'], home_url( '/' ) ) ) : '';
	$data['status']     = 'pending';
	$data['duration']   = '';

	$locked = false;
	if ( '' !== $data['event_date'] ) {
		$locked = twistedoak_catering_lock( $data['event_date'] );
		if ( ! $locked ) {
			return $fail( 'busy' );
		}
	}

	try {
		if ( '' !== $data['event_date'] ) {
			// Re-validate against fresh data inside the lock.
			$day = twistedoak_catering_day_availability( $data['event_date'], (int) $data['guests'], array( 'fresh' => true ) );
			if ( empty( $day['bookable'] ) ) {
				return $fail(
					'' !== $data['start_time'] ? 'unavailable' : 'date_unavailable',
					array( 'event_date' => twistedoak_catering_error_message( '' !== $data['start_time'] ? 'unavailable' : 'date_unavailable' ) ),
					array( 'day' => $day )
				);
			}
			if ( '' !== $data['start_time'] ) {
				$slot = twistedoak_catering_find_slot( $day, $data['start_time'] );
				if ( ! $slot || 'available' !== $slot['status'] ) {
					return $fail( 'unavailable', array( 'start_time' => twistedoak_catering_error_message( 'unavailable' ) ), array( 'day' => $day ) );
				}
				$data['duration'] = (string) $slot['duration'];
				if ( twistedoak_catering_schedule()['auto_hold'] ) {
					$data['status'] = 'held';
				}
			}
		}

		$data['status']    = (string) apply_filters( 'twistedoak_catering_initial_status', $data['status'], $data );
		$data['reference'] = twistedoak_catering_generate_reference();

		$lead_id = twistedoak_catering_store_lead( $data );
	} finally {
		if ( $locked ) {
			twistedoak_catering_unlock( $data['event_date'] );
		}
	}

	if ( ! $lead_id ) {
		return $fail( 'save_failed' );
	}

	$sent = twistedoak_catering_send_mail( $data, $lead_id );
	update_post_meta( $lead_id, '_tobbq_lead_mail_sent', $sent ? '1' : '0' );
	twistedoak_catering_send_email( 'customer_received', $lead_id );

	do_action( 'twistedoak_catering_submitted', $lead_id, $data );

	return array(
		'ok'        => true,
		'code'      => '',
		'data'      => $data,
		'errors'    => array(),
		'lead_id'   => $lead_id,
		'reference' => $data['reference'],
		'status'    => $data['status'],
	);
}

/**
 * Unique, human-friendly reference like "TO-7K3M9Q".
 */
function twistedoak_catering_generate_reference( bool $unique = true ): string {
	$alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
	do {
		$ref = 'TO-';
		for ( $i = 0; $i < 6; $i++ ) {
			$ref .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
		}
	} while ( $unique && twistedoak_catering_find_by_reference( $ref ) );
	return $ref;
}

/**
 * Booking ID by reference, or 0.
 */
function twistedoak_catering_find_by_reference( string $reference ): int {
	$ids = get_posts(
		array(
			'post_type'        => TWISTEDOAK_CATERING_CPT,
			'post_status'      => 'any',
			'numberposts'      => 1,
			'fields'           => 'ids',
			'suppress_filters' => false,
			'meta_key'         => '_tobbq_lead_reference', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'       => $reference, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Persist a booking.
 *
 * @param array<string, mixed> $data Validated data.
 */
function twistedoak_catering_store_lead( array $data ): int {
	$title = sprintf(
		/* translators: 1: name, 2: package */
		__( '%1$s — %2$s', 'twisted-oak-catering' ),
		$data['name'],
		$data['package']
	);
	if ( ! empty( $data['event_date'] ) ) {
		$title = $data['event_date'] . ' · ' . $title;
	}

	$meta = array();
	foreach ( array_keys( twistedoak_catering_lead_fields() ) as $key ) {
		if ( array_key_exists( $key, $data ) ) {
			$meta[ '_tobbq_lead_' . $key ] = $data[ $key ];
		}
	}
	$meta['_tobbq_lead_status_log'] = array(
		array(
			'time' => time(),
			'from' => '',
			'to'   => $data['status'] ?? 'pending',
			'user' => get_current_user_id(),
		),
	);

	$id = wp_insert_post(
		array(
			'post_type'   => TWISTEDOAK_CATERING_CPT,
			'post_status' => 'publish',
			'post_title'  => $title,
			'meta_input'  => $meta,
		),
		true
	);

	if ( is_wp_error( $id ) ) {
		error_log( 'twisted-oak-catering: failed to store lead: ' . $id->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		return 0;
	}
	return (int) $id;
}

/**
 * Admin notification (back-compat name).
 *
 * @param array<string, mixed> $data Validated data (unused; read from the stored booking).
 */
function twistedoak_catering_send_mail( array $data, int $lead_id ): bool {
	return twistedoak_catering_send_email( 'admin_new', $lead_id );
}

/* ---------- admin-post.php fallback (no JS) ---------- */

add_action( 'admin_post_nopriv_twistedoak_catering', 'twistedoak_catering_admin_post' );
add_action( 'admin_post_twistedoak_catering', 'twistedoak_catering_admin_post' );

function twistedoak_catering_admin_post(): void {
	// Nonce is verified inside twistedoak_catering_process().
	$raw    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$raw    = is_array( $raw ) ? $raw : array();
	$result = twistedoak_catering_process( $raw, ! empty( $raw['require_slot'] ) );

	$return = isset( $raw['return_url'] ) && is_string( $raw['return_url'] ) ? $raw['return_url'] : (string) wp_get_referer();
	$return = wp_validate_redirect( $return, home_url( '/catering/' ) );
	$return = remove_query_arg( array( 'catering', 'catering_error', 'catering_token', 'catering_ref' ), $return );

	if ( $result['ok'] ) {
		$url = add_query_arg(
			array(
				'catering'     => 'sent',
				'catering_ref' => $result['reference'] ?? '',
			),
			$return
		);
	} else {
		$token = wp_generate_password( 20, false, false );
		set_transient( 'tobbq_catering_flash_' . $token, array_merge( $result['data'], array( '_errors' => $result['errors'] ) ), 10 * MINUTE_IN_SECONDS );
		$url = add_query_arg(
			array(
				'catering'       => 'error',
				'catering_error' => $result['code'],
				'catering_token' => $token,
			),
			$return
		);
	}

	wp_safe_redirect( $url . '#catering-form-mount', 303 );
	exit;
}
