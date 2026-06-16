<?php
/**
 * Download tracking data access object.
 *
 * @package STC_Product_Showcase_Pro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STC_PSP_Download_Repository
 */
class STC_PSP_Download_Repository {

	/**
	 * Record a download event.
	 *
	 * @param array<string,mixed> $data Download data.
	 * @return int Inserted row ID.
	 */
	public static function insert( array $data ): int {
		global $wpdb;

		$row = array(
			'created_at'   => current_time( 'mysql' ),
			'product_id'   => absint( $data['product_id'] ?? 0 ),
			'product_name' => sanitize_text_field( (string) ( $data['product_name'] ?? '' ) ),
			'file_url'     => esc_url_raw( (string) ( $data['file_url'] ?? '' ) ),
			'file_name'    => sanitize_text_field( (string) ( $data['file_name'] ?? '' ) ),
			'user_id'      => absint( $data['user_id'] ?? 0 ),
			'ip_address'   => sanitize_text_field( (string) ( $data['ip_address'] ?? '' ) ),
			'user_agent'   => sanitize_text_field( (string) ( $data['user_agent'] ?? '' ) ),
			'referrer'     => esc_url_raw( (string) ( $data['referrer'] ?? '' ) ),
		);

		$formats = array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' );

		$result = $wpdb->insert( STC_PSP_Database::downloads_table(), $row, $formats );

		return $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Query download records with pagination.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public static function query( array $args = array() ): array {
		global $wpdb;
		$table = STC_PSP_Database::downloads_table();

		$defaults = array(
			'product_id' => 0,
			'per_page'   => 20,
			'page'       => 1,
			'order'      => 'DESC',
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = 'WHERE 1=1';
		$params = array();

		if ( (int) $args['product_id'] > 0 ) {
			$where   .= ' AND product_id = %d';
			$params[] = (int) $args['product_id'];
		}

		$order    = strtoupper( (string) $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = max( 0, ( (int) $args['page'] - 1 ) * $per_page );

		$count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
		$total     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) // phpcs:ignore WordPress.DB
			: $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB

		$list_sql    = "SELECT * FROM {$table} {$where} ORDER BY created_at {$order} LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, array( $per_page, $offset ) );
		$items       = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ), ARRAY_A ); // phpcs:ignore WordPress.DB

		return array(
			'items' => $items ?: array(),
			'total' => $total,
		);
	}

	/**
	 * Total downloads for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return int
	 */
	public static function count_for_product( int $product_id ): int {
		global $wpdb;
		$table = STC_PSP_Database::downloads_table();

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE product_id = %d", $product_id ) // phpcs:ignore WordPress.DB
		);
	}

	/**
	 * Aggregate top downloaded products.
	 *
	 * @param int $limit Number of rows.
	 * @return array<int,array<string,mixed>>
	 */
	public static function top_products( int $limit = 10 ): array {
		global $wpdb;
		$table = STC_PSP_Database::downloads_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id, product_name, COUNT(*) AS total FROM {$table} GROUP BY product_id, product_name ORDER BY total DESC LIMIT %d", // phpcs:ignore WordPress.DB
				$limit
			),
			ARRAY_A
		);

		return $rows ?: array();
	}
}
