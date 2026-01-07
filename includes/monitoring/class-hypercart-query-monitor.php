<?php
/**
 * Query Monitor
 *
 * Tracks database queries and enforces limits to prevent performance issues.
 * Critical for maintaining <10 queries per search operation.
 *
 * @package Hypercart_Woo_Fast_Search
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hypercart_Query_Monitor {

	/**
	 * Query count at start
	 *
	 * @var int
	 */
	protected $start_count;

	/**
	 * Query limit
	 *
	 * @var int
	 */
	protected $limit;

	/**
	 * Logged queries
	 *
	 * @var array
	 */
	protected $queries = array();

	/**
	 * Constructor
	 *
	 * @param int $limit Query limit (default: 10)
	 */
	public function __construct( $limit = 10 ) {
		$this->limit       = (int) $limit;
		$this->start_count = $this->get_current_query_count();
	}

	/**
	 * Get current query count from WordPress
	 *
	 * @return int Current query count
	 */
	protected function get_current_query_count() {
		global $wpdb;
		return (int) $wpdb->num_queries;
	}

	/**
	 * Get queries executed since start
	 *
	 * @return int Number of queries
	 */
	public function get_query_count() {
		return $this->get_current_query_count() - $this->start_count;
	}

	/**
	 * Check if query limit exceeded
	 *
	 * @throws Exception If query limit exceeded
	 * @return int Current query count
	 */
	public function check() {
		$count = $this->get_query_count();

		if ( $count > $this->limit ) {
			throw new Exception(
				sprintf(
					'Query limit exceeded: %d queries (limit: %d)',
					$count,
					$this->limit
				)
			);
		}

		return $count;
	}

	/**
	 * Log a query for debugging
	 *
	 * @param string $description Query description
	 * @param array  $context Additional context
	 * @return void
	 */
	public function log_query( $description, $context = array() ) {
		$this->queries[] = array(
			'description' => $description,
			'context'     => $context,
			'count'       => $this->get_query_count(),
			'time'        => microtime( true ),
		);
	}

	/**
	 * Get query stats
	 *
	 * @return array Query stats
	 */
	public function get_stats() {
		return array(
			'count'   => $this->get_query_count(),
			'limit'   => $this->limit,
			'percent' => ( $this->get_query_count() / $this->limit ) * 100,
			'queries' => $this->queries,
		);
	}

	/**
	 * Check if approaching limit
	 *
	 * @param int $warning_percent Percentage to warn at (default: 80%)
	 * @return bool True if approaching limit
	 */
	public function is_approaching_limit( $warning_percent = 80 ) {
		$stats = $this->get_stats();
		return $stats['percent'] >= $warning_percent;
	}

	/**
	 * Reset monitor (for testing)
	 *
	 * @return void
	 */
	public function reset() {
		$this->start_count = $this->get_current_query_count();
		$this->queries     = array();
	}

	/**
	 * Get all logged queries
	 *
	 * @return array Logged queries
	 */
	public function get_queries() {
		return $this->queries;
	}
}

