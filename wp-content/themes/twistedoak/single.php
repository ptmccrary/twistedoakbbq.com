<?php
/**
 * Single blog post.
 *
 * @package TwistedOak
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$twistedoak_blog = (int) get_option( 'page_for_posts' );
	get_template_part(
		'template-parts/page-hero',
		null,
		array(
			'title'   => get_the_title(),
			'eyebrow' => get_the_date(),
			'crumbs'  => array(
				array(
					'label' => __( 'Home', 'twistedoak' ),
					'href'  => home_url( '/' ),
				),
				array(
					'label' => $twistedoak_blog ? get_the_title( $twistedoak_blog ) : __( 'News', 'twistedoak' ),
					'href'  => $twistedoak_blog ? get_permalink( $twistedoak_blog ) : home_url( '/' ),
				),
				array( 'label' => get_the_title() ),
			),
		)
	);
	?>
	<section class="band band--torn" aria-label="<?php esc_attr_e( 'Article', 'twistedoak' ); ?>">
		<?php twistedoak_e( twistedoak_torn_edge( 'a' ) ); ?>
		<div class="wrap band__inner">
			<article <?php post_class( 'prose' ); ?>>
				<?php
				if ( has_post_thumbnail() ) {
					the_post_thumbnail( 'large', array( 'class' => 'prose__lead-image' ) );
				}
				the_content();
				wp_link_pages();
				?>
			</article>
		</div>
	</section>
	<?php
endwhile;

get_footer();
