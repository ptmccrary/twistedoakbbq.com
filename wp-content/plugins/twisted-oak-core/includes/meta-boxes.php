<?php
/**
 * Classic meta boxes (also rendered in the block editor's meta box area)
 * and the "order" field on menu category terms.
 *
 * @package TwistedOakCore
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'add_meta_boxes',
	static function (): void {
		add_meta_box( 'tobbq_menu_item_details', __( 'Menu Item Details', 'twisted-oak-core' ), 'tobbq_core_render_meta_box', 'tobbq_menu_item', 'side', 'high' );
		add_meta_box( 'tobbq_event_details', __( 'Event Details', 'twisted-oak-core' ), 'tobbq_core_render_meta_box', 'tobbq_event', 'side', 'high' );
	}
);

/**
 * Renders the details box for either post type from tobbq_core_meta_fields().
 *
 * @param WP_Post $post Current post.
 */
function tobbq_core_render_meta_box( WP_Post $post ): void {
	$fields = tobbq_core_meta_fields()[ $post->post_type ] ?? array();
	wp_nonce_field( 'tobbq_core_save_meta', 'tobbq_core_meta_nonce' );

	foreach ( $fields as $key => $field ) {
		$id    = 'tobbq-' . sanitize_html_class( ltrim( $key, '_' ) );
		$value = get_post_meta( $post->ID, $key, true );

		echo '<p>';
		if ( 'boolean' === $field['type'] ) {
			printf(
				'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
				esc_attr( $id ),
				esc_attr( $key ),
				checked( (bool) $value, true, false ),
				esc_html( $field['label'] )
			);
		} else {
			$type        = 'text';
			$placeholder = '';
			if ( in_array( $key, array( '_tobbq_event_start', '_tobbq_event_end' ), true ) ) {
				$type  = 'datetime-local';
				$value = $value ? str_replace( ' ', 'T', (string) $value ) : '';
			} elseif ( '_tobbq_event_link' === $key ) {
				$type = 'url';
			} elseif ( '_tobbq_price' === $key ) {
				$placeholder = __( '14.50 or Market', 'twisted-oak-core' );
			} elseif ( '_tobbq_unit' === $key ) {
				$placeholder = __( 'each, half pan…', 'twisted-oak-core' );
			}
			printf(
				'<label for="%1$s"><strong>%2$s</strong></label><br /><input type="%3$s" class="widefat" id="%1$s" name="%4$s" value="%5$s" placeholder="%6$s" />',
				esc_attr( $id ),
				esc_html( $field['label'] ),
				esc_attr( $type ),
				esc_attr( $key ),
				esc_attr( (string) $value ),
				esc_attr( $placeholder )
			);
		}
		echo '</p>';
	}
}

add_action(
	'save_post',
	static function ( int $post_id, WP_Post $post ): void {
		$fields = tobbq_core_meta_fields()[ $post->post_type ] ?? null;
		if ( null === $fields
			|| ! isset( $_POST['tobbq_core_meta_nonce'] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['tobbq_core_meta_nonce'] ) ), 'tobbq_core_save_meta' )
			|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| wp_is_post_revision( $post_id )
			|| ! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}

		foreach ( $fields as $key => $field ) {
			if ( 'boolean' === $field['type'] ) {
				update_post_meta( $post_id, $key, ! empty( $_POST[ $key ] ) );
				continue;
			}
			// Sanitized by the registered sanitize_callback inside update_post_meta().
			$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( '' === $raw ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $raw );
			}
		}
	},
	10,
	2
);

/*
 * Term meta "order" on menu categories.
 */
add_action(
	'tobbq_menu_category_add_form_fields',
	static function (): void {
		wp_nonce_field( 'tobbq_core_term_order', 'tobbq_core_term_nonce' );
		?>
		<div class="form-field">
			<label for="tobbq-term-order"><?php esc_html_e( 'Order', 'twisted-oak-core' ); ?></label>
			<input type="number" name="tobbq_term_order" id="tobbq-term-order" value="0" step="1" />
			<p><?php esc_html_e( 'Lower numbers appear first on the menu page.', 'twisted-oak-core' ); ?></p>
		</div>
		<?php
	}
);

add_action(
	'tobbq_menu_category_edit_form_fields',
	static function ( WP_Term $term ): void {
		wp_nonce_field( 'tobbq_core_term_order', 'tobbq_core_term_nonce' );
		?>
		<tr class="form-field">
			<th scope="row"><label for="tobbq-term-order"><?php esc_html_e( 'Order', 'twisted-oak-core' ); ?></label></th>
			<td>
				<input type="number" name="tobbq_term_order" id="tobbq-term-order" value="<?php echo esc_attr( (string) (int) get_term_meta( $term->term_id, 'order', true ) ); ?>" step="1" />
				<p class="description"><?php esc_html_e( 'Lower numbers appear first on the menu page.', 'twisted-oak-core' ); ?></p>
			</td>
		</tr>
		<?php
	}
);

/**
 * Saves the category order field.
 *
 * @param int $term_id Term ID.
 */
function tobbq_core_save_term_order( int $term_id ): void {
	if ( ! isset( $_POST['tobbq_core_term_nonce'], $_POST['tobbq_term_order'] )
		|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['tobbq_core_term_nonce'] ) ), 'tobbq_core_term_order' )
		|| ! current_user_can( 'manage_categories' )
	) {
		return;
	}
	update_term_meta( $term_id, 'order', (int) $_POST['tobbq_term_order'] );
}
add_action( 'created_tobbq_menu_category', 'tobbq_core_save_term_order' );
add_action( 'edited_tobbq_menu_category', 'tobbq_core_save_term_order' );
