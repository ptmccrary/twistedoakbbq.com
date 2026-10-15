<?php
/**
 * Minute arithmetic and time labels (includes/availability.php).
 *
 * @package TwistedOak\Tests
 */

declare(strict_types=1);

namespace TwistedOak\Tests\Unit\Catering;

use PHPUnit\Framework\Attributes\DataProvider;
use TwistedOak\Tests\Unit\TestCase;

final class TimeHelpersTest extends TestCase {

	/** @return iterable<string, array{string, int}> */
	public static function minutes(): iterable {
		yield 'midnight'     => array( '00:00', 0 );
		yield 'morning'      => array( '09:30', 570 );
		yield 'unpadded'     => array( '9:30', 570 );
		yield 'last minute'  => array( '23:59', 1439 );
		yield 'hour only'    => array( '17', 1020 );
	}

	#[DataProvider( 'minutes' )]
	public function test_minutes( string $time, int $expected ): void {
		self::assertSame( $expected, twistedoak_catering_minutes( $time ) );
	}

	/** @return iterable<string, array{int, string}> */
	public static function clock(): iterable {
		yield 'midnight'       => array( 0, '00:00' );
		yield 'afternoon'      => array( 840, '14:00' );
		yield 'wraps past midnight' => array( 1500, '01:00' );
		yield 'negative wraps back' => array( -30, '23:30' );
		yield 'exactly one day' => array( 1440, '00:00' );
	}

	#[DataProvider( 'clock' )]
	public function test_time_from_minutes( int $minutes, string $expected ): void {
		self::assertSame( $expected, twistedoak_catering_time_from_minutes( $minutes ) );
	}

	public function test_window_label_uses_site_time_format(): void {
		self::assertSame( '11:00 am – 2:00 pm', twistedoak_catering_window_label( '11:00', 180 ) );

		$this->options['time_format'] = 'H:i';
		self::assertSame( '17:00 – 21:00', twistedoak_catering_window_label( '17:00', 240 ) );
	}

	public function test_window_label_wraps_past_midnight(): void {
		self::assertSame( '10:00 pm – 1:00 am', twistedoak_catering_window_label( '22:00', 180 ) );
	}

	public function test_format_time_passes_invalid_input_through(): void {
		self::assertSame( 'noon', twistedoak_catering_format_time( 'noon' ) );
	}
}
