<?php
/**
 * Site copy and business details that aren't post content.
 *
 * Everything here comes from the design. Bracketed text like "[response time]" is a placeholder the
 * design leaves for the client to fill in. Each function is filterable (twistedoak_{name}) so a child
 * theme or plugin can override without editing this file.
 *
 * @package TwistedOak
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Business details. Editable under Appearance → Customize → Twisted Oak details.
 *
 * @return array{phone: string, phone_href: string, email: string, address: string[], area: string, order_url: string, instagram: string, facebook: string, swag_url: string, tagline: string}
 */
function twistedoak_info(): array {
	static $info = null;
	if ( null !== $info ) {
		return $info;
	}
	$mod   = static fn( string $key, string $default ): string => trim( (string) get_theme_mod( 'twistedoak_' . $key, $default ) );
	$phone  = $mod( 'phone', '434-260-0010' );
	$digits = (string) preg_replace( '/\D+/', '', $phone );
	$info   = array(
		'phone'      => $phone,
		'phone_href' => 'tel:' . ( 10 === strlen( $digits ) ? '+1' . $digits : '+' . $digits ),
		'email'      => $mod( 'email', 'info@twistedoakbbq.com' ),
		'address'    => array_values( array_filter( array_map( 'trim', explode( "\n", $mod( 'address', "Beacon Kitchen\n221 Carlton Rd, Suite 1\nCharlottesville, VA 22902" ) ) ) ) ),
		'area'       => $mod( 'area', 'Charlottesville, Albemarle County and the surrounding area' ),
		'order_url'  => $mod( 'order_url', 'https://twistedoakbbq.square.site' ),
		'instagram'  => $mod( 'instagram', 'https://www.instagram.com/twistedoakbbqva' ),
		'facebook'   => $mod( 'facebook', 'https://www.facebook.com/458708613994228' ),
		'swag_url'   => $mod( 'swag_url', 'https://twisted-oak-barbecue.printify.me' ),
		'tagline'    => 'Texas inspired · Tennessee rooted · Crafted in Virginia',
	);
	$info  = (array) apply_filters( 'twistedoak_info', $info );
	return $info;
}

/**
 * Catering URL (with optional fragment), resolved from the "catering" page.
 */
function twistedoak_catering_url( string $fragment = '' ): string {
	$page = get_page_by_path( 'catering' );
	$url  = $page ? (string) get_permalink( $page ) : home_url( '/catering/' );
	return $url . ( '' !== $fragment ? '#' . $fragment : '' );
}

/**
 * "Our story" URL (the page with slug "about").
 */
function twistedoak_story_url(): string {
	$page = get_page_by_path( 'about' );
	return $page ? (string) get_permalink( $page ) : home_url( '/about/' );
}

function twistedoak_menu_url( string $fragment = '' ): string {
	$url = (string) ( get_post_type_archive_link( 'tobbq_menu_item' ) ?: home_url( '/menu/' ) );
	return $url . ( '' !== $fragment ? '#' . $fragment : '' );
}

function twistedoak_events_url(): string {
	return (string) ( get_post_type_archive_link( 'tobbq_event' ) ?: home_url( '/events/' ) );
}

/**
 * Catering packages. Names must match the catering plugin's package list so a card can preselect
 * the package in the quote form.
 *
 * @return list<array{name: string, slug: string, guests: string, summary: string, price: string, features: string[], popular: bool}>
 */
function twistedoak_packages(): array {
	return (array) apply_filters(
		'twistedoak_packages',
		array(
			array(
				'name'     => 'Belmont Classic',
				'slug'     => 'belmont-classic',
				'guests'   => '10+ guests',
				'summary'  => 'The essentials, done right. Built for office lunches and casual get-togethers.',
				'price'    => '$20',
				'features' => array( '1 smoked meat', '2 house-made sides', 'Buns, sauce, pickles and pickled onions', 'Pickup or drop-off', 'Full catering pans for 20+ guests' ),
				'popular'  => false,
			),
			array(
				'name'     => 'Monticello Smokehouse',
				'slug'     => 'monticello-smokehouse',
				'guests'   => '10+ guests',
				'summary'  => 'A full spread with dessert. Our go-to for rehearsal dinners, parties and team events.',
				'price'    => '$26',
				'features' => array( '2 smoked meats', '3 house-made sides', 'Your choice of dessert', 'Buns, sauce, pickles and pickled onions', 'Pickup, drop-off or onsite setup' ),
				'popular'  => true,
			),
			array(
				'name'     => 'Jefferson Reserve',
				'slug'     => 'jefferson-reserve',
				'guests'   => '20+ guests · Full service',
				'summary'  => 'We bring the whole operation — staff, setup and service — so you can enjoy your own event.',
				'price'    => '$38',
				'features' => array( '3 smoked meats, premium cuts included', '3 sides plus sweet cornbread with hot honey', 'Dessert, sweet tea and lemonade', 'Delivery, full setup and breakdown', '2 staff and 3 hours of buffet service' ),
				'popular'  => false,
			),
		)
	);
}

/**
 * Service styles: value = the catering plugin's service_style label.
 *
 * @return list<array{value: string, label: string, short: string, what: string, packages: string}>
 */
