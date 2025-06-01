<?php
class Compare_Pricing_Amazon_API {
    
    private $api_key;
    private $base_url = 'https://api.asindataapi.com/request';
    private $debug;
    
    public function __construct($options = array()) {
        $this->api_key = isset($options['amazon_api_key']) ? trim($options['amazon_api_key']) : '';
        $this->debug = isset($options['debug_mode']) ? $options['debug_mode'] : get_option('compare_pricing_debug_mode', 0);
        
        // Log initialization
        $this->log_debug('Amazon API initialized with debug mode: ' . ($this->debug ? 'ON' : 'OFF'));
        $this->log_debug('API key length: ' . strlen($this->api_key));
    }
    
    public function search_products($query, $max_results = 10, $country_code = 'US') {
        $this->log_debug('=== Amazon API Search Started ===');
        $this->log_debug('Query: ' . $query);
        $this->log_debug('Country: ' . $country_code);
        $this->log_debug('Max Results: ' . $max_results);
        
        if (empty($this->api_key)) {
            $this->log_debug('ERROR: Amazon API key not configured');
            return array(
                'success' => false,
                'error' => 'Amazon API key not configured',
                'debug' => array(
                    'config_check' => array(
                        'status' => 'error',
                        'title' => 'Configuration Check',
                        'message' => 'Amazon API key is missing',
                        'help' => 'Please configure your ASIN Data API key in the plugin settings. Get your key from: https://asindataapi.com/'
                    )
                )
            );
        }
        
        $this->log_debug('API key found: ' . substr($this->api_key, 0, 8) . '...' . substr($this->api_key, -4));
        
        // Get marketplace information
        $marketplace_info = $this->get_marketplace_info($country_code);
        $this->log_debug('Marketplace: ' . $marketplace_info['name'] . ' (' . $marketplace_info['domain'] . ')');
        
        $debug_info = array();
        
        // Build request parameters for ASIN Data API
        $params = array(
            'api_key' => $this->api_key,
            'type' => 'search',
            'search_term' => $query,
            'amazon_domain' => $marketplace_info['domain'],
            'max_page' => 1,
            'sort_by' => 'price_low_to_high',
            'output' => 'json'
        );
        
        $this->log_debug('Request parameters: ' . print_r($params, true));
        
        $debug_info['request'] = array(
            'status' => 'info',
            'title' => 'API Request',
            'message' => 'Searching Amazon ' . $marketplace_info['name'] . ' for: ' . $query,
            'details' => array(
                'Domain' => $marketplace_info['domain'],
                'Country' => $country_code,
                'Max Results' => $max_results,
                'API Endpoint' => $this->base_url,
                'API Key (partial)' => substr($this->api_key, 0, 8) . '...' . substr($this->api_key, -4)
            )
        );
        
        $this->log_debug('Making POST request to: ' . $this->base_url);
        
        $api_url = add_query_arg($params, $this->base_url);

        $response = wp_remote_post($api_url, array(
            'body' => $params,
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress Compare Pricing Plugin v1.0',
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));
        
        if (is_wp_error($response)) {
            $error_message = 'Request failed: ' . $response->get_error_message();
            $this->log_debug('ERROR: ' . $error_message);
            
            return array(
                'success' => false,
                'error' => $error_message,
                'debug' => array_merge($debug_info, array(
                    'request_error' => array(
                        'status' => 'error',
                        'title' => 'Request Error',
                        'message' => $error_message,
                        'help' => 'Check your server\'s internet connection and firewall settings'
                    )
                ))
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        $this->log_debug('Response code: ' . $response_code);
        $this->log_debug('Response body (first 1000 chars): ' . substr($body, 0, 1000));
        
        if ($response_code !== 200) {
            $debug_info['response_error'] = array(
                'status' => 'error',
                'title' => 'HTTP Response Error',
                'message' => 'API returned HTTP ' . $response_code,
                'details' => array(
                    'Response Code' => $response_code,
                    'Response Body' => substr($body, 0, 500) . '...'
                ),
                'help' => 'Check your API key and account status at asindataapi.com'
            );
            
            return array(
                'success' => false,
                'error' => 'API returned HTTP ' . $response_code,
                'debug' => $debug_info
            );
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_debug('ERROR: JSON decode failed - ' . json_last_error_msg());
            
            $debug_info['json_error'] = array(
                'status' => 'error',
                'title' => 'JSON Parse Error',
                'message' => 'Failed to parse API response: ' . json_last_error_msg(),
                'details' => array(
                    'JSON Error' => json_last_error_msg(),
                    'Raw Response' => substr($body, 0, 500) . '...'
                )
            );
            
            return array(
                'success' => false,
                'error' => 'Invalid JSON response',
                'debug' => $debug_info
            );
        }
        
        $this->log_debug('Parsed JSON response: ' . print_r($data, true));
        
        // Check for API errors in response
        if (isset($data['error'])) {
            $this->log_debug('ERROR: API returned error - ' . $data['error']);
            
            $debug_info['api_error'] = array(
                'status' => 'error',
                'title' => 'API Error',
                'message' => 'ASIN Data API returned error: ' . $data['error'],
                'help' => $this->get_error_help($data['error'])
            );
            
            return array(
                'success' => false,
                'error' => $data['error'],
                'debug' => $debug_info
            );
        }
        
        // Check if we have search results
        if (!isset($data['search_results']) || !is_array($data['search_results'])) {
            $this->log_debug('WARNING: No search results found in response');
            
            $debug_info['no_results'] = array(
                'status' => 'warning',
                'title' => 'No Results',
                'message' => 'No search results found in API response',
                'details' => array(
                    'Response Keys' => implode(', ', array_keys($data)),
                    'Search Term' => $query,
                    'Marketplace' => $marketplace_info['name']
                )
            );
            
            return array(
                'success' => true,
                'products' => array(),
                'debug' => $debug_info
            );
        }
        
        $this->log_debug('Found ' . count($data['search_results']) . ' search results');
        
        $products = array();
        $results_processed = 0;
        
        foreach ($data['search_results'] as $index => $item) {
            if ($results_processed >= $max_results) {
                break;
            }
            
            $this->log_debug('Processing item ' . $index . ': ' . (isset($item['title']) ? $item['title'] : 'No title'));
            
            // Skip items without price
            if (!isset($item['price']['raw']) || $item['price']['raw'] <= 0) {
                $this->log_debug('Skipping item - no valid price');
                continue;
            }
            
            $products[] = array(
                'title' => isset($item['title']) ? $item['title'] : 'Unknown Product',
                'price' => floatval($item['price']['raw']),
                'currency' => $marketplace_info['currency'],
                'url' => isset($item['link']) ? $item['link'] : '',
                'image' => isset($item['image']) ? $item['image'] : '',
                'source' => 'amazon',
                'marketplace' => $marketplace_info['name'],
                'country' => $country_code,
                'rating' => isset($item['rating']) ? $item['rating'] : null,
                'reviews' => isset($item['reviews_count']) ? $item['reviews_count'] : null,
                'asin' => isset($item['asin']) ? $item['asin'] : null
            );
            $results_processed++;
            
            $this->log_debug('Added product: ' . $item['title'] . ' - $' . $item['price']['raw']);
        }
        
        $debug_info['results'] = array(
            'status' => 'success',
            'title' => 'Results Processing',
            'message' => 'Found ' . count($products) . ' products with valid prices',
            'details' => array(
                'Total items in response' => count($data['search_results']),
                'Items with valid prices' => count($products),
                'Marketplace' => $marketplace_info['name'],
                'Search Term' => $query
            )
        );
        
        $this->log_debug('=== Amazon API Search Completed ===');
        $this->log_debug('Returning ' . count($products) . ' products');
        
        return array(
            'success' => true,
            'products' => $products,
            'debug' => $debug_info
        );
    }
    
    public function search_by_gtin($gtin) {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'error' => 'Amazon API key not configured'
            );
        }
        
        // For GTIN search, we'll search for the GTIN as a search term
        // The ASIN Data API doesn't have a specific GTIN endpoint, but searching for the GTIN often works
        return $this->search_products($gtin, 5);
    }
    
