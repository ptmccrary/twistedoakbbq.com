<?php
/**
 * Base unit test case: Brain Monkey lifecycle plus stubs for the WordPress
 * functions the catering engine calls. Options and "now" are per-test state.
 *
 * @package TwistedOak\Tests
 */

declare(strict_types=1);

namespace TwistedOak\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase {
	use MockeryPHPUnitIntegration;

	protected const TIMEZONE = 'America/New_York';

	/** @var array<string, mixed> Fake wp_options. */
	protected array $options = array();

	/** Frozen "now" in the site timezone (Thursday). */
	protected string $now = '2026-10-15 09:00:00';

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubTranslationFunctions();

		Functions\when( 'get_option' )->alias( fn( string $name, mixed $default = false ): mixed => $this->options[ $name ] ?? $default );
		Functions\when( 'wp_timezone' )->alias( static fn(): DateTimeZone => new DateTimeZone( self::TIMEZONE ) );
		Functions\when( 'current_datetime' )->alias( fn(): DateTimeImmutable => new DateTimeImmutable( $this->now, new DateTimeZone( self::TIMEZONE ) ) );
		Functions\when( 'wp_date' )->alias(
			static fn( string $format, ?int $timestamp = null, ?DateTimeZone $tz = null ): string => ( new DateTimeImmutable( '@' . ( $timestamp ?? time() ) ) )
				->setTimezone( $tz ?? new DateTimeZone( self::TIMEZONE ) )
				->format( $format )
		);
		Functions\when( 'absint' )->alias( static fn( mixed $v ): int => abs( (int) $v ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( string $s ): string => trim( (string) preg_replace( '/\s+/', ' ', strip_tags( $s ) ) ) );

		$this->setSchedule( array() );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Store a schedule option (merged over defaults by the plugin) and drop the memoised copy.
	 *
	 * @param array<string, mixed> $schedule
	 */
	protected function setSchedule( array $schedule ): void {
		$this->options[ TWISTEDOAK_CATERING_SCHEDULE_OPTION ] = $schedule;
		twistedoak_catering_schedule( true );
	}
}
