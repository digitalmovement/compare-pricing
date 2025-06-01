jQuery(document).ready(function($) {
    let userLocation = null;
    
    // Initialize compare pricing widgets
    $('.compare-pricing-container').each(function() {
        var $container = $(this);
        var gtin = $container.data('gtin');
        var productId = $container.data('product-id');
        
        if (gtin) {
            // First detect user location, then load pricing
            detectUserLocation(function(location) {
                userLocation = location;
                loadComparePricing($container, gtin, productId, location);
            });
        }
    });
    
    function detectUserLocation(callback) {
        // Check if we already have location cached
        var cachedLocation = localStorage.getItem('compare_pricing_location');
        var cacheTime = localStorage.getItem('compare_pricing_location_time');
        
        // Cache location for 24 hours
        if (cachedLocation && cacheTime && (Date.now() - parseInt(cacheTime)) < 24 * 60 * 60 * 1000) {
            callback(JSON.parse(cachedLocation));
            return;
        }
        
        // Detect location using freeipapi.com
        $.ajax({
            url: 'https://freeipapi.com/api/json',
            method: 'GET',
            timeout: 5000,
            success: function(response) {
                var location = {
                    country_code: response.countryCode || 'US',
                    country_name: response.countryName || 'United States',
                    detected: true,
                    source: 'freeipapi'
                };
                
                // Cache the location
                localStorage.setItem('compare_pricing_location', JSON.stringify(location));
                localStorage.setItem('compare_pricing_location_time', Date.now().toString());
                
                callback(location);
            },
            error: function() {
                // Fallback to US if location detection fails
                var location = {
                    country_code: 'US',
                    country_name: 'United States',
                    detected: false,
                    source: 'fallback'
                };
                callback(location);
            }
        });
    }
    
    function loadComparePricing($container, gtin, productId, location) {
        var $content = $container.find('.compare-pricing-content');
        
        // Show loading message with location info
        var locationText = location.detected ? 
            'Checking prices for ' + location.country_name + '...' : 
            'Finding you the cheapest price...';
            
        $content.html('<div class="compare-pricing-loading">' + locationText + '</div>');
        
        $.ajax({
            url: comparePricing.ajax_url,
            method: 'POST',
            data: {
                action: 'compare_pricing',
                gtin: gtin,
                product_id: productId,
                location: location,
                nonce: comparePricing.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayResults($content, response.data, location);
                } else {
                    displayError($content, response.data, location);
                }
            },
            error: function() {
                displayError($content, 'Failed to load pricing data', location);
            }
        });
    }
    
    function displayResults($content, data, location) {
        var html = '';
        
        // Location indicator
        if (location.detected) {
            html += '<div class="pricing-location">Prices for ' + location.country_name + '</div>';
        }
        
        if (data.overall_best) {
            html += '<div class="best-price-container">';
            html += '<div class="best-price-header">Best Price Found</div>';
            html += '<div class="best-price-item">';
            html += '<div class="price-amount">$' + data.overall_best.price + '</div>';
            html += '<div class="price-source">from ' + data.overall_best.source + '</div>';
            html += '<div class="price-title">' + data.overall_best.title + '</div>';
            if (data.overall_best.url) {
                html += '<a href="' + data.overall_best.url + '" target="_blank" class="view-deal-btn">View Deal</a>';
            }
            html += '</div>';
            html += '</div>';
            
            // Platform breakdown
            if (data.ebay_best || data.amazon_best) {
                html += '<div class="platform-comparison">';
                html += '<div class="platform-header">Compare Platforms</div>';
                
                if (data.ebay_best) {
                    html += '<div class="platform-item ebay">';
                    html += '<div class="platform-name">eBay</div>';
                    html += '<div class="platform-price">$' + data.ebay_best.price + '</div>';
                    if (data.ebay_best.url) {
                        html += '<a href="' + data.ebay_best.url + '" target="_blank" class="platform-link">View</a>';
                    }
                    html += '</div>';
                }
                
                if (data.amazon_best) {
                    html += '<div class="platform-item amazon">';
                    html += '<div class="platform-name">Amazon</div>';
                    html += '<div class="platform-price">$' + data.amazon_best.price + '</div>';
                    if (data.amazon_best.url) {
                        html += '<a href="' + data.amazon_best.url + '" target="_blank" class="platform-link">View</a>';
                    }
                    html += '</div>';
                }
                
                html += '</div>';
            }
            
            // Results summary
            html += '<div class="results-summary">';
            html += 'Found ' + data.total_results + ' competitive prices';
            if (data.filtering_stats && data.filtering_stats.total_found > data.total_results) {
                html += ' (' + data.filtering_stats.total_found + ' total, ' + data.total_results + ' relevant)';
            }
            html += '</div>';
        }
        
        $content.html(html);
    }
    
    function displayError($content, error, location) {
        var html = '<div class="compare-pricing-error">';
        
        if (location.detected) {
            html += '<div class="pricing-location">Checked prices for ' + location.country_name + '</div>';
        }
        
        html += '<div class="error-message">No competitive prices found</div>';
        html += '<div class="error-details">' + error + '</div>';
        html += '</div>';
        
        $content.html(html);
    }
}); 