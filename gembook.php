<?php
/**
 * Plugin Name: GemBook - WooCommerce Service Booking
 * Plugin URI: https://yoursite.com/gembook
 * Description: A comprehensive booking system for WooCommerce that allows users to create and book services with single-day, multi-day, and time-based options with dynamic pricing.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: gembook
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GEMBOOK_VERSION', '1.0.0');
define('GEMBOOK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GEMBOOK_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GEMBOOK_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main GemBook Class
 */
class GemBook {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
    }
    
    /**
     * Declare HPOS compatibility
     */
    public function declare_hpos_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }
    
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        $this->load_textdomain();
        $this->includes();
        $this->init_hooks();
    }
    
    private function includes() {
        require_once GEMBOOK_PLUGIN_DIR . 'includes/class-gembook-product-type.php';
        require_once GEMBOOK_PLUGIN_DIR . 'includes/class-gembook-admin.php';
        require_once GEMBOOK_PLUGIN_DIR . 'includes/class-gembook-frontend.php';
        require_once GEMBOOK_PLUGIN_DIR . 'includes/class-gembook-cart.php';
        require_once GEMBOOK_PLUGIN_DIR . 'includes/class-gembook-order.php';
        require_once GEMBOOK_PLUGIN_DIR . 'includes/class-gembook-database.php';
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array('GemBook_Database', 'create_tables'));
        add_action('init', array($this, 'init_classes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    }
    
    public function init_classes() {
        new GemBook_Product_Type();
        new GemBook_Admin();
        new GemBook_Frontend();
        new GemBook_Cart();
        new GemBook_Order();
    }
    
    public function enqueue_scripts() {
        // Only load on product pages or if we have a bookable service
        if (is_product() || is_shop() || is_product_category()) {
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('jquery-ui-style', 'https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css');
            
            // Create assets directories if they don't exist
            $css_file = GEMBOOK_PLUGIN_DIR . 'assets/css/frontend.css';
            $js_file = GEMBOOK_PLUGIN_DIR . 'assets/js/frontend.js';
            
            if (file_exists($css_file)) {
                wp_enqueue_style('gembook-frontend', GEMBOOK_PLUGIN_URL . 'assets/css/frontend.css', array(), GEMBOOK_VERSION);
            }
            
            if (file_exists($js_file)) {
                wp_enqueue_script('gembook-frontend', GEMBOOK_PLUGIN_URL . 'assets/js/frontend.js', array('jquery', 'jquery-ui-datepicker'), GEMBOOK_VERSION, true);
            }
            
            wp_localize_script('jquery', 'gembook_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gembook_nonce')
            ));
        }
    }
    
    public function admin_enqueue_scripts() {
        $css_file = GEMBOOK_PLUGIN_DIR . 'assets/css/admin.css';
        $js_file = GEMBOOK_PLUGIN_DIR . 'assets/js/admin.js';
        
        if (file_exists($js_file)) {
            wp_enqueue_script('gembook-admin', GEMBOOK_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), GEMBOOK_VERSION, true);
        }
        
        if (file_exists($css_file)) {
            wp_enqueue_style('gembook-admin', GEMBOOK_PLUGIN_URL . 'assets/css/admin.css', array(), GEMBOOK_VERSION);
        }
    }
    
    private function load_textdomain() {
        load_plugin_textdomain('gembook', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>' . sprintf(esc_html__('GemBook requires WooCommerce to be installed and active. You can download %s here.', 'gembook'), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>') . '</strong></p></div>';
    }
}

// Initialize the plugin
GemBook::get_instance();