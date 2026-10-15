<?php
/**
 * Availability engine: resolves the schedule rules + existing bookings into
 * per-day / per-slot statuses, and the short-lived per-date booking lock.
 *
 * Day status:  available | limited | full | blackout | closed | past | too-soon | beyond-horizon
 * Slot status: available | full
 *
 * @package TwistedOakCatering
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Today (site timezone, midnight).
 */
function twistedoak_catering_today(): DateTimeImmutable {
	return current_datetime()->setTime( 0, 0 );
}

/**
 * Earliest bookable date (today + lead days).
 */
function twistedoak_catering_earliest_date(): DateTimeImmutable {
	return twistedoak_catering_today()->modify( '+' . (int) twistedoak_catering_schedule()['lead_days'] . ' days' );
}

/**
 * Latest bookable date (today + horizon months).
 */
function twistedoak_catering_latest_date(): DateTimeImmutable {
	return twistedoak_catering_today()->modify( '+' . (int) twistedoak_catering_schedule()['horizon_months'] . ' months' );
}

/**
 * Minutes since midnight for "H:i".
 */
function twistedoak_catering_minutes( string $time ): int {
	[ $h, $i ] = array_map( 'intval', explode( ':', $time . ':0' ) );
	return $h * 60 + $i;
}

/**
 * "H:i" for minutes since midnight (wraps past midnight).
 */
function twistedoak_catering_time_from_minutes( int $minutes ): string {
	$minutes = ( ( $minutes % 1440 ) + 1440 ) % 1440;
	return sprintf( '%02d:%02d', intdiv( $minutes, 60 ), $minutes % 60 );
}

/**
 * Human time like "11:00 am" using the site time format.
 */
function twistedoak_catering_format_time( string $time ): string {
	$utc = new DateTimeZone( 'UTC' );
	$dt  = DateTimeImmutable::createFromFormat( '!H:i', $time, $utc );
	return $dt ? (string) wp_date( (string) get_option( 'time_format', 'g:i a' ), $dt->getTimestamp(), $utc ) : $time;
}

/**
 * Human label for a service window, e.g. "11:00 am – 2:00 pm".
 */
function twistedoak_catering_window_label( string $start, int $duration ): string {
	$end = twistedoak_catering_time_from_minutes( twistedoak_catering_minutes( $start ) + $duration );
	return twistedoak_catering_format_time( $start ) . ' – ' . twistedoak_catering_format_time( $end );
}

/**
 * The rules that apply to one date, before bookings are considered.
 *
 * @param bool $admin Ignore past / lead-time / horizon checks.
 * @return array{status: string, note: string, internal: string, capacity: int, slots: list<array{time: string, duration: int}>}
 */
function twistedoak_catering_day_rules( string $date, bool $admin = false ): array {
	$s       = twistedoak_catering_schedule();
	$day     = new DateTimeImmutable( $date, wp_timezone() );
	$weekday = (int) $day->format( 'N' );
	$ymd     = $day->format( 'Y-m-d' );
	$internal = (string) ( $s['overrides'][ $ymd ]['note'] ?? '' ); // Override notes are staff-only.
	$closed   = static fn( string $status, string $note = '' ): array => array(
		'status'   => $status,
		'note'     => $note,
		'internal' => $internal,
		'capacity' => 0,
		'slots'    => array(),
	);

	if ( ! $admin ) {
		if ( $ymd < twistedoak_catering_today()->format( 'Y-m-d' ) ) {
			return $closed( 'past' );
		}
		if ( $ymd < twistedoak_catering_earliest_date()->format( 'Y-m-d' ) ) {
			return $closed( 'too-soon' );
		}
		if ( $ymd > twistedoak_catering_latest_date()->format( 'Y-m-d' ) ) {
			return $closed( 'beyond-horizon' );
		}
	}

	$override = $s['overrides'][ $ymd ] ?? null;
	if ( $override && 'closed' === $override['mode'] ) {
		return $closed( 'closed' );
	}
	$forced_open = $override && 'open' === $override['mode'];

	if ( ! $forced_open ) {
		foreach ( $s['blackouts'] as $b ) {
			if ( $ymd >= $b['start'] && $ymd <= $b['end'] ) {
				return $closed( 'blackout', $b['label'] );
			}
		}
		if ( ! in_array( $weekday, $s['weekdays'], true ) ) {
			return $closed( 'closed' );
		}
	}

	$slots = $override['slots'] ?? null;
	if ( ! $slots ) {
		$slots = $s['slots'][ $weekday ] ?? array();
	}
	if ( ! $slots && $forced_open ) {
		// Opened on a day with no slots of its own: borrow the first bookable weekday's slots.
		foreach ( $s['weekdays'] as $d ) {
			if ( ! empty( $s['slots'][ $d ] ) ) {
				$slots = $s['slots'][ $d ];
				break;
			}
		}
	}
	$capacity = null !== ( $override['capacity'] ?? null ) ? (int) $override['capacity'] : (int) $s['capacity'];

	if ( ! $slots || $capacity < 1 ) {
		return $closed( 'closed' );
	}

	return array(
		'status'   => 'open',
		'note'     => '',
		'internal' => $internal,
		'capacity' => $capacity,
		'slots'    => $slots,
	);
}

