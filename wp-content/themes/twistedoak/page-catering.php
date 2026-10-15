<?php
/**
 * Catering (the page with slug "catering"). The page body is not shown: the design's sections
 * and quote form replace the plugin shortcode the page holds.
 *
 * @package TwistedOak
 */

defined( 'ABSPATH' ) || exit;

get_header();

$twistedoak_info = twistedoak_info();
?>

<section class="band band--hero" data-theme="dark" aria-labelledby="hero-title">
	<div class="wrap hero hero--crumbs">
		<?php
		twistedoak_e(
			twistedoak_breadcrumbs(
				array(
					array(
						'label' => __( 'Home', 'twistedoak' ),
						'href'  => home_url( '/' ),
					),
					array( 'label' => get_the_title() ),
				)
			)
		);
		?>
		<div class="hero__grid">
			<div class="hero__copy">
				<p class="eyebrow"><?php esc_html_e( 'Catering', 'twistedoak' ); ?></p>
				<h1 class="display" id="hero-title"><?php esc_html_e( 'Barbecue for the whole crowd.', 'twistedoak' ); ?></h1>
				<p class="lede"><?php esc_html_e( 'From a 10-person office lunch to a 200-guest wedding, we smoke everything over real hardwood and bring it to you — picked up, dropped off or fully served.', 'twistedoak' ); ?></p>
				<div class="button-row">
					<?php
					twistedoak_e(
						twistedoak_button(
							__( 'Get a quote', 'twistedoak' ),
							array(
								'href'     => '#catering-form',
								'size'     => 'lg',
								'icon_end' => 'arrowRight',
							)
						)
					);
					twistedoak_e(
						twistedoak_button(
							__( 'Compare packages', 'twistedoak' ),
							array(
								'href'    => '#packages',
								'size'    => 'lg',
								'variant' => 'secondary',
							)
						)
					);
					?>
				</div>
				<dl class="stats">
					<div><dt><?php esc_html_e( 'Packages from', 'twistedoak' ); ?></dt><dd><?php echo esc_html( twistedoak_packages()[0]['price'] ?? '$20' ); ?> / <?php esc_html_e( 'guest', 'twistedoak' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Minimum', 'twistedoak' ); ?></dt><dd>
					<?php
					/* translators: %d: minimum guests */
					echo esc_html( sprintf( __( '%d guests', 'twistedoak' ), twistedoak_catering_min_guests() ) );
					?>
					</dd></div>
					<div><dt><?php esc_html_e( 'Serving', 'twistedoak' ); ?></dt><dd><?php esc_html_e( 'Central Virginia', 'twistedoak' ); ?></dd></div>
				</dl>
			</div>
			<?php
			twistedoak_e(
				twistedoak_photo(
					'catering_hero',
					array(
						'class'   => 'hero__photo photo--portrait',
						'size'    => 'twistedoak-tall',
						'loading' => 'eager',
						'sizes'   => '(min-width: 1200px) 568px, (min-width: 960px) 45vw, 100vw',
					)
				)
			);
			?>
		</div>
	</div>
</section>

<section class="band band--torn" id="events" aria-labelledby="events-title">
	<?php twistedoak_e( twistedoak_torn_edge( 'a' ) ); ?>
	<div class="wrap band__inner stack-48">
		<?php
		twistedoak_e(
			twistedoak_section_head(
				array(
					'eyebrow' => __( 'What we cater', 'twistedoak' ),
					'title'   => __( 'Every kind of gathering', 'twistedoak' ),
					'id'      => 'events-title',
				)
			)
		);
		$twistedoak_kinds = array(
			array( 'catering_weddings', __( 'Weddings & rehearsal dinners', 'twistedoak' ), __( 'Full-service buffets with staff, setup and breakdown, so your day runs on time and your guests go home happy.', 'twistedoak' ) ),
			array( 'catering_corporate', __( 'Corporate & team events', 'twistedoak' ), __( 'Drop-off lunches and company cookouts, delivered hot and on schedule with everything labeled.', 'twistedoak' ) ),
			array( 'catering_private', __( 'Private parties', 'twistedoak' ), __( 'Birthdays, graduations, game days and reunions. Pick up a spread or let us set it up for you.', 'twistedoak' ) ),
		);
		?>
		<div class="card-grid">
			<?php foreach ( $twistedoak_kinds as [ $twistedoak_slot, $twistedoak_title, $twistedoak_text ] ) : ?>
				<article class="tile">
					<?php
					twistedoak_e(
						twistedoak_photo(
							$twistedoak_slot,
							array(
								'class' => 'tile__photo photo--3x2',
								'size'  => 'twistedoak-card',
								'sizes' => '(min-width: 1200px) 384px, (min-width: 700px) 45vw, 100vw',
							)
						)
					);
					?>
					<div class="tile__body">
						<h3 class="h3"><?php echo esc_html( $twistedoak_title ); ?></h3>
						<p class="muted-2"><?php echo esc_html( $twistedoak_text ); ?></p>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="band band--alt" id="packages" aria-labelledby="pkg-title">
	<div class="wrap band__inner stack-48">
		<?php
		twistedoak_e(
			twistedoak_section_head(
				array(
					'eyebrow' => __( 'Catering packages', 'twistedoak' ),
					'title'   => __( 'Pick a package, make it yours', 'twistedoak' ),
					'id'      => 'pkg-title',
					'lede'    => __( 'Every package comes with Martin’s potato buns, our Twisted Red BBQ sauce, pickles and pickled onions. Brisket, pork belly and salmon add a little per guest.', 'twistedoak' ),
				)
			)
		);
		get_template_part( 'template-parts/packages' );
		?>
		<div class="menu-summary">
			<?php foreach ( twistedoak_catering_menu_summary() as $twistedoak_col ) : ?>
				<div class="menu-summary__col">
					<h3 class="eyebrow eyebrow--plain"><?php echo esc_html( $twistedoak_col['title'] ); ?></h3>
					<p><?php echo esc_html( $twistedoak_col['items'] ); ?></p>
					<?php if ( ! empty( $twistedoak_col['note'] ) ) : ?>
						<p class="small muted"><?php echo esc_html( $twistedoak_col['note'] ); ?></p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<div class="stack-16">
			<h3 class="h3"><?php esc_html_e( 'Service styles', 'twistedoak' ); ?></h3>
			<div class="to-table-wrap" role="region" aria-label="<?php esc_attr_e( 'How your food gets to the event', 'twistedoak' ); ?>" tabindex="0">
				<table class="to-table to-table--zebra">
					<caption><?php esc_html_e( 'How your food gets to the event', 'twistedoak' ); ?></caption>
					<thead><tr><th scope="col"><?php esc_html_e( 'Service', 'twistedoak' ); ?></th><th scope="col"><?php esc_html_e( 'What you get', 'twistedoak' ); ?></th><th scope="col"><?php esc_html_e( 'Available with', 'twistedoak' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( twistedoak_service_styles() as $twistedoak_style ) : ?>
							<tr><td><?php echo esc_html( $twistedoak_style['label'] ); ?></td><td><?php echo esc_html( $twistedoak_style['what'] ); ?></td><td><?php echo esc_html( $twistedoak_style['packages'] ); ?></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</section>

<section class="band band--surface" aria-labelledby="steps-title">
	<div class="wrap band__inner stack-48">
		<?php
		twistedoak_e(
			twistedoak_section_head(
				array(
					'eyebrow' => __( 'How it works', 'twistedoak' ),
					'title'   => __( 'From first email to last plate', 'twistedoak' ),
					'id'      => 'steps-title',
				)
			)
		);
		$twistedoak_steps = array(
			array( __( 'Tell us about it', 'twistedoak' ), __( 'Send your date, headcount, location and the kind of event with the form below.', 'twistedoak' ) ),
			/* translators: %s: response time */
			array( __( 'Get a menu & quote', 'twistedoak' ), sprintf( __( 'We’ll suggest a package and portions and send an itemized quote within %s.', 'twistedoak' ), twistedoak_copy( 'response_time' ) ) ),
			/* translators: %s: deposit terms */
			array( __( 'Lock your date', 'twistedoak' ), sprintf( __( 'Confirm the menu and %s to hold the smoker for your event.', 'twistedoak' ), twistedoak_copy( 'deposit_terms' ) ) ),
			array( __( 'We smoke & serve', 'twistedoak' ), __( 'Meats go on the night before. On the day, we deliver, set up or serve — your call.', 'twistedoak' ) ),
		);
		?>
		<ol class="steps">
			<?php foreach ( $twistedoak_steps as $twistedoak_i => [ $twistedoak_title, $twistedoak_text ] ) : ?>
				<li class="step">
					<span class="step__num" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $twistedoak_i + 1 ) ); ?></span>
					<h3 class="h3"><?php echo esc_html( $twistedoak_title ); ?></h3>
					<p class="muted-2"><?php echo esc_html( $twistedoak_text ); ?></p>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</section>

