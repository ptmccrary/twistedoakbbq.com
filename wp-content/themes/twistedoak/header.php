<?php
/**
 * Site header: the design system's Header (sticky), with the mobile drawer.
 *
 * @package TwistedOak
 */

defined( 'ABSPATH' ) || exit;

$twistedoak_cta = twistedoak_button(
	__( 'Get a quote', 'twistedoak' ),
	array( 'href' => twistedoak_catering_url( 'catering-form' ) )
);
?>
<!doctype html>
<html <?php language_attributes(); ?> data-theme="light">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'twistedoak' ); ?></a>

<div class="site-header" data-site-header>
	<header class="to-header">
		<div class="to-header__inner">
			<a class="to-header__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"<?php echo is_front_page() ? ' aria-current="page"' : ''; ?>>
				<img class="to-header__logo" src="<?php echo esc_url( twistedoak_logo_url() ); ?>" width="56" height="56" alt="<?php esc_attr_e( 'Twisted Oak Barbecue — home', 'twistedoak' ); ?>">
			</a>
			<nav class="to-header__nav" aria-label="<?php esc_attr_e( 'Main', 'twistedoak' ); ?>">
				<?php twistedoak_e( twistedoak_nav_markup( 'bar' ) ); ?>
			</nav>
			<div class="to-header__cta"><?php twistedoak_e( $twistedoak_cta ); ?></div>
			<button type="button" class="to-btn to-btn--secondary to-iconbtn to-header__menu" aria-label="<?php esc_attr_e( 'Open menu', 'twistedoak' ); ?>" aria-expanded="false" aria-controls="site-drawer" data-drawer-open>
				<?php twistedoak_e( twistedoak_icon( 'menu' ) ); ?>
			</button>
		</div>
	</header>
</div>

<div class="to-overlay site-drawer" id="site-drawer" hidden data-drawer>
	<div class="to-drawer" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Menu', 'twistedoak' ); ?>" tabindex="-1" data-drawer-panel>
		<div class="to-drawer__top">
			<a class="to-header__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<img class="to-header__logo" src="<?php echo esc_url( twistedoak_logo_url() ); ?>" width="48" height="48" alt="">
				<span class="to-header__name">Twisted Oak</span>
			</a>
			<button type="button" class="to-btn to-btn--ghost to-iconbtn" aria-label="<?php esc_attr_e( 'Close menu', 'twistedoak' ); ?>" data-drawer-close>
				<?php twistedoak_e( twistedoak_icon( 'x' ) ); ?>
			</button>
		</div>
		<nav class="to-drawer__nav" aria-label="<?php esc_attr_e( 'Main', 'twistedoak' ); ?>">
			<?php twistedoak_e( twistedoak_nav_markup( 'drawer' ) ); ?>
		</nav>
		<div class="to-drawer__foot">
			<?php
			twistedoak_e(
				twistedoak_button(
					__( 'Get a quote', 'twistedoak' ),
					array(
						'href'  => twistedoak_catering_url( 'catering-form' ),
						'block' => true,
						'size'  => 'lg',
					)
				)
			);
			$twistedoak_info = twistedoak_info();
			?>
			<a class="drawer-phone" href="<?php echo esc_url( $twistedoak_info['phone_href'] ); ?>"><?php twistedoak_e( twistedoak_icon( 'phone' ) ); ?><?php echo esc_html( $twistedoak_info['phone'] ); ?></a>
		</div>
	</div>
</div>

<main id="main" class="site-main" tabindex="-1">
