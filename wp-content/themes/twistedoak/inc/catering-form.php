<?php
/**
 * The catering quote form from the design, posting to the twisted-oak-catering plugin's no-JS
 * endpoint (admin-post.php?action=twistedoak_catering). The plugin validates, stores the lead,
 * sends the emails and redirects back with ?catering=sent or ?catering=error (+ a flash token
 * holding the submitted values).
 *
 * @package TwistedOak
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

function twistedoak_catering_available(): bool {
	return function_exists( 'twistedoak_catering_flash' ) && function_exists( 'twistedoak_catering_packages' );
}

/**
 * Minimum guests (from the plugin's schedule settings when available).
 */
function twistedoak_catering_min_guests(): int {
	if ( function_exists( 'twistedoak_catering_schedule' ) ) {
		return max( 1, (int) ( twistedoak_catering_schedule()['min_guests'] ?? 10 ) );
	}
	return 10;
}

/**
 * Which field an error code belongs to.
 */
function twistedoak_catering_error_field( string $code ): string {
	$map = array(
		'name'             => 'name',
		'email'            => 'email',
		'event_date'       => 'event_date',
		'date_unavailable' => 'event_date',
		'unavailable'      => 'event_date',
		'guests'           => 'guests',
		'guests_min'       => 'guests',
		'guests_max'       => 'guests',
		'package'          => 'package',
		'service_style'    => 'service_style',
	);
	return $map[ $code ] ?? '';
}

/**
 * Render the quote form (or the thank-you / fallback state).
 */
