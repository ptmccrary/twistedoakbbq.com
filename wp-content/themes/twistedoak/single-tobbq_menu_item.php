<?php
/**
 * Single menu item.
 *
 * @package TwistedOak
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$twistedoak_item = function_exists( 'TwistedOak\Core\Menu\source' ) ? \TwistedOak\Core\Menu\source()->item( get_the_ID() ) : null;
	$twistedoak_item = $twistedoak_item ?? array(
		'name'     => get_the_title(),
		'price'    => '',
		'unit'     => '',
		'image'    => array( 'id' => get_post_thumbnail_id() ),
		'category' => null,
	);
	$twistedoak_cat  = $twistedoak_item['category'] ?? null;
	$twistedoak_info = twistedoak_info();
	?>
	<section class="band band--hero" data-theme="dark" aria-labelledby="item-title">
		<div class="wrap hero hero--crumbs">
			<?php
			$twistedoak_crumbs = array(
				array(
					'label' => __( 'Home', 'twistedoak' ),
					'href'  => home_url( '/' ),
				),
				array(
					'label' => __( 'Menu', 'twistedoak' ),
					'href'  => twistedoak_menu_url(),
				),
			);
			if ( $twistedoak_cat ) {
				$twistedoak_crumbs[] = array(
					'label' => twistedoak_menu_category_meta( $twistedoak_cat )['title'],
					'href'  => twistedoak_menu_url( twistedoak_menu_anchor( $twistedoak_cat['slug'] ) ),
				);
			}
			$twistedoak_crumbs[] = array( 'label' => get_the_title() );
			twistedoak_e( twistedoak_breadcrumbs( $twistedoak_crumbs ) );
			?>
			<div class="hero__grid">
				<div class="hero__copy">
					<?php
					$twistedoak_badges = twistedoak_menu_badges( $twistedoak_item );
					if ( $twistedoak_badges ) {
						twistedoak_e( '<div class="badge-row">' . $twistedoak_badges . '</div>' );
					}
					?>
					<h1 class="h1" id="item-title"><?php the_title(); ?></h1>
					<?php if ( '' !== ( $twistedoak_item['price'] ?? '' ) ) : ?>
						<p class="price-lg"><?php echo esc_html( trim( $twistedoak_item['price'] . ' ' . $twistedoak_item['unit'] ) ); ?></p>
					<?php endif; ?>
					<div class="prose prose--lede"><?php the_content(); ?></div>
					<div class="button-row">
						<?php
						twistedoak_e(
							twistedoak_button(
								__( 'Order pickup', 'twistedoak' ),
								array(
									'href'     => $twistedoak_info['order_url'],
									'size'     => 'lg',
									'icon_end' => 'arrowRight',
								)
							)
						);
						twistedoak_e(
							twistedoak_button(
								__( 'Back to the menu', 'twistedoak' ),
								array(
									'href'    => twistedoak_menu_url( $twistedoak_cat ? twistedoak_menu_anchor( $twistedoak_cat['slug'] ) : '' ),
									'size'    => 'lg',
									'variant' => 'secondary',
								)
							)
						);
						?>
					</div>
				</div>
				<?php twistedoak_e( twistedoak_menu_photo( $twistedoak_item, 'hero__photo photo--4x3' ) ); ?>
			</div>
		</div>
	</section>
	<?php
endwhile;

get_template_part(
	'template-parts/cta-band',
	null,
	array(
		'id'      => 'menu-cta-title',
		'title'   => __( 'Feeding a crowd?', 'twistedoak' ),
		'text'    => esc_html__( 'Catering packages start at 10 guests, with pickup, drop-off or full service.', 'twistedoak' ),
		'from'    => 'var(--stone-900)',
		'stack'   => true,
		'actions' => twistedoak_button(
			__( 'See catering packages', 'twistedoak' ),
			array(
				'href'     => twistedoak_catering_url( 'packages' ),
				'size'     => 'lg',
				'icon_end' => 'arrowRight',
			)
		) . twistedoak_or_call(),
	)
);

get_footer();
