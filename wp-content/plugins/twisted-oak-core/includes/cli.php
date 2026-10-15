<?php
/**
 * WP-CLI:
 *   wp twisted-oak-menu seed [--keep-existing]   Catering buffet menu (Proteins / Southern Sides / Desserts). Idempotent. Does not touch events.
 *   wp twisted-oak-menu sync-square [--dry-run]   Square → CPT sync (no-op until Square is configured + live requests enabled).
 *   wp twisted-oak-menu dump [--category=<slug>]  Print the active source's DTOs as JSON.
 *
 * @package TwistedOakCore
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Menu seed content + Square sync.
 */
final class TwistedOak_Menu_CLI {

	/**
	 * Replace menu items with the live-site catering menu. Events are never touched.
	 *
	 * Items are keyed by `_tobbq_seed_key`; re-running updates them in place.
	 * Unless --keep-existing is passed, menu items / menu categories that were
	 * not created by this command are permanently deleted (a DB dump exists in .backups).
	 *
	 * ## OPTIONS
	 *
	 * [--keep-existing]
	 * : Don't delete non-seeded menu items and categories.
	 *
	 * @param array<int, string>    $args       Positional.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function seed( array $args, array $assoc_args ): void {
		$keep = ! empty( $assoc_args['keep-existing'] );

		// Real catering buffet menu (twistedoakbbq.com/catering). Sold by package, so no prices/units.
		$categories = array(
			'proteins' => array( 'Proteins', 10, 'All of our meats are smoked with local cherry, hickory and oak.' ),
			'sides'    => array( 'Southern Sides', 20, '' ),
			'desserts' => array( 'Desserts', 30, '' ),
		);

		// slug => [name, price, unit, dietary[], flags[], description].
		// Flags: featured, sold_out, premium (→ _tobbq_premium), catering_only, seasonal (→ _tobbq_seasonal).
		$items = array(
			'proteins' => array(
				array( 'Texas Style Brisket', '', '', array(), array( 'featured', 'premium' ), 'Smoked low and slow over oak with our in-house Texas rub.' ),
				array( 'Pulled Pork', '', '', array(), array( 'featured' ), 'Seasoned with our in-house rub and smoked over oak low and slow until tender.' ),
				array( 'Smoked Turkey Breast', '', '', array(), array( 'featured' ), 'Not your typical Thanksgiving turkey: our turkey is smoked over oak with an in-house rub until tender. Hands down the best smoked turkey in Charlottesville.' ),
				array( 'Smoked Pulled BBQ Chicken', '', '', array(), array(), 'Seasoned with our in-house rub, smoked over oak and hickory, and pulled with our house-made Twisted Red BBQ sauce.' ),
				array( 'St Louis Cut Ribs', '', '', array(), array( 'featured' ), 'St. Louis ribs smoked low and slow over oak and hickory. Served as a full rack or hand-cut into single-bone “party ribs,” and finished in our signature sweet heat glaze.' ),
				array( 'Texas Style Pork Belly', '', '', array(), array( 'premium' ), 'Seasoned with our house brisket rub and smoked over oak. Finished to tender perfection.' ),
				array( 'Smoked Tofu Burnt Ends', '', '', array( 'vegetarian', 'vegan' ), array(), 'Slow-smoked cubes of extra-firm tofu, seasoned with our house rub and finished low and slow until they’re deeply caramelized.' ),
				array( 'Smoked Salmon', '', '', array(), array( 'premium' ), 'Maple-brined for 12 hours, smoked low and slow over cherry wood, and finished with an orange and maple glaze.' ),
			),
			'sides'    => array(
				array( 'Barbecue Pit Beans', '', '', array(), array(), 'Smoked over oak wood, made with onion, jalapeño and our house-made BBQ sauce. Just the right amount of sweetness and heat.' ),
				array( 'White Cheddar Mac and Cheese', '', '', array(), array(), 'Creamy white cheddar and parmesan. Comfort food at its best.' ),
				array( 'Esquites - Mexican Street Corn Salad', '', '', array(), array(), 'Creamy, tangy and savory. Fire-roasted corn with crema, cotija cheese, lime juice and chili powder. Street corn without the mess!' ),
				array( 'Loaded Potato Salad', '', '', array(), array(), 'Creamy potato salad that captures the flavors of a loaded baked potato: sour cream, chives and bacon. This dish can easily be made vegetarian.' ),
				array( 'Southern Sweet Slaw', '', '', array(), array(), 'A recipe straight from Mom, featuring fresh cabbage, carrots and onion.' ),
				array( 'Sweet Cornbread', '', '', array(), array(), 'Golden baked sweet cornbread with a hot honey drizzle, a perfect side for any barbecue event.' ),
				array( 'Watermelon and Feta Salad', '', '', array(), array( 'seasonal' ), 'Cold, crisp watermelon with salty feta, fresh mint and a light citrus kick. The perfect refreshing side next to smoky barbecue.' ),
			),
			'desserts' => array(
				array( 'Banana Pudding', '', '', array(), array(), 'Made like it should be: light, whipped, with freshly made custard. Perfect for any event.' ),
				array( 'Southern Apple Crisp', '', '', array(), array(), 'Warm cinnamon-spiced apples baked with a buttery oat crumble.' ),
			),
		);

		if ( ! $keep ) {
			// Remove legacy (non-seeded) posts first so seeded slugs don't get "-2" suffixes.
			$legacy = get_posts(
				array(
					'post_type'   => 'tobbq_menu_item',
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => '_tobbq_seed_key',
							'compare' => 'NOT EXISTS',
						),
					),
				)
			);
			foreach ( $legacy as $id ) {
				wp_delete_post( (int) $id, true );
			}
			WP_CLI::log( sprintf( 'Deleted %d legacy menu items.', count( $legacy ) ) );
		}

		// --- Categories ---
		$term_ids = array();
		foreach ( $categories as $slug => [ $name, $order, $desc ] ) {
			$term = get_term_by( 'slug', $slug, 'tobbq_menu_category' );
			if ( $term instanceof WP_Term ) {
				wp_update_term( $term->term_id, 'tobbq_menu_category', array( 'name' => $name, 'description' => $desc ) );
				$term_ids[ $slug ] = (int) $term->term_id;
			} else {
				$res = wp_insert_term( $name, 'tobbq_menu_category', array( 'slug' => $slug, 'description' => $desc ) );
				if ( is_wp_error( $res ) ) {
					WP_CLI::error( $res->get_error_message() );
				}
				$term_ids[ $slug ] = (int) $res['term_id'];
			}
			update_term_meta( $term_ids[ $slug ], 'order', $order );
		}

		$diet_ids = array();
		foreach ( array( 'vegan' => 'Vegan', 'vegetarian' => 'Vegetarian', 'gluten-free' => 'Gluten-Free', 'dairy-free' => 'Dairy-Free', 'contains-pork' => 'Contains Pork', 'contains-nuts' => 'Contains Nuts' ) as $slug => $name ) {
			$term = get_term_by( 'slug', $slug, 'tobbq_dietary' );
			if ( ! $term ) {
				$res  = wp_insert_term( $name, 'tobbq_dietary', array( 'slug' => $slug ) );
				$term = is_wp_error( $res ) ? null : get_term( (int) $res['term_id'] );
			}
			if ( $term instanceof WP_Term ) {
				$diet_ids[ $slug ] = (int) $term->term_id;
			}
		}

		// --- Items ---
		$counts = array( 'created' => 0, 'updated' => 0 );
		$seeded = array();
		foreach ( $items as $cat_slug => $list ) {
			foreach ( $list as $i => [ $name, $price, $unit, $diet, $flags, $desc ] ) {
				$key  = 'menu:' . sanitize_title( $name );
				$meta = array(
					'_tobbq_seed_key'      => $key,
					'_tobbq_price'         => $price,
					'_tobbq_unit'          => $unit,
					'_tobbq_featured'      => in_array( 'featured', $flags, true ),
					'_tobbq_sold_out'      => in_array( 'sold_out', $flags, true ),
					'_tobbq_premium'       => in_array( 'premium', $flags, true ),
					'_tobbq_catering_only' => in_array( 'catering_only', $flags, true ),
					'_tobbq_seasonal'      => in_array( 'seasonal', $flags, true ),
					'_tobbq_square_id'     => '',
				);
				$id       = $this->upsert(
					'tobbq_menu_item',
					$key,
					array(
						'post_title'   => $name,
						'post_name'    => sanitize_title( $name ),
						'post_excerpt' => $desc,
						'post_content' => '<!-- wp:paragraph --><p>' . esc_html( $desc ) . '</p><!-- /wp:paragraph -->',
						'menu_order'   => ( $i + 1 ) * 10,
					),
					$meta,
					$counts
				);
				$seeded[] = $id;
				wp_set_object_terms( $id, array( $term_ids[ $cat_slug ] ), 'tobbq_menu_category' );
				wp_set_object_terms( $id, array_values( array_intersect_key( $diet_ids, array_flip( $diet ) ) ), 'tobbq_dietary' );
			}
		}
		WP_CLI::log( sprintf( 'Menu items: %d created, %d updated.', $counts['created'], $counts['updated'] ) );

		// --- Events ---
		// Deliberately not seeded: the live site lists no events, so invented placeholders are not created.
		// Events are client-managed content; this command never creates, edits or deletes tobbq_event posts.


		if ( ! $keep ) {
			$removed = 0;
			foreach ( array( 'tobbq_menu_item' => $seeded ) as $type => $keep_ids ) {
				$stale = get_posts(
					array(
						'post_type'   => $type,
						'post_status' => 'any',
						'numberposts' => -1,
						'fields'      => 'ids',
						'exclude'     => $keep_ids,
					)
				);
				foreach ( $stale as $id ) {
					wp_delete_post( (int) $id, true );
					++$removed;
				}
			}
			$terms = get_terms(
				array(
					'taxonomy'   => 'tobbq_menu_category',
					'hide_empty' => false,
					'exclude'    => array_values( $term_ids ),
				)
			);
			foreach ( is_array( $terms ) ? $terms : array() as $t ) {
				wp_delete_term( $t->term_id, 'tobbq_menu_category' );
				++$removed;
			}
			WP_CLI::log( "Removed {$removed} non-seeded posts/categories." );
		}

		\TwistedOak\Core\Menu\source( true );
		WP_CLI::success( 'Catering menu ready.' );
	}

	/**
	 * Sync the Square catalog into the CPT (stub until live requests are enabled).
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report what would change.
	 *
	 * @subcommand sync-square
	 *
	 * @param array<int, string>    $args       Positional.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function sync_square( array $args, array $assoc_args ): void {
		$res = \TwistedOak\Core\Menu\SquareSync::run( ! empty( $assoc_args['dry-run'] ) );
		if ( is_wp_error( $res ) ) {
			WP_CLI::warning( $res->get_error_message() );
			return;
		}
		WP_CLI::success( sprintf( 'Created %d, updated %d, drafted %d.', $res['created'], $res['updated'], $res['drafted'] ) );
	}

	/**
	 * Print the active menu source's DTOs as JSON.
	 *
	 * ## OPTIONS
	 *
	 * [--category=<slug>]
	 * : Limit to one category.
	 *
	 * [--groups]
	 * : Print groups (category + items) instead.
	 *
	 * @param array<int, string>    $args       Positional.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function dump( array $args, array $assoc_args ): void {
		$src = \TwistedOak\Core\Menu\source();
		$out = ! empty( $assoc_args['groups'] ) ? \TwistedOak\Core\Menu\groups( $src ) : $src->items( $assoc_args['category'] ?? null );
		WP_CLI::line( (string) wp_json_encode( array( 'source' => $src->id(), 'data' => $out ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Create or update a post keyed by _tobbq_seed_key.
	 *
	 * @param array<string, mixed> $postarr Post fields.
	 * @param array<string, mixed> $meta    Meta.
	 * @param array<string, int>   $counts  Counters (by ref).
	 */
	private function upsert( string $type, string $key, array $postarr, array $meta, array &$counts ): int {
		$found = get_posts(
			array(
				'post_type'   => $type,
				'post_status' => 'any',
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_key'    => '_tobbq_seed_key', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$postarr = array_merge(
			$postarr,
			array(
				'post_type'   => $type,
				'post_status' => 'publish',
				'meta_input'  => $meta,
			)
		);
		if ( $found ) {
			$postarr['ID'] = (int) $found[0];
			$id            = wp_update_post( $postarr, true );
			++$counts['updated'];
		} else {
			$id = wp_insert_post( $postarr, true );
			++$counts['created'];
		}
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( $id->get_error_message() );
		}
		return (int) $id;
	}
}

WP_CLI::add_command( 'twisted-oak-menu', 'TwistedOak_Menu_CLI' );
