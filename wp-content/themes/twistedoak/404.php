<?php
/**
 * Not found.
 *
 * @package TwistedOak
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<section class="band band--hero band--fill" data-theme="dark" aria-labelledby="nf-title">
	<div class="wrap hero">
		<div class="hero__copy hero__copy--narrow">
			<p class="eyebrow"><?php esc_html_e( 'Page not found', 'twistedoak' ); ?></p>
			<h1 class="display" id="nf-title"><?php esc_html_e( 'That one’s already sold out.', 'twistedoak' ); ?></h1>
			<p class="lede"><?php esc_html_e( 'The page you were after isn’t here. It may have moved, or the link may be wrong.', 'twistedoak' ); ?></p>
			<div class="button-row">
				<?php
				twistedoak_e(
					twistedoak_button(
						__( 'See the menu', 'twistedoak' ),
						array(
							'href'     => twistedoak_menu_url(),
							'size'     => 'lg',
							'icon_end' => 'arrowRight',
						)
					)
				);
				twistedoak_e(
					twistedoak_button(
						__( 'Go home', 'twistedoak' ),
						array(
							'href'    => home_url( '/' ),
							'size'    => 'lg',
							'variant' => 'secondary',
						)
					)
				);
				?>
			</div>
		</div>
	</div>
</section>
<?php
get_footer();
