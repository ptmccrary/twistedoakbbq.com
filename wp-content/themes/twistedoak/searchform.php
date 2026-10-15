<?php
/**
 * Search form.
 *
 * @package TwistedOak
 */

defined( 'ABSPATH' ) || exit;

$twistedoak_id = wp_unique_id( 'search-' );
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="to-sr" for="<?php echo esc_attr( $twistedoak_id ); ?>"><?php esc_html_e( 'Search', 'twistedoak' ); ?></label>
	<input class="to-input" type="search" id="<?php echo esc_attr( $twistedoak_id ); ?>" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Search the site', 'twistedoak' ); ?>">
	<?php
	twistedoak_e(
		twistedoak_button(
			__( 'Search', 'twistedoak' ),
			array(
				'type'    => 'submit',
				'variant' => 'secondary',
			)
		)
	);
	?>
</form>
