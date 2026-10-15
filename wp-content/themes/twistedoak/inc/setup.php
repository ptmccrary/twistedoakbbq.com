<?php
/**
 * Theme supports, menus, image sizes and small front-end tweaks.
 *
 * @package TwistedOak
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function (): void {
		load_theme_textdomain( 'twistedoak', TWISTEDOAK_DIR . '/languages' );

		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
		add_theme_support( 'editor-styles' );
		add_editor_style( array( 'assets/css/tokens.css', 'assets/css/editor.css' ) );

		register_nav_menus(
			array(
				'main'   => __( 'Main navigation', 'twistedoak' ),
				'footer' => __( 'Footer: Explore', 'twistedoak' ),
			)
		);

		// Card crops used by menu items and events (the core plugin's image DTO asks for twistedoak-card).
		add_image_size( 'twistedoak-card', 800, 600, true );
		add_image_size( 'twistedoak-tall', 800, 1000, true );
		add_image_size( 'twistedoak-wide', 2000, 860, true );
	}
);

/**
 * Lean <head>: no emoji script, no generator.
 */
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_head', 'wp_generator' );

/**
 * Category archives for the food menu live as sections of /menu/.
 */
add_action(
	'template_redirect',
	static function (): void {
		if ( is_tax( 'tobbq_menu_category' ) ) {
			$term = get_queried_object();
			$base = get_post_type_archive_link( 'tobbq_menu_item' );
			if ( $term instanceof WP_Term && $base ) {
				wp_safe_redirect( $base . '#' . twistedoak_menu_anchor( $term->slug ), 301 );
				exit;
			}
		}
	}
);

/**
 * Body classes: one per page template "kind" so CSS can tune small things.
 *
 * @param string[] $classes Classes.
 * @return string[]
 */
add_filter(
	'body_class',
	static function ( array $classes ): array {
		$classes[] = 'to-site';
		return $classes;
	}
);

/**
 * Excerpt length/ending for archives.
 */
add_filter( 'excerpt_length', static fn(): int => 28 );
add_filter( 'excerpt_more', static fn(): string => '…' );
