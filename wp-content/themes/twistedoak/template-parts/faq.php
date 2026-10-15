<?php
/**
 * FAQ accordion (native <details>).
 *
 * @package TwistedOak
 *
 * @var array{set: string, id: string, title: string, eyebrow?: string} $args
 */

defined( 'ABSPATH' ) || exit;

$twistedoak_faqs = twistedoak_faqs( $args['set'] );
if ( ! $twistedoak_faqs ) {
	return;
}
?>
<section class="band band--alt" id="faq" aria-labelledby="<?php echo esc_attr( $args['id'] ); ?>">
	<div class="wrap wrap--narrow band__inner stack-32">
		<?php
		twistedoak_e(
			twistedoak_section_head(
				array(
					'eyebrow' => $args['eyebrow'] ?? __( 'Good to know', 'twistedoak' ),
					'title'   => $args['title'],
					'id'      => $args['id'],
				)
			)
		);
		?>
		<div class="faq">
			<?php foreach ( $twistedoak_faqs as $twistedoak_i => $twistedoak_faq ) : ?>
				<details<?php echo 0 === $twistedoak_i ? ' open' : ''; ?>>
					<summary><?php echo esc_html( $twistedoak_faq['q'] ); ?><?php twistedoak_e( twistedoak_icon( 'chevronDown' ) ); ?></summary>
					<p><?php echo wp_kses( $twistedoak_faq['a'], twistedoak_inline_html() ); ?></p>
				</details>
			<?php endforeach; ?>
		</div>
	</div>
</section>
