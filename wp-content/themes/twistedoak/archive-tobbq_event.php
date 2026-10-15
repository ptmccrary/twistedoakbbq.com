<?php
/**
 * Events (/events/ and /events/type/{slug}/): next-up card, upcoming list with type filter,
 * recent events, the "host us" pitch and FAQ. The main query holds upcoming events (inc/events.php).
 *
 * @package TwistedOak
 */

defined( 'ABSPATH' ) || exit;

get_header();

$twistedoak_info    = twistedoak_info();
$twistedoak_term    = is_tax( 'tobbq_event_type' ) ? get_queried_object() : null;
$twistedoak_next    = twistedoak_upcoming_events( 1 );
$twistedoak_next    = $twistedoak_next ? twistedoak_event_data( $twistedoak_next[0] ) : null;
$twistedoak_filters = twistedoak_event_filter_terms();
$twistedoak_count   = (int) $GLOBALS['wp_query']->post_count;
?>

<section class="band band--hero" data-theme="dark" aria-labelledby="ev-hero-title">
	<div class="wrap hero hero--crumbs">
		<?php
		$twistedoak_crumbs = array(
			array(
				'label' => __( 'Home', 'twistedoak' ),
				'href'  => home_url( '/' ),
			),
			array(
				'label' => __( 'Events', 'twistedoak' ),
				'href'  => twistedoak_events_url(),
			),
		);
		if ( $twistedoak_term instanceof WP_Term ) {
			$twistedoak_crumbs[] = array( 'label' => $twistedoak_term->name );
		}
		twistedoak_e( twistedoak_breadcrumbs( $twistedoak_crumbs ) );
		?>
		<div class="hero__grid">
			<div class="hero__copy">
				<p class="eyebrow"><?php esc_html_e( 'Events', 'twistedoak' ); ?></p>
				<h1 class="display" id="ev-hero-title"><?php esc_html_e( 'Come find us at the smoker.', 'twistedoak' ); ?></h1>
				<p class="lede"><?php esc_html_e( 'Brewery pop-ups, Saturday markets and the occasional brisket night around Charlottesville. We smoke a set amount each day — when it’s gone, it’s gone, so come early.', 'twistedoak' ); ?></p>
				<div class="button-row">
					<?php
					twistedoak_e(
						twistedoak_button(
							__( 'See what’s coming up', 'twistedoak' ),
							array(
								'href'     => '#upcoming',
								'size'     => 'lg',
								'icon_end' => 'arrowRight',
							)
						)
					);
					twistedoak_e(
						twistedoak_button(
							__( 'Book us for your venue', 'twistedoak' ),
							array(
								'href'    => '#host',
								'size'    => 'lg',
								'variant' => 'secondary',
							)
						)
					);
					?>
				</div>
			</div>

			<article class="next-up" aria-labelledby="next-title">
				<?php
				twistedoak_e(
					twistedoak_photo(
						'events_next',
						array(
							'class'      => 'next-up__photo photo--16x9',
							'attachment' => $twistedoak_next ? (int) get_post_thumbnail_id( twistedoak_upcoming_events( 1 )[0] ) : 0,
							'size'       => 'twistedoak-card',
							'sizes'      => '(min-width: 1200px) 568px, (min-width: 960px) 45vw, 100vw',
						)
					)
				);
				?>
				<div class="next-up__body">
					<?php if ( $twistedoak_next ) : ?>
						<?php twistedoak_e( twistedoak_event_chip( $twistedoak_next, 'hot' ) ); ?>
						<div class="next-up__info">
							<?php twistedoak_e( twistedoak_badge( __( 'Next up', 'twistedoak' ), 'brand', 'flame' ) ); ?>
							<h2 class="next-up__title" id="next-title"><a href="<?php echo esc_url( $twistedoak_next['url'] ); ?>"><?php echo esc_html( $twistedoak_next['title'] ); ?></a></h2>
							<ul class="meta-list">
								<li><?php twistedoak_e( twistedoak_icon( 'clock', array( 'size' => 18 ) ) ); ?><span><span class="to-sr"><?php echo esc_html( $twistedoak_next['date_long'] ); ?>, </span><?php echo esc_html( $twistedoak_next['time'] ?: $twistedoak_next['date_long'] ); ?></span></li>
								<?php if ( '' !== $twistedoak_next['location'] ) : ?>
									<li><?php twistedoak_e( twistedoak_icon( 'mapPin', array( 'size' => 18 ) ) ); ?><span><?php echo esc_html( $twistedoak_next['location'] ); ?></span></li>
								<?php endif; ?>
							</ul>
							<div class="button-row button-row--tight">
								<?php
								twistedoak_e(
									twistedoak_button(
										__( 'Add to calendar', 'twistedoak' ),
										array(
											'href'  => twistedoak_event_calendar_url( $twistedoak_next ),
											'attrs' => array( 'target' => '_blank' ),
										)
									)
								);
								if ( '' !== $twistedoak_next['location'] ) {
									twistedoak_e(
										twistedoak_button(
											__( 'Directions', 'twistedoak' ),
											array(
												'href'     => twistedoak_directions_url( $twistedoak_next['location'] ),
												'variant'  => 'ghost',
												'icon_end' => 'arrowRight',
												'attrs'    => array( 'target' => '_blank' ),
											)
										)
									);
								}
								?>
							</div>
						</div>
					<?php else : ?>
						<div class="next-up__info">
							<?php twistedoak_e( twistedoak_badge( __( 'Next up', 'twistedoak' ), 'brand', 'flame' ) ); ?>
							<h2 class="next-up__title" id="next-title"><?php esc_html_e( 'New dates are on the way', 'twistedoak' ); ?></h2>
							<p class="muted-2"><?php esc_html_e( 'Pop-ups usually land on Instagram first. Follow along so you don’t miss the next brisket day.', 'twistedoak' ); ?></p>
							<div class="button-row button-row--tight">
								<?php
								if ( $twistedoak_info['instagram'] ) {
									twistedoak_e(
										twistedoak_button(
											__( 'Follow on Instagram', 'twistedoak' ),
											array(
												'href'     => $twistedoak_info['instagram'],
												'icon_end' => 'arrowRight',
											)
										)
									);
								}
								twistedoak_e(
									twistedoak_button(
										__( 'Order pickup', 'twistedoak' ),
										array(
											'href'    => $twistedoak_info['order_url'],
											'variant' => 'ghost',
										)
									)
								);
								?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</article>
		</div>
	</div>
