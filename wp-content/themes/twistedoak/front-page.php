<?php
/**
 * Home.
 *
 * @package TwistedOak
 */

defined( 'ABSPATH' ) || exit;

get_header();

$twistedoak_info  = twistedoak_info();
$twistedoak_quote = twistedoak_catering_url( 'catering-form' );
?>

<section class="band band--hero" data-theme="dark" aria-labelledby="hero-title">
	<div class="wrap hero">
		<div class="hero__copy">
			<p class="eyebrow"><?php esc_html_e( 'Charlottesville barbecue & catering', 'twistedoak' ); ?></p>
			<h1 class="display" id="hero-title"><?php esc_html_e( 'Real wood, real smoke, no shortcuts.', 'twistedoak' ); ?></h1>
			<p class="lede"><?php esc_html_e( 'Texas-style brisket, ribs and pulled pork smoked low and slow over cherry, hickory and oak — catered for weddings, corporate events and backyard gatherings across Central Virginia.', 'twistedoak' ); ?></p>
			<div class="button-row">
				<?php
				twistedoak_e(
					twistedoak_button(
						__( 'Get a quote', 'twistedoak' ),
						array(
							'href'     => $twistedoak_quote,
							'size'     => 'lg',
							'icon_end' => 'arrowRight',
						)
					)
				);
				twistedoak_e(
					twistedoak_button(
						__( 'See the menu', 'twistedoak' ),
						array(
							'href'    => twistedoak_menu_url(),
							'size'    => 'lg',
							'variant' => 'secondary',
						)
					)
				);
				?>
			</div>
			<p class="tagline tagline--rule"><?php echo esc_html( $twistedoak_info['tagline'] ); ?></p>
		</div>
		<?php
		twistedoak_e(
			twistedoak_photo(
				'home_hero',
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
</section>

<section class="band band--torn" id="catering" aria-labelledby="pkg-title">
	<?php twistedoak_e( twistedoak_torn_edge( 'a' ) ); ?>
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
		<p class="small muted">
			<?php
			printf(
				/* translators: %s: link to the quote form */
				esc_html__( 'Bigger crowd, longer event or custom staffing? %s and we’ll build it.', 'twistedoak' ),
				'<a href="' . esc_url( $twistedoak_quote ) . '">' . esc_html__( 'Tell us what you need', 'twistedoak' ) . '</a>'
			);
			?>
		</p>
	</div>
</section>

<?php
$twistedoak_featured = twistedoak_menu_featured( 4 );
?>
<section class="band band--alt" id="menu" aria-labelledby="menu-title">
	<div class="wrap band__inner stack-48">
		<div class="section-head-row">
			<?php
			twistedoak_e(
				twistedoak_section_head(
					array(
						'eyebrow' => __( 'From the pit', 'twistedoak' ),
						'title'   => __( 'What’s coming off the smoker', 'twistedoak' ),
						'id'      => 'menu-title',
					)
				)
			);
			twistedoak_e(
				twistedoak_button(
					__( 'See the full menu', 'twistedoak' ),
					array(
						'href'     => twistedoak_menu_url(),
						'variant'  => 'secondary',
						'icon_end' => 'arrowRight',
						'class'    => 'hide-sm',
					)
				)
			);
			?>
		</div>

		<?php if ( $twistedoak_featured ) : ?>
			<div class="dish-grid scroller-sm" role="region" aria-label="<?php esc_attr_e( 'Featured smoked meats', 'twistedoak' ); ?>" tabindex="0">
				<?php foreach ( $twistedoak_featured as $twistedoak_item ) : ?>
					<article class="dish">
						<?php twistedoak_e( twistedoak_menu_photo( $twistedoak_item, 'dish__photo' ) ); ?>
						<div class="dish__body">
							<?php
							$twistedoak_badges = twistedoak_menu_badges( $twistedoak_item );
							if ( $twistedoak_badges ) {
								twistedoak_e( '<div class="badge-row">' . $twistedoak_badges . '</div>' );
							}
							?>
							<h3 class="h3"><?php echo esc_html( $twistedoak_item['name'] ); ?></h3>
							<?php if ( '' !== $twistedoak_item['description'] ) : ?>
								<p class="muted-2"><?php echo esc_html( wp_trim_words( $twistedoak_item['description'], 22 ) ); ?></p>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php
		$twistedoak_groups = twistedoak_menu_groups();
		if ( $twistedoak_groups ) :
			$twistedoak_featured_ids = array_column( $twistedoak_featured, 'id' );
			?>
			<div class="menu-summary">
				<?php
				foreach ( $twistedoak_groups as $twistedoak_group ) :
					$twistedoak_names = array();
					foreach ( $twistedoak_group['items'] as $twistedoak_item ) {
						if ( ! in_array( $twistedoak_item['id'], $twistedoak_featured_ids, true ) ) {
							$twistedoak_names[] = $twistedoak_item['name'];
						}
					}
					if ( ! $twistedoak_names ) {
						continue;
					}
					$twistedoak_meta = twistedoak_menu_category_meta( $twistedoak_group['category'] );
					$twistedoak_head = 'proteins' === $twistedoak_group['category']['slug'] && $twistedoak_featured ? __( 'More smoked meats', 'twistedoak' ) : $twistedoak_meta['title'];
					?>
					<div class="menu-summary__col">
						<h3 class="eyebrow eyebrow--plain"><?php echo esc_html( $twistedoak_head ); ?></h3>
						<p><?php echo esc_html( implode( ' · ', $twistedoak_names ) ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php
		twistedoak_e(
			twistedoak_button(
				__( 'See the full menu', 'twistedoak' ),
				array(
					'href'     => twistedoak_menu_url(),
					'variant'  => 'secondary',
					'icon_end' => 'arrowRight',
					'block'    => true,
					'class'    => 'show-sm',
				)
			)
		);
		?>
	</div>
</section>

<section class="band band--surface" id="story" aria-labelledby="story-title">
	<div class="wrap band__inner split">
		<?php
		twistedoak_e(
			twistedoak_photo(
				'home_story',
				array(
					'class' => 'photo--5x4',
					'sizes' => '(min-width: 1200px) 568px, (min-width: 960px) 45vw, 100vw',
				)
			)
		);
		?>
		<div class="stack-20">
			<p class="eyebrow"><?php esc_html_e( 'Our story', 'twistedoak' ); ?></p>
			<h2 class="h2" id="story-title"><?php esc_html_e( 'It started in the backyard', 'twistedoak' ); ?></h2>
			<p class="lede"><?php esc_html_e( 'Before the trailer and the catering kitchen, it was backyard barbecue and a smoker that rarely got a day off. Pitmaster Michael McCrary grew those weekends into Twisted Oak — still cooking over real hardwood, still making every rub and side from scratch.', 'twistedoak' ); ?></p>
			<blockquote class="pull-quote"><?php esc_html_e( 'Oak is the wood we trust in the smoker. “Twisted” is the personality we bring to tradition.', 'twistedoak' ); ?></blockquote>
			<div>
				<?php
				twistedoak_e(
					twistedoak_button(
						__( 'Read our story', 'twistedoak' ),
						array(
							'href'     => twistedoak_story_url(),
							'variant'  => 'secondary',
							'icon_end' => 'arrowRight',
						)
					)
				);
				?>
			</div>
		</div>
	</div>
</section>

<?php
get_template_part( 'template-parts/testimonials', null, array( 'band' => 'bg' ) );

get_template_part(
	'template-parts/cta-band',
	null,
	array(
		'id'      => 'book-title',
		'eyebrow' => __( 'Spring and fall weekends fill first', 'twistedoak' ),
		'title'   => __( 'Let’s get your event on the smoker', 'twistedoak' ),
		'text'    => esc_html__( 'Tell us your date, headcount and what you’re celebrating. We’ll put together a menu and a quote. Serving Charlottesville, Albemarle County and the surrounding area.', 'twistedoak' ),
		'from'    => 'var(--stone-100)',
		'stack'   => true,
		'actions' => twistedoak_button(
			__( 'Get a quote', 'twistedoak' ),
			array(
				'href'     => $twistedoak_quote,
				'size'     => 'lg',
				'icon_end' => 'arrowRight',
			)
		) . twistedoak_or_call(),
	)
);

get_footer();
