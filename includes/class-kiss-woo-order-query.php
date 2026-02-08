<?php
/**
 * Centralized Order Query Helper
 *
 * SINGLE WRITE PATH: All order listing queries go through query_orders().
 * Follows SOLID principles: Strategy Pattern for pluggable query types.
 *
 * @package KISS_Woo_Customer_Order_Search
 * @since   1.2.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order query helper with support for wholesale, retail, and custom filters.
 *
 * Usage:
 *   $query = new KISS_Woo_Order_Query();
 *   $results = $query->query_orders( 'wholesale', 1, 100 );
 */
class KISS_Woo_Order_Query {

	/**
	 * Query orders with filters and pagination.
	 *
	 * @param string $type      Query type: 'wholesale', 'retail', 'all'.
	 * @param int    $page      Page number (1-based).
	 * @param int    $per_page  Orders per page.
	 * @param array  $args      Additional arguments (status, date_range, etc.).
	 *
	 * @return array {
	 *     @type array $orders      Array of formatted order data.
	 *     @type int   $total       Total orders matching criteria.
	 *     @type int   $pages       Total pages.
	 *     @type int   $current_page Current page number.
	 *     @type float $elapsed_ms  Query execution time in milliseconds.
	 * }
	 */
	public function query_orders( string $type = 'all', int $page = 1, int $per_page = 100, array $args = array() ): array {
		$t_start = microtime( true );

		KISS_Woo_Debug_Tracer::log( 'OrderQuery', 'query_start', array(
			'type'     => $type,
			'page'     => $page,
			'per_page' => $per_page,
			'args'     => $args,
		) );

		// Validate pagination.
		$page     = max( 1, (int) $page );
		$per_page = max( 1, min( 500, (int) $per_page ) ); // Cap at 500 for safety.
		$offset   = ( $page - 1 ) * $per_page;

		// Build query based on type.
		$query_data = $this->build_query( $type, $per_page, $offset, $args );

		if ( empty( $query_data ) ) {
			return $this->empty_result();
		}

		global $wpdb;

		// Execute count query.
		$total = (int) $wpdb->get_var( $query_data['count_sql'] );

		KISS_Woo_Debug_Tracer::log( 'OrderQuery', 'count_result', array(
			'total' => $total,
		) );

		if ( 0 === $total ) {
			return $this->empty_result();
		}

		// Execute data query.
		$rows = $wpdb->get_results( $query_data['data_sql'] );

		KISS_Woo_Debug_Tracer::log( 'OrderQuery', 'data_result', array(
			'rows' => is_array( $rows ) ? count( $rows ) : 0,
		) );

		if ( empty( $rows ) ) {
			return $this->empty_result();
		}

		// Format orders.
		$orders = $this->format_order_rows( $rows );

		$elapsed_ms = round( ( microtime( true ) - $t_start ) * 1000, 2 );

		KISS_Woo_Debug_Tracer::log( 'OrderQuery', 'query_complete', array(
			'orders'     => count( $orders ),
			'total'      => $total,
			'elapsed_ms' => $elapsed_ms,
		) );

		return array(
			'orders'       => $orders,
			'total'        => $total,
			'pages'        => (int) ceil( $total / $per_page ),
			'current_page' => $page,
			'elapsed_ms'   => $elapsed_ms,
		);
	}

	/**
	 * Build SQL queries based on order type.
	 *
	 * @param string $type      Query type.
	 * @param int    $per_page  Limit.
	 * @param int    $offset    Offset.
	 * @param array  $args      Additional filters.
	 *
	 * @return array|null {
	 *     @type string $count_sql COUNT query.
	 *     @type string $data_sql  Data query.
	 * }
	 */
	private function build_query( string $type, int $per_page, int $offset, array $args ): ?array {
		$use_hpos = KISS_Woo_Utils::is_hpos_enabled();

		if ( $use_hpos ) {
			return $this->build_hpos_query( $type, $per_page, $offset, $args );
		}

		return $this->build_legacy_query( $type, $per_page, $offset, $args );
	}

