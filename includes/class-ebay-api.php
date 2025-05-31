<?php

class Compare_Pricing_eBay_API {
    
    private $app_id;
    private $cert_id;
    private $dev_id;
    private $sandbox = false; // Set to true for testing
    
    public function __construct() {
        $options = get_option('compare_pricing_options', array());
        $this->app_id = isset($options['ebay_app_id']) ? $options['ebay_app_id'] : '';
        $this->cert_id = isset($options['ebay_cert_id']) ? $options['ebay_cert_id'] : '';
        $this->dev_id = get_option('compare_pricing_ebay_dev_id');
        $this->sandbox = isset($options['sandbox_mode']) ? $options['sandbox_mode'] : false;
    }
    
    public function search_by_gtin($gtin) {
        if (empty($this->app_id)) {
            return new WP_Error('no_api_key', 'eBay API credentials not configured');
        }
        
        $endpoint = $this->sandbox ? 
            'https://api.sandbox.ebay.com/buy/browse/v1/item_summary/search' :
            'https://api.ebay.com/buy/browse/v1/item_summary/search';
        
        $headers = array(
            'Authorization' => 'Bearer ' . $this->get_access_token(),
            'Content-Type' => 'application/json',
            'X-EBAY-C-MARKETPLACE-ID' => 'EBAY_US'
        );
        
        $query_params = array(
            'gtin' => $gtin,
            'limit' => 10,
            'sort' => 'price',
            'filter' => 'buyingOptions:{FIXED_PRICE},itemLocationCountry:US'
        );
        
        $url = $endpoint . '?' . http_build_query($query_params);
        
        $response = wp_remote_get($url, array(
            'headers' => $headers,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['itemSummaries']) && !empty($data['itemSummaries'])) {
            $cheapest_item = $data['itemSummaries'][0];
            
            return array(
                'price' => $cheapest_item['price']['value'],
                'currency' => $cheapest_item['price']['currency'],
                'url' => $cheapest_item['itemWebUrl'],
                'title' => $cheapest_item['title']
            );
        }
        
        return false;
    }
    
    private function get_access_token() {
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
        
        $response = wp_remote_post($endpoint, array(
            'headers' => $headers,
            'body' => $body,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['access_token'])) {
            // Cache token for slightly less than its expiry time
            $cache_time = isset($data['expires_in']) ? $data['expires_in'] - 300 : 3300;
            set_transient('compare_pricing_ebay_token', $data['access_token'], $cache_time);
            
            return $data['access_token'];
        }
        
        return false;
    }
    
