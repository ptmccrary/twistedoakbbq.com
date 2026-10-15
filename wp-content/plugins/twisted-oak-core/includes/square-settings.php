<?php
/**
 * Food Menu → Square: menu source + Square credentials.
 *
 * Option `twistedoak_square_settings` (not autoloaded):
 *   source        local|square
 *   environment   sandbox|production
 *   access_token  string (prefer the TWISTEDOAK_SQUARE_ACCESS_TOKEN constant in wp-config.php)
 *   location_id   string
 *   cache_minutes int
 *
 * @package TwistedOakCore
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	static function (): void {
		add_submenu_page(
			'edit.php?post_type=tobbq_menu_item',
			__( 'Square integration', 'twisted-oak-core' ),
			__( 'Square', 'twisted-oak-core' ),
			'manage_options',
			'twistedoak-square',
			'tobbq_core_render_square_page'
		);
	}
);

add_action(
	'admin_init',
	static function (): void {
		register_setting(
			'twistedoak_square',
			'twistedoak_square_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'tobbq_core_sanitize_square_settings',
				'default'           => array(),
			)
		);
	}
);

/**
 * @param mixed $input Raw.
 * @return array<string, mixed>
 */
function tobbq_core_sanitize_square_settings( mixed $input ): array {
	$input = is_array( $input ) ? $input : array();
	$old   = get_option( 'twistedoak_square_settings', array() );
	$old   = is_array( $old ) ? $old : array();
	$token = trim( sanitize_text_field( (string) ( $input['access_token'] ?? '' ) ) );

	$clean = array(
		'source'        => 'square' === ( $input['source'] ?? '' ) ? 'square' : 'local',
		'environment'   => 'production' === ( $input['environment'] ?? '' ) ? 'production' : 'sandbox',
		// Blank token field = keep the stored one; "-" clears it.
		'access_token'  => '-' === $token ? '' : ( '' === $token ? (string) ( $old['access_token'] ?? '' ) : $token ),
		'location_id'   => preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $input['location_id'] ?? '' ) ),
		'cache_minutes' => max( 1, min( 1440, absint( $input['cache_minutes'] ?? 15 ) ) ),
	);
	// Settings changed → drop cached catalog.
	delete_transient( 'tobbq_square_catalog_' . md5( $clean['location_id'] . $clean['environment'] ) );
	return $clean;
}

// Keep the token out of the autoloaded options.
foreach ( array( 'add_option_twistedoak_square_settings', 'update_option_twistedoak_square_settings' ) as $tobbq_hook ) {
	add_action( $tobbq_hook, static fn() => wp_set_option_autoload( 'twistedoak_square_settings', false ) );
}
unset( $tobbq_hook );

function tobbq_core_render_square_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$opt  = get_option( 'twistedoak_square_settings', array() );
	$opt  = is_array( $opt ) ? $opt : array();
	$s    = \TwistedOak\Core\Menu\SquareSource::settings();
	$name = 'twistedoak_square_settings';
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<p><?php esc_html_e( 'The menu pages read from a "menu source". Local = the Food Menu items in WordPress. Square = the Square catalog (falls back to Local until configured). Recommended: keep Local and sync Square into it (wp twisted-oak-menu sync-square) so permalinks, photos and SEO stay in WordPress.', 'twisted-oak-core' ); ?></p>
		<p><strong><?php esc_html_e( 'Status:', 'twisted-oak-core' ); ?></strong>
			<?php
			echo esc_html(
				\TwistedOak\Core\Menu\SquareSource::is_configured()
					? __( 'Credentials present. Live requests are disabled in code until the integration is finished (twistedoak_square_live_requests filter).', 'twisted-oak-core' )
					: __( 'Not configured — Local source in use.', 'twisted-oak-core' )
			);
			?>
		</p>
		<form action="options.php" method="post">
			<?php settings_fields( 'twistedoak_square' ); ?>
			<table class="form-table" role="presentation"><tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Menu source', 'twisted-oak-core' ); ?></th>
					<td>
						<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[source]" value="local" <?php checked( 'square' !== ( $opt['source'] ?? '' ) ); ?>> <?php esc_html_e( 'Local (WordPress menu items)', 'twisted-oak-core' ); ?></label><br>
						<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[source]" value="square" <?php checked( 'square' === ( $opt['source'] ?? '' ) ); ?>> <?php esc_html_e( 'Square catalog (live, cached)', 'twisted-oak-core' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Environment', 'twisted-oak-core' ); ?></th>
					<td>
						<select name="<?php echo esc_attr( $name ); ?>[environment]">
							<option value="sandbox" <?php selected( 'sandbox', $s['environment'] ); ?>><?php esc_html_e( 'Sandbox', 'twisted-oak-core' ); ?></option>
							<option value="production" <?php selected( 'production', $s['environment'] ); ?>><?php esc_html_e( 'Production', 'twisted-oak-core' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tobbq-sq-token"><?php esc_html_e( 'Access token', 'twisted-oak-core' ); ?></label></th>
					<td>
						<?php if ( defined( 'TWISTEDOAK_SQUARE_ACCESS_TOKEN' ) ) : ?>
							<p><?php esc_html_e( 'Set in wp-config.php (TWISTEDOAK_SQUARE_ACCESS_TOKEN).', 'twisted-oak-core' ); ?></p>
						<?php else : ?>
							<input type="password" id="tobbq-sq-token" class="regular-text" name="<?php echo esc_attr( $name ); ?>[access_token]" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( '' !== $s['access_token'] ? '•••• ' . substr( $s['access_token'], -4 ) : '' ); ?>">
							<p class="description"><?php esc_html_e( 'Leave blank to keep the saved token; enter "-" to clear it. Prefer defining TWISTEDOAK_SQUARE_ACCESS_TOKEN in wp-config.php.', 'twisted-oak-core' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tobbq-sq-loc"><?php esc_html_e( 'Location ID', 'twisted-oak-core' ); ?></label></th>
					<td><input type="text" id="tobbq-sq-loc" class="regular-text" name="<?php echo esc_attr( $name ); ?>[location_id]" value="<?php echo esc_attr( (string) ( $opt['location_id'] ?? '' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="tobbq-sq-cache"><?php esc_html_e( 'Cache (minutes)', 'twisted-oak-core' ); ?></label></th>
					<td><input type="number" id="tobbq-sq-cache" class="small-text" min="1" max="1440" name="<?php echo esc_attr( $name ); ?>[cache_minutes]" value="<?php echo esc_attr( (string) $s['cache_minutes'] ); ?>"></td>
				</tr>
			</tbody></table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
