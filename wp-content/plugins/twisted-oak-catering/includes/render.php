<?php
/**
 * Front-end form markup, shortcode, block registration and assets.
 *
 * The form is server-rendered as one <form> containing three fieldset steps so
 * it works without JavaScript (all steps visible, posts to admin-post.php).
 * catering-form.js upgrades it to the one-step-at-a-time wizard and submits to
 * the REST route. Class names follow the old module's JS-rendered markup.
 *
 * @package TwistedOakCatering
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function (): void {
		$css = TWISTEDOAK_CATERING_DIR . '/assets/catering-form.css';
		$js  = TWISTEDOAK_CATERING_DIR . '/assets/catering-form.js';

		wp_register_style(
			'tobbq-catering',
			TWISTEDOAK_CATERING_URL . 'assets/catering-form.css',
			array(),
			(string) ( filemtime( $css ) ?: TWISTEDOAK_CATERING_VERSION )
		);
		wp_register_script(
			'tobbq-catering',
			TWISTEDOAK_CATERING_URL . 'assets/catering-form.js',
			array(),
			(string) ( filemtime( $js ) ?: TWISTEDOAK_CATERING_VERSION ),
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		add_shortcode( 'twisted_oak_catering', 'twistedoak_catering_shortcode' );
		register_block_type( TWISTEDOAK_CATERING_DIR . '/blocks/catering-form' );
	}
);

/**
 * Enqueue in <head> when the queried post contains the form, so CSS isn't
 * printed late in the footer. Rendering also enqueues as a fallback (widgets,
 * templates, etc.).
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$post = get_queried_object();
		if ( is_singular() && $post instanceof WP_Post
			&& ( has_shortcode( $post->post_content, 'twisted_oak_catering' ) || has_block( 'twisted-oak/catering-form', $post ) ) ) {
			twistedoak_catering_enqueue_assets();
		}
	},
	20
);

function twistedoak_catering_enqueue_assets(): void {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;

	wp_enqueue_style( 'tobbq-catering' );
	wp_enqueue_script( 'tobbq-catering' );
	wp_localize_script(
		'tobbq-catering',
		'twistedoakCatering',
		// Scheduling config (endpoints, nonce, schedule rules, packages, products) — see SCHEDULING.md.
		twistedoak_catering_public_config() + array(
			'restUrl'   => esc_url_raw( rest_url( 'twisted-oak/v1/catering' ) ),
			// Keeps a logged-in user authenticated on the REST request so the form nonce (user-bound) verifies.
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'i18n'      => array(
				'selectPackage' => __( 'Please select a package to continue.', 'twisted-oak-catering' ),
				'required'      => __( 'Please fill in your name and email.', 'twisted-oak-catering' ),
				'invalidEmail'  => __( 'Please enter a valid email address.', 'twisted-oak-catering' ),
				'thanks'        => __( 'Thank you!', 'twisted-oak-catering' ),
				'sending'       => __( 'Sending…', 'twisted-oak-catering' ),
				'generic'       => __( 'Something went wrong. Please try again.', 'twisted-oak-catering' ),
				'network'       => __( 'Network error. Please check your connection and try again.', 'twisted-oak-catering' ),
				/* translators: 1: current step, 2: total steps */
				'stepOf'        => __( 'Step %1$s of %2$s', 'twisted-oak-catering' ),
			),
		)
	);
}

/**
 * Shortcode callback.
 *
 * @param array<string, string>|string $atts Unused.
 */
function twistedoak_catering_shortcode( array|string $atts = array() ): string {
	return twistedoak_catering_render_form();
}

/**
 * Read back the values of a failed no-JS submission (stored under a one-time token).
 *
 * @return array{values: array<string, mixed>, error: string}
 */
function twistedoak_catering_flash(): array {
	static $flash = null;
	if ( null !== $flash ) {
		return $flash;
	}
	$flash = array(
		'values' => array(),
		'error'  => '',
	);

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only status flags.
	$status = isset( $_GET['catering'] ) ? sanitize_key( wp_unslash( $_GET['catering'] ) ) : '';
	if ( 'error' === $status ) {
		$code           = isset( $_GET['catering_error'] ) ? sanitize_key( wp_unslash( $_GET['catering_error'] ) ) : 'generic';
		$flash['error'] = $code;
		$token          = isset( $_GET['catering_token'] ) ? preg_replace( '/[^a-zA-Z0-9]/', '', (string) wp_unslash( $_GET['catering_token'] ) ) : '';
		if ( $token ) {
			$stored = get_transient( 'tobbq_catering_flash_' . $token );
			if ( is_array( $stored ) ) {
				$flash['values'] = $stored;
			}
		}
	}
	// phpcs:enable

	return $flash;
}

/**
 * Render the whole form.
 */
