<?php
/**
 * Uninstall routine for STC Product Showcase Pro.
 *
 * Runs only when the plugin is deleted from the WordPress admin.
 * Removes plugin data when the "delete data on uninstall" preference is enabled.
 *
 * @package STC_Product_Showcase_Pro
 */

// Exit if not called by WordPress during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Whether the site owner asked us to purge all data.
 * Defaults to keeping data (safer for accidental deletes).
 */
$settings    = get_option( 'stc_psp_settings', array() );
$purge_data  = is_array( $settings ) && isset( $settings['purge_on_uninstall'] ) && 'yes' === $settings['purge_on_uninstall'];

// Always remove the version/option scaffolding.
delete_option( 'stc_psp_db_version' );

if ( $purge_data ) {
	global $wpdb;

	// Drop custom tables.
	$tables = array(
		$wpdb->prefix . 'stc_psp_enquiries',
		$wpdb->prefix . 'stc_psp_downloads',
		$wpdb->prefix . 'stc_psp_templates',
	);
	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	// Remove plugin options.
	delete_option( 'stc_psp_settings' );

	// Remove product meta added by the plugin.
	$meta_keys = array(
		'_stc_psp_pdf_id',
		'_stc_psp_pdf_url',
		'_stc_psp_pdf_source',
		'_stc_psp_brand',
		'_stc_psp_features',
		'_stc_psp_specifications',
		'_stc_psp_applications',
		'_stc_psp_industries',
		'_stc_psp_downloads',
		'_stc_psp_certificates',
		'_stc_psp_approvals',
		'_stc_psp_download_count',
	);
	foreach ( $meta_keys as $meta_key ) {
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $meta_key ), array( '%s' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}
}
