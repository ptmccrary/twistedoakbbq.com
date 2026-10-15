<?php
/**
 * Styles, scripts and font preloads. No build step: files in assets/ are served as-is
 * and versioned by modification time.
 *
 * @package TwistedOak
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Cache-busting version for a theme asset.
 */
function twistedoak_asset_version( string $rel ): string {
	$path = TWISTEDOAK_DIR . '/' . ltrim( $rel, '/' );
	return file_exists( $path ) ? (string) filemtime( $path ) : TWISTEDOAK_VERSION;
}

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		foreach ( array( 'tokens', 'components', 'theme' ) as $i => $name ) {
			$rel = "assets/css/{$name}.css";
			wp_enqueue_style(
				"twistedoak-{$name}",
				TWISTEDOAK_URI . '/' . $rel,
				$i ? array( 'twistedoak-' . ( 1 === $i ? 'tokens' : 'components' ) ) : array(),
				twistedoak_asset_version( $rel )
			);
		}

		wp_enqueue_script(
			'twistedoak',
			TWISTEDOAK_URI . '/assets/js/theme.js',
			array(),
			twistedoak_asset_version( 'assets/js/theme.js' ),
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		// The catering page renders its own quote form (inc/catering-form.php); the plugin's wizard assets aren't used.
		if ( is_page_template( 'page-catering.php' ) || is_page( 'catering' ) ) {
			wp_dequeue_style( 'tobbq-catering' );
			wp_dequeue_script( 'tobbq-catering' );
		}

		// Block library CSS is only needed where post content is rendered.
		if ( is_front_page() || is_post_type_archive( array( 'tobbq_menu_item', 'tobbq_event' ) ) || is_tax( 'tobbq_event_type' ) ) {
			wp_dequeue_style( 'wp-block-library' );
			wp_dequeue_style( 'global-styles' );
			wp_dequeue_style( 'classic-theme-styles' );
		}
	},
	30
);

/**
 * Preload the two faces used above the fold.
 */
add_action(
	'wp_head',
	static function (): void {
		foreach ( array( 'ZillaSlab-Bold.woff2', 'LibreFranklin-Variable.woff2' ) as $font ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url( TWISTEDOAK_URI . '/assets/fonts/' . $font )
			);
		}
		printf( '<meta name="theme-color" content="#21201e">' . "\n" );
	},
	1
);

/**
 * Favicon fallback when no Site Icon is set.
 */
add_action(
	'wp_head',
	static function (): void {
		if ( ! has_site_icon() ) {
			printf( '<link rel="icon" href="%s" type="image/webp">' . "\n", esc_url( twistedoak_logo_url() ) );
		}
	}
);
