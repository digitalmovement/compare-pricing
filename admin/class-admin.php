<?php

class Compare_Pricing_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_compare_pricing_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_compare_pricing_clear_failed_lookups', array($this, 'ajax_clear_failed_lookups'));
        add_action('wp_ajax_compare_pricing_retry_lookup', array($this, 'ajax_retry_lookup'));
        add_action('wp_ajax_compare_pricing_test_api', array($this, 'ajax_test_api'));
        add_action('wp_ajax_compare_pricing_test_amazon_api', array($this, 'ajax_test_amazon_api'));
        add_action('wp_ajax_test_ebay_api', array($this, 'ajax_test_ebay_api'));
        add_action('wp_ajax_test_amazon_api', array($this, 'ajax_test_amazon_api'));
        add_action('wp_ajax_test_gtin_lookup', array($this, 'ajax_test_gtin_lookup'));
        add_action('wp_ajax_test_location_api', array($this, 'ajax_test_location_api'));
        add_action('wp_ajax_compare_pricing_track_click', array($this, 'ajax_track_click'));
        add_action('wp_ajax_nopriv_compare_pricing_track_click', array($this, 'ajax_track_click'));
    }
    
    public function add_admin_menu() {
        // Add top-level menu
        add_menu_page(
            'Compare Pricing',
            'Compare Pricing',
            'manage_options',
            'compare-pricing',
            array($this, 'admin_page'),
            'dashicons-chart-line',
            30
        );
        
        // Add Settings submenu
        add_submenu_page(
            'compare-pricing',
            'Settings',
            'Settings',
            'manage_options',
            'compare-pricing',
            array($this, 'admin_page')
        );
        
        // Add Affiliate IDs submenu
        add_submenu_page(
            'compare-pricing',
            'Affiliate IDs',
            'Affiliate IDs',
            'manage_options',
            'compare-pricing-affiliates',
            array($this, 'affiliates_page')
        );
        
        // Add Stats submenu
        add_submenu_page(
            'compare-pricing',
            'Statistics',
            'Statistics',
            'manage_options',
            'compare-pricing-stats',
            array($this, 'stats_page')
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
        register_setting('compare_pricing_settings', 'compare_pricing_min_keyword_matches');
        register_setting('compare_pricing_settings', 'compare_pricing_debug_mode');
        
        // Register affiliate settings
        register_setting('compare_pricing_affiliate_settings', 'compare_pricing_affiliate_ids');
        
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
        
        add_settings_field(
            'min_keyword_matches',
            'Minimum Keyword Matches',
            array($this, 'min_keyword_matches_callback'),
            'compare_pricing_settings',
            'general_section'
        );
        
        add_settings_field(
            'debug_mode',
            'Debug Mode',
            array($this, 'debug_mode_callback'),
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
            
            <!-- Cache and Performance Stats -->
            <div class="diagnostic-section">
                <h2>📊 Performance Statistics</h2>
                <?php $this->display_cache_stats(); ?>
            </div>
            
            <!-- Failed Lookups -->
            <div class="diagnostic-section">
                <h2>⚠️ Failed Product Lookups</h2>
                <?php $this->display_failed_lookups(); ?>
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
                    <h3>Location Detection Test</h3>
                    <p>Test the freeipapi.com service used for detecting user location and regional pricing.</p>
                    <button type="button" id="test-location-api" class="test-button">Test Location API</button>
                    <div id="location-test-results" class="test-results"></div>
                </div>
                
                <div class="api-test-section">
                    <h3>GTIN Lookup Test</h3>
                    <div class="gtin-test-controls">
                        <input type="text" id="test-gtin" placeholder="Enter GTIN/UPC/EAN" />
                        <button type="button" id="test-gtin-lookup" class="test-button">Test GTIN Lookup</button>
                        <button type="button" id="clear-cache" class="test-button" style="background: #dc3545;">Clear All Cache</button>
                        <button type="button" id="clear-failed-lookups" class="test-button" style="background: #ffc107; color: #000;">Clear Failed Lookups</button>
                    </div>
                    <div class="gtin-examples">
                        <strong>Example GTINs to test:</strong><br>
                        • 3386460065947 (Perfume)<br>
                        • 0885909950805 (Electronics)<br>
                        • 0123456789012 (Generic)
                    </div>
                    <div id="gtin-test-results" class="test-results"></div>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: white;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #0073aa;
            display: block;
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        .failed-lookups-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .failed-lookups-table th,
        .failed-lookups-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .failed-lookups-table th {
            background: #f5f5f5;
            font-weight: bold;
        }
        
        .failed-lookups-table tr:hover {
            background: #f9f9f9;
        }
        
        .error-details {
            font-size: 0.9em;
            color: #666;
        }
        
        .product-link {
            color: #0073aa;
            text-decoration: none;
        }
        
        .product-link:hover {
            text-decoration: underline;
        }
        
        .retry-button {
            background: #0073aa;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.9em;
        }
        
        .retry-button:hover {
            background: #005a87;
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
    
    public function min_keyword_matches_callback() {
        $value = get_option('compare_pricing_min_keyword_matches', 2);
        echo '<select name="compare_pricing_min_keyword_matches">';
        echo '<option value="1"' . selected($value, 1, false) . '>1 keyword match (loose)</option>';
        echo '<option value="2"' . selected($value, 2, false) . '>2 keyword matches (recommended)</option>';
        echo '<option value="3"' . selected($value, 3, false) . '>3 keyword matches (strict)</option>';
        echo '</select>';
        echo '<p class="description">How many keywords must match between your product and API results to be considered relevant</p>';
    }
    
    public function debug_mode_callback() {
        $value = get_option('compare_pricing_debug_mode', 0);
        echo '<input type="checkbox" name="compare_pricing_debug_mode" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">Enable detailed logging for troubleshooting. Logs will appear in your WordPress debug.log file.</p>';
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
        // Only load on our settings page - check for both main page and subpages
        if (strpos($hook, 'compare-pricing') === false) {
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
    
    private function display_cache_stats() {
        global $wpdb;
        
        // Get cache statistics
        $cache_stats = array(
            'total_cached' => 0,
            'successful_cached' => 0,
            'failed_cached' => 0,
            'cache_hits_today' => 0,
            'api_calls_today' => 0,
            'cache_size_mb' => 0
        );
        
        // Count cached entries
        $cache_count = wp_cache_get('compare_pricing_cache_count');
        if ($cache_count === false) {
            // Count transients with our prefix
            $transient_count = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_compare_pricing_%'"
            );
            $cache_stats['total_cached'] = intval($transient_count);
            wp_cache_set('compare_pricing_cache_count', $cache_stats['total_cached'], '', 300); // Cache for 5 minutes
        } else {
            $cache_stats['total_cached'] = $cache_count;
        }
        
        // Get today's stats
        $today_stats = get_option('compare_pricing_daily_stats_' . date('Y-m-d'), array(
            'cache_hits' => 0,
            'api_calls' => 0,
            'successful_lookups' => 0,
            'failed_lookups' => 0
        ));
        
        $cache_stats['cache_hits_today'] = $today_stats['cache_hits'];
        $cache_stats['api_calls_today'] = $today_stats['api_calls'];
        
        // Calculate cache size (approximate)
        $cache_size_bytes = $wpdb->get_var(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE '_transient_compare_pricing_%'"
        );
        $cache_stats['cache_size_mb'] = round($cache_size_bytes / 1024 / 1024, 2);
        
        ?>
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number"><?php echo number_format($cache_stats['total_cached']); ?></span>
                <div class="stat-label">Cached Results</div>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo number_format($cache_stats['cache_hits_today']); ?></span>
                <div class="stat-label">Cache Hits Today</div>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo number_format($cache_stats['api_calls_today']); ?></span>
                <div class="stat-label">API Calls Today</div>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $cache_stats['cache_size_mb']; ?> MB</span>
                <div class="stat-label">Cache Size</div>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo number_format($today_stats['successful_lookups']); ?></span>
                <div class="stat-label">Successful Lookups Today</div>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo number_format($today_stats['failed_lookups']); ?></span>
                <div class="stat-label">Failed Lookups Today</div>
            </div>
        </div>
        
        <p><strong>Cache Efficiency:</strong> 
        <?php 
        $total_requests = $cache_stats['cache_hits_today'] + $cache_stats['api_calls_today'];
        if ($total_requests > 0) {
            $efficiency = round(($cache_stats['cache_hits_today'] / $total_requests) * 100, 1);
            echo $efficiency . '% of requests served from cache today';
        } else {
            echo 'No requests today';
        }
        ?>
        </p>
        <?php
    }
    
    private function display_failed_lookups() {
        $failed_lookups = get_option('compare_pricing_failed_lookups', array());
        
        if (empty($failed_lookups)) {
            echo '<p>✅ No failed lookups recorded. All products are finding competitive pricing data!</p>';
            return;
        }
        
        // Sort by most recent first
        usort($failed_lookups, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        // Group by failure type for summary
        $failure_types = array();
        foreach ($failed_lookups as $lookup) {
            $type = isset($lookup['failure_type']) ? $lookup['failure_type'] : 'unknown';
            if (!isset($failure_types[$type])) {
                $failure_types[$type] = 0;
            }
            $failure_types[$type]++;
        }
        
        echo '<div class="failure-summary">';
        echo '<h4>Failure Summary:</h4>';
        echo '<ul>';
        foreach ($failure_types as $type => $count) {
            $type_label = $this->get_failure_type_label($type);
            echo '<li><strong>' . $type_label . ':</strong> ' . $count . ' products</li>';
        }
        echo '</ul>';
        echo '</div>';
        
        echo '<p>The following products failed to return relevant results. Review their GTINs and product information:</p>';
        
        echo '<table class="failed-lookups-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Product</th>';
        echo '<th>GTIN</th>';
        echo '<th>Failure Type</th>';
        echo '<th>Last Attempt</th>';
        echo '<th>Attempts</th>';
        echo '<th>Error Details</th>';
        echo '<th>Actions</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach (array_slice($failed_lookups, 0, 50) as $index => $lookup) { // Show last 50
            $product_id = $lookup['product_id'];
            $product_title = 'Unknown Product';
            $edit_link = '#';
            
            if ($product_id && $product_id !== 'custom') {
                $product = wc_get_product($product_id);
                if ($product) {
                    $product_title = $product->get_name();
                    $edit_link = admin_url('post.php?post=' . $product_id . '&action=edit');
                }
            }
            
            $failure_type = isset($lookup['failure_type']) ? $lookup['failure_type'] : 'unknown';
            $failure_reason = isset($lookup['failure_reason']) ? $lookup['failure_reason'] : 'Unknown failure';
            $attempt_count = isset($lookup['attempt_count']) ? $lookup['attempt_count'] : 1;
            
            echo '<tr class="failure-type-' . esc_attr($failure_type) . '">';
            echo '<td>';
            if ($edit_link !== '#') {
                echo '<a href="' . esc_url($edit_link) . '" class="product-link" target="_blank">' . esc_html($product_title) . '</a>';
            } else {
                echo esc_html($product_title);
            }
            echo '</td>';
            echo '<td><code>' . esc_html($lookup['gtin']) . '</code></td>';
            echo '<td>';
            echo '<span class="failure-badge failure-' . esc_attr($failure_type) . '">';
            echo esc_html($this->get_failure_type_label($failure_type));
            echo '</span>';
            echo '<br><small>' . esc_html($failure_reason) . '</small>';
            echo '</td>';
            echo '<td>' . esc_html(date('M j, Y g:i A', strtotime($lookup['timestamp']))) . '</td>';
            echo '<td>' . $attempt_count . '</td>';
            echo '<td class="error-details">';
            if (!empty($lookup['errors'])) {
                foreach ($lookup['errors'] as $platform => $error) {
                    echo '<strong>' . ucfirst($platform) . ':</strong> ' . esc_html($error) . '<br>';
                }
            } else {
                echo 'No specific errors recorded';
            }
            echo '</td>';
            echo '<td>';
            echo '<button class="retry-button" onclick="retryLookup(\'' . esc_js($lookup['gtin']) . '\', ' . $index . ')">Retry</button>';
            if ($edit_link !== '#') {
                echo '<br><a href="' . esc_url($edit_link) . '" class="edit-product-link" target="_blank">Edit Product</a>';
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        if (count($failed_lookups) > 50) {
            echo '<p><em>Showing 50 most recent failed lookups. Total: ' . count($failed_lookups) . '</em></p>';
        }
    }

    private function get_failure_type_label($type) {
        switch ($type) {
            case 'keyword_mismatch':
                return 'Keyword Mismatch';
            case 'no_apis':
                return 'No APIs Configured';
            case 'api_failure':
                return 'API Failure';
            case 'no_results':
                return 'No Results Found';
            default:
                return 'Unknown';
        }
    }

    public function ajax_clear_cache() {
        check_ajax_referer('test_api_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        global $wpdb;
        
        // Delete all compare pricing transients
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_compare_pricing_%' OR option_name LIKE '_transient_timeout_compare_pricing_%'"
        );
        
        // Clear cache count
        wp_cache_delete('compare_pricing_cache_count');
        
        wp_send_json_success(array(
            'message' => 'Cache cleared successfully',
            'deleted_entries' => $deleted / 2 // Divide by 2 because each transient has a timeout entry
        ));
    }

    public function ajax_clear_failed_lookups() {
        check_ajax_referer('test_api_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        delete_option('compare_pricing_failed_lookups');
        
        wp_send_json_success(array(
            'message' => 'Failed lookups cleared successfully'
        ));
    }

    public function ajax_retry_lookup() {
        check_ajax_referer('test_api_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $gtin = sanitize_text_field($_POST['gtin']);
        $index = intval($_POST['index']);
        
        if (empty($gtin)) {
            wp_send_json_error('GTIN is required');
            return;
        }
        
        // Clear cache for this GTIN
        $cache_key = 'compare_pricing_' . md5($gtin);
        delete_transient($cache_key);
        
        // Remove from failed lookups
        $failed_lookups = get_option('compare_pricing_failed_lookups', array());
        if (isset($failed_lookups[$index])) {
            unset($failed_lookups[$index]);
            $failed_lookups = array_values($failed_lookups); // Re-index array
            update_option('compare_pricing_failed_lookups', $failed_lookups);
        }
        
        // Try the lookup again
        require_once COMPARE_PRICING_PATH . 'includes/class-compare-pricing.php';
        $compare_pricing = new Compare_Pricing();
        
        // This will be handled by the existing test_gtin_lookup method
        $_POST['gtin'] = $gtin;
        $this->ajax_test_gtin_lookup();
    }

    public function ajax_test_location_api() {
        check_ajax_referer('test_api_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $debug_info = array();
        
        // Test freeipapi.com
        $debug_info['api_test'] = array(
            'status' => 'checking',
            'title' => 'Location API Test',
            'message' => 'Testing freeipapi.com service...'
        );
        
        $response = wp_remote_get('https://freeipapi.com/api/json', array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'WordPress Compare Pricing Plugin Test'
            )
        ));
        
        if (is_wp_error($response)) {
            $debug_info['api_test'] = array(
                'status' => 'error',
                'title' => 'Location API Test',
                'message' => 'Failed to connect to freeipapi.com: ' . $response->get_error_message(),
                'help' => 'Check your server\'s internet connection and firewall settings'
            );
            
            wp_send_json_success(array(
                'success' => false,
                'debug' => $debug_info
            ));
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $debug_info['api_test'] = array(
                'status' => 'error',
                'title' => 'Location API Test',
                'message' => 'Invalid JSON response from freeipapi.com',
                'details' => array(
                    'JSON Error' => json_last_error_msg(),
                    'Raw Response' => substr($body, 0, 200) . '...'
                )
            );
            
            wp_send_json_success(array(
                'success' => false,
                'debug' => $debug_info
            ));
            return;
        }
        
        // Check if we got the expected data
        if (!isset($data['countryCode']) || !isset($data['countryName'])) {
            $debug_info['api_test'] = array(
                'status' => 'warning',
                'title' => 'Location API Test',
                'message' => 'Unexpected response format from freeipapi.com',
                'details' => array(
                    'Expected Fields' => 'countryCode, countryName',
                    'Received Fields' => implode(', ', array_keys($data)),
                    'Sample Data' => $data
                )
            );
            
            wp_send_json_success(array(
                'success' => false,
                'debug' => $debug_info
            ));
            return;
        }
        
        // Success!
        $debug_info['api_test'] = array(
            'status' => 'success',
            'title' => 'Location API Test',
            'message' => 'Successfully detected location: ' . $data['countryName'] . ' (' . $data['countryCode'] . ')',
            'details' => array(
                'Country Code' => $data['countryCode'],
                'Country Name' => $data['countryName'],
                'IP Address' => isset($data['ipAddress']) ? $data['ipAddress'] : 'Not provided',
                'City' => isset($data['cityName']) ? $data['cityName'] : 'Not provided',
                'Region' => isset($data['regionName']) ? $data['regionName'] : 'Not provided'
            ),
            'note' => 'Location detection is working correctly'
        );
        
        // Test marketplace mapping
        $marketplace_test = $this->test_marketplace_mapping($data['countryCode']);
        $debug_info['marketplace_test'] = $marketplace_test;
        
        wp_send_json_success(array(
            'success' => true,
            'debug' => $debug_info
        ));
    }

    private function test_marketplace_mapping($country_code) {
        $supported_countries = array(
            'US' => 'United States (eBay.com, Amazon.com)',
            'GB' => 'United Kingdom (eBay.co.uk, Amazon.co.uk)',
            'DE' => 'Germany (eBay.de, Amazon.de)',
            'FR' => 'France (eBay.fr, Amazon.fr)',
            'IT' => 'Italy (eBay.it, Amazon.it)',
            'ES' => 'Spain (eBay.es, Amazon.es)',
            'CA' => 'Canada (eBay.ca, Amazon.ca)',
            'AU' => 'Australia (eBay.com.au, Amazon.com.au)',
            'JP' => 'Japan (Amazon.co.jp)',
            'IN' => 'India (Amazon.in)'
        );
        
        if (isset($supported_countries[$country_code])) {
            return array(
                'status' => 'success',
                'title' => 'Marketplace Mapping',
                'message' => 'Country is supported: ' . $supported_countries[$country_code],
                'note' => 'Users from this location will see localized pricing'
            );
        } else {
            return array(
                'status' => 'warning',
                'title' => 'Marketplace Mapping',
                'message' => 'Country not specifically supported, will default to US marketplaces',
                'help' => 'Consider adding support for this country if you have many users from this location'
            );
        }
    }

    public function stats_page() {
        // Handle date filtering
        $date_filter = isset($_GET['date_filter']) ? sanitize_text_field($_GET['date_filter']) : 'week';
        $custom_start = isset($_GET['custom_start']) ? sanitize_text_field($_GET['custom_start']) : '';
        $custom_end = isset($_GET['custom_end']) ? sanitize_text_field($_GET['custom_end']) : '';
        $sort_by = isset($_GET['sort_by']) ? sanitize_text_field($_GET['sort_by']) : 'views';
        $sort_order = isset($_GET['sort_order']) ? sanitize_text_field($_GET['sort_order']) : 'desc';
        
        // Get stats data
        $stats_data = $this->get_stats_data($date_filter, $custom_start, $custom_end, $sort_by, $sort_order);
        
        ?>
        <div class="wrap">
            <h1>Compare Pricing Statistics</h1>
            
            <!-- Filters -->
            <div class="stats-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="compare-pricing-stats">
                    
                    <div class="filter-row">
                        <label for="date_filter">Time Period:</label>
                        <select name="date_filter" id="date_filter" onchange="toggleCustomDates()">
                            <option value="today" <?php selected($date_filter, 'today'); ?>>Today</option>
                            <option value="week" <?php selected($date_filter, 'week'); ?>>This Week</option>
                            <option value="month" <?php selected($date_filter, 'month'); ?>>This Month</option>
                            <option value="year" <?php selected($date_filter, 'year'); ?>>This Year</option>
                            <option value="custom" <?php selected($date_filter, 'custom'); ?>>Custom Range</option>
                        </select>
                        
                        <div id="custom-dates" style="<?php echo $date_filter === 'custom' ? 'display:inline-block;' : 'display:none;'; ?>">
                            <input type="date" name="custom_start" value="<?php echo esc_attr($custom_start); ?>" placeholder="Start Date">
                            <input type="date" name="custom_end" value="<?php echo esc_attr($custom_end); ?>" placeholder="End Date">
                        </div>
                        
                        <label for="sort_by">Sort by:</label>
                        <select name="sort_by" id="sort_by">
                            <option value="views" <?php selected($sort_by, 'views'); ?>>Views</option>
                            <option value="clicks" <?php selected($sort_by, 'clicks'); ?>>Clicks</option>
                            <option value="ctr" <?php selected($sort_by, 'ctr'); ?>>Click-through Rate</option>
                            <option value="product_title" <?php selected($sort_by, 'product_title'); ?>>Product Name</option>
                            <option value="last_viewed" <?php selected($sort_by, 'last_viewed'); ?>>Last Viewed</option>
                        </select>
                        
                        <select name="sort_order" id="sort_order">
                            <option value="desc" <?php selected($sort_order, 'desc'); ?>>Descending</option>
                            <option value="asc" <?php selected($sort_order, 'asc'); ?>>Ascending</option>
                        </select>
                        
                        <input type="submit" class="button" value="Filter">
                    </div>
                </form>
            </div>
            
            <!-- Master Stats -->
            <div class="stats-overview">
                <div class="stats-cards">
                    <div class="stats-card">
                        <h3>Total Views</h3>
                        <div class="stats-number"><?php echo number_format($stats_data['totals']['views']); ?></div>
                    </div>
                    <div class="stats-card">
                        <h3>Total Clicks</h3>
                        <div class="stats-number"><?php echo number_format($stats_data['totals']['clicks']); ?></div>
                    </div>
                    <div class="stats-card">
                        <h3>Click-through Rate</h3>
                        <div class="stats-number"><?php echo $stats_data['totals']['ctr']; ?>%</div>
                    </div>
                    <div class="stats-card">
                        <h3>Unique Products</h3>
                        <div class="stats-number"><?php echo number_format($stats_data['totals']['unique_products']); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Detailed Stats Table -->
            <div class="stats-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>GTIN</th>
                            <th>Views</th>
                            <th>Clicks</th>
                            <th>CTR</th>
                            <th>Best Price Found</th>
                            <th>Top Source</th>
                            <th>Last Viewed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($stats_data['products'])): ?>
                            <?php foreach ($stats_data['products'] as $product): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($product['product_title']); ?></strong>
                                        <?php if ($product['product_id'] && $product['product_id'] !== 'custom'): ?>
                                            <br><small><a href="<?php echo get_edit_post_link($product['product_id']); ?>" target="_blank">Edit Product</a></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($product['gtin']); ?></td>
                                    <td><?php echo number_format($product['views']); ?></td>
                                    <td><?php echo number_format($product['clicks']); ?></td>
                                    <td><?php echo $product['ctr']; ?>%</td>
                                    <td>
                                        <?php if ($product['best_price']): ?>
                                            <?php echo esc_html($product['currency_symbol'] . number_format($product['best_price'], 2)); ?>
                                        <?php else: ?>
                                            <span class="no-data">No data</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($product['top_source']); ?></td>
                                    <td><?php echo esc_html($product['last_viewed']); ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=compare-pricing-stats&product_detail=' . urlencode($product['gtin'])); ?>" class="button button-small">View Details</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">No data available for the selected period.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (isset($_GET['product_detail'])): ?>
                <?php $this->render_product_detail_modal($_GET['product_detail']); ?>
            <?php endif; ?>
        </div>
        
        <script>
        function toggleCustomDates() {
            var filter = document.getElementById('date_filter').value;
            var customDates = document.getElementById('custom-dates');
            customDates.style.display = filter === 'custom' ? 'inline-block' : 'none';
        }
        </script>
        
        <style>
        .stats-filters {
            background: #fff;
            padding: 15px;
            margin: 20px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .filter-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .stats-overview {
            margin: 20px 0;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stats-card {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
        }
        
        .stats-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        .stats-number {
            font-size: 32px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .stats-table-container {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .no-data {
            color: #999;
            font-style: italic;
        }
        
        #custom-dates {
            margin-left: 10px;
        }
        
        #custom-dates input {
            margin-right: 5px;
        }
        </style>
        <?php
    }

    private function get_stats_data($date_filter, $custom_start, $custom_end, $sort_by, $sort_order) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'compare_pricing_stats';
        
        // Build date condition
        $date_condition = $this->build_date_condition($date_filter, $custom_start, $custom_end);
        
        // Get totals
        $totals_query = "
            SELECT 
                SUM(views) as total_views,
                SUM(clicks) as total_clicks,
                COUNT(DISTINCT gtin) as unique_products
            FROM {$table_name} 
            WHERE {$date_condition}
        ";
        
        $totals = $wpdb->get_row($totals_query, ARRAY_A);
        $ctr = $totals['total_views'] > 0 ? round(($totals['total_clicks'] / $totals['total_views']) * 100, 2) : 0;
        
        // Get product stats
        $sort_column = $this->get_sort_column($sort_by);
        $products_query = "
            SELECT 
                gtin,
                product_id,
                product_title,
                SUM(views) as views,
                SUM(clicks) as clicks,
                best_price,
                currency_symbol,
                top_source,
                MAX(last_viewed) as last_viewed
            FROM {$table_name} 
            WHERE {$date_condition}
            GROUP BY gtin, product_id, product_title
            ORDER BY {$sort_column} {$sort_order}
        ";
        
        $products = $wpdb->get_results($products_query, ARRAY_A);
        
        // Calculate CTR for each product
        foreach ($products as &$product) {
            $product['ctr'] = $product['views'] > 0 ? round(($product['clicks'] / $product['views']) * 100, 2) : 0;
            $product['last_viewed'] = $product['last_viewed'] ? date('M j, Y g:i A', strtotime($product['last_viewed'])) : 'Never';
            $product['top_source'] = $product['top_source'] ?: 'Unknown';
        }
        
        return array(
            'totals' => array(
                'views' => (int)$totals['total_views'],
                'clicks' => (int)$totals['total_clicks'],
                'ctr' => $ctr,
                'unique_products' => (int)$totals['unique_products']
            ),
            'products' => $products
        );
    }

    private function build_date_condition($date_filter, $custom_start, $custom_end) {
        switch ($date_filter) {
            case 'today':
                return "DATE(created_at) = CURDATE()";
            case 'week':
                return "created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            case 'month':
                return "created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            case 'year':
                return "created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            case 'custom':
                if ($custom_start && $custom_end) {
                    return "DATE(created_at) BETWEEN '" . esc_sql($custom_start) . "' AND '" . esc_sql($custom_end) . "'";
                }
                return "1=1"; // No filter if dates not provided
            default:
                return "created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
        }
    }

    private function get_sort_column($sort_by) {
        switch ($sort_by) {
            case 'views':
                return 'views';
            case 'clicks':
                return 'clicks';
            case 'ctr':
                return '(clicks/views)';
            case 'product_title':
                return 'product_title';
            case 'last_viewed':
                return 'last_viewed';
            default:
                return 'views';
        }
    }

    private function render_product_detail_modal($gtin) {
        // Implement the logic to render the product detail modal
        // This is a placeholder and should be replaced with the actual implementation
        echo '<div class="wrap">';
        echo '<h1>Product Details for GTIN: ' . esc_html($gtin) . '</h1>';
        echo '<p>This is a placeholder for the product detail modal. The actual implementation should be here.</p>';
        echo '</div>';
    }

    public function ajax_track_click() {
        check_ajax_referer('compare_pricing_nonce', 'nonce');
        
        $gtin = sanitize_text_field($_POST['gtin']);
        $product_id = sanitize_text_field($_POST['product_id']);
        $source = sanitize_text_field($_POST['source']); // 'ebay' or 'amazon'
        $price = floatval($_POST['price']);
        $currency_symbol = sanitize_text_field($_POST['currency_symbol']);
        
        $this->track_click($gtin, $product_id, $source, $price, $currency_symbol);
        
        wp_send_json_success('Click tracked');
    }

    private function track_click($gtin, $product_id, $source, $price, $currency_symbol) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'compare_pricing_stats';
        
        // Get or create stats record
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE gtin = %s AND product_id = %s AND DATE(created_at) = CURDATE()",
            $gtin, $product_id
        ));
        
        if ($existing) {
            // Update existing record
            $wpdb->update(
                $table_name,
                array(
                    'clicks' => $existing->clicks + 1,
                    'top_source' => $source,
                    'best_price' => $price,
                    'currency_symbol' => $currency_symbol,
                    'last_clicked' => current_time('mysql')
                ),
                array('id' => $existing->id)
            );
        } else {
            // This shouldn't happen if view was tracked first, but handle it
            $product_title = $this->get_product_title_for_stats($product_id, $gtin);
            
            $wpdb->insert(
                $table_name,
                array(
                    'gtin' => $gtin,
                    'product_id' => $product_id,
                    'product_title' => $product_title,
                    'views' => 0,
                    'clicks' => 1,
                    'top_source' => $source,
                    'best_price' => $price,
                    'currency_symbol' => $currency_symbol,
                    'created_at' => current_time('mysql'),
                    'last_viewed' => current_time('mysql'),
                    'last_clicked' => current_time('mysql')
                )
            );
        }
    }

    private function get_product_title_for_stats($product_id, $gtin) {
        if ($product_id && $product_id !== 'custom') {
            $product = wc_get_product($product_id);
            if ($product) {
                return $product->get_name();
            }
        }
        return 'Product with GTIN: ' . $gtin;
    }

    public function affiliates_page() {
        // Handle form submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['affiliate_nonce'], 'save_affiliate_ids')) {
            $affiliate_ids = array();
            
            // Process eBay affiliate IDs
            if (isset($_POST['ebay_affiliate_ids'])) {
                foreach ($_POST['ebay_affiliate_ids'] as $country => $id) {
                    if (!empty($id)) {
                        $affiliate_ids['ebay'][$country] = sanitize_text_field($id);
                    }
                }
            }
            
            // Process Amazon affiliate IDs
            if (isset($_POST['amazon_affiliate_ids'])) {
                foreach ($_POST['amazon_affiliate_ids'] as $country => $id) {
                    if (!empty($id)) {
                        $affiliate_ids['amazon'][$country] = sanitize_text_field($id);
                    }
                }
            }
            
            update_option('compare_pricing_affiliate_ids', $affiliate_ids);
            echo '<div class="notice notice-success"><p>Affiliate IDs saved successfully!</p></div>';
        }
        
        $affiliate_ids = get_option('compare_pricing_affiliate_ids', array());
        
        // Define supported regions
        $regions = array(
            'US' => 'United States',
            'GB' => 'United Kingdom', 
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'JP' => 'Japan',
            'IN' => 'India'
        );
        
        ?>
        <div class="wrap">
            <h1>Affiliate IDs Configuration</h1>
            
            <div class="notice notice-info">
                <p><strong>About Affiliate IDs:</strong></p>
                <ul>
                    <li>Configure your affiliate/partner IDs for different regions to earn commissions from price comparison clicks</li>
                    <li>eBay Partner Network IDs will be added to eBay links</li>
                    <li>Amazon Associate IDs will be added to Amazon links</li>
                    <li>Users will see links with the appropriate affiliate ID based on their detected location</li>
                </ul>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('save_affiliate_ids', 'affiliate_nonce'); ?>
                
                <!-- eBay Affiliate IDs -->
                <div class="affiliate-section">
                    <h2>🛒 eBay Partner Network IDs</h2>
                    <p>Enter your eBay Partner Network campaign IDs for each region. <a href="https://partnernetwork.ebay.com/" target="_blank">Get your IDs here</a></p>
                    
                    <table class="form-table">
                        <?php foreach ($regions as $code => $name): ?>
                            <tr>
                                <th scope="row">
                                    <label for="ebay_<?php echo strtolower($code); ?>"><?php echo esc_html($name); ?> (<?php echo $code; ?>)</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="ebay_<?php echo strtolower($code); ?>" 
                                           name="ebay_affiliate_ids[<?php echo $code; ?>]" 
                                           value="<?php echo esc_attr(isset($affiliate_ids['ebay'][$code]) ? $affiliate_ids['ebay'][$code] : ''); ?>" 
                                           class="regular-text" 
                                           placeholder="Enter eBay Partner Network ID" />
                                    <p class="description">Your eBay Partner Network campaign ID for <?php echo esc_html($name); ?></p>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                
                <!-- Amazon Affiliate IDs -->
                <div class="affiliate-section">
                    <h2>📦 Amazon Associate IDs</h2>
                    <p>Enter your Amazon Associate tracking IDs for each region. <a href="https://affiliate-program.amazon.com/" target="_blank">Join the Amazon Associates program</a></p>
                    
                    <table class="form-table">
                        <?php foreach ($regions as $code => $name): ?>
                            <tr>
                                <th scope="row">
                                    <label for="amazon_<?php echo strtolower($code); ?>"><?php echo esc_html($name); ?> (<?php echo $code; ?>)</label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="amazon_<?php echo strtolower($code); ?>" 
                                           name="amazon_affiliate_ids[<?php echo $code; ?>]" 
                                           value="<?php echo esc_attr(isset($affiliate_ids['amazon'][$code]) ? $affiliate_ids['amazon'][$code] : ''); ?>" 
                                           class="regular-text" 
                                           placeholder="Enter Amazon Associate ID" />
                                    <p class="description">Your Amazon Associate tracking ID for <?php echo esc_html($name); ?></p>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                
                <?php submit_button('Save Affiliate IDs'); ?>
            </form>
            
            <!-- Usage Information -->
            <div class="usage-info">
                <h2>📋 How It Works</h2>
                <div class="info-grid">
                    <div class="info-card">
                        <h3>Location Detection</h3>
                        <p>The plugin automatically detects user location using their IP address and shows prices from the appropriate regional marketplace.</p>
                    </div>
                    <div class="info-card">
                        <h3>Affiliate Link Generation</h3>
                        <p>When users click on price comparison results, they'll be redirected with your affiliate ID for that region, helping you earn commissions.</p>
                    </div>
                    <div class="info-card">
                        <h3>Fallback Behavior</h3>
                        <p>If no affiliate ID is configured for a user's region, the link will use your US affiliate ID as a fallback, or show without affiliate tracking if none is set.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .affiliate-section {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .affiliate-section h2 {
            margin-top: 0;
            color: #0073aa;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .info-card {
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f9f9f9;
        }
        
        .info-card h3 {
            margin-top: 0;
            color: #0073aa;
        }
        
        .usage-info {
            margin-top: 30px;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        </style>
        <?php
    }

    /**
     * Get affiliate ID for a specific platform and country
     */
    public static function get_affiliate_id($platform, $country_code = null) {
        $affiliate_ids = get_option('compare_pricing_affiliate_ids', array());
        
        if (!$country_code) {
            // Try to detect user's country from various sources
            $country_code = self::detect_user_country();
        }
        
        // Check if we have an affiliate ID for this platform and country
        if (isset($affiliate_ids[$platform][$country_code])) {
            return $affiliate_ids[$platform][$country_code];
        }
        
        // Fallback to US if available
        if (isset($affiliate_ids[$platform]['US'])) {
            return $affiliate_ids[$platform]['US'];
        }
        
        return null;
    }
    
    /**
     * Detect user's country from various sources
     */
    private static function detect_user_country() {
        // First try server-side geolocation methods
        
        // Method 1: CloudFlare CF-IPCountry header
        if (isset($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            $country_code = strtoupper($_SERVER['HTTP_CF_IPCOUNTRY']);
            if (strlen($country_code) === 2 && $country_code !== 'XX') {
                return $country_code;
            }
        }
        
        // Method 2: Check if GeoIP extension is loaded and get country
        if (function_exists('geoip_country_code_by_name')) {
            $ip = self::get_user_ip();
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $country_code = geoip_country_code_by_name($ip);
                if ($country_code && strlen($country_code) === 2) {
                    return $country_code;
                }
            }
        }
        
        // Method 3: Check WordPress locale and map to country
        $locale = get_locale();
        $locale_to_country = array(
            'en_US' => 'US',
            'en_GB' => 'GB',
            'de_DE' => 'DE',
            'fr_FR' => 'FR',
            'it_IT' => 'IT',
            'es_ES' => 'ES',
            'en_CA' => 'CA',
            'en_AU' => 'AU',
            'ja' => 'JP',
            'hi_IN' => 'IN',
            'zh_CN' => 'CN'
        );
        
        if (isset($locale_to_country[$locale])) {
            return $locale_to_country[$locale];
        }
        
        // Extract country from locale pattern (e.g., pt_BR -> BR)
        if (preg_match('/^[a-z]{2}_([A-Z]{2})$/', $locale, $matches)) {
            return $matches[1];
        }
        
        // Default fallback
        return 'US';
    }
    
    /**
     * Get user's IP address
     */
    private static function get_user_ip() {
        // Check various headers that might contain the real IP
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',     // CloudFlare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster balancer
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim($_SERVER[$header]);
                
                // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return null;
    }
} 