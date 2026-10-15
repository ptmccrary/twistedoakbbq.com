<?php
/**
 * Settings → Catering.
 *
 * @package TwistedOakCatering
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	static function (): void {
		// Old location (Settings → Catering) redirects to the Catering menu.
		global $pagenow;
		if ( 'options-general.php' === $pagenow && 'twistedoak-catering' === ( $_GET['page'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( admin_url( 'edit.php?post_type=' . TWISTEDOAK_CATERING_CPT . '&page=twistedoak-catering' ) );
			exit;
		}
		add_submenu_page(
			'edit.php?post_type=' . TWISTEDOAK_CATERING_CPT,
			__( 'Catering Settings', 'twisted-oak-catering' ),
			__( 'Settings', 'twisted-oak-catering' ),
			'manage_options',
			'twistedoak-catering',
			'twistedoak_catering_render_settings_page'
		);
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( TWISTEDOAK_CATERING_FILE ),
	static function ( array $links ): array {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'edit.php?post_type=' . TWISTEDOAK_CATERING_CPT . '&page=twistedoak-catering' ) ), esc_html__( 'Settings', 'twisted-oak-catering' ) )
		);
		return $links;
	}
);

add_action(
	'admin_init',
	static function (): void {
		register_setting(
			'twistedoak_catering',
			TWISTEDOAK_CATERING_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => 'twistedoak_catering_sanitize_settings',
				'default'           => twistedoak_catering_default_settings(),
			)
		);

		add_settings_section( 'twistedoak_catering_main', __( 'Notifications', 'twisted-oak-catering' ), '__return_false', 'twistedoak-catering' );
		add_settings_section( 'twistedoak_catering_form', __( 'Form options', 'twisted-oak-catering' ), '__return_false', 'twistedoak-catering' );

		$fields = array(
			'admin_email'      => array( 'twistedoak_catering_main', __( 'Recipient email', 'twisted-oak-catering' ), 'email', __( 'Where new inquiries are sent. Leave blank to use the site admin email.', 'twisted-oak-catering' ) ),
			'success_message'  => array( 'twistedoak_catering_main', __( 'Thank-you message', 'twisted-oak-catering' ), 'textarea', __( 'Shown after a successful submission.', 'twisted-oak-catering' ) ),
			'rate_limit'       => array( 'twistedoak_catering_main', __( 'Submissions per hour', 'twisted-oak-catering' ), 'number', __( 'Maximum inquiries accepted from one IP address per hour.', 'twisted-oak-catering' ) ),
			'packages'         => array( 'twistedoak_catering_form', __( 'Packages', 'twisted-oak-catering' ), 'lines', __( 'One per line, in display order.', 'twisted-oak-catering' ) ),
			'premium_packages' => array( 'twistedoak_catering_form', __( 'Premium packages', 'twisted-oak-catering' ), 'lines', __( 'One per line. These unlock premium proteins.', 'twisted-oak-catering' ) ),
			'premium_proteins' => array( 'twistedoak_catering_form', __( 'Premium proteins', 'twisted-oak-catering' ), 'lines', __( 'Menu item titles (one per line) only offered with premium packages. Menu items with the _tobbq_premium meta are premium too.', 'twisted-oak-catering' ) ),
		);

		foreach ( $fields as $key => [ $section, $label, $type, $help ] ) {
			add_settings_field(
				$key,
				$label,
				'twistedoak_catering_render_setting_field',
				'twistedoak-catering',
				$section,
				array(
					'key'       => $key,
					'type'      => $type,
					'help'      => $help,
					'label_for' => 'twistedoak-catering-' . $key,
				)
			);
		}
	}
);

/**
 * @param mixed $input Raw option input.
 * @return array<string, mixed>
 */
function twistedoak_catering_sanitize_settings( mixed $input ): array {
	$input    = is_array( $input ) ? $input : array();
	$defaults = twistedoak_catering_default_settings();
	$clean    = array();

	$email = sanitize_email( (string) ( $input['admin_email'] ?? '' ) );
	if ( '' !== trim( (string) ( $input['admin_email'] ?? '' ) ) && '' === $email ) {
		add_settings_error( TWISTEDOAK_CATERING_OPTION, 'admin_email', __( 'The recipient email is not valid; it was cleared.', 'twisted-oak-catering' ) );
	}
	$clean['admin_email'] = $email;

	foreach ( array( 'packages', 'premium_packages', 'premium_proteins' ) as $key ) {
		$lines         = twistedoak_catering_lines( (string) ( $input[ $key ] ?? '' ) );
		$clean[ $key ] = implode( "\n", array_map( 'sanitize_text_field', $lines ) );
	}
	if ( '' === $clean['packages'] ) {
		$clean['packages'] = $defaults['packages'];
		add_settings_error( TWISTEDOAK_CATERING_OPTION, 'packages', __( 'At least one package is required; defaults restored.', 'twisted-oak-catering' ) );
	}

	$clean['success_message'] = sanitize_textarea_field( (string) ( $input['success_message'] ?? '' ) );
	if ( '' === $clean['success_message'] ) {
		$clean['success_message'] = $defaults['success_message'];
	}

	$clean['rate_limit'] = max( 1, min( 100, absint( $input['rate_limit'] ?? $defaults['rate_limit'] ) ) );

	return $clean;
}

/**
 * @param array{key: string, type: string, help: string, label_for: string} $args Field args.
 */
function twistedoak_catering_render_setting_field( array $args ): void {
	$value = (string) twistedoak_catering_setting( $args['key'] );
	$name  = TWISTEDOAK_CATERING_OPTION . '[' . $args['key'] . ']';
	$id    = $args['label_for'];

	switch ( $args['type'] ) {
		case 'lines':
		case 'textarea':
			printf(
				'<textarea id="%1$s" name="%2$s" rows="%3$d" class="large-text">%4$s</textarea>',
				esc_attr( $id ),
				esc_attr( $name ),
				'lines' === $args['type'] ? 5 : 3,
				esc_textarea( $value )
			);
			break;
		case 'number':
			printf( '<input type="number" min="1" max="100" id="%1$s" name="%2$s" value="%3$s" class="small-text">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
			break;
		default:
			printf(
				'<input type="email" id="%1$s" name="%2$s" value="%3$s" class="regular-text" placeholder="%4$s">',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $value ),
				esc_attr( (string) get_option( 'admin_email' ) )
			);
	}
	printf( '<p class="description">%s</p>', esc_html( $args['help'] ) );
}

function twistedoak_catering_render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<p>
			<?php
			printf(
				/* translators: %s: shortcode */
				esc_html__( 'Add the form to any page with the %s shortcode or the “Catering Inquiry Form” block.', 'twisted-oak-catering' ),
				'<code>[twisted_oak_catering]</code>'
			);
			?>
		</p>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'twistedoak_catering' );
			do_settings_sections( 'twistedoak-catering' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}
