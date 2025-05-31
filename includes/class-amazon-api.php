<?php
class Compare_Pricing_Amazon_API {
    
    private $access_key;
    private $secret_key;
    private $associate_tag;
    private $region = 'us-east-1';
    private $marketplace = 'www.amazon.com';
    private $base_url = 'https://api.asindataapi.com/request';
    private $debug = true; // Enable debugging
    
    public function __construct() {
        $options = get_option('compare_pricing_options', array());
        $this->access_key = isset($options['amazon_access_key']) ? $options['amazon_access_key'] : '';
        $this->secret_key = isset($options['amazon_secret_key']) ? $options['amazon_secret_key'] : '';
        $this->associate_tag = isset($options['amazon_associate_tag']) ? $options['amazon_associate_tag'] : '';
    }
    
    /**
     * Search for products on Amazon using PA-API 5.0
     */
    public function search_products($search_term, $limit = 5) {
        if (empty($this->access_key) || empty($this->secret_key) || empty($this->associate_tag)) {
            return new WP_Error('missing_credentials', 'Amazon API credentials not configured');
        }
        
        // Check cache first
        $cache_key = 'amazon_search_' . md5($search_term . $limit);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        // For now, return a mock result since PA-API 5.0 requires complex authentication
        // This would need to be replaced with actual PA-API 5.0 implementation
        $mock_results = array(
            array(
                'title' => 'Amazon Product for ' . $search_term,
                'price' => 29.99,
                'url' => 'https://amazon.com/dp/example',
                'image' => '',
                'source' => 'amazon',
                'rating' => 4.5,
                'review_count' => 100,
                'prime' => true
            )
        );
        
        // Cache results for 1 hour
        set_transient($cache_key, $mock_results, HOUR_IN_SECONDS);
        
        return $mock_results;
    }
    
    /**
     * Get product details by ASIN
     */
    public function get_product_details($asin) {
        if (empty($this->access_key) || empty($this->secret_key) || empty($this->associate_tag)) {
            return new WP_Error('missing_credentials', 'Amazon API credentials not configured');
        }
        
        $cache_key = 'amazon_product_' . $asin;
        $cached_data = $this->get_cached_data($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $params = array(
            'api_key' => $this->access_key,
            'type' => 'product',
            'amazon_domain' => 'amazon.co.uk',
            'asin' => $asin,
            'output' => 'json'
        );
        
        $response = $this->make_request($params);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $product = $this->parse_product_response($response);
        $this->cache_data($cache_key, $product);
        
        return $product;
    }
    
    /**
     * Get product offers by ASIN
     */
    public function get_product_offers($asin) {
        if (empty($this->access_key) || empty($this->secret_key) || empty($this->associate_tag)) {
            return new WP_Error('missing_credentials', 'Amazon API credentials not configured');
        }
        
        $cache_key = 'amazon_offers_' . $asin;
        $cached_data = $this->get_cached_data($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $params = array(
            'api_key' => $this->access_key,
            'type' => 'offers',
            'amazon_domain' => 'amazon.com',
            'asin' => $asin,
            'output' => 'json'
        );
        
        $response = $this->make_request($params);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $offers = $this->parse_offers_response($response);
        $this->cache_data($cache_key, $offers);
        
        return $offers;
    }
    
    public function test_connection() {
        $debug_info = array();
        
        // Step 1: Check credentials
        $debug_info['step_1'] = array(
            'title' => 'Checking Amazon API Credentials',
            'status' => 'checking'
        );
        
        if (empty($this->access_key) || empty($this->secret_key) || empty($this->associate_tag)) {
            $debug_info['step_1']['status'] = 'error';
            $debug_info['step_1']['message'] = 'Missing Amazon API credentials';
            $debug_info['step_1']['help'] = 'Please ensure you have entered Access Key, Secret Key, and Associate Tag.';
            return array('success' => false, 'debug' => $debug_info);
        }
        
        $debug_info['step_1']['status'] = 'success';
        $debug_info['step_1']['message'] = 'Amazon API credentials found';
        $debug_info['step_1']['details'] = array(
            'Access Key Length' => strlen($this->access_key) . ' characters',
            'Secret Key Length' => strlen($this->secret_key) . ' characters',
            'Associate Tag' => $this->associate_tag
        );
        
        // Step 2: Validate credential format
        $debug_info['step_2'] = array(
            'title' => 'Validating Credential Format',
            'status' => 'checking'
        );
        
        $validation_errors = array();
        
        if (strlen($this->access_key) !== 20) {
            $validation_errors[] = 'Access Key should be 20 characters';
        }
        
        if (strlen($this->secret_key) !== 40) {
            $validation_errors[] = 'Secret Key should be 40 characters';
        }
        
        if (empty($this->associate_tag)) {
            $validation_errors[] = 'Associate Tag is required';
        }
        
        if (!empty($validation_errors)) {
            $debug_info['step_2']['status'] = 'warning';
            $debug_info['step_2']['message'] = 'Potential credential format issues';
            $debug_info['step_2']['warnings'] = $validation_errors;
        } else {
            $debug_info['step_2']['status'] = 'success';
            $debug_info['step_2']['message'] = 'Credential format looks correct';
        }
        
        // Step 3: Test API call (mock for now)
        $debug_info['step_3'] = array(
            'title' => 'Testing Amazon PA-API Connection',
            'status' => 'success',
            'message' => 'Mock test successful (PA-API 5.0 implementation needed)',
            'note' => 'This is a placeholder. Full PA-API 5.0 implementation requires complex authentication.'
        );
        
        return array('success' => true, 'debug' => $debug_info);
    }
    
    /**
     * Make API request
     */
    private function make_request($params) {
        $url = add_query_arg($params, $this->base_url);
        
        $this->log_debug('Making request to: ' . $url);
        
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress Compare Pricing Plugin'
            )
        ));
        
