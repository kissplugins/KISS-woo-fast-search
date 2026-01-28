/**
 * KISS Woo Settings Page JavaScript
 *
 * Handles coupon lookup table build UI interactions.
 *
 * @package KISS_Woo_Customer_Order_Search
 * @since   1.2.6
 */

(function($) {
    'use strict';

    let pollInterval = null;

    /**
     * Start build process.
     */
    function startBuild() {
        const $button = $('#kiss-start-build');
        const $cancelButton = $('#kiss-cancel-build');
        const $spinner = $('#kiss-build-spinner');

        $button.prop('disabled', true);
        $spinner.addClass('is-active');

        $.ajax({
            url: kissWooSettings.ajax_url,
            type: 'POST',
            data: {
                action: 'kiss_woo_start_coupon_build',
                nonce: kissWooSettings.nonce
            },
            success: function(response) {
                if (response.success) {
                    $cancelButton.prop('disabled', false);
                    startPolling();
                } else {
                    alert(response.data.message || kissWooSettings.i18n.error);
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            },
            error: function() {
                alert(kissWooSettings.i18n.error);
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    }

    /**
     * Cancel build process.
     */
    function cancelBuild() {
        const $button = $('#kiss-start-build');
        const $cancelButton = $('#kiss-cancel-build');
        const $spinner = $('#kiss-build-spinner');

        $cancelButton.prop('disabled', true);

        $.ajax({
            url: kissWooSettings.ajax_url,
            type: 'POST',
            data: {
                action: 'kiss_woo_cancel_coupon_build',
                nonce: kissWooSettings.nonce
            },
            success: function(response) {
                if (response.success) {
                    stopPolling();
                    updateUI({
                        status: 'idle',
                        processed: 0,
                        total: 0
                    });
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                } else {
                    alert(response.data.message || kissWooSettings.i18n.error);
                    $cancelButton.prop('disabled', false);
                }
            },
            error: function() {
                alert(kissWooSettings.i18n.error);
                $cancelButton.prop('disabled', false);
            }
        });
    }

    /**
     * Poll for build progress.
     */
    function pollProgress() {
        $.ajax({
            url: kissWooSettings.ajax_url,
            type: 'POST',
            data: {
                action: 'kiss_woo_get_build_progress',
                nonce: kissWooSettings.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateUI(response.data);

                    // Stop polling if complete or idle.
                    if (response.data.status === 'complete' || response.data.status === 'idle') {
                        stopPolling();
                        $('#kiss-start-build').prop('disabled', false);
                        $('#kiss-cancel-build').prop('disabled', true);
                        $('#kiss-build-spinner').removeClass('is-active');
                    }
                }
            }
        });
    }

    /**
     * Update UI with progress data.
     */
    function updateUI(progress) {
        const percent = progress.total > 0 ? Math.round((progress.processed / progress.total) * 100) : 0;

        $('#kiss-indexed-count').text(progress.processed.toLocaleString());
        $('#kiss-indexed-percent').text(percent);

        const $status = $('#kiss-build-status');
        if (progress.status === 'running') {
            $status.html('<span class="dashicons dashicons-update spin"></span> ' + kissWooSettings.i18n.building);
        } else if (progress.status === 'complete') {
            $status.html('<span class="dashicons dashicons-yes-alt"></span> ' + kissWooSettings.i18n.complete);
        } else {
            $status.html('<span class="dashicons dashicons-minus"></span> Idle');
        }
    }

    /**
     * Start polling for progress.
     */
    function startPolling() {
        if (pollInterval) {
            return;
        }
        pollInterval = setInterval(pollProgress, 3000); // Poll every 3 seconds.
    }

    /**
     * Stop polling for progress.
     */
    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }

    // Initialize.
    $(document).ready(function() {
        $('#kiss-start-build').on('click', startBuild);
        $('#kiss-cancel-build').on('click', cancelBuild);

        // Auto-start polling if already running.
        const initialStatus = $('#kiss-build-status').text().trim();
        if (initialStatus.includes(kissWooSettings.i18n.building)) {
            startPolling();
        }
    });

})(jQuery);

