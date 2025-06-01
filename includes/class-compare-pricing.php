<?php

class Compare_Pricing {
    
    private $ebay_api;
    private $amazon_api;
    
    public function __construct() {
        // Initialize API classes
        $this->ebay_api = new Compare_Pricing_eBay_API();
        
        // Initialize Amazon API with options
        $amazon_options = array(
            'amazon_api_key' => get_option('compare_pricing_amazon_api_key', ''),
            'debug_mode' => get_option('compare_pricing_debug_mode', 0)
        );
        $this->amazon_api = new Compare_Pricing_Amazon_API($amazon_options);
        
        // Hook into WordPress
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_compare_pricing', array($this, 'handle_ajax_compare'));
        add_action('wp_ajax_nopriv_compare_pricing', array($this, 'handle_ajax_compare'));
        add_shortcode('compare_pricing', array($this, 'shortcode_handler'));
    }
    
    public function init() {
        load_plugin_textdomain('compare-pricing', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function enqueue_scripts() {
        // Only enqueue on pages that might have the shortcode
        if (is_singular() || is_shop() || is_product_category() || is_product_tag()) {
            wp_enqueue_script(
                'compare-pricing-js',
                COMPARE_PRICING_URL . 'assets/js/compare-pricing.js',
                array('jquery'),
                COMPARE_PRICING_VERSION,
                true
            );
            
            wp_enqueue_style(
                'compare-pricing-css',
                COMPARE_PRICING_URL . 'assets/css/compare-pricing.css',
                array(),
                COMPARE_PRICING_VERSION
            );
            
            wp_localize_script('compare-pricing-js', 'comparePricing', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('compare_pricing_nonce'),
                'loading_text' => __('Finding you the cheapest price...', 'compare-pricing')
            ));
        }
    }
    
    /**
     * Shortcode handler for [compare_pricing]
     * 
     * Usage examples:
     * [compare_pricing] - Uses current product (on product pages)
     * [compare_pricing product_id="123"] - Uses specific product ID
     * [compare_pricing gtin="1234567890123"] - Uses specific GTIN
     */
    public function shortcode_handler($atts) {
        $atts = shortcode_atts(array(
            'product_id' => '',
            'gtin' => '',
            'show_title' => 'true',
            'title' => 'Price Comparison'
        ), $atts, 'compare_pricing');
        
        $product_id = '';
        $gtin = '';
        
        // If GTIN is provided directly, use it
        if (!empty($atts['gtin'])) {
            $gtin = sanitize_text_field($atts['gtin']);
            $product_id = 'custom'; // Use custom identifier for direct GTIN
        }
        // If product_id is provided, get GTIN from that product
        elseif (!empty($atts['product_id'])) {
            $product_id = intval($atts['product_id']);
            $gtin = $this->get_product_gtin($product_id);
        }
        // Otherwise, try to get current product (if on product page)
        else {
            global $product;
            if ($product && is_a($product, 'WC_Product')) {
                $product_id = $product->get_id();
                $gtin = $this->get_product_gtin($product_id);
            }
        }
        
        // If no GTIN found, return nothing or error message
        if (empty($gtin)) {
            if (current_user_can('manage_options')) {
                return '<div class="compare-pricing-error"><p>No GTIN found for price comparison. Please add a GTIN to this product.</p></div>';
            }
            return '';
        }
        
        // Generate unique ID for this instance
        $instance_id = 'compare-pricing-' . md5($gtin . $product_id);
        
        $output = '';
        
        // Add title if requested
        if ($atts['show_title'] === 'true' && !empty($atts['title'])) {
            $output .= '<h4 class="compare-pricing-title">' . esc_html($atts['title']) . '</h4>';
        }
        
        $output .= '<div id="' . esc_attr($instance_id) . '" class="compare-pricing-container" data-gtin="' . esc_attr($gtin) . '" data-product-id="' . esc_attr($product_id) . '">';
        $output .= '<div class="compare-pricing-content"></div>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Get GTIN from product ID
     * Checks multiple possible GTIN fields in order of preference
     */
    private function get_product_gtin($product_id) {
        if (empty($product_id) || $product_id === 'custom') {
            return '';
        }
        
        // Priority order for GTIN fields:
        // 1. WooCommerce default Global Unique ID field
        // 2. WooCommerce Google Listings & Ads field
        // 3. Custom _gtin field
        
        $gtin_fields = array(
            '_global_unique_id',    // WooCommerce default GTIN field
            '_wc_gla_gtin',        // WooCommerce Google Listings & Ads
            '_gtin'                // Custom field fallback
        );
        
        foreach ($gtin_fields as $field) {
            $gtin = get_post_meta($product_id, $field, true);
            if (!empty($gtin)) {
                return $gtin;
            }
        }
        
        return '';
    }
    
    public function handle_ajax_compare() {
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'compare_pricing_nonce')) {
                wp_send_json_error('Security check failed');
                return;
            }

            // Get request data
            $gtin = isset($_POST['gtin']) ? sanitize_text_field($_POST['gtin']) : '';
            $product_id = isset($_POST['product_id']) ? sanitize_text_field($_POST['product_id']) : '';
            $location = isset($_POST['location']) ? $_POST['location'] : array('country_code' => 'US');

            if (empty($gtin)) {
                wp_send_json_error('GTIN is required');
                return;
            }

            // Sanitize location data
            $location = array(
                'country_code' => isset($location['country_code']) ? sanitize_text_field($location['country_code']) : 'US',
                'country_name' => isset($location['country_name']) ? sanitize_text_field($location['country_name']) : 'United States',
                'detected' => isset($location['detected']) ? (bool)$location['detected'] : false
            );

            // Get cached or fresh results with location
            $result = $this->get_cached_or_fetch_results($gtin, $product_id, $location);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['error']);
            }
            
        } catch (Exception $e) {
            error_log('Compare Pricing Exception: ' . $e->getMessage());
            wp_send_json_error('An error occurred: ' . $e->getMessage());
        }
    }
    
    private function display_price_comparison($search_term, $limit = 5) {
        echo '<div class="compare-pricing-container">';
        echo '<h3>Price Comparison for: ' . esc_html($search_term) . '</h3>';
        
        // Get eBay results
        echo '<div class="pricing-section ebay-section">';
        echo '<h4><img src="' . COMPARE_PRICING_URL . 'assets/images/ebay-logo.png" alt="eBay" class="platform-logo"> eBay Results</h4>';
        $ebay_results = $this->ebay_api->search_products($search_term, $limit);
        
        if (is_wp_error($ebay_results)) {
            echo '<p class="error">eBay Error: ' . $ebay_results->get_error_message() . '</p>';
        } elseif (empty($ebay_results)) {
            echo '<p class="no-results">No eBay results found.</p>';
        } else {
            echo '<div class="pricing-grid">';
            foreach ($ebay_results as $item) {
                $this->display_product_item($item);
            }
            echo '</div>';
        }
        echo '</div>';
        
        // Get Amazon results
        echo '<div class="pricing-section amazon-section">';
        echo '<h4><img src="' . COMPARE_PRICING_URL . 'assets/images/amazon-logo.png" alt="Amazon" class="platform-logo"> Amazon Results</h4>';
        $amazon_results = $this->amazon_api->search_products($search_term, $limit);
        
        if (is_wp_error($amazon_results)) {
            echo '<p class="error">Amazon Error: ' . $amazon_results->get_error_message() . '</p>';
        } elseif (empty($amazon_results)) {
            echo '<p class="no-results">No Amazon results found.</p>';
        } else {
            echo '<div class="pricing-grid">';
            foreach ($amazon_results as $item) {
                $this->display_product_item($item);
            }
            echo '</div>';
        }
        echo '</div>';
        
        // Combined comparison
        $all_results = array();
        if (!is_wp_error($ebay_results) && !empty($ebay_results)) {
            $all_results = array_merge($all_results, $ebay_results);
        }
        if (!is_wp_error($amazon_results) && !empty($amazon_results)) {
            $all_results = array_merge($all_results, $amazon_results);
        }
        
        if (!empty($all_results)) {
            // Sort by price
            usort($all_results, function($a, $b) {
                return $a['price'] <=> $b['price'];
            });
            
            echo '<div class="pricing-section best-deals-section">';
            echo '<h4>🏆 Best Deals (All Platforms)</h4>';
            echo '<div class="pricing-grid">';
            foreach (array_slice($all_results, 0, $limit) as $item) {
                $this->display_product_item($item, true);
            }
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    private function display_product_item($item, $show_source = false) {
        $price_display = $item['price'] > 0 ? '$' . number_format($item['price'], 2) : 'Price not available';
        $source_class = $item['source'];
        $source_badge = '';
        
        if ($show_source) {
            $source_badge = '<span class="source-badge ' . $source_class . '">' . ucfirst($item['source']) . '</span>';
        }
        
        echo '<div class="pricing-item ' . $source_class . '">';
        
        if (!empty($item['image'])) {
            echo '<div class="item-image">';
            echo '<img src="' . esc_url($item['image']) . '" alt="' . esc_attr($item['title']) . '" loading="lazy">';
            echo '</div>';
        }
        
        echo '<div class="item-details">';
        echo '<h5 class="item-title">' . esc_html(wp_trim_words($item['title'], 10)) . '</h5>';
        echo '<div class="item-price">' . $price_display . '</div>';
        
        if ($item['source'] === 'amazon' && isset($item['prime']) && $item['prime']) {
            echo '<div class="prime-badge">Prime</div>';
        }
        
        if (isset($item['rating']) && $item['rating'] > 0) {
            echo '<div class="item-rating">';
            echo str_repeat('⭐', floor($item['rating']));
            echo ' (' . $item['rating'] . ')';
            if (isset($item['review_count']) && $item['review_count'] > 0) {
                echo ' - ' . number_format($item['review_count']) . ' reviews';
            }
            echo '</div>';
        }
        
        echo $source_badge;
        
        if (!empty($item['url'])) {
            echo '<a href="' . esc_url($item['url']) . '" target="_blank" rel="noopener" class="view-item-btn">View Item</a>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    private function get_api_options() {
        return array(
            'ebay_app_id' => get_option('compare_pricing_ebay_app_id'),
            'ebay_cert_id' => get_option('compare_pricing_ebay_cert_id'),
            'ebay_dev_id' => get_option('compare_pricing_ebay_dev_id'),
            'amazon_api_key' => get_option('compare_pricing_amazon_api_key'),
            'sandbox_mode' => get_option('compare_pricing_sandbox_mode', 0),
            'debug_mode' => get_option('compare_pricing_debug_mode', 0)
        );
    }

    /**
     * Extract keywords from product title for matching
     */
    private function extract_keywords($title) {
        // Convert to lowercase and remove common words
        $title = strtolower($title);
        
        // Remove common stop words that don't help with product matching
        $stop_words = array(
            'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
            'from', 'up', 'about', 'into', 'through', 'during', 'before', 'after', 'above',
            'below', 'between', 'among', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been',
            'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should',
            'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those', 'i', 'you',
            'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them', 'my', 'your',
            'his', 'her', 'its', 'our', 'their', 'new', 'old', 'good', 'best', 'great',
            'free', 'shipping', 'fast', 'quick', 'sale', 'deal', 'offer', 'price', 'cheap',
            'discount', 'save', 'buy', 'get', 'now', 'today', 'limited', 'time', 'only'
        );
        
        // Remove punctuation and split into words
        $title = preg_replace('/[^\w\s]/', ' ', $title);
        $words = preg_split('/\s+/', $title);
        
        // Filter out stop words and short words
        $keywords = array();
        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) >= 3 && !in_array($word, $stop_words) && !is_numeric($word)) {
                $keywords[] = $word;
            }
        }
        
        return array_unique($keywords);
    }

    /**
     * Check if API result matches the WooCommerce product
     */
    private function is_relevant_product($api_title, $wc_product_title, $min_matches = null) {
        if ($min_matches === null) {
            $min_matches = get_option('compare_pricing_min_keyword_matches', 2);
        }
        
        $wc_keywords = $this->extract_keywords($wc_product_title);
        $api_keywords = $this->extract_keywords($api_title);
        
        if (empty($wc_keywords) || empty($api_keywords)) {
            return false;
        }
        
        // Count matching keywords
        $matches = 0;
        $matched_keywords = array();
        
        foreach ($wc_keywords as $wc_keyword) {
            foreach ($api_keywords as $api_keyword) {
                // Exact match
                if ($wc_keyword === $api_keyword) {
                    $matches++;
                    $matched_keywords[] = $wc_keyword;
                    break;
                }
                // Partial match (one contains the other)
                elseif (strlen($wc_keyword) >= 4 && strlen($api_keyword) >= 4) {
                    if (strpos($wc_keyword, $api_keyword) !== false || strpos($api_keyword, $wc_keyword) !== false) {
                        $matches++;
                        $matched_keywords[] = $wc_keyword . '~' . $api_keyword;
                        break;
                    }
                }
            }
        }
        
        // For debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Product matching: WC='$wc_product_title' vs API='$api_title'");
            error_log("WC Keywords: " . implode(', ', $wc_keywords));
            error_log("API Keywords: " . implode(', ', $api_keywords));
            error_log("Matches: $matches (" . implode(', ', $matched_keywords) . ")");
            error_log("Relevant: " . ($matches >= $min_matches ? 'YES' : 'NO'));
        }
        
        return $matches >= $min_matches;
    }

    /**
     * Get WooCommerce product title from product ID
     */
    private function get_wc_product_title($product_id) {
        if (empty($product_id) || $product_id === 'custom') {
            return '';
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return '';
        }
        
        return $product->get_name();
    }

    /**
     * Track daily statistics
     */
    private function track_daily_stats($type, $increment = 1) {
        $today = date('Y-m-d');
        $stats_key = 'compare_pricing_daily_stats_' . $today;
        $stats = get_option($stats_key, array(
            'cache_hits' => 0,
            'api_calls' => 0,
            'successful_lookups' => 0,
            'failed_lookups' => 0
        ));
        
        $stats[$type] += $increment;
        update_option($stats_key, $stats);
        
        // Clean up old daily stats (keep last 30 days)
        $this->cleanup_old_daily_stats();
    }

    /**
     * Clean up old daily statistics
     */
    private function cleanup_old_daily_stats() {
        global $wpdb;
        
        // Only run cleanup occasionally
        if (rand(1, 100) > 5) { // 5% chance
            return;
        }
        
        $cutoff_date = date('Y-m-d', strtotime('-30 days'));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'compare_pricing_daily_stats_%' AND option_name < %s",
            'compare_pricing_daily_stats_' . $cutoff_date
        ));
    }

    /**
     * Record failed lookup for admin review
     */
    private function record_failed_lookup($gtin, $product_id, $errors, $failure_reason = 'Unknown failure') {
        $failed_lookups = get_option('compare_pricing_failed_lookups', array());
        
        // Check if this GTIN already exists in recent failures
        $existing_index = -1;
        foreach ($failed_lookups as $index => $lookup) {
            if ($lookup['gtin'] === $gtin && 
                strtotime($lookup['timestamp']) > strtotime('-24 hours')) {
                $existing_index = $index;
                break;
            }
        }
        
        // Determine failure type for better categorization
        $failure_type = 'unknown';
        if (strpos($failure_reason, 'keyword matching') !== false || strpos($failure_reason, 'none matched') !== false) {
            $failure_type = 'keyword_mismatch';
        } elseif (strpos($failure_reason, 'No APIs configured') !== false) {
            $failure_type = 'no_apis';
        } elseif (strpos($failure_reason, 'All APIs failed') !== false) {
            $failure_type = 'api_failure';
        } elseif (strpos($failure_reason, 'No results found') !== false) {
            $failure_type = 'no_results';
        }
        
        $lookup_data = array(
            'gtin' => $gtin,
            'product_id' => $product_id,
            'timestamp' => current_time('mysql'),
            'errors' => $errors,
            'failure_reason' => $failure_reason,
            'failure_type' => $failure_type,
            'attempt_count' => 1
        );
        
        if ($existing_index >= 0) {
            // Update existing entry and increment attempt count
            $lookup_data['attempt_count'] = $failed_lookups[$existing_index]['attempt_count'] + 1;
            $failed_lookups[$existing_index] = $lookup_data;
        } else {
            // Add new entry
            array_unshift($failed_lookups, $lookup_data);
        }
        
        // Keep only last 200 failed lookups
        $failed_lookups = array_slice($failed_lookups, 0, 200);
        
        update_option('compare_pricing_failed_lookups', $failed_lookups);
        
        // Track in daily stats
        $this->track_daily_stats('failed_lookups');
    }

    /**
     * Get cached results or fetch fresh ones with location support
     */
    private function get_cached_or_fetch_results($gtin, $product_id, $location = array()) {
        // Include location in cache key
        $location_key = isset($location['country_code']) ? $location['country_code'] : 'US';
        $cache_key = 'compare_pricing_' . md5($gtin . '_' . $location_key);
        $cache_duration = get_option('compare_pricing_cache_duration', 24) * HOUR_IN_SECONDS;
        
        // Try to get cached results
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            $this->track_daily_stats('cache_hits');
            $cached_result['cached'] = true;
            return $cached_result;
        }
        
        // Fetch fresh results
        $this->track_daily_stats('api_calls');
        $result = $this->fetch_fresh_results($gtin, $product_id, $location);
        
        // Cache successful results
        if ($result['success']) {
            set_transient($cache_key, $result, $cache_duration);
        }
        
        return $result;
    }

    /**
     * Fetch fresh results from APIs with location support
     */
    private function fetch_fresh_results($gtin, $product_id, $location = array()) {
        $country_code = isset($location['country_code']) ? $location['country_code'] : 'US';
        
        // Get WooCommerce product title for matching
        $wc_product_title = $this->get_wc_product_title($product_id);
        
        $all_results = array();
        $filtered_results = array();
        $errors = array();
        $api_attempts = 0;
        $successful_apis = 0;
        $filtering_stats = array(
            'total_found' => 0,
            'relevant_found' => 0,
            'ebay_total' => 0,
            'ebay_relevant' => 0,
            'amazon_total' => 0,
            'amazon_relevant' => 0,
            'location' => $location
        );

        // Try eBay API with location
        if ($this->ebay_api) {
            $api_attempts++;
            $ebay_results = $this->ebay_api->search_products($gtin, 10, $country_code);
            
            if (is_wp_error($ebay_results)) {
                $errors['ebay'] = $ebay_results->get_error_message();
            } elseif (!empty($ebay_results)) {
                $all_results = array_merge($all_results, $ebay_results);
                $successful_apis++;
                $filtering_stats['ebay_total'] = count($ebay_results);
                
                // Filter eBay results for relevance
                if (!empty($wc_product_title)) {
                    foreach ($ebay_results as $result) {
                        if ($this->is_relevant_product($result['title'], $wc_product_title)) {
                            $filtered_results[] = $result;
                            $filtering_stats['ebay_relevant']++;
                        }
                    }
                    
                    if (count($ebay_results) > 0 && $filtering_stats['ebay_relevant'] === 0) {
                        $errors['ebay'] = 'Found ' . count($ebay_results) . ' products but none matched your product keywords';
                    }
                } else {
                    $filtered_results = array_merge($filtered_results, $ebay_results);
                    $filtering_stats['ebay_relevant'] = count($ebay_results);
                }
            } else {
                $errors['ebay'] = 'No results found on eBay';
            }
        } else {
            $errors['ebay'] = 'eBay API not configured';
        }

        // Try Amazon API with location
        if ($this->amazon_api) {
            $api_attempts++;
            $amazon_result = $this->amazon_api->search_products($gtin, 10, $country_code);

            if ($amazon_result['success'] && !empty($amazon_result['products'])) {
                $all_results = array_merge($all_results, $amazon_result['products']);
                $successful_apis++;
                $filtering_stats['amazon_total'] = count($amazon_result['products']);
                
                // Filter Amazon results for relevance
                if (!empty($wc_product_title)) {
                    foreach ($amazon_result['products'] as $result) {
                        if ($this->is_relevant_product($result['title'], $wc_product_title)) {
                            $filtered_results[] = $result;
                            $filtering_stats['amazon_relevant']++;
                        }
                    }
                    
                    if (count($amazon_result['products']) > 0 && $filtering_stats['amazon_relevant'] === 0) {
                        $errors['amazon'] = 'Found ' . count($amazon_result['products']) . ' products but none matched your product keywords';
                    }
                } else {
                    $filtered_results = array_merge($filtered_results, $amazon_result['products']);
                    $filtering_stats['amazon_relevant'] = count($amazon_result['products']);
                }
            } elseif (!$amazon_result['success']) {
                $errors['amazon'] = $amazon_result['error'];
            } else {
                $errors['amazon'] = 'No results found on Amazon';
            }
        } else {
            $errors['amazon'] = 'Amazon API not configured';
        }

        // Update total stats
        $filtering_stats['total_found'] = count($all_results);
        $filtering_stats['relevant_found'] = count($filtered_results);

        // Record failed lookup if no relevant results
        if (empty($filtered_results)) {
            $failure_reason = $this->determine_failure_reason($api_attempts, $successful_apis, $filtering_stats, $wc_product_title);
            $this->record_failed_lookup($gtin, $product_id, $errors, $failure_reason);
            return array(
                'success' => false,
                'error' => $failure_reason,
                'filtering_stats' => $filtering_stats,
                'errors' => $errors
            );
        }

        // Track successful lookup
        $this->track_daily_stats('successful_lookups');

        // Sort results by price
        usort($filtered_results, function($a, $b) {
            return $a['price'] <=> $b['price'];
        });

        // Get best deals
        $ebay_best = null;
        $amazon_best = null;
        $overall_best = $filtered_results[0];

        foreach ($filtered_results as $result) {
            if ($result['source'] === 'ebay' && !$ebay_best) {
                $ebay_best = $result;
            }
            if ($result['source'] === 'amazon' && !$amazon_best) {
                $amazon_best = $result;
            }
            if ($ebay_best && $amazon_best) break;
        }

        return array(
            'success' => true,
            'overall_best' => $overall_best,
            'ebay_best' => $ebay_best,
            'amazon_best' => $amazon_best,
            'total_results' => count($filtered_results),
            'ebay_count' => count(array_filter($filtered_results, function($r) { return $r['source'] === 'ebay'; })),
            'amazon_count' => count(array_filter($filtered_results, function($r) { return $r['source'] === 'amazon'; })),
            'filtering_stats' => $filtering_stats,
            'wc_product_title' => $wc_product_title,
            'errors' => $errors,
            'location' => $location
        );
    }

    private function determine_failure_reason($api_attempts, $successful_apis, $filtering_stats, $wc_product_title) {
        if ($api_attempts === 0) {
            return 'No APIs configured';
        } elseif ($successful_apis === 0) {
            return 'All APIs failed to return data';
        } elseif ($filtering_stats['total_found'] > 0) {
            return 'Keyword matching failed - found ' . $filtering_stats['total_found'] . ' products but none matched your product';
        } else {
            return 'No relevant results found';
        }
    }
} 