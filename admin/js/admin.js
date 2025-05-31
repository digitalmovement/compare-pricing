jQuery(document).ready(function($) {
    
    // Test eBay API
    $('#test-ebay-api').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $results = $('#ebay-test-results');
        
        $button.prop('disabled', true).text('Testing...');
        $results.html('<div class="testing">🔄 Testing eBay API connection...</div>');
        
        $.ajax({
            url: comparePricingAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'test_ebay_api',
                nonce: comparePricingAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayTestResults($results, response.data.debug, true);
                } else {
                    displayTestResults($results, response.data.debug, false);
                }
            },
            error: function() {
                $results.html('<div class="error">❌ Failed to connect to server</div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test eBay API');
            }
        });
    });
    
    // Test Amazon API
    $('#test-amazon-api').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $results = $('#amazon-test-results');
        
        $button.prop('disabled', true).text('Testing...');
        $results.html('<div class="testing">🔄 Testing Amazon API connection...</div>');
        
        $.ajax({
            url: comparePricingAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'test_amazon_api',
                nonce: comparePricingAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayTestResults($results, response.data.debug, true);
                } else {
                    displayTestResults($results, response.data.debug, false);
                }
            },
            error: function() {
                $results.html('<div class="error">❌ Failed to connect to server</div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test Amazon API');
            }
        });
    });
    
    // Test GTIN Lookup
    $('#test-gtin-lookup').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $results = $('#gtin-test-results');
        var gtin = $('#test-gtin').val().trim();
        
        if (!gtin) {
            alert('Please enter a GTIN to test');
            return;
        }
        
        $button.prop('disabled', true).text('Testing...');
        $results.html('<div class="testing">🔄 Testing GTIN lookup for: ' + gtin + '</div>');
        
        $.ajax({
            url: comparePricingAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'test_gtin_lookup',
                nonce: comparePricingAdmin.nonce,
                gtin: gtin
            },
            success: function(response) {
                if (response.success) {
                    displayGtinResults($results, response.data);
                } else {
                    $results.html('<div class="error">❌ ' + response.data + '</div>');
                }
            },
            error: function() {
                $results.html('<div class="error">❌ Failed to connect to server</div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test GTIN Lookup');
            }
        });
    });
    
    // Display test results
    function displayTestResults($container, debugInfo, success) {
        var html = '<div class="test-results ' + (success ? 'success' : 'error') + '">';
        
        if (debugInfo && typeof debugInfo === 'object') {
            for (var step in debugInfo) {
                var stepData = debugInfo[step];
                var statusIcon = getStatusIcon(stepData.status);
                
                html += '<div class="test-step ' + stepData.status + '">';
                html += '<h4>' + statusIcon + ' ' + stepData.title + '</h4>';
                
                if (stepData.message) {
                    html += '<p>' + stepData.message + '</p>';
                }
                
                if (stepData.details) {
                    html += '<ul>';
                    for (var key in stepData.details) {
                        html += '<li><strong>' + key + ':</strong> ' + stepData.details[key] + '</li>';
                    }
                    html += '</ul>';
                }
                
                if (stepData.warnings && stepData.warnings.length > 0) {
                    html += '<div class="warnings"><strong>Warnings:</strong><ul>';
                    stepData.warnings.forEach(function(warning) {
                        html += '<li>' + warning + '</li>';
                    });
                    html += '</ul></div>';
                }
                
                if (stepData.help) {
                    html += '<div class="help"><strong>Help:</strong> ' + stepData.help + '</div>';
                }
                
                if (stepData.note) {
                    html += '<div class="note"><strong>Note:</strong> ' + stepData.note + '</div>';
                }
                
                html += '</div>';
            }
        } else {
            html += '<p>' + (success ? '✅ Test completed successfully' : '❌ Test failed') + '</p>';
        }
        
        html += '</div>';
        $container.html(html);
    }
    
    // Display GTIN test results
    function displayGtinResults($container, data) {
        var html = '<div class="gtin-results success">';
        html += '<h4>✅ GTIN Lookup Results</h4>';
        
        // Overall best deal
        if (data.overall_best) {
            html += '<div class="best-deal">';
            html += '<h5>🏆 Best Deal Found</h5>';
            html += '<p><strong>Platform:</strong> ' + data.overall_best.source.toUpperCase() + '</p>';
            html += '<p><strong>Price:</strong> $' + parseFloat(data.overall_best.price).toFixed(2) + '</p>';
            html += '<p><strong>Title:</strong> ' + data.overall_best.title + '</p>';
            html += '<p><strong>URL:</strong> <a href="' + data.overall_best.url + '" target="_blank">View Product</a></p>';
            html += '</div>';
        }
        
        // Platform breakdown
        html += '<div class="platform-breakdown">';
        html += '<h5>Platform Results</h5>';
        
        if (data.ebay_best) {
            html += '<div class="platform-result ebay">';
            html += '<strong>eBay:</strong> $' + parseFloat(data.ebay_best.price).toFixed(2);
            html += ' - <a href="' + data.ebay_best.url + '" target="_blank">View</a>';
            html += '</div>';
        } else {
            html += '<div class="platform-result ebay no-results">eBay: No results found</div>';
        }
        
        if (data.amazon_best) {
            html += '<div class="platform-result amazon">';
            html += '<strong>Amazon:</strong> $' + parseFloat(data.amazon_best.price).toFixed(2);
            html += ' - <a href="' + data.amazon_best.url + '" target="_blank">View</a>';
            html += '</div>';
        } else {
            html += '<div class="platform-result amazon no-results">Amazon: No results found</div>';
        }
        
        html += '</div>';
        
        // Summary
        html += '<div class="summary">';
        html += '<p><strong>Total Results:</strong> ' + data.total_results;
        html += ' (' + data.ebay_count + ' from eBay, ' + data.amazon_count + ' from Amazon)</p>';
        
        if (data.errors && Object.keys(data.errors).length > 0) {
            html += '<div class="errors"><strong>Errors:</strong><ul>';
            for (var platform in data.errors) {
                html += '<li>' + platform + ': ' + data.errors[platform] + '</li>';
            }
            html += '</ul></div>';
        }
        
        html += '</div>';
        html += '</div>';
        
        $container.html(html);
    }
    
    // Get status icon
    function getStatusIcon(status) {
        switch (status) {
            case 'success': return '✅';
            case 'error': return '❌';
            case 'warning': return '⚠️';
            case 'checking': return '🔄';
            default: return '📋';
        }
    }
}); 