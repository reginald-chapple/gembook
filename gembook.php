<?php
/**
 * Plugin Name:       GemBook
 * Plugin URI:        https://example.com/
 * Description:       A WooCommerce extension for bookable services.
 * Version:           1.0.0
 * Author:            Reginald Chapple
 * Author URI:        https://example.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       gembook
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The core plugin class.
 */
final class GemBook {

	/**
	 * The single instance of the class.
	 *
	 * @var GemBook
	 */
	protected static $_instance = null;

	/**
	 * Main GemBook Instance.
	 *
	 * Ensures only one instance of GemBook is loaded or can be loaded.
	 *
	 * @static
	 * @return GemBook - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * GemBook Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		
		// The DB class is required for the activation hook, so it must be included before hooks are registered.
		include_once GEMBOOK_PLUGIN_PATH . 'includes/class-gembook-db.php';
		
		$this->init_hooks();
	}

	/**
	 * Define GemBook Constants.
	 */
	private function define_constants() {
		define( 'GEMBOOK_PLUGIN_FILE', __FILE__ );
		define( 'GEMBOOK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		define( 'GEMBOOK_VERSION', '1.0.0' );
		define( 'GEMBOOK_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
		define( 'GEMBOOK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	}

	/**
	 * Include required core files.
	 */
	public function includes() {
		include_once GEMBOOK_PLUGIN_PATH . 'includes/class-wc-product-bookable-service.php';
		include_once GEMBOOK_PLUGIN_PATH . 'includes/class-gembook-woocommerce.php';
		// Add the new list table class
		if ( is_admin() ) {
			include_once GEMBOOK_PLUGIN_PATH . 'includes/admin/class-gembook-bookings-list-table.php';
		}
		include_once GEMBOOK_PLUGIN_PATH . 'includes/admin/class-gembook-admin.php';
		include_once GEMBOOK_PLUGIN_PATH . 'includes/class-gembook-booking.php';
		include_once GEMBOOK_PLUGIN_PATH . 'includes/class-gembook-frontend.php';
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		register_activation_hook( __FILE__, array( 'GemBook_DB', 'install' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Init GemBook when plugins are loaded.
	 */
	public function init() {
		// Don't run the plugin if WooCommerce is not active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Register our custom product type with WordPress.
		// This ensures WooCommerce can find and identify it.
		if ( ! term_exists( 'bookable_service', 'product_type' ) ) {
			wp_insert_term( 'bookable_service', 'product_type' );
		}

		$this->includes();

		// Init classes
		new GemBook_WooCommerce();
		new GemBook_Admin();
		new GemBook_Frontend();
	}
}

/**
 * Main instance of GemBook.
 *
 * Returns the main instance of GemBook.
 *
 * @return GemBook
 */
function GemBook() {
	return GemBook::instance();
}

// Get GemBook running.
GemBook();