    public function test_connection() {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'error' => 'API key not configured',
                'debug' => array(
                    'config_check' => array(
                        'status' => 'error',
                        'title' => 'Configuration Check',
                        'message' => 'Amazon API key is missing',
                        'help' => 'Please configure your ASIN Data API key in the plugin settings'
                    )
                )
            );
        }
        
        $debug_info = array();
        
        // Test API key validity with a simple request
        $test_params = array(
            'api_key' => $this->api_key,
            'type' => 'search',
            'search_term' => 'test',
            'amazon_domain' => 'amazon.com',
            'max_page' => 1,
            'output' => 'json'
        );
        
    
        $debug_info['connection_test'] = array(
            'status' => 'checking',
            'title' => 'Connection Test',
            'message' => 'Testing connection to ASIN Data API...'
        );

        $api_url = add_query_arg($test_params, $this->base_url);

        $response = wp_remote_post($api_url, array(
            'body' => $test_params,
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'WordPress Compare Pricing Plugin Test',
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));
        
        if (is_wp_error($response)) {
            $debug_info['connection_test'] = array(
                'status' => 'error',
                'title' => 'Connection Test',
                'message' => 'Failed to connect: ' . $response->get_error_message(),
                'help' => 'Check your server\'s internet connection and firewall settings'
            );
            
            return array(
                'success' => false,
                'error' => 'Connection failed',
                'debug' => $debug_info
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $debug_info['connection_test'] = array(
                'status' => 'error',
                'title' => 'Connection Test',
                'message' => 'API returned HTTP ' . $response_code,
                'details' => array(
                    'Response Code' => $response_code,
                    'Response Body' => substr($body, 0, 200) . '...'
                ),
                'help' => 'Check your API key and account status at asindataapi.com'
            );
            
            return array(
                'success' => false,
                'error' => 'HTTP ' . $response_code,
                'debug' => $debug_info
            );
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $debug_info['connection_test'] = array(
                'status' => 'error',
                'title' => 'Connection Test',
                'message' => 'Invalid JSON response: ' . json_last_error_msg(),
                'details' => array(
                    'Raw Response' => substr($body, 0, 300) . '...'
                )
            );
            
            return array(
                'success' => false,
                'error' => 'Invalid response format',
                'debug' => $debug_info
            );
        }
        
        // Check for API errors
        if (isset($data['error'])) {
            $debug_info['connection_test'] = array(
                'status' => 'error',
                'title' => 'Connection Test',
                'message' => 'API Error: ' . $data['error'],
                'help' => $this->get_error_help($data['error'])
            );
            
            return array(
                'success' => false,
                'error' => $data['error'],
                'debug' => $debug_info
            );
        }
        
        // Success!
        $debug_info['connection_test'] = array(
            'status' => 'success',
            'title' => 'Connection Test',
            'message' => 'Successfully connected to ASIN Data API',
            'details' => array(
                'API Response' => 'Valid',
                'Search Results' => isset($data['search_results']) ? count($data['search_results']) : 0,
                'Credits Used' => isset($data['request_info']['credits_used']) ? $data['request_info']['credits_used'] : 'Unknown'
            ),
            'note' => 'API is working correctly'
        );
        
        return array(
            'success' => true,
            'debug' => $debug_info
        );
    }
    
    private function get_error_help($error) {
        $error_lower = strtolower($error);
        
        if (strpos($error_lower, 'invalid api key') !== false) {
            return 'Your API key is invalid. Please check it at asindataapi.com';
        } elseif (strpos($error_lower, 'credits') !== false || strpos($error_lower, 'limit') !== false) {
            return 'You may have exceeded your API credit limit. Check your account at asindataapi.com';
        } elseif (strpos($error_lower, 'rate') !== false) {
            return 'You are making requests too quickly. Please wait and try again';
        } else {
            return 'Check your API key and account status at asindataapi.com';
        }
    }
    
    private function log_debug($message) {
        if ($this->debug) {
            error_log('[Compare Pricing - Amazon API] ' . $message);
        }
    }
    
    /**
     * Get marketplace information for different countries
     */
    private function get_marketplace_info($country_code) {
        $marketplaces = array(
            'US' => array('domain' => 'amazon.com', 'name' => 'Amazon.com', 'currency' => 'USD'),
            'GB' => array('domain' => 'amazon.co.uk', 'name' => 'Amazon.co.uk', 'currency' => 'GBP'),
            'DE' => array('domain' => 'amazon.de', 'name' => 'Amazon.de', 'currency' => 'EUR'),
            'FR' => array('domain' => 'amazon.fr', 'name' => 'Amazon.fr', 'currency' => 'EUR'),
            'IT' => array('domain' => 'amazon.it', 'name' => 'Amazon.it', 'currency' => 'EUR'),
            'ES' => array('domain' => 'amazon.es', 'name' => 'Amazon.es', 'currency' => 'EUR'),
            'CA' => array('domain' => 'amazon.ca', 'name' => 'Amazon.ca', 'currency' => 'CAD'),
            'AU' => array('domain' => 'amazon.com.au', 'name' => 'Amazon.com.au', 'currency' => 'AUD'),
            'JP' => array('domain' => 'amazon.co.jp', 'name' => 'Amazon.co.jp', 'currency' => 'JPY'),
            'IN' => array('domain' => 'amazon.in', 'name' => 'Amazon.in', 'currency' => 'INR'),
        );
        
        return isset($marketplaces[$country_code]) ? $marketplaces[$country_code] : $marketplaces['US'];
    }
} 