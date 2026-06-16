<?php
/**
 * Enquiry data access object.
 *
 * @package STC_Product_Showcase_Pro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STC_PSP_Enquiry_Repository
 *
 * All access to the enquiries table goes through here using prepared statements.
 */
class STC_PSP_Enquiry_Repository {

	/**
	 * Insert a new enquiry record.
	 *
	 * @param array<string,mixed> $data Sanitised enquiry data.
	 * @return int Inserted row ID (0 on failure).
	 */
	public static function insert( array $data ): int {
		global $wpdb;

		$row = array(
			'created_at'        => current_time( 'mysql' ),
			'product_id'        => absint( $data['product_id'] ?? 0 ),
			'product_name'      => sanitize_text_field( (string) ( $data['product_name'] ?? '' ) ),
			'product_sku'       => sanitize_text_field( (string) ( $data['product_sku'] ?? '' ) ),
			'product_category'  => sanitize_text_field( (string) ( $data['product_category'] ?? '' ) ),
			'product_url'       => esc_url_raw( (string) ( $data['product_url'] ?? '' ) ),
			'customer_name'     => sanitize_text_field( (string) ( $data['customer_name'] ?? '' ) ),
			'customer_email'    => sanitize_email( (string) ( $data['customer_email'] ?? '' ) ),
			'customer_mobile'   => sanitize_text_field( (string) ( $data['customer_mobile'] ?? '' ) ),
			'customer_company'  => sanitize_text_field( (string) ( $data['customer_company'] ?? '' ) ),
			'customer_city'     => sanitize_text_field( (string) ( $data['customer_city'] ?? '' ) ),
			'customer_country'  => sanitize_text_field( (string) ( $data['customer_country'] ?? '' ) ),
			'customer_industry' => sanitize_text_field( (string) ( $data['customer_industry'] ?? '' ) ),
			'message'           => sanitize_textarea_field( (string) ( $data['message'] ?? '' ) ),
			'extra_fields'      => wp_json_encode( $data['extra_fields'] ?? array() ),
			'status'            => sanitize_key( (string) ( $data['status'] ?? 'new' ) ),
			'ip_address'        => sanitize_text_field( (string) ( $data['ip_address'] ?? '' ) ),
			'user_agent'        => sanitize_text_field( (string) ( $data['user_agent'] ?? '' ) ),
		);

		$formats = array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		$result = $wpdb->insert( STC_PSP_Database::enquiries_table(), $row, $formats );

		return $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Get a single enquiry by ID.
	 *
	 * @param int $id Enquiry ID.
	 * @return array<string,mixed>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = STC_PSP_Database::enquiries_table();

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Query enquiries with pagination and filters.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public static function query( array $args = array() ): array {
		global $wpdb;
		$table = STC_PSP_Database::enquiries_table();

		$defaults = array(
			'status'   => '',
			'search'   => '',
			'per_page' => 20,
			'page'     => 1,
			'orderby'  => 'created_at',
			'order'    => 'DESC',
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = 'WHERE 1=1';
		$params = array();

		if ( '' !== $args['status'] ) {
			$where   .= ' AND status = %s';
			$params[] = sanitize_key( (string) $args['status'] );
		}

		if ( '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where   .= ' AND (customer_name LIKE %s OR customer_email LIKE %s OR product_name LIKE %s OR product_sku LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		// Whitelist orderby/order to prevent injection.
		$allowed_orderby = array( 'id', 'created_at', 'product_name', 'customer_name', 'status' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = strtoupper( (string) $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = max( 0, ( (int) $args['page'] - 1 ) * $per_page );

		// Count total.
		$count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
		$total     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) // phpcs:ignore WordPress.DB
			: $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB

		// Fetch items.
		$list_sql        = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$list_params     = array_merge( $params, array( $per_page, $offset ) );
		$items           = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ), ARRAY_A ); // phpcs:ignore WordPress.DB

		return array(
			'items' => $items ?: array(),
			'total' => $total,
		);
	}

	/**
	 * Update the status of an enquiry.
	 *
	 * @param int    $id     Enquiry ID.
	 * @param string $status New status.
	 * @return bool
	 */
	public static function update_status( int $id, string $status ): bool {
		global $wpdb;

		return false !== $wpdb->update(
			STC_PSP_Database::enquiries_table(),
			array( 'status' => sanitize_key( $status ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete an enquiry.
	 *
	 * @param int $id Enquiry ID.
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		global $wpdb;

		return false !== $wpdb->delete( STC_PSP_Database::enquiries_table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Count enquiries grouped by status.
	 *
	 * @return array<string,int>
	 */
	public static function counts_by_status(): array {
		global $wpdb;
		$table = STC_PSP_Database::enquiries_table();

		$rows   = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB
		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (string) $row['status'] ] = (int) $row['total'];
		}

		return $counts;
	}
}