	/**
	 * Build HPOS queries (wc_orders table).
	 *
	 * @param string $type      Query type.
	 * @param int    $per_page  Limit.
	 * @param int    $offset    Offset.
	 * @param array  $args      Additional filters.
	 *
	 * @return array|null
	 */
	private function build_hpos_query( string $type, int $per_page, int $offset, array $args ): ?array {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'wc_orders';
		$meta_table   = $wpdb->prefix . 'wc_orders_meta';
		$addr_table   = $wpdb->prefix . 'wc_order_addresses';

		// Base WHERE clause.
		$where = array( '1=1' );

		// Add type-specific filters.
		if ( 'wholesale' === $type ) {
			$where[] = $this->get_wholesale_meta_condition( $meta_table );
		} elseif ( 'retail' === $type ) {
			$where[] = $this->get_retail_meta_condition( $meta_table );
		}

		// Add status filter.
		$statuses = isset( $args['status'] ) ? (array) $args['status'] : array_keys( wc_get_order_statuses() );
		$status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$where[] = $wpdb->prepare( "o.status IN ({$status_placeholders})", $statuses );

		// Add date filter (if provided).
		if ( isset( $args['date_from'] ) && ! empty( $args['date_from'] ) ) {
			$where[] = $wpdb->prepare( 'o.date_created_gmt >= %s', $args['date_from'] );
		}
		if ( isset( $args['date_to'] ) && ! empty( $args['date_to'] ) ) {
			$where[] = $wpdb->prepare( 'o.date_created_gmt <= %s', $args['date_to'] );
		}

		$where_clause = implode( ' AND ', $where );

		// COUNT query.
		$count_sql = "SELECT COUNT(DISTINCT o.id)
			FROM {$orders_table} o
			WHERE {$where_clause}";

		// DATA query.
		$data_sql = $wpdb->prepare(
			"SELECT o.id, o.status, o.date_created_gmt, o.total_amount, o.currency,
			        a.email as billing_email, a.first_name, a.last_name
			 FROM {$orders_table} o
			 LEFT JOIN {$addr_table} a ON o.id = a.order_id AND a.address_type = 'billing'
			 WHERE {$where_clause}
			 ORDER BY o.date_created_gmt DESC
			 LIMIT %d OFFSET %d",
			$per_page,
			$offset
		);

		return array(
			'count_sql' => $count_sql,
			'data_sql'  => $data_sql,
		);
	}

	/**
	 * Build legacy queries (wp_posts table).
	 *
	 * @param string $type      Query type.
	 * @param int    $per_page  Limit.
	 * @param int    $offset    Offset.
	 * @param array  $args      Additional filters.
	 *
	 * @return array|null
	 */
	private function build_legacy_query( string $type, int $per_page, int $offset, array $args ): ?array {
		global $wpdb;

		// Base WHERE clause.
		$where = array( "p.post_type = 'shop_order'" );

		// Add type-specific filters.
		if ( 'wholesale' === $type ) {
			$where[] = $this->get_wholesale_meta_condition( $wpdb->postmeta, 'p.ID' );
		} elseif ( 'retail' === $type ) {
			$where[] = $this->get_retail_meta_condition( $wpdb->postmeta, 'p.ID' );
		}

		// Add status filter.
		$statuses = isset( $args['status'] ) ? (array) $args['status'] : array_keys( wc_get_order_statuses() );
		$status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$where[] = $wpdb->prepare( "p.post_status IN ({$status_placeholders})", $statuses );

		// Add date filter (if provided).
		if ( isset( $args['date_from'] ) && ! empty( $args['date_from'] ) ) {
			$where[] = $wpdb->prepare( 'p.post_date_gmt >= %s', $args['date_from'] );
		}
		if ( isset( $args['date_to'] ) && ! empty( $args['date_to'] ) ) {
			$where[] = $wpdb->prepare( 'p.post_date_gmt <= %s', $args['date_to'] );
		}

		$where_clause = implode( ' AND ', $where );

		// COUNT query.
		$count_sql = "SELECT COUNT(DISTINCT p.ID)
			FROM {$wpdb->posts} p
			WHERE {$where_clause}";

		// DATA query - Join with postmeta to get order details.
		// Legacy mode stores order data in postmeta with keys:
		// _order_total, _order_currency, _billing_email, _billing_first_name, _billing_last_name.
		$data_sql = $wpdb->prepare(
			"SELECT
				p.ID as id,
				p.post_status as status,
				p.post_date_gmt as date_created_gmt,
				MAX(CASE WHEN pm_total.meta_key = '_order_total' THEN pm_total.meta_value END) as total_amount,
				MAX(CASE WHEN pm_currency.meta_key = '_order_currency' THEN pm_currency.meta_value END) as currency,
				MAX(CASE WHEN pm_email.meta_key = '_billing_email' THEN pm_email.meta_value END) as billing_email,
				MAX(CASE WHEN pm_fname.meta_key = '_billing_first_name' THEN pm_fname.meta_value END) as first_name,
				MAX(CASE WHEN pm_lname.meta_key = '_billing_last_name' THEN pm_lname.meta_value END) as last_name
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
			LEFT JOIN {$wpdb->postmeta} pm_currency ON p.ID = pm_currency.post_id AND pm_currency.meta_key = '_order_currency'
			LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
			LEFT JOIN {$wpdb->postmeta} pm_fname ON p.ID = pm_fname.post_id AND pm_fname.meta_key = '_billing_first_name'
			LEFT JOIN {$wpdb->postmeta} pm_lname ON p.ID = pm_lname.post_id AND pm_lname.meta_key = '_billing_last_name'
			WHERE {$where_clause}
			GROUP BY p.ID, p.post_status, p.post_date_gmt
			ORDER BY p.post_date_gmt DESC
			LIMIT %d OFFSET %d",
			$per_page,
			$offset
		);

		return array(
			'count_sql' => $count_sql,
			'data_sql'  => $data_sql,
		);
	}

