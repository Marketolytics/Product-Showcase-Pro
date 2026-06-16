<?php
/**
 * Asset registration / enqueueing (front-end + admin).
 *
 * @package STC_Product_Showcase_Pro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STC_PSP_Assets
 */
class STC_PSP_Assets {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( $this, 'register_frontend' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
	}

	/**
	 * Register (and on the front-end, conditionally enqueue) the assets.
	 *
	 * Registering with the same handle the widget declares in
	 * get_script_depends()/get_style_depends() lets Elementor load them
	 * only on pages where the widget is present.
	 */
	public function register_frontend(): void {
		if ( wp_style_is( 'stc-psp-frontend', 'registered' ) ) {
			return;
		}

		wp_register_style(
			'stc-psp-frontend',
			STC_PSP_ASSETS . 'css/frontend.css',
			array( 'dashicons' ),
			STC_PSP_VERSION
		);

		wp_register_script(
			'stc-psp-frontend',
			STC_PSP_ASSETS . 'js/frontend.js',
			array(),
			STC_PSP_VERSION,
			true
		);

		wp_localize_script(
			'stc-psp-frontend',
			'stcPspVars',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'stc_psp_frontend' ),
				'i18n'       => array(
					'sending' => __( 'Sending…', 'stc-product-showcase-pro' ),
					'error'   => __( 'Something went wrong. Please try again.', 'stc-product-showcase-pro' ),
					'loading' => __( 'Loading…', 'stc-product-showcase-pro' ),
				),
			)
		);
	}

	/**
	 * Enqueue admin assets on plugin + product edit screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin( string $hook ): void {
		$screen      = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_product  = $screen && 'product' === $screen->post_type;
		$is_plugin   = false !== strpos( $hook, 'stc-psp' );

		if ( ! $is_product && ! $is_plugin ) {
			return;
		}

		wp_enqueue_style(
			'stc-psp-admin',
			STC_PSP_ASSETS . 'css/admin.css',
			array(),
			STC_PSP_VERSION
		);

		$deps = array( 'jquery' );
		if ( $is_plugin ) {
			$deps[] = 'jquery-ui-sortable';
		}
		if ( $is_product ) {
			wp_enqueue_media();
		}

		wp_enqueue_script(
			'stc-psp-admin',
			STC_PSP_ASSETS . 'js/admin.js',
			$deps,
			STC_PSP_VERSION,
			true
		);

		wp_localize_script(
			'stc-psp-admin',
			'stcPspAdmin',
			array(
				'selectPdfTitle' => __( 'Select or Upload a Catalogue PDF', 'stc-product-showcase-pro' ),
				'selectPdfBtn'   => __( 'Use this file', 'stc-product-showcase-pro' ),
			)
		);
	}
}
