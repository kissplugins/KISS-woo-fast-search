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

    let url = KISSCOS.admin_search_url + '&query=' + encodeURIComponent(q);

    window.open(url, '_blank');
});





