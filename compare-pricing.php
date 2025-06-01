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
    
    // Only include admin class if in admin area and file exists
    if (is_admin() && file_exists(COMPARE_PRICING_PATH . 'admin/class-admin.php')) {
        require_once COMPARE_PRICING_PATH . 'admin/class-admin.php';
        if (class_exists('Compare_Pricing_Admin')) {
            new Compare_Pricing_Admin();
        }
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

function compare_pricing_create_stats_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'compare_pricing_stats';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        gtin varchar(50) NOT NULL,
        product_id varchar(50) NOT NULL,
        product_title text NOT NULL,
        views int(11) DEFAULT 0,
        clicks int(11) DEFAULT 0,
        top_source varchar(20) DEFAULT '',
        best_price decimal(10,2) DEFAULT NULL,
        currency_symbol varchar(10) DEFAULT '',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        last_viewed datetime DEFAULT NULL,
        last_clicked datetime DEFAULT NULL,
        PRIMARY KEY (id),
        KEY gtin_product_date (gtin, product_id, created_at),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'compare_pricing_create_stats_table');