<?php get_template_part( 'template-parts/testimonials', null, array( 'band' => 'alt' ) ); ?>

<section class="band band--bg" id="catering-form" aria-labelledby="form-title">
	<div class="wrap band__inner quote">
		<div class="quote__aside">
			<p class="eyebrow"><?php esc_html_e( 'Request a quote', 'twistedoak' ); ?></p>
			<h2 class="h2" id="form-title"><?php esc_html_e( 'Let’s plan your menu', 'twistedoak' ); ?></h2>
			<p class="lede"><?php esc_html_e( 'The more you tell us, the closer the first quote. No commitment until you confirm.', 'twistedoak' ); ?></p>
			<?php
			twistedoak_e(
				twistedoak_alert(
					__( 'Spring and fall weekends fill first', 'twistedoak' ),
					/* translators: %s: lead time */
					sprintf( __( 'Book %s ahead for Saturdays in April–June and September–October.', 'twistedoak' ), twistedoak_copy( 'lead_time' ) )
				)
			);
			?>
			<dl class="contact-list">
				<div><dt><?php esc_html_e( 'Rather talk it through?', 'twistedoak' ); ?></dt><dd><a class="contact-list__phone" href="<?php echo esc_url( $twistedoak_info['phone_href'] ); ?>"><?php echo esc_html( $twistedoak_info['phone'] ); ?></a></dd></div>
				<div><dt><?php esc_html_e( 'Email', 'twistedoak' ); ?></dt><dd><a class="strong-link" href="mailto:<?php echo esc_attr( $twistedoak_info['email'] ); ?>"><?php echo esc_html( $twistedoak_info['email'] ); ?></a></dd></div>
				<div><dt><?php esc_html_e( 'Service area', 'twistedoak' ); ?></dt><dd><?php echo esc_html( $twistedoak_info['area'] ); ?></dd></div>
			</dl>
		</div>
		<?php twistedoak_e( twistedoak_catering_form() ); ?>
	</div>
</section>

<?php
get_template_part(
	'template-parts/faq',
	null,
	array(
		'set'   => 'catering',
		'id'    => 'faq-title',
		'title' => __( 'Catering questions', 'twistedoak' ),
	)
);

get_template_part(
	'template-parts/cta-band',
	null,
	array(
		'id'      => 'book-title',
		'title'   => __( 'Just need lunch for the office?', 'twistedoak' ),
		'text'    => esc_html__( 'For smaller pickup orders, order online and skip the quote.', 'twistedoak' ),
		'from'    => 'var(--stone-200)',
		'stack'   => true,
		'actions' => twistedoak_button(
			__( 'Order pickup', 'twistedoak' ),
			array(
				'href'     => $twistedoak_info['order_url'],
				'size'     => 'lg',
				'icon_end' => 'arrowRight',
			)
		) . twistedoak_or_call(),
	)
);

get_footer();
