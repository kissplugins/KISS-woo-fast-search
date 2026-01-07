<?php
/**
 * WP User Query Strategy
 *
 * Fallback search using WP_User_Query with meta_query.
 * Used when wc_customer_lookup table is not available.
 *
 * CRITICAL FIX: This now properly handles name splitting!
 * Previous bug: "John Smith" was searched as single string in meta_query
 * Fixed: Now splits into first_name AND last_name queries
 *
 * @package Hypercart_Woo_Fast_Search
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hypercart_WP_User_Query_Strategy implements Hypercart_Search_Strategy {

	/**
	 * Execute search
	 *
	 * @param array $normalized Normalized term
	 * @param int   $limit Maximum results
	 * @return array User IDs
	 */
	public function search( $normalized, $limit = 20 ) {
		// Full name search (first + last) - THE FIX!
		if ( isset( $normalized['name_parts']['first'] ) && isset( $normalized['name_parts']['last'] ) ) {
			return $this->search_by_name_pair( $normalized['name_parts'], $limit );
		}

		// Single term search
		return $this->search_by_single_term( $normalized['sanitized'], $limit );
	}

	/**
	 * Search by first + last name
	 *
	 * CRITICAL FIX: Properly splits "John Smith" into separate meta queries
	 *
	 * @param array $name_parts Name parts with 'first' and 'last' keys
	 * @param int   $limit Maximum results
	 * @return array User IDs
	 */
	protected function search_by_name_pair( $name_parts, $limit ) {
		$args = array(
			'number'      => $limit,
			'count_total' => false,
			'fields'      => array( 'ID' ),
			'meta_query'  => array(
				'relation' => 'AND',
				array(
					'key'     => 'billing_first_name',
					'value'   => $name_parts['first'],
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'billing_last_name',
					'value'   => $name_parts['last'],
					'compare' => 'LIKE',
				),
			),
		);

		$user_query = new WP_User_Query( $args );
		$users      = $user_query->get_results();

		return wp_list_pluck( $users, 'ID' );
	}

	/**
	 * Search by single term (email, first name, or last name)
	 *
	 * @param string $term Sanitized search term
	 * @param int    $limit Maximum results
	 * @return array User IDs
	 */
	protected function search_by_single_term( $term, $limit ) {
		$args = array(
			'search'         => '*' . esc_attr( $term ) . '*',
			'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
			'number'         => $limit,
			'count_total'    => false,
			'fields'         => array( 'ID' ),
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => 'billing_first_name',
					'value'   => $term,
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'billing_last_name',
					'value'   => $term,
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'billing_email',
					'value'   => $term,
					'compare' => 'LIKE',
				),
			),
		);

		$user_query = new WP_User_Query( $args );
		$users      = $user_query->get_results();

		return wp_list_pluck( $users, 'ID' );
	}

	/**
	 * Check if strategy is available
	 *
	 * @return bool Always true (WP_User_Query is always available)
	 */
	public function is_available() {
		return true;
	}

	/**
	 * Get strategy priority
	 *
	 * @return int Priority (50 = medium, fallback to customer_lookup)
	 */
	public function get_priority() {
		return 50; // Lower than customer_lookup (100)
	}

	/**
	 * Get strategy name
	 *
	 * @return string Strategy name
	 */
	public function get_name() {
		return 'wp_user_query';
	}
}

