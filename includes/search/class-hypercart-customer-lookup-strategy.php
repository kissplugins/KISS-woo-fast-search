<?php
/**
 * Customer Lookup Strategy
 *
 * Searches using WooCommerce's wc_customer_lookup table.
 * This is the FASTEST strategy - uses indexed columns.
 *
 * Handles:
 * - Email search (exact and partial)
 * - Name search (first, last, or both)
 * - Username search
 *
 * @package Hypercart_Woo_Fast_Search
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hypercart_Customer_Lookup_Strategy implements Hypercart_Search_Strategy {

	/**
	 * Debug info from last search
	 *
	 * @var array
	 */
	protected $last_debug = array();

	/**
	 * Execute search
	 *
	 * @param array $normalized Normalized term
	 * @param int   $limit Maximum results
	 * @return array User IDs
	 */
	public function search( $normalized, $limit = 20 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wc_customer_lookup';
		$limit = (int) $limit;

		$this->last_debug = array(
			'enabled' => true,
			'mode'    => null,
			'table'   => $table,
			'hit'     => false,
			'count'   => 0,
		);

		// Full name search (first + last)
		if ( isset( $normalized['name_parts']['first'] ) && isset( $normalized['name_parts']['last'] ) ) {
			return $this->search_by_name_pair( $normalized['name_parts'], $limit );
		}

		// Single term search (email, first name, last name, or username)
		return $this->search_by_single_term( $normalized['sanitized'], $normalized['is_partial_email'], $limit );
	}

	/**
	 * Search by first + last name
	 *
	 * @param array $name_parts Name parts with 'first' and 'last' keys
	 * @param int   $limit Maximum results
	 * @return array User IDs
	 */
	protected function search_by_name_pair( $name_parts, $limit ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wc_customer_lookup';

		$this->last_debug['mode'] = 'name_pair_prefix';

		$first_prefix = $wpdb->esc_like( $name_parts['first'] ) . '%';
		$last_prefix  = $wpdb->esc_like( $name_parts['last'] ) . '%';

		// Search both "first last" AND "last first" (handles reversed input)
		$sql = $wpdb->prepare(
			"SELECT user_id
			 FROM {$table}
			 WHERE user_id > 0
			 AND ((first_name LIKE %s AND last_name LIKE %s) OR (first_name LIKE %s AND last_name LIKE %s))
			 ORDER BY date_registered DESC
			 LIMIT %d",
			$first_prefix,
			$last_prefix,
			$last_prefix,
			$first_prefix,
			$limit
		);

		$ids = $wpdb->get_col( $sql );

		return $this->process_results( $ids );
	}

	/**
	 * Search by single term (email, name, or username)
	 *
	 * @param string $term Sanitized search term
	 * @param bool   $is_email_ish Whether term looks like email
	 * @param int    $limit Maximum results
	 * @return array User IDs
	 */
	protected function search_by_single_term( $term, $is_email_ish, $limit ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wc_customer_lookup';

		$this->last_debug['mode'] = 'prefix_multi_column';

		$prefix = $wpdb->esc_like( $term ) . '%';

		// Prefix search across all indexed columns
		$sql = $wpdb->prepare(
			"SELECT user_id
			 FROM {$table}
			 WHERE user_id > 0
			 AND (email LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR username LIKE %s)
			 ORDER BY date_registered DESC
			 LIMIT %d",
			$prefix,
			$prefix,
			$prefix,
			$prefix,
			$limit
		);

		$ids = $wpdb->get_col( $sql );

		// Email fallback: if looks like email and prefix found nothing, try contains
		if ( empty( $ids ) && $is_email_ish && strlen( $term ) >= 3 ) {
			$this->last_debug['mode'] = 'contains_email_fallback';

			$contains = '%' . $wpdb->esc_like( $term ) . '%';

			$sql = $wpdb->prepare(
				"SELECT user_id
				 FROM {$table}
				 WHERE user_id > 0
				 AND email LIKE %s
				 ORDER BY date_registered DESC
				 LIMIT %d",
				$contains,
				$limit
			);

			$ids = $wpdb->get_col( $sql );
		}

		return $this->process_results( $ids );
	}

	/**
	 * Process and validate results
	 *
	 * @param array $ids Raw user IDs from database
	 * @return array Validated user IDs
	 */
	protected function process_results( $ids ) {
		$ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );

		if ( empty( $ids ) ) {
			$this->last_debug['hit']   = false;
			$this->last_debug['count'] = 0;
			return array();
		}

		$this->last_debug['hit']   = true;
		$this->last_debug['count'] = count( $ids );

		return $ids;
	}

	/**
	 * Check if strategy is available
	 *
	 * @return bool True if wc_customer_lookup table exists
	 */
	public function is_available() {
		global $wpdb;

		$table  = $wpdb->prefix . 'wc_customer_lookup';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		return $exists === $table;
	}

	/**
	 * Get strategy priority
	 *
	 * @return int Priority (100 = highest, try first)
	 */
	public function get_priority() {
		return 100; // Highest priority - fastest strategy
	}

	/**
	 * Get strategy name
	 *
	 * @return string Strategy name
	 */
	public function get_name() {
		return 'customer_lookup';
	}

	/**
	 * Get debug info from last search
	 *
	 * @return array Debug info
	 */
	public function get_last_debug() {
		return $this->last_debug;
	}
}