    public function test_connection() {
        $debug_info = array();
        
        // Step 1: Check if credentials are provided
        $debug_info['step_1'] = array(
            'title' => 'Checking API Credentials',
            'status' => 'checking'
        );
        
        if (empty($this->app_id) || empty($this->cert_id)) {
            $debug_info['step_1']['status'] = 'error';
            $debug_info['step_1']['message'] = 'Missing eBay API credentials (App ID or Cert ID)';
            $debug_info['step_1']['help'] = 'Please ensure you have entered both App ID and Cert ID in the settings above.';
            return array('success' => false, 'debug' => $debug_info);
        }
        
        $debug_info['step_1']['status'] = 'success';
        $debug_info['step_1']['message'] = 'API credentials found';
        $debug_info['step_1']['details'] = array(
            'App ID Length' => strlen($this->app_id) . ' characters',
            'Cert ID Length' => strlen($this->cert_id) . ' characters',
            'App ID Preview' => substr($this->app_id, 0, 8) . '...',
            'Environment' => $this->sandbox ? 'Sandbox' : 'Production'
        );
        
        // Step 2: Validate credential format
        $debug_info['step_2'] = array(
            'title' => 'Validating Credential Format',
            'status' => 'checking'
        );
        
        $validation_errors = array();
        
        // eBay App ID should be around 32 characters
        if (strlen($this->app_id) < 20 || strlen($this->app_id) > 50) {
            $validation_errors[] = 'App ID length seems incorrect (should be ~32 characters)';
        }
        
        // eBay Cert ID should be around 32 characters  
        if (strlen($this->cert_id) < 20 || strlen($this->cert_id) > 50) {
            $validation_errors[] = 'Cert ID length seems incorrect (should be ~32 characters)';
        }
        
        // Check for common formatting issues
        if (strpos($this->app_id, ' ') !== false || strpos($this->cert_id, ' ') !== false) {
            $validation_errors[] = 'Credentials contain spaces (should be removed)';
        }
        
        if (!empty($validation_errors)) {
            $debug_info['step_2']['status'] = 'warning';
            $debug_info['step_2']['message'] = 'Potential credential format issues detected';
            $debug_info['step_2']['warnings'] = $validation_errors;
        } else {
            $debug_info['step_2']['status'] = 'success';
            $debug_info['step_2']['message'] = 'Credential format looks correct';
        }
        
        // Step 3: Test OAuth token generation
        $debug_info['step_3'] = array(
            'title' => 'Obtaining Access Token',
            'status' => 'checking'
        );
        
        // Clear any cached token for testing
        delete_transient('compare_pricing_ebay_token');
        
        $token_result = $this->get_access_token_debug();
        
        if (is_wp_error($token_result)) {
            $debug_info['step_3']['status'] = 'error';
            $debug_info['step_3']['message'] = $token_result->get_error_message();
            return array('success' => false, 'debug' => $debug_info);
        }
        
        if (!$token_result['success']) {
            $debug_info['step_3']['status'] = 'error';
            $debug_info['step_3']['message'] = $token_result['message'];
            $debug_info['step_3']['response'] = $token_result['response'];
            
            // Add specific help for common errors
            if (isset($token_result['response']['error']) && $token_result['response']['error'] === 'invalid_client') {
                $debug_info['step_3']['help'] = array(
                    'This error means your App ID or Cert ID is incorrect.',
                    'Common causes:',
                    '• Wrong App ID or Cert ID copied from eBay Developer account',
                    '• Using Sandbox credentials for Production (or vice versa)',
                    '• Extra spaces or characters in the credentials',
                    '• Credentials from wrong eBay application',
                    '',
                    'To fix this:',
                    '1. Log into your eBay Developer account at developer.ebay.com',
                    '2. Go to "My Account" → "Application Keys"',
                    '3. Copy the App ID (Client ID) and Cert ID (Client Secret) exactly',
                    '4. Make sure you\'re using the right environment (Production vs Sandbox)',
                    '5. Ensure no extra spaces when pasting'
                );
            }
            
            return array('success' => false, 'debug' => $debug_info);
        }
        
        $debug_info['step_3']['status'] = 'success';
        $debug_info['step_3']['message'] = 'Access token obtained successfully';
        $debug_info['step_3']['token_preview'] = substr($token_result['token'], 0, 20) . '...';
        $debug_info['step_3']['expires_in'] = $token_result['expires_in'] . ' seconds';
        
        // Step 4: Test API search functionality
        $debug_info['step_4'] = array(
            'title' => 'Testing Search API',
            'status' => 'checking'
        );
        
        $search_result = $this->test_search_api($token_result['token']);
        
        if (is_wp_error($search_result)) {
            $debug_info['step_4']['status'] = 'error';
            $debug_info['step_4']['message'] = $search_result->get_error_message();
            return array('success' => false, 'debug' => $debug_info);
        }
        
        if (!$search_result['success']) {
            $debug_info['step_4']['status'] = 'error';
            $debug_info['step_4']['message'] = $search_result['message'];
            $debug_info['step_4']['response'] = $search_result['response'];
            return array('success' => false, 'debug' => $debug_info);
        }
        
        $debug_info['step_4']['status'] = 'success';
        $debug_info['step_4']['message'] = 'Search API working correctly';
        $debug_info['step_4']['results_found'] = $search_result['results_count'];
        
        return array('success' => true, 'debug' => $debug_info);
    }
    
