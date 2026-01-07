<?php
/**
 * Order Formatter
 *
 * Optimized order data fetching using direct SQL instead of WC_Order objects.
 * Reduces memory usage from ~100KB per order to ~1KB per order.
 *
 * @package Hypercart_Woo_Fast_Search
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hypercart_Order_Formatter {

	/**
	 * Fetch order summaries using direct SQL (HPOS-aware)
	 *
	 * @param array $order_ids Order IDs to fetch
	 * @return array Order summaries (id, number, date, status, total)
	 */
	public function get_order_summaries( $order_ids ) {
		if ( empty( $order_ids ) ) {
			return array();
		}

		// Check if HPOS is enabled
		if ( $this->is_hpos_enabled() ) {
			return $this->get_order_summaries_hpos( $order_ids );
		}

		return $this->get_order_summaries_legacy( $order_ids );
	}

	/**
	 * Fetch order summaries from HPOS tables
	 *
	 * @param array $order_ids Order IDs
	 * @return array Order summaries
	 */
	protected function get_order_summaries_hpos( $order_ids ) {
		global $wpdb;

		$order_ids = array_values( array_filter( array_map( 'intval', $order_ids ) ) );

		if ( empty( $order_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
		$orders_table = $wpdb->prefix . 'wc_orders';

		$sql = $wpdb->prepare(
			"SELECT id, order_key, date_created_gmt, status, total_amount, currency
			 FROM {$orders_table}
			 WHERE id IN ({$placeholders})
			 ORDER BY date_created_gmt DESC",
			$order_ids
		);

		$rows = $wpdb->get_results( $sql );

		if ( empty( $rows ) ) {
			return array();
		}

		$results = array();
		foreach ( $rows as $row ) {
			$results[] = array(
				'id'       => (int) $row->id,
				'number'   => $this->format_order_number( $row->id, $row->order_key ),
				'date'     => $row->date_created_gmt,
				'date_h'   => $this->format_date_human( $row->date_created_gmt ),
				'status'   => $this->format_status( $row->status ),
				'total'    => $this->format_price( $row->total_amount, $row->currency ),
				'edit_url' => $this->get_edit_url( $row->id ),
			);
		}

		return $results;
	}

	/**
	 * Fetch order summaries from legacy posts table
	 *
	 * @param array $order_ids Order IDs
	 * @return array Order summaries
	 */
	protected function get_order_summaries_legacy( $order_ids ) {
		global $wpdb;

		$order_ids = array_values( array_filter( array_map( 'intval', $order_ids ) ) );

		if ( empty( $order_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );

		// Fetch order data with a single query using GROUP_CONCAT for meta
		$sql = $wpdb->prepare(
			"SELECT p.ID, p.post_date_gmt, p.post_status,
			        MAX(CASE WHEN pm.meta_key = '_order_total' THEN pm.meta_value END) as total,
			        MAX(CASE WHEN pm.meta_key = '_order_currency' THEN pm.meta_value END) as currency,
			        MAX(CASE WHEN pm.meta_key = '_order_key' THEN pm.meta_value END) as order_key
			 FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE p.ID IN ({$placeholders})
			 AND p.post_type = 'shop_order'
			 GROUP BY p.ID
			 ORDER BY p.post_date_gmt DESC",
			$order_ids
		);

		$rows = $wpdb->get_results( $sql );

		if ( empty( $rows ) ) {
			return array();
		}

		$results = array();
		foreach ( $rows as $row ) {
			$results[] = array(
				'id'       => (int) $row->ID,
				'number'   => $this->format_order_number( $row->ID, $row->order_key ),
				'date'     => $row->post_date_gmt,
				'date_h'   => $this->format_date_human( $row->post_date_gmt ),
				'status'   => $this->format_status( $row->post_status ),
				'total'    => $this->format_price( $row->total, $row->currency ),
				'edit_url' => $this->get_edit_url( $row->ID ),
			);
		}

		return $results;
	}

	/**
	 * Check if HPOS is enabled
	 *
	 * @return bool True if HPOS enabled
	 */
	protected function is_hpos_enabled() {
		return class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) &&
		       method_exists( 'Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ) &&
		       \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Format order number
	 *
	 * @param int    $order_id Order ID
	 * @param string $order_key Order key
	 * @return string Formatted order number
	 */
	protected function format_order_number( $order_id, $order_key ) {
		// Use order ID as number (can be customized)
		return '#' . $order_id;
	}

	/**
	 * Format date to human-readable
	 *
	 * @param string $date Date string
	 * @return string Formatted date
	 */
	protected function format_date_human( $date ) {
		if ( empty( $date ) || '0000-00-00 00:00:00' === $date ) {
			return '';
		}

		return human_time_diff( strtotime( $date ), current_time( 'timestamp' ) ) . ' ago';
	}

	/**
	 * Format status
	 *
	 * @param string $status Raw status
	 * @return string Formatted status
	 */
	protected function format_status( $status ) {
		// Remove 'wc-' prefix if present
		$status = str_replace( 'wc-', '', $status );
		return ucfirst( $status );
	}

	/**
	 * Format price
	 *
	 * @param float  $amount Amount
	 * @param string $currency Currency code
	 * @return string Formatted price
	 */
	protected function format_price( $amount, $currency = 'USD' ) {
		if ( function_exists( 'wc_price' ) ) {
			return wp_strip_all_tags( wc_price( $amount, array( 'currency' => $currency ) ) );
		}

		return $currency . ' ' . number_format( (float) $amount, 2 );
	}

	/**
	 * Get edit URL for order
	 *
	 * @param int $order_id Order ID
	 * @return string Edit URL
	 */
	protected function get_edit_url( $order_id ) {
		return admin_url( 'post.php?post=' . $order_id . '&action=edit' );
	}
}

