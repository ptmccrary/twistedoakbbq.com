<?php
/**
 * Navigation links for the header, drawer and footer. Menus come from Appearance → Menus
 * (locations "main" and "footer"); without one assigned, the design's default links are used.
 *
 * @package TwistedOak
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Default links per location, straight from the design.
 *
 * @return list<array{label: string, href: string}>
 */
function twistedoak_default_links( string $location ): array {
	$info = twistedoak_info();
	if ( 'footer' === $location ) {
		return array(
			array( 'label' => 'Catering', 'href' => twistedoak_catering_url() ),
			array( 'label' => 'Menu', 'href' => twistedoak_menu_url() ),
			array( 'label' => 'Our story', 'href' => twistedoak_story_url() ),
			array( 'label' => 'Events', 'href' => twistedoak_events_url() ),
			array( 'label' => 'Twisted swag', 'href' => $info['swag_url'] ),
		);
	}
	return array(
		array( 'label' => 'Catering', 'href' => twistedoak_catering_url() ),
		array( 'label' => 'Menu', 'href' => twistedoak_menu_url() ),
		array( 'label' => 'Our story', 'href' => twistedoak_story_url() ),
		array( 'label' => 'Events', 'href' => twistedoak_events_url() ),
		array( 'label' => 'Order pickup', 'href' => $info['order_url'] ),
	);
}

/**
 * Links for a menu location, each flagged `current` when it matches the request.
 *
 * @return list<array{label: string, href: string, current: bool}>
 */
function twistedoak_nav_links( string $location ): array {
	$links     = array();
	$locations = get_nav_menu_locations();
	if ( ! empty( $locations[ $location ] ) ) {
		$items = wp_get_nav_menu_items( $locations[ $location ] );
		foreach ( (array) $items as $item ) {
			if ( ! $item instanceof WP_Post || (int) $item->menu_item_parent ) {
				continue;
			}
			$links[] = array(
				'label' => (string) $item->title,
				'href'  => (string) $item->url,
			);
		}
	}
	if ( ! $links ) {
		$links = twistedoak_default_links( $location );
	}

	$here = untrailingslashit( (string) wp_parse_url( home_url( add_query_arg( array() ) ), PHP_URL_PATH ) );
	foreach ( $links as &$link ) {
		$host = wp_parse_url( $link['href'], PHP_URL_HOST );
		$path = untrailingslashit( (string) wp_parse_url( $link['href'], PHP_URL_PATH ) );
		$same = ! $host || wp_parse_url( home_url(), PHP_URL_HOST ) === $host;

		$link['current'] = $same && '' !== $path && ( $here === $path || str_starts_with( $here . '/', $path . '/' ) );
	}
	return $links;
}

/**
 * Header link markup (desktop nav or drawer).
 */
function twistedoak_nav_markup( string $style ): string {
	$out = '';
	foreach ( twistedoak_nav_links( 'main' ) as $link ) {
		$current = $link['current'] ? ' aria-current="page"' : '';
		if ( 'drawer' === $style ) {
			$out .= sprintf( '<a class="to-drawer__link" href="%s"%s>%s%s</a>', esc_url( $link['href'] ), $current, esc_html( $link['label'] ), twistedoak_icon( 'chevronRight' ) );
		} else {
			$out .= sprintf( '<a class="to-navlink" href="%s"%s>%s</a>', esc_url( $link['href'] ), $current, esc_html( $link['label'] ) );
		}
	}
	return $out;
}
