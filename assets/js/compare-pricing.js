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
        
        // Make AJAX request to get eBay price
        $.ajax({
            url: compare_pricing_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'compare_pricing',
                nonce: compare_pricing_ajax.nonce,
                gtin: gtin,
                product_id: productId,
                product_name: productName
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var cacheIndicator = data.cached ? '<small class="cached-indicator">(cached)</small>' : '';
                    
                    $content.html(
                        '<div class="compare-pricing-result">' +
                        '<div class="ebay-price">$' + parseFloat(data.price).toFixed(2) + ' ' + cacheIndicator + '</div>' +
                        '<a href="' + data.url + '" target="_blank" class="ebay-link">View on eBay</a>' +
                        '</div>'
                    );
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
}); 