function twistedoak_service_styles(): array {
	return (array) apply_filters(
		'twistedoak_service_styles',
		array(
			array(
				'value'    => 'Pickup',
				'label'    => 'Pickup',
				'short'    => 'Grab it hot from Beacon Kitchen',
				'what'     => 'Hot and packed in catering pans, ready at Beacon Kitchen',
				'packages' => 'Belmont, Monticello',
			),
			array(
				'value'    => 'Drop-off',
				'label'    => 'Drop-off',
				'short'    => 'Delivered to your door',
				'what'     => 'Delivered hot to your door at your serving time',
				'packages' => 'Belmont, Monticello',
			),
			array(
				'value'    => 'Onsite setup',
				'label'    => 'Onsite setup',
				'short'    => 'Delivered and set up as a buffet',
				'what'     => 'Delivered and set up as a self-serve buffet',
				'packages' => 'Monticello',
			),
			array(
				'value'    => 'Full service (staffed)',
				'label'    => 'Full service',
				'short'    => 'Staff on site to serve and clean up',
				'what'     => 'Staff, setup, 3 hours of buffet service and breakdown',
				'packages' => 'Jefferson Reserve',
			),
		)
	);
}

/**
 * The catering menu summary under the package cards.
 *
 * @return list<array{title: string, items: string, note?: string}>
 */
function twistedoak_catering_menu_summary(): array {
	return (array) apply_filters(
		'twistedoak_catering_menu_summary',
		array(
			array(
				'title' => 'Smoked meats',
				'items' => 'Pulled pork · Pulled BBQ chicken · Smoked turkey breast · St. Louis cut ribs · Smoked tofu burnt ends',
				'note'  => 'Premium add-ons: Texas-style brisket [+$X] · Pork belly [+$X] · Cherry-smoked salmon [+$X] per guest',
			),
			array(
				'title' => 'Southern sides',
				'items' => 'Pit beans · White cheddar mac & cheese · Esquites · Loaded potato salad · Sweet slaw · Cornbread with hot honey',
			),
			array(
				'title' => 'Desserts',
				'items' => 'Banana pudding · Southern apple crisp',
			),
		)
	);
}

/**
 * Testimonials. The design ships placeholders; replace with real reviews.
 *
 * @return list<array{quote: string, name: string, context: string}>
 */
function twistedoak_testimonials(): array {
	return (array) apply_filters(
		'twistedoak_testimonials',
		array(
			array(
				'quote'   => '[Customer quote — a wedding review that mentions the brisket and the service]',
				'name'    => '[Customer name]',
				'context' => '[Wedding · Month Year]',
			),
			array(
				'quote'   => '[Customer quote — a corporate client on timing, setup and how the team was fed]',
				'name'    => '[Customer name]',
				'context' => '[Company · Corporate event]',
			),
			array(
				'quote'   => '[Customer quote — a private party guest on the turkey, ribs or banana pudding]',
				'name'    => '[Customer name]',
				'context' => '[Private party · Charlottesville]',
			),
		)
	);
}

/**
 * FAQ sets. Answers may contain inline links (kses'd on output).
 *
 * @return list<array{q: string, a: string}>
 */
function twistedoak_faqs( string $set ): array {
	$sets = array(
		'catering' => array(
			array(
				'q' => 'Is there a minimum order?',
				'a' => 'Our packages start at 10 guests. Jefferson Reserve full service starts at 20.',
			),
			array(
				'q' => 'How far ahead should I book?',
				'a' => '[Lead time guidance — e.g. how far ahead for weddings vs. office lunches]',
			),
			array(
				'q' => 'Can you handle vegetarian and dietary needs?',
				'a' => 'Yes — our smoked tofu burnt ends are a guest favorite. [Allergen and gluten-free details]',
			),
			array(
				'q' => 'Do you charge for delivery?',
				'a' => '[Delivery fee and radius]',
			),
			array(
				'q' => 'What’s the deposit and cancellation policy?',
				'a' => '[Deposit amount, due date and cancellation terms]',
			),
		),
		'events'   => array(
			array(
				'q' => 'Can I pre-order?',
				'a' => 'For events marked “Pre-order open,” yes — order online and skip the line. [Pre-order cutoff]',
			),
			array(
				'q' => 'What’s on the menu at pop-ups?',
				'a' => sprintf( 'A shorter version of our <a href="%s">full menu</a>, plus whatever special we’re testing that week.', esc_url( twistedoak_menu_url() ) ),
			),
			array(
				'q' => 'Do you take cards?',
				'a' => '[Payment methods accepted]',
			),
			array(
				'q' => 'Are vegetarian options available?',
				'a' => 'Usually — our smoked tofu burnt ends make most pop-up menus. [Confirm per event]',
			),
		),
	);
	return (array) apply_filters( 'twistedoak_faqs', $sets[ $set ] ?? array(), $set );
}

/**
 * Small placeholders the design leaves open, used inline in copy.
 */
function twistedoak_copy( string $key ): string {
	$copy = array(
		'response_time' => '[response time]',
		'deposit_terms' => '[deposit terms]',
		'lead_time'     => '[lead time]',
		'pickup_hours'  => '[Pickup days and hours]',
	);
	$copy = (array) apply_filters( 'twistedoak_copy', $copy );
	return (string) ( $copy[ $key ] ?? '' );
}
