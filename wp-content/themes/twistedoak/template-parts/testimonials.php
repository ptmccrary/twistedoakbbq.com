<?php
/**
 * "What our guests say" section.
 *
 * @package TwistedOak
 *
 * @var array{band?: string} $args band: background modifier (bg | alt | surface).
 */

defined( 'ABSPATH' ) || exit;

$twistedoak_items = twistedoak_testimonials();
if ( ! $twistedoak_items ) {
	return;
}
?>
<section class="band band--<?php echo esc_attr( $args['band'] ?? 'bg' ); ?>" aria-labelledby="reviews-title">
	<div class="wrap band__inner stack-48">
		<?php
		twistedoak_e(
			twistedoak_section_head(
				array(
					'eyebrow' => __( 'Kind words', 'twistedoak' ),
					'title'   => __( 'What our guests say', 'twistedoak' ),
					'id'      => 'reviews-title',
				)
			)
		);
		?>
		<div class="review-grid scroller-sm" role="region" aria-label="<?php esc_attr_e( 'Customer reviews', 'twistedoak' ); ?>" tabindex="0">
			<?php foreach ( $twistedoak_items as $twistedoak_item ) : ?>
				<figure class="review">
					<span class="review__mark" aria-hidden="true">&ldquo;</span>
					<blockquote><?php echo esc_html( $twistedoak_item['quote'] ); ?></blockquote>
					<figcaption><span class="review__name"><?php echo esc_html( $twistedoak_item['name'] ); ?></span><span class="review__context"><?php echo esc_html( $twistedoak_item['context'] ); ?></span></figcaption>
				</figure>
			<?php endforeach; ?>
		</div>
	</div>
</section>
