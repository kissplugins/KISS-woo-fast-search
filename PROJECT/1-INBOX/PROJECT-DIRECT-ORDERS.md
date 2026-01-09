# Order Number Search Enhancement

**Date:** 2025-01-08
**Status:** ✅ Completed (v1.1.0 - 2026-01-09)
**Priority:** P1

---

## Executive Summary

Add order number search to the existing admin search input field toolbar and existing search page with full support for SkyVerge Sequential Order Numbers Pro. Design emphasizes centralized helpers, single write paths, and built-in observability from day one.

### Performance Targets

| Scenario | Target | Method |
|----------|--------|--------|
| Cached lookup | < 5ms | Transient cache |
| Sequential plugin lookup | < 100ms | `wc_sequential_order_numbers()->find_order_by_order_number()` |
| Direct ID fallback | < 20ms | `wc_get_order($id)` |

### Supported Formats

All formats are case-insensitive: `12345`, `#12345`, `B349445`, `#B349445`, `D349445`, `#D349445`

---

## Architecture Principles

### 1. Single Write Paths

Every operation has exactly ONE method responsible for writing/modifying data. No duplicate logic scattered across files.

```
Order Lookup     → OrderNumberResolver::resolve()
Result Caching   → SearchCache::set()
Debug Logging    → DebugTracer::log()
AJAX Response    → AjaxHandler::send_response()
```

### 2. Centralized Helpers

All shared logic lives in dedicated helper classes. Business logic never duplicated between AJAX handlers, admin pages, or toolbar.

### 3. Observable by Default

Every code path logs to the debug tracer. Problems are visible immediately, not discovered through user reports.

---

## File Structure

```
kiss-woo-customer-order-search/
├── includes/
│   ├── class-kiss-woo-search.php           # Main search orchestrator
│   ├── class-kiss-woo-order-resolver.php   # NEW: Centralized order lookup
│   ├── class-kiss-woo-debug-tracer.php     # NEW: Observability system
│   ├── class-kiss-woo-search-cache.php     # Existing cache (reuse)
│   └── class-kiss-woo-ajax-handler.php     # Refactored AJAX handling
├── admin/
│   ├── class-kiss-woo-admin-page.php       # Search page
│   ├── class-kiss-woo-debug-panel.php      # NEW: Debug UI
│   ├── kiss-woo-admin.js                   # Frontend JS
│   └── kiss-woo-admin.css                  # Styles
└── toolbar.php                              # Admin toolbar
```

---

## Core Components

### 1. Debug Tracer (Build First)

The debug tracer is the foundation. Build it before anything else so all subsequent code is observable.

**File:** `includes/class-kiss-woo-debug-tracer.php`

