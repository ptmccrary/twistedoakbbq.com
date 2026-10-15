<?php
/**
 * Plain-text emails + .ics calendar file.
 *
 * Types: admin_new, customer_received, customer_confirmed, customer_declined, customer_cancelled.
 * Every email passes through `twistedoak_catering_email` (and `twistedoak_catering_email_{type}`)
 * as array{to, subject, message, headers, attachments} — return false to suppress.
 *
 * @package TwistedOakCatering
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Stored booking as a flat array of raw values.
 *
 * @return array<string, mixed>
 */
function twistedoak_catering_booking( int $id ): array {
	$out = array( 'id' => $id );
	foreach ( array_keys( twistedoak_catering_lead_fields() ) as $key ) {
		$out[ $key ] = get_post_meta( $id, '_tobbq_lead_' . $key, true );
	}
	$out['status'] = '' !== (string) $out['status'] ? (string) $out['status'] : 'pending';
	return $out;
}

/**
 * Start/end DateTimes for a booking (site timezone), or null without a date.
 *
 * @param array<string, mixed> $b Booking.
 * @return array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: bool}|null [start, end, all_day]
 */
function twistedoak_catering_booking_times( array $b ): ?array {
	if ( ! twistedoak_catering_is_date( $b['event_date'] ?? null ) ) {
		return null;
	}
	$time = twistedoak_catering_normalise_time( (string) ( $b['start_time'] ?? '' ) );
	if ( '' === $time ) {
		$start = new DateTimeImmutable( $b['event_date'] . ' 00:00', wp_timezone() );
		return array( $start, $start->modify( '+1 day' ), true );
	}
	$start = new DateTimeImmutable( $b['event_date'] . ' ' . $time, wp_timezone() );
	$dur   = max( 30, (int) ( $b['duration'] ?? 0 ) ? (int) $b['duration'] : 180 );
	return array( $start, $start->modify( '+' . $dur . ' minutes' ), false );
}

/**
 * Secret key for the public .ics link.
 */
function twistedoak_catering_ics_key( int $id ): string {
	return substr( hash_hmac( 'sha256', $id . '|' . get_post_meta( $id, '_tobbq_lead_reference', true ), wp_salt( 'auth' ) ), 0, 20 );
}

/**
 * Public .ics download URL for a booking.
 */
function twistedoak_catering_ics_url( int $id ): string {
	return add_query_arg(
		array(
			'action' => 'twistedoak_catering_ics',
			'ref'    => (string) get_post_meta( $id, '_tobbq_lead_reference', true ),
			'key'    => twistedoak_catering_ics_key( $id ),
		),
		admin_url( 'admin-post.php' )
	);
}

/**
 * iCalendar text escape.
 */
function twistedoak_catering_ics_escape( string $text ): string {
	return str_replace( array( '\\', ';', ',', "\r\n", "\n" ), array( '\\\\', '\;', '\,', '\n', '\n' ), $text );
}

/**
 * Build an .ics for a booking.
 */
function twistedoak_catering_ics( int $id ): string {
	$b     = twistedoak_catering_booking( $id );
	$times = twistedoak_catering_booking_times( $b );
	if ( ! $times ) {
		return '';
	}
	[ $start, $end, $all_day ] = $times;
	$utc    = new DateTimeZone( 'UTC' );
	$site   = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	$status = 'confirmed' === $b['status'] || 'completed' === $b['status'] ? 'CONFIRMED' : ( in_array( $b['status'], array( 'declined', 'cancelled' ), true ) ? 'CANCELLED' : 'TENTATIVE' );
	/* translators: %s: site name */
	$summary = sprintf( __( '%s catering', 'twisted-oak-catering' ), $site );
	if ( 'CONFIRMED' !== $status ) {
		$summary .= ' (' . twistedoak_catering_lead_value( $id, 'status' ) . ')';
	}
	$desc = array(
		__( 'Reference', 'twisted-oak-catering' ) . ': ' . $b['reference'],
		__( 'Package', 'twisted-oak-catering' ) . ': ' . $b['package'],
		__( 'Guests', 'twisted-oak-catering' ) . ': ' . $b['guests'],
	);
	if ( $b['service_style'] ) {
		$desc[] = __( 'Service style', 'twisted-oak-catering' ) . ': ' . $b['service_style'];
	}

	$lines = array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//Twisted Oak BBQ//Catering//EN',
		'CALSCALE:GREGORIAN',
		'METHOD:PUBLISH',
		'BEGIN:VEVENT',
		'UID:' . $b['reference'] . '@' . wp_parse_url( home_url(), PHP_URL_HOST ),
		'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
		'SEQUENCE:' . count( (array) get_post_meta( $id, '_tobbq_lead_status_log', true ) ),
		$all_day ? 'DTSTART;VALUE=DATE:' . $start->format( 'Ymd' ) : 'DTSTART:' . $start->setTimezone( $utc )->format( 'Ymd\THis\Z' ),
		$all_day ? 'DTEND;VALUE=DATE:' . $end->format( 'Ymd' ) : 'DTEND:' . $end->setTimezone( $utc )->format( 'Ymd\THis\Z' ),
		'SUMMARY:' . twistedoak_catering_ics_escape( $summary ),
		'DESCRIPTION:' . twistedoak_catering_ics_escape( implode( "\n", $desc ) ),
		'STATUS:' . $status,
	);
	if ( $b['location'] ) {
		$lines[] = 'LOCATION:' . twistedoak_catering_ics_escape( (string) $b['location'] );
	}
	$lines[] = 'URL:' . home_url( '/catering/' );
	$lines[] = 'END:VEVENT';
	$lines[] = 'END:VCALENDAR';

	// Fold lines at 75 octets (RFC 5545 §3.1).
	$folded = array();
	foreach ( $lines as $line ) {
		while ( strlen( $line ) > 75 ) {
			$cut = 75;
			while ( $cut > 1 && ( ord( $line[ $cut ] ) & 0xC0 ) === 0x80 ) {
				--$cut; // Don't split a UTF-8 sequence.
			}
			$folded[] = substr( $line, 0, $cut );
			$line     = ' ' . substr( $line, $cut );
		}
		$folded[] = $line;
	}
	return (string) apply_filters( 'twistedoak_catering_ics', implode( "\r\n", $folded ) . "\r\n", $id );
}

