<?php
/**
 * Centralised, deliverability-friendly mailer for enquiry notifications.
 *
 * @package STC_Product_Showcase_Pro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STC_PSP_Mailer
 *
 * Handles all outbound email. Designed to maximise deliverability when no SMTP
 * plugin is configured by using a From address aligned with the site domain
 * (so SPF/DKIM pass) and the customer address as Reply-To.
 */
class STC_PSP_Mailer {

	/**
	 * Resolve the list of admin recipient addresses.
	 *
	 * Supports a comma-separated list in the setting and always falls back to
	 * the WordPress admin email so a notification is never lost to a blank field.
	 *
	 * @return array<int,string>
	 */
	public static function recipients(): array {
		$raw   = (string) STC_PSP_Settings::get( 'admin_email', '' );
		$list  = array();

		foreach ( preg_split( '/[,;]+/', $raw ) as $candidate ) {
			$candidate = trim( (string) $candidate );
			if ( '' !== $candidate && is_email( $candidate ) ) {
				$list[] = $candidate;
			}
		}

		if ( empty( $list ) ) {
			$fallback = get_option( 'admin_email' );
			if ( is_email( $fallback ) ) {
				$list[] = $fallback;
			}
		}

		return array_values( array_unique( $list ) );
	}

	/**
	 * The site domain (without www), used for default From addresses.
	 *
	 * @return string
	 */
	public static function site_domain(): string {
		$host = (string) wp_parse_url( network_home_url(), PHP_URL_HOST );
		if ( 0 === strpos( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}

		return $host ?: 'localhost';
	}

	/**
	 * Resolve the From email address.
	 *
	 * Uses the configured value only when it is valid; otherwise defaults to a
	 * domain-aligned address (wordpress@yoursite.com) so the message passes SPF
	 * and is far less likely to be rejected or marked as spam.
	 *
	 * @return string
	 */
	public static function from_email(): string {
		$domain     = self::site_domain();
		$configured = (string) STC_PSP_Settings::get( 'from_email', '' );

		// Honour an explicitly configured From only when it is on the site's own
		// domain. A cross-domain From (e.g. a Gmail address) fails SPF/DKIM and is
		// the most common reason enquiry emails are rejected or land in spam, so we
		// fall back to a domain-aligned address in that case.
		if ( '' !== $configured && is_email( $configured ) ) {
			$configured_domain = strtolower( (string) substr( strrchr( $configured, '@' ), 1 ) );
			if ( $configured_domain === strtolower( $domain ) ) {
				return $configured;
			}
		}

		return 'wordpress@' . $domain;
	}

	/**
	 * Resolve the From name.
	 *
	 * @return string
	 */
	public static function from_name(): string {
		$name = (string) STC_PSP_Settings::get( 'from_name', '' );

		return '' !== $name ? $name : (string) get_bloginfo( 'name' );
	}

	/**
	 * Build email headers.
	 *
	 * @param string $reply_to Optional Reply-To address.
	 * @param string $cc       Optional Cc list.
	 * @return array<int,string>
	 */
	public static function headers( string $reply_to = '', string $cc = '' ): array {
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', self::from_name(), self::from_email() ),
		);

		if ( '' !== $reply_to && is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		if ( '' !== $cc ) {
			$headers[] = 'Cc: ' . $cc;
		}

		return $headers;
	}

	/**
	 * Send the admin enquiry notification.
	 *
	 * @param array<string,mixed> $data Enquiry data.
	 * @return bool True if wp_mail accepted the message.
	 */
	public static function send_admin_notification( array $data ): bool {
		$to = self::recipients();
		if ( empty( $to ) ) {
			self::log( 'No valid recipient address configured; enquiry email not sent.' );
			return false;
		}

		$subject  = (string) STC_PSP_Settings::get( 'email_subject', __( 'New Product Enquiry', 'stc-product-showcase-pro' ) );
		$subject  = '' !== $subject ? $subject : __( 'New Product Enquiry', 'stc-product-showcase-pro' );
		$reply_to = (string) ( $data['customer_email'] ?? '' );
		$cc       = (string) STC_PSP_Settings::get( 'email_cc', '' );
		$headers  = self::headers( $reply_to, $cc );
		$body     = self::build_body( $data, false );

		$sent = self::dispatch( $to, $subject, $body, $headers );
		if ( ! $sent ) {
			self::log( 'wp_mail() returned false when sending the admin enquiry notification.' );
		}

		return $sent;
	}

	/**
	 * Send the optional customer acknowledgement.
	 *
	 * @param array<string,mixed> $data Enquiry data.
	 * @return bool
	 */
	public static function send_customer_ack( array $data ): bool {
		if ( 'yes' !== STC_PSP_Settings::get( 'send_copy_to_user', 'no' ) ) {
			return false;
		}

		$customer = (string) ( $data['customer_email'] ?? '' );
		if ( '' === $customer || ! is_email( $customer ) ) {
			return false;
		}

		$subject = sprintf(
			/* translators: %s: site name. */
			__( 'We received your enquiry – %s', 'stc-product-showcase-pro' ),
			get_bloginfo( 'name' )
		);

		return self::dispatch( array( $customer ), $subject, self::build_body( $data, true ), self::headers() );
	}

	/**
	 * Send a diagnostic test email to the configured recipients.
	 *
	 * @return bool
	 */
	public static function send_test(): bool {
		$to = self::recipients();
		if ( empty( $to ) ) {
			return false;
		}

		$subject = __( 'STC Showcase – Test Email', 'stc-product-showcase-pro' );
		$body    = self::build_body(
			array(
				'product_name'  => __( 'Test Product', 'stc-product-showcase-pro' ),
				'customer_name' => __( 'Test Sender', 'stc-product-showcase-pro' ),
				'customer_email' => self::from_email(),
				'message'       => __( 'This is a test email confirming that enquiry notifications are working.', 'stc-product-showcase-pro' ),
			),
			false
		);

		return self::dispatch( $to, $subject, $body, self::headers() );
	}

	/**
	 * Low-level wp_mail wrapper that records the last failure message.
	 *
	 * @param array<int,string> $to      Recipients.
	 * @param string            $subject Subject.
	 * @param string            $body    HTML body.
	 * @param array<int,string> $headers Headers.
	 * @return bool
	 */
	private static function dispatch( array $to, string $subject, string $body, array $headers ): bool {
		$capture = static function ( $wp_error ) {
			if ( is_wp_error( $wp_error ) ) {
				STC_PSP_Mailer::log( 'wp_mail_failed: ' . $wp_error->get_error_message() );
			}
		};

		add_action( 'wp_mail_failed', $capture );
		$result = wp_mail( $to, $subject, $body, $headers );
		remove_action( 'wp_mail_failed', $capture );

		return (bool) $result;
	}

	/**
	 * Write a message to the PHP error log when debugging is enabled.
	 *
	 * @param string $message Message.
	 */
	public static function log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[STC Product Showcase Pro] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Build the HTML email body.
	 *
	 * @param array<string,mixed> $data        Enquiry data.
	 * @param bool                $is_customer Whether this is the customer copy.
	 * @return string
	 */
	public static function build_body( array $data, bool $is_customer = false ): string {
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
			$rows[ (string) $label ] = $value;
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
}
