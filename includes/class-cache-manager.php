<?php

class Compare_Pricing_Cache_Manager {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'compare_pricing_cache';
    }
    
    public function get_cached_price($gtin) {
        global $wpdb;
        
        $ttl = get_option('compare_pricing_cache_ttl', 3600);
        $expiry_time = date('Y-m-d H:i:s', time() - $ttl);
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE gtin = %s AND cached_at > %s",
            $gtin,
            $expiry_time
        ), ARRAY_A);
        
        return $result;
    }
    
    public function cache_price($gtin, $price, $url) {
        global $wpdb;
        
        $wpdb->replace(
            $this->table_name,
            array(
                'gtin' => $gtin,
                'ebay_price' => $price,
                'ebay_url' => $url,
                'cached_at' => current_time('mysql')
            ),
            array('%s', '%f', '%s', '%s')
        );
    }
    
    public function clear_cache() {
        global $wpdb;
        return $wpdb->query("TRUNCATE TABLE {$this->table_name}");
    }
    
    public function get_cache_stats() {
        global $wpdb;
        
        $total_cached = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        $ttl = get_option('compare_pricing_cache_ttl', 3600);
        $expiry_time = date('Y-m-d H:i:s', time() - $ttl);
        
        $valid_cached = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE cached_at > %s",
            $expiry_time
        ));
        
        return array(
            'total' => intval($total_cached),
            'valid' => intval($valid_cached),
            'expired' => intval($total_cached) - intval($valid_cached)
        );
    }
    
    public function cleanup_expired() {
        global $wpdb;
        
        $ttl = get_option('compare_pricing_cache_ttl', 3600);
        $expiry_time = date('Y-m-d H:i:s', time() - $ttl);
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE cached_at <= %s",
            $expiry_time
        ));
    }
} 