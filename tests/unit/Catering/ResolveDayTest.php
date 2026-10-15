<?php
/**
 * Day / slot availability engine (twistedoak_catering_day_rules() and
 * twistedoak_catering_resolve_day()).
 *
 * Fixed clock: today is Thursday 2026-10-15 (America/New_York), and the default
 * schedule applies unless a test overrides it:
 *   Thu 17:00/180 · Fri 11:30/150, 17:30/210 · Sat 11:00/180, 17:00/240 · Sun 11:00/240
 *   capacity 2/day, 1 per slot, 7 days' lead time, 6-month horizon, limited at <= 1 left.
 *
 * @package TwistedOak\Tests
 */

declare(strict_types=1);

namespace TwistedOak\Tests\Unit\Catering;

use PHPUnit\Framework\Attributes\DataProvider;
use TwistedOak\Tests\Unit\TestCase;

final class ResolveDayTest extends TestCase {

	private const SATURDAY = '2026-10-24';
	private const MONDAY   = '2026-10-26';

	/**
	 * @return array{id: int, start: string, duration: int, guests: int, status: string}
	 */
	private static function booking( string $start, int $duration = 0, int $guests = 50 ): array {
		return array(
			'id'       => 1,
			'start'    => $start,
			'duration' => $duration,
			'guests'   => $guests,
			'status'   => 'confirmed',
		);
	}

	/**
	 * @param array<string, mixed> $day
	 * @return array<string, string>
	 */
	private static function slotStatuses( array $day ): array {
		return array_column( $day['slots'], 'status', 'time' );
	}

	/** @return iterable<string, array{string, string, string}> */
	public static function bookingWindow(): iterable {
		yield 'yesterday'               => array( '2026-10-14', 'past', 'past' );
		yield 'today is inside lead time' => array( '2026-10-15', 'too-soon', 'lead-time' );
		yield 'day before lead time ends' => array( '2026-10-21', 'too-soon', 'lead-time' );
		yield 'first bookable day'      => array( '2026-10-22', 'limited', 'limited' );
		yield 'last day of horizon'     => array( '2027-04-15', 'limited', 'limited' );
		yield 'day after horizon'       => array( '2027-04-16', 'beyond-horizon', 'blackout' );
		yield 'closed weekday'          => array( self::MONDAY, 'closed', 'blackout' );
	}

	#[DataProvider( 'bookingWindow' )]
	public function test_booking_window( string $date, string $status, string $state ): void {
		$day = twistedoak_catering_resolve_day( $date, array() );

		self::assertSame( $status, $day['status'] );
		self::assertSame( $state, $day['state'] );
	}

	public function test_too_soon_reason_mentions_lead_time(): void {
		$day = twistedoak_catering_resolve_day( '2026-10-21', array() );

		self::assertSame( "Needs 7 days' notice", $day['reason'] );
		self::assertFalse( $day['bookable'] );
		self::assertSame( array(), $day['slots'] );
	}

	public function test_admin_ignores_past_lead_time_and_horizon(): void {
		foreach ( array( '2026-10-01', '2026-10-17', '2027-10-16' ) as $date ) { // Thu, Sat, Sat.
			self::assertTrue( twistedoak_catering_resolve_day( $date, array(), 0, true )['bookable'], $date );
		}
		self::assertSame( 'closed', twistedoak_catering_resolve_day( self::MONDAY, array(), 0, true )['status'], 'Admin still respects the weekly schedule.' );
	}

	public function test_open_day_with_no_bookings_is_available(): void {
		$day = twistedoak_catering_resolve_day( self::SATURDAY, array() );

		self::assertSame( 'available', $day['status'] );
		self::assertTrue( $day['bookable'] );
		self::assertSame( 2, $day['remaining'] );
		self::assertSame( 6, $day['weekday'] );
		self::assertSame(
			array(
				array(
					'time'      => '11:00',
					'end'       => '14:00',
					'duration'  => 180,
					'label'     => '11:00 am – 2:00 pm',
					'status'    => 'available',
					'remaining' => 1,
					'start'     => '11:00',
					'state'     => 'available',
				),
				array(
					'time'      => '17:00',
					'end'       => '21:00',
					'duration'  => 240,
					'label'     => '5:00 pm – 9:00 pm',
					'status'    => 'available',
					'remaining' => 1,
					'start'     => '17:00',
					'state'     => 'available',
				),
			),
			$day['slots']
		);
	}

