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
		$this->send_emails( $data );

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
			)
		);
	}

	/**
	 * Send the admin (and optional customer) notification emails.
	 *
	 * Uses wp_mail() so it is fully SMTP-plugin compatible.
	 *
	 * @param array<string,mixed> $data Enquiry data.
	 */
	private function send_emails( array $data ): void {
		$to      = STC_PSP_Settings::get( 'admin_email', get_option( 'admin_email' ) );
		$subject = (string) STC_PSP_Settings::get( 'email_subject', __( 'New Product Enquiry', 'stc-product-showcase-pro' ) );

		$from_name  = (string) STC_PSP_Settings::get( 'from_name', get_bloginfo( 'name' ) );
		$from_email = (string) STC_PSP_Settings::get( 'from_email', get_option( 'admin_email' ) );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
		);

		if ( ! empty( $data['customer_email'] ) && is_email( $data['customer_email'] ) ) {
			$headers[] = 'Reply-To: ' . $data['customer_email'];
		}

		$cc = (string) STC_PSP_Settings::get( 'email_cc', '' );
		if ( '' !== $cc ) {
			$headers[] = 'Cc: ' . $cc;
		}

		$body = $this->build_email_body( $data );

		wp_mail( $to, $subject, $body, $headers );

		// Optional acknowledgement to the customer.
		if ( 'yes' === STC_PSP_Settings::get( 'send_copy_to_user', 'no' )
			&& ! empty( $data['customer_email'] )
			&& is_email( $data['customer_email'] ) ) {
			$ack_subject = sprintf(
				/* translators: %s: site name. */
				__( 'We received your enquiry – %s', 'stc-product-showcase-pro' ),
				get_bloginfo( 'name' )
			);
			wp_mail(
				$data['customer_email'],
				$ack_subject,
				$this->build_email_body( $data, true ),
				array( 'Content-Type: text/html; charset=UTF-8', sprintf( 'From: %s <%s>', $from_name, $from_email ) )
			);
		}
	}

	/**
	 * Build the HTML email body.
	 *
	 * @param array<string,mixed> $data        Enquiry data.
	 * @param bool                $is_customer Whether this is the customer copy.
	 * @return string
	 */
	private function build_email_body( array $data, bool $is_customer = false ): string {
		$rows = array(
			__( 'Product Name', 'stc-product-showcase-pro' ) => $data['product_name'] ?? '',
			__( 'SKU', 'stc-product-showcase-pro' )          => $data['product_sku'] ?? '',
			__( 'Category', 'stc-product-showcase-pro' )     => $data['product_category'] ?? '',
			__( 'Product URL', 'stc-product-showcase-pro' )  => $data['product_url'] ?? '',
			__( 'Name', 'stc-product-showcase-pro' )         => $data['customer_name'] ?? '',
			__( 'Email', 'stc-product-showcase-pro' )        => $data['customer_email'] ?? '',
			__( 'Mobile', 'stc-product-showcase-pro' )       => $data['customer_mobile'] ?? '',
			__( 'Company', 'stc-product-showcase-pro' )      => $data['customer_company'] ?? '',
			__( 'City', 'stc-product-showcase-pro' )         => $data['customer_city'] ?? '',
			__( 'Country', 'stc-product-showcase-pro' )      => $data['customer_country'] ?? '',
			__( 'Industry', 'stc-product-showcase-pro' )     => $data['customer_industry'] ?? '',
			__( 'Message', 'stc-product-showcase-pro' )      => $data['message'] ?? '',
		);

		foreach ( (array) ( $data['extra_fields'] ?? array() ) as $label => $value ) {
			$rows[ $label ] = $value;
		}

		$heading = $is_customer
			? __( 'Thank you for your enquiry', 'stc-product-showcase-pro' )
			: __( 'New Product Enquiry', 'stc-product-showcase-pro' );

		ob_start();
		echo '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;">';
		echo '<h2 style="background:#0b5cab;color:#fff;padding:16px;border-radius:6px 6px 0 0;margin:0;">' . esc_html( $heading ) . '</h2>';
		echo '<table style="width:100%;border-collapse:collapse;border:1px solid #e2e2e2;">';
		foreach ( $rows as $label => $value ) {
			if ( '' === trim( (string) $value ) ) {
				continue;
			}
			printf(
				'<tr><td style="padding:10px;border:1px solid #e2e2e2;background:#f7f7f7;font-weight:bold;width:35%%;">%s</td><td style="padding:10px;border:1px solid #e2e2e2;">%s</td></tr>',
				esc_html( (string) $label ),
				esc_html( (string) $value )
			);
		}
		echo '</table>';
		echo '<p style="color:#888;font-size:12px;padding:12px;">' . esc_html__( 'Sent by STC Product Showcase Pro', 'stc-product-showcase-pro' ) . '</p>';
		echo '</div>';

		return (string) ob_get_clean();
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
