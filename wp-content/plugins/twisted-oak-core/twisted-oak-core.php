<?php
/**
 * Plugin Name:       Twisted Oak Core
 * Description:       Content model for Twisted Oak BBQ: menu items, events, their taxonomies and meta, plus the menu source abstraction (Local CPT / Square).
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            Twisted Oak BBQ
 * Text Domain:       twisted-oak-core
 * License:           GPL-2.0-or-later
 *
 * @package TwistedOakCore
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TOBBQ_CORE_FILE', __FILE__ );
define( 'TOBBQ_CORE_DIR', __DIR__ );

require_once TOBBQ_CORE_DIR . '/includes/content-types.php';
require_once TOBBQ_CORE_DIR . '/includes/meta.php';
require_once TOBBQ_CORE_DIR . '/includes/meta-boxes.php';
require_once TOBBQ_CORE_DIR . '/includes/admin-columns.php';
require_once TOBBQ_CORE_DIR . '/includes/menu/interface-source.php';
require_once TOBBQ_CORE_DIR . '/includes/menu/functions.php';
require_once TOBBQ_CORE_DIR . '/includes/menu/class-local-source.php';
require_once TOBBQ_CORE_DIR . '/includes/menu/class-square-source.php';
require_once TOBBQ_CORE_DIR . '/includes/menu/class-square-sync.php';
require_once TOBBQ_CORE_DIR . '/includes/square-settings.php';
require_once TOBBQ_CORE_DIR . '/includes/cli.php';

add_action( 'init', 'tobbq_core_register_content_types' );
add_action( 'init', 'tobbq_core_register_meta' );

register_activation_hook(
	__FILE__,
	static function (): void {
		tobbq_core_register_content_types();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		flush_rewrite_rules();
	}
);