/**
 * Bookings between two dates (inclusive), grouped by date.
 *
 * @param string[]|null $statuses Statuses to include; null = the capacity-consuming ones.
 * @param int           $exclude  Booking ID to ignore (re-validating an existing booking).
 * @param bool          $fresh    Bypass the per-request cache.
 * @return array<string, list<array{id: int, start: string, duration: int, guests: int, status: string}>>
 */
function twistedoak_catering_bookings_between( string $from, string $to, ?array $statuses = null, int $exclude = 0, bool $fresh = false ): array {
	static $cache = array();
	$statuses = $statuses ?? twistedoak_catering_schedule()['consuming_statuses'];
	$key      = md5( wp_json_encode( array( $from, $to, $statuses, $exclude ) ) );
	if ( ! $fresh && isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	if ( ! $statuses ) {
		$cache[ $key ] = array();
		return array();
	}

	$ids = get_posts(
		array(
			'post_type'        => TWISTEDOAK_CATERING_CPT,
			'post_status'      => array( 'publish', 'private', 'draft' ),
			'numberposts'      => -1,
			'fields'           => 'ids',
			'exclude'          => $exclude ? array( $exclude ) : array(),
			'suppress_filters' => false,
			'cache_results'    => ! $fresh,
			'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array(
					'key'     => '_tobbq_lead_event_date',
					'value'   => array( $from, $to ),
					'compare' => 'BETWEEN',
					'type'    => 'CHAR',
				),
				array(
					'key'     => '_tobbq_lead_status',
					'value'   => $statuses,
					'compare' => 'IN',
				),
			),
		)
	);

	if ( $fresh && $ids ) {
		// Make sure we read meta straight from the DB inside the lock.
		foreach ( $ids as $id ) {
			wp_cache_delete( (int) $id, 'post_meta' );
		}
	}
	update_meta_cache( 'post', $ids );

	$out = array();
	foreach ( $ids as $id ) {
		$id    = (int) $id;
		$date  = (string) get_post_meta( $id, '_tobbq_lead_event_date', true );
		$start = twistedoak_catering_normalise_time( (string) get_post_meta( $id, '_tobbq_lead_start_time', true ) );
		$out[ $date ][] = array(
			'id'       => $id,
			'start'    => $start,
			'duration' => max( 0, (int) get_post_meta( $id, '_tobbq_lead_duration', true ) ),
			'guests'   => max( 0, (int) get_post_meta( $id, '_tobbq_lead_guests', true ) ),
			'status'   => (string) get_post_meta( $id, '_tobbq_lead_status', true ),
		);
	}

	$cache[ $key ] = $out;
	return $out;
}

/**
 * Resolve one day.
 *
 * @param list<array{id: int, start: string, duration: int, guests: int, status: string}> $bookings Consuming bookings that day.
 * @param int  $guests Requested party size (0 = don't check guest capacity).
 * @param bool $admin  Ignore past / lead-time / horizon.
 * @return array<string, mixed> Day DTO (see SCHEDULING.md).
 */
