jQuery(document).ready(function($) {

    function injectKISSSearch() {

        // target container for React orders header
        let target = $('ul.subsubsub');

        if (target.length && !$('#kiss-fast-search').length) {

            target.before(`
                <div id="kiss-fast-search" style="margin-right:15px;">
                    <input type="text" id="kiss-fast-order-search"
                        placeholder="Fast search orders or customers..."
                        style="padding:6px 10px; font-size:14px; width:50%;" />
                    <button class="button button-primary" id="kiss-fast-order-search-btn" style="padding:6px 25px;">
                        Search
                    </button>
                </div>
            `);

            console.log('KISS Search Bar Injected Successfully');
        }
    }

    // react screens load async → poll until available
    let poll = setInterval(() => {
        injectKISSSearch();
    }, 500);

    // stop polling after 10 seconds
    setTimeout(() => clearInterval(poll), 10000);
});

// Handle search button click
jQuery(document).on('click', '#kiss-fast-order-search-btn', function(e) {
    e.preventDefault();

    let q = jQuery('#kiss-fast-order-search').val().trim();

    if (!q) {
        alert('Please enter email, name, or order number.');
        return;
    }

    jQuery('#kiss-fast-search-btn').prop('disabled', true);

    // Show a loading area (inject anywhere you want)
    if (!jQuery('#kiss-fast-results').length) {
        jQuery('#kiss-fast-search').after('<div id="kiss-fast-results" style="margin-top:15px;"></div>');
    }

    jQuery('#kiss-fast-results').html('<p><em>Searching...</em></p>');

    jQuery.ajax({
        url: KISSCOS.ajax_url,
        method: 'POST',
        dataType: 'json',
        data: {
            action: 'kiss_woo_customer_search',
            nonce: KISSCOS.nonce,
            q: q
        }
    })
    .done(function(resp){

        if (!resp || !resp.success) {
            jQuery('#kiss-fast-results').html('<p><strong>No results found.</strong></p>');
            return;
        }

        // Render results
        renderKISSResults(resp.data);
    })
    .fail(function(){
        jQuery('#kiss-fast-results').html('<p><strong>Request failed. Try again.</strong></p>');
    })
    .always(function(){
        jQuery('#kiss-fast-order-search-btn').prop('disabled', false);
    });
});

function renderKISSResults(data) {

    let html = '';

    /** ------------------------------
     *  CUSTOMERS TABLE
     * ------------------------------ */
    if (data.customers && data.customers.length) {

        html += `
            <h3 style="margin-top:20px;">Matching Customers</h3>

            <table class="wp-list-table widefat striped fixed">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Registered</th>
                        <th>Total Orders</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
        `;

        data.customers.forEach(c => {

            html += `
                <tr>
                    <td><strong>${c.name}</strong></td>
                    <td>${c.email}</td>
                    <td>${c.registered_h}</td>
                    <td>${c.orders}</td>
                    <td>
                        <a href="${c.edit_url}" target="_blank" class="button">Edit User</a>
                    </td>
                </tr>

                <tr>
                    <td colspan="5" style="background:#f9f9f9; padding:10px 15px;">
                        <strong>Orders:</strong>
                        <table class="widefat striped" style="margin-top:10px;">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Status</th>
                                    <th>Total</th>
                                    <th>Date</th>
                                    <th>Payment</th>
                                    <th>Shipping Method</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

            c.orders_list.forEach(order => {
                html += `
                    <tr>
                        <td>
                            <a href="${order.view_url}" target="_blank"><strong>${order.number}</strong></a>
                        </td>
                        <td>${order.status_label}</td>
                        <td>${order.total}</td>
                        <td>${order.date}</td>
                        <td>${order.payment}</td>
                        <td>${order.shipping}</td>
                    </tr>
                `;
            });

            html += `
                            </tbody>
                        </table>
                    </td>
                </tr>
            `;
        });

        html += `
                </tbody>
            </table>
        `;
    }

    /** ------------------------------
     *  GUEST ORDERS TABLE
     * ------------------------------ */
    if (data.guest_orders && data.guest_orders.length) {

        html += `
            <h3 style="margin-top:25px;">Guest Orders (No Account)</h3>

            <table class="wp-list-table widefat striped fixed">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Date</th>
                        <th>Payment</th>
                        <th>Shipping Method</th>
                    </tr>
                </thead>
                <tbody>
        `;

        data.guest_orders.forEach(o => {

            html += `
                <tr>
                    <td>
                        <a href="${o.view_url}" target="_blank"><strong>${o.number}</strong></a>
                    </td>
                    <td>${o.billing_email}</td>
                    <td>${o.status_label}</td>
                    <td>${o.total}</td>
                    <td>${o.date}</td>
                    <td>${o.payment}</td>
                    <td>${o.shipping}</td>
                </tr>
            `;
        });

        html += `
                </tbody>
            </table>
        `;
    }

    // Output
    jQuery('#kiss-fast-results').html(html);
}



