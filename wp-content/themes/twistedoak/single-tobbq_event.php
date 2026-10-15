<?php
/**
 * Single event.
 *
 * @package TwistedOak
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$twistedoak_ev   = twistedoak_event_data( get_post() );
	$twistedoak_past = $twistedoak_ev['start'] && ( $twistedoak_ev['end'] ?? $twistedoak_ev['start'] ) < new DateTimeImmutable( 'today', wp_timezone() );
	?>
	<section class="band band--hero" data-theme="dark" aria-labelledby="event-title">
		<div class="wrap hero hero--crumbs">
			<?php
			twistedoak_e(
				twistedoak_breadcrumbs(
					array(
						array(
							'label' => __( 'Home', 'twistedoak' ),
							'href'  => home_url( '/' ),
						),
						array(
							'label' => __( 'Events', 'twistedoak' ),
							'href'  => twistedoak_events_url(),
						),
						array( 'label' => get_the_title() ),
					)
				)
			);
			?>
			<div class="hero__grid">
				<div class="hero__copy">
					<div class="event-head">
						<?php twistedoak_e( twistedoak_event_chip( $twistedoak_ev, 'hot' ) ); ?>
						<div class="stack-12">
							<?php if ( '' !== $twistedoak_ev['type'] ) : ?>
								<p class="eyebrow"><?php echo esc_html( $twistedoak_ev['type'] ); ?></p>
							<?php endif; ?>
							<?php
							if ( $twistedoak_past ) {
								twistedoak_e( twistedoak_badge( __( 'This event has passed', 'twistedoak' ) ) );
							}
							?>
						</div>
					</div>
					<h1 class="h1" id="event-title"><?php the_title(); ?></h1>
					<ul class="meta-list meta-list--lg">
						<?php if ( '' !== $twistedoak_ev['date_long'] ) : ?>
							<li><?php twistedoak_e( twistedoak_icon( 'calendar' ) ); ?><span><?php echo esc_html( $twistedoak_ev['date_long'] ); ?></span></li>
						<?php endif; ?>
						<?php if ( '' !== $twistedoak_ev['time'] ) : ?>
							<li><?php twistedoak_e( twistedoak_icon( 'clock' ) ); ?><span><?php echo esc_html( $twistedoak_ev['time'] ); ?></span></li>
						<?php endif; ?>
						<?php if ( '' !== $twistedoak_ev['location'] ) : ?>
							<li><?php twistedoak_e( twistedoak_icon( 'mapPin' ) ); ?><span><?php echo esc_html( $twistedoak_ev['location'] ); ?></span></li>
						<?php endif; ?>
					</ul>
					<?php if ( ! $twistedoak_past ) : ?>
						<div class="button-row">
							<?php
							if ( '' !== $twistedoak_ev['link'] ) {
								twistedoak_e(
									twistedoak_button(
										__( 'Tickets & info', 'twistedoak' ),
										array(
											'href'     => $twistedoak_ev['link'],
											'size'     => 'lg',
											'icon_end' => 'arrowRight',
										)
									)
								);
							}
							twistedoak_e(
								twistedoak_button(
									__( 'Add to calendar', 'twistedoak' ),
									array(
										'href'    => twistedoak_event_calendar_url( $twistedoak_ev ),
										'size'    => 'lg',
										'variant' => '' !== $twistedoak_ev['link'] ? 'secondary' : 'primary',
										'attrs'   => array( 'target' => '_blank' ),
									)
								)
							);
							if ( '' !== $twistedoak_ev['location'] ) {
								twistedoak_e(
									twistedoak_button(
										__( 'Directions', 'twistedoak' ),
										array(
											'href'     => twistedoak_directions_url( $twistedoak_ev['location'] ),
											'size'     => 'lg',
											'variant'  => 'ghost',
											'icon_end' => 'arrowRight',
											'attrs'    => array( 'target' => '_blank' ),
										)
									)
								);
							}
							?>
						</div>
					<?php endif; ?>
				</div>
				<?php
				twistedoak_e(
					twistedoak_photo(
						'events_next',
						array(
							'class'      => 'hero__photo photo--4x3',
							'attachment' => (int) get_post_thumbnail_id(),
							'size'       => 'twistedoak-card',
							'loading'    => 'eager',
						)
					)
				);
				?>
			</div>
		</div>
	</section>

	<section class="band band--torn" aria-label="<?php esc_attr_e( 'About this event', 'twistedoak' ); ?>">
		<?php twistedoak_e( twistedoak_torn_edge( 'a' ) ); ?>
		<div class="wrap band__inner">
			<div class="prose">
				<?php
				if ( '' !== trim( get_the_content() ) ) {
					the_content();
				} else {
					echo '<p>' . esc_html__( 'Brisket, ribs and sides until we sell out. Come early.', 'twistedoak' ) . '</p>';
				}
				?>
				<p><a href="<?php echo esc_url( twistedoak_events_url() ); ?>"><?php esc_html_e( '← All events', 'twistedoak' ); ?></a></p>
			</div>
		</div>
	</section>
	<?php
endwhile;

get_template_part(
	'template-parts/cta-band',
	null,
	array(
		'id'      => 'ev-cta-title',
		'title'   => __( 'Can’t make it out?', 'twistedoak' ),
		'text'    => esc_html__( 'Order pickup from Beacon Kitchen, or bring the smoker to your wedding, office or backyard.', 'twistedoak' ),
		'from'    => 'var(--stone-100)',
		'actions' => twistedoak_button(
			__( 'Order pickup', 'twistedoak' ),
			array(
				'href'     => twistedoak_info()['order_url'],
				'size'     => 'lg',
				'icon_end' => 'arrowRight',
			)
		) . twistedoak_button(
			__( 'Get a quote', 'twistedoak' ),
			array(
				'href'    => twistedoak_catering_url( 'catering-form' ),
				'size'    => 'lg',
				'variant' => 'secondary',
			)
		),
	)
);

get_footer();
