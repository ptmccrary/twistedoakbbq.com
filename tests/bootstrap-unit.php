<?php
/**
 * Unit test bootstrap: no WordPress, no database. WP functions are stubbed per
 * test with Brain Monkey (see tests/unit/TestCase.php).
 *
 * @package TwistedOak\Tests
 */

declare(strict_types=1);

require dirname( __DIR__ ) . '/vendor/autoload.php';

// The plugin files bail out without ABSPATH.
define( 'ABSPATH', dirname( __DIR__ ) . '/' );

// WordPress forces UTC as the PHP default timezone and keeps the site zone in wp_timezone().
date_default_timezone_set( 'UTC' );

// Pure-ish catering logic. Some files register hooks at load time, so Brain Monkey
// must be up (it defines add_action() and friends) while they are required.
\Brain\Monkey\setUp();
$catering = dirname( __DIR__ ) . '/wp-content/plugins/twisted-oak-catering/includes';
require_once $catering . '/data.php';
require_once $catering . '/schedule.php';
require_once $catering . '/availability.php';
\Brain\Monkey\tearDown();
