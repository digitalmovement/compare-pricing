<?php
/**
 * Plugin Name: Compare Pricing
 * Plugin URI: https://yourwebsite.com
 * Description: Compare prices from eBay and Amazon for WooCommerce products
 * Version: 1.1.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: compare-pricing
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('COMPARE_PRICING_VERSION', '1.1.0');
define('COMPARE_PRICING_PATH', plugin_dir_path(__FILE__));
define('COMPARE_PRICING_URL', plugin_dir_url(__FILE__));

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>Compare Pricing requires WooCommerce to be installed and activated.</p></div>';
    });
    return;
}

// Include required files
require_once COMPARE_PRICING_PATH . 'includes/class-compare-pricing.php';
require_once COMPARE_PRICING_PATH . 'includes/class-ebay-api.php';
require_once COMPARE_PRICING_PATH . 'includes/class-amazon-api.php';

// Initialize the plugin
function init_compare_pricing() {
    new Compare_Pricing();
    
    // Only include admin class if in admin area
    if (is_admin()) {
        require_once COMPARE_PRICING_PATH . 'admin/class-admin.php';
        new Compare_Pricing_Admin();
    }
}
add_action('plugins_loaded', 'init_compare_pricing');

// Activation hook
register_activation_hook(__FILE__, function() {
    // Set default options
    $default_options = array(
        'cache_duration' => 24,
        'ebay_app_id' => '',
        'ebay_cert_id' => '',
        'amazon_api_key' => '',
        'sandbox_mode' => 0
    );
    
    add_option('compare_pricing_options', $default_options);
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clean up transients
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_amazon_%' OR option_name LIKE '_transient_timeout_amazon_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ebay_%' OR option_name LIKE '_transient_timeout_ebay_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_compare_pricing_%' OR option_name LIKE '_transient_timeout_compare_pricing_%'");
});

public function enqueue_scripts() {
    // existing code...
    wp_enqueue_script('compare-pricing-js', plugin_dir_url(__FILE__) . 'assets/js/compare-pricing.js', array('jquery'), '1.0.0', true);
    
    // Add nonce to localized script
    wp_localize_script('compare-pricing-js', 'compare_pricing_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('compare_pricing_nonce')
    ));
} 