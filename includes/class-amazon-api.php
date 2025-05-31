<?php
class Compare_Pricing_Amazon_API {
    
    private $api_key;
    private $base_url = 'https://api.asindataapi.com/request';
    private $debug = true; // Enable debugging
    
    public function __construct() {
        $options = get_option('compare_pricing_options');
        $this->api_key = isset($options['amazon_api_key']) ? $options['amazon_api_key'] : '';
    }
    
    /**
     * Search for products on Amazon
     */
    public function search_products($query, $limit = 10) {
        if (empty($this->api_key)) {
            $this->log_debug('Amazon API key not configured');
            return new WP_Error('no_api_key', 'Amazon API key not configured');
        }
        
        $cache_key = 'amazon_search_' . md5($query . $limit);
        $cached_data = $this->get_cached_data($cache_key);
        
        if ($cached_data !== false) {
            $this->log_debug('Returning cached Amazon search results for: ' . $query);
            return $cached_data;
        }
        
        $params = array(
            'api_key' => $this->api_key,
            'type' => 'search',
            'amazon_domain' => 'amazon.com',
            'search_term' => $query,
            'max_page' => 1,
            'output' => 'json'
        );
        
        $this->log_debug('Making Amazon API search request with params: ' . print_r($params, true));
        
        $response = $this->make_request($params);
        
        if (is_wp_error($response)) {
            $this->log_debug('Amazon API error: ' . $response->get_error_message());
            return $response;
        }
        
        $this->log_debug('Amazon API raw response: ' . print_r($response, true));
        
        $products = $this->parse_search_response($response);
        $this->log_debug('Parsed ' . count($products) . ' Amazon products');
        
        $this->cache_data($cache_key, $products);
        
        return array_slice($products, 0, $limit);
    }
    
    /**
     * Get product details by ASIN
     */
    public function get_product_details($asin) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'Amazon API key not configured');
        }
        
        $cache_key = 'amazon_product_' . $asin;
        $cached_data = $this->get_cached_data($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $params = array(
            'api_key' => $this->api_key,
            'type' => 'product',
            'amazon_domain' => 'amazon.com',
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
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'Amazon API key not configured');
        }
        
        $cache_key = 'amazon_offers_' . $asin;
        $cached_data = $this->get_cached_data($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $params = array(
            'api_key' => $this->api_key,
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
    
    /**
     * Test Amazon API connection
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => 'Amazon API key not configured'
            );
        }
        
        // Test with a simple search
        $test_query = 'iPhone';
        $params = array(
            'api_key' => $this->api_key,
            'type' => 'search',
            'amazon_domain' => 'amazon.com',
            'search_term' => $test_query,
            'max_page' => 1,
            'output' => 'json'
        );
        
        $response = $this->make_request($params);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        if (isset($response['search_results']) && is_array($response['search_results'])) {
            return array(
                'success' => true,
                'message' => 'Amazon API connection successful! Found ' . count($response['search_results']) . ' test results.'
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Amazon API returned unexpected response format'
        );
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