<?php
/**
 * Search Cache
 *
 * Caches search results to avoid re-fetching same data.
 * Uses WordPress transients with automatic expiration.
 *
 * @package Hypercart_Woo_Fast_Search
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hypercart_Search_Cache {

	/**
	 * Cache prefix
	 *
	 * @var string
	 */
	protected $prefix = 'hypercart_search_';

	/**
	 * Cache TTL in seconds (default: 5 minutes)
	 *
	 * @var int
	 */
	protected $ttl;

	/**
	 * Cache enabled flag
	 *
	 * @var bool
	 */
	protected $enabled;

	/**
	 * Constructor
	 *
	 * @param int  $ttl Cache TTL in seconds (default: 300 = 5 minutes)
	 * @param bool $enabled Whether cache is enabled (default: true)
	 */
	public function __construct( $ttl = 300, $enabled = true ) {
		$this->ttl     = (int) $ttl;
		$this->enabled = (bool) $enabled;
	}

	/**
	 * Get cached result
	 *
	 * @param string $key Cache key
	 * @return mixed|null Cached value or null if not found
	 */
	public function get( $key ) {
		if ( ! $this->enabled ) {
			return null;
		}

		$cache_key = $this->get_cache_key( $key );
		$value     = get_transient( $cache_key );

		return false === $value ? null : $value;
	}

	/**
	 * Set cached result
	 *
	 * @param string $key Cache key
	 * @param mixed  $value Value to cache
	 * @param int    $ttl Optional TTL override
	 * @return bool True on success
	 */
	public function set( $key, $value, $ttl = null ) {
		if ( ! $this->enabled ) {
			return false;
		}

		$cache_key = $this->get_cache_key( $key );
		$ttl       = null !== $ttl ? (int) $ttl : $this->ttl;

		return set_transient( $cache_key, $value, $ttl );
	}

	/**
	 * Delete cached result
	 *
	 * @param string $key Cache key
	 * @return bool True on success
	 */
	public function delete( $key ) {
		$cache_key = $this->get_cache_key( $key );
		return delete_transient( $cache_key );
	}

	/**
	 * Clear all cache
	 *
	 * @return void
	 */
	public function clear_all() {
		global $wpdb;

		// Delete all transients with our prefix
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				 WHERE option_name LIKE %s
				 OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . $this->prefix ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . $this->prefix ) . '%'
			)
		);
	}

	/**
	 * Generate cache key
	 *
	 * @param string $key User-provided key
	 * @return string Full cache key
	 */
	protected function get_cache_key( $key ) {
		return $this->prefix . md5( $key );
	}

	/**
	 * Generate cache key from search term
	 *
	 * @param string $term Search term
	 * @param string $type Search type (customers, guest_orders, etc.)
	 * @return string Cache key
	 */
	public function get_search_key( $term, $type = 'customers' ) {
		return sprintf( '%s:%s', $type, strtolower( trim( $term ) ) );
	}

	/**
	 * Check if cache is enabled
	 *
	 * @return bool True if enabled
	 */
	public function is_enabled() {
		return $this->enabled;
	}

	/**
	 * Enable cache
	 *
	 * @return void
	 */
	public function enable() {
		$this->enabled = true;
	}

	/**
	 * Disable cache
	 *
	 * @return void
	 */
	public function disable() {
		$this->enabled = false;
	}
}

