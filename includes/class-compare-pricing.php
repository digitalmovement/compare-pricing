<?php

class Compare_Pricing {
    
    private $ebay_api;
    private $amazon_api;
    
    public function __construct() {
        $this->ebay_api = new Compare_Pricing_eBay_API();
        $this->amazon_api = new Compare_Pricing_Amazon_API();
        
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('compare_pricing', array($this, 'shortcode_handler'));
        add_action('wp_ajax_compare_pricing', array($this, 'handle_ajax_compare'));
        add_action('wp_ajax_nopriv_compare_pricing', array($this, 'handle_ajax_compare'));
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
        // Enable error logging for debugging
        error_log('Compare Pricing AJAX called');
        
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'compare_pricing_nonce')) {
                error_log('Compare Pricing: Nonce verification failed');
                wp_send_json_error('Security check failed');
                return;
            }

            // Get GTIN from request
            $gtin = isset($_POST['gtin']) ? sanitize_text_field($_POST['gtin']) : '';
            $product_id = isset($_POST['product_id']) ? sanitize_text_field($_POST['product_id']) : '';

            error_log('Compare Pricing: GTIN = ' . $gtin . ', Product ID = ' . $product_id);

            if (empty($gtin)) {
                error_log('Compare Pricing: GTIN is empty');
                wp_send_json_error('GTIN is required');
                return;
            }

            // Check if API classes exist
            if (!$this->ebay_api || !$this->amazon_api) {
                error_log('Compare Pricing: API classes not initialized');
                wp_send_json_error('API services not available');
                return;
            }

            $all_results = array();
            $errors = array();

            // Get eBay results using GTIN
            error_log('Compare Pricing: Calling eBay API with GTIN: ' . $gtin);
            $ebay_results = $this->ebay_api->search_products($gtin, 5);
            
            if (is_wp_error($ebay_results)) {
                error_log('Compare Pricing: eBay API Error - ' . $ebay_results->get_error_message());
                $errors['ebay'] = $ebay_results->get_error_message();
            } elseif (!empty($ebay_results)) {
                $all_results = array_merge($all_results, $ebay_results);
                error_log('Compare Pricing: Found ' . count($ebay_results) . ' eBay results');
            }

            // Get Amazon results using GTIN
            error_log('Compare Pricing: Calling Amazon API with GTIN: ' . $gtin);
            $amazon_results = $this->amazon_api->search_products($gtin, 5);
            
            if (is_wp_error($amazon_results)) {
                error_log('Compare Pricing: Amazon API Error - ' . $amazon_results->get_error_message());
                $errors['amazon'] = $amazon_results->get_error_message();
            } elseif (!empty($amazon_results)) {
                $all_results = array_merge($all_results, $amazon_results);
                error_log('Compare Pricing: Found ' . count($amazon_results) . ' Amazon results');
            }

            // If no results from either platform
            if (empty($all_results)) {
                $error_message = 'No results found';
                if (!empty($errors)) {
                    $error_message .= '. Errors: ' . implode(', ', $errors);
                }
                error_log('Compare Pricing: ' . $error_message);
                wp_send_json_error($error_message);
                return;
            }

            // Sort all results by price (lowest first)
            usort($all_results, function($a, $b) {
                return $a['price'] <=> $b['price'];
            });

            // Get the best deals from each platform
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

            error_log('Compare Pricing: Success - Best price: $' . $overall_best['price'] . ' from ' . $overall_best['source']);
            
            // Send success response with comparison data
            wp_send_json_success(array(
                'overall_best' => $overall_best,
                'ebay_best' => $ebay_best,
                'amazon_best' => $amazon_best,
                'total_results' => count($all_results),
                'ebay_count' => count(array_filter($all_results, function($r) { return $r['source'] === 'ebay'; })),
                'amazon_count' => count(array_filter($all_results, function($r) { return $r['source'] === 'amazon'; })),
                'errors' => $errors,
                'cached' => false
            ));
            
        } catch (Exception $e) {
            error_log('Compare Pricing Exception: ' . $e->getMessage());
            wp_send_json_error('An error occurred: ' . $e->getMessage());
        } catch (Error $e) {
            error_log('Compare Pricing Fatal Error: ' . $e->getMessage());
            wp_send_json_error('A fatal error occurred: ' . $e->getMessage());
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
} 