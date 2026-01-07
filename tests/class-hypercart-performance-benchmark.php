<?php
/**
 * Performance Benchmark Harness for Hypercart Woo Fast Search
 *
 * Compares performance against stock WooCommerce and WordPress search
 *
 * CRITICAL: Must prove 10-30x performance advantage over stock WC/WP
 *
 * @package Hypercart_Woo_Fast_Search
 * @subpackage Tests
 * @since 2.0.0
 */

class Hypercart_Performance_Benchmark {

	/**
	 * Query counter
	 */
	protected $query_count = 0;

	/**
	 * Query log
	 */
	protected $query_log = [];

	/**
	 * Run comparative benchmark against all implementations
	 *
	 * @param array $scenario Test scenario with customer data
	 * @param bool  $skip_stock_wc Skip stock WC search (memory safety)
	 * @return array Comparison metrics
	 */
	public function run_comparative_benchmark( $scenario, $skip_stock_wc = false ) {
		$results = [
			'scenario'              => $scenario,
			'stock_wp_user_search'  => $this->benchmark_stock_wp_search( $scenario ),
			'hypercart_current'     => $this->benchmark_hypercart_current( $scenario ),
		];

		// Only run stock WC if not skipped (it can cause memory exhaustion)
		if ( ! $skip_stock_wc ) {
			$results['stock_wc_search'] = $this->benchmark_stock_wc_search( $scenario );

			// Calculate improvement ratios vs stock WC
			$results['improvement_vs_stock_wc'] = [
				'query_reduction'   => $this->calculate_ratio( $results['stock_wc_search']['query_count'], $results['hypercart_current']['query_count'] ),
				'speed_improvement' => $this->calculate_ratio( $results['stock_wc_search']['total_time'], $results['hypercart_current']['total_time'] ),
				'memory_reduction'  => $this->calculate_ratio( $results['stock_wc_search']['memory_peak'], $results['hypercart_current']['memory_peak'] ),
			];
		} else {
			$results['stock_wc_search'] = $this->get_empty_metrics( 'Skipped for memory safety' );
			$results['improvement_vs_stock_wc'] = null;
		}

		// Calculate improvement ratios vs stock WP
		$results['improvement_vs_stock_wp'] = [
			'query_reduction'   => $this->calculate_ratio( $results['stock_wp_user_search']['query_count'], $results['hypercart_current']['query_count'] ),
			'speed_improvement' => $this->calculate_ratio( $results['stock_wp_user_search']['total_time'], $results['hypercart_current']['total_time'] ),
			'memory_reduction'  => $this->calculate_ratio( $results['stock_wp_user_search']['memory_peak'], $results['hypercart_current']['memory_peak'] ),
		];

		return $results;
	}

	/**
	 * Benchmark stock WooCommerce customer search
	 * Uses WC_Customer_Data_Store::search_customers()
	 *
	 * @param array $scenario Test scenario
	 * @return array Metrics
	 */
	protected function benchmark_stock_wc_search( $scenario ) {
		if ( ! class_exists( 'WC_Customer_Data_Store' ) ) {
			return $this->get_empty_metrics( 'WooCommerce not available' );
		}

		$metrics = $this->init_metrics();
		$this->start_tracking();

		$start_time   = microtime( true );
		$start_memory = memory_get_usage();

		try {
			// Check memory before running
			$memory_limit = $this->get_memory_limit();
			$current_usage = memory_get_usage();
			$available = $memory_limit - $current_usage;

			if ( $available < 100 * 1024 * 1024 ) { // Less than 100MB available
				throw new Exception( 'Insufficient memory available (need 100MB, have ' . round($available / 1024 / 1024) . 'MB)' );
			}

			$data_store = WC_Data_Store::load( 'customer' );

			// CRITICAL: Stock WC search has NO LIMIT - this can load thousands of customers!
			// This is the likely cause of memory exhaustion
			$results    = $data_store->search_customers( $scenario['term'] );

			$metrics['result_count'] = count( $results );
			$metrics['status']       = 'success';
		} catch ( Exception $e ) {
			$metrics['status'] = 'error: ' . $e->getMessage();
		}

		$metrics['total_time']  = microtime( true ) - $start_time;
		$metrics['memory_peak'] = memory_get_peak_usage() - $start_memory;
		$metrics['query_count'] = $this->stop_tracking();

		return $metrics;
	}

	/**
	 * Benchmark stock WordPress user search
	 * Uses WP_User_Query with meta_query
	 *
	 * @param array $scenario Test scenario
	 * @return array Metrics
	 */
	protected function benchmark_stock_wp_search( $scenario ) {
		$metrics = $this->init_metrics();
		$this->start_tracking();

		$start_time   = microtime( true );
		$start_memory = memory_get_usage();

		try {
			$user_query = new WP_User_Query( [
				'search'         => '*' . esc_attr( $scenario['term'] ) . '*',
				'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
				'number'         => 20,
			] );

			$metrics['result_count'] = $user_query->get_total();
			$metrics['status']       = 'success';
		} catch ( Exception $e ) {
			$metrics['status'] = 'error: ' . $e->getMessage();
		}

		$metrics['total_time']  = microtime( true ) - $start_time;
		$metrics['memory_peak'] = memory_get_peak_usage() - $start_memory;
		$metrics['query_count'] = $this->stop_tracking();

		return $metrics;
	}

