<?php
/**
 * Main plugin orchestrator (singleton).
 *
 * @package STC_Product_Showcase_Pro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STC_PSP_Plugin
 *
 * Wires together all sub-systems of the plugin.
 */
final class STC_PSP_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var STC_PSP_Plugin|null
	 */
	private static ?STC_PSP_Plugin $instance = null;

	/**
	 * Loaded component instances keyed by handle.
	 *
	 * @var array<string,object>
	 */
	private array $components = array();

	/**
	 * Get the singleton instance.
	 */
	public static function instance(): STC_PSP_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}

		return self::$instance;
	}

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {}

	/**
	 * Boot all components.
	 */
	private function boot(): void {
		// Core services.
		$this->components['settings']     = new STC_PSP_Settings();
		$this->components['product_meta'] = new STC_PSP_Product_Meta();
		$this->components['enquiry']      = new STC_PSP_Enquiry_System();
		$this->components['forms']        = new STC_PSP_Form_Manager();

		// AJAX endpoints (front + admin).
		$this->components['ajax_products'] = new STC_PSP_Ajax_Products();
		$this->components['ajax_enquiry']  = new STC_PSP_Ajax_Enquiry();
		$this->components['ajax_download'] = new STC_PSP_Ajax_Download();

		// Admin dashboard.
		if ( is_admin() ) {
			$this->components['admin'] = new STC_PSP_Admin_Dashboard();
		}

		// Assets.
		$this->components['assets'] = new STC_PSP_Assets();

		// Elementor integration.
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_widget_category' ) );

		do_action( 'stc_psp_loaded', $this );
	}

	/**
	 * Register the Elementor widget category.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 */
	public function register_widget_category( $elements_manager ): void {
		$elements_manager->add_category(
			'stc-psp',
			array(
				'title' => __( 'STC Product Showcase', 'stc-product-showcase-pro' ),
				'icon'  => 'eicon-products',
			)
		);
	}

	/**
	 * Register Elementor widgets.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public function register_widgets( $widgets_manager ): void {
		$widgets_manager->register( new STC_PSP_Widget_Showcase() );
	}

	/**
	 * Retrieve a loaded component.
	 *
	 * @param string $handle Component key.
	 * @return object|null
	 */
	public function get( string $handle ): ?object {
		return $this->components[ $handle ] ?? null;
	}
}
