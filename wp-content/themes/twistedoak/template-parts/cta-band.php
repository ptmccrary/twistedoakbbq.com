<?php
/**
 * Closing call-to-action band: dark, with a torn top edge.
 *
 * @package TwistedOak
 *
 * @var array{id: string, title: string, text: string, actions: string, eyebrow?: string, from?: string, stack?: bool} $args
 *      from: background of the section above, so the torn edge blends (CSS color value).
 *      stack: actions stacked in a column (button + "or call" line) instead of a row.
 */

defined( 'ABSPATH' ) || exit;

$twistedoak_from = $args['from'] ?? 'var(--stone-100)';
?>
<section class="band band--cta" data-theme="dark" aria-labelledby="<?php echo esc_attr( $args['id'] ); ?>" style="--torn-from: <?php echo esc_attr( $twistedoak_from ); ?>">
	<?php twistedoak_e( twistedoak_torn_edge( 'b' ) ); ?>
	<div class="wrap band__inner cta">
		<div class="cta__copy">
			<?php if ( ! empty( $args['eyebrow'] ) ) : ?>
				<p class="eyebrow eyebrow--icon"><?php twistedoak_e( twistedoak_icon( 'flame' ) ); ?><?php echo esc_html( $args['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h2 class="h1" id="<?php echo esc_attr( $args['id'] ); ?>"><?php echo esc_html( $args['title'] ); ?></h2>
			<p class="lede"><?php echo wp_kses( $args['text'], twistedoak_inline_html() ); ?></p>
		</div>
		<div class="cta__actions<?php echo ! empty( $args['stack'] ) ? ' cta__actions--stack' : ''; ?>">
			<?php twistedoak_e( $args['actions'] ); ?>
		</div>
	</div>
</section>
