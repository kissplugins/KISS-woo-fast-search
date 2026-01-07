<?php
/**
 * Memory Monitor
 *
 * Tracks memory usage and enforces limits to prevent crashes.
 * Critical for preventing the >512MB memory exhaustion issues.
 *
 * @package Hypercart_Woo_Fast_Search
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hypercart_Memory_Monitor {

	/**
	 * Memory limit in bytes (default: 50MB)
	 *
	 * @var int
	 */
	protected $threshold;

	/**
	 * Starting memory usage
	 *
	 * @var int
	 */
	protected $start_memory;

	/**
	 * Peak memory usage
	 *
	 * @var int
	 */
	protected $peak_memory;

	/**
	 * Constructor
	 *
	 * @param int $threshold Memory threshold in bytes (default: 50MB)
	 */
	public function __construct( $threshold = null ) {
		if ( null === $threshold ) {
			$threshold = 50 * 1024 * 1024; // 50MB default
		}

		$this->threshold    = (int) $threshold;
		$this->start_memory = memory_get_usage();
		$this->peak_memory  = $this->start_memory;
	}

	/**
	 * Check current memory usage
	 *
	 * @throws Exception If memory limit exceeded
	 * @return int Current memory usage in bytes
	 */
	public function check() {
		$current = memory_get_usage();
		$used    = $current - $this->start_memory;

		// Update peak
		if ( $current > $this->peak_memory ) {
			$this->peak_memory = $current;
		}

		// Check threshold
		if ( $used > $this->threshold ) {
			throw new Exception(
				sprintf(
					'Memory limit exceeded: %s used (limit: %s)',
					$this->format_bytes( $used ),
					$this->format_bytes( $this->threshold )
				)
			);
		}

		return $used;
	}

	/**
	 * Get memory usage stats
	 *
	 * @return array Memory stats
	 */
	public function get_stats() {
		$current = memory_get_usage();
		$used    = $current - $this->start_memory;

		return array(
			'start'     => $this->start_memory,
			'current'   => $current,
			'peak'      => $this->peak_memory,
			'used'      => $used,
			'threshold' => $this->threshold,
			'percent'   => ( $used / $this->threshold ) * 100,
		);
	}

	/**
	 * Get peak memory usage
	 *
	 * @return int Peak memory in bytes
	 */
	public function get_peak() {
		return $this->peak_memory - $this->start_memory;
	}

	/**
	 * Format bytes to human-readable
	 *
	 * @param int $bytes Bytes
	 * @return string Formatted string
	 */
	protected function format_bytes( $bytes ) {
		$units = array( 'B', 'KB', 'MB', 'GB' );
		$bytes = max( $bytes, 0 );
		$pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow   = min( $pow, count( $units ) - 1 );

		$bytes /= pow( 1024, $pow );

		return round( $bytes, 2 ) . ' ' . $units[ $pow ];
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
		$this->start_memory = memory_get_usage();
		$this->peak_memory  = $this->start_memory;
	}
}