    private function get_access_token_debug() {
        $endpoint = $this->sandbox ?
            'https://api.sandbox.ebay.com/identity/v1/oauth2/token' :
            'https://api.ebay.com/identity/v1/oauth2/token';
        
        $credentials = base64_encode($this->app_id . ':' . $this->cert_id);
        
        $headers = array(
            'Authorization' => 'Basic ' . $credentials,
            'Content-Type' => 'application/x-www-form-urlencoded'
        );
        
        $body = 'grant_type=client_credentials&scope=https://api.ebay.com/oauth/api_scope';
        
        $response = wp_remote_post($endpoint, array(
            'headers' => $headers,
            'body' => $body,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('http_error', 'HTTP request failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($response_code !== 200) {
            $error_message = 'HTTP ' . $response_code;
            if (isset($data['error_description'])) {
                $error_message .= ': ' . $data['error_description'];
            } elseif (isset($data['error'])) {
                $error_message .= ': ' . $data['error'];
            }
            
            return array(
                'success' => false,
                'message' => $error_message,
                'response' => $data,
                'request_details' => array(
                    'endpoint' => $endpoint,
                    'credentials_length' => strlen($credentials),
                    'app_id_preview' => substr($this->app_id, 0, 8) . '...',
                    'cert_id_preview' => substr($this->cert_id, 0, 8) . '...'
                )
            );
        }
        
        if (!isset($data['access_token'])) {
            return array(
                'success' => false,
                'message' => 'No access token in response',
                'response' => $data
            );
        }
        
        // Cache the token
        $cache_time = isset($data['expires_in']) ? $data['expires_in'] - 300 : 3300;
        set_transient('compare_pricing_ebay_token', $data['access_token'], $cache_time);
        
        return array(
            'success' => true,
            'token' => $data['access_token'],
            'expires_in' => $data['expires_in'] ?? 3600
        );
    }
    
    private function test_search_api($token) {
        $endpoint = $this->sandbox ? 
            'https://api.sandbox.ebay.com/buy/browse/v1/item_summary/search' :
            'https://api.ebay.com/buy/browse/v1/item_summary/search';
        
        $headers = array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'X-EBAY-C-MARKETPLACE-ID' => 'EBAY_US'
        );
        
        // Use a common product GTIN for testing (iPhone 13)
        $test_gtin = '194252707050';
        
        $query_params = array(
            'gtin' => $test_gtin,
            'limit' => 5,
            'sort' => 'price',
            'filter' => 'buyingOptions:{FIXED_PRICE}'
        );
        
        $url = $endpoint . '?' . http_build_query($query_params);
        
        $response = wp_remote_get($url, array(
            'headers' => $headers,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('search_http_error', 'Search API HTTP request failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($response_code !== 200) {
            $error_message = 'Search API HTTP ' . $response_code;
            if (isset($data['errors'][0]['message'])) {
                $error_message .= ': ' . $data['errors'][0]['message'];
            }
            
            return array(
                'success' => false,
                'message' => $error_message,
                'response' => $data
            );
        }
        
        $results_count = isset($data['itemSummaries']) ? count($data['itemSummaries']) : 0;
        
        return array(
            'success' => true,
            'results_count' => $results_count
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
     * Search for products on eBay
     * 
     * @param string $search_term The search term or GTIN
     * @param int $limit Number of results to return
     * @return array|WP_Error Array of products or error
     */
    public function search_products($search_term, $limit = 5) {
        // Check if we have required credentials
        if (empty($this->app_id)) {
            return new WP_Error('missing_credentials', 'eBay App ID is required');
        }
        
        // Check cache first
        $cache_key = 'ebay_search_' . md5($search_term . $limit);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        // Prepare API request
        $endpoint = $this->sandbox ? 
            'https://svcs.sandbox.ebay.com/services/search/FindingService/v1' :
            'https://svcs.ebay.com/services/search/FindingService/v1';
        
        $params = array(
            'OPERATION-NAME' => 'findItemsByKeywords',
            'SERVICE-VERSION' => '1.0.0',
            'SECURITY-APPNAME' => $this->app_id,
            'RESPONSE-DATA-FORMAT' => 'JSON',
            'REST-PAYLOAD' => '',
            'keywords' => $search_term,
            'paginationInput.entriesPerPage' => $limit,
            'sortOrder' => 'PricePlusShippingLowest'
        );
        
        // Build query string
        $query_string = http_build_query($params);
        $url = $endpoint . '?' . $query_string;
        
        // Make API request
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'Compare-Pricing-Plugin/1.0'
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Invalid JSON response from eBay API');
        }
        
        // Parse results
        $products = $this->parse_ebay_response($data);
        
        // Cache results for 1 hour
        set_transient($cache_key, $products, HOUR_IN_SECONDS);
        
        return $products;
    }
    
    /**
     * Parse eBay API response
     */
    private function parse_ebay_response($data) {
        $products = array();
        
        if (!isset($data['findItemsByKeywordsResponse'][0]['searchResult'][0]['item'])) {
            return $products;
        }
        
        $items = $data['findItemsByKeywordsResponse'][0]['searchResult'][0]['item'];
        
        foreach ($items as $item) {
            $product = array(
                'title' => isset($item['title'][0]) ? $item['title'][0] : 'No title',
                'price' => 0,
                'url' => isset($item['viewItemURL'][0]) ? $item['viewItemURL'][0] : '',
                'image' => '',
                'source' => 'ebay',
                'rating' => 0,
                'review_count' => 0
            );
            
            // Get price
            if (isset($item['sellingStatus'][0]['currentPrice'][0]['__value__'])) {
                $product['price'] = floatval($item['sellingStatus'][0]['currentPrice'][0]['__value__']);
            }
            
            // Get image
            if (isset($item['galleryURL'][0])) {
                $product['image'] = $item['galleryURL'][0];
            }
            
            $products[] = $product;
        }
        
        return $products;
    }
} 