	/**
	 * Benchmark current Hypercart implementation
	 *
	 * @param array $scenario Test scenario
	 * @return array Metrics
	 */
	protected function benchmark_hypercart_current( $scenario ) {
		if ( ! class_exists( 'KISS_Woo_COS_Search' ) ) {
			return $this->get_empty_metrics( 'Hypercart search class not available' );
		}

		$metrics = $this->init_metrics();
		$this->start_tracking();

		$start_time   = microtime( true );
		$start_memory = memory_get_usage();

		try {
			$search  = new KISS_Woo_COS_Search();
			$results = $search->search_customers( $scenario['term'] );

			$metrics['result_count'] = is_array( $results ) ? count( $results ) : 0;
			$metrics['status']       = 'success';
		} catch ( Exception $e ) {
			$metrics['status'] = 'error: ' . $e->getMessage();
		}

		$metrics['total_time']  = microtime( true ) - $start_time;
		$metrics['memory_peak'] = memory_get_peak_usage() - $start_memory;
		$metrics['query_count'] = $this->stop_tracking();

		return $metrics;
	}

	/**
	 * Generate comparison report
	 *
	 * @param array $results Benchmark results
	 * @return bool True if passes performance gates
	 */
	public function generate_report( $results ) {
		echo "\n" . str_repeat( '=', 80 ) . "\n";
		echo "HYPERCART WOO FAST SEARCH - PERFORMANCE COMPARISON REPORT\n";
		echo str_repeat( '=', 80 ) . "\n\n";

		echo "Scenario: {$results['scenario']['description']}\n";
		echo "Search Term: \"{$results['scenario']['term']}\"\n\n";

		// Stock WooCommerce
		echo "Stock WooCommerce Search:\n";
		echo "  Queries: {$results['stock_wc_search']['query_count']}\n";
		echo "  Time: " . number_format( $results['stock_wc_search']['total_time'], 3 ) . "s\n";
		echo "  Memory: " . $this->format_bytes( $results['stock_wc_search']['memory_peak'] ) . "\n";
		echo "  Results: {$results['stock_wc_search']['result_count']}\n";
		echo "  Status: {$results['stock_wc_search']['status']}\n\n";

		// Stock WordPress
		echo "Stock WordPress User Search:\n";
		echo "  Queries: {$results['stock_wp_user_search']['query_count']}\n";
		echo "  Time: " . number_format( $results['stock_wp_user_search']['total_time'], 3 ) . "s\n";
		echo "  Memory: " . $this->format_bytes( $results['stock_wp_user_search']['memory_peak'] ) . "\n";
		echo "  Results: {$results['stock_wp_user_search']['result_count']}\n";
		echo "  Status: {$results['stock_wp_user_search']['status']}\n\n";

		// Hypercart Current
		echo "Hypercart Fast Search (Current):\n";
		echo "  Queries: {$results['hypercart_current']['query_count']}\n";
		echo "  Time: " . number_format( $results['hypercart_current']['total_time'], 3 ) . "s\n";
		echo "  Memory: " . $this->format_bytes( $results['hypercart_current']['memory_peak'] ) . "\n";
		echo "  Results: {$results['hypercart_current']['result_count']}\n";
		echo "  Status: {$results['hypercart_current']['status']}\n\n";

		// Improvement vs Stock WC
		echo str_repeat( '-', 80 ) . "\n";
		echo "IMPROVEMENT vs Stock WooCommerce:\n";
		echo "  Query Reduction: " . number_format( $results['improvement_vs_stock_wc']['query_reduction'], 1 ) . "x\n";
		echo "  Speed Improvement: " . number_format( $results['improvement_vs_stock_wc']['speed_improvement'], 1 ) . "x\n";
		echo "  Memory Reduction: " . number_format( $results['improvement_vs_stock_wc']['memory_reduction'], 1 ) . "x\n\n";

		// Improvement vs Stock WP
		echo "IMPROVEMENT vs Stock WordPress:\n";
		echo "  Query Reduction: " . number_format( $results['improvement_vs_stock_wp']['query_reduction'], 1 ) . "x\n";
		echo "  Speed Improvement: " . number_format( $results['improvement_vs_stock_wp']['speed_improvement'], 1 ) . "x\n";
		echo "  Memory Reduction: " . number_format( $results['improvement_vs_stock_wp']['memory_reduction'], 1 ) . "x\n\n";

		// Performance Gates
		echo str_repeat( '=', 80 ) . "\n";
		echo "PERFORMANCE GATES (Minimum Requirements):\n";
		echo str_repeat( '=', 80 ) . "\n\n";

		$pass = true;

		// Gate 1: Must be at least 10x faster than stock WC
		if ( $results['improvement_vs_stock_wc']['speed_improvement'] < 10 ) {
			echo "❌ FAIL: Must be at least 10x faster than stock WC\n";
			echo "   Current: " . number_format( $results['improvement_vs_stock_wc']['speed_improvement'], 1 ) . "x\n\n";
			$pass = false;
		} else {
			echo "✅ PASS: " . number_format( $results['improvement_vs_stock_wc']['speed_improvement'], 1 ) . "x faster than stock WC (target: 10x)\n\n";
		}

		// Gate 2: Must use <10 queries
		if ( $results['hypercart_current']['query_count'] >= 10 ) {
			echo "❌ FAIL: Must use <10 queries\n";
			echo "   Current: {$results['hypercart_current']['query_count']} queries\n\n";
			$pass = false;
		} else {
			echo "✅ PASS: {$results['hypercart_current']['query_count']} queries (target: <10)\n\n";
		}

		// Gate 3: Must use <50MB memory
		$memory_mb = $results['hypercart_current']['memory_peak'] / 1024 / 1024;
		if ( $memory_mb >= 50 ) {
			echo "❌ FAIL: Must use <50MB memory\n";
			echo "   Current: " . number_format( $memory_mb, 1 ) . "MB\n\n";
			$pass = false;
		} else {
			echo "✅ PASS: " . number_format( $memory_mb, 1 ) . "MB memory (target: <50MB)\n\n";
		}

		// Gate 4: Must complete in <2 seconds
		if ( $results['hypercart_current']['total_time'] >= 2.0 ) {
			echo "❌ FAIL: Must complete in <2 seconds\n";
			echo "   Current: " . number_format( $results['hypercart_current']['total_time'], 3 ) . "s\n\n";
			$pass = false;
		} else {
			echo "✅ PASS: " . number_format( $results['hypercart_current']['total_time'], 3 ) . "s (target: <2s)\n\n";
		}

		echo str_repeat( '=', 80 ) . "\n";
		if ( $pass ) {
			echo "✅ ALL PERFORMANCE GATES PASSED\n";
		} else {
			echo "❌ PERFORMANCE GATES FAILED - Refactoring incomplete\n";
		}
		echo str_repeat( '=', 80 ) . "\n\n";

		return $pass;
	}

