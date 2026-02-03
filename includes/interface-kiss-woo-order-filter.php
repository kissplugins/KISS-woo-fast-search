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
 *
 * CONTRACT:
 * - Input: Structured hash with keys: 'customers', 'guest_orders', 'orders'
 * - Output: Structured hash with same keys (filtered)
 * - Filters receive and return the same structure for consistency
 */
interface KISS_Woo_Order_Filter {

	/**
	 * Apply filter to search results.
	 *
	 * @param array $results Structured hash with keys:
	 *                       - 'customers' (array): Customer objects with orders_list
	 *                       - 'guest_orders' (array): Guest order objects
	 *                       - 'orders' (array): Direct order matches (pass-through)
	 * @return array Filtered results with same structure.
	 */
	public function apply( array $results ): array;

	/**
	 * Get filter name for logging/debugging.
	 *
	 * @return string Filter name (e.g., 'wholesale', 'retail', 'b2b').
	 */
	public function get_filter_name(): string;
}