```php
<?php
/**
 * Centralized debug tracing for observability.
 * 
 * SINGLE WRITE PATH: All debug logging goes through this class.
 * Never use error_log() directly elsewhere.
 */
class KISS_Woo_Debug_Tracer {
    
    /** @var array In-memory trace buffer for current request */
    private static $traces = [];
    
    /** @var float Request start time */
    private static $request_start;
    
    /** @var bool Whether debug mode is enabled */
    private static $enabled = null;
    
    /**
     * Initialize tracer at plugin load.
     */
    public static function init() {
        self::$request_start = microtime( true );
        self::$enabled = self::is_debug_enabled();
        
        if ( self::$enabled ) {
            // Store traces in transient for debug panel retrieval
            add_action( 'shutdown', [ __CLASS__, 'persist_traces' ] );
        }
    }
    
    /**
     * Check if debug mode is enabled.
     * 
     * Enable via: define( 'KISS_WOO_DEBUG', true ); or filter.
     */
    private static function is_debug_enabled(): bool {
        if ( defined( 'KISS_WOO_DEBUG' ) && KISS_WOO_DEBUG ) {
            return true;
        }
        return apply_filters( 'kiss_woo_debug_enabled', false );
    }
    
    /**
     * Log a trace event.
     * 
     * @param string $component Component name (e.g., 'OrderResolver', 'AjaxHandler')
     * @param string $action    Action being performed (e.g., 'resolve', 'cache_hit')
     * @param array  $context   Additional context data
     * @param string $level     Log level: 'debug', 'info', 'warn', 'error'
     */
    public static function log( string $component, string $action, array $context = [], string $level = 'debug' ): void {
        if ( ! self::$enabled && $level !== 'error' ) {
            return; // Always log errors, skip others if disabled
        }
        
        $elapsed = round( ( microtime( true ) - self::$request_start ) * 1000, 2 );
        
        $trace = [
            'timestamp'  => current_time( 'mysql' ),
            'elapsed_ms' => $elapsed,
            'component'  => $component,
            'action'     => $action,
            'level'      => $level,
            'context'    => $context,
            'memory_mb'  => round( memory_get_usage() / 1024 / 1024, 2 ),
        ];
        
        self::$traces[] = $trace;
        
        // Also write errors to error_log for server logs
        if ( $level === 'error' ) {
            error_log( sprintf(
                '[KISS-WOO][%s] %s::%s - %s',
                strtoupper( $level ),
                $component,
                $action,
                wp_json_encode( $context )
            ) );
        }
    }
    
    /**
     * Start a timed operation. Returns a closure to call when done.
     * 
     * Usage:
     *   $done = DebugTracer::start_timer( 'OrderResolver', 'sequential_lookup' );
     *   // ... do work ...
     *   $done( ['order_id' => 123] ); // Logs with duration
     */
    public static function start_timer( string $component, string $action ): callable {
        $start = microtime( true );
        
        return function( array $context = [] ) use ( $component, $action, $start ) {
            $duration = round( ( microtime( true ) - $start ) * 1000, 2 );
            $context['duration_ms'] = $duration;
            self::log( $component, $action, $context, 'info' );
        };
    }
    
    /**
     * Get all traces for current request.
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
        
        // Get existing trace history (keep last 50 requests)
        $history = get_transient( 'kiss_woo_debug_traces' ) ?: [];
        
        $request_id = uniqid( 'req_', true );
        $history[ $request_id ] = [
            'url'       => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method'    => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'timestamp' => current_time( 'mysql' ),
            'traces'    => self::$traces,
            'total_ms'  => round( ( microtime( true ) - self::$request_start ) * 1000, 2 ),
        ];
        
        // Keep only last 50 requests
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
     */
    public static function get_history(): array {
        return get_transient( 'kiss_woo_debug_traces' ) ?: [];
    }
}
```

---

### 2. Order Number Resolver (Centralized Lookup)

**File:** `includes/class-kiss-woo-order-resolver.php`

This is the SINGLE entry point for all order number lookups. No other code should call `wc_get_order()` or Sequential plugin methods directly for order number searches.

