<?php

class Compare_Pricing_eBay_API {
    
    private $app_id;
    private $cert_id;
    private $dev_id;
    private $sandbox = false; // Set to true for testing
    
    public function __construct($options = array()) {
        $this->app_id = isset($options['ebay_app_id']) ? $options['ebay_app_id'] : get_option('compare_pricing_ebay_app_id', '');
        $this->cert_id = isset($options['ebay_cert_id']) ? $options['ebay_cert_id'] : get_option('compare_pricing_ebay_cert_id', '');
        $this->dev_id = isset($options['ebay_dev_id']) ? $options['ebay_dev_id'] : get_option('compare_pricing_ebay_dev_id', '');
        $this->sandbox = isset($options['sandbox_mode']) ? $options['sandbox_mode'] : get_option('compare_pricing_sandbox_mode', 0);
    }
    
    public function search_products($query, $limit = 10, $country_code = 'US') {
        if (empty($this->app_id)) {
            return new WP_Error('no_api_key', 'eBay API credentials not configured');
        }
        
        // Get access token first
        $access_token = $this->get_access_token();
        if (is_wp_error($access_token)) {
            return $access_token;
        }
        
        // Get the appropriate eBay marketplace
        $marketplace_info = $this->get_marketplace_info($country_code);
        
        $endpoint = $this->sandbox ? 
            'https://api.sandbox.ebay.com/buy/browse/v1/item_summary/search' :
            'https://api.ebay.com/buy/browse/v1/item_summary/search';
        
        // Build query parameters
        $params = array(
            'q' => $query,
            'limit' => min($limit, 50),
            'sort' => 'price',
            'filter' => 'buyingOptions:{FIXED_PRICE}',
            'fieldgroups' => 'MATCHING_ITEMS,EXTENDED'
        );
        
        $url = $endpoint . '?' . http_build_query($params);
        
        $headers = array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
            'X-EBAY-C-MARKETPLACE-ID' => $marketplace_info['marketplace_id'],
            'X-EBAY-C-ENDUSERCTX' => 'contextualLocation=country=' . $country_code
        );
        
        error_log('eBay API Request for ' . $country_code . ': ' . $url);
        
        $response = wp_remote_get($url, array(
            'headers' => $headers,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('eBay API Error: ' . $response->get_error_message());
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data['itemSummaries'])) {
            error_log('eBay API: No results found for query: ' . $query);
            return array();
        }
        
        $results = array();
        foreach ($data['itemSummaries'] as $item) {
            $price = 0;
            if (isset($item['price']['value'])) {
                $price = floatval($item['price']['value']);
            }
            
            $results[] = array(
                'title' => isset($item['title']) ? $item['title'] : 'Unknown Product',
                'price' => $price,
                'currency' => isset($item['price']['currency']) ? $item['price']['currency'] : $marketplace_info['currency'],
                'url' => isset($item['itemWebUrl']) ? $item['itemWebUrl'] : '',
                'image' => isset($item['image']['imageUrl']) ? $item['image']['imageUrl'] : '',
                'source' => 'ebay',
                'marketplace' => $marketplace_info['name'],
                'country' => $country_code
            );
        }
        
