<?php
/**
 * Fallback listing: blog index, archives and search results.
 *
 * @package TwistedOak
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( is_search() ) {
	/* translators: %s: search query */
	$twistedoak_title = sprintf( __( 'Results for “%s”', 'twistedoak' ), get_search_query() );
} elseif ( is_home() && get_option( 'page_for_posts' ) ) {
	$twistedoak_title = get_the_title( (int) get_option( 'page_for_posts' ) );
} elseif ( is_archive() ) {
	$twistedoak_title = wp_strip_all_tags( get_the_archive_title() );
} else {
	$twistedoak_title = __( 'News', 'twistedoak' );
}

get_template_part( 'template-parts/page-hero', null, array( 'title' => $twistedoak_title ) );
?>
<section class="band band--torn" aria-label="<?php echo esc_attr( $twistedoak_title ); ?>">
	<?php twistedoak_e( twistedoak_torn_edge( 'a' ) ); ?>
	<div class="wrap band__inner stack-32">
		<?php if ( is_search() ) : ?>
			<?php get_search_form(); ?>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>
			<div class="post-list">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article <?php post_class( 'post-card' ); ?>>
						<?php if ( has_post_thumbnail() ) : ?>
							<a class="post-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"><?php the_post_thumbnail( 'twistedoak-card', array( 'class' => 'photo photo--4x3 photo--img' ) ); ?></a>
						<?php endif; ?>
						<div class="post-card__body">
							<p class="eyebrow eyebrow--muted eyebrow--sm"><?php echo esc_html( 'post' === get_post_type() ? get_the_date() : (string) get_post_type_object( (string) get_post_type() )?->labels->singular_name ); ?></p>
							<h2 class="h3"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<p class="muted-2"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
						</div>
					</article>
				<?php endwhile; ?>
			</div>
			<?php
			the_posts_pagination(
				array(
					'prev_text' => __( 'Newer', 'twistedoak' ),
					'next_text' => __( 'Older', 'twistedoak' ),
				)
			);
			?>
		<?php else : ?>
			<div class="empty">
				<p class="empty__title"><?php esc_html_e( 'Nothing here yet', 'twistedoak' ); ?></p>
				<p>
					<?php
					printf(
						/* translators: %s: link to the menu */
						esc_html__( 'Try another search, or %s.', 'twistedoak' ),
						'<a href="' . esc_url( twistedoak_menu_url() ) . '">' . esc_html__( 'see the menu', 'twistedoak' ) . '</a>'
					);
					?>
				</p>
			</div>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
