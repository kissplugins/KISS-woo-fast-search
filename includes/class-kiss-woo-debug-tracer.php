<?php
/**
 * Centralized debug tracing for observability.
 *
 * SINGLE WRITE PATH: All debug logging goes through this class.
 * Never use error_log() directly elsewhere.
 *
 * @package KISS_Woo_Customer_Order_Search
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KISS_Woo_Debug_Tracer {

    /** @var array In-memory trace buffer for current request */
    private static $traces = array();

    /** @var float Request start time */
    private static $request_start;

    /** @var bool Whether debug mode is enabled */
    private static $enabled = null;

    /**
     * Initialize tracer at plugin load.
     */
    public static function init() {
        self::$request_start = microtime( true );
        self::$enabled       = self::is_debug_enabled();

        if ( self::$enabled ) {
            // Store traces in transient for debug panel retrieval.
            add_action( 'shutdown', array( __CLASS__, 'persist_traces' ) );
        }
    }

    /**
     * Check if debug mode is enabled.
     *
     * Enable via: define( 'KISS_WOO_FAST_SEARCH_DEBUG', true ); or filter.
     *
     * @return bool
     */
    private static function is_debug_enabled(): bool {
        if ( defined( 'KISS_WOO_FAST_SEARCH_DEBUG' ) && KISS_WOO_FAST_SEARCH_DEBUG ) {
            return true;
        }
        return apply_filters( 'kiss_woo_debug_enabled', false );
    }

    /**
     * Redact sensitive data from context before logging to error_log.
     *
     * Prevents PII leaks in server logs by redacting known sensitive keys.
     *
     * @param array $context Context data to redact.
     * @return array Redacted context.
     */
    private static function redact_sensitive_data( array $context ): array {
        $sensitive_keys = array(
            'email',
            'billing_email',
            'shipping_email',
            'search_term',
            'customer_id',
            'user_id',
            'billing_phone',
            'shipping_phone',
            'billing_address_1',
            'billing_address_2',
            'shipping_address_1',
            'shipping_address_2',
            'ip_address',
            'user_agent',
        );

        $redacted = $context;

        foreach ( $sensitive_keys as $key ) {
            if ( isset( $redacted[ $key ] ) ) {
                // Keep first 3 chars for debugging context, redact rest
                $value = (string) $redacted[ $key ];
                if ( strlen( $value ) > 3 ) {
                    $redacted[ $key ] = substr( $value, 0, 3 ) . '***';
                } else {
                    $redacted[ $key ] = '***';
                }
            }
        }

        // Recursively redact nested arrays
        foreach ( $redacted as $key => $value ) {
            if ( is_array( $value ) ) {
                $redacted[ $key ] = self::redact_sensitive_data( $value );
            }
        }

        return $redacted;
    }

    /**
     * Log a trace event.
     *
     * @param string $component Component name (e.g., 'OrderResolver', 'AjaxHandler').
     * @param string $action    Action being performed (e.g., 'resolve', 'cache_hit').
     * @param array  $context   Additional context data.
     * @param string $level     Log level: 'debug', 'info', 'warn', 'error'.
     */
    public static function log( string $component, string $action, array $context = array(), string $level = 'debug' ): void {
        if ( ! self::$enabled && 'error' !== $level ) {
            return; // Always log errors, skip others if disabled.
        }

        $elapsed = round( ( microtime( true ) - self::$request_start ) * 1000, 2 );

        $trace = array(
            'timestamp'  => current_time( 'mysql' ),
            'elapsed_ms' => $elapsed,
            'component'  => $component,
            'action'     => $action,
            'level'      => $level,
            'context'    => $context,
            'memory_mb'  => round( memory_get_usage() / 1024 / 1024, 2 ),
        );

        self::$traces[] = $trace;

        // Also write errors to error_log for server logs.
        // SECURITY: Redact PII before logging to prevent data leaks.
        if ( 'error' === $level ) {
            $redacted_context = self::redact_sensitive_data( $context );
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log(
                sprintf(
                    '[KISS-WOO][%s] %s::%s - %s',
                    strtoupper( $level ),
                    $component,
                    $action,
                    wp_json_encode( $redacted_context )
                )
            );
        }
    }

    /**
     * Start a timed operation. Returns a closure to call when done.
     *
     * Usage:
     *   $done = KISS_Woo_Debug_Tracer::start_timer( 'OrderResolver', 'sequential_lookup' );
     *   // ... do work ...
     *   $done( ['order_id' => 123] ); // Logs with duration
     *
     * @param string $component Component name.
     * @param string $action    Action name.
     * @return callable
     */
    public static function start_timer( string $component, string $action ): callable {
        $start = microtime( true );

        return function ( array $context = array() ) use ( $component, $action, $start ) {
            $duration              = round( ( microtime( true ) - $start ) * 1000, 2 );
            $context['duration_ms'] = $duration;
            self::log( $component, $action, $context, 'info' );
        };
    }

    /**
     * Get all traces for current request.
     *
     * @return array
     */
    public static function get_traces(): array {
        return self::$traces;
    }

    /**
     * Persist traces to transient for debug panel.
     * Called on shutdown hook.
     */
    public static function persist_traces(): void {
        if ( empty( self::$traces ) ) {
            return;
        }

        // Get existing trace history (keep last 50 requests).
        $history = get_transient( 'kiss_woo_debug_traces' );
        if ( ! is_array( $history ) ) {
            $history = array();
        }

        $request_id              = uniqid( 'req_', true );
        $history[ $request_id ] = array(
            'url'       => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : 'unknown',
            'method'    => isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'unknown',
            'timestamp' => current_time( 'mysql' ),
            'traces'    => self::$traces,
            'total_ms'  => round( ( microtime( true ) - self::$request_start ) * 1000, 2 ),
        );

        // Keep only last 50 requests.
        if ( count( $history ) > 50 ) {
            $history = array_slice( $history, -50, 50, true );
        }

        set_transient( 'kiss_woo_debug_traces', $history, HOUR_IN_SECONDS );
    }

    /**
     * Clear trace history.
     */
    public static function clear_history(): void {
        delete_transient( 'kiss_woo_debug_traces' );
    }

    /**
     * Get trace history for debug panel.
     *
     * @return array
     */
    public static function get_history(): array {
        $history = get_transient( 'kiss_woo_debug_traces' );
        return is_array( $history ) ? $history : array();
    }
}

