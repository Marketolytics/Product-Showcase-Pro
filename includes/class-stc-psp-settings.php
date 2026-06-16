<?php
/**
 * Plugin settings registry and helpers.
 *
 * @package STC_Product_Showcase_Pro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STC_PSP_Settings
 *
 * Centralised access to plugin options stored under "stc_psp_settings".
 */
class STC_PSP_Settings {

	const OPTION = 'stc_psp_settings';

	/**
	 * Cached settings array.
	 *
	 * @var array<string,mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Constructor registers settings with the Settings API.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/**
	 * Default settings used on first install and as fallbacks.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_defaults(): array {
		return array(
			// General.
			'remove_add_to_cart'   => 'yes',
			'enable_enquiry'       => 'yes',
			'purge_on_uninstall'   => 'no',
			'enquiry_button_text'  => __( 'Enquire Now', 'stc-product-showcase-pro' ),
			'download_button_text' => __( 'Download Catalogue', 'stc-product-showcase-pro' ),

			// Email.
			'admin_email'          => get_option( 'admin_email' ),
			'email_subject'        => __( 'New Product Enquiry', 'stc-product-showcase-pro' ),
			'email_cc'             => '',
			'send_copy_to_user'    => 'no',
			'from_name'            => get_bloginfo( 'name' ),
			'from_email'           => get_option( 'admin_email' ),

			// Download options.
			'track_downloads'      => 'yes',
			'show_download_count'  => 'yes',
			'show_file_size'       => 'yes',
			'show_pdf_icon'        => 'yes',
			'open_new_tab'         => 'yes',

			// Popup / form builder fields. Each entry is a field definition.
			'form_fields'          => self::default_form_fields(),
			'popup_title'          => __( 'Product Enquiry', 'stc-product-showcase-pro' ),
			'popup_submit_text'    => __( 'Send Enquiry', 'stc-product-showcase-pro' ),
			'popup_success_msg'    => __( 'Thank you! Your enquiry has been sent successfully.', 'stc-product-showcase-pro' ),
		);
	}

	/**
	 * The default popup/enquiry form fields.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function default_form_fields(): array {
		return array(
			array(
				'key'      => 'customer_name',
				'type'     => 'text',
				'label'    => __( 'Name', 'stc-product-showcase-pro' ),
				'enabled'  => true,
				'required' => true,
				'options'  => array(),
			),
			array(
				'key'      => 'customer_mobile',
				'type'     => 'phone',
				'label'    => __( 'Mobile', 'stc-product-showcase-pro' ),
				'enabled'  => true,
				'required' => true,
				'options'  => array(),
			),
			array(
				'key'      => 'customer_email',
				'type'     => 'email',
				'label'    => __( 'Email', 'stc-product-showcase-pro' ),
				'enabled'  => true,
				'required' => true,
				'options'  => array(),
			),
			array(
				'key'      => 'customer_company',
				'type'     => 'text',
				'label'    => __( 'Company', 'stc-product-showcase-pro' ),
				'enabled'  => true,
				'required' => false,
				'options'  => array(),
			),
			array(
				'key'      => 'customer_city',
				'type'     => 'text',
				'label'    => __( 'City', 'stc-product-showcase-pro' ),
				'enabled'  => true,
				'required' => false,
				'options'  => array(),
			),
			array(
				'key'      => 'customer_country',
				'type'     => 'text',
				'label'    => __( 'Country', 'stc-product-showcase-pro' ),
				'enabled'  => true,
				'required' => false,
				'options'  => array(),
			),
			array(
				'key'      => 'customer_industry',
				'type'     => 'text',
				'label'    => __( 'Industry', 'stc-product-showcase-pro' ),
				'enabled'  => true,
				'required' => false,
				'options'  => array(),
			),
			array(
				'key'      => 'message',
				'type'     => 'textarea',
				'label'    => __( 'Message', 'stc-product-showcase-pro' ),
				'enabled'  => true,
				'required' => false,
				'options'  => array(),
			),
		);
	}

	/**
	 * Retrieve all settings (merged with defaults).
	 *
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::get_defaults() );
		}

		return self::$cache;
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get( string $key, $default = '' ) {
		$all = self::all();
		return $all[ $key ] ?? $default;
	}

	/**
	 * Persist a full settings array (already sanitised by caller).
	 *
	 * @param array<string,mixed> $data Settings.
	 */
	public static function save( array $data ): void {
		update_option( self::OPTION, $data );
		self::$cache = null;
	}

	/**
	 * Register the option with the WordPress Settings API.
	 */
	public function register(): void {
		register_setting(
			'stc_psp_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::get_defaults(),
			)
		);
	}

	/**
	 * Sanitize the entire settings payload.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ): array {
		$defaults = self::get_defaults();
		$clean    = self::all();
		$input    = is_array( $input ) ? $input : array();

		$text_keys = array(
			'enquiry_button_text',
			'download_button_text',
			'email_subject',
			'from_name',
			'popup_title',
			'popup_submit_text',
		);
		foreach ( $text_keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$clean[ $key ] = sanitize_text_field( (string) $input[ $key ] );
			}
		}

		$bool_keys = array(
			'remove_add_to_cart',
			'enable_enquiry',
			'purge_on_uninstall',
			'track_downloads',
			'show_download_count',
			'show_file_size',
			'show_pdf_icon',
			'open_new_tab',
			'send_copy_to_user',
		);
		foreach ( $bool_keys as $key ) {
			$clean[ $key ] = ( isset( $input[ $key ] ) && 'yes' === $input[ $key ] ) ? 'yes' : 'no';
		}

		if ( isset( $input['admin_email'] ) ) {
			$clean['admin_email'] = sanitize_email( (string) $input['admin_email'] );
		}
		if ( isset( $input['from_email'] ) ) {
			$clean['from_email'] = sanitize_email( (string) $input['from_email'] );
		}
		if ( isset( $input['email_cc'] ) ) {
			$emails           = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', (string) $input['email_cc'] ) ) ) );
			$clean['email_cc'] = implode( ', ', $emails );
		}
		if ( isset( $input['popup_success_msg'] ) ) {
			$clean['popup_success_msg'] = sanitize_textarea_field( (string) $input['popup_success_msg'] );
		}

		// Form fields (from the form builder).
		if ( isset( $input['form_fields'] ) ) {
			$clean['form_fields'] = STC_PSP_Form_Manager::sanitize_fields( $input['form_fields'] );
		}

		// Fall back to defaults for anything missing.
		return wp_parse_args( $clean, $defaults );
	}
}