</section>

<section class="band band--torn" id="upcoming" aria-labelledby="up-title">
	<?php twistedoak_e( twistedoak_torn_edge( 'a' ) ); ?>
	<div class="wrap band__inner stack-40">
		<div class="section-head-row">
			<?php
			twistedoak_e(
				twistedoak_section_head(
					array(
						'eyebrow' => __( 'On the calendar', 'twistedoak' ),
						'title'   => $twistedoak_term instanceof WP_Term ? $twistedoak_term->name : __( 'Upcoming events', 'twistedoak' ),
						'id'      => 'up-title',
					)
				)
			);
			?>
			<?php if ( count( $twistedoak_filters ) > 1 || $twistedoak_term ) : ?>
				<nav class="filter-row" aria-label="<?php esc_attr_e( 'Filter events by type', 'twistedoak' ); ?>">
					<a class="to-tag" href="<?php echo esc_url( twistedoak_events_url() ); ?>#upcoming"<?php echo $twistedoak_term ? '' : ' aria-current="page"'; ?>><?php echo $twistedoak_term ? '' : twistedoak_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'All', 'twistedoak' ); ?></a>
					<?php foreach ( $twistedoak_filters as $twistedoak_filter ) : ?>
						<?php $twistedoak_on = $twistedoak_term instanceof WP_Term && $twistedoak_term->term_id === $twistedoak_filter->term_id; ?>
						<a class="to-tag" href="<?php echo esc_url( get_term_link( $twistedoak_filter ) ); ?>#upcoming"<?php echo $twistedoak_on ? ' aria-current="page"' : ''; ?>><?php echo $twistedoak_on ? twistedoak_icon( 'check' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $twistedoak_filter->name ); ?></a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
		</div>

		<div class="stack-12">
			<?php if ( have_posts() ) : ?>
				<p class="small muted">
					<?php
					/* translators: %d: number of events */
					echo esc_html( sprintf( _n( '%d event', '%d events', $twistedoak_count, 'twistedoak' ), $twistedoak_count ) );
					?>
				</p>
				<ol class="event-list">
					<?php
					while ( have_posts() ) :
						the_post();
						get_template_part( 'template-parts/event-row', null, array( 'event' => twistedoak_event_data( get_post() ) ) );
					endwhile;
					?>
				</ol>
			<?php else : ?>
				<div class="empty">
					<p class="empty__title"><?php esc_html_e( 'Nothing on the books for this one yet', 'twistedoak' ); ?></p>
					<p>
						<?php
						printf(
							/* translators: %s: Instagram link */
							esc_html__( 'New dates usually land on %s first.', 'twistedoak' ),
							'<a href="' . esc_url( $twistedoak_info['instagram'] ) . '">Instagram</a>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>
		</div>

		<?php twistedoak_e( twistedoak_alert( __( 'Weather or sell-out changes', 'twistedoak' ), __( 'If it rains hard or we sell out early, we’ll post it on Instagram before we pack up.', 'twistedoak' ) ) ); ?>
	</div>
</section>

<?php
$twistedoak_past = twistedoak_past_events( 4 );
if ( $twistedoak_past ) :
	?>
	<section class="band band--bg" aria-labelledby="past-title">
		<div class="wrap band__inner stack-40">
			<?php
			twistedoak_e(
				twistedoak_section_head(
					array(
						'eyebrow' => __( 'Recently', 'twistedoak' ),
						'title'   => __( 'Where we’ve been', 'twistedoak' ),
						'id'      => 'past-title',
					)
				)
			);
			?>
			<div class="past-grid">
				<?php
				foreach ( $twistedoak_past as $twistedoak_post ) :
					$twistedoak_ev = twistedoak_event_data( $twistedoak_post );
					?>
					<figure class="past">
						<?php
						twistedoak_e(
							twistedoak_photo(
								'event_' . $twistedoak_post->ID,
								array(
									'class'      => 'photo--4x5',
									'attachment' => (int) get_post_thumbnail_id( $twistedoak_post ),
									'size'       => 'twistedoak-tall',
									'alt'        => $twistedoak_ev['title'],
									'caption'    => strtolower( $twistedoak_ev['title'] ),
									'sizes'      => '(min-width: 1200px) 282px, (min-width: 640px) 45vw, 100vw',
								)
							)
						);
						?>
						<figcaption><a class="past__title" href="<?php echo esc_url( $twistedoak_ev['url'] ); ?>"><?php echo esc_html( '' !== $twistedoak_ev['location'] ? $twistedoak_ev['location'] : $twistedoak_ev['title'] ); ?></a><span class="small muted"><?php echo esc_html( $twistedoak_ev['start'] ? wp_date( 'F j', $twistedoak_ev['start']->getTimestamp() ) : '' ); ?></span></figcaption>
					</figure>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<section class="band band--surface" id="host" aria-labelledby="host-title">
	<div class="wrap band__inner split split--top">
		<div class="stack-20">
			<p class="eyebrow"><?php esc_html_e( 'For breweries, wineries & markets', 'twistedoak' ); ?></p>
			<h2 class="h2" id="host-title"><?php esc_html_e( 'Want the smoker at your place?', 'twistedoak' ); ?></h2>
			<p class="lede"><?php esc_html_e( 'We bring the trailer, the food and the line. You bring the taps. Tell us about your venue and the dates you have open.', 'twistedoak' ); ?></p>
			<div class="button-row">
				<?php
				twistedoak_e(
					twistedoak_button(
						__( 'Pitch a pop-up', 'twistedoak' ),
						array(
							'href'     => 'mailto:' . $twistedoak_info['email'] . '?subject=' . rawurlencode( 'Pop-up inquiry' ),
							'size'     => 'lg',
							'icon_end' => 'arrowRight',
						)
					)
				);
				twistedoak_e(
					twistedoak_button(
						__( 'Private event? See catering', 'twistedoak' ),
						array(
							'href'    => twistedoak_catering_url(),
							'size'    => 'lg',
							'variant' => 'secondary',
						)
					)
				);
				?>
			</div>
		</div>
		<ul class="perk-list">
			<?php
			$twistedoak_perks = array(
				array( __( 'Self-contained setup', 'twistedoak' ), __( 'Smoker trailer, service tent and [power needs]. We just need a spot to park.', 'twistedoak' ) ),
				array( __( 'We promote it too', 'twistedoak' ), __( 'Every date goes on this page and our socials.', 'twistedoak' ) ),
				array( __( '[Booking terms]', 'twistedoak' ), __( '[Lead time, minimums or revenue-share details for venues]', 'twistedoak' ) ),
			);
			foreach ( $twistedoak_perks as [ $twistedoak_title, $twistedoak_text ] ) :
				?>
				<li class="perk">
					<?php twistedoak_e( twistedoak_icon( 'check' ) ); ?>
					<div><h3 class="perk__title"><?php echo esc_html( $twistedoak_title ); ?></h3><p class="muted-2"><?php echo esc_html( $twistedoak_text ); ?></p></div>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>

<?php
get_template_part(
	'template-parts/faq',
	null,
	array(
		'set'   => 'events',
		'id'    => 'ev-faq-title',
		'title' => __( 'Pop-up questions', 'twistedoak' ),
	)
);

get_template_part(
	'template-parts/cta-band',
	null,
	array(
		'id'      => 'ev-cta-title',
		'title'   => __( 'Can’t make it out?', 'twistedoak' ),
		'text'    => esc_html__( 'Order pickup from Beacon Kitchen, or bring the smoker to your wedding, office or backyard.', 'twistedoak' ),
		'from'    => 'var(--stone-200)',
		'actions' => twistedoak_button(
			__( 'Order pickup', 'twistedoak' ),
			array(
				'href'     => $twistedoak_info['order_url'],
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