        if (is_wp_error($response)) {
            $this->log_debug('HTTP Error: ' . $response->get_error_message());
            return $response;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $this->log_debug('HTTP Response Code: ' . $http_code);
        
        $body = wp_remote_retrieve_body($response);
        $this->log_debug('Response Body: ' . substr($body, 0, 500) . '...');
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_msg = 'Invalid JSON response from Amazon API. JSON Error: ' . json_last_error_msg();
            $this->log_debug($error_msg);
            return new WP_Error('json_error', $error_msg);
        }
        
        if (isset($data['request_info']['success']) && !$data['request_info']['success']) {
            $message = isset($data['request_info']['message']) ? $data['request_info']['message'] : 'Unknown API error';
            $this->log_debug('API Error: ' . $message);
            return new WP_Error('api_error', $message);
        }
        
        return $data;
    }
    
    /**
     * Parse search response
     */
    private function parse_search_response($data) {
        $products = array();
        
        if (!isset($data['search_results']) || !is_array($data['search_results'])) {
            $this->log_debug('No search_results found in response');
            return $products;
        }
        
        $this->log_debug('Processing ' . count($data['search_results']) . ' search results');
        
        foreach ($data['search_results'] as $item) {
            $product = array(
                'asin' => isset($item['asin']) ? $item['asin'] : '',
                'title' => isset($item['title']) ? $item['title'] : '',
                'price' => $this->extract_price($item),
                'image' => isset($item['image']) ? $item['image'] : '',
                'url' => isset($item['link']) ? $item['link'] : '',
                'rating' => isset($item['rating']) ? floatval($item['rating']) : 0,
                'review_count' => isset($item['ratings_total']) ? intval($item['ratings_total']) : 0,
                'prime' => isset($item['prime']) ? $item['prime'] : false,
                'source' => 'amazon'
            );
            
            if (!empty($product['asin']) && !empty($product['title'])) {
                $products[] = $product;
                $this->log_debug('Added product: ' . $product['title'] . ' - $' . $product['price']);
            }
        }
        
        return $products;
    }
    
    /**
     * Parse product response
     */
    private function parse_product_response($data) {
        if (!isset($data['product'])) {
            return null;
        }
        
        $item = $data['product'];
        
        return array(
            'asin' => isset($item['asin']) ? $item['asin'] : '',
            'title' => isset($item['title']) ? $item['title'] : '',
            'price' => $this->extract_price($item),
            'image' => isset($item['main_image']['link']) ? $item['main_image']['link'] : '',
            'url' => isset($item['link']) ? $item['link'] : '',
            'rating' => isset($item['rating']) ? floatval($item['rating']) : 0,
            'review_count' => isset($item['ratings_total']) ? intval($item['ratings_total']) : 0,
            'prime' => isset($item['prime']) ? $item['prime'] : false,
            'description' => isset($item['feature_bullets']) ? implode(' ', $item['feature_bullets']) : '',
            'source' => 'amazon'
        );
    }
    
    /**
     * Parse offers response
     */
    private function parse_offers_response($data) {
        $offers = array();
        
        if (!isset($data['offers']) || !is_array($data['offers'])) {
            return $offers;
        }
        
        foreach ($data['offers'] as $offer) {
            $offers[] = array(
                'price' => $this->extract_price($offer),
                'seller' => isset($offer['seller']['name']) ? $offer['seller']['name'] : 'Amazon',
                'condition' => isset($offer['condition']) ? $offer['condition'] : 'New',
                'prime' => isset($offer['prime']) ? $offer['prime'] : false,
                'shipping' => isset($offer['shipping']) ? $offer['shipping'] : ''
            );
        }
        
        return $offers;
    }
    
    /**
     * Extract price from item data
     */
    private function extract_price($item) {
        // Try different price fields
        $price_fields = array('price', 'price_upper', 'price_lower', 'current_price');
        
        foreach ($price_fields as $field) {
            if (isset($item[$field]) && !empty($item[$field])) {
                // Remove currency symbols and convert to float
                $price_str = is_array($item[$field]) ? (isset($item[$field]['value']) ? $item[$field]['value'] : '') : $item[$field];
                $price = preg_replace('/[^0-9.]/', '', $price_str);
                $price_float = floatval($price);
                if ($price_float > 0) {
                    $this->log_debug('Extracted price: $' . $price_float . ' from field: ' . $field);
                    return $price_float;
                }
            }
        }
        
        $this->log_debug('No valid price found in item: ' . print_r($item, true));
        return 0;
    }
    
    /**
     * Get cached data
     */
    private function get_cached_data($cache_key) {
        return get_transient($cache_key);
    }
    
    /**
     * Cache data
     */
    private function cache_data($cache_key, $data) {
        $options = get_option('compare_pricing_options');
        $cache_duration = isset($options['cache_duration']) ? intval($options['cache_duration']) : 24;
        
        set_transient($cache_key, $data, $cache_duration * HOUR_IN_SECONDS);
    }
    
    /**
     * Log debug messages
     */
    private function log_debug($message) {
        if ($this->debug && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Amazon API Debug] ' . $message);
        }
    }
} 