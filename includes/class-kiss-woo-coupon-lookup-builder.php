<?php
/**
 * Shared batch processor for building coupon lookup table.
 *
 * This class provides the core logic for building the lookup table in batches.
 * It can be called from both WP-CLI and admin UI background jobs.
 *
 * SINGLE WRITE PATH: All batch building goes through this class.
 *
 * @package KISS_Woo_Customer_Order_Search
 * @since   1.2.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KISS_Woo_Coupon_Lookup_Builder {

	/**
	 * Option name for tracking build progress.
	 *
	 * @var string
	 */
	private const PROGRESS_OPTION = 'kiss_woo_coupon_build_progress';

	/**
	 * Option name for build lock (prevents concurrent runs).
	 *
	 * @var string
	 */
	private const LOCK_OPTION = 'kiss_woo_coupon_build_lock';

	/**
	 * Option name for next run timestamp (rate limiting).
	 *
	 * @var string
	 */
	private const NEXT_RUN_OPTION = 'kiss_woo_coupon_build_next_run';

	/**
	 * Minimum seconds between background job runs (rate limiting).
	 *
	 * @var int
	 */
	private const MIN_RUN_INTERVAL = 60;

	/**
	 * Lock timeout in seconds (prevents stuck locks).
	 *
	 * @var int
	 */
	private const LOCK_TIMEOUT = 300; // 5 minutes

	/**
	 * Get current build progress.
	 *
	 * @return array{last_id:int,processed:int,total:int,started_at:int,status:string}
	 */
	public function get_progress(): array {
		$default = array(
			'last_id'    => 0,
			'processed'  => 0,
			'total'      => 0,
			'started_at' => 0,
			'status'     => 'idle', // idle, running, complete, error
		);

		$progress = get_option( self::PROGRESS_OPTION, $default );

		return wp_parse_args( $progress, $default );
	}

	/**
	 * Update build progress.
	 *
	 * @param array $progress Progress data.
	 * @return void
	 */
	private function update_progress( array $progress ): void {
		update_option( self::PROGRESS_OPTION, $progress, false );
	}

	/**
	 * Reset build progress.
	 *
	 * @return void
	 */
	public function reset_progress(): void {
		delete_option( self::PROGRESS_OPTION );
		delete_option( self::LOCK_OPTION );
		delete_option( self::NEXT_RUN_OPTION );
	}

	/**
	 * Acquire build lock.
	 *
	 * @return bool True if lock acquired, false if already locked.
	 */
	private function acquire_lock(): bool {
		$lock = get_option( self::LOCK_OPTION, 0 );
		$now  = time();

		// Check if lock is stale (older than timeout).
		if ( $lock > 0 && ( $now - $lock ) < self::LOCK_TIMEOUT ) {
			return false; // Lock is held by another process.
		}

		// Acquire lock.
		update_option( self::LOCK_OPTION, $now, false );

		return true;
	}

	/**
	 * Release build lock.
	 *
	 * @return void
	 */
	private function release_lock(): void {
		delete_option( self::LOCK_OPTION );
	}

	/**
	 * Check if rate limit allows running now.
	 *
	 * @return bool True if can run, false if too soon.
	 */
	private function can_run_now(): bool {
		$next_run = get_option( self::NEXT_RUN_OPTION, 0 );
		$now      = time();

		return $now >= $next_run;
	}

	/**
	 * Set next run timestamp (rate limiting).
	 *
	 * @param int $seconds Seconds from now.
	 * @return void
	 */
	private function set_next_run( int $seconds ): void {
		update_option( self::NEXT_RUN_OPTION, time() + $seconds, false );
	}

	/**
	 * Get total coupon count.
	 *
	 * @return int
	 */
	public function get_total_coupons(): int {
		global $wpdb;

		$count = $wpdb->get_var(
			"SELECT COUNT(*)
			   FROM {$wpdb->posts}
			  WHERE post_type = 'shop_coupon'
			    AND post_status NOT IN ('trash', 'auto-draft')"
		);

		return (int) $count;
	}

	/**
	 * Run a single batch of the build process.
	 *
	 * This is the core method called by both WP-CLI and background jobs.
	 *
	 * @param int  $batch_size Number of coupons to process (default: 500).
	 * @param bool $force      Force run even if rate limited (for CLI).
	 * @return array{success:bool,processed:int,last_id:int,done:bool,message:string}
	 */
	public function run_batch( int $batch_size = 500, bool $force = false ): array {
		// Rate limiting check (skip for CLI with --force).
		if ( ! $force && ! $this->can_run_now() ) {
			return array(
				'success'   => false,
				'processed' => 0,
				'last_id'   => 0,
				'done'      => false,
				'message'   => 'Rate limited - too soon to run again',
			);
		}

		// Acquire lock.
		if ( ! $this->acquire_lock() ) {
			return array(
				'success'   => false,
				'processed' => 0,
				'last_id'   => 0,
				'done'      => false,
				'message'   => 'Another build process is already running',
			);
		}

		try {
			// Get current progress.
			$progress = $this->get_progress();

			// If idle, initialize.
			if ( 'idle' === $progress['status'] || 0 === $progress['total'] ) {
				$progress['total']      = $this->get_total_coupons();
				$progress['started_at'] = time();
				$progress['status']     = 'running';
				$this->update_progress( $progress );
			}

			// Run batch using existing backfill class.
			$backfill = new KISS_Woo_Coupon_Backfill();
			$result   = $backfill->run_batch( $progress['last_id'], $batch_size );

			// Update progress.
			$progress['last_id']   = $result['last_id'];
			$progress['processed'] += $result['processed'];

			if ( $result['done'] ) {
				$progress['status'] = 'complete';
			}

			$this->update_progress( $progress );

			// Set next run time (rate limiting for background jobs).
			if ( ! $result['done'] ) {
				$this->set_next_run( self::MIN_RUN_INTERVAL );
			}

			return array(
				'success'   => true,
				'processed' => $result['processed'],
				'last_id'   => $result['last_id'],
				'done'      => $result['done'],
				'message'   => sprintf(
					'Processed %d coupons (total: %d/%d)',
					$result['processed'],
					$progress['processed'],
					$progress['total']
				),
			);

		} finally {
			// Always release lock.
			$this->release_lock();
		}
	}

	/**
	 * Start a new build process.
	 *
	 * Resets progress and schedules first batch.
	 *
	 * @return array{success:bool,message:string}
	 */
	public function start_build(): array {
		// Ensure table exists.
		$lookup = KISS_Woo_Coupon_Lookup::instance();
		$lookup->maybe_install();

		// Reset progress.
		$this->reset_progress();

		// Initialize progress.
		$total = $this->get_total_coupons();

		$this->update_progress(
			array(
				'last_id'    => 0,
				'processed'  => 0,
				'total'      => $total,
				'started_at' => time(),
				'status'     => 'running',
			)
		);

		// Schedule first batch (for background jobs).
		$this->schedule_next_batch();

		return array(
			'success' => true,
			'message' => sprintf( 'Build started - %d coupons to process', $total ),
		);
	}

	/**
	 * Schedule next batch via WP-Cron.
	 *
	 * @return void
	 */
	private function schedule_next_batch(): void {
		// Schedule single event to run in 10 seconds.
		if ( ! wp_next_scheduled( 'kiss_woo_coupon_build_batch' ) ) {
			wp_schedule_single_event( time() + 10, 'kiss_woo_coupon_build_batch' );
		}
	}

	/**
	 * Cancel ongoing build process.
	 *
	 * @return array{success:bool,message:string}
	 */
	public function cancel_build(): array {
		$this->reset_progress();

		// Clear scheduled events.
		wp_clear_scheduled_hook( 'kiss_woo_coupon_build_batch' );

		return array(
			'success' => true,
			'message' => 'Build cancelled',
		);
	}
}

