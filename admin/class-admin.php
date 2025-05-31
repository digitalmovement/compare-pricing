<?php

class Compare_Pricing_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_compare_pricing_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_compare_pricing_test_api', array($this, 'ajax_test_api'));
        add_action('wp_ajax_compare_pricing_test_amazon_api', array($this, 'ajax_test_amazon_api'));
    }
    
    public function add_admin_menu() {
        add_options_page(
            'Compare Pricing Settings',
            'Compare Pricing',
            'manage_options',
            'compare-pricing',
            array($this, 'settings_page')
        );
    }
    
    public function init_settings() {
        register_setting('compare_pricing_settings', 'compare_pricing_options', array($this, 'sanitize_options'));
        
        // API Settings Section
        add_settings_section(
            'compare_pricing_api_section',
            'API Settings',
            array($this, 'api_section_callback'),
            'compare-pricing'
        );
        
        // eBay App ID
        add_settings_field(
            'ebay_app_id',
            'eBay App ID',
            array($this, 'ebay_app_id_callback'),
            'compare-pricing',
            'compare_pricing_api_section'
        );
        
        // eBay Cert ID
        add_settings_field(
            'ebay_cert_id',
            'eBay Cert ID',
            array($this, 'ebay_cert_id_callback'),
            'compare-pricing',
            'compare_pricing_api_section'
        );
        
        // Amazon API Key
        add_settings_field(
            'amazon_api_key',
            'Amazon ASIN Data API Key',
            array($this, 'amazon_api_key_callback'),
            'compare-pricing',
            'compare_pricing_api_section'
        );
        
        // Cache Duration
        add_settings_field(
            'cache_duration',
            'Cache Duration (hours)',
            array($this, 'cache_duration_callback'),
            'compare-pricing',
            'compare_pricing_api_section'
        );
        
        // Sandbox Mode
        add_settings_field(
            'sandbox_mode',
            'eBay Sandbox Mode',
            array($this, 'sandbox_mode_callback'),
            'compare-pricing',
            'compare_pricing_api_section'
        );
    }
    
    public function sanitize_options($input) {
        $sanitized = array();
        
        if (isset($input['ebay_app_id'])) {
            $sanitized['ebay_app_id'] = sanitize_text_field($input['ebay_app_id']);
        }
        
        if (isset($input['ebay_cert_id'])) {
            $sanitized['ebay_cert_id'] = sanitize_text_field($input['ebay_cert_id']);
        }
        
        if (isset($input['amazon_api_key'])) {
            $sanitized['amazon_api_key'] = sanitize_text_field($input['amazon_api_key']);
        }
        
        if (isset($input['cache_duration'])) {
            $sanitized['cache_duration'] = max(1, min(168, intval($input['cache_duration'])));
        }
        
        if (isset($input['sandbox_mode'])) {
            $sanitized['sandbox_mode'] = intval($input['sandbox_mode']);
        }
        
        return $sanitized;
    }
    
    public function api_section_callback() {
        echo '<p>Configure your API credentials for price comparison services.</p>';
    }
    
    public function ebay_app_id_callback() {
        $options = get_option('compare_pricing_options');
        $value = isset($options['ebay_app_id']) ? $options['ebay_app_id'] : '';
        echo '<input type="text" name="compare_pricing_options[ebay_app_id]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your eBay Developer App ID</p>';
    }
    
    public function ebay_cert_id_callback() {
        $options = get_option('compare_pricing_options');
        $value = isset($options['ebay_cert_id']) ? $options['ebay_cert_id'] : '';
        echo '<input type="text" name="compare_pricing_options[ebay_cert_id]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your eBay Developer Cert ID</p>';
    }
    
    public function amazon_api_key_callback() {
        $options = get_option('compare_pricing_options');
        $value = isset($options['amazon_api_key']) ? $options['amazon_api_key'] : '';
        echo '<input type="text" name="compare_pricing_options[amazon_api_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your ASIN Data API key from <a href="https://docs.trajectdata.com/asindataapi/" target="_blank">trajectdata.com</a></p>';
    }
    
    public function cache_duration_callback() {
        $options = get_option('compare_pricing_options');
        $value = isset($options['cache_duration']) ? $options['cache_duration'] : 24;
        echo '<input type="number" name="compare_pricing_options[cache_duration]" value="' . esc_attr($value) . '" min="1" max="168" />';
        echo '<p class="description">How long to cache pricing data (1-168 hours)</p>';
    }
    
    public function sandbox_mode_callback() {
        $options = get_option('compare_pricing_options');
        $value = isset($options['sandbox_mode']) ? $options['sandbox_mode'] : 0;
        echo '<input type="checkbox" name="compare_pricing_options[sandbox_mode]" value="1" ' . checked(1, $value, false) . ' />';
        echo '<label for="compare_pricing_options[sandbox_mode]">Enable eBay Sandbox Mode (for testing)</label>';
    }
    
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Compare Pricing Settings</h1>
            
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Settings saved successfully!</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('compare_pricing_settings');
                do_settings_sections('compare-pricing');
                submit_button();
                ?>
            </form>
            
            <div class="card">
                <h2>Tools</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Test eBay API Connection</th>
                        <td>
                            <button type="button" id="test-ebay-api" class="button">Test eBay API</button>
                            <div id="ebay-test-result"></div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Test Amazon API Connection</th>
                        <td>
                            <button type="button" id="test-amazon-api" class="button">Test Amazon API</button>
                            <div id="amazon-test-result"></div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Clear Cache</th>
                        <td>
                            <button type="button" id="clear-cache-btn" class="button">Clear All Cache</button>
                            <p class="description">Clear cached pricing data to force fresh API requests.</p>
                            <div id="cache-status"></div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Clear eBay Token</th>
                        <td>
                            <button type="button" id="clear-token-btn" class="button">Clear eBay Token</button>
                            <p class="description">Clear the cached eBay authentication token.</p>
                            <div id="token-status"></div>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="card">
                <h2>API Setup Instructions</h2>
                
                <h3>eBay API Setup</h3>
                <ol>
                    <li>Visit <a href="https://developer.ebay.com/" target="_blank">eBay Developers Program</a></li>
                    <li>Create an account and register a new application</li>
                    <li>Get your App ID (Client ID) and Cert ID (Client Secret)</li>
                    <li>Enter the credentials in the settings above</li>
                    <li>Use the "Test eBay API" button to verify your setup</li>
                </ol>
                
                <h3>Amazon ASIN Data API Setup</h3>
                <ol>
                    <li>Visit <a href="https://docs.trajectdata.com/asindataapi/" target="_blank">ASIN Data API</a></li>
                    <li>Sign up for an account and get your API key</li>
                    <li>Enter the API key in the settings above</li>
                    <li>Use the "Test Amazon API" button to verify your setup</li>
                </ol>
                
                <h3>Usage</h3>
                <p>Use the shortcode <code>[compare_pricing]</code> on product pages to display price comparisons.</p>
                <p>You can also specify a product ID: <code>[compare_pricing product_id="123"]</code></p>
                <p>Or use a specific GTIN: <code>[compare_pricing gtin="1234567890123"]</code></p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var nonce = '<?php echo wp_create_nonce('compare_pricing_admin'); ?>';
            
            // Test eBay API
            $('#test-ebay-api').on('click', function() {
                var $btn = $(this);
                var $result = $('#ebay-test-result');
                
                $btn.prop('disabled', true).text('Testing...');
                $result.html('');
                
                $.post(ajaxurl, {
                    action: 'compare_pricing_test_api',
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success"><p>✓ eBay API connection successful!</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error"><p>✗ eBay API Error: ' + response.data.message + '</p></div>');
                    }
                }).fail(function() {
                    $result.html('<div class="notice notice-error"><p>✗ Request failed</p></div>');
                }).always(function() {
                    $btn.prop('disabled', false).text('Test eBay API');
                });
            });
            
            // Test Amazon API
            $('#test-amazon-api').on('click', function() {
                var $btn = $(this);
                var $result = $('#amazon-test-result');
                
                $btn.prop('disabled', true).text('Testing...');
                $result.html('');
                
                $.post(ajaxurl, {
                    action: 'compare_pricing_test_amazon_api',
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success"><p>✓ Amazon API connection successful!</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error"><p>✗ Amazon API Error: ' + response.data.message + '</p></div>');
                    }
                }).fail(function() {
                    $result.html('<div class="notice notice-error"><p>✗ Request failed</p></div>');
                }).always(function() {
                    $btn.prop('disabled', false).text('Test Amazon API');
                });
            });
            
            // Clear cache
            $('#clear-cache-btn').on('click', function() {
                var $btn = $(this);
                var $status = $('#cache-status');
                
                $btn.prop('disabled', true).text('Clearing...');
                $status.html('');
                
                $.post(ajaxurl, {
                    action: 'compare_pricing_clear_cache',
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        $status.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                    } else {
                        $status.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                }).always(function() {
                    $btn.prop('disabled', false).text('Clear All Cache');
                });
            });
            
            // Clear token
            $('#clear-token-btn').on('click', function() {
                var $btn = $(this);
                var $status = $('#token-status');
                
                $btn.prop('disabled', true).text('Clearing...');
                $status.html('');
                
                $.post(ajaxurl, {
                    action: 'compare_pricing_clear_token',
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        $status.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                    } else {
                        $status.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                }).always(function() {
                    $btn.prop('disabled', false).text('Clear eBay Token');
                });
            });
        });
        </script>
        <?php
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_compare-pricing') {
            return;
        }
        
        wp_enqueue_style(
            'compare-pricing-admin-css',
            COMPARE_PRICING_URL . 'assets/css/admin.css',
            array(),
            COMPARE_PRICING_VERSION
        );
    }
    
    public function ajax_clear_cache() {
        check_ajax_referer('compare_pricing_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Clear all compare pricing transients
        global $wpdb;
        $deleted = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_amazon_%' OR option_name LIKE '_transient_timeout_amazon_%' OR option_name LIKE '_transient_ebay_%' OR option_name LIKE '_transient_timeout_ebay_%' OR option_name LIKE '_transient_compare_pricing_%' OR option_name LIKE '_transient_timeout_compare_pricing_%'");
        
        if ($deleted !== false) {
            wp_send_json_success('Cache cleared successfully. Deleted ' . $deleted . ' cached entries.');
        } else {
            wp_send_json_error('Failed to clear cache');
        }
    }
    
    public function ajax_test_api() {
        check_ajax_referer('compare_pricing_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $ebay_api = new Compare_Pricing_eBay_API();
        
        // Set sandbox mode based on settings
        $options = get_option('compare_pricing_options');
        $sandbox_mode = isset($options['sandbox_mode']) ? $options['sandbox_mode'] : 0;
        $ebay_api->set_sandbox_mode($sandbox_mode);
        
        $result = $ebay_api->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function ajax_test_amazon_api() {
        check_ajax_referer('compare_pricing_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $amazon_api = new Compare_Pricing_Amazon_API();
        $result = $amazon_api->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
} 