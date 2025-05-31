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
        register_setting('compare_pricing_settings', 'compare_pricing_options');
        
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
            'amazon_access_key',
            'Amazon Access Key ID',
            array($this, 'amazon_access_key_callback'),
            'compare_pricing_settings',
            'amazon_api_section'
        );
        
        add_settings_field(
            'amazon_secret_key',
            'Amazon Secret Access Key',
            array($this, 'amazon_secret_key_callback'),
            'compare_pricing_settings',
            'amazon_api_section'
        );
        
        add_settings_field(
            'amazon_associate_tag',
            'Amazon Associate Tag',
            array($this, 'amazon_associate_tag_callback'),
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
        $options = get_option('compare_pricing_options', array());
        ?>
        <div class="wrap">
            <h1>Compare Pricing Settings</h1>
            
            <div class="notice notice-info">
                <p><strong>Setup Instructions:</strong></p>
                <ul>
                    <li><strong>eBay API:</strong> Get your credentials from <a href="https://developer.ebay.com/my/keys" target="_blank">eBay Developer Program</a></li>
                    <li><strong>Amazon API:</strong> Get your credentials from <a href="https://webservices.amazon.com/paapi5/documentation/" target="_blank">Amazon PA-API</a>.</li>
                    <li>Use the diagnostic tools below to test your API connections</li>
                </ul>
            </div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('compare_pricing_settings');
                do_settings_sections('compare_pricing_settings');
                submit_button();
                ?>
            </form>
            
            <!-- API Diagnostics Section -->
            <div class="postbox" style="margin-top: 30px;">
                <h2 class="hndle" style="padding: 15px;"><span>🔧 API Diagnostics</span></h2>
                <div class="inside" style="padding: 15px;">
                    
                    <!-- eBay API Test -->
                    <div class="diagnostic-section">
                        <h3>eBay API Test</h3>
                        <p>Test your eBay API credentials and connection:</p>
                        <button type="button" id="test-ebay-api" class="button button-secondary">Test eBay API</button>
                        <div id="ebay-test-results" class="test-results"></div>
                    </div>
                    
                    <hr style="margin: 30px 0;">
                    
                    <!-- Amazon API Test -->
                    <div class="diagnostic-section">
                        <h3>Amazon API Test</h3>
                        <p>Test your Amazon API credentials and connection:</p>
                        <button type="button" id="test-amazon-api" class="button button-secondary">Test Amazon API</button>
                        <div id="amazon-test-results" class="test-results"></div>
                    </div>
                    
                    <hr style="margin: 30px 0;">
                    
                    <!-- GTIN Lookup Test -->
                    <div class="diagnostic-section">
                        <h3>GTIN Lookup Test</h3>
                        <p>Test price comparison with a specific GTIN:</p>
                        <div style="margin-bottom: 15px;">
                            <label for="test-gtin"><strong>Enter GTIN:</strong></label><br>
                            <input type="text" id="test-gtin" class="regular-text" placeholder="e.g., 194252707050" value="194252707050">
                            <p class="description">Default GTIN is for iPhone 13 (should return results)</p>
                        </div>
                        <button type="button" id="test-gtin-lookup" class="button button-primary">Test GTIN Lookup</button>
                        <div id="gtin-test-results" class="test-results"></div>
                    </div>
                    
                </div>
            </div>
            
            <!-- Usage Instructions -->
            <div class="postbox" style="margin-top: 20px;">
                <h2 class="hndle" style="padding: 15px;"><span>📖 Usage Instructions</span></h2>
                <div class="inside" style="padding: 15px;">
                    <h3>Shortcode Usage</h3>
                    <p>Use the <code>[compare_pricing]</code> shortcode in your product pages:</p>
                    <ul>
                        <li><code>[compare_pricing]</code> - Automatic detection on product pages</li>
                        <li><code>[compare_pricing product_id="123"]</code> - Specific product ID</li>
                        <li><code>[compare_pricing gtin="1234567890123"]</code> - Direct GTIN</li>
                    </ul>
                    
                    <h3>GTIN Field Setup</h3>
                    <p>The plugin looks for GTIN in these WooCommerce product fields (in order):</p>
                    <ol>
                        <li><code>_global_unique_id</code> - WooCommerce default GTIN field</li>
                        <li><code>_wc_gla_gtin</code> - WooCommerce Google Listings & Ads</li>
                        <li><code>_gtin</code> - Custom field fallback</li>
                    </ol>
                </div>
            </div>
        </div>
        
        <style>
        .test-results {
            margin-top: 15px;
            padding: 10px;
            border-radius: 4px;
            display: none;
        }
        .test-results.loading {
            display: block;
            background: #f0f8ff;
            border: 1px solid #0073aa;
            color: #0073aa;
        }
        .test-results.success {
            display: block;
            background: #f0fff4;
            border: 1px solid #00a32a;
            color: #00a32a;
        }
        .test-results.error {
            display: block;
            background: #fef7f7;
            border: 1px solid #d63638;
            color: #d63638;
        }
        .test-step {
            margin: 10px 0;
            padding: 8px;
            border-radius: 3px;
        }
        .test-step.success {
            background: #f0fff4;
            border-left: 4px solid #00a32a;
        }
        .test-step.error {
            background: #fef7f7;
            border-left: 4px solid #d63638;
        }
        .test-step.warning {
            background: #fffbf0;
            border-left: 4px solid #dba617;
        }
        .diagnostic-section {
            margin-bottom: 20px;
        }
        .gtin-results {
            margin-top: 15px;
        }
        .platform-result {
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
            border-left: 4px solid #ccc;
        }
        .platform-result.ebay {
            border-left-color: #e53238;
            background: #fef9e7;
        }
        .platform-result.amazon {
            border-left-color: #ff9900;
            background: #f0f8ff;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // eBay API Test
            $('#test-ebay-api').click(function() {
                var $results = $('#ebay-test-results');
                $results.removeClass('success error').addClass('loading').show();
                $results.html('🔄 Testing eBay API connection...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'test_ebay_api',
                        nonce: '<?php echo wp_create_nonce('test_api_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $results.removeClass('loading error').addClass('success');
                            $results.html('✅ eBay API test completed successfully!<br>' + formatTestResults(response.data));
                        } else {
                            $results.removeClass('loading success').addClass('error');
                            $results.html('❌ eBay API test failed: ' + response.data);
                        }
                    },
                    error: function() {
                        $results.removeClass('loading success').addClass('error');
                        $results.html('❌ AJAX request failed');
                    }
                });
            });
            
            // Amazon API Test
            $('#test-amazon-api').click(function() {
                var $results = $('#amazon-test-results');
                $results.removeClass('success error').addClass('loading').show();
                $results.html('🔄 Testing Amazon API connection...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'test_amazon_api',
                        nonce: '<?php echo wp_create_nonce('test_api_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $results.removeClass('loading error').addClass('success');
                            $results.html('✅ Amazon API test completed successfully!<br>' + formatTestResults(response.data));
                        } else {
                            $results.removeClass('loading success').addClass('error');
                            $results.html('❌ Amazon API test failed: ' + response.data);
                        }
                    },
                    error: function() {
                        $results.removeClass('loading success').addClass('error');
                        $results.html('❌ AJAX request failed');
                    }
                });
            });
            
            // GTIN Lookup Test
            $('#test-gtin-lookup').click(function() {
                var gtin = $('#test-gtin').val().trim();
                var $results = $('#gtin-test-results');
                
                if (!gtin) {
                    alert('Please enter a GTIN to test');
                    return;
                }
                
                $results.removeClass('success error').addClass('loading').show();
                $results.html('🔄 Testing GTIN lookup for: ' + gtin);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'test_gtin_lookup',
                        gtin: gtin,
                        nonce: '<?php echo wp_create_nonce('test_api_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $results.removeClass('loading error').addClass('success');
                            $results.html(formatGtinResults(response.data));
                        } else {
                            $results.removeClass('loading success').addClass('error');
                            $results.html('❌ GTIN lookup failed: ' + response.data);
                        }
                    },
                    error: function() {
                        $results.removeClass('loading success').addClass('error');
                        $results.html('❌ AJAX request failed');
                    }
                });
            });
            
            function formatTestResults(data) {
                if (typeof data === 'string') return data;
                
                var html = '<div class="test-details">';
                if (data.debug) {
                    for (var step in data.debug) {
                        var stepData = data.debug[step];
                        var statusClass = stepData.status === 'success' ? 'success' : 
                                        stepData.status === 'error' ? 'error' : 'warning';
                        
                        html += '<div class="test-step ' + statusClass + '">';
                        html += '<strong>' + stepData.title + ':</strong> ' + stepData.message;
                        if (stepData.details) {
                            html += '<br><small>' + JSON.stringify(stepData.details) + '</small>';
                        }
                        html += '</div>';
                    }
                }
                html += '</div>';
                return html;
            }
            
            function formatGtinResults(data) {
                var html = '<div class="gtin-results">';
                html += '<h4>🔍 GTIN Lookup Results</h4>';
                
                if (data.overall_best) {
                    html += '<div style="background: #f0fff4; padding: 10px; border-radius: 4px; margin: 10px 0;">';
                    html += '<strong>🏆 Best Deal Found:</strong><br>';
                    html += 'Platform: ' + data.overall_best.source.toUpperCase() + '<br>';
                    html += 'Price: $' + parseFloat(data.overall_best.price).toFixed(2) + '<br>';
                    html += 'Title: ' + data.overall_best.title + '<br>';
                    html += '<a href="' + data.overall_best.url + '" target="_blank">View Product</a>';
                    html += '</div>';
                }
                
                if (data.ebay_best) {
                    html += '<div class="platform-result ebay">';
                    html += '<strong>eBay Best Deal:</strong><br>';
                    html += 'Price: $' + parseFloat(data.ebay_best.price).toFixed(2) + '<br>';
                    html += 'Title: ' + data.ebay_best.title + '<br>';
                    html += '<a href="' + data.ebay_best.url + '" target="_blank">View on eBay</a>';
                    html += '</div>';
                }
                
                if (data.amazon_best) {
                    html += '<div class="platform-result amazon">';
                    html += '<strong>Amazon Best Deal:</strong><br>';
                    html += 'Price: $' + parseFloat(data.amazon_best.price).toFixed(2) + '<br>';
                    html += 'Title: ' + data.amazon_best.title + '<br>';
                    html += '<a href="' + data.amazon_best.url + '" target="_blank">View on Amazon</a>';
                    html += '</div>';
                }
                
                html += '<div style="margin-top: 15px; font-size: 12px; color: #666;">';
                html += 'Total Results: ' + data.total_results + ' ';
                html += '(eBay: ' + data.ebay_count + ', Amazon: ' + data.amazon_count + ')';
                if (data.errors && Object.keys(data.errors).length > 0) {
                    html += '<br>Errors: ' + JSON.stringify(data.errors);
                }
                html += '</div>';
                
                html += '</div>';
                return html;
            }
        });
        </script>
        <?php
    }
    
    // Section callbacks
    public function ebay_section_callback() {
        echo '<p>Configure your eBay API credentials. Get these from <a href="https://developer.ebay.com/my/keys" target="_blank">eBay Developer Program</a>.</p>';
    }
    
    public function amazon_section_callback() {
        echo '<p>Configure your Amazon Product Advertising API credentials. Get these from <a href="https://webservices.amazon.com/paapi5/documentation/" target="_blank">Amazon PA-API</a>.</p>';
    }
    
    public function general_section_callback() {
        echo '<p>General plugin settings and configuration options.</p>';
    }
    
    // Field callbacks
    public function ebay_app_id_callback() {
        $options = get_option('compare_pricing_options', array());
        $value = isset($options['ebay_app_id']) ? $options['ebay_app_id'] : '';
        echo '<input type="text" name="compare_pricing_options[ebay_app_id]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your eBay Application ID (Client ID)</p>';
    }
    
    public function ebay_dev_id_callback() {
        $options = get_option('compare_pricing_options', array());
        $value = isset($options['ebay_dev_id']) ? $options['ebay_dev_id'] : '';
        echo '<input type="text" name="compare_pricing_options[ebay_dev_id]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your eBay Developer ID</p>';
    }
    
    public function ebay_cert_id_callback() {
        $options = get_option('compare_pricing_options', array());
        $value = isset($options['ebay_cert_id']) ? $options['ebay_cert_id'] : '';
        echo '<input type="password" name="compare_pricing_options[ebay_cert_id]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your eBay Certificate ID (Client Secret)</p>';
    }
    
    public function amazon_access_key_callback() {
        $options = get_option('compare_pricing_options', array());
        $value = isset($options['amazon_access_key']) ? $options['amazon_access_key'] : '';
        echo '<input type="text" name="compare_pricing_options[amazon_access_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your Amazon Access Key ID</p>';
    }
    
    public function amazon_secret_key_callback() {
        $options = get_option('compare_pricing_options', array());
        $value = isset($options['amazon_secret_key']) ? $options['amazon_secret_key'] : '';
        echo '<input type="password" name="compare_pricing_options[amazon_secret_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your Amazon Secret Access Key</p>';
    }
    
    public function amazon_associate_tag_callback() {
        $options = get_option('compare_pricing_options', array());
        $value = isset($options['amazon_associate_tag']) ? $options['amazon_associate_tag'] : '';
        echo '<input type="text" name="compare_pricing_options[amazon_associate_tag]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your Amazon Associate Tag (for affiliate links)</p>';
    }
    
    public function cache_duration_callback() {
        $options = get_option('compare_pricing_options', array());
        $value = isset($options['cache_duration']) ? $options['cache_duration'] : 24;
        echo '<input type="number" name="compare_pricing_options[cache_duration]" value="' . esc_attr($value) . '" min="1" max="168" />';
        echo '<p class="description">How long to cache API results (1-168 hours)</p>';
    }
    
    public function sandbox_mode_callback() {
        $options = get_option('compare_pricing_options', array());
        $value = isset($options['sandbox_mode']) ? $options['sandbox_mode'] : 0;
        echo '<input type="checkbox" name="compare_pricing_options[sandbox_mode]" value="1" ' . checked(1, $value, false) . ' />';
        echo '<label for="sandbox_mode">Enable sandbox/testing mode</label>';
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
        $amazon_api = new Compare_Pricing_Amazon_API();
        
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
        
        // Use the main compare pricing class to test GTIN lookup
        require_once COMPARE_PRICING_PATH . 'includes/class-compare-pricing.php';
        require_once COMPARE_PRICING_PATH . 'includes/class-ebay-api.php';
        require_once COMPARE_PRICING_PATH . 'includes/class-amazon-api.php';
        
        $compare_pricing = new Compare_Pricing();
        $ebay_api = new Compare_Pricing_eBay_API();
        $amazon_api = new Compare_Pricing_Amazon_API();
        
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
        $amazon_results = $amazon_api->search_products($gtin, 5);
        if (is_wp_error($amazon_results)) {
            $errors['amazon'] = $amazon_results->get_error_message();
        } elseif (!empty($amazon_results)) {
            $all_results = array_merge($all_results, $amazon_results);
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
} 