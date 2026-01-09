jQuery(function ($) {
    // Version check - helps identify if cached JS is being used (debug mode only)
    if (typeof KISSCOS !== 'undefined' && KISSCOS.debug) {
        console.log('🔍 KISS Search JS loaded - Version 1.2.3 (explicit state machine)');
    }

    var $form   = $('#kiss-cos-search-form');
    var $input  = $('#kiss-cos-search-input');
    var $status = $('#kiss-cos-search-status');
    var $results = $('#kiss-cos-results');
    var $searchTime = $('#kiss-cos-search-time');

    /**
     * Explicit State Machine for Search UI
     * Prevents impossible states and ensures consistent UI behavior.
     */
    var SearchState = {
        IDLE: 'idle',
        SEARCHING: 'searching',
        SUCCESS: 'success',
        ERROR: 'error',
        REDIRECTING: 'redirecting'
    };

    var currentState = SearchState.IDLE;
    var stateData = {
        searchTerm: '',
        startTime: 0,
        xhr: null
    };

    /**
     * Transition to a new state with validation.
     * Prevents invalid state transitions and logs them for debugging.
     */
    function transitionTo(newState, data) {
        var validTransitions = {
            'idle': ['searching'],
            'searching': ['success', 'error', 'redirecting', 'idle'],
            'success': ['idle', 'searching'],
            'error': ['idle', 'searching'],
            'redirecting': []
        };

        if (!validTransitions[currentState] || validTransitions[currentState].indexOf(newState) === -1) {
            if (typeof KISSCOS !== 'undefined' && KISSCOS.debug) {
                console.warn('⚠️ Invalid state transition:', currentState, '→', newState);
            }
            return false;
        }

        if (typeof KISSCOS !== 'undefined' && KISSCOS.debug) {
            console.log('🔄 State transition:', currentState, '→', newState, data || {});
        }

        currentState = newState;

        // Update state data
        if (data) {
            Object.assign(stateData, data);
        }

        // Update UI based on new state
        updateUIForState();

        return true;
    }

    /**
     * Update UI elements based on current state.
     * Centralizes all UI updates to prevent inconsistencies.
     */
    function updateUIForState() {
        switch (currentState) {
            case SearchState.IDLE:
                $status.text('');
                $input.prop('disabled', false);
                $form.find('button[type="submit"]').prop('disabled', false);
                break;

            case SearchState.SEARCHING:
                $status.text(KISSCOS.i18n.searching || 'Searching...');
                $results.empty();
                $searchTime.text('');
                $input.prop('disabled', true);
                $form.find('button[type="submit"]').prop('disabled', true);
                break;

            case SearchState.SUCCESS:
                $status.text('');
                $input.prop('disabled', false);
                $form.find('button[type="submit"]').prop('disabled', false);
                break;

            case SearchState.ERROR:
                $status.text('');
                $input.prop('disabled', false);
                $form.find('button[type="submit"]').prop('disabled', false);
                break;

            case SearchState.REDIRECTING:
                $status.text('Redirecting to order...');
                $input.prop('disabled', true);
                $form.find('button[type="submit"]').prop('disabled', true);
                break;
        }
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
            var orderNumber = order.order_number || order.number || order.id;
            html += '<tr>' +
                '<td><a href="' + escapeHtml(order.view_url) + '" target="_blank">' + escapeHtml(orderNumber) + '</a></td>' +
                '<td><span class="kiss-status-pill">' + escapeHtml(order.status_label) + '</span></td>' +
                '<td>' + (order.total_display || order.total || '') + '</td>' +
                '<td>' + escapeHtml(order.date_display || order.date || '') + '</td>' +
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

        // Prevent double submission
        if (currentState === SearchState.SEARCHING || currentState === SearchState.REDIRECTING) {
            if (typeof KISSCOS !== 'undefined' && KISSCOS.debug) {
                console.warn('⚠️ Search already in progress, ignoring duplicate submission');
            }
            return;
        }

        // Transition to SEARCHING state
        if (!transitionTo(SearchState.SEARCHING, {
            searchTerm: q,
            startTime: performance.now()
        })) {
            return;
        }

        // Abort any existing request
        if (stateData.xhr) {
            stateData.xhr.abort();
        }

        stateData.xhr = $.ajax({
            url: KISSCOS.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'kiss_woo_customer_search',
                nonce: KISSCOS.nonce,
                q: q
            }
        }).done(function (resp) {
            // Only process if still in SEARCHING state
            if (currentState !== SearchState.SEARCHING) {
                if (typeof KISSCOS !== 'undefined' && KISSCOS.debug) {
                    console.warn('⚠️ Response received but state is no longer SEARCHING:', currentState);
                }
                return;
            }

            if (!resp || !resp.success) {
                var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Something went wrong.';
                transitionTo(SearchState.ERROR);
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
                if (typeof KISSCOS !== 'undefined' && KISSCOS.debug) {
                    console.log('🔄 KISS: Redirecting to order...', {
                        redirect_url: resp.data.redirect_url,
                        should_redirect: resp.data.should_redirect_to_order,
                        orders: resp.data.orders
                    });
                }

                transitionTo(SearchState.REDIRECTING);

                // Auto-redirect to the order page
                window.location.href = resp.data.redirect_url;
                return;
            }

            // Transition to SUCCESS state
            transitionTo(SearchState.SUCCESS);
            renderResults(resp.data);

            // Display both total round-trip time and database search time with percentage
            var totalSeconds = ((performance.now() - stateData.startTime) / 1000).toFixed(2);
            var dbSeconds = (resp.data && typeof resp.data.search_time !== 'undefined') ? resp.data.search_time : null;

            if (dbSeconds !== null && totalSeconds > 0) {
                var dbPercent = Math.round((dbSeconds / totalSeconds) * 100);
                $searchTime.text('Search completed in ' + totalSeconds + 's (database: ' + dbSeconds + 's / ' + dbPercent + '%)');
            } else {
                $searchTime.text('Search completed in ' + totalSeconds + ' seconds');
            }
        }).fail(function (xhr, status, error) {
            // Only process if still in SEARCHING state
            if (currentState !== SearchState.SEARCHING) {
                return;
            }

            // Don't show error for aborted requests
            if (status === 'abort') {
                transitionTo(SearchState.IDLE);
                return;
            }

            transitionTo(SearchState.ERROR);
            $results.html('<p><strong>Request failed. Please try again.</strong></p>');
        }).always(function () {
            stateData.xhr = null;
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
