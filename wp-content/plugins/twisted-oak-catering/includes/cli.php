<?php
/**
 * WP-CLI: `wp twisted-oak-catering seed` — idempotent placeholder schedule + bookings.
 *
 * @package TwistedOakCatering
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Placeholder scheduling data.
 */
final class TwistedOak_Catering_CLI {

	/**
	 * Seed placeholder availability settings and ~7 placeholder bookings over the next 6 weeks.
	 *
	 * Re-running updates the same bookings (matched by _tobbq_lead_seed_key) with dates
	 * recomputed from today. Never sends email.
	 *
	 * ## OPTIONS
	 *
	 * [--settings-only]
	 * : Only (re)write the availability settings.
	 *
	 * [--remove]
	 * : Delete the seeded bookings instead.
	 *
	 * @param array<int, string>    $args       Positional.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function seed( array $args, array $assoc_args ): void {
		if ( ! empty( $assoc_args['remove'] ) ) {
			$n = 0;
			foreach ( $this->seeded_ids() as $id ) {
				wp_delete_post( $id, true );
				++$n;
			}
			WP_CLI::success( "Removed {$n} seeded bookings." );
			return;
		}

		if ( '' === (string) get_option( 'timezone_string' ) ) {
			update_option( 'timezone_string', 'America/New_York' );
			WP_CLI::log( 'Site timezone was a raw UTC offset; set to America/New_York (Charlottesville).' );
		}

		$today = current_datetime()->setTime( 0, 0 );
		$next  = static function ( DateTimeImmutable $from, int $iso_weekday ): DateTimeImmutable {
			$diff = ( $iso_weekday - (int) $from->format( 'N' ) + 7 ) % 7;
			return $from->modify( '+' . $diff . ' days' );
		};

		// Blackouts: a single Saturday ~3 weeks out, a Thu–Sun range ~7 weeks out, plus Thanksgiving if ahead.
		$b_single = $next( $today->modify( '+18 days' ), 6 );
		$b_range  = $next( $today->modify( '+46 days' ), 4 );
		$blackouts = array(
			array(
				'start' => $b_single->format( 'Y-m-d' ),
				'end'   => $b_single->format( 'Y-m-d' ),
				'label' => 'Private event booked',
			),
			array(
				'start' => $b_range->format( 'Y-m-d' ),
				'end'   => $b_range->modify( '+3 days' )->format( 'Y-m-d' ),
				'label' => 'Pit maintenance',
			),
		);
		$thanksgiving = new DateTimeImmutable( 'fourth thursday of november ' . $today->format( 'Y' ), wp_timezone() );
		if ( $thanksgiving > $today ) {
			$blackouts[] = array(
				'start' => $thanksgiving->format( 'Y-m-d' ),
				'end'   => $thanksgiving->format( 'Y-m-d' ),
				'label' => 'Thanksgiving',
			);
		}

		// Overrides: open one Wednesday (corporate lunch slot), close one Friday, bump one Sunday's capacity.
		$o_open  = $next( $today->modify( '+12 days' ), 3 );
		$o_close = $next( $today->modify( '+26 days' ), 5 );
		$o_cap   = $next( $today->modify( '+33 days' ), 7 );
		$overrides = array(
			$o_open->format( 'Y-m-d' )  => array(
				'mode'     => 'open',
				'capacity' => 1,
				'slots'    => array(
					array(
						'time'     => '11:30',
						'duration' => 120,
					),
				),
				'note'     => 'Opened for corporate lunch demand',
			),
			$o_close->format( 'Y-m-d' ) => array(
				'mode'     => 'closed',
				'capacity' => null,
				'slots'    => null,
				'note'     => 'Staff party',
			),
			$o_cap->format( 'Y-m-d' )   => array(
				'mode'     => 'custom',
				'capacity' => 3,
				'slots'    => array(
					array(
						'time'     => '11:00',
						'duration' => 180,
					),
					array(
						'time'     => '14:30',
						'duration' => 180,
					),
					array(
						'time'     => '17:30',
						'duration' => 180,
					),
				),
				'note'     => 'Extra crew scheduled',
			),
		);

		$schedule = array_merge(
			twistedoak_catering_schedule_defaults(),
			array(
				'blackouts' => $blackouts,
				'overrides' => $overrides,
			)
		);
		update_option( TWISTEDOAK_CATERING_SCHEDULE_OPTION, twistedoak_catering_sanitize_schedule( $schedule ), false );
		twistedoak_catering_schedule( true );
		WP_CLI::log( 'Availability settings written: Thu–Sun, capacity 2/day, 7-day lead, 6-month horizon, ' . count( $blackouts ) . ' blackouts, ' . count( $overrides ) . ' overrides.' );

		if ( ! empty( $assoc_args['settings-only'] ) ) {
			WP_CLI::success( 'Done.' );
			return;
		}

		$blocked = static function ( DateTimeImmutable $d ) use ( $blackouts, $overrides ): bool {
			$ymd = $d->format( 'Y-m-d' );
			foreach ( $blackouts as $b ) {
				if ( $ymd >= $b['start'] && $ymd <= $b['end'] ) {
					return true;
				}
			}
			return isset( $overrides[ $ymd ] );
		};
		$pick = static function ( int $offset, int $weekday ) use ( $today, $next, $blocked ): DateTimeImmutable {
			$d = $next( $today->modify( '+' . $offset . ' days' ), $weekday );
			while ( $blocked( $d ) ) {
				$d = $d->modify( '+7 days' );
			}
			return $d;
		};

		$full_sat = $pick( 8, 6 );
		$lim_fri  = $pick( 14, 5 );
		$lim_sun  = $pick( 21, 7 );
		$pend_thu = $pick( 28, 4 );
		$mix_sat  = $pick( 34, 6 );
		$past     = $today->modify( '-5 days' );

		$bookings = array(
			'seed:1' => array( $full_sat, '11:00', 180, 'confirmed', 'Avery Placeholder', 'Monticello Smokehouse', 120, 'Onsite setup', 'Private residence, Ivy, VA' ),
			'seed:2' => array( $full_sat, '17:00', 240, 'held', 'Jordan Sample', 'Jefferson Reserve', 180, 'Full service (staffed)', 'Barn venue, Crozet, VA' ),
			'seed:3' => array( $lim_fri, '17:30', 210, 'confirmed', 'Sam Example', 'Belmont Classic', 60, 'Drop-off', 'Office park, Charlottesville, VA' ),
			'seed:4' => array( $lim_sun, '11:00', 240, 'held', 'Riley Testcase', 'Monticello Smokehouse', 90, 'Onsite setup', 'Brewery lot, Charlottesville, VA' ),
			'seed:5' => array( $pend_thu, '17:00', 180, 'pending', 'Casey Demo', 'Custom', 40, 'Drop-off', 'Church hall, Charlottesville, VA' ),
			'seed:6' => array( $mix_sat, '17:00', 240, 'confirmed', 'Morgan Mock', 'Jefferson Reserve', 220, 'Full service (staffed)', 'Winery, Afton, VA' ),
			'seed:7' => array( $mix_sat, '11:00', 180, 'declined', 'Taylor Stub', 'Belmont Classic', 300, 'Onsite setup', 'Park pavilion, Charlottesville, VA' ),
			'seed:8' => array( $past, '11:00', 180, 'completed', 'Jamie Former', 'Belmont Classic', 75, 'Drop-off', 'School gym, Charlottesville, VA' ),
		);

		$existing = array();
		foreach ( $this->seeded_ids() as $id ) {
			$existing[ (string) get_post_meta( $id, '_tobbq_lead_seed_key', true ) ] = $id;
		}

		$created = 0;
		$updated = 0;
		foreach ( $bookings as $key => [ $date, $time, $dur, $status, $name, $package, $guests, $style, $location ] ) {
			$slug = sanitize_title( $name );
			$meta = array(
				'_tobbq_lead_seed_key'      => $key,
				'_tobbq_lead_name'          => $name,
				'_tobbq_lead_email'         => $slug . '@example.com',
				'_tobbq_lead_phone'         => '434-555-01' . substr( (string) crc32( $key ), -2 ),
				'_tobbq_lead_event_date'    => $date->format( 'Y-m-d' ),
				'_tobbq_lead_start_time'    => $time,
				'_tobbq_lead_duration'      => (string) $dur,
				'_tobbq_lead_guests'        => (string) $guests,
				'_tobbq_lead_service_style' => $style,
				'_tobbq_lead_location'      => $location,
				'_tobbq_lead_package'       => $package,
				'_tobbq_lead_meats'         => array( 'Pulled Pork', 'Smoked Half Chicken' ),
				'_tobbq_lead_sides'         => array( 'White Cheddar Mac & Cheese', 'Southern Slaw' ),
				'_tobbq_lead_desserts'      => array( 'Banana Pudding' ),
				'_tobbq_lead_message'       => 'Placeholder booking created by `wp twisted-oak-catering seed`.',
				'_tobbq_lead_status'        => $status,
				'_tobbq_lead_mail_sent'     => '1',
			);
			$postarr = array(
				'post_type'   => TWISTEDOAK_CATERING_CPT,
				'post_status' => 'publish',
				'post_title'  => $date->format( 'Y-m-d' ) . ' · ' . $name . ' — ' . $package,
				'meta_input'  => $meta,
			);
			if ( isset( $existing[ $key ] ) ) {
				$postarr['ID'] = $existing[ $key ];
				wp_update_post( $postarr );
				++$updated;
			} else {
				$meta['_tobbq_lead_reference']  = twistedoak_catering_generate_reference();
				$meta['_tobbq_lead_status_log'] = array(
					array(
						'time' => time(),
						'from' => '',
						'to'   => $status,
						'user' => 0,
					),
				);
				$postarr['meta_input'] = $meta;
				wp_insert_post( $postarr );
				++$created;
			}
		}

		WP_CLI::success( sprintf( 'Bookings: %d created, %d updated. Full day: %s; limited: %s, %s, %s.', $created, $updated, $full_sat->format( 'D Y-m-d' ), $lim_fri->format( 'D Y-m-d' ), $lim_sun->format( 'D Y-m-d' ), $mix_sat->format( 'D Y-m-d' ) ) );
	}

	/**
	 * @return int[]
	 */
	private function seeded_ids(): array {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'   => TWISTEDOAK_CATERING_CPT,
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
					'meta_key'    => '_tobbq_lead_seed_key', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				)
			)
		);
	}
}

WP_CLI::add_command( 'twisted-oak-catering', 'TwistedOak_Catering_CLI' );