add_action( 'admin_post_nopriv_twistedoak_catering_ics', 'twistedoak_catering_serve_ics' );
add_action( 'admin_post_twistedoak_catering_ics', 'twistedoak_catering_serve_ics' );

/**
 * GET admin-post.php?action=twistedoak_catering_ics&ref=TO-XXXXXX&key=… → text/calendar.
 */
function twistedoak_catering_serve_ics(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- authorised by the HMAC key.
	$ref = isset( $_GET['ref'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['ref'] ) ) ) : '';
	$key = isset( $_GET['key'] ) ? sanitize_key( wp_unslash( $_GET['key'] ) ) : '';
	// phpcs:enable
	$id = preg_match( '/^TO-[A-Z0-9]{6}$/', $ref ) ? twistedoak_catering_find_by_reference( $ref ) : 0;
	if ( ! $id || ! hash_equals( twistedoak_catering_ics_key( $id ), $key ) ) {
		wp_die( esc_html__( 'Calendar file not found.', 'twisted-oak-catering' ), '', array( 'response' => 404 ) );
	}
	$ics = twistedoak_catering_ics( $id );
	if ( '' === $ics ) {
		wp_die( esc_html__( 'This booking has no date yet.', 'twisted-oak-catering' ), '', array( 'response' => 404 ) );
	}
	nocache_headers();
	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="twisted-oak-catering-' . strtolower( $ref ) . '.ics"' );
	echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/calendar body.
	exit;
}

/**
 * Plain-text summary block of a booking.
 */
function twistedoak_catering_booking_summary( int $id, bool $admin = false ): string {
	$skip  = $admin ? array( 'mail_sent' ) : array( 'mail_sent', 'source_url', 'email', 'phone', 'name' );
	$lines = array();
	foreach ( twistedoak_catering_lead_fields() as $key => $label ) {
		if ( in_array( $key, $skip, true ) || 'duration' === $key ) {
			continue;
		}
		$value = twistedoak_catering_lead_value( $id, $key );
		if ( '' !== $value ) {
			$lines[] = $label . ': ' . $value;
		}
	}
	return implode( "\n", $lines );
}

/**
 * Compose + send one email type for a booking.
 *
 * @param array<string, mixed> $extra Extra context (e.g. 'note' => admin message).
 */
