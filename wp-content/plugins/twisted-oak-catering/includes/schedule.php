<?php
/**
 * Booking statuses + availability rules (the `twistedoak_catering_schedule` option).
 *
 * Stored shape (always structured; the admin form posts textareas that are
 * parsed by twistedoak_catering_sanitize_schedule(), which is idempotent and
 * also accepts the structured shape — WP-CLI seeding writes it directly):
 *
 *   weekdays           int[]   ISO-8601 weekdays that are bookable (1 = Mon … 7 = Sun).
 *   slots              array<int, list<array{time: string, duration: int}>>  per ISO weekday; time "H:i", duration minutes.
 *   capacity           int     max events per day.
 *   slot_capacity      int     max events per slot (overlapping service windows).
 *   max_guests_per_day int     0 = unlimited.
 *   min_guests         int     per booking.
 *   max_guests         int     per booking.
 *   lead_days          int     minimum days between today and the event.
 *   horizon_months     int     furthest bookable date, in months from today.
 *   limited_threshold  int     a partly-booked day is "limited" when remaining events <= this.
 *   blackouts          list<array{start: string, end: string, label: string}>  Y-m-d, inclusive.
 *   overrides          array<string, array{mode: string, capacity: ?int, slots: ?list, note: string}>  keyed by Y-m-d; mode open|closed.
 *   consuming_statuses string[] booking statuses that use capacity.
 *   auto_hold          bool    web bookings with a valid slot start as "held" (tentative hold) instead of "pending".
 *   service_styles     string[]
 *
 * @package TwistedOakCatering
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const TWISTEDOAK_CATERING_SCHEDULE_OPTION = 'twistedoak_catering_schedule';

/**
 * Booking statuses (workflow: pending → held (tentative hold) → confirmed → completed; or declined / cancelled).
 * Public statuses (API/UI) are pending | held | confirmed | declined — see twistedoak_catering_public_status().
 *
 * @return array<string, string> slug => label.
 */
function twistedoak_catering_statuses(): array {
	return (array) apply_filters(
		'twistedoak_catering_statuses',
		array(
			'pending'   => __( 'Pending', 'twisted-oak-catering' ),
			'held'      => __( 'On hold (tentative)', 'twisted-oak-catering' ),
			'confirmed' => __( 'Confirmed', 'twisted-oak-catering' ),
			'completed' => __( 'Completed', 'twisted-oak-catering' ),
			'declined'  => __( 'Declined', 'twisted-oak-catering' ),
			'cancelled' => __( 'Cancelled', 'twisted-oak-catering' ),
		)
	);
}

/**
 * @return array<string, mixed>
 */
function twistedoak_catering_schedule_defaults(): array {
	$evening = array(
		array(
			'time'     => '17:00',
			'duration' => 180,
		),
	);
	$weekend = array(
		array(
			'time'     => '11:00',
			'duration' => 180,
		),
		array(
			'time'     => '17:00',
			'duration' => 240,
		),
	);
	return array(
		'weekdays'           => array( 4, 5, 6, 7 ),
		'slots'              => array(
			1 => array(),
			2 => array(),
			3 => array(),
			4 => $evening,
			5 => array(
				array(
					'time'     => '11:30',
					'duration' => 150,
				),
				array(
					'time'     => '17:30',
					'duration' => 210,
				),
			),
			6 => $weekend,
			7 => array(
				array(
					'time'     => '11:00',
					'duration' => 240,
				),
			),
		),
		'capacity'           => 2,
		'slot_capacity'      => 1,
		'max_guests_per_day' => 400,
		'min_guests'         => 10,
		'max_guests'         => 300,
		'lead_days'          => 7,
		'horizon_months'     => 6,
		'limited_threshold'  => 1,
		'blackouts'          => array(),
		'overrides'          => array(),
		'consuming_statuses' => array( 'held', 'confirmed' ),
		'auto_hold'          => true,
		'service_styles'     => array( 'Pickup', 'Drop-off', 'Onsite setup', 'Full service (staffed)' ),
	);
}

/**
 * Current schedule merged over defaults (memoised per request; reset with $reset).
 *
 * @return array<string, mixed>
 */
function twistedoak_catering_schedule( bool $reset = false ): array {
	static $cache = null;
	if ( null !== $cache && ! $reset ) {
		return $cache;
	}
	$saved = get_option( TWISTEDOAK_CATERING_SCHEDULE_OPTION, array() );
	$cache = twistedoak_catering_sanitize_schedule( array_merge( twistedoak_catering_schedule_defaults(), is_array( $saved ) ? $saved : array() ) );
	$cache = (array) apply_filters( 'twistedoak_catering_schedule', $cache );
	return $cache;
}

add_action(
	'update_option_' . TWISTEDOAK_CATERING_SCHEDULE_OPTION,
	static function (): void {
		twistedoak_catering_schedule( true );
	}
);

/**
 * Is a string a valid Y-m-d date?
 */
