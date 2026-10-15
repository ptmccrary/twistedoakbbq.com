<?php
/**
 * One row in the upcoming events list.
 *
 * @package TwistedOak
 *
 * @var array{event: array<string, mixed>} $args
 */

defined( 'ABSPATH' ) || exit;

$twistedoak_ev = $args['event'];
?>
<li class="event-row">
	<?php twistedoak_e( twistedoak_event_chip( $twistedoak_ev ) ); ?>
	<div class="event-row__main">
		<?php if ( '' !== $twistedoak_ev['type'] ) : ?>
			<p class="eyebrow eyebrow--muted eyebrow--sm"><?php echo esc_html( $twistedoak_ev['type'] ); ?></p>
		<?php endif; ?>
		<h3 class="h3"><a href="<?php echo esc_url( $twistedoak_ev['url'] ); ?>"><?php echo esc_html( $twistedoak_ev['title'] ); ?></a></h3>
		<p class="event-row__meta">
			<span><span class="to-sr"><?php echo esc_html( $twistedoak_ev['date_long'] ); ?> · </span><?php echo esc_html( $twistedoak_ev['time'] ); ?></span>
			<?php if ( '' !== $twistedoak_ev['location'] ) : ?>
				<span><?php echo esc_html( $twistedoak_ev['location'] ); ?></span>
			<?php endif; ?>
		</p>
		<?php if ( '' !== $twistedoak_ev['excerpt'] ) : ?>
			<p class="small muted"><?php echo esc_html( $twistedoak_ev['excerpt'] ); ?></p>
		<?php endif; ?>
	</div>
	<div class="event-row__cta">
		<?php
		$twistedoak_external = '' !== $twistedoak_ev['link'];
		twistedoak_e(
			twistedoak_button(
				$twistedoak_external ? __( 'Tickets & info', 'twistedoak' ) : __( 'Details', 'twistedoak' ),
				array(
					'href'    => $twistedoak_external ? $twistedoak_ev['link'] : $twistedoak_ev['url'],
					'variant' => $twistedoak_external ? 'primary' : 'secondary',
					'attrs'   => array(
						/* translators: %s: event title */
						'aria-label' => sprintf( __( 'Details: %s', 'twistedoak' ), $twistedoak_ev['title'] ),
					),
				)
			)
		);
		?>
	</div>
</li>