```php
<?php
/**
 * Centralized order number resolution.
 * 
 * SINGLE WRITE PATH: All order-by-number lookups go through resolve().
 * Never call wc_sequential_order_numbers() directly elsewhere.
 */
class KISS_Woo_Order_Resolver {
    
    /** @var KISS_Woo_Search_Cache */
    private $cache;
    
    /** @var array Allowed order number prefixes */
    private $allowed_prefixes;
    
    /**
     * Constructor.
     */
    public function __construct( KISS_Woo_Search_Cache $cache ) {
        $this->cache = $cache;
        $this->allowed_prefixes = apply_filters( 'kiss_woo_order_search_prefixes', [ 'B', 'D' ] );
    }
    
    /**
     * Resolve an order number to an order.
     * 
     * This is the ONLY method external code should call for order number lookups.
     * 
     * @param string $input Raw user input (e.g., "#B349445", "349445", "b349445")
     * @return array{order: WC_Order|null, source: string, cached: bool}
     */
    public function resolve( string $input ): array {
        $done = KISS_Woo_Debug_Tracer::start_timer( 'OrderResolver', 'resolve' );
        
        // Step 1: Normalize input
        $normalized = $this->normalize( $input );
        
        if ( $normalized === null ) {
            KISS_Woo_Debug_Tracer::log( 'OrderResolver', 'invalid_input', [
                'input' => $input,
                'reason' => 'Does not match order number pattern',
            ] );
            $done( [ 'result' => 'invalid_input' ] );
            return [ 'order' => null, 'source' => 'invalid', 'cached' => false ];
        }
        
        // Step 2: Check cache
        $cache_key = $this->cache->get_search_key( $normalized['cache_key'], 'order' );
        $cached_id = $this->cache->get( $cache_key );
        
        if ( $cached_id !== null ) {
            KISS_Woo_Debug_Tracer::log( 'OrderResolver', 'cache_hit', [
                'input' => $input,
                'cached_id' => $cached_id,
            ] );
            
            if ( $cached_id === 0 ) {
                // Cached "not found" result
                $done( [ 'result' => 'cached_miss' ] );
                return [ 'order' => null, 'source' => 'cache', 'cached' => true ];
            }
            
            $order = wc_get_order( $cached_id );
            $done( [ 'result' => 'cached_hit', 'order_id' => $cached_id ] );
            return [ 'order' => $order, 'source' => 'cache', 'cached' => true ];
        }
        
        // Step 3: Try Sequential Order Numbers Pro (if available)
        $order = $this->try_sequential_plugin( $normalized );
        
        if ( $order ) {
            $this->cache->set( $cache_key, $order->get_id() );
            $done( [ 'result' => 'sequential_hit', 'order_id' => $order->get_id() ] );
            return [ 'order' => $order, 'source' => 'sequential_plugin', 'cached' => false ];
        }
        
        // Step 4: Fallback to direct ID lookup
        $order = $this->try_direct_id( $normalized, $input );
        
        if ( $order ) {
            $this->cache->set( $cache_key, $order->get_id() );
            $done( [ 'result' => 'direct_hit', 'order_id' => $order->get_id() ] );
            return [ 'order' => $order, 'source' => 'direct_id', 'cached' => false ];
        }
        
        // Step 5: Not found - cache the miss
        $this->cache->set( $cache_key, 0 ); // Cache "not found" as 0
        $done( [ 'result' => 'not_found' ] );
        return [ 'order' => null, 'source' => 'not_found', 'cached' => false ];
    }
    
    /**
     * Normalize user input to standard format.
     * 
     * @param string $input Raw input
     * @return array|null Normalized data or null if invalid
     */
    private function normalize( string $input ): ?array {
        $done = KISS_Woo_Debug_Tracer::start_timer( 'OrderResolver', 'normalize' );
        
        $term = trim( $input );
        $term = ltrim( $term, '#' );
        $term = strtoupper( $term );
        
        // Check for prefix (B, D, etc.)
        $prefix = '';
        $number = $term;
        
        foreach ( $this->allowed_prefixes as $p ) {
            if ( strpos( $term, $p ) === 0 ) {
                $prefix = $p;
                $number = substr( $term, strlen( $p ) );
                break;
            }
        }
        
        // Must be numeric after prefix removal
        if ( ! ctype_digit( $number ) || $number === '' ) {
            $done( [ 'valid' => false ] );
            return null;
        }
        
        $result = [
            'original'    => $input,
            'prefix'      => $prefix,
            'number'      => $number,
            'full_number' => $prefix . $number, // e.g., "B349445"
            'numeric_id'  => (int) $number,
            'cache_key'   => strtolower( $prefix . $number ), // Lowercase for consistent caching
        ];
        
        $done( [ 'valid' => true, 'parsed' => $result ] );
        return $result;
    }
    
    /**
     * Try to find order via Sequential Order Numbers Pro.
     * 
     * @param array $normalized Normalized input data
     * @return WC_Order|null
     */
    private function try_sequential_plugin( array $normalized ): ?WC_Order {
        if ( ! function_exists( 'wc_sequential_order_numbers' ) ) {
            KISS_Woo_Debug_Tracer::log( 'OrderResolver', 'sequential_skip', [
                'reason' => 'Plugin not active',
            ] );
            return null;
        }
        
        $done = KISS_Woo_Debug_Tracer::start_timer( 'OrderResolver', 'sequential_lookup' );
        
        // Try with full number (including prefix)
        $order_id = wc_sequential_order_numbers()->find_order_by_order_number( $normalized['full_number'] );
        
        if ( ! $order_id ) {
            // Try without prefix (some configurations)
            $order_id = wc_sequential_order_numbers()->find_order_by_order_number( $normalized['number'] );
        }
        
        if ( ! $order_id ) {
            $done( [ 'found' => false ] );
            KISS_Woo_Debug_Tracer::log( 'OrderResolver', 'sequential_miss', [
                'searched' => $normalized['full_number'],
            ] );
            return null;
        }
        
        $order = wc_get_order( $order_id );
        
        if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
            $done( [ 'found' => false, 'reason' => 'Invalid order object' ] );
            KISS_Woo_Debug_Tracer::log( 'OrderResolver', 'sequential_invalid_order', [
                'order_id' => $order_id,
            ], 'warn' );
            return null;
        }
        
        $done( [ 'found' => true, 'order_id' => $order_id ] );
        KISS_Woo_Debug_Tracer::log( 'OrderResolver', 'sequential_hit', [
            'searched' => $normalized['full_number'],
            'order_id' => $order_id,
        ] );
        
        return $order;
    }
    
    /**
     * Try direct ID lookup (fallback for non-sequential sites).
     * 
     * @param array  $normalized Normalized input data
     * @param string $original   Original user input (for validation)
     * @return WC_Order|null
     */
    private function try_direct_id( array $normalized, string $original ): ?WC_Order {
        $done = KISS_Woo_Debug_Tracer::start_timer( 'OrderResolver', 'direct_lookup' );
        
        $order = wc_get_order( $normalized['numeric_id'] );
        
        if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
            $done( [ 'found' => false ] );
            return null;
        }
        
        // Verify the order number matches what user searched
        // This prevents returning wrong order if user searched "B349445" 
        // but order 349445 displays as "D349445"
        $actual_number = $order->get_order_number();
        $expected_variants = [
            $normalized['full_number'],
            $normalized['number'],
            '#' . $normalized['full_number'],
            '#' . $normalized['number'],
        ];
        
        // Case-insensitive comparison
        $actual_upper = strtoupper( $actual_number );
        $match_found = false;
        
        foreach ( $expected_variants as $variant ) {
            if ( strtoupper( $variant ) === $actual_upper ) {
                $match_found = true;
                break;
            }
        }
        
        if ( ! $match_found ) {
            $done( [ 'found' => false, 'reason' => 'Number mismatch' ] );
            KISS_Woo_Debug_Tracer::log( 'OrderResolver', 'direct_mismatch', [
                'searched' => $normalized['full_number'],
                'actual' => $actual_number,
            ] );
            return null;
        }
        
        $done( [ 'found' => true, 'order_id' => $order->get_id() ] );
        return $order;
    }
    
    /**
     * Check if input looks like an order number.
     * 
     * Use this to skip order search for obvious non-order terms like "john smith".
     * 
     * @param string $input Raw user input
     * @return bool
     */
    public function looks_like_order_number( string $input ): bool {
        $term = trim( $input );
        $term = ltrim( $term, '#' );
        
        // Build pattern dynamically from allowed prefixes
        $prefixes = implode( '', $this->allowed_prefixes );
        $pattern = '/^[' . preg_quote( $prefixes, '/' ) . ']?\d+$/i';
        
        return (bool) preg_match( $pattern, $term );
    }
}
```

