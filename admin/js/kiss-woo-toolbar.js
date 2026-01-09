/**
 * KISS Woo Fast Order Search - Toolbar JavaScript
 * Extracted from inline scripts in toolbar.php
 */

(function($) {
    'use strict';

    if (typeof KISSCOS !== 'undefined' && KISSCOS.debug) {
        console.log('🔍 KISS Toolbar loaded - Version 1.1.8 (direct order search enabled)');
    }

    const toolbar = document.getElementById('floating-search-toolbar');
    const input = document.getElementById('floating-search-input');
    const submitBtn = document.getElementById('floating-search-submit');

    if (!toolbar || !input || !submitBtn) {
        return;
    }

    // Add class to body for CSS adjustments
    document.body.classList.add('floating-toolbar-active');

    // Store original button text
    const originalBtnText = submitBtn.textContent;

    function handleSearch() {
        const searchTerm = input.value.trim();
        if (!searchTerm) {
            input.focus();
            return;
        }

        if (floatingSearchBar && floatingSearchBar.minChars && searchTerm.length < floatingSearchBar.minChars) {
            input.focus();
            return;
        }

        // Show loading state
        submitBtn.disabled = true;
        submitBtn.textContent = 'Searching...';
        input.disabled = true;

        // Try AJAX search first (fast path for direct order matches)
        $.ajax({
            url: floatingSearchBar.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'kiss_woo_customer_search',
                nonce: floatingSearchBar.nonce,
                q: searchTerm
            },
            timeout: 3000 // 3 second timeout
        }).done(function(resp) {
            if (typeof KISSCOS !== 'undefined' && KISSCOS.debug) {
                console.log('🔍 KISS Toolbar: AJAX response', resp);
            }

            // If we got a direct order match, redirect immediately
            if (resp && resp.success && resp.data && resp.data.should_redirect_to_order && resp.data.redirect_url) {
                if (typeof KISSCOS !== 'undefined' && KISSCOS.debug) {
                    console.log('✅ KISS Toolbar: Direct order match found, redirecting to:', resp.data.redirect_url);
                }
                window.location.href = resp.data.redirect_url;
                return;
            }

            // Otherwise, fall back to search page
            if (typeof KISSCOS !== 'undefined' && KISSCOS.debug) {
                console.log('📋 KISS Toolbar: No direct match, going to search page');
            }
            fallbackToSearchPage(searchTerm);

        }).fail(function(xhr, status, error) {
            if (typeof KISSCOS !== 'undefined' && KISSCOS.debug) {
                console.log('⚠️ KISS Toolbar: AJAX failed, falling back to search page', error);
            }
            // On error, fall back to search page
            fallbackToSearchPage(searchTerm);
        });
    }

    function fallbackToSearchPage(searchTerm) {
        const baseUrl = (floatingSearchBar && floatingSearchBar.searchUrl) ? floatingSearchBar.searchUrl : '';
        if (!baseUrl) {
            // Reset UI
            submitBtn.disabled = false;
            submitBtn.textContent = originalBtnText;
            input.disabled = false;
            return;
        }
        window.location.href = baseUrl + '&q=' + encodeURIComponent(searchTerm);
    }

    submitBtn.addEventListener('click', handleSearch);

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleSearch();
        }
    });

})(jQuery);

