jQuery(function ($) {
    var $form   = $('#kiss-cos-search-form');
    var $input  = $('#kiss-cos-search-input');
    var $status = $('#kiss-cos-search-status');
    var $results = $('#kiss-cos-results');
    var $searchTime = $('#kiss-cos-search-time');

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
                '<td><a href="' + escapeHtml(order.view_url) + '" target="_blank">' + escapeHtml(order.number || order.id) + '</a></td>' +
                '<td><span class="kiss-status-pill">' + escapeHtml(order.status_label) + '</span></td>' +
                '<td>' + order.total + '</td>' +
                '<td>' + escapeHtml(order.date) + '</td>' +
                '<td>' + escapeHtml(order.payment || '') + '</td>' +
                '<td>' + escapeHtml(order.shipping || '') + '</td>' +
                '<td><a href="' + escapeHtml(order.view_url) + '" class="button button-small" target="_blank">View</a></td>' +
                '</tr>';
        });

        html += '</tbody></table>';
        return html;
    }

    /**
     * Render a single order match result.
     */
    function renderOrderMatch(order) {
        var html = '<div class="kiss-cos-order-match">';
        html += '<div class="kiss-cos-order-match-header">';
        html += '<h2>Order Found: #' + escapeHtml(order.order_number) + '</h2>';
        html += '</div>';
        html += '<div class="kiss-cos-order-match-details">';
        html += '<table class="kiss-cos-order-details-table">';
        html += '<tr><th>Order Number</th><td>' + escapeHtml(order.order_number) + '</td></tr>';
        html += '<tr><th>Status</th><td><span class="kiss-status-pill">' + escapeHtml(order.status_label) + '</span></td></tr>';
        html += '<tr><th>Total</th><td>' + escapeHtml(order.total_display) + '</td></tr>';
        html += '<tr><th>Date</th><td>' + escapeHtml(order.date_display) + '</td></tr>';
        html += '<tr><th>Customer</th><td>' + escapeHtml(order.customer.name) + ' &lt;' + escapeHtml(order.customer.email) + '&gt;</td></tr>';
        html += '</table>';
        html += '<div class="kiss-cos-order-match-actions">';
        html += '<a href="' + escapeHtml(order.view_url) + '" class="button button-primary" target="_blank">View Order</a>';
        html += '</div>';
        html += '</div>';
        html += '</div>';
        return html;
    }

    function renderResults(data) {
        var customers = data.customers || [];
        var guestOrders = data.guest_orders || [];
        var orders = data.orders || [];

        if (!customers.length && !guestOrders.length && !orders.length) {
            $results.html('<p><strong>' + (KISSCOS.i18n.no_results || 'No matching customers found.') + '</strong></p>');
            return;
        }

        var html = '';

        // Show order matches first (if any)
        if (orders.length) {
            orders.forEach(function(order) {
                html += renderOrderMatch(order);
            });
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
                html += '<a href="' + escapeHtml(cust.edit_url) + '" class="button button-secondary button-small" target="_blank">View user</a>';
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
                q: q
            }
        }).done(function (resp) {
            if (!resp || !resp.success) {
                var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Something went wrong.';
                $results.html('<p><strong>' + msg + '</strong></p>');
                return;
            }

            // Log debug data to console if present
            if (resp.data.debug) {
                console.group('KISS Search Debug');
                console.log('Search time:', resp.data.search_time_ms + 'ms');
                console.log('Traces:', resp.data.debug.traces);
                console.log('Memory peak:', resp.data.debug.memory_peak_mb + 'MB');
                console.groupEnd();
            }

            // Handle direct order redirect when searching for an order number.
            if (resp.data.should_redirect_to_order && resp.data.redirect_url) {
                console.log('🔄 KISS: Redirecting to order...', {
                    redirect_url: resp.data.redirect_url,
                    should_redirect: resp.data.should_redirect_to_order,
                    orders: resp.data.orders
                });

                // TEMPORARY DEBUG: Show redirect URL instead of redirecting
                var debugHtml = '<div class="notice notice-warning" style="padding: 20px; margin: 20px 0; border-left: 4px solid #ffb900;">';
                debugHtml += '<h3 style="margin-top: 0;">🔍 DEBUG MODE: Redirect Intercepted</h3>';
                debugHtml += '<p><strong>The plugin wants to redirect you to:</strong></p>';
                debugHtml += '<p style="background: #f0f0f0; padding: 10px; font-family: monospace; word-break: break-all;">' + resp.data.redirect_url + '</p>';
                debugHtml += '<p><strong>Test this URL:</strong></p>';
                debugHtml += '<p><a href="' + resp.data.redirect_url + '" class="button button-primary button-large" target="_blank">Open in New Tab →</a></p>';
                debugHtml += '<p><a href="' + resp.data.redirect_url + '" class="button button-secondary button-large">Navigate to URL →</a></p>';
                debugHtml += '<hr style="margin: 15px 0;">';
                debugHtml += '<details><summary><strong>Full Response Data</strong></summary>';
                debugHtml += '<pre style="background: #f9f9f9; padding: 10px; overflow: auto; max-height: 300px;">' + JSON.stringify(resp.data, null, 2) + '</pre>';
                debugHtml += '</details>';
                debugHtml += '<p style="margin-top: 15px; font-size: 12px; color: #666;"><em>To re-enable auto-redirect, comment out the debug code in kiss-woo-admin.js</em></p>';
                debugHtml += '</div>';

                $results.html(debugHtml);

                // UNCOMMENT THIS LINE TO RE-ENABLE AUTO-REDIRECT:
                // window.location.href = resp.data.redirect_url;
                return;
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
        }).fail(function () {
            $results.html('<p><strong>Request failed. Please try again.</strong></p>');
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
    if (initialQ.length >= 2) {
        $input.val(initialQ);
        $form.trigger('submit');
    }
});