---

### 3. Order Formatter (Centralized Output)

**File:** `includes/class-kiss-woo-order-formatter.php`

```php
<?php
/**
 * Centralized order formatting for API responses.
 * 
 * SINGLE WRITE PATH: All order-to-array conversion goes through format().
 */
class KISS_Woo_Order_Formatter {
    
    /**
     * Format an order for JSON/API output.
     * 
     * @param WC_Order $order
     * @return array
     */
    public static function format( WC_Order $order ): array {
        $order_id = $order->get_id();
        
        // HPOS-compatible edit URL
        $edit_url = self::get_edit_url( $order_id );
        
        return [
            'id'            => $order_id,
            'order_number'  => $order->get_order_number(),
            'status'        => $order->get_status(),
            'status_label'  => wc_get_order_status_name( $order->get_status() ),
            'total'         => $order->get_total(),
            'total_display' => wp_strip_all_tags( $order->get_formatted_order_total() ),
            'currency'      => $order->get_currency(),
            'date_created'  => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : null,
            'date_display'  => $order->get_date_created() ? $order->get_date_created()->format( get_option( 'date_format' ) ) : '',
            'customer'      => [
                'name'  => self::get_customer_name( $order ),
                'email' => $order->get_billing_email(),
            ],
            'view_url'      => esc_url( $edit_url ),
        ];
    }
    
    /**
     * Get HPOS-compatible edit URL.
     * 
     * @param int $order_id
     * @return string
     */
    private static function get_edit_url( int $order_id ): string {
        // Try WooCommerce's method first (HPOS-aware)
        $edit_url = get_edit_post_link( $order_id, 'raw' );
        
        if ( empty( $edit_url ) ) {
            // Fallback for HPOS or edge cases
            $edit_url = admin_url( 'post.php?post=' . $order_id . '&action=edit' );
        }
        
        return $edit_url;
    }
    
    /**
     * Get formatted customer name.
     * 
     * @param WC_Order $order
     * @return string
     */
    private static function get_customer_name( WC_Order $order ): string {
        $first = $order->get_billing_first_name();
        $last = $order->get_billing_last_name();
        
        $name = trim( $first . ' ' . $last );
        
        if ( empty( $name ) ) {
            $name = __( 'Guest', 'kiss-woo-customer-order-search' );
        }
        
        return $name;
    }
    
    /**
     * Format multiple orders.
     * 
     * @param WC_Order[] $orders
     * @return array
     */
    public static function format_many( array $orders ): array {
        return array_map( [ self::class, 'format' ], $orders );
    }
}
```

