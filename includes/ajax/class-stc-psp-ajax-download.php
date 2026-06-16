<?php
/**
 * AJAX: download tracking + counter.
 *
 * @package STC_Product_Showcase_Pro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STC_PSP_Ajax_Download
 */
class STC_PSP_Ajax_Download {

	const NONCE_ACTION = 'stc_psp_frontend';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_stc_psp_track_download', array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_stc_psp_track_download', array( $this, 'handle' ) );
	}

	/**
	 * Handle a download tracking ping.
	 */
	public function handle(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( 'yes' !== STC_PSP_Settings::get( 'track_downloads', 'yes' ) ) {
			wp_send_json_success( array( 'tracked' => false ) );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing product.', 'stc-product-showcase-pro' ) ), 400 );
		}

		$product   = wc_get_product( $product_id );
		$file_url  = STC_PSP_Product_Meta::get_pdf_url( $product_id );
		$file_name = $file_url ? wp_basename( wp_parse_url( $file_url, PHP_URL_PATH ) ?: $file_url ) : '';

		$id = STC_PSP_Download_Repository::insert(
			array(
				'product_id'   => $product_id,
				'product_name' => $product instanceof WC_Product ? $product->get_name() : '',
				'file_url'     => $file_url,
				'file_name'    => $file_name,
				'user_id'      => get_current_user_id(),
				'ip_address'   => $this->get_ip(),
				'user_agent'   => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'referrer'     => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
			)
		);

		// Maintain a fast counter on the product itself.
		$count = (int) get_post_meta( $product_id, STC_PSP_Product_Meta::META['download_count'], true );
		update_post_meta( $product_id, STC_PSP_Product_Meta::META['download_count'], $count + 1 );

		/**
		 * Fires after a download has been tracked.
		 *
		 * @param int $product_id Product ID.
		 * @param int $id         Download row ID.
		 */
		do_action( 'stc_psp_download_tracked', $product_id, $id );

		wp_send_json_success(
			array(
				'tracked' => (bool) $id,
				'count'   => STC_PSP_Download_Repository::count_for_product( $product_id ),
			)
		);
	}

	/**
	 * Resolve a best-effort client IP.
	 *
	 * @return string
	 */
	private function get_ip(): string {
		$keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		foreach ( $keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				$ip = trim( explode( ',', $ip )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '';
	}
}
