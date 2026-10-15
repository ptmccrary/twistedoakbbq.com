<?php
/**
 * Twisted Oak theme bootstrap.
 *
 * @package TwistedOak
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'TWISTEDOAK_VERSION', '2.0.0' );
define( 'TWISTEDOAK_DIR', get_template_directory() );
define( 'TWISTEDOAK_URI', get_template_directory_uri() );

require TWISTEDOAK_DIR . '/inc/setup.php';
require TWISTEDOAK_DIR . '/inc/assets.php';
require TWISTEDOAK_DIR . '/inc/content.php';
require TWISTEDOAK_DIR . '/inc/customizer.php';
require TWISTEDOAK_DIR . '/inc/components.php';
require TWISTEDOAK_DIR . '/inc/navigation.php';
require TWISTEDOAK_DIR . '/inc/menu.php';
require TWISTEDOAK_DIR . '/inc/events.php';
require TWISTEDOAK_DIR . '/inc/catering-form.php';
