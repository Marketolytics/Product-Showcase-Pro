<?php
/**
 * AJAX: load more / infinite scroll products.
 *
 * @package STC_Product_Showcase_Pro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STC_PSP_Ajax_Products
 */
class STC_PSP_Ajax_Products {

	const NONCE_ACTION = 'stc_psp_frontend';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_stc_psp_load_products', array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_stc_psp_load_products', array( $this, 'handle' ) );
	}

	/**
	 * Handle the load-more / infinite-scroll request.
	 */
	public function handle(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$page = isset( $_POST['page'] ) ? max( 1, absint( wp_unslash( $_POST['page'] ) ) ) : 1;

		$raw_settings = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';
		$settings     = $this->parse_settings( $raw_settings );

		if ( empty( $settings ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'stc-product-showcase-pro' ) ), 400 );
		}

		$query = STC_PSP_Query::run( $settings, $page );
		$html  = STC_PSP_Renderer::render_cards( $query, $settings );
		wp_reset_postdata();

		wp_send_json_success(
			array(
				'html'      => $html,
				'page'      => $page,
				'max_pages' => (int) $query->max_num_pages,
				'has_more'  => $page < (int) $query->max_num_pages,
			)
		);
	}

	/**
	 * Decode and sanitise the settings payload sent from the browser.
	 *
	 * The payload originates from the widget output, so we re-sanitise every
	 * value rather than trusting it.
	 *
	 * @param mixed $raw Raw settings (JSON string or array).
	 * @return array<string,mixed>
	 */
	private function parse_settings( $raw ): array {
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
		} else {
			$decoded = $raw;
		}

		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$clean = array();

		// Scalar string/enum keys.
		$scalar = array(
			'source', 'orderby', 'order', 'layout', 'image_aspect', 'image_fit',
			'image_hover', 'desc_limit_type', 'features_source', 'features_meta_key',
			'features_style', 'features_icon', 'enquiry_button_text', 'enquiry_icon',
			'enquiry_anim', 'download_button_text', 'download_anim', 'read_more_text',
			'read_less_text', 'skus', 'enquiry_icon_position', 'enquiry_icon_color',
			'download_fallback_text',
		);
		foreach ( $scalar as $key ) {
			if ( isset( $decoded[ $key ] ) && is_scalar( $decoded[ $key ] ) ) {
				$clean[ $key ] = sanitize_text_field( (string) $decoded[ $key ] );
			}
		}

		// Numeric keys.
		foreach ( array( 'per_page', 'desc_limit' ) as $key ) {
			if ( isset( $decoded[ $key ] ) ) {
				$clean[ $key ] = absint( $decoded[ $key ] );
			}
		}

		// Slider value (icon size) may arrive as {size,unit} or a number.
		if ( isset( $decoded['enquiry_icon_size'] ) ) {
			$size = $decoded['enquiry_icon_size'];
			if ( is_array( $size ) ) {
				$clean['enquiry_icon_size'] = array( 'size' => absint( $size['size'] ?? 0 ), 'unit' => 'px' );
			} else {
				$clean['enquiry_icon_size'] = absint( $size );
			}
		}

		// yes/no switches.
		$switches = array(
			'hide_out_of_stock', 'show_image', 'show_name', 'show_sku', 'show_brand',
			'show_category', 'show_description', 'show_features', 'show_applications',
			'show_downloads', 'show_price', 'show_rating', 'show_tags', 'show_stock',
			'enable_read_more', 'enable_enquiry_btn', 'enable_download_btn',
			'enquiry_icon_enable', 'download_fallback', 'enable_custom_order',
			'enable_product_link',
		);
		foreach ( $switches as $key ) {
			if ( isset( $decoded[ $key ] ) ) {
				$clean[ $key ] = ( 'yes' === $decoded[ $key ] ) ? 'yes' : '';
			}
		}

		// Array-of-id keys.
		foreach ( array( 'categories', 'brands', 'tags', 'product_ids' ) as $key ) {
			if ( isset( $decoded[ $key ] ) ) {
				$value         = is_array( $decoded[ $key ] ) ? $decoded[ $key ] : explode( ',', (string) $decoded[ $key ] );
				$clean[ $key ] = array_values( array_filter( array_map( 'absint', $value ) ) );
			}
		}

		// Element order repeater: list of { element: key }.
		if ( isset( $decoded['element_order'] ) && is_array( $decoded['element_order'] ) ) {
			$order = array();
			foreach ( $decoded['element_order'] as $row ) {
				if ( is_array( $row ) && isset( $row['element'] ) ) {
					$order[] = array( 'element' => sanitize_key( (string) $row['element'] ) );
				}
			}
			$clean['element_order'] = $order;
		}

		return $clean;
	}
}
