<?php
/**
 * Enquiry system: disables WooCommerce purchasing and provides shared helpers.
 *
 * @package STC_Product_Showcase_Pro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STC_PSP_Enquiry_System
 */
class STC_PSP_Enquiry_System {

	/**
	 * Hook registration.
	 */
	public function __construct() {
		if ( 'yes' === STC_PSP_Settings::get( 'remove_add_to_cart', 'yes' ) ) {
			$this->disable_purchasing();
		}
	}

	/**
	 * Remove the Add To Cart functionality across the store.
	 */
	private function disable_purchasing(): void {
		// Make every product non-purchasable.
		add_filter( 'woocommerce_is_purchasable', '__return_false', 99 );

		// Hide prices' add-to-cart button on loops and single pages.
		add_action(
			'init',
			static function (): void {
				remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
			},
			20
		);

		// Replace the single product add to cart with an enquiry button.
		add_action(
			'woocommerce_single_product_summary',
			static function (): void {
				if ( 'yes' !== STC_PSP_Settings::get( 'enable_enquiry', 'yes' ) ) {
					return;
				}
				global $product;
				if ( ! $product instanceof WC_Product ) {
					return;
				}

				echo STC_PSP_Enquiry_System::render_enquiry_button( $product->get_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			},
			30
		);
	}

	/**
	 * Build the auto product data passed to the popup for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string,string>
	 */
	public static function product_payload( int $product_id ): array {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product ) {
			return array(
				'product_id'       => (string) $product_id,
				'product_name'     => '',
				'product_sku'      => '',
				'product_category' => '',
				'product_url'      => '',
			);
		}

		$cats  = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
		$cats  = is_wp_error( $cats ) ? array() : $cats;

		return array(
			'product_id'       => (string) $product_id,
			'product_name'     => $product->get_name(),
			'product_sku'      => $product->get_sku(),
			'product_category' => implode( ', ', $cats ),
			'product_url'      => get_permalink( $product_id ),
		);
	}

	/**
	 * Render a self-contained Enquire Now button + data attributes.
	 *
	 * @param int                 $product_id Product ID.
	 * @param array<string,mixed> $args       Optional button arguments (text, class, icon).
	 * @return string
	 */
	public static function render_enquiry_button( int $product_id, array $args = array() ): string {
		$payload = self::product_payload( $product_id );

		$text  = $args['text'] ?? STC_PSP_Settings::get( 'enquiry_button_text', __( 'Enquire Now', 'stc-product-showcase-pro' ) );
		$class = $args['class'] ?? '';
		$icon  = $args['icon'] ?? 'dashicons dashicons-email-alt';

		// Icon controls.
		$icon_enabled  = ! array_key_exists( 'icon_enabled', $args ) || ! empty( $args['icon_enabled'] );
		$icon_position = ( ( $args['icon_position'] ?? 'left' ) === 'right' ) ? 'right' : 'left';
		$icon_size     = isset( $args['icon_size'] ) && '' !== $args['icon_size'] ? (int) $args['icon_size'] : 0;
		$icon_color    = isset( $args['icon_color'] ) ? (string) $args['icon_color'] : '';

		if ( 'right' === $icon_position ) {
			$class .= ' stc-psp-icon-right';
		}

		$icon_style = '';
		if ( $icon_size > 0 ) {
			$icon_style .= 'font-size:' . $icon_size . 'px;width:' . $icon_size . 'px;height:' . $icon_size . 'px;';
		}
		if ( '' !== $icon_color ) {
			$icon_style .= 'color:' . $icon_color . ';';
		}

		$icon_html = $icon_enabled ? self::render_icon_markup( $icon, $icon_style ) : '';

		ob_start();
		?>
		<button type="button"
			class="stc-psp-enquire-btn <?php echo esc_attr( $class ); ?>"
			data-product-id="<?php echo esc_attr( $payload['product_id'] ); ?>"
			data-product-name="<?php echo esc_attr( $payload['product_name'] ); ?>"
			data-product-sku="<?php echo esc_attr( $payload['product_sku'] ); ?>"
			data-product-category="<?php echo esc_attr( $payload['product_category'] ); ?>"
			data-product-url="<?php echo esc_url( $payload['product_url'] ); ?>">
			<?php echo $icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span class="stc-psp-btn-text"><?php echo esc_html( $text ); ?></span>
		</button>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render an icon for a button, supporting both the Elementor ICONS control
	 * value (Font Awesome / SVG) and a legacy icon class string.
	 *
	 * @param mixed  $icon  Icon value (array from ICONS control or class string).
	 * @param string $style Inline style applied to the wrapper.
	 * @return string
	 */
	public static function render_icon_markup( $icon, string $style = '' ): string {
		$wrapper_open  = '<span class="stc-psp-btn-icon" style="' . esc_attr( $style ) . '" aria-hidden="true">';
		$wrapper_close = '</span>';

		// Elementor ICONS control value: array( 'value' => ..., 'library' => ... ).
		if ( is_array( $icon ) ) {
			if ( empty( $icon['value'] ) ) {
				return '';
			}

			if ( class_exists( '\Elementor\Icons_Manager' ) ) {
				ob_start();
				\Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
				$rendered = (string) ob_get_clean();
				if ( '' !== trim( $rendered ) ) {
					return $wrapper_open . $rendered . $wrapper_close;
				}
			}

			// Fallback: treat value as a class string (e.g. "fas fa-envelope").
			$icon = is_string( $icon['value'] ) ? $icon['value'] : '';
		}

		$icon = (string) $icon;
		if ( '' === trim( $icon ) ) {
			return '';
		}

		return '<i class="stc-psp-btn-icon ' . esc_attr( $icon ) . '" style="' . esc_attr( $style ) . '" aria-hidden="true"></i>';
	}
}