---

### 4. AJAX Handler (Refactored)

**File:** `includes/class-kiss-woo-ajax-handler.php`

```php
<?php
/**
 * Centralized AJAX handling.
 * 
 * SINGLE WRITE PATH: All AJAX responses go through send_response().
 */
class KISS_Woo_AJAX_Handler {
    
    /** @var KISS_Woo_Search */
    private $search;
    
    /** @var KISS_Woo_Order_Resolver */
    private $order_resolver;
    
    /**
     * Constructor.
     */
    public function __construct( 
        KISS_Woo_Search $search, 
        KISS_Woo_Order_Resolver $order_resolver 
    ) {
        $this->search = $search;
        $this->order_resolver = $order_resolver;
    }
    
    /**
     * Register AJAX hooks.
     */
    public function register(): void {
        add_action( 'wp_ajax_kiss_woo_customer_search', [ $this, 'handle_search' ] );
    }
    
    /**
     * Handle search AJAX request.
     */
    public function handle_search(): void {
        $request_start = microtime( true );
        
        KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'request_start', [
            'action' => 'kiss_woo_customer_search',
        ] );
        
        // Security checks
        if ( ! $this->verify_request() ) {
            return; // verify_request sends error response
        }
        
        // Get and sanitize input
        $term = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
        
        if ( empty( $term ) ) {
            $this->send_response( false, [ 'message' => __( 'Search term required.', 'kiss-woo-customer-order-search' ) ] );
            return;
        }
        
        KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'search_term', [
            'term' => $term,
            'length' => strlen( $term ),
        ] );
        
        // Perform searches
        $results = $this->perform_search( $term );
        
        // Calculate timing
        $elapsed = round( ( microtime( true ) - $request_start ) * 1000, 2 );
        
        KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'request_complete', [
            'elapsed_ms' => $elapsed,
            'customer_count' => count( $results['customers'] ),
            'guest_count' => count( $results['guest_orders'] ),
            'order_count' => count( $results['orders'] ),
        ] );
        
        $this->send_response( true, array_merge( $results, [
            'search_time_ms' => $elapsed,
            'debug' => $this->get_debug_data(),
        ] ) );
    }
    
    /**
     * Verify request security.
     * 
     * @return bool
     */
    private function verify_request(): bool {
        // Capability check
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'auth_failed', [
                'user_id' => get_current_user_id(),
            ], 'warn' );
            $this->send_response( false, [ 'message' => __( 'Permission denied.', 'kiss-woo-customer-order-search' ) ], 403 );
            return false;
        }
        
        // Nonce check
        if ( ! check_ajax_referer( 'kiss_woo_cos_search', 'nonce', false ) ) {
            KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'nonce_failed', [], 'warn' );
            $this->send_response( false, [ 'message' => __( 'Invalid security token.', 'kiss-woo-customer-order-search' ) ], 403 );
            return false;
        }
        
        return true;
    }
    
    /**
     * Perform all searches.
     * 
     * @param string $term
     * @return array
     */
    private function perform_search( string $term ): array {
        $results = [
            'customers'                => [],
            'guest_orders'             => [],
            'orders'                   => [],
            'should_redirect_to_order' => false,
            'redirect_url'             => null,
        ];
        
        // Customer search (existing functionality)
        $done = KISS_Woo_Debug_Tracer::start_timer( 'AjaxHandler', 'customer_search' );
        $results['customers'] = $this->search->search_customers( $term );
        $done( [ 'count' => count( $results['customers'] ) ] );
        
        // Guest order search (existing functionality)
        $done = KISS_Woo_Debug_Tracer::start_timer( 'AjaxHandler', 'guest_search' );
        $results['guest_orders'] = $this->search->search_guest_orders_by_email( $term );
        $done( [ 'count' => count( $results['guest_orders'] ) ] );
        
        // Order number search (only if term looks like an order number)
        if ( $this->order_resolver->looks_like_order_number( $term ) ) {
            $done = KISS_Woo_Debug_Tracer::start_timer( 'AjaxHandler', 'order_search' );
            
            $resolution = $this->order_resolver->resolve( $term );
            
            if ( $resolution['order'] ) {
                $formatted = KISS_Woo_Order_Formatter::format( $resolution['order'] );
                $results['orders'] = [ $formatted ];
                
                // Set redirect flag for exact match
                $results['should_redirect_to_order'] = true;
                $results['redirect_url'] = $formatted['view_url'];
                
                $done( [ 'found' => true, 'order_id' => $formatted['id'] ] );
            } else {
                $done( [ 'found' => false, 'source' => $resolution['source'] ] );
            }
        } else {
            KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'order_search_skipped', [
                'reason' => 'Term does not look like order number',
                'term' => $term,
            ] );
        }
        
        return $results;
    }
    
    /**
     * Get debug data for response (only if debug enabled).
     * 
     * @return array|null
     */
    private function get_debug_data(): ?array {
        if ( ! defined( 'KISS_WOO_DEBUG' ) || ! KISS_WOO_DEBUG ) {
            return null;
        }
        
        return [
            'traces' => KISS_Woo_Debug_Tracer::get_traces(),
            'memory_peak_mb' => round( memory_get_peak_usage() / 1024 / 1024, 2 ),
            'php_version' => PHP_VERSION,
            'wc_version' => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
        ];
    }
    
    /**
     * Send JSON response.
     * 
     * SINGLE WRITE PATH: All AJAX responses go through this method.
     * 
     * @param bool  $success
     * @param array $data
     * @param int   $status_code
     */
    private function send_response( bool $success, array $data, int $status_code = 200 ): void {
        if ( $success ) {
            wp_send_json_success( $data, $status_code );
        } else {
            wp_send_json_error( $data, $status_code );
        }
    }
}
```

