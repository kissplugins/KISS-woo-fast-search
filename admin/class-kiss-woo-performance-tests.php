<?php
/**
 * Performance Test Admin Page
 *
 * Comprehensive performance benchmarking with historical tracking
 *
 * @package KISS_Woo_Customer_Order_Search
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KISS_Woo_COS_Performance_Tests {

	/**
	 * Singleton instance
	 */
	protected static $instance = null;

	/**
	 * Option name for storing benchmark results
	 */
	const OPTION_NAME = 'kiss_woo_benchmark_results';

	/**
	 * Get singleton instance
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_post_kiss_run_performance_test', array( $this, 'handle_run_test' ) );
		add_action( 'admin_post_kiss_clear_benchmark_history', array( $this, 'handle_clear_history' ) );
	}

	/**
	 * Register admin menu
	 */
	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Performance Tests', 'kiss-woo-customer-order-search' ),
			__( 'Performance Tests', 'kiss-woo-customer-order-search' ),
			'manage_woocommerce',
			'kiss-performance-tests',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle running performance test
	 */
	public function handle_run_test() {
		// Verify nonce
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'kiss_run_performance_test' ) ) {
			wp_die( __( 'Security check failed.', 'kiss-woo-customer-order-search' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Insufficient permissions.', 'kiss-woo-customer-order-search' ) );
		}

		// Load test classes
		require_once KISS_WOO_COS_PATH . 'tests/fixtures/class-hypercart-test-data-factory.php';
		require_once KISS_WOO_COS_PATH . 'tests/class-hypercart-performance-benchmark.php';

		// Get test scenario
		$scenario_key = isset( $_POST['scenario'] ) ? sanitize_text_field( $_POST['scenario'] ) : 'two_word_name';
		$scenarios    = Hypercart_Test_Data_Factory::get_search_scenarios();

		if ( ! isset( $scenarios[ $scenario_key ] ) ) {
			$scenario_key = 'two_word_name';
		}

		$scenario = $scenarios[ $scenario_key ];

		// Run benchmark with memory safety
		$benchmark = new Hypercart_Performance_Benchmark();

		// SAFETY: Skip stock WC search if it might cause memory issues
		$skip_stock_wc = isset( $_POST['skip_stock_wc'] ) && $_POST['skip_stock_wc'] === '1';

		try {
			$results = $benchmark->run_comparative_benchmark( $scenario, $skip_stock_wc );
		} catch ( Exception $e ) {
			// If benchmark crashes, redirect with error
			wp_safe_redirect( add_query_arg(
				array(
					'page'    => 'kiss-performance-tests',
					'message' => 'benchmark_error',
					'error'   => urlencode( $e->getMessage() ),
				),
				admin_url( 'admin.php' )
			) );
			exit;
		}

		// Add metadata
		$results['scenario_key'] = $scenario_key;
		$results['timestamp']    = current_time( 'mysql' );
		$results['user_id']      = get_current_user_id();
		$results['version']      = KISS_WOO_COS_VERSION;
		$results['skip_stock_wc'] = $skip_stock_wc;

		// Store results
		$this->store_benchmark_result( $results );

		// Redirect back with success message
		wp_safe_redirect( add_query_arg(
			array(
				'page'    => 'kiss-performance-tests',
				'message' => 'test_complete',
			),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * Handle clearing benchmark history
	 */
	public function handle_clear_history() {
		// Verify nonce
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'kiss_clear_benchmark_history' ) ) {
			wp_die( __( 'Security check failed.', 'kiss-woo-customer-order-search' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Insufficient permissions.', 'kiss-woo-customer-order-search' ) );
		}

		// Clear history
		delete_option( self::OPTION_NAME );

		// Redirect back
		wp_safe_redirect( add_query_arg(
			array(
				'page'    => 'kiss-performance-tests',
				'message' => 'history_cleared',
			),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * Store benchmark result in options table
	 *
	 * @param array $result Benchmark result
	 */
	protected function store_benchmark_result( $result ) {
		$history = get_option( self::OPTION_NAME, array() );

		// Add new result
		$history[] = $result;

		// Keep only last 50 results
		if ( count( $history ) > 50 ) {
			$history = array_slice( $history, -50 );
		}

		update_option( self::OPTION_NAME, $history, false );
	}

	/**
	 * Get benchmark history
	 *
	 * @return array Benchmark history
	 */
	public function get_benchmark_history() {
		return get_option( self::OPTION_NAME, array() );
	}

	/**
	 * Get latest benchmark result
	 *
	 * @return array|null Latest result or null
	 */
	public function get_latest_result() {
		$history = $this->get_benchmark_history();
		return ! empty( $history ) ? end( $history ) : null;
	}

	/**
	 * Render admin page
	 */
	public function render_page() {
		// Load test data factory for scenarios
		require_once KISS_WOO_COS_PATH . 'tests/fixtures/class-hypercart-test-data-factory.php';
		$scenarios = Hypercart_Test_Data_Factory::get_search_scenarios();

		// Get history
		$history       = $this->get_benchmark_history();
		$latest_result = $this->get_latest_result();

		// Show message if any
		$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Performance Tests', 'kiss-woo-customer-order-search' ); ?></h1>

			<?php if ( 'test_complete' === $message ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Performance test completed successfully!', 'kiss-woo-customer-order-search' ); ?></p>
				</div>
			<?php elseif ( 'history_cleared' === $message ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Benchmark history cleared.', 'kiss-woo-customer-order-search' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="card" style="max-width: 800px;">
				<h2><?php esc_html_e( 'Run Performance Benchmark', 'kiss-woo-customer-order-search' ); ?></h2>
				<p><?php esc_html_e( 'Compare Hypercart Fast Search against stock WooCommerce and WordPress search.', 'kiss-woo-customer-order-search' ); ?></p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="kiss_run_performance_test">
					<?php wp_nonce_field( 'kiss_run_performance_test' ); ?>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="scenario"><?php esc_html_e( 'Test Scenario', 'kiss-woo-customer-order-search' ); ?></label>
							</th>
							<td>
								<select name="scenario" id="scenario" class="regular-text">
									<?php foreach ( $scenarios as $key => $scenario ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>">
											<?php echo esc_html( $scenario['description'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Select a search scenario to test.', 'kiss-woo-customer-order-search' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="skip_stock_wc"><?php esc_html_e( 'Memory Safety', 'kiss-woo-customer-order-search' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" name="skip_stock_wc" id="skip_stock_wc" value="1" checked>
									<?php esc_html_e( 'Skip Stock WooCommerce search (prevents memory exhaustion)', 'kiss-woo-customer-order-search' ); ?>
								</label>
								<p class="description" style="color: #d63638;">
									<strong><?php esc_html_e( '⚠️ WARNING:', 'kiss-woo-customer-order-search' ); ?></strong>
									<?php esc_html_e( 'Stock WC search can use >512MB memory and crash. Keep this checked unless you have >1GB memory available.', 'kiss-woo-customer-order-search' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<p class="submit">
						<button type="submit" class="button button-primary button-large">
							<?php esc_html_e( 'Run Performance Test', 'kiss-woo-customer-order-search' ); ?>
						</button>
					</p>
				</form>

				<div style="background: #f0f0f1; padding: 15px; border-radius: 4px; margin-top: 20px;">
					<h3 style="margin-top: 0;"><?php esc_html_e( 'Performance Gates', 'kiss-woo-customer-order-search' ); ?></h3>
					<p><?php esc_html_e( 'Tests must pass these minimum requirements:', 'kiss-woo-customer-order-search' ); ?></p>
					<ul style="list-style: disc; margin-left: 20px;">
						<li><?php esc_html_e( '✓ Must be at least 10x faster than stock WooCommerce', 'kiss-woo-customer-order-search' ); ?></li>
						<li><?php esc_html_e( '✓ Must use less than 10 database queries', 'kiss-woo-customer-order-search' ); ?></li>
						<li><?php esc_html_e( '✓ Must use less than 50MB memory', 'kiss-woo-customer-order-search' ); ?></li>
						<li><?php esc_html_e( '✓ Must complete in less than 2 seconds', 'kiss-woo-customer-order-search' ); ?></li>
					</ul>
				</div>
			</div>

			<?php if ( $latest_result ) : ?>
				<div class="card" style="max-width: 800px; margin-top: 20px;">
					<h2><?php esc_html_e( 'Latest Test Results', 'kiss-woo-customer-order-search' ); ?></h2>
					<?php $this->render_test_results( $latest_result ); ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $history ) ) : ?>
				<div class="card" style="max-width: 800px; margin-top: 20px;">
					<h2><?php esc_html_e( 'Test History', 'kiss-woo-customer-order-search' ); ?></h2>
					<p>
						<?php
						printf(
							esc_html__( 'Showing %d most recent test results.', 'kiss-woo-customer-order-search' ),
							count( $history )
						);
						?>
					</p>
					<?php $this->render_history_table( $history ); ?>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 15px;">
						<input type="hidden" name="action" value="kiss_clear_benchmark_history">
						<?php wp_nonce_field( 'kiss_clear_benchmark_history' ); ?>
						<button type="submit" class="button" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to clear all benchmark history?', 'kiss-woo-customer-order-search' ); ?>');">
							<?php esc_html_e( 'Clear History', 'kiss-woo-customer-order-search' ); ?>
						</button>
					</form>
				</div>
			<?php endif; ?>

			<style>
				.performance-metric {
					display: inline-block;
					padding: 8px 12px;
					margin: 5px;
					background: #f0f0f1;
					border-radius: 4px;
					font-family: monospace;
				}
				.performance-metric.pass {
					background: #d4edda;
					color: #155724;
				}
				.performance-metric.fail {
					background: #f8d7da;
					color: #721c24;
				}
				.improvement-badge {
					display: inline-block;
					padding: 4px 8px;
					background: #0073aa;
					color: white;
					border-radius: 3px;
					font-size: 12px;
					font-weight: bold;
				}
			</style>
		</div>
		<?php
	}

	/**
	 * Render test results
	 *
	 * @param array $result Test result
	 */
	protected function render_test_results( $result ) {
		$scenario = isset( $result['scenario'] ) ? $result['scenario'] : array();
		?>
		<div style="margin: 15px 0;">
			<p>
				<strong><?php esc_html_e( 'Scenario:', 'kiss-woo-customer-order-search' ); ?></strong>
				<?php echo esc_html( isset( $scenario['description'] ) ? $scenario['description'] : 'N/A' ); ?>
				<br>
				<strong><?php esc_html_e( 'Search Term:', 'kiss-woo-customer-order-search' ); ?></strong>
				"<?php echo esc_html( isset( $scenario['term'] ) ? $scenario['term'] : 'N/A' ); ?>"
				<br>
				<strong><?php esc_html_e( 'Tested:', 'kiss-woo-customer-order-search' ); ?></strong>
				<?php echo esc_html( isset( $result['timestamp'] ) ? $result['timestamp'] : 'N/A' ); ?>
			</p>
		</div>

		<h3><?php esc_html_e( 'Performance Comparison', 'kiss-woo-customer-order-search' ); ?></h3>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Implementation', 'kiss-woo-customer-order-search' ); ?></th>
					<th><?php esc_html_e( 'Queries', 'kiss-woo-customer-order-search' ); ?></th>
					<th><?php esc_html_e( 'Time', 'kiss-woo-customer-order-search' ); ?></th>
					<th><?php esc_html_e( 'Memory', 'kiss-woo-customer-order-search' ); ?></th>
					<th><?php esc_html_e( 'Results', 'kiss-woo-customer-order-search' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! isset( $result['skip_stock_wc'] ) || ! $result['skip_stock_wc'] ) : ?>
					<tr>
						<td><strong><?php esc_html_e( 'Stock WooCommerce', 'kiss-woo-customer-order-search' ); ?></strong></td>
						<td><?php echo esc_html( isset( $result['stock_wc_search']['query_count'] ) ? $result['stock_wc_search']['query_count'] : 'N/A' ); ?></td>
						<td><?php echo esc_html( isset( $result['stock_wc_search']['total_time'] ) ? number_format( $result['stock_wc_search']['total_time'], 3 ) . 's' : 'N/A' ); ?></td>
						<td><?php echo esc_html( isset( $result['stock_wc_search']['memory_peak'] ) ? $this->format_bytes( $result['stock_wc_search']['memory_peak'] ) : 'N/A' ); ?></td>
						<td><?php echo esc_html( isset( $result['stock_wc_search']['result_count'] ) ? $result['stock_wc_search']['result_count'] : 'N/A' ); ?></td>
					</tr>
				<?php else : ?>
					<tr style="background: #fff3cd;">
						<td colspan="5">
							<strong><?php esc_html_e( 'Stock WooCommerce', 'kiss-woo-customer-order-search' ); ?></strong>
							<em style="color: #856404;"><?php esc_html_e( ' - Skipped for memory safety (causes >512MB memory exhaustion)', 'kiss-woo-customer-order-search' ); ?></em>
						</td>
					</tr>
				<?php endif; ?>
				<tr>
					<td><strong><?php esc_html_e( 'Stock WordPress', 'kiss-woo-customer-order-search' ); ?></strong></td>
					<td><?php echo esc_html( isset( $result['stock_wp_user_search']['query_count'] ) ? $result['stock_wp_user_search']['query_count'] : 'N/A' ); ?></td>
					<td><?php echo esc_html( isset( $result['stock_wp_user_search']['total_time'] ) ? number_format( $result['stock_wp_user_search']['total_time'], 3 ) . 's' : 'N/A' ); ?></td>
					<td><?php echo esc_html( isset( $result['stock_wp_user_search']['memory_peak'] ) ? $this->format_bytes( $result['stock_wp_user_search']['memory_peak'] ) : 'N/A' ); ?></td>
					<td><?php echo esc_html( isset( $result['stock_wp_user_search']['result_count'] ) ? $result['stock_wp_user_search']['result_count'] : 'N/A' ); ?></td>
				</tr>
				<tr style="background: #d4edda;">
					<td><strong><?php esc_html_e( 'Hypercart Fast Search', 'kiss-woo-customer-order-search' ); ?></strong></td>
					<td><?php echo esc_html( isset( $result['hypercart_current']['query_count'] ) ? $result['hypercart_current']['query_count'] : 'N/A' ); ?></td>
					<td><?php echo esc_html( isset( $result['hypercart_current']['total_time'] ) ? number_format( $result['hypercart_current']['total_time'], 3 ) . 's' : 'N/A' ); ?></td>
					<td><?php echo esc_html( isset( $result['hypercart_current']['memory_peak'] ) ? $this->format_bytes( $result['hypercart_current']['memory_peak'] ) : 'N/A' ); ?></td>
					<td><?php echo esc_html( isset( $result['hypercart_current']['result_count'] ) ? $result['hypercart_current']['result_count'] : 'N/A' ); ?></td>
				</tr>
			</tbody>
		</table>

		<h3 style="margin-top: 20px;"><?php esc_html_e( 'Performance Improvement', 'kiss-woo-customer-order-search' ); ?></h3>
		<div style="margin: 15px 0;">
			<?php if ( isset( $result['improvement_vs_stock_wc'] ) ) : ?>
				<div style="margin-bottom: 15px;">
					<strong><?php esc_html_e( 'vs Stock WooCommerce:', 'kiss-woo-customer-order-search' ); ?></strong><br>
					<span class="improvement-badge"><?php echo esc_html( number_format( $result['improvement_vs_stock_wc']['query_reduction'], 1 ) ); ?>x</span> <?php esc_html_e( 'fewer queries', 'kiss-woo-customer-order-search' ); ?>
					&nbsp;
					<span class="improvement-badge"><?php echo esc_html( number_format( $result['improvement_vs_stock_wc']['speed_improvement'], 1 ) ); ?>x</span> <?php esc_html_e( 'faster', 'kiss-woo-customer-order-search' ); ?>
					&nbsp;
					<span class="improvement-badge"><?php echo esc_html( number_format( $result['improvement_vs_stock_wc']['memory_reduction'], 1 ) ); ?>x</span> <?php esc_html_e( 'less memory', 'kiss-woo-customer-order-search' ); ?>
				</div>
			<?php endif; ?>
		</div>

		<h3><?php esc_html_e( 'Performance Gates', 'kiss-woo-customer-order-search' ); ?></h3>
		<div style="margin: 15px 0;">
			<?php
			$gates_passed = 0;
			$gates_total  = 4;

			// Gate 1: 10x faster
			$speed_pass = isset( $result['improvement_vs_stock_wc']['speed_improvement'] ) && $result['improvement_vs_stock_wc']['speed_improvement'] >= 10;
			if ( $speed_pass ) {
				$gates_passed++;
			}
			?>
			<div class="performance-metric <?php echo $speed_pass ? 'pass' : 'fail'; ?>">
				<?php echo $speed_pass ? '✅' : '❌'; ?>
				<?php esc_html_e( '10x faster than stock WC', 'kiss-woo-customer-order-search' ); ?>
				<?php if ( isset( $result['improvement_vs_stock_wc']['speed_improvement'] ) ) : ?>
					(<?php echo esc_html( number_format( $result['improvement_vs_stock_wc']['speed_improvement'], 1 ) ); ?>x)
				<?php endif; ?>
			</div>

			<?php
			// Gate 2: <10 queries
			$query_pass = isset( $result['hypercart_current']['query_count'] ) && $result['hypercart_current']['query_count'] < 10;
			if ( $query_pass ) {
				$gates_passed++;
			}
			?>
			<div class="performance-metric <?php echo $query_pass ? 'pass' : 'fail'; ?>">
				<?php echo $query_pass ? '✅' : '❌'; ?>
				<?php esc_html_e( '<10 queries', 'kiss-woo-customer-order-search' ); ?>
				<?php if ( isset( $result['hypercart_current']['query_count'] ) ) : ?>
					(<?php echo esc_html( $result['hypercart_current']['query_count'] ); ?>)
				<?php endif; ?>
			</div>

			<?php
			// Gate 3: <50MB memory
			$memory_mb   = isset( $result['hypercart_current']['memory_peak'] ) ? $result['hypercart_current']['memory_peak'] / 1024 / 1024 : 0;
			$memory_pass = $memory_mb < 50;
			if ( $memory_pass ) {
				$gates_passed++;
			}
			?>
			<div class="performance-metric <?php echo $memory_pass ? 'pass' : 'fail'; ?>">
				<?php echo $memory_pass ? '✅' : '❌'; ?>
				<?php esc_html_e( '<50MB memory', 'kiss-woo-customer-order-search' ); ?>
				(<?php echo esc_html( number_format( $memory_mb, 1 ) ); ?>MB)
			</div>

			<?php
			// Gate 4: <2s execution
			$time_pass = isset( $result['hypercart_current']['total_time'] ) && $result['hypercart_current']['total_time'] < 2.0;
			if ( $time_pass ) {
				$gates_passed++;
			}
			?>
			<div class="performance-metric <?php echo $time_pass ? 'pass' : 'fail'; ?>">
				<?php echo $time_pass ? '✅' : '❌'; ?>
				<?php esc_html_e( '<2s execution', 'kiss-woo-customer-order-search' ); ?>
				<?php if ( isset( $result['hypercart_current']['total_time'] ) ) : ?>
					(<?php echo esc_html( number_format( $result['hypercart_current']['total_time'], 3 ) ); ?>s)
				<?php endif; ?>
			</div>
		</div>

		<?php if ( $gates_passed === $gates_total ) : ?>
			<div class="notice notice-success inline" style="margin: 15px 0;">
				<p><strong><?php esc_html_e( '✅ All performance gates passed!', 'kiss-woo-customer-order-search' ); ?></strong></p>
			</div>
		<?php else : ?>
			<div class="notice notice-warning inline" style="margin: 15px 0;">
				<p><strong><?php printf( esc_html__( '⚠️ %d of %d performance gates passed', 'kiss-woo-customer-order-search' ), $gates_passed, $gates_total ); ?></strong></p>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render history table
	 *
	 * @param array $history Benchmark history
	 */
	protected function render_history_table( $history ) {
		// Reverse to show newest first
		$history = array_reverse( $history );
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'kiss-woo-customer-order-search' ); ?></th>
					<th><?php esc_html_e( 'Scenario', 'kiss-woo-customer-order-search' ); ?></th>
					<th><?php esc_html_e( 'Queries', 'kiss-woo-customer-order-search' ); ?></th>
					<th><?php esc_html_e( 'Time', 'kiss-woo-customer-order-search' ); ?></th>
					<th><?php esc_html_e( 'Improvement', 'kiss-woo-customer-order-search' ); ?></th>
					<th><?php esc_html_e( 'Gates', 'kiss-woo-customer-order-search' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $history as $result ) : ?>
					<tr>
						<td><?php echo esc_html( isset( $result['timestamp'] ) ? $result['timestamp'] : 'N/A' ); ?></td>
						<td>
							<?php
							if ( isset( $result['scenario']['description'] ) ) {
								echo esc_html( $result['scenario']['description'] );
							} else {
								echo esc_html( isset( $result['scenario_key'] ) ? $result['scenario_key'] : 'N/A' );
							}
							?>
						</td>
						<td><?php echo esc_html( isset( $result['hypercart_current']['query_count'] ) ? $result['hypercart_current']['query_count'] : 'N/A' ); ?></td>
						<td><?php echo esc_html( isset( $result['hypercart_current']['total_time'] ) ? number_format( $result['hypercart_current']['total_time'], 3 ) . 's' : 'N/A' ); ?></td>
						<td>
							<?php if ( isset( $result['improvement_vs_stock_wc']['speed_improvement'] ) ) : ?>
								<?php echo esc_html( number_format( $result['improvement_vs_stock_wc']['speed_improvement'], 1 ) ); ?>x
							<?php else : ?>
								N/A
							<?php endif; ?>
						</td>
						<td>
							<?php
							// Calculate gates passed
							$gates_passed = 0;
							if ( isset( $result['improvement_vs_stock_wc']['speed_improvement'] ) && $result['improvement_vs_stock_wc']['speed_improvement'] >= 10 ) {
								$gates_passed++;
							}
							if ( isset( $result['hypercart_current']['query_count'] ) && $result['hypercart_current']['query_count'] < 10 ) {
								$gates_passed++;
							}
							if ( isset( $result['hypercart_current']['memory_peak'] ) && ( $result['hypercart_current']['memory_peak'] / 1024 / 1024 ) < 50 ) {
								$gates_passed++;
							}
							if ( isset( $result['hypercart_current']['total_time'] ) && $result['hypercart_current']['total_time'] < 2.0 ) {
								$gates_passed++;
							}
							echo esc_html( $gates_passed . '/4' );
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Format bytes to human readable
	 *
	 * @param int $bytes Bytes
	 * @return string Formatted string
	 */
	protected function format_bytes( $bytes ) {
		if ( $bytes >= 1073741824 ) {
			return number_format( $bytes / 1073741824, 2 ) . ' GB';
		} elseif ( $bytes >= 1048576 ) {
			return number_format( $bytes / 1048576, 2 ) . ' MB';
		} elseif ( $bytes >= 1024 ) {
			return number_format( $bytes / 1024, 2 ) . ' KB';
		} else {
			return $bytes . ' B';
		}
	}
}
