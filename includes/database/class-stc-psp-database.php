<?php
/**
 * Database installer + low level table name helpers.
 *
 * @package STC_Product_Showcase_Pro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STC_PSP_Database
 *
 * Creates and upgrades the custom tables used by the plugin.
 */
class STC_PSP_Database {

	/**
	 * Get the fully-qualified enquiries table name.
	 */
	public static function enquiries_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'stc_psp_enquiries';
	}

	/**
	 * Get the fully-qualified downloads table name.
	 */
	public static function downloads_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'stc_psp_downloads';
	}

	/**
	 * Get the fully-qualified templates table name.
	 */
	public static function templates_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'stc_psp_templates';
	}

	/**
	 * Create or upgrade the database schema using dbDelta.
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$enquiries       = self::enquiries_table();
		$downloads       = self::downloads_table();
		$templates       = self::templates_table();

		$schema = array();

		$schema[] = "CREATE TABLE {$enquiries} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			product_name VARCHAR(255) NOT NULL DEFAULT '',
			product_sku VARCHAR(100) NOT NULL DEFAULT '',
			product_category VARCHAR(255) NOT NULL DEFAULT '',
			product_url VARCHAR(255) NOT NULL DEFAULT '',
			customer_name VARCHAR(191) NOT NULL DEFAULT '',
			customer_email VARCHAR(191) NOT NULL DEFAULT '',
			customer_mobile VARCHAR(60) NOT NULL DEFAULT '',
			customer_company VARCHAR(191) NOT NULL DEFAULT '',
			customer_city VARCHAR(120) NOT NULL DEFAULT '',
			customer_country VARCHAR(120) NOT NULL DEFAULT '',
			customer_industry VARCHAR(191) NOT NULL DEFAULT '',
			message LONGTEXT NULL,
			extra_fields LONGTEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'new',
			ip_address VARCHAR(100) NOT NULL DEFAULT '',
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY status (status),
			KEY created_at (created_at),
			KEY customer_email (customer_email)
		) {$charset_collate};";

		$schema[] = "CREATE TABLE {$downloads} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			product_name VARCHAR(255) NOT NULL DEFAULT '',
			file_url VARCHAR(255) NOT NULL DEFAULT '',
			file_name VARCHAR(255) NOT NULL DEFAULT '',
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			ip_address VARCHAR(100) NOT NULL DEFAULT '',
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			referrer VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		$schema[] = "CREATE TABLE {$templates} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			name VARCHAR(191) NOT NULL DEFAULT '',
			slug VARCHAR(191) NOT NULL DEFAULT '',
			is_global TINYINT(1) NOT NULL DEFAULT 0,
			settings LONGTEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY is_global (is_global)
		) {$charset_collate};";

		foreach ( $schema as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * Drop all custom tables (used by uninstall when configured).
	 */
	public static function drop_tables(): void {
		global $wpdb;

		foreach ( array( self::enquiries_table(), self::downloads_table(), self::templates_table() ) as $table ) {
			// Table name is internal, cannot be parameterised.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}
}