---

### 5. Debug Panel UI

**File:** `admin/class-kiss-woo-debug-panel.php`

```php
<?php
/**
 * Debug panel admin page.
 */
class KISS_Woo_Debug_Panel {
    
    /**
     * Register admin page.
     */
    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
        add_action( 'wp_ajax_kiss_woo_debug_get_traces', [ $this, 'ajax_get_traces' ] );
        add_action( 'wp_ajax_kiss_woo_debug_clear_traces', [ $this, 'ajax_clear_traces' ] );
    }
    
    /**
     * Add submenu page under WooCommerce.
     */
    public function add_menu_page(): void {
        // Only show if debug mode is enabled
        if ( ! defined( 'KISS_WOO_DEBUG' ) || ! KISS_WOO_DEBUG ) {
            return;
        }
        
        add_submenu_page(
            'woocommerce',
            __( 'KISS Search Debug', 'kiss-woo-customer-order-search' ),
            __( 'Search Debug', 'kiss-woo-customer-order-search' ),
            'manage_options',
            'kiss-woo-debug',
            [ $this, 'render_page' ]
        );
    }
    
    /**
     * Render debug panel page.
     */
    public function render_page(): void {
        ?>
        <div class="wrap kiss-woo-debug-panel">
            <h1><?php esc_html_e( 'KISS Search Debug Panel', 'kiss-woo-customer-order-search' ); ?></h1>
            
            <div class="kiss-debug-controls">
                <button type="button" class="button button-primary" id="kiss-debug-refresh">
                    <?php esc_html_e( 'Refresh', 'kiss-woo-customer-order-search' ); ?>
                </button>
                <button type="button" class="button" id="kiss-debug-clear">
                    <?php esc_html_e( 'Clear History', 'kiss-woo-customer-order-search' ); ?>
                </button>
                <label>
                    <input type="checkbox" id="kiss-debug-auto-refresh" />
                    <?php esc_html_e( 'Auto-refresh (5s)', 'kiss-woo-customer-order-search' ); ?>
                </label>
            </div>
            
            <div class="kiss-debug-status">
                <h3><?php esc_html_e( 'System Status', 'kiss-woo-customer-order-search' ); ?></h3>
                <table class="widefat">
                    <tr>
                        <th><?php esc_html_e( 'Sequential Order Numbers Pro', 'kiss-woo-customer-order-search' ); ?></th>
                        <td>
                            <?php if ( function_exists( 'wc_sequential_order_numbers' ) ) : ?>
                                <span class="kiss-status-ok">✓ <?php esc_html_e( 'Active', 'kiss-woo-customer-order-search' ); ?></span>
                            <?php else : ?>
                                <span class="kiss-status-warn">○ <?php esc_html_e( 'Not Active', 'kiss-woo-customer-order-search' ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'HPOS Enabled', 'kiss-woo-customer-order-search' ); ?></th>
                        <td>
                            <?php 
                            $hpos_enabled = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) 
                                && Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
                            ?>
                            <?php if ( $hpos_enabled ) : ?>
                                <span class="kiss-status-ok">✓ <?php esc_html_e( 'Enabled', 'kiss-woo-customer-order-search' ); ?></span>
                            <?php else : ?>
                                <span class="kiss-status-info">○ <?php esc_html_e( 'Legacy Mode', 'kiss-woo-customer-order-search' ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Cache Status', 'kiss-woo-customer-order-search' ); ?></th>
                        <td>
                            <?php esc_html_e( 'Active (5 min TTL)', 'kiss-woo-customer-order-search' ); ?>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="kiss-debug-traces">
                <h3><?php esc_html_e( 'Request History', 'kiss-woo-customer-order-search' ); ?></h3>
                <div id="kiss-debug-traces-container">
                    <p class="loading"><?php esc_html_e( 'Loading...', 'kiss-woo-customer-order-search' ); ?></p>
                </div>
            </div>
        </div>
        
        <style>
            .kiss-woo-debug-panel { max-width: 1200px; }
            .kiss-debug-controls { margin: 20px 0; display: flex; gap: 10px; align-items: center; }
            .kiss-debug-status { margin: 20px 0; }
            .kiss-debug-status table { max-width: 500px; }
            .kiss-status-ok { color: #46b450; }
            .kiss-status-warn { color: #ffb900; }
            .kiss-status-info { color: #00a0d2; }
            .kiss-debug-traces { margin: 20px 0; }
            .kiss-debug-request { 
                background: #fff; 
                border: 1px solid #ccd0d4; 
                margin-bottom: 10px; 
                border-radius: 4px;
            }
            .kiss-debug-request-header {
                padding: 10px 15px;
                background: #f8f9fa;
                border-bottom: 1px solid #ccd0d4;
                cursor: pointer;
                display: flex;
                justify-content: space-between;
            }
            .kiss-debug-request-header:hover { background: #f0f0f1; }
            .kiss-debug-request-body { padding: 15px; display: none; }
            .kiss-debug-request.expanded .kiss-debug-request-body { display: block; }
            .kiss-trace-item { 
                font-family: monospace; 
                font-size: 12px; 
                padding: 5px 10px;
                border-left: 3px solid #ccc;
                margin: 5px 0;
            }
            .kiss-trace-item.level-info { border-color: #00a0d2; }
            .kiss-trace-item.level-warn { border-color: #ffb900; }
            .kiss-trace-item.level-error { border-color: #dc3232; }
            .kiss-trace-time { color: #666; }
            .kiss-trace-component { color: #0073aa; font-weight: bold; }
            .kiss-trace-action { color: #23282d; }
            .kiss-trace-context { color: #666; margin-left: 20px; }
        </style>
        
        <script>
        jQuery(function($) {
            var autoRefreshInterval = null;
            
            function loadTraces() {
                $.post(ajaxurl, {
                    action: 'kiss_woo_debug_get_traces',
                    nonce: '<?php echo wp_create_nonce( 'kiss_woo_debug' ); ?>'
                }, function(response) {
                    if (response.success) {
                        renderTraces(response.data);
                    }
                });
            }
            
            function renderTraces(history) {
                var $container = $('#kiss-debug-traces-container');
                
                if (!history || Object.keys(history).length === 0) {
                    $container.html('<p><?php esc_html_e( 'No trace history. Perform a search to see traces.', 'kiss-woo-customer-order-search' ); ?></p>');
                    return;
                }
                
                var html = '';
                
                // Reverse to show newest first
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
            
            // Click to expand/collapse
            $(document).on('click', '.kiss-debug-request-header', function() {
                $(this).closest('.kiss-debug-request').toggleClass('expanded');
            });
            
            // Refresh button
            $('#kiss-debug-refresh').on('click', loadTraces);
            
            // Clear button
            $('#kiss-debug-clear').on('click', function() {
                $.post(ajaxurl, {
                    action: 'kiss_woo_debug_clear_traces',
                    nonce: '<?php echo wp_create_nonce( 'kiss_woo_debug' ); ?>'
                }, function() {
                    loadTraces();
                });
            });
            
            // Auto-refresh toggle
            $('#kiss-debug-auto-refresh').on('change', function() {
                if ($(this).is(':checked')) {
                    autoRefreshInterval = setInterval(loadTraces, 5000);
                } else {
                    clearInterval(autoRefreshInterval);
                }
            });
            
            // Initial load
            loadTraces();
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler to get traces.
     */
    public function ajax_get_traces(): void {
        check_ajax_referer( 'kiss_woo_debug', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        
        wp_send_json_success( KISS_Woo_Debug_Tracer::get_history() );
    }
    
    /**
     * AJAX handler to clear traces.
     */
    public function ajax_clear_traces(): void {
        check_ajax_referer( 'kiss_woo_debug', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        
        KISS_Woo_Debug_Tracer::clear_history();
        wp_send_json_success();
    }
}
```

