<?php
/**
 * Generic page (contact, FAQs, privacy, …).
 *
 * @package TwistedOak
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	get_template_part( 'template-parts/page-hero', null, array( 'title' => get_the_title() ) );
	?>
	<section class="band band--torn" aria-label="<?php echo esc_attr( get_the_title() ); ?>">
		<?php twistedoak_e( twistedoak_torn_edge( 'a' ) ); ?>
		<div class="wrap band__inner">
			<div class="prose">
				<?php
				the_content();
				wp_link_pages();
				?>
			</div>
		</div>
	</section>
	<?php
endwhile;

get_footer();
