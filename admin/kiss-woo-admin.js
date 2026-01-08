jQuery(function ($) {
    var $form   = $('#kiss-cos-search-form');
    var $input  = $('#kiss-cos-search-input');
    var $status = $('#kiss-cos-search-status');
    var $results = $('#kiss-cos-results');
    var $searchTime = $('#kiss-cos-search-time');

    var $debugPanel = $('#kiss-cos-debug-panel');
    var $debugToggle = $('#kiss-cos-debug-toggle');
    var $debugBody = $('#kiss-cos-debug-body');
    var $debugContent = $('#kiss-cos-debug-content');

    function hideDebugPanel() {
        if (!$debugPanel.length) return;
        $debugPanel.attr('hidden', true);
        $debugBody.attr('hidden', true);
        $debugToggle.attr('aria-expanded', 'false');
        $debugToggle.find('.kiss-cos-debug__caret').text('▸');
        $debugContent.text('');
    }

    function showDebugPanel(debugObj) {
        if (!$debugPanel.length) return;

        // Default collapsed to avoid distracting non-technical users.
        $debugPanel.removeAttr('hidden');
        $debugBody.attr('hidden', true);
        $debugToggle.attr('aria-expanded', 'false');
        $debugToggle.find('.kiss-cos-debug__caret').text('▸');

        try {
            $debugContent.text(JSON.stringify(debugObj, null, 2));
        } catch (e) {
            $debugContent.text(String(debugObj));
        }
    }

    if ($debugToggle.length) {
        $debugToggle.on('click', function () {
            var expanded = $debugToggle.attr('aria-expanded') === 'true';
            if (expanded) {
                $debugToggle.attr('aria-expanded', 'false');
                $debugBody.attr('hidden', true);
                $debugToggle.find('.kiss-cos-debug__caret').text('▸');
            } else {
                $debugToggle.attr('aria-expanded', 'true');
                $debugBody.removeAttr('hidden');
                $debugToggle.find('.kiss-cos-debug__caret').text('▾');
            }
        });
    }

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

    function renderResults(data) {
        var customers = data.customers || [];
        var guestOrders = data.guest_orders || [];
        var orders = data.orders || [];

        var html = '';

        // If the server says this was an order-like query but no order was found,
        // surface that clearly in the main results column.
        try {
            if (data && data.debug && data.debug.is_order_like && (!orders || !orders.length)) {
                html += '<div class="notice notice-warning" style="padding:10px 12px; margin: 0 0 12px 0;">';
                html += '<p style="margin:0;"><strong>Order not found.</strong> No exact match for <code>' + escapeHtml(data.debug.term || '') + '</code>. The search is super fast because it relies on an exact amount of digits. Please make sure you\'re not missing a digit (or more) when pasting it.</p>';
                html += '</div>';
            }
        } catch (e) {
            // ignore
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

        if (!q.length) {
            return;
        }

        $status.text(KISSCOS.i18n.searching || 'Searching...');
        $results.empty();
        $searchTime.text('');
        hideDebugPanel();

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

            // Developer debugging panel (only present when KISS_WOO_COS_DEBUG is enabled server-side)
            if (resp.data && resp.data.debug) {
                showDebugPanel(resp.data.debug);
                try {
                    // eslint-disable-next-line no-console
                    console.log('[KISS_WOO_COS DEBUG]', resp.data.debug);
                } catch (e) {
                    // ignore
                }
            }

            // Auto-redirect if backend determined this is a direct order match
            if (resp.data.should_redirect_to_order && resp.data.orders && resp.data.orders.length === 1) {
                window.location.href = resp.data.orders[0].view_url;
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
    if (initialQ.length >= 2) {
        $input.val(initialQ);
        $form.trigger('submit');
    }
});