	/**
	 * Get wholesale meta condition for SQL WHERE clause.
	 *
	 * @param string $meta_table Meta table name.
	 * @param string $order_id_col Order ID column (default: 'o.id').
	 *
	 * @return string SQL condition.
	 */
	private function get_wholesale_meta_condition( string $meta_table, string $order_id_col = 'o.id' ): string {
		global $wpdb;

		// Reuse wholesale meta keys from filter class.
		$meta_keys = array(
			'_wwpp_order_type',
			'_wholesale_order',
			'_is_wholesale_order',
			'_wwp_wholesale_order',
		);

		// Determine meta foreign key column based on table.
		// HPOS uses 'order_id', legacy wp_postmeta uses 'post_id'.
		$meta_fk_col = ( $meta_table === $wpdb->postmeta ) ? 'post_id' : 'order_id';

		$conditions = array();
		foreach ( $meta_keys as $key ) {
			$conditions[] = $wpdb->prepare(
				"EXISTS (SELECT 1 FROM {$meta_table} m WHERE m.{$meta_fk_col} = {$order_id_col} AND m.meta_key = %s AND m.meta_value IN ('wholesale', 'yes', '1'))",
				$key
			);
		}

		return '(' . implode( ' OR ', $conditions ) . ')';
	}

	/**
	 * Get retail meta condition (NOT wholesale).
	 *
	 * @param string $meta_table Meta table name.
	 * @param string $order_id_col Order ID column.
	 *
	 * @return string SQL condition.
	 */
	private function get_retail_meta_condition( string $meta_table, string $order_id_col = 'o.id' ): string {
		// Retail = NOT wholesale.
		return 'NOT ' . $this->get_wholesale_meta_condition( $meta_table, $order_id_col );
	}

	/**
	 * Format order rows from SQL results.
	 *
	 * @param array $rows Raw SQL rows.
	 *
	 * @return array Formatted orders.
	 */
	private function format_order_rows( array $rows ): array {
		$orders = array();

		foreach ( $rows as $row ) {
			$data = array(
				'id'            => (int) $row->id,
				'status'        => isset( $row->status ) ? str_replace( 'wc-', '', $row->status ) : '',
				'date_gmt'      => isset( $row->date_created_gmt ) ? $row->date_created_gmt : '',
				'total'         => isset( $row->total_amount ) ? $row->total_amount : '',
				'currency'      => isset( $row->currency ) ? $row->currency : '',
				'billing_email' => isset( $row->billing_email ) ? $row->billing_email : '',
				'customer_name' => '',
			);

			// Build customer name.
			if ( isset( $row->first_name ) || isset( $row->last_name ) ) {
				$first = isset( $row->first_name ) ? $row->first_name : '';
				$last  = isset( $row->last_name ) ? $row->last_name : '';
				$data['customer_name'] = trim( $first . ' ' . $last );
			}

			// Use centralized formatter.
			$orders[] = KISS_Woo_Order_Formatter::format_from_raw( $data );
		}

		return $orders;
	}

	/**
	 * Return empty result structure.
	 *
	 * @return array
	 */
	private function empty_result(): array {
		return array(
			'orders'       => array(),
			'total'        => 0,
			'pages'        => 0,
			'current_page' => 1,
			'elapsed_ms'   => 0,
		);
	}
}

