<?php
/**
 * Product query builder for the showcase widget.
 *
 * Designed for large catalogues (50k+ products): uses WP_Query with
 * 'no_found_rows' tuning, update caches, and avoids per-item meta queries
 * that would cause N+1 problems.
 *
 * @package STC_Product_Showcase_Pro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STC_PSP_Query
 */
class STC_PSP_Query {

	/**
	 * Build WP_Query arguments from showcase settings.
	 *
	 * @param array<string,mixed> $settings Normalised widget settings.
	 * @param int                 $paged    Page number.
	 * @return array<string,mixed>
	 */
	public static function build_args( array $settings, int $paged = 1 ): array {
		$per_page = max( 1, (int) ( $settings['per_page'] ?? 6 ) );
		$source   = (string) ( $settings['source'] ?? 'latest' );
		$orderby  = (string) ( $settings['orderby'] ?? 'date' );
		$order    = strtoupper( (string) ( $settings['order'] ?? 'DESC' ) ) === 'ASC' ? 'ASC' : 'DESC';

		$args = array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => $per_page,
			'paged'                  => max( 1, $paged ),
			'orderby'                => 'date',
			'order'                  => $order,
			'ignore_sticky_posts'    => true,
			// Performance: only prime the caches we actually use.
			'no_found_rows'          => false, // We need pagination totals.
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
			'tax_query'              => array( 'relation' => 'AND' ),
			'meta_query'             => array( 'relation' => 'AND' ),
		);

		// Only show products that are in stock if requested.
		if ( ! empty( $settings['hide_out_of_stock'] ) && 'yes' === $settings['hide_out_of_stock'] ) {
			$args['meta_query'][] = array(
				'key'     => '_stock_status',
				'value'   => 'instock',
				'compare' => '=',
			);
		}

		self::apply_orderby( $args, $orderby );
		self::apply_source( $args, $settings, $source );

		/**
		 * Filter the final query args.
		 *
		 * @param array $args     WP_Query args.
		 * @param array $settings Widget settings.
		 */
		return apply_filters( 'stc_psp_query_args', $args, $settings );
	}

	/**
	 * Apply ordering rules.
	 *
	 * @param array<string,mixed> $args    Query args (by reference).
	 * @param string              $orderby Orderby key.
	 */
	private static function apply_orderby( array &$args, string $orderby ): void {
		switch ( $orderby ) {
			case 'title':
				$args['orderby'] = 'title';
				break;
			case 'menu_order':
				$args['orderby'] = 'menu_order';
				break;
			case 'rand':
				$args['orderby'] = 'rand';
				break;
			case 'price':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				break;
			case 'popularity':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				break;
			case 'rating':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_wc_average_rating'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				break;
			case 'date':
			default:
				$args['orderby'] = 'date';
				break;
		}
	}

	/**
	 * Apply the product source / filtering strategy.
	 *
	 * @param array<string,mixed> $args     Query args (by reference).
	 * @param array<string,mixed> $settings Widget settings.
	 * @param string              $source   Source key.
	 */
	private static function apply_source( array &$args, array $settings, string $source ): void {
		switch ( $source ) {
			case 'featured':
				$args['tax_query'][] = array(
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => 'featured',
					'operator' => 'IN',
				);
				break;

			case 'best_selling':
				$args['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;

			case 'manual':
				$ids = self::to_int_array( $settings['product_ids'] ?? array() );
				if ( empty( $ids ) ) {
					$ids = array( 0 ); // Force no results.
				}
				$args['post__in'] = $ids;
				$args['orderby']  = 'post__in';
				break;

			case 'sku':
				$skus = self::to_string_array( $settings['skus'] ?? '' );
				$ids  = self::ids_from_skus( $skus );
				$args['post__in'] = $ids ?: array( 0 );
				break;

			case 'category':
			case 'subcategory':
				$cats = self::to_int_array( $settings['categories'] ?? array() );
				if ( ! empty( $cats ) ) {
					$args['tax_query'][] = array(
						'taxonomy'         => 'product_cat',
						'field'            => 'term_id',
						'terms'            => $cats,
						'include_children' => 'subcategory' !== $source,
					);
				}
				break;

			case 'brand':
				$brands   = self::to_int_array( $settings['brands'] ?? array() );
				$taxonomy = self::detect_brand_taxonomy();
				if ( ! empty( $brands ) && $taxonomy ) {
					$args['tax_query'][] = array(
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => $brands,
					);
				}
				break;

			case 'tags':
				$tags = self::to_int_array( $settings['tags'] ?? array() );
				if ( ! empty( $tags ) ) {
					$args['tax_query'][] = array(
						'taxonomy' => 'product_tag',
						'field'    => 'term_id',
						'terms'    => $tags,
					);
				}
				break;

			case 'latest':
			default:
				// Default date ordering already set.
				break;
		}
	}

	/**
	 * Detect the active brand taxonomy.
	 *
	 * @return string Empty string if none found.
	 */
	public static function detect_brand_taxonomy(): string {
		foreach ( array( 'product_brand', 'pwb-brand', 'pa_brand' ) as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				return $taxonomy;
			}
		}

		return '';
	}

	/**
	 * Resolve product IDs from a list of SKUs in a single query (no N+1).
	 *
	 * @param array<int,string> $skus SKUs.
	 * @return array<int,int>
	 */
	private static function ids_from_skus( array $skus ): array {
		global $wpdb;
		if ( empty( $skus ) ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $skus ), '%s' ) );
		$sql          = "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value IN ({$placeholders})";

		$ids = $wpdb->get_col( $wpdb->prepare( $sql, $skus ) ); // phpcs:ignore WordPress.DB

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Run the query.
	 *
	 * @param array<string,mixed> $settings Widget settings.
	 * @param int                 $paged    Page number.
	 * @return WP_Query
	 */
	public static function run( array $settings, int $paged = 1 ): WP_Query {
		return new WP_Query( self::build_args( $settings, $paged ) );
	}

	/* ------------------------------------------------------------------ */

	/**
	 * Coerce mixed input into an array of ints.
	 *
	 * @param mixed $value Input.
	 * @return array<int,int>
	 */
	private static function to_int_array( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[,\s]+/', $value );
		}
		$value = array_map( 'intval', (array) $value );

		return array_values( array_filter( $value ) );
	}

	/**
	 * Coerce mixed input into an array of trimmed strings.
	 *
	 * @param mixed $value Input.
	 * @return array<int,string>
	 */
	private static function to_string_array( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[,\n\r]+/', $value );
		}
		$value = array_map( 'trim', (array) $value );

		return array_values( array_filter( $value ) );
	}
}
