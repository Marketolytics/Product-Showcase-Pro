<?php
/**
 * Product card template data access object.
 *
 * @package STC_Product_Showcase_Pro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STC_PSP_Template_Repository
 *
 * Stores reusable showcase card templates (import/export/global).
 */
class STC_PSP_Template_Repository {

	/**
	 * Create or update a template.
	 *
	 * @param array<string,mixed> $data Template payload.
	 * @return int Template ID.
	 */
	public static function save( array $data ): int {
		global $wpdb;
		$table = STC_PSP_Database::templates_table();

		$name = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
		$slug = sanitize_title( (string) ( $data['slug'] ?? $name ) );
		$now  = current_time( 'mysql' );

		$row = array(
			'updated_at' => $now,
			'name'       => $name,
			'slug'       => $slug,
			'is_global'  => ! empty( $data['is_global'] ) ? 1 : 0,
			'settings'   => wp_json_encode( $data['settings'] ?? array() ),
		);

		$id = (int) ( $data['id'] ?? 0 );

		if ( $id > 0 ) {
			$wpdb->update(
				$table,
				$row,
				array( 'id' => $id ),
				array( '%s', '%s', '%s', '%d', '%s' ),
				array( '%d' )
			);
			return $id;
		}

		$row['created_at'] = $now;
		$wpdb->insert(
			$table,
			$row,
			array( '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get a template by ID.
	 *
	 * @param int $id Template ID.
	 * @return array<string,mixed>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = STC_PSP_Database::templates_table();

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), // phpcs:ignore WordPress.DB
			ARRAY_A
		);

		if ( $row ) {
			$row['settings'] = json_decode( (string) $row['settings'], true ) ?: array();
		}

		return $row ?: null;
	}

	/**
	 * Get all templates.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function all(): array {
		global $wpdb;
		$table = STC_PSP_Database::templates_table();

		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( (array) $rows as &$row ) {
			$row['settings'] = json_decode( (string) $row['settings'], true ) ?: array();
		}
		unset( $row );

		return $rows ?: array();
	}

	/**
	 * Delete a template.
	 *
	 * @param int $id Template ID.
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		return false !== $wpdb->delete( STC_PSP_Database::templates_table(), array( 'id' => $id ), array( '%d' ) );
	}
}
