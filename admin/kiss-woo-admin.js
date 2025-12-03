jQuery(function ($) {
    var $form   = $('#kiss-cos-search-form');
    var $input  = $('#kiss-cos-search-input');
    var $status = $('#kiss-cos-search-status');
    var $results = $('#kiss-cos-results');

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
                '<td>' + (order.number || order.id) + '</td>' +
                '<td><span class="kiss-status-pill">' + order.status_label + '</span></td>' +
                '<td>' + order.total + '</td>' +
                '<td>' + order.date + '</td>' +
                '<td>' + (order.payment || '') + '</td>' +
                '<td>' + (order.shipping || '') + '</td>' +
                '<td><a href="' + order.view_url + '" class="button button-small" target="_blank">View</a></td>' +
                '</tr>';
        });

        html += '</tbody></table>';
        return html;
    }

    function renderResults(data) {
        var customers = data.customers || [];
        var guestOrders = data.guest_orders || [];

        if (!customers.length && !guestOrders.length) {
            $results.html('<p><strong>' + (KISSCOS.i18n.no_results || 'No matching customers found.') + '</strong></p>');
            return;
        }

        var html = '';

        customers.forEach(function (cust) {
            html += '<div class="kiss-cos-customer">';
            html += '<div class="kiss-cos-customer-header">';
            html += '<div class="kiss-cos-customer-name">' +
                (cust.name || '(No name)') +
                ' &lt;' + (cust.email || '') + '&gt;' +
                '</div>';
            html += '<div class="kiss-cos-customer-meta">' +
                'User ID: ' + cust.id +
                (cust.registered_h ? ' · Since: ' + cust.registered_h : '') +
                ' · Orders: ' + cust.orders +
                '</div>';
            html += '</div>';

            html += '<div class="kiss-cos-customer-actions">';
            if (cust.edit_url) {
                html += '<a href="' + cust.edit_url + '" class="button button-secondary button-small" target="_blank">View user</a>';
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

            renderResults(resp.data);
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
});
