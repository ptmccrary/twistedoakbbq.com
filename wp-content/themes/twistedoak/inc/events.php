<?php
/**
 * Events (tobbq_event): queries and presentation helpers.
 *
 * Dates are stored by twisted-oak-core as "Y-m-d H:i" in the site timezone, so string comparison
 * sorts correctly.
 *
 * @package TwistedOak
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * The events archive and event-type archives list upcoming events only, soonest first.
 */
add_action(
	'pre_get_posts',
	static function ( WP_Query $q ): void {
		if ( is_admin() || ! $q->is_main_query() ) {
			return;
		}
		if ( $q->is_post_type_archive( 'tobbq_event' ) || $q->is_tax( 'tobbq_event_type' ) ) {
			$q->set( 'posts_per_page', 50 );
			$q->set( 'meta_key', '_tobbq_event_start' );
			$q->set( 'orderby', 'meta_value' );
			$q->set( 'order', 'ASC' );
			$q->set( 'meta_query', array( twistedoak_event_upcoming_clause() ) );
		}
	}
);

/**
 * Meta clause: event hasn't ended (falls back to start when there is no end).
 *
 * @return array<string, mixed>
 */
function twistedoak_event_upcoming_clause(): array {
	$today = wp_date( 'Y-m-d 00:00' );
	return array(
		'relation' => 'OR',
		array(
			'key'     => '_tobbq_event_start',
			'value'   => $today,
			'compare' => '>=',
		),
		array(
			'key'     => '_tobbq_event_end',
			'value'   => $today,
			'compare' => '>=',
		),
	);
}

/**
 * Upcoming events.
 *
 * @return WP_Post[]
 */
function twistedoak_upcoming_events( int $limit = 1 ): array {
	return get_posts(
		array(
			'post_type'      => 'tobbq_event',
			'posts_per_page' => $limit,
			'meta_key'       => '_tobbq_event_start', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => array( twistedoak_event_upcoming_clause() ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
}

/**
 * Most recent past events.
 *
 * @return WP_Post[]
 */
function twistedoak_past_events( int $limit = 4 ): array {
	return get_posts(
		array(
			'post_type'      => 'tobbq_event',
			'posts_per_page' => $limit,
			'meta_key'       => '_tobbq_event_start', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'        => 'meta_value',
			'order'          => 'DESC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'     => '_tobbq_event_start',
					'value'   => wp_date( 'Y-m-d 00:00' ),
					'compare' => '<',
				),
			),
		)
	);
}

/**
 * Everything a template needs to show one event.
 *
 * @return array{title: string, url: string, mon: string, day: string, dow: string, date_long: string, time: string, location: string, link: string, type: string, type_slug: string, excerpt: string, start: ?DateTimeImmutable, end: ?DateTimeImmutable}
 */
function twistedoak_event_data( WP_Post $post ): array {
	$tz    = wp_timezone();
	$parse = static function ( string $v ) use ( $tz ): ?DateTimeImmutable {
		$d = '' !== $v ? DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $v, $tz ) : false;
		return $d ?: null;
	};
	$start = $parse( (string) get_post_meta( $post->ID, '_tobbq_event_start', true ) );
	$end   = $parse( (string) get_post_meta( $post->ID, '_tobbq_event_end', true ) );
	$terms = get_the_terms( $post, 'tobbq_event_type' );
	$term  = is_array( $terms ) && $terms ? $terms[0] : null;

	$time = '';
	if ( $start ) {
		$fmt  = static fn( DateTimeImmutable $d ): string => '00' === $d->format( 'i' ) ? $d->format( 'ga' ) : $d->format( 'g:ia' );
		$time = $fmt( $start );
		if ( $end && $end->format( 'Y-m-d' ) === $start->format( 'Y-m-d' ) ) {
			$time .= '–' . $fmt( $end );
		} elseif ( $end ) {
			$time .= ' – ' . wp_date( 'M j, ', $end->getTimestamp() ) . $fmt( $end );
		}
	}

	return array(
		'title'     => get_the_title( $post ),
		'url'       => (string) get_permalink( $post ),
		'mon'       => $start ? wp_date( 'M', $start->getTimestamp() ) : '',
		'day'       => $start ? wp_date( 'j', $start->getTimestamp() ) : '',
		'dow'       => $start ? wp_date( 'D', $start->getTimestamp() ) : '',
		'date_long' => $start ? wp_date( 'l, F j', $start->getTimestamp() ) : '',
		'time'      => $time,
		'location'  => (string) get_post_meta( $post->ID, '_tobbq_event_location', true ),
		'link'      => (string) get_post_meta( $post->ID, '_tobbq_event_link', true ),
		'type'      => $term ? $term->name : '',
		'type_slug' => $term ? $term->slug : '',
		'excerpt'   => has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 22 ),
		'start'     => $start,
		'end'       => $end,
	);
}

/**
 * Google Calendar "add event" link.
 *
 * @param array<string, mixed> $ev Event data.
 */
function twistedoak_event_calendar_url( array $ev ): string {
	if ( empty( $ev['start'] ) ) {
		return '';
	}
	$utc   = new DateTimeZone( 'UTC' );
	$start = $ev['start']->setTimezone( $utc );
	$end   = ( $ev['end'] ?? null ) ? $ev['end']->setTimezone( $utc ) : $start->modify( '+3 hours' );
	return add_query_arg(
		array(
			'action'   => 'TEMPLATE',
			'text'     => rawurlencode( $ev['title'] . ' — Twisted Oak Barbecue' ),
			'dates'    => $start->format( 'Ymd\THis\Z' ) . '/' . $end->format( 'Ymd\THis\Z' ),
			'location' => rawurlencode( (string) $ev['location'] ),
			'details'  => rawurlencode( (string) $ev['url'] ),
		),
		'https://calendar.google.com/calendar/render'
	);
}

/**
 * Google Maps directions link for a location string.
 */
function twistedoak_directions_url( string $location ): string {
	return '' === $location ? '' : 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $location );
}

/**
 * Date chip (decorative; the full date is repeated for screen readers next to it).
 *
 * @param array<string, mixed> $ev Event data.
 */
function twistedoak_event_chip( array $ev, string $variant = '' ): string {
	if ( '' === $ev['mon'] ) {
		return '';
	}
	return sprintf(
		'<div class="date-chip%s" aria-hidden="true"><span class="date-chip__mon">%s</span><span class="date-chip__day">%s</span><span class="date-chip__dow">%s</span></div>',
		$variant ? ' date-chip--' . esc_attr( $variant ) : '',
		esc_html( $ev['mon'] ),
		esc_html( $ev['day'] ),
		esc_html( $ev['dow'] )
	);
}

/**
 * Event types that have upcoming events (for the filter row).
 *
 * @return WP_Term[]
 */
function twistedoak_event_filter_terms(): array {
	$ids = wp_list_pluck( twistedoak_upcoming_events( 200 ), 'ID' );
	if ( ! $ids ) {
		return array();
	}
	$terms = wp_get_object_terms( $ids, 'tobbq_event_type', array( 'orderby' => 'name' ) );
	return is_wp_error( $terms ) ? array() : array_values( $terms );
}
