<?php
/**
 * Plugin Name:       STC Product Showcase Pro
 * Plugin URI:        https://example.com/stc-product-showcase-pro
 * Description:       Premium WooCommerce + Elementor horizontal product showcase with enquiry system, PDF catalogue downloads, drag-and-drop form/popup builder, download tracking and an admin dashboard.
 * Version:           1.1.0
 * Author:            STC
 * Author URI:        https://example.com
 * Text Domain:       stc-product-showcase-pro
 * Domain Path:       /languages
 * Requires PHP:      8.1
 * Requires at least: 6.8
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * WC requires at least: 7.0
 * WC tested up to:   9.0
 *
 * @package STC_Product_Showcase_Pro
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'STC_PSP_VERSION' ) ) {
	return;
}

/* -------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------- */
define( 'STC_PSP_VERSION', '1.1.0' );
define( 'STC_PSP_DB_VERSION', '1.0.0' );
define( 'STC_PSP_FILE', __FILE__ );
define( 'STC_PSP_BASENAME', plugin_basename( __FILE__ ) );
define( 'STC_PSP_PATH', plugin_dir_path( __FILE__ ) );
define( 'STC_PSP_URL', plugin_dir_url( __FILE__ ) );
define( 'STC_PSP_INCLUDES', STC_PSP_PATH . 'includes/' );
define( 'STC_PSP_ASSETS', STC_PSP_URL . 'assets/' );
define( 'STC_PSP_MIN_PHP', '8.1' );
define( 'STC_PSP_MIN_WP', '6.8' );

/* -------------------------------------------------------------------------
 * Autoloader
 *
 * Maps class names like STC_PSP_Database -> includes/class-stc-psp-database.php
 * and STC_PSP_Widget_Showcase -> includes/widgets/class-stc-psp-widget-showcase.php
 * ---------------------------------------------------------------------- */
spl_autoload_register(
	static function ( string $class ): void {
		if ( strpos( $class, 'STC_PSP_' ) !== 0 ) {
			return;
		}

		$relative = strtolower( str_replace( array( 'STC_PSP_', '_' ), array( '', '-' ), $class ) );
		$filename = 'class-stc-psp-' . $relative . '.php';

		// Sub-directory mapping based on class prefix.
		$map = array(
			'widget-' => 'widgets/',
			'ajax-'   => 'ajax/',
			'admin-'  => 'admin/',
			'form-'   => 'forms/',
		);

		// Exact class -> sub-directory overrides (data layer).
		$exact = array(
			'database'            => 'database/',
			'enquiry-repository'  => 'database/',
			'download-repository' => 'database/',
			'template-repository' => 'database/',
		);

		$subdir = $exact[ $relative ] ?? '';
		if ( '' === $subdir ) {
			foreach ( $map as $needle => $dir ) {
				if ( strpos( $relative, $needle ) === 0 ) {
					$subdir = $dir;
					break;
				}
			}
		}

		$path = STC_PSP_INCLUDES . $subdir . $filename;
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

/* -------------------------------------------------------------------------
 * Environment compatibility check
 * ---------------------------------------------------------------------- */
function stc_psp_environment_ok(): bool {
	$php_ok = version_compare( PHP_VERSION, STC_PSP_MIN_PHP, '>=' );
	$wp_ok  = version_compare( get_bloginfo( 'version' ), STC_PSP_MIN_WP, '>=' );

	return $php_ok && $wp_ok;
}

/**
 * Display an admin notice when a hard dependency is missing.
 */
function stc_psp_dependency_notice(): void {
	$messages = array();

	if ( ! stc_psp_environment_ok() ) {
		$messages[] = sprintf(
			/* translators: 1: required PHP version, 2: required WP version. */
			esc_html__( 'STC Product Showcase Pro requires PHP %1$s+ and WordPress %2$s+.', 'stc-product-showcase-pro' ),
			esc_html( STC_PSP_MIN_PHP ),
			esc_html( STC_PSP_MIN_WP )
		);
	}

	if ( ! class_exists( 'WooCommerce' ) ) {
		$messages[] = esc_html__( 'STC Product Showcase Pro requires WooCommerce to be installed and active.', 'stc-product-showcase-pro' );
	}

	if ( ! did_action( 'elementor/loaded' ) ) {
		$messages[] = esc_html__( 'STC Product Showcase Pro requires Elementor to be installed and active.', 'stc-product-showcase-pro' );
	}

	if ( empty( $messages ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p><strong>%s</strong></p><ul style="list-style:disc;margin-left:20px;"><li>%s</li></ul></div>',
		esc_html__( 'STC Product Showcase Pro', 'stc-product-showcase-pro' ),
		implode( '</li><li>', $messages ) // Already escaped above.
	);
}

/* -------------------------------------------------------------------------
 * Activation / Deactivation / Uninstall hooks
 * ---------------------------------------------------------------------- */
register_activation_hook(
	__FILE__,
	static function (): void {
		require_once STC_PSP_INCLUDES . 'database/class-stc-psp-database.php';
		STC_PSP_Database::install();

		// Store defaults if first install.
		if ( false === get_option( 'stc_psp_settings' ) ) {
			require_once STC_PSP_INCLUDES . 'class-stc-psp-settings.php';
			add_option( 'stc_psp_settings', STC_PSP_Settings::get_defaults() );
		}

		add_option( 'stc_psp_db_version', STC_PSP_DB_VERSION );
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		flush_rewrite_rules();
	}
);

/* -------------------------------------------------------------------------
 * Bootstrap the plugin once all plugins are loaded.
 * ---------------------------------------------------------------------- */
function stc_psp(): ?STC_PSP_Plugin {
	if ( ! stc_psp_environment_ok() ) {
		return null;
	}

	return STC_PSP_Plugin::instance();
}

add_action(
	'plugins_loaded',
	static function (): void {
		load_plugin_textdomain( 'stc-product-showcase-pro', false, dirname( STC_PSP_BASENAME ) . '/languages' );

		// Hard requirements: WooCommerce + Elementor + environment.
		if ( ! stc_psp_environment_ok() || ! class_exists( 'WooCommerce' ) || ! did_action( 'elementor/loaded' ) ) {
			add_action( 'admin_notices', 'stc_psp_dependency_notice' );
			return;
		}

		// Run DB upgrades if needed.
		if ( version_compare( (string) get_option( 'stc_psp_db_version', '0' ), STC_PSP_DB_VERSION, '<' ) ) {
			STC_PSP_Database::install();
			update_option( 'stc_psp_db_version', STC_PSP_DB_VERSION );
		}

		stc_psp();
	},
	20
);

/* -------------------------------------------------------------------------
 * Declare HPOS (High-Performance Order Storage) compatibility.
 * ---------------------------------------------------------------------- */
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', STC_PSP_FILE, true );
		}
	}
);