        error_log('eBay API: Found ' . count($results) . ' results for ' . $country_code);
        return $results;
    }
    
    public function search_by_gtin($gtin) {
        // For GTIN search, we can use the gtin parameter
        if (empty($this->app_id)) {
            return new WP_Error('no_api_key', 'eBay API credentials not configured');
        }
        
        $access_token = $this->get_access_token();
        if (is_wp_error($access_token)) {
            return $access_token;
        }
        
        $endpoint = $this->sandbox ? 
            'https://api.sandbox.ebay.com/buy/browse/v1/item_summary/search' :
            'https://api.ebay.com/buy/browse/v1/item_summary/search';
        
        // Use GTIN filter for more accurate results
        $params = array(
            'gtin' => $gtin,
            'limit' => 10,
            'sort' => 'price',
            'filter' => 'buyingOptions:{FIXED_PRICE}',
            'fieldgroups' => 'MATCHING_ITEMS,EXTENDED'
        );
        
        $url = $endpoint . '?' . http_build_query($params);
        
        error_log('eBay GTIN Search URL: ' . $url);
        
        $headers = array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
            'X-EBAY-C-MARKETPLACE-ID' => 'EBAY_US'
        );
        
        $response = wp_remote_get($url, array(
            'headers' => $headers,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('eBay GTIN API Request Error: ' . $response->get_error_message());
            // Fallback to regular search with GTIN as query
            return $this->search_products($gtin, 10);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            error_log('eBay GTIN API Error: ' . $response_code . ' - ' . $body);
            // Fallback to regular search
            return $this->search_products($gtin, 10);
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || isset($data['errors'])) {
            // Fallback to regular search
            return $this->search_products($gtin, 10);
        }
        
        // Process results same as regular search
        $products = array();
        
        if (isset($data['itemSummaries']) && is_array($data['itemSummaries'])) {
            foreach ($data['itemSummaries'] as $item) {
                $price = 0;
                if (isset($item['price']['value'])) {
                    $price = floatval($item['price']['value']);
                }
                
                if ($price <= 0) continue;
                
                $products[] = array(
                    'title' => $item['title'] ?? 'Unknown Product',
                    'price' => $price,
                    'currency' => $item['price']['currency'] ?? 'USD',
                    'url' => $item['itemWebUrl'] ?? '',
                    'image' => $item['image']['imageUrl'] ?? '',
                    'source' => 'ebay',
                    'condition' => $item['condition'] ?? 'Unknown',
                    'seller' => $item['seller']['username'] ?? 'Unknown',
                    'shipping' => isset($item['shippingOptions'][0]['shippingCost']['value']) ? 
                                 floatval($item['shippingOptions'][0]['shippingCost']['value']) : 0
                );
            }
        }
        
        return $products;
    }
    
    private function get_access_token() {
        // Check for cached token
        $cached_token = get_transient('compare_pricing_ebay_token');
        if ($cached_token) {
            return $cached_token;
        }
        
        $endpoint = $this->sandbox ?
            'https://api.sandbox.ebay.com/identity/v1/oauth2/token' :
            'https://api.ebay.com/identity/v1/oauth2/token';
        
        $credentials = base64_encode($this->app_id . ':' . $this->cert_id);
        
        $headers = array(
            'Authorization' => 'Basic ' . $credentials,
            'Content-Type' => 'application/x-www-form-urlencoded'
        );
        
        $body = 'grant_type=client_credentials&scope=https://api.ebay.com/oauth/api_scope';
        
        error_log('eBay Token Request: ' . $endpoint);
        
        $response = wp_remote_post($endpoint, array(
            'headers' => $headers,
            'body' => $body,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('eBay Token Error: ' . $response->get_error_message());
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('eBay Token Response Code: ' . $response_code);
        
        if ($response_code !== 200) {
            error_log('eBay Token Error Response: ' . $body);
            return new WP_Error('token_error', 'Failed to get eBay access token: ' . $response_code);
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Failed to parse token response');
        }
        
        if (!isset($data['access_token'])) {
            return new WP_Error('token_error', 'No access token in response');
        }
        
        $token = $data['access_token'];
        $expires_in = isset($data['expires_in']) ? intval($data['expires_in']) : 7200;
        
        // Cache token for slightly less than expiry time
        set_transient('compare_pricing_ebay_token', $token, $expires_in - 300);
        
        error_log('eBay Token obtained successfully, expires in: ' . $expires_in . ' seconds');
        
        return $token;
    }
    
    public function test_connection() {
        if (empty($this->app_id) || empty($this->cert_id)) {
            return array(
                'success' => false,
                'error' => 'eBay API credentials not configured',
                'debug' => array(
                    'config_check' => array(
                        'status' => 'error',
                        'title' => 'Configuration Check',
                        'message' => 'eBay API credentials missing',
                        'help' => 'Please configure your eBay App ID and Cert ID'
                    )
                )
            );
        }
        
        $debug_info = array();
        
        // Step 1: Configuration check
        $debug_info['config_check'] = array(
            'status' => 'success',
            'title' => 'Configuration Check',
            'message' => 'eBay credentials configured',
            'details' => array(
                'App ID' => substr($this->app_id, 0, 8) . '...',
                'Cert ID' => substr($this->cert_id, 0, 8) . '...',
                'Dev ID' => !empty($this->dev_id) ? substr($this->dev_id, 0, 8) . '...' : 'Not set',
                'Sandbox Mode' => $this->sandbox ? 'Yes' : 'No'
            )
        );
        
        // Step 2: Test token retrieval
        $token = $this->get_access_token();
        
        if (is_wp_error($token)) {
            $debug_info['token_test'] = array(
                'status' => 'error',
                'title' => 'Token Retrieval',
                'message' => 'Failed to get access token: ' . $token->get_error_message(),
                'help' => 'Check your App ID and Cert ID credentials'
            );
            
            return array(
                'success' => false,
                'error' => 'Token retrieval failed',
                'debug' => $debug_info
            );
        }
        
        $debug_info['token_test'] = array(
            'status' => 'success',
            'title' => 'Token Retrieval',
            'message' => 'Access token obtained successfully',
            'details' => array(
                'Token' => substr($token, 0, 20) . '...'
            )
        );
        
        // Step 3: Test API call
        $test_results = $this->search_products('test', 1);
        
        if (is_wp_error($test_results)) {
            $debug_info['api_test'] = array(
                'status' => 'error',
                'title' => 'API Test Call',
                'message' => 'API call failed: ' . $test_results->get_error_message(),
                'help' => 'Check your API permissions and marketplace access'
            );
            
            return array(
                'success' => false,
                'error' => 'API test failed',
                'debug' => $debug_info
            );
        }
        
        $debug_info['api_test'] = array(
            'status' => 'success',
            'title' => 'API Test Call',
            'message' => 'API call successful, found ' . count($test_results) . ' results',
            'note' => 'eBay API is working correctly'
        );
        
        return array(
            'success' => true,
            'debug' => $debug_info
        );
    }
    
    // Helper method to switch between sandbox and production
    public function set_sandbox_mode($sandbox = true) {
        $this->sandbox = $sandbox;
    }
    
    // Method to get current environment
    public function get_environment() {
        return $this->sandbox ? 'Sandbox' : 'Production';
    }
    
    /**
     * Get marketplace information for different countries
     */
    private function get_marketplace_info($country_code) {
        $marketplaces = array(
            'US' => array('marketplace_id' => 'EBAY_US', 'name' => 'eBay.com', 'currency' => 'USD'),
            'GB' => array('marketplace_id' => 'EBAY_GB', 'name' => 'eBay.co.uk', 'currency' => 'GBP'),
            'DE' => array('marketplace_id' => 'EBAY_DE', 'name' => 'eBay.de', 'currency' => 'EUR'),
            'FR' => array('marketplace_id' => 'EBAY_FR', 'name' => 'eBay.fr', 'currency' => 'EUR'),
            'IT' => array('marketplace_id' => 'EBAY_IT', 'name' => 'eBay.it', 'currency' => 'EUR'),
            'ES' => array('marketplace_id' => 'EBAY_ES', 'name' => 'eBay.es', 'currency' => 'EUR'),
            'CA' => array('marketplace_id' => 'EBAY_CA', 'name' => 'eBay.ca', 'currency' => 'CAD'),
            'AU' => array('marketplace_id' => 'EBAY_AU', 'name' => 'eBay.com.au', 'currency' => 'AUD'),
        );
        
        return isset($marketplaces[$country_code]) ? $marketplaces[$country_code] : $marketplaces['US'];
    }
} 