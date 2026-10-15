<?php
/**
 * Schedule option parsing / sanitising (includes/schedule.php).
 *
 * @package TwistedOak\Tests
 */

declare(strict_types=1);

namespace TwistedOak\Tests\Unit\Catering;

use PHPUnit\Framework\Attributes\DataProvider;
use TwistedOak\Tests\Unit\TestCase;

final class ScheduleParsingTest extends TestCase {

	/** @return iterable<string, array{mixed, bool}> */
	public static function dates(): iterable {
		yield 'valid'          => array( '2026-10-15', true );
		yield 'leap day'       => array( '2028-02-29', true );
		yield 'non-leap day'   => array( '2026-02-29', false );
		yield 'overflow day'   => array( '2026-02-30', false );
		yield 'no zero pad'    => array( '2026-1-5', false );
		yield 'datetime'       => array( '2026-10-15 10:00', false );
		yield 'empty'          => array( '', false );
		yield 'not a string'   => array( 20261015, false );
		yield 'null'           => array( null, false );
	}

	#[DataProvider( 'dates' )]
	public function test_is_date( mixed $value, bool $expected ): void {
		self::assertSame( $expected, twistedoak_catering_is_date( $value ) );
	}

	/** @return iterable<string, array{mixed, string}> */
	public static function times(): iterable {
		yield 'pads hour'        => array( '9:30', '09:30' );
		yield 'already padded'   => array( '17:00', '17:00' );
		yield 'trims'            => array( ' 7:05 ', '07:05' );
		yield 'last minute'      => array( '23:59', '23:59' );
		yield 'hour out of range' => array( '24:00', '' );
		yield 'minute out of range' => array( '12:60', '' );
		yield 'one-digit minute' => array( '12:5', '' );
		yield 'seconds'          => array( '12:00:00', '' );
		yield 'garbage'          => array( 'noon', '' );
		yield 'not a string'     => array( 1200, '' );
	}

	#[DataProvider( 'times' )]
	public function test_normalise_time( mixed $value, string $expected ): void {
		self::assertSame( $expected, twistedoak_catering_normalise_time( $value ) );
	}

	public function test_parse_slots_accepts_every_text_separator_and_sorts_by_time(): void {
		$slots = twistedoak_catering_parse_slots( "17:00 240\n11:00/180;12:30,90\r\n 9:15|60 " );

		self::assertSame(
			array(
				array( 'time' => '09:15', 'duration' => 60 ),
				array( 'time' => '11:00', 'duration' => 180 ),
				array( 'time' => '12:30', 'duration' => 90 ),
				array( 'time' => '17:00', 'duration' => 240 ),
			),
			$slots
		);
	}

	public function test_parse_slots_defaults_and_clamps_duration(): void {
		$slots = twistedoak_catering_parse_slots( "10:00\n11:00 5\n12:00 9999" );

		self::assertSame( array( 180, 30, 720 ), array_column( $slots, 'duration' ) );
	}

	public function test_parse_slots_drops_invalid_times_and_dedupes_by_time(): void {
		$slots = twistedoak_catering_parse_slots(
			array(
				array( 'time' => '11:00', 'duration' => 120 ),
				array( 'time' => '25:00', 'duration' => 120 ),
				'not a row',
				array( 'duration' => 120 ),
				array( 'time' => '11:00', 'duration' => 240 ),
			)
		);

		self::assertSame( array( array( 'time' => '11:00', 'duration' => 240 ) ), $slots );
	}

	public function test_parse_slots_rejects_non_list_input(): void {
		self::assertSame( array(), twistedoak_catering_parse_slots( null ) );
		self::assertSame( array(), twistedoak_catering_parse_slots( "\n\n" ) );
	}