function twistedoak_catering_send_email( string $type, int $id, array $extra = array() ): bool {
	$b    = twistedoak_catering_booking( $id );
	$site = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	if ( ! is_email( (string) $b['email'] ) && 'admin_new' !== $type ) {
		return false;
	}
	$when = twistedoak_catering_lead_value( $id, 'event_date' );
	$time = twistedoak_catering_lead_value( $id, 'start_time' );
	$when = trim( $when . ( '' !== $time ? ', ' . $time : '' ) );
	$ics  = '';
	$sign = "\n\n— " . $site . "\n" . home_url( '/' );

	$customer_headers = array();
	$reply            = twistedoak_catering_recipient();
	if ( is_email( $reply ) ) {
		$customer_headers[] = 'Reply-To: ' . $reply;
	}

	switch ( $type ) {
		case 'admin_new':
			$to      = twistedoak_catering_recipient();
			/* translators: 1: customer name, 2: date */
			$subject = sprintf( __( 'New catering request from %1$s%2$s', 'twisted-oak-catering' ), $b['name'], '' !== $when ? ' — ' . $when : '' );
			$message = twistedoak_catering_booking_summary( $id, true ) . "\n\n" . __( 'Manage booking:', 'twisted-oak-catering' ) . ' ' . admin_url( 'post.php?post=' . $id . '&action=edit' );
			$headers = array( sprintf( 'Reply-To: %s <%s>', str_replace( array( '<', '>', '"', "\r", "\n" ), '', (string) $b['name'] ), $b['email'] ) );
			break;

		case 'customer_received':
			$to = (string) $b['email'];
			/* translators: 1: site name, 2: reference */
			$subject = sprintf( __( '%1$s — we received your catering request (%2$s)', 'twisted-oak-catering' ), $site, $b['reference'] );
			$intro   = 'held' === $b['status']
				? __( 'Thanks! We have placed a tentative hold on your date while we review the details. We will confirm within 2 business days.', 'twisted-oak-catering' )
				: __( 'Thanks! We received your catering request and will be in touch within 2 business days.', 'twisted-oak-catering' );
			/* translators: %s: customer name */
			$message = sprintf( __( 'Hi %s,', 'twisted-oak-catering' ), $b['name'] ) . "\n\n" . $intro . "\n\n" . twistedoak_catering_booking_summary( $id );
			$ics     = twistedoak_catering_ics( $id );
			if ( '' !== $ics ) {
				$message .= "\n\n" . __( 'Add it to your calendar:', 'twisted-oak-catering' ) . ' ' . twistedoak_catering_ics_url( $id );
			}
			$message .= $sign;
			$headers  = $customer_headers;
			break;

		case 'customer_confirmed':
		case 'customer_declined':
		case 'customer_cancelled':
			$to    = (string) $b['email'];
			$intro = match ( $type ) {
				/* translators: %s: date/time */
				'customer_confirmed' => sprintf( __( 'Good news — your catering booking for %s is confirmed. We will reach out about final menu details and payment.', 'twisted-oak-catering' ), $when ),
				/* translators: %s: date/time */
				'customer_declined'  => sprintf( __( 'Unfortunately we are not able to cater your event on %s. We are sorry — please reply if another date could work.', 'twisted-oak-catering' ), $when ),
				/* translators: %s: date/time */
				default              => sprintf( __( 'Your catering booking for %s has been cancelled. Reply to this email if that is unexpected.', 'twisted-oak-catering' ), $when ),
			};
			$subject = match ( $type ) {
				/* translators: 1: site name, 2: reference */
				'customer_confirmed' => sprintf( __( '%1$s — your catering booking is confirmed (%2$s)', 'twisted-oak-catering' ), $site, $b['reference'] ),
				/* translators: 1: site name, 2: reference */
				'customer_declined'  => sprintf( __( '%1$s — about your catering request (%2$s)', 'twisted-oak-catering' ), $site, $b['reference'] ),
				/* translators: 1: site name, 2: reference */
				default              => sprintf( __( '%1$s — catering booking cancelled (%2$s)', 'twisted-oak-catering' ), $site, $b['reference'] ),
			};
			/* translators: %s: customer name */
			$message = sprintf( __( 'Hi %s,', 'twisted-oak-catering' ), $b['name'] ) . "\n\n" . $intro;
			if ( ! empty( $extra['note'] ) ) {
				$message .= "\n\n" . (string) $extra['note'];
			}
			$message .= "\n\n" . twistedoak_catering_booking_summary( $id );
			if ( 'customer_confirmed' === $type ) {
				$ics = twistedoak_catering_ics( $id );
				if ( '' !== $ics ) {
					$message .= "\n\n" . __( 'Add it to your calendar:', 'twisted-oak-catering' ) . ' ' . twistedoak_catering_ics_url( $id );
				}
			}
			$message .= $sign;
			$headers  = $customer_headers;
			break;

		default:
			return false;
	}

	$attachments = array();
	$tmp         = '';
	if ( '' !== $ics ) {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$tmp = wp_tempnam( 'tobbq-catering.ics' );
		if ( $tmp && false !== file_put_contents( $tmp, $ics ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$attachments[ 'twisted-oak-catering-' . strtolower( (string) $b['reference'] ) . '.ics' ] = $tmp;
		}
	}

	$email = array(
		'to'          => $to,
		'subject'     => wp_specialchars_decode( $subject, ENT_QUOTES ),
		'message'     => $message,
		'headers'     => $headers,
		'attachments' => $attachments,
	);
	$email = apply_filters( 'twistedoak_catering_email_' . $type, $email, $id, $b, $extra );
	$email = apply_filters( 'twistedoak_catering_email', $email, $type, $id, $b, $extra );

	$sent = false;
	if ( is_array( $email ) && ! empty( $email['to'] ) ) {
		$sent = (bool) wp_mail( $email['to'], $email['subject'], $email['message'], $email['headers'], $email['attachments'] );
	}
	if ( $tmp && file_exists( $tmp ) ) {
		wp_delete_file( $tmp );
	}
	return $sent;
}
