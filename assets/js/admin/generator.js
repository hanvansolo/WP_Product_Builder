/**
 * WP Product Builder - Content Generator JavaScript
 */
(function($) {
    'use strict';

    var WPBGenerator = {
        currentStep: 1,
        maxStep: 5,
        selectedType: null,
        selectedNetwork: 'amazon',
        selectedProducts: [],
        generatedContent: null,
        historyId: null,

        /**
         * Initialize generator
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Content type selection
            $(document).on('click', '.wpb-content-type-card', this.selectContentType.bind(this));

            // Network selector
            $(document).on('change', '#wpb-network-select', this.changeNetwork.bind(this));

            // Product search
            $('#wpb-search-btn').on('click', this.searchProducts.bind(this));
            $('#wpb-product-search').on('keypress', function(e) {
                if (e.which === 13) {
                    WPBGenerator.searchProducts();
                }
            });

            // Add ASIN/URL directly
            $('#wpb-add-asin-btn').on('click', this.addAsinDirect.bind(this));

            // Bulk add
            $('#wpb-bulk-add-btn').on('click', this.bulkAddProducts.bind(this));
            $('#wpb-asin-direct').on('keypress', function(e) {
                if (e.which === 13) {
                    WPBGenerator.addAsinDirect();
                }
            });

            // Add product from search results
            $(document).on('click', '.wpb-result-item', this.addProductFromSearch.bind(this));

            // Remove selected product
            $(document).on('click', '.wpb-selected-item-remove', this.removeProduct.bind(this));

            // Navigation
            $('#wpb-next-btn').on('click', this.nextStep.bind(this));
            $('#wpb-prev-btn').on('click', this.prevStep.bind(this));

            // Generate content
            $('#wpb-generate-btn').on('click', this.generateContent.bind(this));

            // Publish actions
            $('#wpb-create-post-btn').on('click', this.createPost.bind(this));
            $('#wpb-copy-content-btn').on('click', this.copyContent.bind(this));
            $('#wpb-regenerate-btn').on('click', this.regenerate.bind(this));
            $('#wpb-woo-import-btn').on('click', this.importToWooCommerce.bind(this));
        },

        /**
         * Select content type
         */
        selectContentType: function(e) {
            var $card = $(e.currentTarget);

            $('.wpb-content-type-card').removeClass('selected');
            $card.addClass('selected');

            this.selectedType = $card.data('type');

            // Update product selection description based on type
            var typeConfig = wpbAdmin.contentTypes[this.selectedType];
            if (typeConfig) {
                var maxProducts = typeConfig.maxProducts;
                var description = '';

                if (maxProducts === 1) {
                    description = 'Select 1 product for your review.';
                } else {
                    description = 'Select up to ' + maxProducts + ' products.';
                }

                $('#wpb-products-description').text(description);
            }
        },

        /**
         * Search products
         */
        searchProducts: function() {
            var query = $('#wpb-product-search').val().trim();

            if (!query) {
                return;
            }

            var $button = $('#wpb-search-btn');
            var $results = $('#wpb-search-results');

            $button.prop('disabled', true).html('<span class="wpb-loading"></span>');

            $.ajax({
                url: wpbAdmin.apiUrl + '/products/search',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpbAdmin.nonce);
                },
                data: { q: query, network: WPBGenerator.selectedNetwork },
                success: function(response) {
                    if (response.success && response.products) {
                        WPBGenerator.displaySearchResults(response.products, response.message, response.search_url);
                    } else {
                        WPBGenerator.displaySearchResults([], response.message, response.search_url);
                    }
                },
                error: function(xhr) {
                    var message = xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'Search failed. Please try again.';
                    WPBAdmin.showNotice(message, 'error', '#wpb-generator-notices');
                },
                complete: function() {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Search');
                }
            });
        },

        /**
         * Display search results
         */
        displaySearchResults: function(products, message, searchUrl) {
            var $results = $('#wpb-search-results');
            var $list = $results.find('.wpb-results-list');

            $list.empty();

            if (products.length === 0) {
                var msg = message || 'No products found. Try a different search term.';
                var html = '<p class="wpb-no-results">' + msg + '</p>';
                if (searchUrl) {
                    html += '<p><a href="' + searchUrl + '" target="_blank" class="button button-primary">' +
                        '<span class="dashicons dashicons-external" style="margin-top:4px;"></span> ' +
                        'Search on Amazon</a> ' +
                        '<small>Find products, then paste the URLs below</small></p>';
                }
                $list.html(html);
            } else {
                products.forEach(function(product) {
                    var idLabel = product.network === 'amazon' ? 'ASIN' : 'ID';
                    var idValue = product.product_id || product.asin || '';
                    var html = '<div class="wpb-result-item" data-product=\'' + JSON.stringify(product).replace(/'/g, "&#39;") + '\'>' +
                        '<img src="' + (product.image_url || '') + '" alt="">' +
                        '<div class="wpb-result-item-info">' +
                        '<div class="wpb-result-item-title">' + WPBGenerator.escapeHtml(product.title) + '</div>' +
                        '<div class="wpb-result-item-price">' + (product.price || 'Price not available') + '</div>' +
                        '<small>' + idLabel + ': ' + WPBGenerator.escapeHtml(idValue) + '</small>' +
                        (product.merchant_name ? ' <small>via ' + WPBGenerator.escapeHtml(product.merchant_name) + '</small>' : '') +
                        '</div>' +
                        '<button type="button" class="button button-small">Add</button>' +
                        '</div>';
                    $list.append(html);
                });
            }

            $results.show();
        },

        /**
         * Add ASIN directly
         */
        /**
         * Extract ASIN from Amazon URL or raw ASIN
         */
        extractAsin: function(input) {
            input = input.trim();

            // Already a plain ASIN
            if (/^[A-Z0-9]{10}$/i.test(input)) {
                return input.toUpperCase();
            }

            // Amazon URL - extract ASIN from /dp/XXXX or /gp/product/XXXX
            var match = input.match(/\/(?:dp|gp\/product)\/([A-Z0-9]{10})/i);
            if (match) {
                return match[1].toUpperCase();
            }

            // Amazon URL with /product/ pattern
            match = input.match(/amazon\.[^\/]+\/.*?\/([A-Z0-9]{10})/i);
            if (match) {
                return match[1].toUpperCase();
            }

            return null;
        },

        addAsinDirect: function() {
            var rawInput = $('#wpb-asin-direct').val().trim();
            var network = this.selectedNetwork;
            var productId = rawInput;

            if (network === 'amazon') {
                productId = WPBGenerator.extractAsin(rawInput);
                if (!productId) {
                    WPBAdmin.showNotice('Please enter a valid Amazon ASIN or product URL.', 'error', '#wpb-generator-notices');
                    return;
                }
            } else {
                if (!productId) {
                    WPBAdmin.showNotice('Please enter a product ID.', 'error', '#wpb-generator-notices');
                    return;
                }
            }

            // Check if already added
            var uniqueKey = network + ':' + productId;
            if (this.selectedProducts.some(function(p) {
                var pKey = (p.network || 'amazon') + ':' + (p.product_id || p.asin);
                return pKey === uniqueKey;
            })) {
                WPBAdmin.showNotice('This product is already selected.', 'warning', '#wpb-generator-notices');
                return;
            }

            // Fetch product info
            var $button = $('#wpb-add-asin-btn');
            $button.prop('disabled', true).text('Loading...');

            var fetchUrl, fetchData;
            if (network === 'amazon') {
                fetchUrl = wpbAdmin.apiUrl + '/products/' + productId;
                fetchData = { network: network };
            } else {
                fetchUrl = wpbAdmin.apiUrl + '/products/get';
                fetchData = { product_id: productId, network: network };
            }

            $.ajax({
                url: fetchUrl,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpbAdmin.nonce);
                },
                data: fetchData,
                success: function(response) {
                    if (response.success && response.product) {
                        WPBGenerator.addProduct(response.product);
                        $('#wpb-asin-direct').val('');
                    } else {
                        WPBAdmin.showNotice('Product not found.', 'error', '#wpb-generator-notices');
                    }
                },
                error: function() {
                    WPBAdmin.showNotice('Failed to fetch product.', 'error', '#wpb-generator-notices');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Add Product');
                }
            });
        },

        /**
         * Add product from search results
         */
        addProductFromSearch: function(e) {
            var $item = $(e.currentTarget);
            var product = $item.data('product');

            if (product) {
                this.addProduct(product);
            }
        },

        /**
         * Add product to selected list
         */
        addProduct: function(product) {
            // Check max products
            var typeConfig = wpbAdmin.contentTypes[this.selectedType];
            var maxProducts = typeConfig ? typeConfig.maxProducts : 10;

            if (this.selectedProducts.length >= maxProducts) {
                WPBAdmin.showNotice('Maximum ' + maxProducts + ' products allowed for this content type.', 'warning', '#wpb-generator-notices');
                return;
            }

            // Check if already added (use product_id + network as unique key)
            var newKey = (product.network || 'amazon') + ':' + (product.product_id || product.asin);
            if (this.selectedProducts.some(function(p) {
                return (p.network || 'amazon') + ':' + (p.product_id || p.asin) === newKey;
            })) {
                WPBAdmin.showNotice('This product is already selected.', 'warning', '#wpb-generator-notices');
                return;
            }

            this.selectedProducts.push(product);
            this.renderSelectedProducts();
        },

        /**
         * Remove product from selection
         */
        removeProduct: function(e) {
            var removeKey = $(e.currentTarget).data('product-key');
            this.selectedProducts = this.selectedProducts.filter(function(p) {
                var key = (p.network || 'amazon') + ':' + (p.product_id || p.asin);
                return key !== removeKey;
            });
            this.renderSelectedProducts();
        },

        /**
         * Change selected network
         */
        changeNetwork: function(e) {
            this.selectedNetwork = $(e.currentTarget).val();

            var placeholder = this.selectedNetwork === 'amazon'
                ? 'Paste Amazon URL or ASIN (e.g., B08N5WRWNW)'
                : 'Enter product ID';
            $('#wpb-asin-direct').attr('placeholder', placeholder);

            // Clear search results when switching networks
            $('#wpb-search-results').find('.wpb-results-list').empty();
            $('#wpb-search-results').hide();
        },

        /**
         * Render selected products
         */
        renderSelectedProducts: function() {
            var $list = $('#wpb-selected-products');
            var $count = $('#wpb-product-count');

            $list.empty();
            $count.text(this.selectedProducts.length);

            this.selectedProducts.forEach(function(product) {
                var idLabel = (product.network || 'amazon') === 'amazon' ? 'ASIN' : 'ID';
                var idValue = product.product_id || product.asin || '';
                var productKey = (product.network || 'amazon') + ':' + idValue;
                var html = '<div class="wpb-selected-item">' +
                    '<img src="' + (product.image_url || '') + '" alt="">' +
                    '<div class="wpb-selected-item-info">' +
                    '<strong>' + WPBGenerator.escapeHtml(product.title) + '</strong>' +
                    '<div>' + (product.price || 'Price not available') + '</div>' +
                    '<small>' + idLabel + ': ' + WPBGenerator.escapeHtml(idValue) + '</small>' +
                    (product.merchant_name ? ' <small>via ' + WPBGenerator.escapeHtml(product.merchant_name) + '</small>' : '') +
                    '</div>' +
                    '<span class="wpb-selected-item-remove dashicons dashicons-no-alt" data-product-key="' + WPBGenerator.escapeHtml(productKey) + '"></span>' +
                    '</div>';
                $list.append(html);
            });
        },

        /**
         * Navigate to next step
         */
        nextStep: function() {
            // Validate current step
            if (!this.validateStep(this.currentStep)) {
                return;
            }

            if (this.currentStep < this.maxStep) {
                this.goToStep(this.currentStep + 1);
            }
        },

        /**
         * Navigate to previous step
         */
        prevStep: function() {
            if (this.currentStep > 1) {
                this.goToStep(this.currentStep - 1);
            }
        },

        /**
         * Go to specific step
         */
        goToStep: function(step) {
            this.currentStep = step;

            // Update step indicators
            $('.wpb-step').removeClass('active completed');
            $('.wpb-step').each(function() {
                var stepNum = $(this).data('step');
                if (stepNum < step) {
                    $(this).addClass('completed');
                } else if (stepNum === step) {
                    $(this).addClass('active');
                }
            });

            // Show/hide content
            $('.wpb-wizard-content').hide();
            $('.wpb-wizard-content[data-step="' + step + '"]').show();

            // Update navigation buttons
            $('#wpb-prev-btn').toggle(step > 1);
            $('#wpb-next-btn').toggle(step < 4);

            // Update summary on step 4
            if (step === 4) {
                this.updateSummary();
            }
        },

        /**
         * Validate current step
         */
        validateStep: function(step) {
            switch (step) {
                case 1:
                    if (!this.selectedType) {
                        WPBAdmin.showNotice('Please select a content type.', 'error', '#wpb-generator-notices');
                        return false;
                    }
                    break;
                case 2:
                    var typeConfig = wpbAdmin.contentTypes[this.selectedType];
                    var minProducts = this.selectedType === 'product_review' ? 1 : 2;

                    if (this.selectedProducts.length < minProducts) {
                        WPBAdmin.showNotice('Please select at least ' + minProducts + ' product(s).', 'error', '#wpb-generator-notices');
                        return false;
                    }
                    break;
            }
            return true;
        },

        /**
         * Update summary on step 4
         */
        updateSummary: function() {
            var typeConfig = wpbAdmin.contentTypes[this.selectedType];

            $('#summary-type').text(typeConfig ? typeConfig.label : this.selectedType);
            $('#summary-products').text(this.selectedProducts.length + ' product(s)');
            $('#summary-title').text($('#wpb-title').val() || '(Auto-generated)');

            // Estimate tokens based on length
            var length = $('#wpb-length').val();
            var tokenEstimate = length === 'short' ? '2,000-3,000' :
                               length === 'medium' ? '4,000-6,000' : '8,000-12,000';
            $('#summary-tokens').text('~' + tokenEstimate);
        },

        /**
         * Generate content
         */
        generateContent: function() {
            var $button = $('#wpb-generate-btn');
            var $progress = $('#wpb-generation-progress');

            // Collect options
            var options = {
                title: $('#wpb-title').val(),
                focus_keywords: $('#wpb-keywords').val().split(',').map(function(k) { return k.trim(); }).filter(Boolean),
                tone: $('#wpb-tone').val(),
                length: $('#wpb-length').val(),
                include_pros_cons: $('#wpb-include-pros-cons').is(':checked'),
                include_faq: $('#wpb-include-faq').is(':checked'),
                include_verdict: $('#wpb-include-verdict').is(':checked'),
                include_buying_guide: $('#wpb-include-buying-guide').is(':checked')
            };

            var data = {
                type: this.selectedType,
                network: this.selectedNetwork,
                products: this.selectedProducts.map(function(p) { return p.product_id || p.asin; }),
                options: options
            };

            $button.hide();
            $progress.show();

            // Animate progress bar
            var $fill = $progress.find('.wpb-progress-fill');
            $fill.css('width', '0%');

            var progress = 0;
            var progressInterval = setInterval(function() {
                progress += Math.random() * 10;
                if (progress > 90) progress = 90;
                $fill.css('width', progress + '%');
            }, 500);

            $.ajax({
                url: wpbAdmin.apiUrl + '/content/generate',
                method: 'POST',
                timeout: 120000,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpbAdmin.nonce);
                    xhr.setRequestHeader('Content-Type', 'application/json');
                },
                data: JSON.stringify(data),
                success: function(response) {
                    clearInterval(progressInterval);
                    $fill.css('width', '100%');

                    if (response.success) {
                        WPBGenerator.generatedContent = response.content;
                        WPBGenerator.historyId = response.history_id;

                        // Display preview
                        $('#wpb-preview-title').text(response.title);
                        $('#wpb-preview-content').html(response.content);

                        // Go to step 5
                        setTimeout(function() {
                            WPBGenerator.goToStep(5);
                        }, 500);
                    } else {
                        WPBAdmin.showNotice(response.message || 'Generation failed.', 'error', '#wpb-generator-notices');
                        $button.show();
                        $progress.hide();
                    }
                },
                error: function(xhr) {
                    clearInterval(progressInterval);
                    var message = xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'Generation failed. Please try again.';
                    WPBAdmin.showNotice(message, 'error', '#wpb-generator-notices');
                    $button.show();
                    $progress.hide();
                }
            });
        },

        /**
         * Create WordPress post
         */
        createPost: function() {
            if (!this.historyId) {
                WPBAdmin.showNotice('No content to publish.', 'error', '#wpb-generator-notices');
                return;
            }

            var $button = $('#wpb-create-post-btn');
            $button.prop('disabled', true).text('Creating post...');

            var data = {
                history_id: this.historyId,
                status: $('#wpb-post-status').val(),
                category: $('#wpb-post-category').val()
            };

            $.ajax({
                url: wpbAdmin.apiUrl + '/content/create-post',
                method: 'POST',
                timeout: 30000,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpbAdmin.nonce);
                    xhr.setRequestHeader('Content-Type', 'application/json');
                },
                data: JSON.stringify(data),
                success: function(response) {
                    if (response.success && response.post_id) {
                        WPBAdmin.showNotice('Post created successfully!', 'success', '#wpb-generator-notices');

                        // Redirect to edit post
                        setTimeout(function() {
                            window.location.href = response.edit_url;
                        }, 1000);
                    } else {
                        WPBAdmin.showNotice(response.message || 'Failed to create post.', 'error', '#wpb-generator-notices');
                    }
                },
                error: function(xhr) {
                    var message = xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'Failed to create post.';
                    WPBAdmin.showNotice(message, 'error', '#wpb-generator-notices');
                },
                complete: function() {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-admin-post"></span> Create Post');
                }
            });
        },

        /**
         * Copy content to clipboard
         */
        copyContent: function() {
            var content = $('#wpb-preview-content').html();

            if (navigator.clipboard) {
                navigator.clipboard.writeText(content).then(function() {
                    WPBAdmin.showNotice('Content copied to clipboard!', 'success', '#wpb-generator-notices');
                });
            } else {
                // Fallback
                var $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(content).select();
                document.execCommand('copy');
                $temp.remove();
                WPBAdmin.showNotice('Content copied to clipboard!', 'success', '#wpb-generator-notices');
            }
        },

        /**
         * Regenerate content
         */
        regenerate: function() {
            this.goToStep(4);
            $('#wpb-generate-btn').show();
            $('#wpb-generation-progress').hide();
        },

        /**
         * Bulk add products from textarea
         */
        bulkAddProducts: function() {
            var text = $('#wpb-bulk-asins').val().trim();
            if (!text) {
                WPBAdmin.showNotice('Please paste URLs or ASINs, one per line.', 'error', '#wpb-generator-notices');
                return;
            }

            var lines = text.split('\n').map(function(l) { return l.trim(); }).filter(Boolean);
            var $button = $('#wpb-bulk-add-btn');
            var $status = $('#wpb-bulk-status');
            var network = this.selectedNetwork;
            var ids = [];

            // Extract ASINs/IDs from each line
            lines.forEach(function(line) {
                if (network === 'amazon') {
                    var asin = WPBGenerator.extractAsin(line);
                    if (asin) ids.push(asin);
                } else {
                    ids.push(line);
                }
            });

            if (ids.length === 0) {
                WPBAdmin.showNotice('No valid product identifiers found.', 'error', '#wpb-generator-notices');
                return;
            }

            // Remove duplicates
            ids = ids.filter(function(id, index) { return ids.indexOf(id) === index; });

            $button.prop('disabled', true);
            $status.html('Fetching ' + ids.length + ' products...');

            // Batch fetch all products
            $.ajax({
                url: wpbAdmin.apiUrl + '/products/batch',
                method: 'POST',
                timeout: 60000,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpbAdmin.nonce);
                    xhr.setRequestHeader('Content-Type', 'application/json');
                },
                data: JSON.stringify({ product_ids: ids, network: network }),
                success: function(response) {
                    if (response.success && response.products) {
                        var added = 0;
                        var products = response.products;

                        // Products may be keyed by ID
                        if (!Array.isArray(products)) {
                            products = Object.values(products);
                        }

                        products.forEach(function(product) {
                            WPBGenerator.addProduct(product);
                            added++;
                        });

                        $status.html('<span style="color:green;">' + added + ' of ' + ids.length + ' products added!</span>');
                        $('#wpb-bulk-asins').val('');

                        setTimeout(function() { $status.html(''); }, 5000);
                    } else {
                        $status.html('<span style="color:red;">Failed to fetch products.</span>');
                    }
                },
                error: function() {
                    $status.html('<span style="color:red;">Error fetching products.</span>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Import selected products to WooCommerce
         */
        importToWooCommerce: function() {
            if (this.selectedProducts.length === 0) {
                WPBAdmin.showNotice('No products to import.', 'error', '#wpb-generator-notices');
                return;
            }

            var $button = $('#wpb-woo-import-btn');
            $button.prop('disabled', true).text('Importing...');

            var ids = this.selectedProducts.map(function(p) { return p.product_id || p.asin; });
            var network = this.selectedNetwork;

            $.ajax({
                url: wpbAdmin.apiUrl + '/import/woocommerce',
                method: 'POST',
                timeout: 120000,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpbAdmin.nonce);
                    xhr.setRequestHeader('Content-Type', 'application/json');
                },
                data: JSON.stringify({
                    product_ids: ids,
                    network: network
                }),
                success: function(response) {
                    if (response.success) {
                        var msg = response.imported + ' product(s) imported to WooCommerce!';
                        if (response.failed > 0) {
                            msg += ' (' + response.failed + ' failed)';
                        }
                        WPBAdmin.showNotice(msg, 'success', '#wpb-generator-notices');
                    } else {
                        WPBAdmin.showNotice(response.message || 'Import failed.', 'error', '#wpb-generator-notices');
                    }
                },
                error: function(xhr) {
                    var message = xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'WooCommerce import failed.';
                    WPBAdmin.showNotice(message, 'error', '#wpb-generator-notices');
                },
                complete: function() {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-cart"></span> Push to WooCommerce');
                }
            });
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    $(document).ready(function() {
        WPBGenerator.init();
    });

})(jQuery);
