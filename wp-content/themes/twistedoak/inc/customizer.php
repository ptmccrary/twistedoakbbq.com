<?php
/**
 * Customizer: business details and the photo slots the design calls for.
 *
 * Photos: every image in the design is a named slot. Until a slot has an image, the page shows a
 * labelled placeholder (the design's own caption), so it's obvious what to shoot.
 *
 * @package TwistedOak
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Photo slots: key => [label, placeholder caption, alt text].
 *
 * @return array<string, array{0: string, 1: string, 2: string}>
 */
function twistedoak_photo_slots(): array {
	return array(
		'home_hero'          => array( 'Home: hero', 'sliced brisket, smoke ring, butcher paper, low warm light', 'Sliced brisket with a deep smoke ring on butcher paper' ),
		'home_story'         => array( 'Home: our story', 'Michael at the smoker, golden hour', 'Pitmaster Michael McCrary at the smoker' ),
		'catering_hero'      => array( 'Catering: hero', 'full buffet spread at an outdoor event, guests in soft focus', 'Buffet line of brisket, ribs and sides set up at an outdoor event' ),
		'catering_weddings'  => array( 'Catering: weddings', 'wedding buffet, string lights', 'Wedding reception buffet' ),
		'catering_corporate' => array( 'Catering: corporate', 'office lunch, catering pans', 'Office lunch spread in catering pans' ),
		'catering_private'   => array( 'Catering: private parties', 'backyard party, picnic table', 'Backyard party around a picnic table' ),
		'menu_proteins'      => array( 'Menu: smoked meats', 'sliced brisket on butcher paper', 'Sliced brisket on butcher paper' ),
		'menu_sides'         => array( 'Menu: sides', 'mac & cheese, slaw, cornbread', 'Mac and cheese, slaw and cornbread' ),
		'menu_desserts'      => array( 'Menu: desserts', 'banana pudding', 'Banana pudding' ),
		'events_next'        => array( 'Events: next-up card', 'smoker trailer at a brewery pop-up, line forming', 'The smoker trailer parked outside a brewery, smoke rising' ),
		'story_hero'         => array( 'Our story: hero', 'smoker trailer at an evening event, smoke rising', 'The Twisted Oak smoker trailer at an evening event' ),
		'story_portrait'     => array( 'Our story: Michael', 'Michael McCrary, portrait at the pit', 'Pitmaster Michael McCrary' ),
		'story_texas'        => array( 'Our story: Texas inspired', 'brisket bark, slicing', 'Brisket with dark bark being sliced' ),
		'story_tennessee'    => array( 'Our story: Tennessee rooted', 'pulled pork and ribs', 'Pulled pork and ribs on a tray' ),
		'story_virginia'     => array( 'Our story: Crafted in Virginia', 'catering spread, Virginia venue', 'Catering spread at a Central Virginia wedding' ),
		'story_wood'         => array( 'Our story: how we cook', 'split cherry, hickory & oak by the smoker', 'Split cherry, hickory and oak stacked beside the smoker' ),
	);
}

add_action(
	'customize_register',
	static function ( WP_Customize_Manager $wp_customize ): void {
		$wp_customize->add_panel(
			'twistedoak',
			array(
				'title'    => __( 'Twisted Oak', 'twistedoak' ),
				'priority' => 30,
			)
		);

		$wp_customize->add_section(
			'twistedoak_details',
			array(
				'title' => __( 'Business details', 'twistedoak' ),
				'panel' => 'twistedoak',
			)
		);
		$fields = array(
			'phone'     => array( __( 'Phone', 'twistedoak' ), 'text', '434-260-0010' ),
			'email'     => array( __( 'Email', 'twistedoak' ), 'email', 'info@twistedoakbbq.com' ),
			'address'   => array( __( 'Address (one line per row)', 'twistedoak' ), 'textarea', "Beacon Kitchen\n221 Carlton Rd, Suite 1\nCharlottesville, VA 22902" ),
			'area'      => array( __( 'Service area', 'twistedoak' ), 'text', 'Charlottesville, Albemarle County and the surrounding area' ),
			'order_url' => array( __( 'Online ordering URL', 'twistedoak' ), 'url', 'https://twistedoakbbq.square.site' ),
			'instagram' => array( __( 'Instagram URL', 'twistedoak' ), 'url', 'https://www.instagram.com/twistedoakbbqva' ),
			'facebook'  => array( __( 'Facebook URL', 'twistedoak' ), 'url', 'https://www.facebook.com/458708613994228' ),
			'swag_url'  => array( __( 'Merch store URL', 'twistedoak' ), 'url', 'https://twisted-oak-barbecue.printify.me' ),
		);
		foreach ( $fields as $key => [ $label, $type, $default ] ) {
			$wp_customize->add_setting(
				'twistedoak_' . $key,
				array(
					'default'           => $default,
					'sanitize_callback' => 'url' === $type ? 'esc_url_raw' : ( 'email' === $type ? 'sanitize_email' : ( 'textarea' === $type ? 'sanitize_textarea_field' : 'sanitize_text_field' ) ),
				)
			);
			$wp_customize->add_control(
				'twistedoak_' . $key,
				array(
					'label'   => $label,
					'section' => 'twistedoak_details',
					'type'    => $type,
				)
			);
		}

		$wp_customize->add_section(
			'twistedoak_photos',
			array(
				'title'       => __( 'Photos', 'twistedoak' ),
				'panel'       => 'twistedoak',
				'description' => __( 'Each slot shows a labelled placeholder until you pick an image.', 'twistedoak' ),
			)
		);
		foreach ( twistedoak_photo_slots() as $slot => [ $label, $caption ] ) {
			$wp_customize->add_setting(
				'twistedoak_photo_' . $slot,
				array(
					'default'           => 0,
					'sanitize_callback' => 'absint',
				)
			);
			$wp_customize->add_control(
				new WP_Customize_Media_Control(
					$wp_customize,
					'twistedoak_photo_' . $slot,
					array(
						'label'       => $label,
						'description' => $caption,
						'section'     => 'twistedoak_photos',
						'mime_type'   => 'image',
					)
				)
			);
		}
	}
);
