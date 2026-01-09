/**
 * KISS Woo Fast Order Search - Debug Panel JavaScript
 * Extracted from inline scripts in class-kiss-woo-debug-panel.php
 */

jQuery(function($) {
    'use strict';

    var autoRefreshInterval = null;
    var nonce = kissWooDebug.nonce;

    function loadTraces() {
        $.post(ajaxurl, {
            action: 'kiss_woo_debug_get_traces',
            nonce: nonce
        }, function(response) {
            if (response.success) {
                renderTraces(response.data);
            }
        });
    }

    function renderTraces(history) {
        var $container = $('#kiss-debug-traces-container');

        if (!history || Object.keys(history).length === 0) {
            $container.html('<p>' + kissWooDebug.i18n.noTraces + '</p>');
            return;
        }

        var html = '';
        var keys = Object.keys(history).reverse();

        keys.forEach(function(requestId) {
            var request = history[requestId];
            html += '<div class="kiss-debug-request" data-id="' + requestId + '">';
            html += '<div class="kiss-debug-request-header">';
            html += '<span><strong>' + request.method + '</strong> ' + escapeHtml(request.url) + '</span>';
            html += '<span>' + request.total_ms + 'ms - ' + request.timestamp + '</span>';
            html += '</div>';
            html += '<div class="kiss-debug-request-body">';

            request.traces.forEach(function(trace) {
                html += '<div class="kiss-trace-item level-' + trace.level + '">';
                html += '<span class="kiss-trace-time">[' + trace.elapsed_ms + 'ms]</span> ';
                html += '<span class="kiss-trace-component">' + trace.component + '</span>';
                html += '::<span class="kiss-trace-action">' + trace.action + '</span>';
                if (trace.context && Object.keys(trace.context).length > 0) {
                    html += '<div class="kiss-trace-context">' + escapeHtml(JSON.stringify(trace.context)) + '</div>';
                }
                html += '</div>';
            });

            html += '</div></div>';
        });

        $container.html(html);
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    $(document).on('click', '.kiss-debug-request-header', function() {
        $(this).closest('.kiss-debug-request').toggleClass('expanded');
    });

    $('#kiss-debug-refresh').on('click', loadTraces);

    $('#kiss-debug-clear').on('click', function() {
        $.post(ajaxurl, {
            action: 'kiss_woo_debug_clear_traces',
            nonce: nonce
        }, function() {
            loadTraces();
        });
    });

    $('#kiss-debug-auto-refresh').on('change', function() {
        if ($(this).is(':checked')) {
            autoRefreshInterval = setInterval(loadTraces, 5000);
        } else {
            clearInterval(autoRefreshInterval);
        }
    });

    loadTraces();
});