	/**
	 * Start query tracking
	 */
	protected function start_tracking() {
		$this->query_count = 0;
		$this->query_log   = [];
		add_filter( 'query', [ $this, 'track_query' ] );
	}

	/**
	 * Stop query tracking
	 *
	 * @return int Query count
	 */
	protected function stop_tracking() {
		remove_filter( 'query', [ $this, 'track_query' ] );
		return $this->query_count;
	}

	/**
	 * Track individual query
	 *
	 * @param string $query SQL query
	 * @return string Unmodified query
	 */
	public function track_query( $query ) {
		$this->query_count++;
		$this->query_log[] = $query;
		return $query;
	}

	/**
	 * Initialize metrics array
	 *
	 * @return array Empty metrics
	 */
	protected function init_metrics() {
		return [
			'query_count'  => 0,
			'total_time'   => 0,
			'memory_peak'  => 0,
			'result_count' => 0,
			'status'       => 'not_run',
		];
	}

	/**
	 * Get empty metrics with error message
	 *
	 * @param string $message Error message
	 * @return array Metrics with error
	 */
	protected function get_empty_metrics( $message ) {
		$metrics = $this->init_metrics();
		$metrics['status'] = 'error: ' . $message;
		return $metrics;
	}

	/**
	 * Calculate improvement ratio
	 *
	 * @param float $before Before value
	 * @param float $after After value
	 * @return float Improvement ratio
	 */
	protected function calculate_ratio( $before, $after ) {
		if ( $after == 0 ) {
			return 0;
		}
		return $before / $after;
	}

	/**
	 * Get PHP memory limit in bytes
	 *
	 * @return int Memory limit in bytes
	 */
	protected function get_memory_limit() {
		$memory_limit = ini_get( 'memory_limit' );

		if ( $memory_limit == -1 ) {
			return PHP_INT_MAX;
		}

		$unit = strtoupper( substr( $memory_limit, -1 ) );
		$value = (int) $memory_limit;

		switch ( $unit ) {
			case 'G':
				$value *= 1024;
			case 'M':
				$value *= 1024;
			case 'K':
				$value *= 1024;
		}

		return $value;
	}

	/**
	 * Format bytes to human-readable
	 *
	 * @param int $bytes Bytes
	 * @return string Formatted string
	 */
	protected function format_bytes( $bytes ) {
		$units = [ 'B', 'KB', 'MB', 'GB' ];
		$bytes = max( $bytes, 0 );
		$pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow   = min( $pow, count( $units ) - 1 );
		$bytes /= pow( 1024, $pow );
		return round( $bytes, 2 ) . ' ' . $units[ $pow ];
	}
}