---

## JavaScript Updates

**File:** `admin/kiss-woo-admin.js`

Add to the AJAX success handler:

```javascript
.done(function(resp) {
    // Log debug data to console if present
    if (resp.data.debug) {
        console.group('KISS Search Debug');
        console.log('Search time:', resp.data.search_time_ms + 'ms');
        console.log('Traces:', resp.data.debug.traces);
        console.log('Memory peak:', resp.data.debug.memory_peak_mb + 'MB');
        console.groupEnd();
    }
    
    // Handle direct order redirect
    if (resp.data.should_redirect_to_order && resp.data.redirect_url) {
        window.location.href = resp.data.redirect_url;
        return;
    }
    
    // Render results (existing logic)
    renderResults(resp.data);
});
```

---

## Implementation Order

Build in this exact sequence to ensure observability from the start:

### Phase 1: Foundation (Day 1)

1. **Debug Tracer** - Build first so all subsequent code is observable
2. **Order Formatter** - Simple, no dependencies
3. **Order Resolver** - Core logic with tracer integration

### Phase 2: Integration (Day 1-2)

4. **AJAX Handler** - Wire up resolver and formatter
5. **Debug Panel** - UI for observability

### Phase 3: Frontend (Day 2)

6. **JavaScript updates** - Redirect logic and console logging
7. **Toolbar updates** - Placeholder text