function twistedoak_catering_form(): string {
	$info = twistedoak_info();

	if ( ! twistedoak_catering_available() ) {
		return twistedoak_alert(
			__( 'Online quotes are offline right now', 'twistedoak' ),
			sprintf(
				/* translators: 1: phone link, 2: email link */
				__( 'Call %1$s or email %2$s and we’ll put a quote together.', 'twistedoak' ),
				'<a href="' . esc_url( $info['phone_href'] ) . '">' . esc_html( $info['phone'] ) . '</a>',
				'<a href="mailto:' . esc_attr( $info['email'] ) . '">' . esc_html( $info['email'] ) . '</a>'
			),
			'warning'
		);
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only flags.
	$status = isset( $_GET['catering'] ) ? sanitize_key( wp_unslash( $_GET['catering'] ) ) : '';
	$picked = isset( $_GET['package'] ) ? sanitize_title( wp_unslash( $_GET['package'] ) ) : '';
	// phpcs:enable

	if ( 'sent' === $status ) {
		$message = function_exists( 'twistedoak_catering_setting' ) ? (string) twistedoak_catering_setting( 'success_message' ) : '';
		return '<div class="quote-form quote-form--sent" id="catering-form-mount" tabindex="-1">'
			. twistedoak_alert( __( 'Thanks — your request is in', 'twistedoak' ), $message ?: __( 'We’ll be in touch shortly with a menu and quote.', 'twistedoak' ), 'success' )
			. '<p class="quote-form__again"><a href="' . esc_url( remove_query_arg( array( 'catering', 'catering_error', 'catering_token', 'package' ) ) . '#catering-form' ) . '">' . esc_html__( 'Send another request', 'twistedoak' ) . '</a></p>'
			. '</div>';
	}

	$flash = twistedoak_catering_flash();
	$old   = $flash['values'];
	$code  = (string) $flash['error'];
	$bad   = twistedoak_catering_error_field( $code );
	$val   = static fn( string $k ): string => isset( $old[ $k ] ) && is_scalar( $old[ $k ] ) ? (string) $old[ $k ] : '';

	$packages = twistedoak_catering_packages();
	$current  = $val( 'package' );
	if ( '' === $current && '' !== $picked ) {
		foreach ( $packages as $p ) {
			if ( sanitize_title( $p ) === $picked ) {
				$current = $p;
			}
		}
	}
	$custom     = in_array( 'Custom', $packages, true ) ? 'Custom' : '';
	$min_guests = twistedoak_catering_min_guests();
	$styles     = function_exists( 'twistedoak_catering_service_styles' ) ? twistedoak_catering_service_styles() : array();
	$service    = $val( 'service_style' ) ?: 'Drop-off';

	$field = static function ( array $f ) use ( $bad, $code ): string {
		$id       = 'quote-' . $f['name'];
		$hint_id  = $id . '-hint';
		$err_id   = $id . '-err';
		$invalid  = $bad === $f['name'];
		$describe = trim( ( ! empty( $f['hint'] ) && ! $invalid ? $hint_id : '' ) . ( $invalid ? ' ' . $err_id : '' ) );
		$attrs    = sprintf( ' id="%1$s" name="%2$s"%3$s%4$s', esc_attr( $id ), esc_attr( $f['name'] ), $invalid ? ' aria-invalid="true"' : '', $describe ? ' aria-describedby="' . esc_attr( $describe ) . '"' : '' );
		foreach ( $f['attrs'] ?? array() as $k => $v ) {
			$attrs .= true === $v ? ' ' . esc_attr( $k ) : sprintf( ' %s="%s"', esc_attr( $k ), esc_attr( (string) $v ) );
		}

		$out  = '<div class="to-field' . ( ! empty( $f['wide'] ) ? ' field--wide' : '' ) . '">';
		$out .= '<label class="to-label" for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] )
			. ( ! empty( $f['optional'] ) ? '<span class="to-label__opt"> ' . esc_html__( '(optional)', 'twistedoak' ) . '</span>' : '' ) . '</label>';

		if ( 'select' === $f['type'] ) {
			$out .= '<div class="to-select-wrap"><select class="to-select"' . $attrs . '>';
			foreach ( $f['options'] as $opt ) {
				$out .= sprintf( '<option value="%s"%s%s>%s</option>', esc_attr( $opt['value'] ), selected( $f['value'], $opt['value'], false ), ! empty( $opt['disabled'] ) ? ' disabled' : '', esc_html( $opt['label'] ) );
			}
			$out .= '</select>' . twistedoak_icon( 'chevronDown' ) . '</div>';
		} elseif ( 'textarea' === $f['type'] ) {
			$out .= '<textarea class="to-textarea"' . $attrs . '>' . esc_textarea( $f['value'] ) . '</textarea>';
		} else {
			$out .= sprintf( '<input class="to-input" type="%s" value="%s"%s>', esc_attr( $f['type'] ), esc_attr( $f['value'] ), $attrs );
		}

		if ( $invalid ) {
			$out .= '<p class="to-error-msg" id="' . esc_attr( $err_id ) . '">' . twistedoak_icon( 'error' ) . esc_html( twistedoak_catering_error_message( $code ) ) . '</p>';
		} elseif ( ! empty( $f['hint'] ) ) {
			$out .= '<p class="to-hint" id="' . esc_attr( $hint_id ) . '">' . esc_html( $f['hint'] ) . '</p>';
		}
		return $out . '</div>';
	};

	$event_types = array( 'Wedding or rehearsal dinner', 'Corporate or team event', 'Private party', 'Other' );
	$type_opts   = array_merge(
		array(
			array(
				'value'    => '',
				'label'    => __( 'Choose one', 'twistedoak' ),
				'disabled' => false,
			),
		),
		array_map( static fn( string $t ): array => array( 'value' => $t, 'label' => $t ), $event_types )
	);
	$pkg_opts    = array();
	if ( $custom ) {
		$pkg_opts[] = array(
			'value' => $custom,
			'label' => __( 'Not sure yet', 'twistedoak' ),
		);
	}
	foreach ( $packages as $p ) {
		if ( $p !== $custom ) {
			$pkg_opts[] = array(
				'value' => $p,
				'label' => $p,
			);
		}
	}

	ob_start();
	?>
	<div class="quote-form" id="catering-form-mount">
		<form class="quote-form__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" aria-labelledby="form-title" novalidate data-quote-form>
			<input type="hidden" name="action" value="twistedoak_catering">
			<input type="hidden" name="tobbq_catering_nonce" value="<?php echo esc_attr( wp_create_nonce( 'twistedoak_catering_submit' ) ); ?>">
			<input type="hidden" name="return_url" value="<?php echo esc_url( remove_query_arg( array( 'catering', 'catering_error', 'catering_token', 'package' ), twistedoak_catering_url() ) ); ?>">

			<?php
			if ( '' !== $code ) {
				twistedoak_e( twistedoak_alert( __( 'We couldn’t send that yet', 'twistedoak' ), twistedoak_catering_error_message( $code ), 'error', array( 'tabindex' => '-1' ) ) );
			}
			?>

			<fieldset class="quote-form__group">
				<legend class="quote-form__legend"><?php esc_html_e( 'Your details', 'twistedoak' ); ?></legend>
				<?php
				twistedoak_e(
					$field(
						array(
							'name'  => 'name',
							'label' => __( 'Full name', 'twistedoak' ),
							'type'  => 'text',
							'value' => $val( 'name' ),
							'attrs' => array(
								'autocomplete' => 'name',
								'maxlength'    => '200',
								'required'     => true,
							),
						)
					)
				);
				twistedoak_e(
					$field(
						array(
							'name'  => 'phone',
							'label' => __( 'Phone', 'twistedoak' ),
							'type'  => 'tel',
							'value' => $val( 'phone' ),
							'attrs' => array(
								'autocomplete' => 'tel',
								'maxlength'    => '40',
							),
						)
					)
				);
				twistedoak_e(
					$field(
						array(
							'name'  => 'email',
							'label' => __( 'Email', 'twistedoak' ),
							'type'  => 'email',
							'value' => $val( 'email' ),
							'wide'  => true,
							'attrs' => array(
								'autocomplete' => 'email',
								'maxlength'    => '200',
								'required'     => true,
							),
						)
					)
				);
				?>
			</fieldset>

			<fieldset class="quote-form__group">
				<legend class="quote-form__legend"><?php esc_html_e( 'Your event', 'twistedoak' ); ?></legend>
				<?php
				twistedoak_e(
					$field(
						array(
							'name'  => 'event_date',
							'label' => __( 'Event date', 'twistedoak' ),
							'type'  => 'date',
							'value' => $val( 'event_date' ),
							'attrs' => array(
								'min'      => wp_date( 'Y-m-d' ),
								'required' => true,
							),
						)
					)
				);
				twistedoak_e(
					$field(
						array(
							'name'  => 'guests',
							'label' => __( 'Guest count', 'twistedoak' ),
							'type'  => 'number',
							'value' => $val( 'guests' ),
							/* translators: %d: minimum number of guests */
							'hint'  => sprintf( __( '%d guest minimum', 'twistedoak' ), $min_guests ),
							'attrs' => array(
								'min'       => (string) $min_guests,
								'max'       => '10000',
								'inputmode' => 'numeric',
								'required'  => true,
							),
						)
					)
				);
				twistedoak_e(
					$field(
						array(
							'name'    => 'event_type',
							'label'   => __( 'Event type', 'twistedoak' ),
							'type'    => 'select',
							'value'   => $val( 'event_type' ),
							'options' => $type_opts,
						)
					)
				);
				twistedoak_e(
					$field(
						array(
							'name'     => 'package',
							'label'    => __( 'Package', 'twistedoak' ),
							'type'     => 'select',
							'value'    => $current ?: $custom,
							'optional' => true,
							'options'  => $pkg_opts,
						)
					)
				);
				twistedoak_e(
					$field(
						array(
							'name'  => 'location',
							'label' => __( 'Event location', 'twistedoak' ),
							'type'  => 'text',
							'value' => $val( 'location' ),
							'hint'  => __( 'Helps us quote delivery and setup', 'twistedoak' ),
							'wide'  => true,
							'attrs' => array(
								'placeholder' => __( 'Venue name or town', 'twistedoak' ),
								'maxlength'   => '300',
							),
						)
					)
				);
				?>
			</fieldset>

			<?php if ( $styles ) : ?>
				<fieldset class="to-fieldset"<?php echo 'service_style' === $bad ? ' aria-invalid="true"' : ''; ?>>
					<legend class="to-label"><?php esc_html_e( 'Service style', 'twistedoak' ); ?></legend>
					<?php
					$described = array_column( twistedoak_service_styles(), null, 'value' );
					foreach ( $styles as $style ) :
						$d = $described[ $style ] ?? null;
						?>
						<label class="to-choice">
							<input class="to-choice__input" type="radio" name="service_style" value="<?php echo esc_attr( $style ); ?>"<?php checked( $service, $style ); ?>>
							<span class="to-box to-box--radio" aria-hidden="true"></span>
							<span><?php echo esc_html( $d['label'] ?? $style ); ?>
								<?php if ( $d ) : ?>
									<span class="to-choice__desc"><?php echo esc_html( $d['short'] ); ?></span>
								<?php endif; ?>
							</span>
						</label>
					<?php endforeach; ?>
				</fieldset>
			<?php endif; ?>

			<?php
			twistedoak_e(
				$field(
					array(
						'name'     => 'message',
						'label'    => __( 'Anything else?', 'twistedoak' ),
						'type'     => 'textarea',
						'value'    => $val( 'message' ),
						'optional' => true,
						'attrs'    => array(
							'rows'        => '4',
							'maxlength'   => '5000',
							'placeholder' => __( 'Favorite meats, dietary needs, timing, rentals…', 'twistedoak' ),
						),
					)
				)
			);
			?>

			<div class="quote-form__hp" aria-hidden="true">
				<label for="quote-website"><?php esc_html_e( 'Leave this field empty', 'twistedoak' ); ?></label>
				<input type="text" id="quote-website" name="website" value="" tabindex="-1" autocomplete="off">
			</div>

			<div class="quote-form__foot">
				<p class="quote-form__note">
					<?php
					/* translators: %s: response time, e.g. "one business day" */
					echo esc_html( sprintf( __( 'We’ll reply within %s with a menu and quote.', 'twistedoak' ), twistedoak_copy( 'response_time' ) ) );
					?>
				</p>
				<?php
				twistedoak_e(
					twistedoak_button(
						__( 'Get my quote', 'twistedoak' ),
						array(
							'size'     => 'lg',
							'type'     => 'submit',
							'icon_end' => 'arrowRight',
						)
					)
				);
				?>
			</div>
		</form>
	</div>
	<?php
	return (string) ob_get_clean();
}
