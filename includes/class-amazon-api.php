<?php
class Compare_Pricing_Amazon_API {
    
    private $api_key;
    private $base_url = 'https://api.asindataapi.com/request';
    private $debug;
    
    public function __construct($options = array()) {
        $this->api_key = isset($options['amazon_api_key']) ? $options['amazon_api_key'] : '';
        $this->debug = isset($options['debug_mode']) ? $options['debug_mode'] : false;
    }
    
    public function search_products($query, $max_results = 10, $country_code = 'US') {
        $this->log_debug('Amazon API search called with query: ' . $query . ' for country: ' . $country_code);
        
        if (empty($this->api_key)) {
            $this->log_debug('Amazon API key not configured');
            return array(
                'success' => false,
                'error' => 'Amazon API key not configured',
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
        
        $this->log_debug('Amazon API key found: ' . substr($this->api_key, 0, 8) . '...');
        
        // Get marketplace information
        $marketplace_info = $this->get_marketplace_info($country_code);
        
        $debug_info = array();
        
        // Build request parameters
        $params = array(
            'api_key' => $this->api_key,
            'type' => 'search',
            'search_term' => $query,
            'amazon_domain' => $marketplace_info['domain'],
            'max_page' => 1,
            'sort_by' => 'price_low_to_high',
            'output' => 'json'
        );
        
        $debug_info['request'] = array(
            'status' => 'info',
            'title' => 'API Request',
            'message' => 'Searching Amazon ' . $marketplace_info['name'] . ' for: ' . $query,
            'details' => array(
                'Domain' => $marketplace_info['domain'],
                'Country' => $country_code,
                'Max Results' => $max_results
            )
        );
        
        $this->log_debug('Making request to: ' . $this->base_url);
        $this->log_debug('Parameters: ' . print_r($params, true));
        
        $response = wp_remote_post($this->base_url, array(
            'body' => $params,
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress Compare Pricing Plugin'
            )
        ));
        
        if (is_wp_error($response)) {
            $error_message = 'Request failed: ' . $response->get_error_message();
            $this->log_debug($error_message);
            
            return array(
                'success' => false,
                'error' => $error_message,
                'debug' => array_merge($debug_info, array(
                    'request_error' => array(
                        'status' => 'error',
                        'title' => 'Request Error',
                        'message' => $error_message
                    )
                ))
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = 'Invalid JSON response: ' . json_last_error_msg();
            $this->log_debug($error_message);
            
            return array(
                'success' => false,
                'error' => $error_message,
                'debug' => array_merge($debug_info, array(
                    'json_error' => array(
                        'status' => 'error',
                        'title' => 'JSON Parse Error',
                        'message' => $error_message,
                        'raw_response' => substr($body, 0, 500) . '...'
                    )
                ))
            );
        }
        
        if (!isset($data['search_results']) || !is_array($data['search_results'])) {
            $error_message = 'No search results in response';
            if (isset($data['message'])) {
                $error_message .= ': ' . $data['message'];
            }
            
            $this->log_debug($error_message);
            
            return array(
                'success' => false,
                'error' => $error_message,
                'debug' => array_merge($debug_info, array(
                    'no_results' => array(
                        'status' => 'warning',
                        'title' => 'No Results',
                        'message' => $error_message,
                        'response_keys' => array_keys($data)
                    )
                ))
            );
        }
        
        $products = array();
        $results_processed = 0;
        
        foreach ($data['search_results'] as $item) {
            if ($results_processed >= $max_results) {
                break;
            }
            
            // Extract price
            $price = 0;
            if (isset($item['price']['raw'])) {
                $price = floatval($item['price']['raw']);
            } elseif (isset($item['price']['symbol']) && isset($item['price']['value'])) {
                $price = floatval(str_replace(',', '', $item['price']['value']));
            }
            
            if ($price > 0) {
                $products[] = array(
                    'title' => isset($item['title']) ? $item['title'] : 'Unknown Product',
                    'price' => $price,
                    'currency' => $marketplace_info['currency'],
                    'url' => isset($item['link']) ? $item['link'] : '',
                    'image' => isset($item['image']) ? $item['image'] : '',
                    'source' => 'amazon',
                    'marketplace' => $marketplace_info['name'],
                    'country' => $country_code,
                    'rating' => isset($item['rating']) ? $item['rating'] : null,
                    'reviews' => isset($item['reviews_count']) ? $item['reviews_count'] : null
                );
                $results_processed++;
            }
        }
        
        $debug_info['results'] = array(
            'status' => 'success',
            'title' => 'Results Processing',
            'message' => 'Found ' . count($products) . ' products with valid prices',
            'details' => array(
                'Total items in response' => count($data['search_results']),
                'Items with valid prices' => count($products),
                'Marketplace' => $marketplace_info['name']
            )
        );
        
        $this->log_debug('Processed ' . count($products) . ' products from Amazon ' . $marketplace_info['name']);
        
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
        
        // Test with a simple search
        $result = $this->search_products('test', 1);
        
        if ($result['success']) {
            // Add connection test specific info
            $result['debug']['connection_test'] = array(
                'status' => 'success',
                'title' => 'Connection Test',
                'message' => 'Successfully connected to ASIN Data API',
                'note' => 'API is working correctly'
            );
        }
        
        return $result;
    }
    
    private function log_debug($message) {
        if ($this->debug && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Amazon API Debug] ' . $message);
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