	public function test_parse_blackouts_reads_single_days_and_every_range_syntax(): void {
		$blackouts = twistedoak_catering_parse_blackouts(
			"2026-12-24..2026-12-26 Holiday break\n"
			. "2026-11-26 Thanksgiving\n"
			. "2027-01-01 – 2027-01-02 New Year\n"
			. "2027-02-01 to 2027-02-03\n"
			. "not a date\n"
		);

		self::assertSame(
			array(
				array( 'start' => '2026-11-26', 'end' => '2026-11-26', 'label' => 'Thanksgiving' ),
				array( 'start' => '2026-12-24', 'end' => '2026-12-26', 'label' => 'Holiday break' ),
				array( 'start' => '2027-01-01', 'end' => '2027-01-02', 'label' => 'New Year' ),
				array( 'start' => '2027-02-01', 'end' => '2027-02-03', 'label' => '' ),
			),
			$blackouts
		);
	}

	public function test_parse_blackouts_swaps_reversed_ranges_and_repairs_bad_end_dates(): void {
		$blackouts = twistedoak_catering_parse_blackouts(
			array(
				array( 'start' => '2026-12-26', 'end' => '2026-12-24', 'label' => 'Reversed' ),
				array( 'start' => '2026-11-26', 'end' => '2026-02-30', 'label' => 'Bad end' ),
				array( 'start' => '2026-02-30', 'end' => '2026-03-01', 'label' => 'Bad start' ),
			)
		);

		self::assertSame(
			array(
				array( 'start' => '2026-11-26', 'end' => '2026-11-26', 'label' => 'Bad end' ),
				array( 'start' => '2026-12-24', 'end' => '2026-12-26', 'label' => 'Reversed' ),
			),
			$blackouts
		);
	}

	public function test_parse_blackouts_sanitises_and_truncates_labels(): void {
		$blackouts = twistedoak_catering_parse_blackouts(
			array( array( 'start' => '2026-11-26', 'label' => '<b>Thanks</b>giving ' . str_repeat( 'x', 200 ) ) )
		);

		self::assertSame( 120, mb_strlen( $blackouts[0]['label'] ) );
		self::assertStringStartsWith( 'Thanksgiving x', $blackouts[0]['label'] );
	}

	public function test_parse_overrides_reads_the_documented_text_syntax(): void {
		$overrides = twistedoak_catering_parse_overrides(
			"2026-10-23 closed Staff party\n"
			. "2026-10-14 open slots=11:00/180,17:00/240 capacity=1 Corporate lunch\n"
			. "2026-10-31 capacity=3\n"
		);

		self::assertSame(
			array(
				'2026-10-14' => array(
					'mode'     => 'open',
					'capacity' => 1,
					'slots'    => array(
						array( 'time' => '11:00', 'duration' => 180 ),
						array( 'time' => '17:00', 'duration' => 240 ),
					),
					'note'     => 'Corporate lunch',
				),
				'2026-10-23' => array(
					'mode'     => 'closed',
					'capacity' => null,
					'slots'    => null,
					'note'     => 'Staff party',
				),
				'2026-10-31' => array(
					'mode'     => 'custom',
					'capacity' => 3,
					'slots'    => null,
					'note'     => '',
				),
			),
			$overrides
		);
	}

	public function test_parse_overrides_drops_empty_custom_rows_and_invalid_dates(): void {
		$overrides = twistedoak_catering_parse_overrides(
			"2026-10-31 Just a note\n"
			. "2026-02-30 closed\n"
			. "2026-11-01 custom slots=nonsense\n"
		);

		self::assertSame( array(), $overrides );
	}

	public function test_parse_overrides_keeps_zero_capacity_and_clamps_large_capacity(): void {
		$overrides = twistedoak_catering_parse_overrides( "2026-10-31 capacity=0\n2026-11-01 capacity=999" );

		self::assertSame( 0, $overrides['2026-10-31']['capacity'] );
		self::assertSame( 50, $overrides['2026-11-01']['capacity'] );
	}

	public function test_parse_overrides_first_mode_word_wins_and_later_ones_become_note(): void {
		$overrides = twistedoak_catering_parse_overrides( '2026-10-23 CLOSED open house' );

		self::assertSame( 'closed', $overrides['2026-10-23']['mode'] );
		self::assertSame( 'open house', $overrides['2026-10-23']['note'] );
	}

