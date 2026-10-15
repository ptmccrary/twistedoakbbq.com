<?php
/**
 * PHP versions of the design-system components (window.TwistedOak in the design), producing the
 * same markup and class names so components.css styles them unchanged. All return strings.
 *
 * @package TwistedOak
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

function twistedoak_logo_url(): string {
	return TWISTEDOAK_URI . '/assets/img/twisted-oak-badge.webp';
}

/**
 * Lucide icon (24px grid, 2px stroke). Decorative unless a label is given.
 *
 * @param array{label?: string, size?: int, class?: string} $args Options.
 */
function twistedoak_icon( string $name, array $args = array() ): string {
	static $shapes = array(
		'check'        => array( 'M20 6 9 17l-5-5' ),
		'x'            => array( 'M18 6 6 18', 'm6 6 12 12' ),
		'chevronDown'  => array( 'm6 9 6 6 6-6' ),
		'chevronRight' => array( 'm9 18 6-6-6-6' ),
		'menu'         => array( 'M4 6h16', 'M4 12h16', 'M4 18h16' ),
		'info'         => array( '<circle cx="12" cy="12" r="10"/>', 'M12 16v-4', 'M12 8h.01' ),
		'success'      => array( '<circle cx="12" cy="12" r="10"/>', 'm9 12 2 2 4-4' ),
		'warning'      => array( 'm21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z', 'M12 9v4', 'M12 17h.01' ),
		'error'        => array( '<circle cx="12" cy="12" r="10"/>', 'm15 9-6 6', 'm9 9 6 6' ),
		'flame'        => array( 'M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z' ),
		'plus'         => array( 'M5 12h14', 'M12 5v14' ),
		'arrowRight'   => array( 'M5 12h14', 'm12 5 7 7-7 7' ),
		'clock'        => array( '<circle cx="12" cy="12" r="10"/>', 'M12 6v6l4 2' ),
		'mapPin'       => array( 'M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z', '<circle cx="12" cy="10" r="3"/>' ),
		'calendar'     => array( '<rect x="3" y="4" width="18" height="18" rx="2"/>', 'M16 2v4', 'M8 2v4', 'M3 10h18' ),
		'phone'        => array( 'M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z' ),
	);
	$parts = $shapes[ $name ] ?? $shapes['info'];
	$inner = '';
	foreach ( $parts as $p ) {
		$inner .= str_starts_with( $p, '<' ) ? $p : '<path d="' . esc_attr( $p ) . '"/>';
	}
	$label = $args['label'] ?? '';
	$a11y  = '' !== $label ? ' role="img" aria-label="' . esc_attr( $label ) . '"' : ' aria-hidden="true"';
	$style = ! empty( $args['size'] ) ? sprintf( ' style="width:%1$dpx;height:%1$dpx"', (int) $args['size'] ) : '';
	$class = trim( 'to-icon ' . ( $args['class'] ?? '' ) );
	return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 24 24"' . $a11y . $style . ' focusable="false">' . $inner . '</svg>';
}

/**
 * Button or link-button.
 *
 * @param array{variant?: string, size?: string, href?: string, icon_end?: string, icon_start?: string, block?: bool, class?: string, attrs?: array<string, string|bool>, type?: string} $args Options.
 */
function twistedoak_button( string $label, array $args = array() ): string {
	$variant = $args['variant'] ?? 'primary';
	$size    = $args['size'] ?? 'md';
	$classes = array( 'to-btn', 'to-btn--' . $variant );
	if ( 'md' !== $size ) {
		$classes[] = 'to-btn--' . $size;
	}
	if ( ! empty( $args['block'] ) ) {
		$classes[] = 'to-btn--block';
	}
	if ( ! empty( $args['class'] ) ) {
		$classes[] = $args['class'];
	}
	$kids  = ! empty( $args['icon_start'] ) ? twistedoak_icon( $args['icon_start'] ) : '';
	$kids .= '<span>' . esc_html( $label ) . '</span>';
	$kids .= ! empty( $args['icon_end'] ) ? twistedoak_icon( $args['icon_end'] ) : '';

	$attrs = $args['attrs'] ?? array();
	$extra = '';
	foreach ( $attrs as $k => $v ) {
		if ( true === $v ) {
			$extra .= ' ' . esc_attr( $k );
		} elseif ( false !== $v ) {
			$extra .= sprintf( ' %s="%s"', esc_attr( $k ), esc_attr( (string) $v ) );
		}
	}
	if ( isset( $args['href'] ) ) {
		$external = str_starts_with( $args['href'], 'http' ) && ! str_starts_with( $args['href'], home_url() );
		if ( $external && ! isset( $attrs['rel'] ) ) {
			$extra .= ' rel="noopener"';
		}
		return sprintf( '<a class="%s" href="%s"%s>%s</a>', esc_attr( implode( ' ', $classes ) ), esc_url( $args['href'] ), $extra, $kids );
	}
	return sprintf( '<button type="%s" class="%s"%s>%s</button>', esc_attr( $args['type'] ?? 'button' ), esc_attr( implode( ' ', $classes ) ), $extra, $kids );
}

