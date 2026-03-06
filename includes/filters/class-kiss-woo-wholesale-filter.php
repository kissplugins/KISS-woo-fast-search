<?php
/**
 * Wholesale Order Filter
 *
 * Filters search results to show only wholesale orders.
 * Follows SOLID principles: Single Responsibility Principle (SRP).
 *
 * @package KISS_Woo_Customer_Order_Search
 * @since   1.2.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filter to show only wholesale orders.
 *
 * Detection logic:
 * 1. Check if customer has wholesale user role
 * 2. Check if order has wholesale meta keys
 * 3. Filter out non-wholesale orders
 */
class KISS_Woo_Wholesale_Filter implements KISS_Woo_Order_Filter {

	/**
	 * Known wholesale user roles.
	 *
	 * @var string[]
	 */
	private const WHOLESALE_ROLES = array(
		'wholesale_customer',
		'wholesale_lead',
		'wwpp_wholesale_customer',
		'wws_wholesale_customer',
	);

	/**
	 * Known wholesale order meta keys.
	 *
	 * @var string[]
	 */
	private const WHOLESALE_META_KEYS = array(
		'_wwpp_order_type',
		'_wholesale_order',
		'_is_wholesale_order',
		'_wwp_wholesale_order',
	);

	/**
	 * Apply wholesale filter to search results.
	 *
	 * @param array $results Search results from KISS_Woo_Search::search_customers().
	 * @return array Filtered results containing only wholesale orders.
	 */
	public function apply( array $results ): array {
		$done = KISS_Woo_Debug_Tracer::start_timer( 'WholesaleFilter', 'apply' );

		$filtered_customers  = array();
		$filtered_guest_orders = array();
		$total_orders_before = 0;
		$total_orders_after  = 0;

		// Filter customer results.
		if ( isset( $results['customers'] ) && is_array( $results['customers'] ) ) {
			foreach ( $results['customers'] as $customer ) {
				$customer_id = isset( $customer['id'] ) ? (int) $customer['id'] : 0;
				$orders_list = isset( $customer['orders_list'] ) ? $customer['orders_list'] : array();

				$total_orders_before += count( $orders_list );

				// Check if customer has wholesale role.
				$is_wholesale_customer = $this->is_wholesale_customer( $customer_id );

				// Filter orders to only wholesale orders.
				$wholesale_orders = array();
				foreach ( $orders_list as $order ) {
					$order_id = isset( $order['id'] ) ? (int) $order['id'] : 0;

					if ( $this->is_wholesale_order( $order_id, $customer_id, $is_wholesale_customer ) ) {
						$wholesale_orders[] = $order;
					}
				}

				$total_orders_after += count( $wholesale_orders );

				// Only include customer if they have wholesale orders.
				if ( ! empty( $wholesale_orders ) ) {
					$customer['orders_list'] = $wholesale_orders;
					$customer['orders']      = count( $wholesale_orders );
					$filtered_customers[]    = $customer;
				}
			}
		}

		// Filter guest orders.
		if ( isset( $results['guest_orders'] ) && is_array( $results['guest_orders'] ) ) {
			foreach ( $results['guest_orders'] as $order ) {
				$order_id = isset( $order['id'] ) ? (int) $order['id'] : 0;

				if ( $this->is_wholesale_order( $order_id, 0, false ) ) {
					$filtered_guest_orders[] = $order;
				}
			}
		}

		$done( array(
			'customers_before'     => isset( $results['customers'] ) ? count( $results['customers'] ) : 0,
			'customers_after'      => count( $filtered_customers ),
			'guest_orders_before'  => isset( $results['guest_orders'] ) ? count( $results['guest_orders'] ) : 0,
			'guest_orders_after'   => count( $filtered_guest_orders ),
			'total_orders_before'  => $total_orders_before,
			'total_orders_after'   => $total_orders_after,
		) );

		return array(
			'customers'    => $filtered_customers,
			'guest_orders' => $filtered_guest_orders,
			'orders'       => isset( $results['orders'] ) ? $results['orders'] : array(), // Direct order matches pass through.
		);
	}

	/**
	 * Get filter name.
	 *
	 * @return string
	 */
	public function get_filter_name(): string {
		return 'wholesale';
	}

	/**
	 * Check if customer has wholesale role.
	 *
	 * @param int $customer_id Customer user ID.
	 * @return bool
	 */
	private function is_wholesale_customer( int $customer_id ): bool {
		if ( $customer_id <= 0 ) {
			return false;
		}

		$user = get_userdata( $customer_id );
		if ( ! $user ) {
			return false;
		}

		foreach ( self::WHOLESALE_ROLES as $role ) {
			if ( in_array( $role, (array) $user->roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if order is a wholesale order.
	 *
	 * @param int  $order_id Order ID.
	 * @param int  $customer_id Customer user ID.
	 * @param bool $is_wholesale_customer Whether customer has wholesale role.
	 * @return bool
	 */
	private function is_wholesale_order( int $order_id, int $customer_id, bool $is_wholesale_customer ): bool {
		if ( $order_id <= 0 ) {
			return false;
		}

		// Check order meta for wholesale indicators.
		foreach ( self::WHOLESALE_META_KEYS as $meta_key ) {
			$meta_value = get_post_meta( $order_id, $meta_key, true );

			if ( ! empty( $meta_value ) ) {
				// Check for common wholesale values.
				if ( 'wholesale' === $meta_value || 'yes' === $meta_value || '1' === $meta_value || 1 === $meta_value ) {
					return true;
				}
			}
		}

		// Fallback: If customer has wholesale role, assume all their orders are wholesale.
		// This is a reasonable assumption for most wholesale plugins.
		if ( $is_wholesale_customer ) {
			return true;
		}

		return false;
	}
}

