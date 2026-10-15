<?php
/**
 * Site footer (dark band).
 *
 * @package TwistedOak
 */

defined( 'ABSPATH' ) || exit;

$twistedoak_info = twistedoak_info();
?>
</main>

<footer class="site-footer" data-theme="dark">
	<div class="wrap site-footer__inner">
		<div class="site-footer__cols">
			<div class="site-footer__brand">
				<img src="<?php echo esc_url( twistedoak_logo_url() ); ?>" width="96" height="96" alt="<?php esc_attr_e( 'Twisted Oak Barbecue', 'twistedoak' ); ?>" loading="lazy">
				<p class="tagline"><?php echo esc_html( $twistedoak_info['tagline'] ); ?></p>
			</div>

			<div class="site-footer__col">
				<h2 class="eyebrow eyebrow--muted"><?php esc_html_e( 'Find us', 'twistedoak' ); ?></h2>
				<address><?php echo wp_kses( implode( '<br>', array_map( 'esc_html', $twistedoak_info['address'] ) ), array( 'br' => array() ) ); ?></address>
			</div>

			<div class="site-footer__col">
				<h2 class="eyebrow eyebrow--muted"><?php esc_html_e( 'Get in touch', 'twistedoak' ); ?></h2>
				<a class="site-footer__link" href="<?php echo esc_url( $twistedoak_info['phone_href'] ); ?>"><?php echo esc_html( $twistedoak_info['phone'] ); ?></a>
				<a class="site-footer__link" href="mailto:<?php echo esc_attr( $twistedoak_info['email'] ); ?>"><?php echo esc_html( $twistedoak_info['email'] ); ?></a>
				<div class="site-footer__social">
					<?php if ( $twistedoak_info['instagram'] ) : ?>
						<a href="<?php echo esc_url( $twistedoak_info['instagram'] ); ?>" rel="noopener">Instagram</a>
					<?php endif; ?>
					<?php if ( $twistedoak_info['facebook'] ) : ?>
						<a href="<?php echo esc_url( $twistedoak_info['facebook'] ); ?>" rel="noopener">Facebook</a>
					<?php endif; ?>
				</div>
			</div>

			<nav class="site-footer__col" aria-labelledby="footer-explore">
				<h2 class="eyebrow eyebrow--muted" id="footer-explore"><?php esc_html_e( 'Explore', 'twistedoak' ); ?></h2>
				<?php foreach ( twistedoak_nav_links( 'footer' ) as $twistedoak_link ) : ?>
					<a class="site-footer__link" href="<?php echo esc_url( $twistedoak_link['href'] ); ?>"<?php echo $twistedoak_link['current'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $twistedoak_link['label'] ); ?></a>
				<?php endforeach; ?>
			</nav>
		</div>

		<div class="site-footer__legal">
			<p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> Twisted Oak Barbecue. <?php esc_html_e( 'All rights reserved.', 'twistedoak' ); ?></p>
			<?php
			$twistedoak_privacy = get_privacy_policy_url();
			if ( $twistedoak_privacy ) :
				?>
				<a href="<?php echo esc_url( $twistedoak_privacy ); ?>"><?php esc_html_e( 'Privacy policy', 'twistedoak' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