	public function test_booking_fills_its_slot_and_leaves_day_limited(): void {
		$day = twistedoak_catering_resolve_day( self::SATURDAY, array( self::booking( '11:00', 180 ) ) );

		self::assertSame( 'limited', $day['status'] );
		self::assertSame( 1, $day['remaining'] );
		self::assertSame( array( '11:00' => 'full', '17:00' => 'available' ), self::slotStatuses( $day ) );
		self::assertSame( 'booked', $day['slots'][0]['state'] );
	}

	public function test_day_is_full_when_daily_capacity_is_used(): void {
		$day = twistedoak_catering_resolve_day( self::SATURDAY, array( self::booking( '11:00' ), self::booking( '17:00' ) ) );

		self::assertSame( 'full', $day['status'] );
		self::assertSame( 'booked', $day['state'] );
		self::assertSame( 'Fully booked', $day['reason'] );
		self::assertSame( 0, $day['remaining'] );
		self::assertFalse( $day['bookable'] );
	}

	public function test_daily_capacity_caps_slots_even_when_slots_have_room(): void {
		$this->setSchedule( array( 'slot_capacity' => 5, 'capacity' => 1 ) );

		$day = twistedoak_catering_resolve_day( self::SATURDAY, array( self::booking( '11:00' ) ) );

		self::assertSame( 'full', $day['status'] );
		self::assertSame( array( '11:00' => 'full', '17:00' => 'full' ), self::slotStatuses( $day ) );
	}

	public function test_booking_overlapping_a_slot_fills_it_even_off_the_slot_start(): void {
		$day = twistedoak_catering_resolve_day( self::SATURDAY, array( self::booking( '12:30', 60 ) ) );

		self::assertSame( array( '11:00' => 'full', '17:00' => 'available' ), self::slotStatuses( $day ) );
	}

	public function test_back_to_back_booking_does_not_overlap(): void {
		// Slot 11:00–14:00; booking 14:00–15:00 touches the end but does not overlap it.
		$day = twistedoak_catering_resolve_day( self::SATURDAY, array( self::booking( '14:00', 60 ) ) );

		self::assertSame( array( '11:00' => 'available', '17:00' => 'available' ), self::slotStatuses( $day ) );
	}

	public function test_booking_without_duration_uses_the_slot_duration(): void {
		// 16:00 + the 17:00 slot's 240 min reaches into it; 16:00 + the 11:00 slot's 180 min starts after it ends.
		$day = twistedoak_catering_resolve_day( self::SATURDAY, array( self::booking( '16:00', 0 ) ) );

		self::assertSame( array( '11:00' => 'available', '17:00' => 'full' ), self::slotStatuses( $day ) );
	}

	public function test_date_only_booking_uses_day_capacity_but_no_slot(): void {
		$day = twistedoak_catering_resolve_day( self::SATURDAY, array( self::booking( '' ) ) );

		self::assertSame( 'limited', $day['status'] );
		self::assertSame( 1, $day['remaining'] );
		self::assertSame( array( '11:00' => 'available', '17:00' => 'available' ), self::slotStatuses( $day ) );
	}

	public function test_slot_capacity_above_one_allows_overlapping_bookings(): void {
		$this->setSchedule( array( 'slot_capacity' => 2, 'capacity' => 4 ) );

		$day = twistedoak_catering_resolve_day( self::SATURDAY, array( self::booking( '11:00' ) ) );

		self::assertSame( 'available', $day['status'] );
		self::assertSame( array( 1, 2 ), array_column( $day['slots'], 'remaining' ) );
		self::assertSame( array( 'limited', 'available' ), array_column( $day['slots'], 'state' ), 'With multi-booking slots, nearly full ones show "limited".' );
	}

	/** @return iterable<string, array{int, string}> */
	public static function guestCounts(): iterable {
		yield 'fits exactly'     => array( 50, 'limited' );
		yield 'one too many'     => array( 51, 'full' );
		yield 'not yet known'    => array( 0, 'limited' );
	}

	#[DataProvider( 'guestCounts' )]
	public function test_daily_guest_cap( int $guests, string $status ): void {
		// 350 of the 400 daily guests are already booked.
		$day = twistedoak_catering_resolve_day( self::SATURDAY, array( self::booking( '11:00', 180, 350 ) ), $guests );

		self::assertSame( $status, $day['status'] );
	}

