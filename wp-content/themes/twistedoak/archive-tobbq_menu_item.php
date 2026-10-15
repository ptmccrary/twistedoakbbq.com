<?php
/**
 * Menu (/menu/): every category from the menu source, as a menu board.
 *
 * @package TwistedOak
 */

defined( 'ABSPATH' ) || exit;

get_header();

$twistedoak_info   = twistedoak_info();
$twistedoak_groups = twistedoak_menu_groups();
?>

<section class="band band--hero band--hero-short" data-theme="dark" aria-labelledby="menu-title">
	<div class="wrap hero hero--crumbs">
		<?php
		twistedoak_e(
			twistedoak_breadcrumbs(
				array(
					array(
						'label' => __( 'Home', 'twistedoak' ),
						'href'  => home_url( '/' ),
					),
					array( 'label' => __( 'Menu', 'twistedoak' ) ),
				)
			)
		);
		?>
		<div class="hero__row">
			<div class="hero__copy">
				<p class="eyebrow"><?php esc_html_e( 'Menu', 'twistedoak' ); ?></p>
				<h1 class="display" id="menu-title"><?php esc_html_e( 'Straight from the pit.', 'twistedoak' ); ?></h1>
				<p class="lede"><?php esc_html_e( 'Every order comes with Martin’s potato buns, our Twisted Red BBQ sauce, pickles and pickled onions.', 'twistedoak' ); ?></p>
			</div>
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
			?>
		</div>
	</div>
</section>