function twistedoak_catering_is_date( mixed $value ): bool {
	if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
		return false;
	}
	$d = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
	return $d && $d->format( 'Y-m-d' ) === $value;
}

/**
 * Normalise "H:i" / "H:ii" / "9:30" → "09:30"; '' when invalid.
 */
function twistedoak_catering_normalise_time( mixed $value ): string {
	if ( ! is_string( $value ) || ! preg_match( '/^(\d{1,2}):(\d{2})$/', trim( $value ), $m ) ) {
		return '';
	}
	$h = (int) $m[1];
	$i = (int) $m[2];
	return ( $h <= 23 && $i <= 59 ) ? sprintf( '%02d:%02d', $h, $i ) : '';
}

/**
 * Parse slot input: structured list, or lines like "11:00 180" / "11:00/180" / "11:00,180".
 *
 * @return list<array{time: string, duration: int}>
 */
function twistedoak_catering_parse_slots( mixed $input ): array {
	$rows = array();
	if ( is_string( $input ) ) {
		foreach ( preg_split( '/[\r\n;]+/', $input ) ?: array() as $line ) {
			$parts = preg_split( '/[\s\/,|]+/', trim( $line ) ) ?: array();
			if ( '' === ( $parts[0] ?? '' ) ) {
				continue;
			}
			$rows[] = array(
				'time'     => $parts[0],
				'duration' => $parts[1] ?? 180,
			);
		}
	} elseif ( is_array( $input ) ) {
		$rows = $input;
	}

	$slots = array();
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$time = twistedoak_catering_normalise_time( $row['time'] ?? '' );
		$dur  = max( 30, min( 720, absint( $row['duration'] ?? 180 ) ) );
		if ( '' !== $time ) {
			$slots[ $time ] = array(
				'time'     => $time,
				'duration' => $dur,
			);
		}
	}
	ksort( $slots );
	return array_values( $slots );
}

/**
 * Parse blackout input: structured list or lines "2026-11-26 Thanksgiving" / "2026-12-24..2026-12-26 Holiday break".
 *
 * @return list<array{start: string, end: string, label: string}>
 */
function twistedoak_catering_parse_blackouts( mixed $input ): array {
	$rows = array();
	if ( is_string( $input ) ) {
		foreach ( preg_split( '/\R/', $input ) ?: array() as $line ) {
			$line = trim( $line );
			if ( ! preg_match( '/^(\d{4}-\d{2}-\d{2})(?:\s*(?:\.\.|–|to)\s*(\d{4}-\d{2}-\d{2}))?\s*(.*)$/u', $line, $m ) ) {
				continue;
			}
			$rows[] = array(
				'start' => $m[1],
				'end'   => '' !== $m[2] ? $m[2] : $m[1],
				'label' => $m[3],
			);
		}
	} elseif ( is_array( $input ) ) {
		$rows = $input;
	}

	$out = array();
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) || ! twistedoak_catering_is_date( $row['start'] ?? null ) ) {
			continue;
		}
		$start = $row['start'];
		$end   = twistedoak_catering_is_date( $row['end'] ?? null ) ? $row['end'] : $start;
		if ( $end < $start ) {
			[ $start, $end ] = array( $end, $start );
		}
		$out[] = array(
			'start' => $start,
			'end'   => $end,
			'label' => mb_substr( sanitize_text_field( (string) ( $row['label'] ?? '' ) ), 0, 120 ),
		);
	}
	usort( $out, static fn( array $a, array $b ): int => strcmp( $a['start'], $b['start'] ) );
	return $out;
}

/**
 * Parse per-date overrides: structured map or lines like
 *   "2026-10-14 open slots=11:00/180,17:00/240 capacity=1 Corporate lunch"
 *   "2026-10-23 closed Staff party"
 *   "2026-10-31 capacity=3"            (custom capacity; open/closed follows the normal rules)
 *
 * @return array<string, array{mode: string, capacity: ?int, slots: ?list<array{time: string, duration: int}>, note: string}>
 */