	public function test_zero_guest_cap_means_unlimited(): void {
		$this->setSchedule( array( 'max_guests_per_day' => 0 ) );

		$day = twistedoak_catering_resolve_day( self::SATURDAY, array( self::booking( '11:00', 180, 5000 ) ), 5000 );

		self::assertTrue( $day['bookable'] );
	}

	public function test_blackout_closes_a_bookable_day_with_its_label(): void {
		$this->setSchedule( array( 'blackouts' => '2026-11-26..2026-11-28 Thanksgiving' ) );

		$day = twistedoak_catering_resolve_day( '2026-11-27', array() );

		self::assertSame( 'blackout', $day['status'] );
		self::assertSame( 'Closed for Thanksgiving', $day['reason'] );
		self::assertSame( 'limited', twistedoak_catering_resolve_day( '2026-11-29', array() )['status'], 'Day after the range is open again.' );
	}

	public function test_closed_override_beats_the_weekly_schedule(): void {
		$this->setSchedule( array( 'overrides' => '2026-10-24 closed Staff party' ) );

		$day = twistedoak_catering_resolve_day( self::SATURDAY, array() );

		self::assertSame( 'closed', $day['status'] );
		self::assertSame( '', $day['note'], 'Override notes are staff-only and never reach the public DTO.' );
	}

	public function test_open_override_beats_a_blackout(): void {
		$this->setSchedule(
			array(
				'blackouts' => '2026-11-26..2026-11-28 Thanksgiving',
				'overrides' => '2026-11-28 open Private event',
			)
		);

		self::assertTrue( twistedoak_catering_resolve_day( '2026-11-28', array() )['bookable'] );
	}

	public function test_open_override_on_a_closed_weekday_borrows_the_first_bookable_days_slots(): void {
		$this->setSchedule( array( 'overrides' => '2026-10-26 open' ) );

		$day = twistedoak_catering_resolve_day( self::MONDAY, array() );

		self::assertTrue( $day['bookable'] );
		self::assertSame( array( '17:00' ), array_column( $day['slots'], 'time' ), 'Thursday is the first bookable weekday.' );
	}

	public function test_override_slots_replace_the_weekday_slots(): void {
		$this->setSchedule( array( 'overrides' => '2026-10-24 open slots=12:00/120 capacity=1' ) );

		$day = twistedoak_catering_resolve_day( self::SATURDAY, array() );

		self::assertSame( array( '12:00' ), array_column( $day['slots'], 'time' ) );
		self::assertSame( 1, $day['capacity'] );
	}

	public function test_zero_capacity_override_closes_the_day(): void {
		$this->setSchedule( array( 'overrides' => '2026-10-24 capacity=0' ) );

		self::assertSame( 'closed', twistedoak_catering_resolve_day( self::SATURDAY, array() )['status'] );
	}

	public function test_custom_capacity_override_follows_the_normal_open_rules(): void {
		$this->setSchedule( array( 'overrides' => "2026-10-24 capacity=3\n2026-10-26 capacity=3" ) );

		self::assertSame( 3, twistedoak_catering_resolve_day( self::SATURDAY, array() )['capacity'] );
		self::assertSame( 'closed', twistedoak_catering_resolve_day( self::MONDAY, array() )['status'] );
	}

	/** @return iterable<string, array{string}> */
	public static function dstSundays(): iterable {
		yield 'clocks fall back' => array( '2026-11-01' );
		yield 'clocks spring forward' => array( '2027-03-14' );
	}

	#[DataProvider( 'dstSundays' )]
	public function test_dst_change_days_resolve_like_any_other_sunday( string $date ): void {
		$day = twistedoak_catering_resolve_day( $date, array() );

		self::assertSame( 7, $day['weekday'] );
		self::assertTrue( $day['bookable'] );
		self::assertSame( '11:00 am – 3:00 pm', $day['slots'][0]['label'] );
	}

	public function test_lead_time_counts_calendar_days_late_in_the_evening(): void {
		$this->now = '2026-10-15 23:30:00';

		self::assertSame( 'too-soon', twistedoak_catering_resolve_day( '2026-10-21', array() )['status'] );
		self::assertTrue( twistedoak_catering_resolve_day( '2026-10-22', array() )['bookable'] );
	}

	public function test_find_slot(): void {
		$day = twistedoak_catering_resolve_day( self::SATURDAY, array() );

		self::assertSame( '17:00', twistedoak_catering_find_slot( $day, '17:00' )['time'] ?? null );
		self::assertNull( twistedoak_catering_find_slot( $day, '12:00' ) );
	}
}
