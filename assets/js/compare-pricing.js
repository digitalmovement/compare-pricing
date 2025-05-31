jQuery(document).ready(function($) {
    // Handle multiple compare pricing containers on the same page
    $('.compare-pricing-container').each(function() {
        var $container = $(this);
        var gtin = $container.data('gtin');
        var productId = $container.data('product-id');
        var $content = $container.find('.compare-pricing-content');
        
        if (!gtin) {
            return; // Skip if no GTIN
        }
        
        // Show loading state
        $content.html(
            '<div class="compare-pricing-loading">' +
            '<span class="spinner"></span>' +
            '<span class="loading-text">' + comparePricing.loading_text + '</span>' +
            '</div>'
        );
        
        // Make AJAX request to get price comparison
        $.ajax({
            url: comparePricing.ajax_url,
            type: 'POST',
            data: {
                action: 'compare_pricing',
                nonce: comparePricing.nonce,
                gtin: gtin,
                product_id: productId
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var html = '<div class="compare-pricing-result">';
                    
                    // Overall best deal
                    if (data.overall_best) {
                        html += '<div class="best-deal-section">';
                        html += '<h4 class="best-deal-title">🏆 Best Deal Found</h4>';
                        html += '<div class="best-deal-item ' + data.overall_best.source + '">';
                        html += '<div class="deal-platform">' + data.overall_best.source.toUpperCase() + '</div>';
                        html += '<div class="deal-price">$' + parseFloat(data.overall_best.price).toFixed(2) + '</div>';
                        html += '<div class="deal-title">' + truncateTitle(data.overall_best.title, 50) + '</div>';
                        html += '<a href="' + data.overall_best.url + '" target="_blank" class="view-deal-btn">View Deal</a>';
                        html += '</div>';
                        html += '</div>';
                    }
                    
                    // Platform comparison
                    html += '<div class="platform-comparison">';
                    
                    // eBay section
                    if (data.ebay_best) {
                        html += '<div class="platform-section ebay-section">';
                        html += '<div class="platform-header">';
                        html += '<span class="platform-name">eBay</span>';
                        html += '<span class="platform-price">$' + parseFloat(data.ebay_best.price).toFixed(2) + '</span>';
                        html += '</div>';
                        html += '<div class="platform-title">' + truncateTitle(data.ebay_best.title, 40) + '</div>';
                        html += '<a href="' + data.ebay_best.url + '" target="_blank" class="platform-link">View on eBay</a>';
                        html += '</div>';
                    } else {
                        html += '<div class="platform-section ebay-section no-results">';
                        html += '<div class="platform-header">';
                        html += '<span class="platform-name">eBay</span>';
                        html += '<span class="no-results-text">No results</span>';
                        html += '</div>';
                        html += '</div>';
                    }
                    
                    // Amazon section
                    if (data.amazon_best) {
                        html += '<div class="platform-section amazon-section">';
                        html += '<div class="platform-header">';
                        html += '<span class="platform-name">Amazon</span>';
                        html += '<span class="platform-price">$' + parseFloat(data.amazon_best.price).toFixed(2) + '</span>';
                        html += '</div>';
                        html += '<div class="platform-title">' + truncateTitle(data.amazon_best.title, 40) + '</div>';
                        html += '<a href="' + data.amazon_best.url + '" target="_blank" class="platform-link">View on Amazon</a>';
                        html += '</div>';
                    } else {
                        html += '<div class="platform-section amazon-section no-results">';
                        html += '<div class="platform-header">';
                        html += '<span class="platform-name">Amazon</span>';
                        html += '<span class="no-results-text">No results</span>';
                        html += '</div>';
                        html += '</div>';
                    }
                    
                    html += '</div>'; // End platform-comparison
                    
                    // Results summary
                    if (data.total_results > 0) {
                        html += '<div class="results-summary">';
                        html += '<small>Found ' + data.total_results + ' total results';
                        if (data.ebay_count > 0) html += ' (' + data.ebay_count + ' from eBay';
                        if (data.amazon_count > 0) {
                            if (data.ebay_count > 0) html += ', ';
                            html += data.amazon_count + ' from Amazon';
                        }
                        if (data.ebay_count > 0 || data.amazon_count > 0) html += ')';
                        html += '</small>';
                        html += '</div>';
                    }
                    
                    html += '</div>'; // End compare-pricing-result
                    
                    $content.html(html);
                } else {
                    $content.html(
                        '<div class="compare-pricing-error">' +
                        '<p>' + response.data + '</p>' +
                        '</div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', xhr.responseText);
                $content.html(
                    '<div class="compare-pricing-error">' +
                    '<p>Error loading price comparison</p>' +
                    '</div>'
                );
            }
        });
    });
    
    // Helper function to truncate titles
    function truncateTitle(title, maxLength) {
        if (title.length <= maxLength) return title;
        return title.substring(0, maxLength) + '...';
    }
}); 