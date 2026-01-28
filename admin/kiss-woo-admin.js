jQuery(function ($) {
    var $form   = $('#kiss-cos-search-form');
    var $input  = $('#kiss-cos-search-input');
    var $status = $('#kiss-cos-search-status');
    var $results = $('#kiss-cos-results');
    var $searchTime = $('#kiss-cos-search-time');
    var $scopeInputs = $('input[name="kiss-cos-scope"]');
    var $desc = $('.kiss-cos-description');

    function getQueryParam(name) {
        try {
            var params = new URLSearchParams(window.location.search || '');
            return params.get(name);
        } catch (e) {
            return null;
        }
    }

    /**
     * Escape HTML special characters to prevent XSS attacks.
     *
     * @param {string} text - The text to escape
     * @return {string} - Escaped text safe for HTML insertion
     */
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function getScope() {
        var $checked = $scopeInputs.filter(':checked');
        if ($checked.length) {
            return $checked.val();
        }
        return 'users';
    }

    function saveScope(scope) {
        try {
            localStorage.setItem('kiss_woo_search_scope', scope);
        } catch (e) {
            // localStorage not available (private browsing, etc.)
        }
    }

    function loadScope() {
        try {
            return localStorage.getItem('kiss_woo_search_scope') || 'users';
        } catch (e) {
            return 'users';
        }
    }

    function syncScopeUI() {
        var scope = getScope();
        var placeholderUsers = $input.data('placeholder-users') || $input.attr('placeholder') || '';
        var placeholderCoupons = $input.data('placeholder-coupons') || placeholderUsers;
        var descUsers = $desc.data('desc-users') || '';
        var descCoupons = $desc.data('desc-coupons') || '';

        if (scope === 'coupons') {
            if (placeholderCoupons) {
                $input.attr('placeholder', placeholderCoupons);
            }
            if (descCoupons) {
                $desc.text(descCoupons);
            }
        } else {
            if (placeholderUsers) {
                $input.attr('placeholder', placeholderUsers);
            }
            if (descUsers) {
                $desc.text(descUsers);
            }
        }
    }

    // Save scope to localStorage when changed
    $scopeInputs.on('change', function() {
        var scope = getScope();
        saveScope(scope);
        syncScopeUI();
    });

    // Restore saved scope on page load (unless URL param overrides)
    var initialScope = (getQueryParam('scope') || '').trim();
    if (initialScope) {
        // URL parameter takes precedence
        if (initialScope === 'coupons') {
            $scopeInputs.filter('[value="coupons"]').prop('checked', true);
        } else {
            $scopeInputs.filter('[value="users"]').prop('checked', true);
        }
    } else {
        // Restore from localStorage
        var savedScope = loadScope();
        if (savedScope === 'coupons') {
            $scopeInputs.filter('[value="coupons"]').prop('checked', true);
        } else {
            $scopeInputs.filter('[value="users"]').prop('checked', true);
        }
    }
    syncScopeUI();

    function renderOrdersTable(orders) {
        if (!orders || !orders.length) {
            return '<p><em>No orders found.</em></p>';
        }

        var html = '<table class="kiss-cos-orders-table">';
        html += '<thead><tr>' +
            '<th>#</th>' +
            '<th>Status</th>' +
            '<th>Total</th>' +
            '<th>Date</th>' +
            '<th>Payment</th>' +
            '<th>Shipping</th>' +
            '<th></th>' +
            '</tr></thead><tbody>';

        orders.forEach(function (order) {
            html += '<tr>' +
                '<td><a href="' + escapeHtml(order.view_url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(order.number || order.id) + '</a></td>' +
                '<td><span class="kiss-status-pill">' + escapeHtml(order.status_label) + '</span></td>' +
                '<td>' + order.total + '</td>' +
                '<td>' + escapeHtml(order.date) + '</td>' +
                '<td>' + escapeHtml(order.payment || '') + '</td>' +
                '<td>' + escapeHtml(order.shipping || '') + '</td>' +
                '<td><a href="' + escapeHtml(order.view_url) + '" class="button button-small" target="_blank" rel="noopener noreferrer">View</a></td>' +
                '</tr>';
        });

        html += '</tbody></table>';
        return html;
    }

    function renderCouponsTable(coupons) {
        if (!coupons || !coupons.length) {
            return '<p><em>' + (KISSCOS.i18n.no_coupons || 'No matching coupons found.') + '</em></p>';
        }

        function formatUsage(coupon) {
            var usage = (typeof coupon.usage_count !== 'undefined') ? coupon.usage_count : 0;
            var limit = (typeof coupon.usage_limit !== 'undefined') ? coupon.usage_limit : 0;
            var usageText = usage;
            usageText += (limit && limit > 0) ? ' / ' + limit : ' / unlimited';
            if (coupon.usage_limit_per_user && coupon.usage_limit_per_user > 0) {
                usageText += ' (per user ' + coupon.usage_limit_per_user + ')';
            }
            return usageText;
        }

        function formatSource(coupon) {
            if (!coupon.source_flags || !coupon.source_flags.length) {
                return '';
            }
            return coupon.source_flags.map(function(flag) {
                return String(flag);
            }).join(', ');
        }

        var html = '<table class="kiss-cos-coupons-table">';
        html += '<thead><tr>' +
            '<th>Code</th>' +
            '<th>Title</th>' +
            '<th>Type</th>' +
            '<th>Amount</th>' +
            '<th>Expiry</th>' +
            '<th>Usage</th>' +
            '<th>Status</th>' +
            '<th>Source</th>' +
            '<th></th>' +
            '</tr></thead><tbody>';

        coupons.forEach(function (coupon) {
            var viewUrl = escapeHtml(coupon.view_url || '');
            var code = escapeHtml(coupon.code || '');
            var title = escapeHtml(coupon.title || '');
            var type = escapeHtml(coupon.discount_type || '');
            var amount = escapeHtml(coupon.amount_display || coupon.amount || '');
            var expiry = escapeHtml(coupon.expiry_display || coupon.expiry_date || '');
            var usage = escapeHtml(formatUsage(coupon));
            var status = escapeHtml(coupon.status || '');
            var source = escapeHtml(formatSource(coupon));

            html += '<tr>' +
                '<td><a href="' + viewUrl + '" target="_blank" rel="noopener noreferrer">' + code + '</a></td>' +
                '<td>' + title + '</td>' +
                '<td>' + type + '</td>' +
                '<td>' + amount + '</td>' +
                '<td>' + expiry + '</td>' +
                '<td>' + usage + '</td>' +
                '<td>' + status + '</td>' +
                '<td>' + source + '</td>' +
                '<td><a href="' + viewUrl + '" class="button button-small" target="_blank" rel="noopener noreferrer">View</a></td>' +
                '</tr>';
        });

        html += '</tbody></table>';
        return html;
    }

    function renderResults(data) {
        var customers = data.customers || [];
        var guestOrders = data.guest_orders || [];
        var orders = data.orders || [];
        var coupons = data.coupons || [];
        var scope = data.search_scope || getScope();

        var html = '';

        // DEBUG: Show debug information if available
        if (data.debug) {
            html += '<div class="kiss-cos-debug" style="background: #fff3cd; border: 2px solid #ffc107; padding: 15px; margin-bottom: 20px; font-family: monospace; font-size: 12px;">';
            html += '<h3 style="margin-top: 0; color: #856404;">🔍 Order Search Debug Info</h3>';
            html += '<pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word;">';
            html += JSON.stringify(data.debug, null, 2);
            html += '</pre>';
            html += '</div>';
        }

        if (scope === 'coupons') {
            if (!coupons.length) {
                $results.html(html + '<p><strong>' + (KISSCOS.i18n.no_coupons || 'No matching coupons found.') + '</strong></p>');
                return;
            }

            html += '<div class="kiss-cos-coupons">';
            html += '<h2>' + (KISSCOS.i18n.coupon_title || 'Matching Coupons') + '</h2>';
            html += renderCouponsTable(coupons);
            html += '</div>';
            $results.html(html);
            return;
        }

        if (!customers.length && !guestOrders.length && !orders.length) {
            $results.html(html + '<p><strong>' + (KISSCOS.i18n.no_results || 'No matching customers found.') + '</strong></p>');
            return;
        }

        // Render matching orders first (direct order search results)
        if (orders.length) {
            html += '<div class="kiss-cos-matching-orders">';
            html += '<h2>' + (KISSCOS.i18n.matching_orders || 'Matching Orders') + '</h2>';
            html += renderOrdersTable(orders);
            html += '</div>';
        }

        customers.forEach(function (cust) {
            html += '<div class="kiss-cos-customer">';
            html += '<div class="kiss-cos-customer-header">';
            html += '<div class="kiss-cos-customer-name">' +
                escapeHtml(cust.name || '(No name)') +
                ' &lt;' + escapeHtml(cust.email || '') + '&gt;' +
                '</div>';
            html += '<div class="kiss-cos-customer-meta">' +
                'User ID: ' + escapeHtml(cust.id) +
                (cust.registered_h ? ' · Since: ' + escapeHtml(cust.registered_h) : '') +
                ' · Orders: ' + escapeHtml(cust.orders) +
                '</div>';
            html += '</div>';

            html += '<div class="kiss-cos-customer-actions">';
            if (cust.edit_url) {
                html += '<a href="' + escapeHtml(cust.edit_url) + '" class="button button-secondary button-small" target="_blank" rel="noopener noreferrer">View user</a>';
            }
            html += '</div>';

            if (cust.orders_list && cust.orders_list.length) {
                html += '<div class="kiss-cos-customer-orders">';
                html += renderOrdersTable(cust.orders_list);
                html += '</div>';
            } else {
                html += '<p><em>No orders found for this customer.</em></p>';
                }
            html += '</div>';
        });

        if (guestOrders.length) {
            html += '<div class="kiss-cos-guest-orders">';
            html += '<h2>' + (KISSCOS.i18n.guest_title || 'Guest Orders (no account)') + '</h2>';
            html += renderOrdersTable(guestOrders);
            html += '</div>';
        }

        $results.html(html);
    }

    $form.on('submit', function (e) {
        e.preventDefault();

        var q = $input.val().trim();
        var scope = getScope();

        if (!q.length) {
            return;
        }

        $status.text(KISSCOS.i18n.searching || 'Searching...');
        $results.empty();
        $searchTime.text('');

        var startTime = performance.now();

        $.ajax({
            url: KISSCOS.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'kiss_woo_customer_search',
                nonce: KISSCOS.nonce,
                q: q,
                scope: scope
            }
        }).done(function (resp) {
            // Debug: Log the full response
            console.log('AJAX Response:', resp);

            if (!resp || !resp.success) {
                var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Something went wrong.';
                var debugHtml = '<div style="background: #fff3cd; border: 2px solid #ffc107; padding: 15px; margin-bottom: 20px;">';
                debugHtml += '<h3 style="margin-top: 0; color: #856404;">⚠️ Request Not Successful</h3>';
                debugHtml += '<p><strong>Message:</strong> ' + msg + '</p>';
                debugHtml += '<p><strong>Full Response:</strong></p>';
                debugHtml += '<pre style="background: #fff; padding: 10px; overflow: auto; max-height: 300px;">' +
                             JSON.stringify(resp, null, 2) + '</pre>';
                debugHtml += '</div>';
                $results.html(debugHtml);
                return;
            }

            // Auto-redirect if backend determined this is a direct match (order or coupon)
            if (resp.data.should_redirect_to_order && resp.data.redirect_url) {
                if (typeof KISSCOS !== 'undefined' && KISSCOS.debug) {
                    console.log('🔄 KISS: Auto-redirecting to:', resp.data.redirect_url);
                }
                window.location.href = resp.data.redirect_url;
                return; // Skip rendering, we're redirecting
            }

            renderResults(resp.data);

            // Display both total round-trip time and database search time with percentage
            var totalSeconds = ((performance.now() - startTime) / 1000).toFixed(2);
            var dbSeconds = (resp.data && typeof resp.data.search_time !== 'undefined') ? resp.data.search_time : null;

            if (dbSeconds !== null && totalSeconds > 0) {
                var dbPercent = Math.round((dbSeconds / totalSeconds) * 100);
                $searchTime.text('Search completed in ' + totalSeconds + 's (database: ' + dbSeconds + 's / ' + dbPercent + '%)');
            } else {
                $searchTime.text('Search completed in ' + totalSeconds + ' seconds');
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            var errorHtml = '<div style="background: #f8d7da; border: 2px solid #f5c6cb; padding: 15px; margin-bottom: 20px;">';
            errorHtml += '<h3 style="margin-top: 0; color: #721c24;">❌ AJAX Request Failed</h3>';
            errorHtml += '<p><strong>Status:</strong> ' + textStatus + '</p>';
            errorHtml += '<p><strong>Error:</strong> ' + errorThrown + '</p>';
            errorHtml += '<p><strong>HTTP Status:</strong> ' + jqXHR.status + '</p>';
            errorHtml += '<p><strong>Response Text:</strong></p>';
            errorHtml += '<pre style="background: #fff; padding: 10px; overflow: auto; max-height: 300px;">' +
                         (jqXHR.responseText || 'No response text') + '</pre>';
            errorHtml += '</div>';
            $results.html(errorHtml);
        }).always(function () {
            $status.text('');
        });
    });

    // Also trigger on Enter key in input (form submit handles this anyway).
    $input.on('keypress', function (e) {
        if (e.which === 13) {
            $form.trigger('submit');
        }
    });

    // If we landed here from the floating toolbar, auto-run the search.
    var initialQ = (getQueryParam('q') || '').trim();
    // Note: initialScope is already handled above in the scope restoration logic
    if (initialQ.length >= 2) {
        $input.val(initialQ);
        $form.trigger('submit');
    }
});