function twistedoak_catering_parse_overrides( mixed $input ): array {
	$rows = array();
	if ( is_string( $input ) ) {
		foreach ( preg_split( '/\R/', $input ) ?: array() as $line ) {
			$tokens = preg_split( '/\s+/', trim( $line ) ) ?: array();
			$date   = array_shift( $tokens );
			if ( ! twistedoak_catering_is_date( $date ) ) {
				continue;
			}
			$row  = array(
				'mode'     => '',
				'capacity' => null,
				'slots'    => null,
				'note'     => '',
			);
			$note = array();
			foreach ( $tokens as $t ) {
				$lt = strtolower( $t );
				if ( in_array( $lt, array( 'open', 'closed', 'custom' ), true ) && '' === $row['mode'] ) {
					$row['mode'] = $lt;
				} elseif ( str_starts_with( $lt, 'capacity=' ) ) {
					$row['capacity'] = absint( substr( $lt, 9 ) );
				} elseif ( str_starts_with( $lt, 'slots=' ) ) {
					$row['slots'] = str_replace( ',', "\n", substr( $t, 6 ) );
				} else {
					$note[] = $t;
				}
			}
			$row['note']   = implode( ' ', $note );
			$rows[ $date ] = $row;
		}
	} elseif ( is_array( $input ) ) {
		$rows = $input;
	}

	$out = array();
	foreach ( $rows as $date => $row ) {
		if ( ! twistedoak_catering_is_date( $date ) || ! is_array( $row ) ) {
			continue;
		}
		$mode = in_array( $row['mode'] ?? '', array( 'open', 'closed' ), true ) ? $row['mode'] : 'custom';
		$cap  = isset( $row['capacity'] ) && '' !== $row['capacity'] && null !== $row['capacity'] ? min( 50, absint( $row['capacity'] ) ) : null;
		$slots = null;
		if ( isset( $row['slots'] ) && null !== $row['slots'] ) {
			$slots = twistedoak_catering_parse_slots( $row['slots'] );
			$slots = $slots ? $slots : null;
		}
		// "custom" without anything custom is meaningless; custom with slots implies open.
		if ( 'custom' === $mode && null === $cap && null === $slots ) {
			continue;
		}
		$out[ $date ] = array(
			'mode'     => $mode,
			'capacity' => $cap,
			'slots'    => $slots,
			'note'     => mb_substr( sanitize_text_field( (string) ( $row['note'] ?? '' ) ), 0, 120 ),
		);
	}
	ksort( $out );
	return $out;
}

/**
 * Sanitize / normalise the schedule option (idempotent).
 *
 * @param mixed $input Raw form input or structured array.
 * @return array<string, mixed>
 */
function twistedoak_catering_sanitize_schedule( mixed $input ): array {
	$input    = is_array( $input ) ? $input : array();
	$defaults = twistedoak_catering_schedule_defaults();
	$int      = static fn( string $k, int $min, int $max ): int => max( $min, min( $max, (int) ( $input[ $k ] ?? $defaults[ $k ] ) ) );

	$weekdays = array_values( array_unique( array_filter( array_map( 'intval', (array) ( $input['weekdays'] ?? array() ) ), static fn( int $d ): bool => $d >= 1 && $d <= 7 ) ) );
	sort( $weekdays );

	$slots = array();
	for ( $d = 1; $d <= 7; $d++ ) {
		$slots[ $d ] = twistedoak_catering_parse_slots( $input['slots'][ $d ] ?? ( $input['slots'][ (string) $d ] ?? array() ) );
	}

	$statuses  = array_keys( twistedoak_catering_statuses() );
	$consuming = array_map( static fn( $v ): string => 'tentative' === (string) $v ? 'held' : (string) $v, (array) ( $input['consuming_statuses'] ?? array() ) ); // v2.0 alias.
	$consuming = array_values( array_intersect( $statuses, $consuming ) );

	$styles = $input['service_styles'] ?? $defaults['service_styles'];
	$styles = is_string( $styles ) ? twistedoak_catering_lines( $styles ) : (array) $styles;
	$styles = array_values( array_unique( array_filter( array_map( static fn( $s ): string => mb_substr( sanitize_text_field( (string) $s ), 0, 80 ), $styles ) ) ) );

	return array(
		'weekdays'           => $weekdays,
		'slots'              => $slots,
		'capacity'           => $int( 'capacity', 0, 50 ),
		'slot_capacity'      => $int( 'slot_capacity', 1, 50 ),
		'max_guests_per_day' => $int( 'max_guests_per_day', 0, 100000 ),
		'min_guests'         => $int( 'min_guests', 1, 10000 ),
		'max_guests'         => $int( 'max_guests', 1, 10000 ),
		'lead_days'          => $int( 'lead_days', 0, 365 ),
		'horizon_months'     => $int( 'horizon_months', 1, 36 ),
		'limited_threshold'  => $int( 'limited_threshold', 0, 50 ),
		'blackouts'          => twistedoak_catering_parse_blackouts( $input['blackouts'] ?? array() ),
		'overrides'          => twistedoak_catering_parse_overrides( $input['overrides'] ?? array() ),
		'consuming_statuses' => $consuming,
		'auto_hold'          => ! empty( $input['auto_hold'] ),
		'service_styles'     => $styles,
	);
}

/**
 * Service styles offered on the form.
 *
 * @return string[]
 */
function twistedoak_catering_service_styles(): array {
	return (array) apply_filters( 'twistedoak_catering_service_styles', twistedoak_catering_schedule()['service_styles'] );
}

/**
 * Map an internal status to the public one used by the API/UI: pending | held | confirmed | declined.
 */
function twistedoak_catering_public_status( string $status ): string {
	return match ( $status ) {
		'held', 'tentative'      => 'held',
		'confirmed', 'completed' => 'confirmed',
		'declined', 'cancelled'  => 'declined',
		default                  => 'pending',
	};
}
