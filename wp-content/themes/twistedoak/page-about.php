<?php
/**
 * Our story (the page with slug "about"). The page's own text fills "Where it started".
 *
 * @package TwistedOak
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<section class="band band--bg" aria-labelledby="story-title">
		<div class="wrap story-hero">
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
			<div class="story-hero__row">
				<div class="story-hero__title">
					<p class="eyebrow"><?php the_title(); ?></p>
					<h1 class="display" id="story-title"><?php esc_html_e( 'Backyard weekends, grown into Twisted Oak.', 'twistedoak' ); ?></h1>
				</div>
				<p class="lede story-hero__lede"><?php esc_html_e( 'Real wood, real smoke and no shortcuts — the same way pitmaster Michael McCrary cooked before there was a trailer, a kitchen or a name on the door.', 'twistedoak' ); ?></p>
			</div>
			<?php
			twistedoak_e(
				twistedoak_photo(
					'story_hero',
					array(
						'class'   => 'photo--21x9',
						'size'    => 'twistedoak-wide',
						'loading' => 'eager',
						'sizes'   => '(min-width: 1200px) 1136px, 100vw',
					)
				)
			);
			?>
		</div>
	</section>

	<section class="band band--bg band--flush-top" aria-labelledby="start-title">
		<div class="wrap band__inner story-start">
			<div class="story-start__aside">
				<?php
				twistedoak_e(
					twistedoak_photo(
						'story_portrait',
						array(
							'class' => 'photo--4x5',
							'size'  => 'twistedoak-tall',
							'sizes' => '(min-width: 960px) 420px, 100vw',
						)
					)
				);
				?>
				<div>
					<p class="person__name">Michael McCrary</p>
					<p class="small muted"><?php esc_html_e( 'Founder & pitmaster', 'twistedoak' ); ?></p>
				</div>
			</div>
			<div class="story-start__body">
				<h2 class="h2" id="start-title"><?php esc_html_e( 'Where it started', 'twistedoak' ); ?></h2>
				<div class="prose prose--story">
					<?php the_content(); ?>
				</div>
				<dl class="facts">
					<div><dt><?php esc_html_e( 'The wood', 'twistedoak' ); ?></dt><dd><?php esc_html_e( 'Cherry, hickory & oak', 'twistedoak' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'The kitchen', 'twistedoak' ); ?></dt><dd><?php esc_html_e( 'Beacon Kitchen, Charlottesville', 'twistedoak' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Serving', 'twistedoak' ); ?></dt><dd><?php esc_html_e( 'Central Virginia', 'twistedoak' ); ?></dd></div>
				</dl>
			</div>
		</div>
	</section>
	<?php
endwhile;
?>

<section class="band band--surface" aria-labelledby="name-title">
	<div class="wrap name-quote">
		<?php twistedoak_e( twistedoak_icon( 'flame', array( 'size' => 32 ) ) ); ?>
		<h2 class="eyebrow" id="name-title"><?php esc_html_e( 'Why “Twisted Oak”', 'twistedoak' ); ?></h2>
		<blockquote class="name-quote__text"><?php esc_html_e( 'Oak is the wood we trust in the smoker. “Twisted” is the personality we bring to tradition.', 'twistedoak' ); ?></blockquote>
	</div>
</section>

<section class="band band--bg" aria-labelledby="roots-title">
	<div class="wrap band__inner stack-40">
		<?php
		twistedoak_e(
			twistedoak_section_head(
				array(
					'eyebrow' => __( 'Our roots', 'twistedoak' ),
					'title'   => __( 'Three places on every plate', 'twistedoak' ),
					'id'      => 'roots-title',
				)
			)
		);
		$twistedoak_roots = array(
			array( 'story_texas', __( 'Texas inspired', 'twistedoak' ), __( 'Brisket in our Texas rub, smoked low and slow until the bark sets and the slices bend. It’s the cook everything else is measured against.', 'twistedoak' ) ),
			array( 'story_tennessee', __( 'Tennessee rooted', 'twistedoak' ), __( '[Michael’s Tennessee roots — where he grew up or learned to cook, and what that brings to the pit.]', 'twistedoak' ) ),
			array( 'story_virginia', __( 'Crafted in Virginia', 'twistedoak' ), __( 'Cooked in Charlottesville and served across Central Virginia, with every rub and side made from scratch in our own kitchen.', 'twistedoak' ) ),
		);
		?>
		<div class="roots-grid">
			<?php foreach ( $twistedoak_roots as [ $twistedoak_slot, $twistedoak_title, $twistedoak_text ] ) : ?>
				<div class="root">
					<?php
					twistedoak_e(
						twistedoak_photo(
							$twistedoak_slot,
							array(
								'class' => 'photo--4x3',
								'size'  => 'twistedoak-card',
								'sizes' => '(min-width: 1200px) 378px, (min-width: 700px) 45vw, 100vw',
							)
						)
					);
					?>
					<div class="rule-card">
						<h3 class="h3"><?php echo esc_html( $twistedoak_title ); ?></h3>
						<p class="muted-2"><?php echo esc_html( $twistedoak_text ); ?></p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="band band--alt" aria-labelledby="cook-title">
	<div class="wrap band__inner split">
		<?php
		twistedoak_e(
			twistedoak_photo(
				'story_wood',
				array(
					'class' => 'photo--5x4',
					'sizes' => '(min-width: 1200px) 568px, (min-width: 960px) 45vw, 100vw',
				)
			)
		);
		?>
		<div class="stack-32">
			<div class="stack-12">
				<p class="eyebrow"><?php esc_html_e( 'How we cook', 'twistedoak' ); ?></p>
				<h2 class="h2" id="cook-title"><?php esc_html_e( 'No shortcuts, still', 'twistedoak' ); ?></h2>
			</div>
			<ol class="num-list">
				<?php
				$twistedoak_cook = array(
					array( __( 'Real wood, real smoke', 'twistedoak' ), __( 'Every cook runs on split cherry, hickory and oak. No gas assist, no liquid smoke.', 'twistedoak' ) ),
					array( __( 'Low and slow', 'twistedoak' ), __( 'Meats come off when they’re ready, not when the clock says so. When it sells out, it sells out.', 'twistedoak' ) ),
					array( __( 'Made from scratch', 'twistedoak' ), __( 'Rubs, sauce and every Southern side are made in house, from the pit beans to the banana pudding.', 'twistedoak' ) ),
				);
				foreach ( $twistedoak_cook as $twistedoak_i => [ $twistedoak_title, $twistedoak_text ] ) :
					?>
					<li>
						<span class="num-list__n" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $twistedoak_i + 1 ) ); ?></span>
						<div><h3 class="num-list__title"><?php echo esc_html( $twistedoak_title ); ?></h3><p class="muted-2"><?php echo esc_html( $twistedoak_text ); ?></p></div>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	</div>
</section>

<?php
get_template_part(
	'template-parts/cta-band',
	null,
	array(
		'id'      => 'story-cta-title',
		'title'   => __( 'Come taste the story', 'twistedoak' ),
		'text'    => esc_html__( 'Order pickup from Beacon Kitchen, or bring the smoker to your wedding, office or backyard.', 'twistedoak' ),
		'from'    => 'var(--stone-200)',
		'actions' => twistedoak_button(
			__( 'Get a quote', 'twistedoak' ),
			array(
				'href'     => twistedoak_catering_url( 'catering-form' ),
				'size'     => 'lg',
				'icon_end' => 'arrowRight',
			)
		) . twistedoak_button(
			__( 'See the menu', 'twistedoak' ),
			array(
				'href'    => twistedoak_menu_url(),
				'size'    => 'lg',
				'variant' => 'secondary',
			)
		),
	)
);

get_footer();
