<?php
/**
 * AJAX: enquiry form submission + email notification.
 *
 * @package STC_Product_Showcase_Pro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STC_PSP_Ajax_Enquiry
 */
class STC_PSP_Ajax_Enquiry {

	const NONCE_ACTION = 'stc_psp_enquiry';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_stc_psp_submit_enquiry', array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_stc_psp_submit_enquiry', array( $this, 'handle' ) );
	}

	/**
	 * Handle an enquiry submission.
	 */
	public function handle(): void {
		// Verify nonce.
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'stc_psp_enquiry_nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload the page.', 'stc-product-showcase-pro' ) ), 403 );
		}

		// Honeypot: silently accept but discard bots.
		if ( ! empty( $_POST['stc_psp_hp'] ) ) {
			wp_send_json_success( array( 'message' => STC_PSP_Settings::get( 'popup_success_msg' ) ) );
		}

		$raw_fields = isset( $_POST['stc_psp_field'] ) && is_array( $_POST['stc_psp_field'] )
			? wp_unslash( $_POST['stc_psp_field'] )
			: array();

		$fields = STC_PSP_Form_Manager::get_enabled_fields();
		$data   = array(
			'extra_fields' => array(),
		);
		$errors = array();

		foreach ( $fields as $field ) {
			$key   = (string) $field['key'];
			$type  = (string) $field['type'];
			$label = (string) $field['label'];
			$value = $raw_fields[ $key ] ?? '';

			// Sanitise per type.
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'sanitize_text_field', $value ) );
			} elseif ( 'email' === $type ) {
				$value = sanitize_email( (string) $value );
			} elseif ( 'textarea' === $type ) {
				$value = sanitize_textarea_field( (string) $value );
			} else {
				$value = sanitize_text_field( (string) $value );
			}

			// Required validation.
			if ( ! empty( $field['required'] ) && '' === trim( (string) $value ) ) {
				/* translators: %s: field label. */
				$errors[] = sprintf( __( '%s is required.', 'stc-product-showcase-pro' ), $label );
				continue;
			}

			if ( 'email' === $type && '' !== $value && ! is_email( $value ) ) {
				$errors[] = __( 'Please enter a valid email address.', 'stc-product-showcase-pro' );
			}

			// Map reserved keys to columns, everything else into extra_fields.
			if ( in_array( $key, STC_PSP_Form_Manager::reserved_keys(), true ) ) {
				$data[ $key ] = $value;
			} else {
				$data['extra_fields'][ $label ] = $value;
			}
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'message' => implode( ' ', $errors ) ), 422 );
		}

		// Auto product data.
		$product_id            = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$data['product_id']    = $product_id;
		$data['product_name']  = isset( $_POST['product_name'] ) ? sanitize_text_field( wp_unslash( $_POST['product_name'] ) ) : '';
		$data['product_sku']   = isset( $_POST['product_sku'] ) ? sanitize_text_field( wp_unslash( $_POST['product_sku'] ) ) : '';
		$data['product_category'] = isset( $_POST['product_category'] ) ? sanitize_text_field( wp_unslash( $_POST['product_category'] ) ) : '';
		$data['product_url']   = isset( $_POST['product_url'] ) ? esc_url_raw( wp_unslash( $_POST['product_url'] ) ) : '';

		// If a real product exists, trust server-side values over posted ones.
		if ( $product_id ) {
			$payload                  = STC_PSP_Enquiry_System::product_payload( $product_id );
			$data['product_name']     = $payload['product_name'] ?: $data['product_name'];
			$data['product_sku']      = $payload['product_sku'] ?: $data['product_sku'];
			$data['product_category'] = $payload['product_category'] ?: $data['product_category'];
			$data['product_url']      = $payload['product_url'] ?: $data['product_url'];
		}

		$data['status']     = 'new';
		$data['ip_address'] = $this->get_ip();
		$data['user_agent'] = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$id = STC_PSP_Enquiry_Repository::insert( $data );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Could not save your enquiry. Please try again.', 'stc-product-showcase-pro' ) ), 500 );
		}

		$data['id'] = $id;
		$email_sent = STC_PSP_Mailer::send_admin_notification( $data );
		STC_PSP_Mailer::send_customer_ack( $data );

		/**
		 * Fires after an enquiry has been stored and notification sent.
		 *
		 * @param int   $id   Enquiry ID.
		 * @param array $data Enquiry data.
		 */
		do_action( 'stc_psp_enquiry_submitted', $id, $data );

		wp_send_json_success(
			array(
				'message'    => STC_PSP_Settings::get( 'popup_success_msg' ),
				'enquiry_id' => $id,
				'email_sent' => $email_sent,
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
