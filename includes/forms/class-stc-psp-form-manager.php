<?php
/**
 * Form / popup field manager (definitions, sanitisation, rendering).
 *
 * @package STC_Product_Showcase_Pro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STC_PSP_Form_Manager
 *
 * Owns the drag-and-drop enquiry form field model and renders the front-end form.
 */
class STC_PSP_Form_Manager {

	/**
	 * Supported field types.
	 *
	 * @return array<string,string>
	 */
	public static function field_types(): array {
		return array(
			'text'     => __( 'Text', 'stc-product-showcase-pro' ),
			'email'    => __( 'Email', 'stc-product-showcase-pro' ),
			'phone'    => __( 'Phone', 'stc-product-showcase-pro' ),
			'dropdown' => __( 'Dropdown', 'stc-product-showcase-pro' ),
			'radio'    => __( 'Radio', 'stc-product-showcase-pro' ),
			'checkbox' => __( 'Checkbox', 'stc-product-showcase-pro' ),
			'textarea' => __( 'Textarea', 'stc-product-showcase-pro' ),
			'hidden'   => __( 'Hidden Field', 'stc-product-showcase-pro' ),
			'date'     => __( 'Date', 'stc-product-showcase-pro' ),
			'file'     => __( 'File Upload', 'stc-product-showcase-pro' ),
		);
	}

	/**
	 * Reserved keys that map to dedicated enquiry table columns.
	 *
	 * @return array<int,string>
	 */
	public static function reserved_keys(): array {
		return array(
			'customer_name',
			'customer_email',
			'customer_mobile',
			'customer_company',
			'customer_city',
			'customer_country',
			'customer_industry',
			'message',
		);
	}

	/**
	 * Sanitise an array of field definitions coming from the builder.
	 *
	 * @param mixed $fields Raw fields.
	 * @return array<int,array<string,mixed>>
	 */
	public static function sanitize_fields( $fields ): array {
		if ( ! is_array( $fields ) ) {
			return STC_PSP_Settings::default_form_fields();
		}

		$types = array_keys( self::field_types() );
		$clean = array();

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$type = isset( $field['type'] ) && in_array( $field['type'], $types, true ) ? $field['type'] : 'text';
			$key  = isset( $field['key'] ) ? sanitize_key( (string) $field['key'] ) : '';
			if ( '' === $key ) {
				$key = 'field_' . wp_generate_password( 6, false, false );
			}

			$options = array();
			if ( isset( $field['options'] ) ) {
				if ( is_array( $field['options'] ) ) {
					$options = array_values( array_filter( array_map( 'sanitize_text_field', $field['options'] ) ) );
				} elseif ( is_string( $field['options'] ) ) {
					$options = array_values(
						array_filter(
							array_map( 'trim', preg_split( '/\r\n|\r|\n/', $field['options'] ) )
						)
					);
				}
			}

			$clean[] = array(
				'key'      => $key,
				'type'     => $type,
				'label'    => sanitize_text_field( (string) ( $field['label'] ?? '' ) ),
				'enabled'  => ! empty( $field['enabled'] ),
				'required' => ! empty( $field['required'] ),
				'options'  => $options,
			);
		}