function twistedoak_catering_resolve_day( string $date, array $bookings, int $guests = 0, bool $admin = false ): array {
	$s     = twistedoak_catering_schedule();
	$rules = twistedoak_catering_day_rules( $date, $admin );
	$day   = array(
		'date'      => $date,
		'weekday'   => (int) ( new DateTimeImmutable( $date ) )->format( 'N' ),
		'status'    => $rules['status'],
		'bookable'  => false,
		'note'      => $rules['note'],
		'capacity'  => $rules['capacity'],
		'remaining' => 0,
		'slots'     => array(),
	);
	if ( 'open' !== $rules['status'] ) {
		return twistedoak_catering_ui_fields( $day );
	}

	$used_guests      = array_sum( array_column( $bookings, 'guests' ) );
	$remaining        = max( 0, $rules['capacity'] - count( $bookings ) );
	$max_guests       = (int) $s['max_guests_per_day'];
	$guests_ok        = 0 === $max_guests || $guests <= 0 || ( $max_guests - $used_guests ) >= $guests;
	$day['remaining'] = $remaining;

	$open_slots = 0;
	foreach ( $rules['slots'] as $slot ) {
		$s_start = twistedoak_catering_minutes( $slot['time'] );
		$s_end   = $s_start + $slot['duration'];
		$overlap = 0;
		foreach ( $bookings as $b ) {
			if ( '' === $b['start'] ) {
				continue; // Date-only booking: uses day capacity, not a slot.
			}
			$b_start = twistedoak_catering_minutes( $b['start'] );
			$b_end   = $b_start + max( 1, $b['duration'] ? $b['duration'] : $slot['duration'] );
			if ( $b_start < $s_end && $s_start < $b_end ) {
				++$overlap;
			}
		}
		$slot_remaining = max( 0, min( $remaining, (int) $s['slot_capacity'] - $overlap ) );
		$ok             = $slot_remaining > 0 && $guests_ok;
		$open_slots    += $ok ? 1 : 0;
		$day['slots'][] = array(
			'time'      => $slot['time'],
			'end'       => twistedoak_catering_time_from_minutes( $s_end ),
			'duration'  => $slot['duration'],
			'label'     => twistedoak_catering_window_label( $slot['time'], $slot['duration'] ),
			'status'    => $ok ? 'available' : 'full',
			'remaining' => $ok ? $slot_remaining : 0,
		);
	}

	// A day can't take more events than its open slots can hold.
	$remaining        = min( $remaining, (int) array_sum( array_column( $day['slots'], 'remaining' ) ) );
	$day['remaining'] = $remaining;

	if ( 0 === $remaining || 0 === $open_slots ) {
		$day['status']    = 'full';
		$day['remaining'] = 0;
	} elseif ( $remaining <= (int) $s['limited_threshold'] || $open_slots < count( $rules['slots'] ) ) {
		$day['status'] = 'limited';
	} else {
		$day['status'] = 'available';
	}
	$day['bookable'] = in_array( $day['status'], array( 'available', 'limited' ), true );

	return twistedoak_catering_ui_fields( (array) apply_filters( 'twistedoak_catering_day_availability', $day, $date, $bookings, $guests, $admin ) );
}

/**
 * Add the DESIGN-SPEC (§10.3/10.4) aliases next to the engine's own fields:
 *   day.state  available | limited | booked | blackout | lead-time | past
 *   day.reason human text for non-bookable days ('' otherwise)
 *   slot.start (= time), slot.state available | limited | booked
 *
 * @param array<string, mixed> $day Day DTO.
 * @return array<string, mixed>
 */
function twistedoak_catering_ui_fields( array $day ): array {
	$day['state'] = match ( $day['status'] ) {
		'available', 'limited', 'past', 'blackout' => $day['status'],
		'full'                                     => 'booked',
		'too-soon'                                 => 'lead-time',
		default                                    => 'blackout', // closed, beyond-horizon.
	};
	$lead          = (int) twistedoak_catering_schedule()['lead_days'];
	$day['reason'] = match ( $day['status'] ) {
		/* translators: %s: blackout label, e.g. Thanksgiving */
		'blackout'       => '' !== $day['note'] ? sprintf( __( 'Closed for %s', 'twisted-oak-catering' ), $day['note'] ) : __( 'Closed', 'twisted-oak-catering' ),
		'closed'         => __( 'Closed', 'twisted-oak-catering' ),
		'beyond-horizon' => __( 'Not open for booking yet', 'twisted-oak-catering' ),
		/* translators: %d: days of notice */
		'too-soon'       => sprintf( _n( 'Needs %d day\'s notice', 'Needs %d days\' notice', $lead, 'twisted-oak-catering' ), $lead ),
		'full'           => __( 'Fully booked', 'twisted-oak-catering' ),
		default          => '',
	};
	$cap = max( 1, (int) twistedoak_catering_schedule()['slot_capacity'] );
	foreach ( $day['slots'] as &$slot ) {
		$slot['start'] = $slot['time'];
		$slot['state'] = 'available' !== $slot['status'] ? 'booked' : ( $cap > 1 && $slot['remaining'] <= (int) twistedoak_catering_schedule()['limited_threshold'] ? 'limited' : 'available' );
	}
	unset( $slot );
	return $day;
}

