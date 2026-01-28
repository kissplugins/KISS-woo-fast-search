/**
 * KISS Woo Fast Order Search - Toolbar JavaScript
 * Extracted from inline scripts in toolbar.php
 */

(function($) {
    'use strict';

    if (typeof KISSCOS !== 'undefined' && KISSCOS.debug) {
        console.log('🔍 KISS Toolbar loaded - Version 1.2.1 (state machine + timeout fallback)');
    }

    const toolbar = document.getElementById('floating-search-toolbar');
    const input = document.getElementById('floating-search-input');
    const submitBtn = document.getElementById('floating-search-submit');
    const scopeInputs = document.querySelectorAll('input[name="kiss-search-scope"]');

    if (!toolbar || !input || !submitBtn || !scopeInputs.length) {
        return;
    }

    // Add class to body for CSS adjustments
    document.body.classList.add('floating-toolbar-active');

    // Store original button text
    const originalBtnText = submitBtn.textContent;
    const placeholderUsers = input.getAttribute('data-placeholder-users') || input.placeholder || '';
    const placeholderCoupons = input.getAttribute('data-placeholder-coupons') || input.placeholder || '';

    function getSearchScope() {
        for (let i = 0; i < scopeInputs.length; i++) {
            if (scopeInputs[i].checked) {
                return scopeInputs[i].value;
            }
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

    function syncPlaceholderForScope() {
        const scope = getSearchScope();
        if (scope === 'coupons') {
            input.placeholder = placeholderCoupons;
        } else {
            input.placeholder = placeholderUsers;
        }
    }

    // Save scope to localStorage when changed
    scopeInputs.forEach(function(inputEl) {
        inputEl.addEventListener('change', function() {
            const scope = getSearchScope();
            saveScope(scope);
            syncPlaceholderForScope();
        });
    });

    // Restore saved scope on page load
    const savedScope = loadScope();
    scopeInputs.forEach(function(inputEl) {
        if (inputEl.value === savedScope) {
            inputEl.checked = true;
        }
    });
    syncPlaceholderForScope();

    /**
     * Explicit State Machine for Toolbar Search
     * Prevents impossible states and ensures consistent UI behavior.
     */
    const ToolbarState = {
        IDLE: 'idle',
        SEARCHING: 'searching',
        REDIRECTING_ORDER: 'redirecting_order',
        REDIRECTING_SEARCH: 'redirecting_search'
    };

    let currentState = ToolbarState.IDLE;
    let currentXhr = null;
    let safetyTimeout = null;

    /**
     * Transition to a new state with validation.
     */
    function transitionTo(newState) {
        const validTransitions = {
            'idle': ['searching'],
            'searching': ['redirecting_order', 'redirecting_search', 'idle'],
            'redirecting_order': ['idle'], // Allow recovery from stuck redirect
            'redirecting_search': ['idle']  // Allow recovery from stuck redirect
        };

        if (!validTransitions[currentState] || validTransitions[currentState].indexOf(newState) === -1) {
            console.warn('⚠️ Toolbar: Invalid state transition:', currentState, '→', newState);
            return false;
        }

        if (typeof KISSCOS !== 'undefined' && KISSCOS.debug) {
            console.log('🔄 Toolbar state transition:', currentState, '→', newState);
        }

        // Clear any existing safety timeout
        if (safetyTimeout) {
            clearTimeout(safetyTimeout);
            safetyTimeout = null;
        }

        currentState = newState;
        updateUIForState();

        // Set safety timeout for redirect states
        // If navigation is blocked (popup blocker, etc.), reset UI after 5 seconds
        if (newState === ToolbarState.REDIRECTING_ORDER || newState === ToolbarState.REDIRECTING_SEARCH) {
            safetyTimeout = setTimeout(function() {
                if (currentState === ToolbarState.REDIRECTING_ORDER || currentState === ToolbarState.REDIRECTING_SEARCH) {
                    if (typeof KISSCOS !== 'undefined' && KISSCOS.debug) {
                        console.warn('⚠️ Toolbar: Navigation timeout - resetting UI (possible popup blocker)');
                    }
                    currentState = ToolbarState.IDLE; // Force transition
                    updateUIForState();
                    safetyTimeout = null;
                }
            }, 5000); // 5 second safety timeout
        }

        return true;
    }

    /**
     * Update UI elements based on current state.
     */
    function updateUIForState() {
        switch (currentState) {
            case ToolbarState.IDLE:
                submitBtn.disabled = false;
                submitBtn.textContent = originalBtnText;
                input.disabled = false;
                break;

            case ToolbarState.SEARCHING:
                submitBtn.disabled = true;
                submitBtn.textContent = 'Searching...';
                input.disabled = true;
                break;

            case ToolbarState.REDIRECTING_ORDER:
                submitBtn.disabled = true;
                submitBtn.textContent = 'Opening order...';
                input.disabled = true;
                break;

            case ToolbarState.REDIRECTING_SEARCH:
                submitBtn.disabled = true;
                submitBtn.textContent = 'Loading results...';
                input.disabled = true;
                break;
        }
    }

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

        // Prevent double submission
        if (currentState !== ToolbarState.IDLE) {
            console.warn('⚠️ Toolbar: Search already in progress, ignoring duplicate submission');
            return;
        }

        const scope = getSearchScope();

        // Transition to SEARCHING state
        if (!transitionTo(ToolbarState.SEARCHING)) {
            return;
        }

        // Coupons scope: skip AJAX direct-order search and go straight to search page.
        if (scope === 'coupons') {
            fallbackToSearchPage(searchTerm, scope);
            return;
        }

        // Abort any existing request
        if (currentXhr) {
            currentXhr.abort();
        }

        // Try AJAX search first (fast path for direct order matches)
        currentXhr = $.ajax({
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
            // Only process if still in SEARCHING state
            if (currentState !== ToolbarState.SEARCHING) {
                console.warn('⚠️ Toolbar: Response received but state is no longer SEARCHING:', currentState);
                return;
            }

            if (typeof KISSCOS !== 'undefined' && KISSCOS.debug) {
                console.log('🔍 KISS Toolbar: AJAX response', resp);
            }

            // If we got a direct match (order or coupon), redirect immediately
            if (resp && resp.success && resp.data && resp.data.should_redirect_to_order && resp.data.redirect_url) {
                if (typeof KISSCOS !== 'undefined' && KISSCOS.debug) {
                    console.log('✅ KISS Toolbar: Direct match found, redirecting to:', resp.data.redirect_url);
                }
                transitionTo(ToolbarState.REDIRECTING_ORDER);
                window.location.href = resp.data.redirect_url;
                return;
            }

            // Otherwise, fall back to search page
            if (typeof KISSCOS !== 'undefined' && KISSCOS.debug) {
                console.log('📋 KISS Toolbar: No direct match, going to search page');
            }
            fallbackToSearchPage(searchTerm, scope);

        }).fail(function(xhr, status, error) {
            // Only process if still in SEARCHING state
            if (currentState !== ToolbarState.SEARCHING) {
                return;
            }

            // Don't show error for aborted requests
            if (status === 'abort') {
                transitionTo(ToolbarState.IDLE);
                return;
            }

            if (typeof KISSCOS !== 'undefined' && KISSCOS.debug) {
                console.log('⚠️ KISS Toolbar: AJAX failed, falling back to search page', error);
            }
            // On error, fall back to search page
            fallbackToSearchPage(searchTerm, scope);
        }).always(function() {
            currentXhr = null;
        });
    }

    function fallbackToSearchPage(searchTerm, scope) {
        const baseUrl = (floatingSearchBar && floatingSearchBar.searchUrl) ? floatingSearchBar.searchUrl : '';
        if (!baseUrl) {
            // Reset to IDLE state
            transitionTo(ToolbarState.IDLE);
            return;
        }
        const searchScope = scope || 'users';
        transitionTo(ToolbarState.REDIRECTING_SEARCH);
        window.location.href = baseUrl + '&q=' + encodeURIComponent(searchTerm) + '&scope=' + encodeURIComponent(searchScope);
    }

    submitBtn.addEventListener('click', handleSearch);

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleSearch();
        }
    });

    // Clear safety timeout on successful navigation
    window.addEventListener('beforeunload', function() {
        if (safetyTimeout) {
            clearTimeout(safetyTimeout);
            safetyTimeout = null;
        }
    });

})(jQuery);
