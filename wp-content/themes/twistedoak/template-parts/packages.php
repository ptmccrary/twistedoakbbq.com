<?php
/**
 * The three catering package cards.
 *
 * @package TwistedOak
 *
 * @var array{link?: string} $args link: "form" links each card to the quote form with the package preselected.
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="package-grid">
	<?php
	foreach ( twistedoak_packages() as $twistedoak_pkg ) :
		$twistedoak_href = add_query_arg( 'package', $twistedoak_pkg['slug'], twistedoak_catering_url() ) . '#catering-form';
		?>
		<div class="package<?php echo $twistedoak_pkg['popular'] ? ' package--popular' : ''; ?>">
			<div class="package__head">
				<div class="package__eyebrow-row">
					<p class="eyebrow eyebrow--muted"><?php echo esc_html( $twistedoak_pkg['guests'] ); ?></p>
					<?php
					if ( $twistedoak_pkg['popular'] ) {
						twistedoak_e( twistedoak_badge( __( 'Most popular', 'twistedoak' ), 'brand', 'flame' ) );
					}
					?>
				</div>
				<h3 class="h3"><?php echo esc_html( $twistedoak_pkg['name'] ); ?></h3>
				<p class="package__summary"><?php echo esc_html( $twistedoak_pkg['summary'] ); ?></p>
			</div>
			<p class="package__price"><span><?php esc_html_e( 'From', 'twistedoak' ); ?></span><strong><?php echo esc_html( $twistedoak_pkg['price'] ); ?></strong><span><?php esc_html_e( 'per guest', 'twistedoak' ); ?></span></p>
			<?php twistedoak_e( twistedoak_check_list( $twistedoak_pkg['features'], 'check-list package__features' ) ); ?>
			<?php
			twistedoak_e(
				twistedoak_button(
					__( 'Get a quote', 'twistedoak' ),
					array(
						'href'    => $twistedoak_href,
						'variant' => $twistedoak_pkg['popular'] ? 'primary' : 'secondary',
						'block'   => true,
						'attrs'   => array(
							/* translators: %s: package name */
							'aria-label' => sprintf( __( 'Get a quote for %s', 'twistedoak' ), $twistedoak_pkg['name'] ),
						),
					)
				)
			);
			?>
		</div>
	<?php endforeach; ?>
</div>