function twistedoak_catering_render_form(): string {
	static $instance = 0;
	++$instance;
	twistedoak_catering_enqueue_assets();

	$p        = 'catering-' . ( $instance > 1 ? $instance . '-' : '' );
	$settings = twistedoak_catering_settings();
	$packages = twistedoak_catering_packages();
	$products = twistedoak_catering_products();
	$premium  = twistedoak_catering_premium_packages();
	$flash    = twistedoak_catering_flash();
	$old      = $flash['values'];
	$status   = isset( $_GET['catering'] ) ? sanitize_key( wp_unslash( $_GET['catering'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$val     = static fn( string $k ): string => isset( $old[ $k ] ) && is_scalar( $old[ $k ] ) ? (string) $old[ $k ] : '';
	$checked = static fn( string $k, string $v ): bool => isset( $old[ $k ] ) && in_array( $v, (array) $old[ $k ], true );

	$id_attr = 1 === $instance ? ' id="catering-form-mount"' : '';

	ob_start();

	if ( 'sent' === $status ) {
		?>
		<div<?php echo $id_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static string. ?> class="catering-form">
			<div class="catering-success" role="status" tabindex="-1">
				<p class="catering-success__heading"><?php esc_html_e( 'Thank you!', 'twisted-oak-catering' ); ?></p>
				<p><?php echo esc_html( (string) $settings['success_message'] ); ?></p>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	$initial_step = ( $flash['error'] && in_array( $flash['error'], array( 'package', 'premium' ), true ) ) ? ( 'premium' === $flash['error'] ? 2 : 1 ) : ( $flash['error'] ? 3 : 1 );

	$checkbox_group = static function ( string $name, string $legend, array $items, array $premium_items = array() ) use ( $checked, $p ): void {
		if ( ! $items && ! $premium_items ) {
			return;
		}
		?>
		<fieldset class="catering-step__group" data-group="<?php echo esc_attr( $name ); ?>">
			<legend class="catering-step__group-label"><?php echo esc_html( $legend ); ?></legend>
			<?php
			foreach ( array( false => $items, true => $premium_items ) as $is_premium => $list ) :
				foreach ( $list as $item ) :
					?>
					<label class="catering-step__option"<?php echo $is_premium ? ' data-premium' : ''; ?>>
						<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $item ); ?>"<?php checked( $checked( $name, $item ) ); ?>>
						<?php echo esc_html( $item ); ?>
						<?php if ( $is_premium ) : ?>
							<span class="catering-premium-badge"><?php esc_html_e( 'Premium', 'twisted-oak-catering' ); ?></span>
						<?php endif; ?>
					</label>
					<?php
				endforeach;
			endforeach;
			?>
		</fieldset>
		<?php
	};

	$nav_button = static function ( string $action, string $label ): void {
		printf( '<button type="button" class="catering-nav__button" data-action="%1$s" hidden>%2$s</button>', esc_attr( $action ), esc_html( $label ) );
	};
	?>
	<div<?php echo $id_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static string. ?> class="catering-form" data-catering-form data-initial-step="<?php echo esc_attr( (string) $initial_step ); ?>">
		<form class="catering-form__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="twistedoak_catering">
			<input type="hidden" name="tobbq_catering_nonce" value="<?php echo esc_attr( wp_create_nonce( 'twistedoak_catering_submit' ) ); ?>">
			<input type="hidden" name="return_url" value="<?php echo esc_url( twistedoak_catering_current_url() ); ?>">

			<div class="catering-progress" aria-hidden="true" hidden>
				<div class="catering-progress__dot catering-progress__dot--active"></div>
				<div class="catering-progress__dot"></div>
				<div class="catering-progress__dot"></div>
			</div>
			<p class="catering-progress__label visually-hidden" aria-live="polite"></p>

			<?php if ( $flash['error'] ) : ?>
				<p class="catering-error catering-error--summary" role="alert"><?php echo esc_html( twistedoak_catering_error_message( $flash['error'] ) ); ?></p>
			<?php endif; ?>

			<fieldset class="catering-step" data-step="1">
				<legend class="catering-step__legend"><h2 class="catering-step__heading"><?php esc_html_e( 'Select a Package', 'twisted-oak-catering' ); ?></h2></legend>
				<div class="catering-step__group">
					<?php foreach ( $packages as $i => $pkg ) : ?>
						<label class="catering-step__option">
							<input type="radio" name="package" value="<?php echo esc_attr( $pkg ); ?>"<?php checked( $val( 'package' ), $pkg ); ?><?php echo in_array( $pkg, $premium, true ) ? ' data-premium' : ''; ?><?php echo 0 === $i ? ' required' : ''; ?>>
							<?php echo esc_html( $pkg ); ?>
							<?php if ( in_array( $pkg, $premium, true ) ) : ?>
								<span class="catering-premium-badge"><?php esc_html_e( 'Premium', 'twisted-oak-catering' ); ?></span>
							<?php endif; ?>
						</label>
					<?php endforeach; ?>
				</div>
				<p class="catering-error" data-error hidden></p>
				<div class="catering-nav">
					<?php $nav_button( 'next', __( 'Next', 'twisted-oak-catering' ) ); ?>
				</div>
			</fieldset>

			<fieldset class="catering-step" data-step="2">
				<legend class="catering-step__legend"><h2 class="catering-step__heading" data-package-heading><?php esc_html_e( 'Build Your Spread', 'twisted-oak-catering' ); ?></h2></legend>
				<?php if ( $products['premiumMeats'] && $premium ) : ?>
					<p class="catering-step__note" data-nojs-only>
						<?php
						printf(
							/* translators: %s: premium package names */
							esc_html__( 'Premium proteins are available with the %s package.', 'twisted-oak-catering' ),
							esc_html( implode( ' / ', $premium ) )
						);
						?>
					</p>
				<?php endif; ?>
				<?php
				$checkbox_group( 'meats', __( 'Proteins', 'twisted-oak-catering' ), $products['meats'], $products['premiumMeats'] );
				$checkbox_group( 'sides', __( 'Sides', 'twisted-oak-catering' ), $products['sides'] );
				$checkbox_group( 'desserts', __( 'Desserts', 'twisted-oak-catering' ), $products['desserts'] );
				?>
				<p class="catering-error" data-error hidden></p>
				<div class="catering-nav">
					<?php
					$nav_button( 'back', __( 'Back', 'twisted-oak-catering' ) );
					$nav_button( 'next', __( 'Next', 'twisted-oak-catering' ) );
					?>
				</div>
			</fieldset>

			<fieldset class="catering-step" data-step="3">
				<legend class="catering-step__legend"><h2 class="catering-step__heading"><?php esc_html_e( 'Your Information', 'twisted-oak-catering' ); ?></h2></legend>
				<div class="catering-step__field">
					<label for="<?php echo esc_attr( $p ); ?>name"><?php esc_html_e( 'Name', 'twisted-oak-catering' ); ?> <span aria-hidden="true">*</span></label>
					<input type="text" id="<?php echo esc_attr( $p ); ?>name" name="name" value="<?php echo esc_attr( $val( 'name' ) ); ?>" autocomplete="name" maxlength="200" required>
				</div>
				<div class="catering-step__field">
					<label for="<?php echo esc_attr( $p ); ?>email"><?php esc_html_e( 'Email', 'twisted-oak-catering' ); ?> <span aria-hidden="true">*</span></label>
					<input type="email" id="<?php echo esc_attr( $p ); ?>email" name="email" value="<?php echo esc_attr( $val( 'email' ) ); ?>" autocomplete="email" maxlength="200" required>
				</div>
				<div class="catering-step__field">
					<label for="<?php echo esc_attr( $p ); ?>phone"><?php esc_html_e( 'Phone', 'twisted-oak-catering' ); ?></label>
					<input type="tel" id="<?php echo esc_attr( $p ); ?>phone" name="phone" value="<?php echo esc_attr( $val( 'phone' ) ); ?>" autocomplete="tel" maxlength="40">
				</div>
				<div class="catering-step__row">
					<div class="catering-step__field">
						<label for="<?php echo esc_attr( $p ); ?>event-date"><?php esc_html_e( 'Event date', 'twisted-oak-catering' ); ?></label>
						<input type="date" id="<?php echo esc_attr( $p ); ?>event-date" name="event_date" value="<?php echo esc_attr( $val( 'event_date' ) ); ?>" min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>">
					</div>
					<div class="catering-step__field">
						<label for="<?php echo esc_attr( $p ); ?>guests"><?php esc_html_e( 'Guests', 'twisted-oak-catering' ); ?></label>
						<input type="number" id="<?php echo esc_attr( $p ); ?>guests" name="guests" value="<?php echo esc_attr( $val( 'guests' ) ); ?>" min="1" max="10000" inputmode="numeric">
					</div>
				</div>
				<div class="catering-step__field">
					<label for="<?php echo esc_attr( $p ); ?>message"><?php esc_html_e( 'Message', 'twisted-oak-catering' ); ?></label>
					<textarea id="<?php echo esc_attr( $p ); ?>message" name="message" maxlength="5000"><?php echo esc_textarea( $val( 'message' ) ); ?></textarea>
				</div>
				<div class="catering-hp" aria-hidden="true">
					<label for="<?php echo esc_attr( $p ); ?>website"><?php esc_html_e( 'Leave this field empty', 'twisted-oak-catering' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $p ); ?>website" name="website" value="" tabindex="-1" autocomplete="off">
				</div>
				<p class="catering-error" data-error hidden></p>
				<div class="catering-nav">
					<?php $nav_button( 'back', __( 'Back', 'twisted-oak-catering' ) ); ?>
					<button type="submit" class="catering-nav__button" data-action="submit"><?php esc_html_e( 'Submit', 'twisted-oak-catering' ); ?></button>
				</div>
			</fieldset>
		</form>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Current front-end URL without our status query args.
 */
function twistedoak_catering_current_url(): string {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
	if ( wp_is_serving_rest_request() || is_admin() ) {
		$uri = '/';
	}
	return remove_query_arg( array( 'catering', 'catering_error', 'catering_token' ), home_url( $uri ) );
}
