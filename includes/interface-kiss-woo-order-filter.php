<?php
/**
 * Order Filter Interface
 *
 * Defines contract for order filtering implementations.
 * Follows SOLID principles: Dependency Inversion Principle (DIP).
 *
 * @package KISS_Woo_Customer_Order_Search
 * @since   1.2.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for order filters.
 *
 * Implementations can filter orders by wholesale status, retail status,
 * B2B status, or any other criteria.
 */
interface KISS_Woo_Order_Filter {

	/**
	 * Apply filter to search results.
	 *
	 * @param array $results Search results from KISS_Woo_Search::search_customers().
	 * @return array Filtered results.
	 */
	public function apply( array $results ): array;

	/**
	 * Get filter name for logging/debugging.
	 *
	 * @return string Filter name (e.g., 'wholesale', 'retail', 'b2b').
	 */
	public function get_filter_name(): string;
}