### Phase 4: Testing (Day 2)

8. **Manual testing** with debug panel
9. **Verify Sequential plugin integration**
10. **Performance validation**

---

## Testing Checklist

### Debug Panel Verification

- [ ] Panel appears under WooCommerce menu when `KISS_WOO_DEBUG` is true
- [ ] Panel hidden when debug mode is off
- [ ] Traces appear after performing a search
- [ ] Each request shows timing breakdown
- [ ] Clear history button works
- [ ] Auto-refresh toggles correctly

### Order Search Verification

**With Sequential Order Numbers Pro:**
- [ ] `B349445` finds correct order (not order ID 349445)
- [ ] `b349445` (lowercase) works
- [ ] `#B349445` works
- [ ] Debug trace shows "sequential_hit" source

**Without Sequential Plugin:**
- [ ] `12345` finds order ID 12345
- [ ] `B12345` finds order only if display number matches
- [ ] Debug trace shows "direct_id" source

**Cache Verification:**
- [ ] First lookup shows ~50-100ms in traces
- [ ] Second lookup shows ~1-5ms (cache hit)
- [ ] Debug trace shows "cache_hit" on second lookup

### Edge Cases

- [ ] Non-existent order number shows "not_found" in traces
- [ ] "john smith" skips order search (trace shows "order_search_skipped")
- [ ] Empty search term returns error
- [ ] Unauthorized user gets 403

---

## Configuration

### Enable Debug Mode

Add to `wp-config.php`:

```php
define( 'KISS_WOO_DEBUG', true );
```

Or via filter in theme/plugin:

```php
add_filter( 'kiss_woo_debug_enabled', '__return_true' );
```

### Custom Prefixes

```php
add_filter( 'kiss_woo_order_search_prefixes', function( $prefixes ) {
    return [ 'B', 'D', 'W', 'R' ]; // Add Wholesale, Retail prefixes
} );
```

---

## Performance Expectations

| Scenario | Expected Time | Debug Trace Shows |
|----------|---------------|-------------------|
| Cached hit | < 5ms | `cache_hit` |
| Sequential plugin lookup | 50-100ms | `sequential_hit` |
| Direct ID lookup | 10-20ms | `direct_hit` |
| Not found (cached) | < 5ms | `cache_hit` with `cached_id: 0` |
| Not found (first) | 50-100ms | `not_found` |
| Non-order term | 0ms (skipped) | `order_search_skipped` |

---

## Summary

This revised plan:

1. **Supports Sequential Order Numbers Pro** via official helper method
2. **Single write paths** - each operation has exactly one responsible method
3. **Centralized helpers** - DRY code with no duplication
4. **Observable by default** - debug tracer captures every code path
5. **Debug panel** - visual UI to see exactly what's happening
6. **Console logging** - JS debug output for frontend issues

The debug panel is built FIRST so you can see exactly where things fail as you build the rest. No more guessing why searches don't work.