/**
 * First bookable slot after $date (exclusive), searching up to 92 days. Null when none.
 *
 * @return array{date: string, start: string, label: string}|null
 */
function twistedoak_catering_next_available( string $date, int $guests = 0 ): ?array {
	if ( ! twistedoak_catering_is_date( $date ) ) {
		return null;
	}
	$from = ( new DateTimeImmutable( $date, wp_timezone() ) )->modify( '+1 day' );
	foreach ( twistedoak_catering_availability( $from->format( 'Y-m-d' ), $from->modify( '+91 days' )->format( 'Y-m-d' ), $guests, array( 'fresh' => true ) ) as $day ) {
		if ( ! $day['bookable'] ) {
			continue;
		}
		foreach ( $day['slots'] as $slot ) {
			if ( 'available' === $slot['status'] ) {
				return array(
					'date'  => $day['date'],
					'start' => $slot['time'],
					'label' => wp_date( 'D, M j', ( new DateTimeImmutable( $day['date'] . ' 12:00', wp_timezone() ) )->getTimestamp() ) . ' ' . __( 'at', 'twisted-oak-catering' ) . ' ' . twistedoak_catering_format_time( $slot['time'] ),
				);
			}
		}
	}
	return null;
}

/**
 * Availability for a date range (inclusive).
 *
 * @param array{admin?: bool, exclude?: int, fresh?: bool} $opts Options.
 * @return list<array<string, mixed>>
 */
function twistedoak_catering_availability( string $from, string $to, int $guests = 0, array $opts = array() ): array {
	$bookings = twistedoak_catering_bookings_between( $from, $to, null, (int) ( $opts['exclude'] ?? 0 ), ! empty( $opts['fresh'] ) );
	$days     = array();
	$cursor   = new DateTimeImmutable( $from, wp_timezone() );
	$end      = new DateTimeImmutable( $to, wp_timezone() );
	while ( $cursor <= $end ) {
		$ymd    = $cursor->format( 'Y-m-d' );
		$days[] = twistedoak_catering_resolve_day( $ymd, $bookings[ $ymd ] ?? array(), $guests, ! empty( $opts['admin'] ) );
		$cursor = $cursor->modify( '+1 day' );
	}
	return $days;
}

/**
 * One day's availability.
 *
 * @param array{admin?: bool, exclude?: int, fresh?: bool} $opts Options.
 * @return array<string, mixed>
 */
function twistedoak_catering_day_availability( string $date, int $guests = 0, array $opts = array() ): array {
	return twistedoak_catering_availability( $date, $date, $guests, $opts )[0];
}

/**
 * Find a slot in a resolved day by start time.
 *
 * @param array<string, mixed> $day Day DTO.
 * @return array<string, mixed>|null
 */
function twistedoak_catering_find_slot( array $day, string $time ): ?array {
	foreach ( $day['slots'] as $slot ) {
		if ( $slot['time'] === $time ) {
			return $slot;
		}
	}
	return null;
}

/* ---------- Per-date lock (prevents two requests booking the last slot at once) ---------- */

/**
 * Acquire a short-lived lock for a date. Uses INSERT IGNORE on the options
 * table's unique option_name index, which is atomic (add_option() is not: it
 * upserts). Stale locks (> 15 s) are broken.
 */
function twistedoak_catering_lock( string $date, float $wait_seconds = 5.0 ): bool {
	global $wpdb;
	$name     = 'tobbq_catering_lock_' . str_replace( '-', '', $date );
	$deadline = microtime( true ) + $wait_seconds;
	do {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic lock.
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $name, (string) time() ) );
		if ( 1 === (int) $wpdb->rows_affected ) {
			return true;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- lock state must bypass cache.
		$since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
		if ( $since && $since < time() - 15 ) {
			twistedoak_catering_unlock( $date );
			continue;
		}
		usleep( 150000 );
	} while ( microtime( true ) < $deadline );
	return false;
}

function twistedoak_catering_unlock( string $date ): void {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- lock release.
	$wpdb->delete( $wpdb->options, array( 'option_name' => 'tobbq_catering_lock_' . str_replace( '-', '', $date ) ), array( '%s' ) );
}
