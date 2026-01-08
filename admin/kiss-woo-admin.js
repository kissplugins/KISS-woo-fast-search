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

    function renderResults(data) {
        var customers = data.customers || [];
        var guestOrders = data.guest_orders || [];
        var orders = data.orders || [];

        if (!customers.length && !guestOrders.length && !orders.length) {
            $results.html('<p><strong>' + (KISSCOS.i18n.no_results || 'No matching customers found.') + '</strong></p>');
            return;
        }

        var html = '';

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
