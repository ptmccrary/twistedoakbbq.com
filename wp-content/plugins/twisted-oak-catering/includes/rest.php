<?php
/**
 * REST routes (namespace twisted-oak/v1):
 *   GET  /availability?from=Y-m-d&to=Y-m-d&guests=N
 *   POST /bookings                 (booking mode: date + start_time + guests required)
 *   GET  /catering/config          (public form config, no nonces)
 *   POST /catering                 (legacy inquiry endpoint used by the v1 form JS; date/time optional)
 *
 * See themes/twistedoak/SCHEDULING.md for the contract.
 *
 * @package TwistedOakCatering
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const TWISTEDOAK_CATERING_MAX_RANGE_DAYS = 93;

add_action(
	'rest_api_init',
	static function (): void {
		$date_arg = static fn( string $desc ): array => array(
			'type'              => 'string',
			'description'       => $desc,
			'pattern'           => '^\d{4}-\d{2}-\d{2}$',
			'validate_callback' => static fn( $v ): bool => twistedoak_catering_is_date( $v ),
		);

		register_rest_route(
			'twisted-oak/v1',
			'/availability',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'twistedoak_catering_rest_availability',
				'permission_callback' => '__return_true',
				'args'                => array(
					'from'   => $date_arg( 'First day (Y-m-d). Defaults to today.' ),
					'to'     => $date_arg( 'Last day (Y-m-d), inclusive. Defaults to from + 41 days. Max range 93 days.' ),
					'guests' => array(
						'type'              => 'integer',
						'minimum'           => 0,
						'maximum'           => 10000,
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'twisted-oak/v1',
			'/bookings',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => static fn( WP_REST_Request $r ): WP_REST_Response => twistedoak_catering_rest_submit( $r, true ),
				'permission_callback' => '__return_true', // Public form; nonce + honeypot + rate limit checked in the pipeline.
			)
		);

		register_rest_route(
			'twisted-oak/v1',
			'/catering/config',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static fn(): WP_REST_Response => new WP_REST_Response( twistedoak_catering_public_config( false ) ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'twisted-oak/v1',
			'/catering',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => static fn( WP_REST_Request $r ): WP_REST_Response => twistedoak_catering_rest_submit( $r, false ),
				'permission_callback' => '__return_true', // Public form; nonce verified in callback.
			)
		);
	}
);

/**
 * GET /availability.
 */
function twistedoak_catering_rest_availability( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$today = twistedoak_catering_today();
	$from  = (string) ( $request->get_param( 'from' ) ?? $today->format( 'Y-m-d' ) );
	$to    = (string) ( $request->get_param( 'to' ) ?? ( new DateTimeImmutable( $from, wp_timezone() ) )->modify( '+41 days' )->format( 'Y-m-d' ) );
	if ( $to < $from ) {
		return new WP_Error( 'invalid_range', __( '"to" must be on or after "from".', 'twisted-oak-catering' ), array( 'status' => 400 ) );
	}
	$span = (int) ( new DateTimeImmutable( $from ) )->diff( new DateTimeImmutable( $to ) )->days + 1;
	if ( $span > TWISTEDOAK_CATERING_MAX_RANGE_DAYS ) {
		/* translators: %d: max days */
		return new WP_Error( 'range_too_large', sprintf( __( 'Request at most %d days at a time.', 'twisted-oak-catering' ), TWISTEDOAK_CATERING_MAX_RANGE_DAYS ), array( 'status' => 400 ) );
	}
	$guests = (int) $request->get_param( 'guests' );
	$s      = twistedoak_catering_schedule();

	$response = new WP_REST_Response(
		array(
			'from'     => $from,
			'to'       => $to,
			'guests'   => $guests,
			'timezone' => wp_timezone_string(),
			'today'    => $today->format( 'Y-m-d' ),
			'earliest' => twistedoak_catering_earliest_date()->format( 'Y-m-d' ),
			'latest'   => twistedoak_catering_latest_date()->format( 'Y-m-d' ),
			'rules'    => array(
				'weekdays'      => $s['weekdays'],
				'leadDays'      => (int) $s['lead_days'],
				'horizonMonths' => (int) $s['horizon_months'],
				'minGuests'     => (int) $s['min_guests'],
				'maxGuests'     => (int) $s['max_guests'],
			),
			'days'     => twistedoak_catering_availability( $from, $to, $guests ),
		)
	);
	$response->header( 'Cache-Control', 'no-cache, must-revalidate, max-age=0' );
	return $response;
}

/**
 * POST /bookings and legacy POST /catering.
 */
function twistedoak_catering_rest_submit( WP_REST_Request $request, bool $require_slot ): WP_REST_Response {
	$result = twistedoak_catering_process( $request->get_params(), $require_slot );

	if ( $result['ok'] ) {
		$body = array(
			'success'   => true,
			'message'   => (string) twistedoak_catering_setting( 'success_message' ),
			'reference' => (string) ( $result['reference'] ?? '' ),
			'status'        => twistedoak_catering_public_status( (string) ( $result['status'] ?? 'pending' ) ),
			'public_status' => twistedoak_catering_public_status( (string) ( $result['status'] ?? 'pending' ) ),
		);
		if ( ! empty( $result['lead_id'] ) ) {
			$body['booking'] = twistedoak_catering_public_booking( (int) $result['lead_id'] );
		}
		return new WP_REST_Response( $body, $require_slot ? 201 : 200 );
	}

	$status = match ( $result['code'] ) {
		'nonce'                           => 403,
		'rate_limited'                    => 429,
		'unavailable', 'date_unavailable' => 409,
		'busy'                            => 503,
		'save_failed'                     => 500,
		default                           => 400,
	};

	$body = array(
		'success' => false,
		'code'    => $result['code'],
		'error'   => twistedoak_catering_error_message( $result['code'] ),
		'errors'  => (object) $result['errors'],
	);
	if ( isset( $result['day'] ) ) {
		$body['day']            = $result['day'];
		$body['next_available'] = twistedoak_catering_next_available( (string) $result['day']['date'], (int) ( $result['data']['guests'] ?? 0 ) );
	}
	$response = new WP_REST_Response( $body, $status );
	if ( 503 === $status ) {
		$response->header( 'Retry-After', '2' );
	}
	return $response;
}