/**
 * Badge: tone neutral | brand | accent | success | warning | error | info.
 */
function twistedoak_badge( string $label, string $tone = 'neutral', string $icon = '' ): string {
	$class = 'to-badge' . ( 'neutral' !== $tone ? ' to-badge--' . $tone : '' );
	return '<span class="' . esc_attr( $class ) . '">' . ( $icon ? twistedoak_icon( $icon ) : '' ) . esc_html( $label ) . '</span>';
}

/**
 * Breadcrumbs. The last item is the current page.
 *
 * @param list<array{label: string, href?: string}> $items Trail.
 */
function twistedoak_breadcrumbs( array $items ): string {
	$out  = '<nav class="to-crumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'twistedoak' ) . '"><ol>';
	$last = count( $items ) - 1;
	foreach ( $items as $i => $item ) {
		$out .= '<li>';
		$out .= $i === $last || empty( $item['href'] )
			? '<span' . ( $i === $last ? ' aria-current="page"' : '' ) . '>' . esc_html( $item['label'] ) . '</span>'
			: '<a href="' . esc_url( $item['href'] ) . '">' . esc_html( $item['label'] ) . '</a>';
		$out .= $i === $last ? '' : twistedoak_icon( 'chevronRight' );
		$out .= '</li>';
	}
	return $out . '</ol></nav>';
}

/**
 * Inline alert: tone info | success | warning | error. $text may contain inline HTML (kses'd).
 */
function twistedoak_alert( string $title, string $text = '', string $tone = 'info', array $attrs = array() ): string {
	$icons = array(
		'info'    => 'info',
		'success' => 'success',
		'warning' => 'warning',
		'error'   => 'error',
	);
	$role  = in_array( $tone, array( 'error', 'warning' ), true ) ? 'alert' : 'status';
	$extra = '';
	foreach ( $attrs as $k => $v ) {
		$extra .= sprintf( ' %s="%s"', esc_attr( $k ), esc_attr( (string) $v ) );
	}
	return sprintf(
		'<div class="to-alert to-alert--%1$s" role="%2$s"%3$s>%4$s<div class="to-alert__body">%5$s%6$s</div></div>',
		esc_attr( $tone ),
		$role,
		$extra,
		twistedoak_icon( $icons[ $tone ] ?? 'info' ),
		'' !== $title ? '<p class="to-alert__title">' . esc_html( $title ) . '</p>' : '',
		'' !== $text ? '<p class="to-alert__text">' . wp_kses( $text, twistedoak_inline_html() ) . '</p>' : ''
	);
}

/**
 * Allowed inline HTML for copy strings.
 *
 * @return array<string, array<string, bool>>
 */
function twistedoak_inline_html(): array {
	return array(
		'a'      => array(
			'href'   => true,
			'rel'    => true,
			'target' => true,
		),
		'strong' => array(),
		'em'     => array(),
		'br'     => array(),
	);
}

/**
 * Section heading block: eyebrow + h2 (+ optional lede).
 *
 * @param array{eyebrow?: string, title: string, id?: string, lede?: string, tag?: string, class?: string} $args Options.
 */
function twistedoak_section_head( array $args ): string {
	$tag = $args['tag'] ?? 'h2';
	$out = '<div class="section-head' . ( ! empty( $args['class'] ) ? ' ' . esc_attr( $args['class'] ) : '' ) . '">';
	if ( ! empty( $args['eyebrow'] ) ) {
		$out .= '<p class="eyebrow">' . esc_html( $args['eyebrow'] ) . '</p>';
	}
	$out .= sprintf( '<%1$s class="h2"%2$s>%3$s</%1$s>', tag_escape( $tag ), ! empty( $args['id'] ) ? ' id="' . esc_attr( $args['id'] ) . '"' : '', esc_html( $args['title'] ) );
	if ( ! empty( $args['lede'] ) ) {
		$out .= '<p class="lede">' . wp_kses( $args['lede'], twistedoak_inline_html() ) . '</p>';
	}
	return $out . '</div>';
}

/**
 * The rough "torn paper" edge between bands. $variant a = dark band into light, b = light into dark.
 */
