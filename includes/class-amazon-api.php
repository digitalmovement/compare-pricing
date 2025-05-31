<?php
class Compare_Pricing_Amazon_API {
    
    private $api_key;
    private $base_url = 'https://api.asindataapi.com/request';
    private $debug;
    
    public function __construct($options = array()) {
        $this->api_key = isset($options['amazon_api_key']) ? $options['amazon_api_key'] : '';
        $this->debug = isset($options['debug_mode']) ? $options['debug_mode'] : false;
    }
    
    public function search_products($query, $max_results = 10) {
        $this->log_debug('Amazon API search called with query: ' . $query);
        
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
        
        $debug_info = array();
        
        // Step 1: Configuration check
        $debug_info['config_check'] = array(
            'status' => 'success',
            'title' => 'Configuration Check',
            'message' => 'API key configured',
            'details' => array(
                'API Key' => substr($this->api_key, 0, 8) . '...',
                'Base URL' => $this->base_url
            )
        );
        
        // Step 2: Prepare request
        $params = array(
            'api_key' => $this->api_key,
            'type' => 'search',
            'search_term' => $query,
            'amazon_domain' => 'amazon.com'
        );
        
        $debug_info['request_prep'] = array(
            'status' => 'success',
            'title' => 'Request Preparation',
            'message' => 'Search parameters prepared',
            'details' => array(
                'Search Term' => $query,
                'Type' => 'search',
                'Domain' => 'amazon.com',
                'Max Results' => $max_results
            )
        );
        
        // Step 3: Make API request
        $url = $this->base_url . '?' . http_build_query($params);
        
        $this->log_debug('Making request to: ' . $url);
        
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress Compare Pricing Plugin'
            )
        ));
        
        if (is_wp_error($response)) {
            $debug_info['api_request'] = array(
                'status' => 'error',
                'title' => 'API Request',
                'message' => 'Request failed: ' . $response->get_error_message(),
                'help' => 'Check your internet connection and API key'
            );
            
            return array(
                'success' => false,
                'error' => 'API request failed: ' . $response->get_error_message(),
                'debug' => $debug_info
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        $debug_info['api_request'] = array(
            'status' => $response_code == 200 ? 'success' : 'error',
            'title' => 'API Request',
            'message' => 'Response received',
            'details' => array(
                'Status Code' => $response_code,
                'Response Size' => strlen($body) . ' bytes'
            )
        );
        
        if ($response_code !== 200) {
            $debug_info['api_request']['message'] = 'API returned error status: ' . $response_code;
            $debug_info['api_request']['help'] = 'Check your API key and request parameters';
            
            return array(
                'success' => false,
                'error' => 'API returned status: ' . $response_code,
                'debug' => $debug_info
            );
        }
        
        // Step 4: Parse response
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $debug_info['response_parse'] = array(
                'status' => 'error',
                'title' => 'Response Parsing',
                'message' => 'Failed to parse JSON response',
                'help' => 'The API response is not valid JSON'
            );
            
            return array(
                'success' => false,
                'error' => 'Invalid JSON response',
                'debug' => $debug_info
            );
        }
        
        $debug_info['response_parse'] = array(
            'status' => 'success',
            'title' => 'Response Parsing',
            'message' => 'JSON parsed successfully'
        );
        
        // Step 5: Check API response status
        if (!isset($data['request_info']) || !$data['request_info']['success']) {
            $error_msg = 'API request failed';
            if (isset($data['request_info']['error'])) {
                $error_msg = $data['request_info']['error'];
            }
            
            $debug_info['api_status'] = array(
                'status' => 'error',
                'title' => 'API Status Check',
                'message' => $error_msg,
                'help' => 'Check your API key and credits remaining'
            );
            
            return array(
                'success' => false,
                'error' => $error_msg,
                'debug' => $debug_info
            );
        }
        
        $debug_info['api_status'] = array(
            'status' => 'success',
            'title' => 'API Status Check',
            'message' => 'API request successful',
            'details' => array(
                'Credits Used' => $data['request_info']['credits_used'] ?? 'Unknown',
                'Credits Remaining' => $data['request_info']['credits_remaining'] ?? 'Unknown'
            )
        );
        
        // Step 6: Process results
        $products = array();
        
        if (isset($data['search_results']) && is_array($data['search_results'])) {
            $count = 0;
            foreach ($data['search_results'] as $item) {
                if ($count >= $max_results) break;
                
                // Extract price
                $price = 0;
                if (isset($item['price']['value'])) {
                    $price = floatval($item['price']['value']);
                } elseif (isset($item['prices']) && is_array($item['prices']) && !empty($item['prices'])) {
                    $price = floatval($item['prices'][0]['value']);
                }
                
                if ($price > 0) {
                    $products[] = array(
                        'title' => $item['title'] ?? 'Unknown Product',
                        'price' => $price,
                        'currency' => $item['price']['currency'] ?? 'USD',
                        'url' => $item['link'] ?? '',
                        'image' => $item['image'] ?? '',
                        'rating' => $item['rating'] ?? null,
                        'reviews' => $item['ratings_total'] ?? null,
                        'source' => 'amazon',
                        'asin' => $item['asin'] ?? '',
                        'is_prime' => $item['is_prime'] ?? false
                    );
                    $count++;
                }
            }
        }
        
        $debug_info['results_processing'] = array(
            'status' => 'success',
            'title' => 'Results Processing',
            'message' => count($products) . ' products processed',
            'details' => array(
                'Total Results Found' => count($data['search_results'] ?? array()),
                'Valid Products' => count($products),
                'Max Results Limit' => $max_results
            )
        );
        
        $this->log_debug('Amazon search completed. Found ' . count($products) . ' products');
        
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
} 