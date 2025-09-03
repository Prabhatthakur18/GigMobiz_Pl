<?php
/*
Plugin Name: WooCommerce Smart Side Cart + Bulk Buy
Description: Adds a side cart with recommended products, bulk buying options, and mobile selector with image upload.
Version: 1.0.6
Author: Prabhat Thakur
*/

if (!defined('ABSPATH')) exit;

define('WSSC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WSSC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include all required classes
require_once WSSC_PLUGIN_PATH . 'includes/class-wssc-db.php';
require_once WSSC_PLUGIN_PATH . 'includes/class-wssc-mobile-selector.php';
require_once WSSC_PLUGIN_PATH . 'includes/class-wssc-mobile-admin.php';
require_once WSSC_PLUGIN_PATH . 'includes/class-wssc-ajax.php';
require_once WSSC_PLUGIN_PATH . 'includes/class-wssc-sidecart.php';
require_once WSSC_PLUGIN_PATH . 'includes/class-wssc-admin.php';

// Initialize the plugin
class WSSC_Plugin {
    public function __construct() {
        // Hook into WordPress initialization
        add_action('plugins_loaded', [$this, 'init']);
        
        // Register activation hook
        register_activation_hook(__FILE__, [$this, 'activate']);
        
        // Add debug info for troubleshooting
        add_action('wp_footer', [$this, 'debug_info']);
    }
    
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }
        
        // Initialize mobile selector first (other classes depend on it)
        global $wssc_mobile_selector;
        $wssc_mobile_selector = new WSSC_Mobile_Selector();
        
        // Initialize other classes
        new WSSC_Ajax();
        new WSSC_SideCart();
        new WSSC_Admin();
        new WSSC_Mobile_Admin();
    }
    
      // Add this to the activate method in WSSC_Plugin class
public function activate() {
    // Create database tables
    WSSC_DB::create_table();
    WSSC_DB::create_image_table();
    
    // Create mobile selector tables
    $mobile_selector = new WSSC_Mobile_Selector();
    $mobile_selector->create_tables();
    
    // Update plugin version
    $current_version = get_option('wssc_plugin_version', '1.0.0');
    if (version_compare($current_version, '1.0.4', '<')) {
        $this->upgrade_database();
        update_option('wssc_plugin_version', '1.0.4');
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

private function upgrade_database() {
    global $wpdb;
    $table = $wpdb->prefix . 'wssc_bulk_requests';
    
    // Add cart_products column if it doesn't exist
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'cart_products'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN cart_products TEXT AFTER product_id");
    }
}
    
    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        echo __('WooCommerce Smart Side Cart requires WooCommerce to be installed and active.', 'wssc');
        echo '</p></div>';
    }
    
    public function debug_info() {
        // Only show debug info for administrators
        if (!current_user_can('manage_options') || !isset($_GET['wssc_debug'])) {
            return;
        }
        
        echo '<div style="position: fixed; bottom: 10px; left: 10px; background: #fff; padding: 10px; border: 1px solid #ccc; z-index: 9999; font-size: 12px;">';
        echo '<strong>WSSC Debug Info:</strong><br>';
        echo 'Plugin URL: ' . WSSC_PLUGIN_URL . '<br>';
        echo 'WooCommerce Active: ' . (class_exists('WooCommerce') ? 'Yes' : 'No') . '<br>';
        echo 'Cart Items: ' . (WC()->cart ? WC()->cart->get_cart_contents_count() : '0') . '<br>';
        echo 'Ajax URL: ' . admin_url('admin-ajax.php') . '<br>';
        echo '</div>';
    }
}

// Initialize the plugin
new WSSC_Plugin();

// Hook into the mobile selector plugin compatibility
add_action('init', function() {
    // Check if the mobile selector functions exist
    if (function_exists('mms_add_cart_item_data')) {
        // Ensure our plugin uses the same cart item data structure
        add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id, $variation_id) {
            // This ensures compatibility with the mobile selector plugin
            if (isset($_POST['mobile_brand']) && isset($_POST['mobile_model'])) {
                $cart_item_data['mobile_brand'] = sanitize_text_field($_POST['mobile_brand']);
                $cart_item_data['mobile_model'] = sanitize_text_field($_POST['mobile_model']);
                $cart_item_data['unique_key'] = md5(microtime().rand());
            }
            return $cart_item_data;
        }, 5, 3); // Lower priority to run before other plugins
    }
});