function twistedoak_torn_edge( string $variant = 'a' ): string {
	$paths = array(
		'a' => 'M0,46 L0,24.1 L32.2,14.0 L52.2,19.4 L66.2,18.3 L87.0,19.8 L107.0,19.2 L125.3,13.7 L149.6,20.9 L183.9,17.4 L202.5,19.9 L219.6,14.0 L247.3,20.1 L273.0,15.2 L290.4,22.8 L302.4,18.6 L326.3,22.8 L344.9,16.5 L374.1,19.6 L385.9,22.4 L416.4,15.9 L449.3,20.1 L462.7,29.8 L472.3,22.7 L506.6,22.7 L529.6,24.9 L547.6,24.7 L561.6,13.9 L575.7,24.2 L590.4,14.2 L603.8,19.5 L619.4,18.1 L639.9,17.1 L670.1,23.7 L689.8,21.3 L715.4,15.9 L735.1,22.3 L758.4,29.1 L776.0,19.7 L791.5,30.2 L814.6,15.8 L835.8,18.4 L844.9,17.5 L857.6,23.4 L884.3,15.1 L904.3,14.0 L925.8,21.3 L949.2,19.3 L981.6,14.0 L1013.0,19.0 L1039.6,20.3 L1056.9,17.2 L1090.8,17.1 L1110.7,22.8 L1134.1,16.1 L1145.4,15.6 L1176.6,24.3 L1188.7,19.8 L1220.1,16.1 L1235.5,24.8 L1245.6,22.7 L1264.0,14.8 L1275.4,21.9 L1302.0,24.1 L1328.1,15.0 L1360.8,21.2 L1373.4,21.8 L1400.8,19.8 L1431.2,20.2 L1440,19 L1440,46 Z',
		'b' => 'M0,46 L0,20.6 L33.2,20.7 L57.5,18.5 L82.9,27.6 L94.1,23.5 L104.0,24.9 L130.1,21.9 L139.3,25.0 L154.5,17.2 L174.9,23.7 L200.6,21.0 L221.5,19.2 L256.6,23.7 L273.7,18.8 L284.4,23.1 L315.5,20.1 L346.7,17.0 L379.5,20.8 L398.8,17.6 L428.1,23.5 L462.4,27.0 L473.9,17.5 L487.4,21.5 L501.2,22.9 L527.0,17.9 L541.4,19.2 L571.4,19.4 L585.8,20.2 L611.5,17.8 L626.0,19.1 L643.4,22.7 L667.6,18.9 L686.2,20.6 L707.8,21.6 L721.4,18.2 L751.8,19.0 L780.1,24.5 L814.0,24.1 L834.0,24.7 L849.0,22.6 L879.6,21.8 L893.0,26.7 L916.6,23.8 L932.8,24.3 L942.0,19.2 L952.4,18.4 L976.3,18.1 L1008.6,24.8 L1035.7,21.7 L1045.4,17.1 L1072.7,30.2 L1094.3,22.8 L1129.4,17.6 L1157.7,24.2 L1185.1,23.3 L1203.2,22.5 L1235.0,20.3 L1266.6,21.6 L1285.5,21.7 L1296.4,22.1 L1328.4,22.8 L1356.6,21.6 L1387.5,17.7 L1397.1,21.8 L1412.0,22.6 L1437.0,24.4 L1440,21 L1440,46 Z',
	);
	return '<svg class="torn" aria-hidden="true" focusable="false" viewBox="0 -3 1440 46" preserveAspectRatio="none"><path d="' . $paths[ 'b' === $variant ? 'b' : 'a' ] . '"/></svg>';
}

/**
 * A photo slot: the Customizer image if set, else the design's labelled placeholder.
 *
 * @param array{class?: string, size?: string, attachment?: int, alt?: string, caption?: string, sizes?: string, loading?: string} $args Options.
 */
function twistedoak_photo( string $slot, array $args = array() ): string {
	$slots   = twistedoak_photo_slots();
	$def     = $slots[ $slot ] ?? array( '', '', '' );
	$class   = trim( 'photo ' . ( $args['class'] ?? '' ) );
	$id      = (int) ( $args['attachment'] ?? 0 ) ?: (int) get_theme_mod( 'twistedoak_photo_' . $slot, 0 );
	$alt     = $args['alt'] ?? $def[2];
	$caption = $args['caption'] ?? $def[1];

	if ( $id && wp_attachment_is_image( $id ) ) {
		$img_alt = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) ?: $alt;
		return wp_get_attachment_image(
			$id,
			$args['size'] ?? 'large',
			false,
			array_filter(
				array(
					'class'   => $class . ' photo--img',
					'alt'     => $img_alt,
					'sizes'   => $args['sizes'] ?? null,
					'loading' => $args['loading'] ?? 'lazy',
				)
			)
		);
	}

	return sprintf(
		'<div class="%1$s photo--placeholder" role="img" aria-label="%2$s"><span>%3$s</span></div>',
		esc_attr( $class ),
		esc_attr( sprintf( /* translators: %s: photo description */ __( 'Photo placeholder: %s', 'twistedoak' ), $alt ) ),
		esc_html( 'PHOTO — ' . $caption )
	);
}

/**
 * Check-mark feature list (package cards).
 *
 * @param string[] $items Lines.
 */
function twistedoak_check_list( array $items, string $class = 'check-list' ): string {
	$out = '<ul class="' . esc_attr( $class ) . '">';
	foreach ( $items as $item ) {
		$out .= '<li>' . twistedoak_icon( 'check' ) . '<span>' . esc_html( $item ) . '</span></li>';
	}
	return $out . '</ul>';
}

/**
 * "Or call 434-…" line under a CTA button.
 */
function twistedoak_or_call(): string {
	$info = twistedoak_info();
	return sprintf(
		'<p class="or-call">%s <a href="%s">%s</a></p>',
		esc_html__( 'Or call', 'twistedoak' ),
		esc_url( $info['phone_href'] ),
		esc_html( $info['phone'] )
	);
}

/**
 * Echo helper for template files: components return already-escaped HTML.
 */
function twistedoak_e( string $html ): void {
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
}
