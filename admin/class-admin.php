<?php

class Compare_Pricing_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_compare_pricing_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_compare_pricing_test_api', array($this, 'ajax_test_api'));
        add_action('wp_ajax_compare_pricing_test_amazon_api', array($this, 'ajax_test_amazon_api'));
        add_action('wp_ajax_test_ebay_api', array($this, 'ajax_test_ebay_api'));
        add_action('wp_ajax_test_amazon_api', array($this, 'ajax_test_amazon_api'));
        add_action('wp_ajax_test_gtin_lookup', array($this, 'ajax_test_gtin_lookup'));
    }
    
    public function add_admin_menu() {
        add_options_page(
            'Compare Pricing Settings',
            'Compare Pricing',
            'manage_options',
            'compare-pricing',
            array($this, 'admin_page')
        );
    }
    
    public function init_settings() {
        // Register individual settings instead of array
        register_setting('compare_pricing_settings', 'compare_pricing_ebay_app_id');
        register_setting('compare_pricing_settings', 'compare_pricing_ebay_dev_id');
        register_setting('compare_pricing_settings', 'compare_pricing_ebay_cert_id');
        register_setting('compare_pricing_settings', 'compare_pricing_amazon_api_key');
        register_setting('compare_pricing_settings', 'compare_pricing_cache_duration');
        register_setting('compare_pricing_settings', 'compare_pricing_sandbox_mode');
        
        // eBay API Settings
        add_settings_section(
            'ebay_api_section',
            'eBay API Configuration',
            array($this, 'ebay_section_callback'),
            'compare_pricing_settings'
        );
        
        add_settings_field(
            'ebay_app_id',
            'eBay App ID (Client ID)',
            array($this, 'ebay_app_id_callback'),
            'compare_pricing_settings',
            'ebay_api_section'
        );
        
        add_settings_field(
            'ebay_dev_id',
            'eBay Developer ID',
            array($this, 'ebay_dev_id_callback'),
            'compare_pricing_settings',
            'ebay_api_section'
        );
        
        add_settings_field(
            'ebay_cert_id',
            'eBay Cert ID (Client Secret)',
            array($this, 'ebay_cert_id_callback'),
            'compare_pricing_settings',
            'ebay_api_section'
        );
        
        // Amazon API Settings
        add_settings_section(
            'amazon_api_section',
            'Amazon API Configuration',
            array($this, 'amazon_section_callback'),
            'compare_pricing_settings'
        );
        
        add_settings_field(
            'amazon_api_key',
            'Amazon API Key',
            array($this, 'amazon_access_key_callback'),
            'compare_pricing_settings',
            'amazon_api_section'
        );
        
        // General Settings
        add_settings_section(
            'general_section',
            'General Settings',
            array($this, 'general_section_callback'),
            'compare_pricing_settings'
        );
        
        add_settings_field(
            'cache_duration',
            'Cache Duration (hours)',
            array($this, 'cache_duration_callback'),
            'compare_pricing_settings',
            'general_section'
        );
        
        add_settings_field(
            'sandbox_mode',
            'Sandbox Mode',
            array($this, 'sandbox_mode_callback'),
            'compare_pricing_settings',
            'general_section'
        );
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Compare Pricing Settings</h1>
            
            <div class="notice notice-info">
                <p><strong>API Information:</strong></p>
                <ul>
                    <li><strong>eBay API:</strong> Uses eBay Developer Program APIs for product search</li>
                    <li><strong>Amazon API:</strong> Uses ASIN Data API from Traject Data for reliable Amazon product data</li>
                </ul>
            </div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('compare_pricing_settings');
                do_settings_sections('compare_pricing_settings');
                submit_button();
                ?>
            </form>
            
            <!-- API Testing Section -->
            <div class="diagnostic-section">
                <h2>🔧 API Diagnostics & Testing</h2>
                <p>Test your API connections and GTIN lookups to ensure everything is working correctly.</p>
                
                <div class="api-test-section">
                    <h3>eBay API Test</h3>
                    <p>Test your eBay Developer API credentials and connection.</p>
                    <button type="button" id="test-ebay-api" class="test-button">Test eBay API</button>
                    <div id="ebay-test-results" class="test-results"></div>
                </div>
                
                <div class="api-test-section">
                    <h3>Amazon API Test</h3>
                    <p>Test your ASIN Data API connection and credentials.</p>
                    <button type="button" id="test-amazon-api" class="test-button">Test Amazon API</button>
                    <div id="amazon-test-results" class="test-results"></div>
                </div>
                
                <div class="api-test-section">
                    <h3>GTIN Lookup Test</h3>
                    <p>Test price comparison for a specific GTIN/UPC/EAN code.</p>
                    <div class="gtin-test-controls">
                        <input type="text" id="test-gtin" placeholder="Enter GTIN/UPC/EAN" class="regular-text" />
                        <button type="button" id="test-gtin-lookup" class="test-button">Test GTIN Lookup</button>
                    </div>
                    <div class="gtin-examples">
                        <p><strong>Example GTINs to test:</strong></p>
                        <ul>
                            <li><code>3386460065947</code> - Perfume/Fragrance product</li>
                            <li><code>0885909950805</code> - Electronics product</li>
                            <li><code>0194252014233</code> - Beauty product</li>
                            <li><code>0123456789012</code> - Generic test GTIN</li>
                        </ul>
                        <p><em>Note: Results depend on product availability on eBay and Amazon.</em></p>
                    </div>
                    <div id="gtin-test-results" class="test-results"></div>
                </div>
                
                <div class="api-info-section">
                    <h3>📋 API Information</h3>
                    <div class="api-info-grid">
                        <div class="api-info-card">
                            <h4>eBay Developer API</h4>
                            <p><strong>Purpose:</strong> Search eBay marketplace for products</p>
                            <p><strong>Required:</strong> App ID, Dev ID, Cert ID</p>
                            <p><strong>Get credentials:</strong> <a href="https://developer.ebay.com/" target="_blank">eBay Developer Program</a></p>
                            <p><strong>Documentation:</strong> <a href="https://developer.ebay.com/api-docs/buy/browse/overview.html" target="_blank">Browse API Docs</a></p>
                        </div>
                        
                        <div class="api-info-card">
                            <h4>ASIN Data API (Traject Data)</h4>
                            <p><strong>Purpose:</strong> Search Amazon marketplace for products</p>
                            <p><strong>Required:</strong> API Key only</p>
                            <p><strong>Get API key:</strong> <a href="https://app.asindataapi.com/signup" target="_blank">Sign up at asindataapi.com</a></p>
                            <p><strong>Documentation:</strong> <a href="https://docs.trajectdata.com/asindataapi/product-data-api/overview" target="_blank">API Documentation</a></p>
                            <p><strong>Pricing:</strong> Pay-per-request model with free tier available</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="usage-section">
                <h2>📖 Usage Instructions</h2>
                <div class="usage-grid">
                    <div class="usage-card">
                        <h3>Shortcode Usage</h3>
                        <p>Add price comparison to any page or post:</p>
                        <code>[compare_pricing]</code>
                        <p>With custom GTIN:</p>
                        <code>[compare_pricing gtin="1234567890123"]</code>
                        <p>With title:</p>
                        <code>[compare_pricing title="Compare Prices" show_title="true"]</code>
                    </div>
                    
                    <div class="usage-card">
                        <h3>Product Integration</h3>
                        <p>The plugin automatically detects GTINs from WooCommerce products using these fields (in order of priority):</p>
                        <ol>
                            <li><code>_global_unique_id</code> - WooCommerce default GTIN field</li>
                            <li><code>_wc_gla_gtin</code> - WooCommerce Google Listings & Ads</li>
                            <li><code>_gtin</code> - Custom GTIN field</li>
                        </ol>
                    </div>
                    
                    <div class="usage-card">
                        <h3>Template Integration</h3>
                        <p>Add to your theme templates:</p>
                        <code>&lt;?php echo do_shortcode('[compare_pricing]'); ?&gt;</code>
                        <p>Or use the function directly:</p>
                        <code>&lt;?php 
if (function_exists('compare_pricing_display')) {
    compare_pricing_display($gtin);
}
?&gt;</code>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .api-test-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f9f9f9;
        }
        
        .api-info-grid, .usage-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .api-info-card, .usage-card {
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: white;
        }
        
        .api-info-card h4, .usage-card h3 {
            margin-top: 0;
            color: #0073aa;
        }
        
        .gtin-test-controls {
            margin-bottom: 15px;
        }
        
        .gtin-examples {
            margin: 15px 0;
            padding: 15px;
            background: #f0f8ff;
            border-left: 4px solid #0073aa;
            border-radius: 3px;
        }
        
        .gtin-examples ul {
            margin: 10px 0;
        }
        
        .gtin-examples code {
            background: #e1ecf4;
            padding: 2px 4px;
            border-radius: 3px;
            font-family: monospace;
        }
        </style>
        <?php
    }
    
    // Section callbacks
    public function ebay_section_callback() {
        echo '<p>Configure your eBay API credentials. Get these from <a href="https://developer.ebay.com/my/keys" target="_blank">eBay Developer Program</a>.</p>';
    }
    
    public function amazon_section_callback() {
        echo '<p>Configure your Amazon product data API settings. We use the ASIN Data API from Traject Data for reliable Amazon product information.</p>';
        echo '<p><strong>Get your API key:</strong> <a href="https://app.asindataapi.com/signup" target="_blank">Sign up at asindataapi.com</a></p>';
        echo '<p><strong>Documentation:</strong> <a href="https://docs.trajectdata.com/asindataapi/product-data-api/overview" target="_blank">API Documentation</a></p>';
    }
    
    public function general_section_callback() {
        echo '<p>General plugin settings and configuration options.</p>';
    }
    
    // Field callbacks
    public function ebay_app_id_callback() {
        $value = get_option('compare_pricing_ebay_app_id', '');
        echo '<input type="text" name="compare_pricing_ebay_app_id" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your eBay App ID from the eBay Developer Program</p>';
    }
    
    public function ebay_dev_id_callback() {
        $value = get_option('compare_pricing_ebay_dev_id', '');
        echo '<input type="text" name="compare_pricing_ebay_dev_id" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your eBay Developer ID</p>';
    }
    
    public function ebay_cert_id_callback() {
        $value = get_option('compare_pricing_ebay_cert_id', '');
        echo '<input type="text" name="compare_pricing_ebay_cert_id" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your eBay Cert ID (Client Secret)</p>';
    }
    
    public function amazon_access_key_callback() {
        $value = get_option('compare_pricing_amazon_api_key', '');
        echo '<input type="text" id="amazon_api_key" name="compare_pricing_amazon_api_key" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your ASIN Data API key from <a href="https://app.asindataapi.com/" target="_blank">asindataapi.com</a></p>';
    }
    
    public function amazon_secret_key_callback() {
        // Remove this field since we're not using AWS PA-API anymore
        echo '<p class="description"><em>Not required for ASIN Data API</em></p>';
    }
    
    public function amazon_associate_tag_callback() {
        // Remove this field since we're not using AWS PA-API anymore  
        echo '<p class="description"><em>Not required for ASIN Data API</em></p>';
    }
    
    public function cache_duration_callback() {
        $value = get_option('compare_pricing_cache_duration', 24);
        echo '<input type="number" name="compare_pricing_cache_duration" value="' . esc_attr($value) . '" min="1" max="168" />';
        echo '<p class="description">How long to cache API results (1-168 hours)</p>';
    }
    
    public function sandbox_mode_callback() {
        $value = get_option('compare_pricing_sandbox_mode', 0);
        echo '<input type="checkbox" name="compare_pricing_sandbox_mode" value="1" ' . checked($value, 1, false) . ' />';
        echo '<label>Enable sandbox mode for testing eBay API</label>';
    }
    
    // AJAX handlers for API testing
    public function ajax_test_ebay_api() {
        check_ajax_referer('test_api_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        require_once COMPARE_PRICING_PATH . 'includes/class-ebay-api.php';
        $ebay_api = new Compare_Pricing_eBay_API();
        
        $result = $ebay_api->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function ajax_test_amazon_api() {
        check_ajax_referer('test_api_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        require_once COMPARE_PRICING_PATH . 'includes/class-amazon-api.php';
        
        $options = array(
            'amazon_api_key' => get_option('compare_pricing_amazon_api_key', ''),
            'debug_mode' => get_option('compare_pricing_debug_mode', 0)
        );
        
        $amazon_api = new Compare_Pricing_Amazon_API($options);
        $result = $amazon_api->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function ajax_test_gtin_lookup() {
        check_ajax_referer('test_api_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $gtin = sanitize_text_field($_POST['gtin']);
        
        if (empty($gtin)) {
            wp_send_json_error('GTIN is required');
            return;
        }
        
        // Load API classes
        require_once COMPARE_PRICING_PATH . 'includes/class-ebay-api.php';
        require_once COMPARE_PRICING_PATH . 'includes/class-amazon-api.php';
        
        $ebay_options = array(
            'ebay_app_id' => get_option('compare_pricing_ebay_app_id', ''),
            'ebay_cert_id' => get_option('compare_pricing_ebay_cert_id', ''),
            'ebay_dev_id' => get_option('compare_pricing_ebay_dev_id', ''),
            'sandbox_mode' => get_option('compare_pricing_sandbox_mode', 0)
        );
        
        $amazon_options = array(
            'amazon_api_key' => get_option('compare_pricing_amazon_api_key', ''),
            'debug_mode' => get_option('compare_pricing_debug_mode', 0)
        );
        
        $ebay_api = new Compare_Pricing_eBay_API($ebay_options);
        $amazon_api = new Compare_Pricing_Amazon_API($amazon_options);
        
        $all_results = array();
        $errors = array();
        
        // Test eBay
        $ebay_results = $ebay_api->search_products($gtin, 5);
        if (is_wp_error($ebay_results)) {
            $errors['ebay'] = $ebay_results->get_error_message();
        } elseif (!empty($ebay_results)) {
            $all_results = array_merge($all_results, $ebay_results);
        }
        
        // Test Amazon
        $amazon_result = $amazon_api->search_products($gtin, 5);
        if ($amazon_result['success'] && !empty($amazon_result['products'])) {
            $all_results = array_merge($all_results, $amazon_result['products']);
        } elseif (!$amazon_result['success']) {
            $errors['amazon'] = $amazon_result['error'];
        }
        
        if (empty($all_results)) {
            $error_message = 'No results found for GTIN: ' . $gtin;
            if (!empty($errors)) {
                $error_message .= '. Errors: ' . implode(', ', $errors);
            }
            wp_send_json_error($error_message);
            return;
        }
        
        // Sort by price
        usort($all_results, function($a, $b) {
            return $a['price'] <=> $b['price'];
        });
        
        // Get best from each platform
        $ebay_best = null;
        $amazon_best = null;
        $overall_best = $all_results[0];
        
        foreach ($all_results as $result) {
            if ($result['source'] === 'ebay' && !$ebay_best) {
                $ebay_best = $result;
            }
            if ($result['source'] === 'amazon' && !$amazon_best) {
                $amazon_best = $result;
            }
            if ($ebay_best && $amazon_best) break;
        }
        
        wp_send_json_success(array(
            'overall_best' => $overall_best,
            'ebay_best' => $ebay_best,
            'amazon_best' => $amazon_best,
            'total_results' => count($all_results),
            'ebay_count' => count(array_filter($all_results, function($r) { return $r['source'] === 'ebay'; })),
            'amazon_count' => count(array_filter($all_results, function($r) { return $r['source'] === 'amazon'; })),
            'errors' => $errors
        ));
    }
    
    public function enqueue_admin_scripts($hook) {
        // Only load on our settings page
        if ($hook !== 'settings_page_compare-pricing') {
            return;
        }
        
        // Enqueue admin styles
        wp_enqueue_style(
            'compare-pricing-admin',
            COMPARE_PRICING_URL . 'admin/css/admin.css',
            array(),
            COMPARE_PRICING_VERSION
        );
        
        // Enqueue admin scripts
        wp_enqueue_script(
            'compare-pricing-admin',
            COMPARE_PRICING_URL . 'admin/js/admin.js',
            array('jquery'),
            COMPARE_PRICING_VERSION,
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('compare-pricing-admin', 'comparePricingAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('test_api_nonce'),
            'strings' => array(
                'testing' => 'Testing...',
                'success' => 'Success!',
                'error' => 'Error!',
                'warning' => 'Warning!'
            )
        ));
    }
} 