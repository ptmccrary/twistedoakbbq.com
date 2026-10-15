<?php
/**
 * Plugin Name:       Twisted Oak Catering
 * Description:       Catering booking system: availability rules, date/time scheduling REST API, booking workflow + calendar, and the [twisted_oak_catering] form/block.
 * Version:           2.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            Twisted Oak BBQ
 * Text Domain:       twisted-oak-catering
 *
 * @package TwistedOakCatering
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'TWISTEDOAK_CATERING_VERSION', '2.0.0' );
define( 'TWISTEDOAK_CATERING_FILE', __FILE__ );
define( 'TWISTEDOAK_CATERING_DIR', __DIR__ );
define( 'TWISTEDOAK_CATERING_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/schedule.php';
require_once __DIR__ . '/includes/post-type.php';
require_once __DIR__ . '/includes/availability.php';
require_once __DIR__ . '/includes/emails.php';
require_once __DIR__ . '/includes/booking.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/render.php';
require_once __DIR__ . '/includes/submission.php';
require_once __DIR__ . '/includes/rest.php';
require_once __DIR__ . '/includes/cli.php';

register_activation_hook(
	__FILE__,
	static function (): void {
		twistedoak_catering_register_post_type();
		if ( false === get_option( TWISTEDOAK_CATERING_OPTION ) ) {
			add_option( TWISTEDOAK_CATERING_OPTION, twistedoak_catering_default_settings() );
		}
		if ( false === get_option( TWISTEDOAK_CATERING_SCHEDULE_OPTION ) ) {
			add_option( TWISTEDOAK_CATERING_SCHEDULE_OPTION, twistedoak_catering_schedule_defaults(), '', false );
		}
	}
);