		return $clean ?: STC_PSP_Settings::default_form_fields();
	}

	/**
	 * Get the enabled form fields.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_enabled_fields(): array {
		$fields = STC_PSP_Settings::get( 'form_fields', STC_PSP_Settings::default_form_fields() );
		if ( ! is_array( $fields ) ) {
			$fields = STC_PSP_Settings::default_form_fields();
		}

		return array_values(
			array_filter(
				$fields,
				static fn( $f ) => ! empty( $f['enabled'] )
			)
		);
	}

	/**
	 * Render a single field's HTML.
	 *
	 * @param array<string,mixed> $field Field definition.
	 * @return string
	 */
	public static function render_field( array $field ): string {
		$key      = (string) ( $field['key'] ?? '' );
		$type     = (string) ( $field['type'] ?? 'text' );
		$label    = (string) ( $field['label'] ?? '' );
		$required = ! empty( $field['required'] );
		$req_attr = $required ? 'required' : '';
		$req_mark = $required ? ' <span class="stc-psp-req">*</span>' : '';
		$name     = 'stc_psp_field[' . $key . ']';
		$id       = 'stc_psp_' . $key;
		$options  = (array) ( $field['options'] ?? array() );

		ob_start();

		if ( 'hidden' === $type ) {
			printf( '<input type="hidden" name="%s" id="%s" value="" />', esc_attr( $name ), esc_attr( $id ) );
			return (string) ob_get_clean();
		}

		echo '<div class="stc-psp-field stc-psp-field-' . esc_attr( $type ) . '">';

		if ( ! in_array( $type, array( 'checkbox', 'radio' ), true ) ) {
			printf(
				'<label for="%s" class="stc-psp-label">%s%s</label>',
				esc_attr( $id ),
				esc_html( $label ),
				$req_mark // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		}

		switch ( $type ) {
			case 'textarea':
				printf(
					'<textarea name="%s" id="%s" rows="4" %s></textarea>',
					esc_attr( $name ),
					esc_attr( $id ),
					esc_attr( $req_attr )
				);
				break;

			case 'dropdown':
				printf( '<select name="%s" id="%s" %s>', esc_attr( $name ), esc_attr( $id ), esc_attr( $req_attr ) );
				printf( '<option value="">%s</option>', esc_html__( 'Select…', 'stc-product-showcase-pro' ) );
				foreach ( $options as $opt ) {
					printf( '<option value="%1$s">%1$s</option>', esc_attr( $opt ) );
				}
				echo '</select>';
				break;

			case 'radio':
			case 'checkbox':
				printf( '<span class="stc-psp-label">%s%s</span>', esc_html( $label ), $req_mark ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<div class="stc-psp-options">';
				$input_type = 'radio' === $type ? 'radio' : 'checkbox';
				$input_name = 'checkbox' === $type ? $name . '[]' : $name;
				foreach ( $options as $i => $opt ) {
					printf(
						'<label class="stc-psp-opt"><input type="%1$s" name="%2$s" value="%3$s" %4$s /> %3$s</label>',
						esc_attr( $input_type ),
						esc_attr( $input_name ),
						esc_attr( $opt ),
						( $required && 0 === $i && 'radio' === $type ) ? 'required' : ''
					);
				}
				echo '</div>';
				break;

			case 'file':
				printf(
					'<input type="file" name="%s" id="%s" %s />',
					esc_attr( $name ),
					esc_attr( $id ),
					esc_attr( $req_attr )
				);
				break;

			case 'date':
				printf( '<input type="date" name="%s" id="%s" %s />', esc_attr( $name ), esc_attr( $id ), esc_attr( $req_attr ) );
				break;

			case 'phone':
				printf( '<input type="tel" name="%s" id="%s" %s />', esc_attr( $name ), esc_attr( $id ), esc_attr( $req_attr ) );
				break;

			case 'email':
				printf( '<input type="email" name="%s" id="%s" %s />', esc_attr( $name ), esc_attr( $id ), esc_attr( $req_attr ) );
				break;

			case 'text':
			default:
				printf( '<input type="text" name="%s" id="%s" %s />', esc_attr( $name ), esc_attr( $id ), esc_attr( $req_attr ) );
				break;
		}

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * Render the full enquiry form markup (used inside the popup).
	 *
	 * @return string
	 */
	public static function render_form(): string {
		$fields = self::get_enabled_fields();

		ob_start();
		?>
		<form class="stc-psp-enquiry-form" method="post" enctype="multipart/form-data" novalidate>
			<?php wp_nonce_field( 'stc_psp_enquiry', 'stc_psp_enquiry_nonce' ); ?>

			<input type="hidden" name="action" value="stc_psp_submit_enquiry" />
			<input type="hidden" name="product_id" value="" class="stc-psp-meta-product-id" />
			<input type="hidden" name="product_name" value="" class="stc-psp-meta-product-name" />
			<input type="hidden" name="product_sku" value="" class="stc-psp-meta-product-sku" />
			<input type="hidden" name="product_category" value="" class="stc-psp-meta-product-category" />
			<input type="hidden" name="product_url" value="" class="stc-psp-meta-product-url" />

			<?php
			// Honeypot anti-spam field.
			echo '<div class="stc-psp-hp" aria-hidden="true" style="position:absolute;left:-9999px;">';
			echo '<label>' . esc_html__( 'Leave this empty', 'stc-product-showcase-pro' ) . '<input type="text" name="stc_psp_hp" tabindex="-1" autocomplete="off" /></label>';
			echo '</div>';

			foreach ( $fields as $field ) {
				echo self::render_field( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>

			<div class="stc-psp-form-footer">
				<button type="submit" class="stc-psp-submit-btn">
					<?php echo esc_html( STC_PSP_Settings::get( 'popup_submit_text', __( 'Send Enquiry', 'stc-product-showcase-pro' ) ) ); ?>
				</button>
			</div>
			<div class="stc-psp-form-message" role="status" aria-live="polite"></div>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the popup markup once per page (in the footer).
	 */
	public function __construct() {
		add_action( 'wp_footer', array( $this, 'render_popup' ) );
	}

	/**
	 * Output the enquiry popup container in the footer.
	 */
	public function render_popup(): void {
		if ( 'yes' !== STC_PSP_Settings::get( 'enable_enquiry', 'yes' ) ) {
			return;
		}
		?>
		<div class="stc-psp-popup-overlay" id="stc-psp-popup" aria-hidden="true">
			<div class="stc-psp-popup" role="dialog" aria-modal="true" aria-labelledby="stc-psp-popup-title">
				<button type="button" class="stc-psp-popup-close" aria-label="<?php esc_attr_e( 'Close', 'stc-product-showcase-pro' ); ?>">&times;</button>
				<h3 class="stc-psp-popup-title" id="stc-psp-popup-title"><?php echo esc_html( STC_PSP_Settings::get( 'popup_title', __( 'Product Enquiry', 'stc-product-showcase-pro' ) ) ); ?></h3>
				<div class="stc-psp-popup-product"></div>
				<?php echo self::render_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
		<?php
	}
}
