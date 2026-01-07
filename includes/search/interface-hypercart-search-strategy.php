<?php
/**
 * Search Strategy Interface
 *
 * Defines the contract for all search strategies.
 * Each strategy implements a different search method (customer lookup, user query, guest orders).
 *
 * @package Hypercart_Woo_Fast_Search
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Hypercart_Search_Strategy {

	/**
	 * Execute search with normalized term
	 *
	 * @param array $normalized Normalized term from Hypercart_Search_Term_Normalizer
	 * @param int   $limit Maximum number of results to return
	 * @return array Array of user IDs or order IDs
	 */
	public function search( $normalized, $limit = 20 );

	/**
	 * Check if this strategy is available
	 *
	 * Example: Customer lookup requires wc_customer_lookup table
	 *
	 * @return bool True if strategy can be used
	 */
	public function is_available();

	/**
	 * Get strategy priority
	 *
	 * Higher priority strategies are tried first.
	 * Example: Customer lookup (100) > WP_User_Query (50)
	 *
	 * @return int Priority (higher = try first)
	 */
	public function get_priority();

	/**
	 * Get strategy name for debugging
	 *
	 * @return string Strategy name
	 */
	public function get_name();
}

