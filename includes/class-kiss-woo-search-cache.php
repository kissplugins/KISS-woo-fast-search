<?php
/**
 * Centralized search result caching.
 *
 * SINGLE WRITE PATH: All cache operations go through this class.
 *
 * @package KISS_Woo_Customer_Order_Search
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KISS_Woo_Search_Cache {

    /** @var int Cache TTL in seconds (5 minutes default). */
    private $ttl;

    /** @var string Cache key prefix. */
    private const PREFIX = 'kiss_woo_';

    /**
     * Constructor.
     *
     * @param int $ttl Cache TTL in seconds.
     */
    public function __construct( int $ttl = 300 ) {
        $this->ttl = $ttl;
    }

    /**
     * Generate a cache key for a search.
     *
     * @param string $term Search term.
     * @param string $type Search type (e.g., 'order', 'customer').
     * @return string
     */
    public function get_search_key( string $term, string $type = 'order' ): string {
        return self::PREFIX . $type . '_' . md5( strtolower( trim( $term ) ) );
    }

    /**
     * Get a cached value.
     *
     * @param string $key Cache key.
     * @return mixed|null Cached value or null if not found.
     */
    public function get( string $key ) {
        $value = get_transient( $key );

        if ( false === $value ) {
            return null;
        }

        KISS_Woo_Debug_Tracer::log( 'SearchCache', 'get', array(
            'key'   => $key,
            'found' => true,
        ) );

        return $value;
    }

    /**
     * Set a cached value.
     *
     * @param string $key   Cache key.
     * @param mixed  $value Value to cache.
     * @param int    $ttl   Optional TTL override.
     * @return bool
     */
    public function set( string $key, $value, int $ttl = 0 ): bool {
        $ttl = $ttl > 0 ? $ttl : $this->ttl;

        KISS_Woo_Debug_Tracer::log( 'SearchCache', 'set', array(
            'key' => $key,
            'ttl' => $ttl,
        ) );

        return set_transient( $key, $value, $ttl );
    }

    /**
     * Delete a cached value.
     *
     * @param string $key Cache key.
     * @return bool
     */
    public function delete( string $key ): bool {
        KISS_Woo_Debug_Tracer::log( 'SearchCache', 'delete', array(
            'key' => $key,
        ) );

        return delete_transient( $key );
    }

    /**
     * Clear all plugin caches.
     *
     * @return int Number of keys deleted.
     */
    public function clear_all(): int {
        global $wpdb;

        KISS_Woo_Debug_Tracer::log( 'SearchCache', 'clear_all', array() );

        // Delete all transients with our prefix.
        $prefix   = '_transient_' . self::PREFIX;
        $deleted  = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $prefix . '%'
            )
        );

        // Also delete timeout entries.
        $timeout_prefix = '_transient_timeout_' . self::PREFIX;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $timeout_prefix . '%'
            )
        );

        return $deleted !== false ? (int) $deleted : 0;
    }

    /**
     * Get cache statistics.
     *
     * @return array
     */
    public function get_stats(): array {
        global $wpdb;

        $prefix = '_transient_' . self::PREFIX;
        $count  = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                $prefix . '%'
            )
        );

        return array(
            'count'  => (int) $count,
            'prefix' => self::PREFIX,
            'ttl'    => $this->ttl,
        );
    }
}