	public function test_sanitize_schedule_is_idempotent_for_defaults(): void {
		$once = twistedoak_catering_sanitize_schedule( twistedoak_catering_schedule_defaults() );

		self::assertSame( $once, twistedoak_catering_sanitize_schedule( $once ) );
	}

	public function test_sanitize_schedule_is_idempotent_for_admin_form_input(): void {
		$form = array(
			'weekdays'           => array( '7', '5', '5', '9', '0' ),
			'slots'              => array( '5' => "17:30 210\n11:30 150", '6' => '11:00' ),
			'capacity'           => '3',
			'blackouts'          => "2026-11-26 Thanksgiving\n2026-12-24..2026-12-26 Holiday break",
			'overrides'          => "2026-10-23 closed Staff party\n2026-10-31 capacity=1",
			'consuming_statuses' => array( 'confirmed', 'tentative', 'bogus' ),
			'service_styles'     => "Pickup\nDrop-off\n\nPickup",
			'auto_hold'          => '1',
		);
		$once = twistedoak_catering_sanitize_schedule( $form );

		self::assertSame( $once, twistedoak_catering_sanitize_schedule( $once ) );
		self::assertSame( array( 5, 7 ), $once['weekdays'] );
		self::assertSame( array( '11:30', '17:30' ), array_column( $once['slots'][5], 'time' ) );
		self::assertSame( array( array( 'time' => '11:00', 'duration' => 180 ) ), $once['slots'][6] );
		self::assertSame( array(), $once['slots'][1] );
		self::assertSame( 3, $once['capacity'] );
		self::assertSame( array( 'held', 'confirmed' ), $once['consuming_statuses'], 'v2.0 "tentative" alias maps to "held"; unknown statuses are dropped.' );
		self::assertSame( array( 'Pickup', 'Drop-off' ), $once['service_styles'] );
		self::assertTrue( $once['auto_hold'] );
	}

	public function test_sanitize_schedule_clamps_integers_and_falls_back_to_defaults(): void {
		$s = twistedoak_catering_sanitize_schedule(
			array(
				'capacity'       => 500,
				'slot_capacity'  => 0,
				'lead_days'      => -4,
				'horizon_months' => 99,
			)
		);

		self::assertSame( 50, $s['capacity'] );
		self::assertSame( 1, $s['slot_capacity'] );
		self::assertSame( 0, $s['lead_days'] );
		self::assertSame( 36, $s['horizon_months'] );
		self::assertSame( 10, $s['min_guests'], 'Missing keys use the defaults.' );
		self::assertFalse( $s['auto_hold'] );
		self::assertSame( array(), $s['weekdays'] );
	}

	public function test_sanitize_schedule_treats_non_arrays_as_empty(): void {
		self::assertSame( twistedoak_catering_sanitize_schedule( array() ), twistedoak_catering_sanitize_schedule( 'garbage' ) );
	}

	public function test_schedule_merges_saved_option_over_defaults(): void {
		$this->setSchedule( array( 'capacity' => 4, 'weekdays' => array( 6 ) ) );

		$s = twistedoak_catering_schedule();
		self::assertSame( 4, $s['capacity'] );
		self::assertSame( array( 6 ), $s['weekdays'] );
		self::assertSame( 7, $s['lead_days'] );
	}

	/** @return iterable<string, array{string, string}> */
	public static function statuses(): iterable {
		yield 'pending'   => array( 'pending', 'pending' );
		yield 'held'      => array( 'held', 'held' );
		yield 'tentative' => array( 'tentative', 'held' );
		yield 'confirmed' => array( 'confirmed', 'confirmed' );
		yield 'completed' => array( 'completed', 'confirmed' );
		yield 'declined'  => array( 'declined', 'declined' );
		yield 'cancelled' => array( 'cancelled', 'declined' );
		yield 'unknown'   => array( 'whatever', 'pending' );
	}

	#[DataProvider( 'statuses' )]
	public function test_public_status( string $status, string $expected ): void {
		self::assertSame( $expected, twistedoak_catering_public_status( $status ) );
	}
}
