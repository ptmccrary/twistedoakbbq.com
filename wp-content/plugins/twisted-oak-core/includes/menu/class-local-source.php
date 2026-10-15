<?php
/**
 * Menu source backed by the tobbq_menu_item CPT + tobbq_menu_category / tobbq_dietary taxonomies.
 *
 * @package TwistedOakCore
 */

declare(strict_types=1);

namespace TwistedOak\Core\Menu;

use WP_Post;
use WP_Term;

defined( 'ABSPATH' ) || exit;

final class LocalSource implements Source {

	/** @var list<array<string, mixed>>|null */
	private ?array $all_items = null;

	/** @var list<array<string, mixed>>|null */
	private ?array $all_categories = null;

	public function id(): string {
		return 'local';
	}

	public function categories(): array {
		if ( null !== $this->all_categories ) {
			return $this->all_categories;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'tobbq_menu_category',
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || ! $terms ) {
			$this->all_categories = array();
			return array();
		}

		$counts = array();
		foreach ( $this->items() as $item ) {
			$slug            = $item['category']['slug'] ?? '';
			$counts[ $slug ] = ( $counts[ $slug ] ?? 0 ) + 1;
		}

		$cats = array_map(
			static function ( WP_Term $t ) use ( $counts ): array {
				$link = get_term_link( $t );
				return array(
					'id'          => (string) $t->term_id,
					'slug'        => $t->slug,
					'name'        => html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' ),
					'description' => wp_strip_all_tags( (string) $t->description ),
					'sort'        => (int) get_term_meta( $t->term_id, 'order', true ),
					'url'         => is_wp_error( $link ) ? '' : $link,
					'square_id'   => ( (string) get_term_meta( $t->term_id, 'square_id', true ) ) ?: null,
					'count'       => $counts[ $t->slug ] ?? 0,
				);
			},
			$terms
		);
		usort( $cats, static fn( array $a, array $b ): int => ( $a['sort'] <=> $b['sort'] ) ?: strnatcasecmp( $a['name'], $b['name'] ) );

		$this->all_categories = $cats;
		return $cats;
	}

	public function items( ?string $category = null ): array {
		if ( null === $this->all_items ) {
			$posts = get_posts(
				array(
					'post_type'        => 'tobbq_menu_item',
					'post_status'      => 'publish',
					'posts_per_page'   => -1,
					'orderby'          => array(
						'menu_order' => 'ASC',
						'title'      => 'ASC',
					),
					'suppress_filters' => false,
				)
			);
			$ids = wp_list_pluck( $posts, 'ID' );
			update_object_term_cache( $ids, 'tobbq_menu_item' );
			update_meta_cache( 'post', $ids );
			$thumb_ids = array_filter( array_map( static fn( WP_Post $p ): int => (int) get_post_thumbnail_id( $p ), $posts ) );
			if ( $thumb_ids ) {
				_prime_post_caches( $thumb_ids, false, true );
			}
			$this->all_items = array_map( array( $this, 'to_dto' ), $posts );
		}

		if ( null === $category || '' === $category ) {
			return $this->all_items;
		}
		return array_values( array_filter( $this->all_items, static fn( array $i ): bool => ( $i['category']['slug'] ?? '' ) === $category ) );
	}

	public function item( int|string $id_or_slug ): ?array {
		if ( is_int( $id_or_slug ) || ctype_digit( (string) $id_or_slug ) ) {
			$post = get_post( (int) $id_or_slug );
		} else {
			$found = get_posts(
				array(
					'post_type'   => 'tobbq_menu_item',
					'name'        => sanitize_title( (string) $id_or_slug ),
					'post_status' => 'publish',
					'numberposts' => 1,
				)
			);
			$post  = $found[0] ?? null;
		}
		if ( ! $post instanceof WP_Post || 'tobbq_menu_item' !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}
		return $this->to_dto( $post );
	}

	/**
	 * Map a menu item post to the item DTO.
	 *
	 * @return array<string, mixed>
	 */
	public function to_dto( WP_Post $post ): array {
		$raw_price = trim( (string) get_post_meta( $post->ID, '_tobbq_price', true ) );
		$unit      = trim( (string) get_post_meta( $post->ID, '_tobbq_unit', true ) );
		$cents     = is_numeric( $raw_price ) ? (int) round( (float) $raw_price * 100 ) : null;
		$price     = null !== $cents ? format_price( $cents ) : $raw_price;

		$cats = get_the_terms( $post, 'tobbq_menu_category' );
		$cat  = $cats && ! is_wp_error( $cats ) ? $cats[0] : null;
		$diet = get_the_terms( $post, 'tobbq_dietary' );
		$diet = $diet && ! is_wp_error( $diet ) ? $diet : array();

		$sold_out  = (bool) get_post_meta( $post->ID, '_tobbq_sold_out', true );
		$square_id = trim( (string) get_post_meta( $post->ID, '_tobbq_square_id', true ) );
		$content   = (string) $post->post_content;

		$dto = array(
			'id'               => (string) $post->ID,
			'post_id'          => $post->ID,
			'slug'             => $post->post_name,
			'name'             => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'description'      => trim( wp_strip_all_tags( has_excerpt( $post ) ? $post->post_excerpt : strip_shortcodes( excerpt_remove_blocks( $content ) ) ) ),
			'description_html' => trim( wp_kses_post( do_blocks( $content ) ) ),
			'price_cents'      => $cents,
			'price'            => $price,
			'unit'             => $unit,
			'price_label'      => '' === $price ? '' : trim( $price . ' ' . $unit ),
			'currency'         => 'USD',
			'category'         => $cat ? array(
				'id'   => (string) $cat->term_id,
				'slug' => $cat->slug,
				'name' => html_entity_decode( $cat->name, ENT_QUOTES, 'UTF-8' ),
			) : null,
			'dietary'          => array_values(
				array_map(
					static fn( WP_Term $t ): array => array(
						'slug' => $t->slug,
						'name' => $t->name,
					),
					$diet
				)
			),
			'image'            => image_dto( (int) get_post_thumbnail_id( $post ) ),
			'available'        => ! $sold_out,
			'sold_out'         => $sold_out,
			'featured'         => (bool) get_post_meta( $post->ID, '_tobbq_featured', true ),
			'premium'          => (bool) get_post_meta( $post->ID, '_tobbq_premium', true ),
			'catering_only'    => (bool) get_post_meta( $post->ID, '_tobbq_catering_only', true ),
			'seasonal'         => (bool) get_post_meta( $post->ID, '_tobbq_seasonal', true ),
			'sort'             => (int) $post->menu_order,
			'square_id'        => '' !== $square_id ? $square_id : null,
			'url'              => (string) get_permalink( $post ),
		);

		/**
		 * Filter one item DTO built from a CPT post.
		 */
		return (array) apply_filters( 'twistedoak_menu_item_dto', $dto, $post, $this );
	}
}
