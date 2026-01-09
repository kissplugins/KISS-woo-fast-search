<?php
/**
 * Centralized order number resolution.
 *
 * SINGLE WRITE PATH: All order-by-number lookups go through resolve().
 * Never call wc_sequential_order_numbers() directly elsewhere.
 *
 * @package KISS_Woo_Customer_Order_Search
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KISS_Woo_Order_Resolver {

    /** @var KISS_Woo_Search_Cache */
    private $cache;

    /** @var array Allowed order number prefixes */
    private $allowed_prefixes;

    /**
     * Constructor.
     *
     * @param KISS_Woo_Search_Cache $cache Cache instance.
     */
    public function __construct( KISS_Woo_Search_Cache $cache ) {
        $this->cache            = $cache;
        $this->allowed_prefixes = apply_filters( 'kiss_woo_order_search_prefixes', array( 'B', 'D' ) );
    }

    /**
     * Resolve an order number to an order.
     *
     * This is the ONLY method external code should call for order number lookups.
     *
     * @param string $input Raw user input (e.g., "#B349445", "349445", "b349445").
     * @return array{order: WC_Order|null, source: string, cached: bool}
     */
    public function resolve( string $input ): array {
        $done = KISS_Woo_Debug_Tracer::start_timer( 'OrderResolver', 'resolve' );

        // Step 1: Normalize input.
        $normalized = $this->normalize( $input );

        if ( null === $normalized ) {
            KISS_Woo_Debug_Tracer::log( 'OrderResolver', 'invalid_input', array(
                'input'  => $input,
                'reason' => 'Does not match order number pattern',
            ) );
            $done( array( 'result' => 'invalid_input' ) );
            return array( 'order' => null, 'source' => 'invalid', 'cached' => false );
        }

        // Step 2: Check cache.
        $cache_key = $this->cache->get_search_key( $normalized['cache_key'], 'order' );
        $cached_id = $this->cache->get( $cache_key );

        if ( null !== $cached_id ) {
            KISS_Woo_Debug_Tracer::log( 'OrderResolver', 'cache_hit', array(
                'input'     => $input,
                'cached_id' => $cached_id,
            ) );

            if ( 0 === $cached_id ) {
                // Cached "not found" result.
                $done( array( 'result' => 'cached_miss' ) );
                return array( 'order' => null, 'source' => 'cache', 'cached' => true );
            }

            $order = wc_get_order( $cached_id );
            $done( array( 'result' => 'cached_hit', 'order_id' => $cached_id ) );
            return array( 'order' => $order, 'source' => 'cache', 'cached' => true );
        }

        // Step 3: Try Sequential Order Numbers Pro (if available).
        $order = $this->try_sequential_plugin( $normalized );

        if ( $order ) {
            $this->cache->set( $cache_key, $order->get_id() );
            $done( array( 'result' => 'sequential_hit', 'order_id' => $order->get_id() ) );
            return array( 'order' => $order, 'source' => 'sequential_plugin', 'cached' => false );
        }

        // Step 4: Fallback to direct ID lookup.
        $order = $this->try_direct_id( $normalized, $input );

        if ( $order ) {
            $this->cache->set( $cache_key, $order->get_id() );
            $done( array( 'result' => 'direct_hit', 'order_id' => $order->get_id() ) );
            return array( 'order' => $order, 'source' => 'direct_id', 'cached' => false );
        }

        // Step 5: Not found - cache the miss.
        $this->cache->set( $cache_key, 0 ); // Cache "not found" as 0.
        $done( array( 'result' => 'not_found' ) );
        return array( 'order' => null, 'source' => 'not_found', 'cached' => false );
    }

    /**
     * Normalize user input to standard format.
     *
     * @param string $input Raw input.
     * @return array|null Normalized data or null if invalid.
     */
    private function normalize( string $input ): ?array {
        $done = KISS_Woo_Debug_Tracer::start_timer( 'OrderResolver', 'normalize' );

        $term = trim( $input );
        $term = ltrim( $term, '#' );
        $term = strtoupper( $term );

        // Check for prefix (B, D, etc.).
        $prefix = '';
        $number = $term;

        foreach ( $this->allowed_prefixes as $p ) {
            if ( 0 === strpos( $term, $p ) ) {
                $prefix = $p;
                $number = substr( $term, strlen( $p ) );
                break;
            }
        }

        // Must be numeric after prefix removal.
        if ( ! ctype_digit( $number ) || '' === $number ) {
            $done( array( 'valid' => false ) );
            return null;
        }

        $result = array(
            'original'    => $input,
            'prefix'      => $prefix,
            'number'      => $number,
            'full_number' => $prefix . $number, // e.g., "B349445".
            'numeric_id'  => (int) $number,
            'cache_key'   => strtolower( $prefix . $number ), // Lowercase for consistent caching.
        );

        $done( array( 'valid' => true, 'parsed' => $result ) );
        return $result;
    }

    /**
     * Try to find order via Sequential Order Numbers Pro.
     *
     * @param array $normalized Normalized input data.
     * @return WC_Order|null
     */
    private function try_sequential_plugin( array $normalized ): ?WC_Order {
        // Check for SkyVerge Sequential Order Numbers Pro plugin.
        if ( ! function_exists( 'wc_seq_order_number_pro' ) ) {
            KISS_Woo_Debug_Tracer::log( 'OrderResolver', 'sequential_skip', array(
                'reason' => 'Plugin not active (wc_seq_order_number_pro not found)',
            ) );
            return null;
        }

        $done = KISS_Woo_Debug_Tracer::start_timer( 'OrderResolver', 'sequential_lookup' );

        $plugin = wc_seq_order_number_pro();

        // Try with full number (including prefix) - this is the formatted order number.
        $order_id = $plugin->find_order_by_order_number( $normalized['full_number'] );

        if ( ! $order_id ) {
            // Try without prefix (some configurations).
            $order_id = $plugin->find_order_by_order_number( $normalized['number'] );
        }

        if ( ! $order_id ) {
            $done( array( 'found' => false ) );
            KISS_Woo_Debug_Tracer::log( 'OrderResolver', 'sequential_miss', array(
                'searched' => $normalized['full_number'],
            ) );
            return null;
        }

        $order = wc_get_order( $order_id );

        if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
            $done( array( 'found' => false, 'reason' => 'Invalid order object' ) );
            KISS_Woo_Debug_Tracer::log( 'OrderResolver', 'sequential_invalid_order', array(
                'order_id' => $order_id,
            ), 'warn' );
            return null;
        }

        $done( array( 'found' => true, 'order_id' => $order_id ) );
        KISS_Woo_Debug_Tracer::log( 'OrderResolver', 'sequential_hit', array(
            'searched' => $normalized['full_number'],
            'order_id' => $order_id,
        ) );

        return $order;
    }

    /**
     * Try direct ID lookup (fallback for non-sequential sites).
     *
     * @param array  $normalized Normalized input data.
     * @param string $original   Original user input (for validation).
     * @return WC_Order|null
     */
    private function try_direct_id( array $normalized, string $original ): ?WC_Order {
        $done = KISS_Woo_Debug_Tracer::start_timer( 'OrderResolver', 'direct_lookup' );

        $order = wc_get_order( $normalized['numeric_id'] );

        if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
            $done( array( 'found' => false ) );
            return null;
        }

        // Verify the order number matches what user searched.
        // This prevents returning wrong order if user searched "B349445"
        // but order 349445 displays as "D349445".
        $actual_number    = $order->get_order_number();
        $expected_variants = array(
            $normalized['full_number'],
            $normalized['number'],
            '#' . $normalized['full_number'],
            '#' . $normalized['number'],
        );

        // Case-insensitive comparison.
        $actual_upper = strtoupper( $actual_number );
        $match_found  = false;

        foreach ( $expected_variants as $variant ) {
            if ( strtoupper( $variant ) === $actual_upper ) {
                $match_found = true;
                break;
            }
        }

        if ( ! $match_found ) {
            $done( array( 'found' => false, 'reason' => 'Number mismatch' ) );
            KISS_Woo_Debug_Tracer::log( 'OrderResolver', 'direct_mismatch', array(
                'searched' => $normalized['full_number'],
                'actual'   => $actual_number,
            ) );
            return null;
        }

        $done( array( 'found' => true, 'order_id' => $order->get_id() ) );
        return $order;
    }

    /**
     * Check if input looks like an order number.
     *
     * Use this to skip order search for obvious non-order terms like "john smith".
     *
     * @param string $input Raw user input.
     * @return bool
     */
    public function looks_like_order_number( string $input ): bool {
        $term = trim( $input );
        $term = ltrim( $term, '#' );

        // Build pattern dynamically from allowed prefixes.
        $prefixes = implode( '', $this->allowed_prefixes );
        $pattern  = '/^[' . preg_quote( $prefixes, '/' ) . ']?\d+$/i';

        return (bool) preg_match( $pattern, $term );
    }
}

