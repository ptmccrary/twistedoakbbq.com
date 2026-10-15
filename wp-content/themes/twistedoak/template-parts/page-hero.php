<?php
/**
 * Light page header for generic pages, posts and archives.
 *
 * @package TwistedOak
 *
 * @var array{title: string, eyebrow?: string, lede?: string, crumbs?: list<array{label: string, href?: string}>} $args
 */

defined( 'ABSPATH' ) || exit;

$twistedoak_crumbs = $args['crumbs'] ?? array(
	array(
		'label' => __( 'Home', 'twistedoak' ),
		'href'  => home_url( '/' ),
	),
	array( 'label' => wp_strip_all_tags( $args['title'] ) ),
);
?>
<section class="band band--hero band--hero-short" data-theme="dark" aria-labelledby="page-title">
	<div class="wrap hero hero--crumbs">
		<?php twistedoak_e( twistedoak_breadcrumbs( $twistedoak_crumbs ) ); ?>
		<div class="hero__copy hero__copy--narrow">
			<?php if ( ! empty( $args['eyebrow'] ) ) : ?>
				<p class="eyebrow"><?php echo esc_html( $args['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h1 class="h1" id="page-title"><?php echo esc_html( wp_strip_all_tags( $args['title'] ) ); ?></h1>
			<?php if ( ! empty( $args['lede'] ) ) : ?>
				<p class="lede"><?php echo esc_html( $args['lede'] ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</section>