<section class="band band--torn" id="menu" aria-label="<?php esc_attr_e( 'Food menu', 'twistedoak' ); ?>">
	<?php twistedoak_e( twistedoak_torn_edge( 'a' ) ); ?>
	<div class="wrap band__inner band__inner--menu stack-40">
		<?php if ( $twistedoak_groups ) : ?>
			<nav class="pill-nav" aria-label="<?php esc_attr_e( 'Menu categories', 'twistedoak' ); ?>">
				<?php foreach ( $twistedoak_groups as $twistedoak_group ) : ?>
					<a class="pill" href="#<?php echo esc_attr( twistedoak_menu_anchor( $twistedoak_group['category']['slug'] ) ); ?>"><?php echo esc_html( twistedoak_menu_category_meta( $twistedoak_group['category'] )['title'] ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php
			foreach ( $twistedoak_groups as $twistedoak_group ) :
				$twistedoak_meta   = twistedoak_menu_category_meta( $twistedoak_group['category'] );
				$twistedoak_anchor = twistedoak_menu_anchor( $twistedoak_group['category']['slug'] );
				?>
				<div class="menu-group" id="<?php echo esc_attr( $twistedoak_anchor ); ?>">
					<div class="menu-group__aside">
						<h2 class="h2" id="<?php echo esc_attr( $twistedoak_anchor ); ?>-title"><?php echo esc_html( $twistedoak_meta['title'] ); ?></h2>
						<?php if ( '' !== $twistedoak_meta['intro'] ) : ?>
							<p class="muted-2"><?php echo esc_html( $twistedoak_meta['intro'] ); ?></p>
						<?php endif; ?>
						<?php
						twistedoak_e(
							twistedoak_photo(
								$twistedoak_meta['photo'],
								array(
									'class'   => 'photo--4x3',
									'size'    => 'twistedoak-card',
									'caption' => twistedoak_photo_slots()[ $twistedoak_meta['photo'] ][1] ?? strtolower( $twistedoak_meta['title'] ),
									'alt'     => twistedoak_photo_slots()[ $twistedoak_meta['photo'] ][2] ?? $twistedoak_meta['title'],
									'sizes'   => '(min-width: 960px) 360px, 100vw',
								)
							)
						);
						?>
					</div>
					<ul class="menu-list" aria-labelledby="<?php echo esc_attr( $twistedoak_anchor ); ?>-title">
						<?php foreach ( $twistedoak_group['items'] as $twistedoak_item ) : ?>
							<li class="menu-item<?php echo ! empty( $twistedoak_item['sold_out'] ) ? ' menu-item--sold-out' : ''; ?>">
								<div class="menu-item__line">
									<h3 class="menu-item__name"><?php echo esc_html( $twistedoak_item['name'] ); ?></h3>
									<?php
									$twistedoak_badges = twistedoak_menu_badges( $twistedoak_item );
									if ( $twistedoak_badges ) {
										twistedoak_e( '<span class="badge-row">' . $twistedoak_badges . '</span>' );
									}
									?>
									<?php if ( '' !== $twistedoak_item['price'] ) : ?>
										<span class="menu-item__leader" aria-hidden="true"></span>
										<p class="menu-item__price"><strong><?php echo esc_html( $twistedoak_item['price'] ); ?></strong><?php echo '' !== $twistedoak_item['unit'] ? '<span> ' . esc_html( $twistedoak_item['unit'] ) . '</span>' : ''; ?></p>
									<?php endif; ?>
								</div>
								<?php if ( '' !== $twistedoak_item['description'] ) : ?>
									<p class="menu-item__desc"><?php echo esc_html( $twistedoak_item['description'] ); ?></p>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		<?php else : ?>
			<div class="empty">
				<p class="empty__title"><?php esc_html_e( 'The menu board is being rewritten', 'twistedoak' ); ?></p>
				<p>
					<?php
					printf(
						/* translators: %s: link to online ordering */
						esc_html__( 'The %s always shows what’s available today.', 'twistedoak' ),
						'<a href="' . esc_url( $twistedoak_info['order_url'] ) . '">' . esc_html__( 'online order page', 'twistedoak' ) . '</a>'
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<?php twistedoak_e( twistedoak_alert( __( 'Menu and prices may change', 'twistedoak' ), __( 'Some items sell out on busy days. The online order page always shows what’s available.', 'twistedoak' ) ) ); ?>
	</div>
</section>

<section class="band band--alt" id="good-to-know" aria-labelledby="gtk-title">
	<div class="wrap band__inner stack-32">
		<h2 class="h2" id="gtk-title"><?php esc_html_e( 'Before you order', 'twistedoak' ); ?></h2>
		<div class="rule-grid">
			<div class="rule-card">
				<h3 class="h3"><?php esc_html_e( 'Pickup', 'twistedoak' ); ?></h3>
				<p class="muted-2">
					<?php
					/* translators: 1: street address, 2: pickup days and hours */
					echo esc_html( sprintf( __( 'Order online and pick up hot at Beacon Kitchen, %1$s. %2$s', 'twistedoak' ), $twistedoak_info['address'][1] ?? '221 Carlton Rd, Suite 1', twistedoak_copy( 'pickup_hours' ) ) );
					?>
				</p>
			</div>
			<div class="rule-card">
				<h3 class="h3"><?php esc_html_e( 'Dietary needs', 'twistedoak' ); ?></h3>
				<p class="muted-2"><?php esc_html_e( 'Our smoked tofu burnt ends are vegetarian. [Allergen and gluten-free details]', 'twistedoak' ); ?></p>
			</div>
			<div class="rule-card">
				<h3 class="h3"><?php esc_html_e( 'How much to order', 'twistedoak' ); ?></h3>
				<p class="muted-2"><?php esc_html_e( '[Portion guidance — e.g. pounds of meat per person]', 'twistedoak' ); ?></p>
			</div>
		</div>
	</div>
</section>

<?php
get_template_part(
	'template-parts/cta-band',
	null,
	array(
		'id'      => 'menu-cta-title',
		'title'   => __( 'Feeding a crowd?', 'twistedoak' ),
		'text'    => esc_html__( 'Catering packages start at 10 guests, with pickup, drop-off or full service.', 'twistedoak' ),
		'from'    => 'var(--stone-200)',
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
