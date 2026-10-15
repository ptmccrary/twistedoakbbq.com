<?php
/**
 * Constants PHPStan cannot infer because they are define()d from runtime values.
 *
 * @package TwistedOak\Tests
 */

declare(strict_types=1);

define( 'TWISTEDOAK_CATERING_URL', 'https://example.test/wp-content/plugins/twisted-oak-catering/' );
define( 'TWISTEDOAK_DIR', '/var/www/wp-content/themes/twistedoak' );
define( 'TWISTEDOAK_URI', 'https://example.test/wp-content/themes/twistedoak' );
