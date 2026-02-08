<?php
/**
 * Self-test page for diagnosing order URL generation and redirect issues.
 *
 * @package KISS_Woo_Customer_Order_Search
 * @since   1.1.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KISS_Woo_Self_Test {

    /**
     * Register the self-test page.
     */
    public function register(): void {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
    }

    /**
     * Add submenu page under WooCommerce.
     */
    public function add_menu_page(): void {
        add_submenu_page(
            'woocommerce',
            __( 'KISS Self-Test', 'kiss-woo-customer-order-search' ),
            __( 'KISS Self-Test', 'kiss-woo-customer-order-search' ),
            'manage_woocommerce',
            'kiss-woo-self-test',
            array( $this, 'render_page' )
        );
    }

    /**
     * Render self-test page.
     */
    public function render_page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'kiss-woo-customer-order-search' ) );
        }

        // Get a recent order for testing
        $test_order = $this->get_test_order();

        ?>
        <div class="wrap kiss-self-test">
            <h1><?php esc_html_e( 'KISS Order Search Self-Test', 'kiss-woo-customer-order-search' ); ?></h1>
            
            <div class="notice notice-info">
                <p><strong>Purpose:</strong> This page helps diagnose order URL generation and redirect issues.</p>
            </div>

            <?php $this->render_system_status(); ?>

            <?php if ( $test_order ) : ?>
                <?php $this->render_url_tests( $test_order ); ?>
                <?php $this->render_ajax_test( $test_order ); ?>
            <?php else : ?>
                <div class="notice notice-warning">
                    <p><strong>No orders found.</strong> Create at least one order to run the tests.</p>
                </div>
            <?php endif; ?>

            <?php $this->render_wholesale_tests(); ?>

            <?php $this->render_styles(); ?>
        </div>
        <?php
    }

    /**
     * Get a test order.
     */
    private function get_test_order() {
        $orders = wc_get_orders( array(
            'limit'   => 1,
            'orderby' => 'date',
            'order'   => 'DESC',
        ) );

        return ! empty( $orders ) ? $orders[0] : null;
    }

    /**
     * Render system status section.
     */
    private function render_system_status(): void {
        $hpos_enabled = class_exists( 'KISS_Woo_Utils' )
            ? KISS_Woo_Utils::is_hpos_enabled()
            : false;

        $seq_pro_active = function_exists( 'wc_seq_order_number_pro' );
        
        ?>
        <div class="test-section">
            <h2>System Status</h2>
            <table class="widefat">
                <tr>
                    <th>WooCommerce Version</th>
                    <td><?php echo esc_html( defined( 'WC_VERSION' ) ? WC_VERSION : 'Unknown' ); ?></td>
                </tr>
                <tr>
                    <th>HPOS Enabled</th>
                    <td>
                        <?php if ( $hpos_enabled ) : ?>
                            <span class="status-yes">✓ YES</span>
                            <p class="description">Orders are stored in custom tables (wp_wc_orders)</p>
                        <?php else : ?>
                            <span class="status-no">✗ NO</span>
                            <p class="description">Orders are stored as WordPress posts</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Sequential Order Numbers Pro</th>
                    <td>
                        <?php if ( $seq_pro_active ) : ?>
                            <span class="status-yes">✓ Active</span>
                        <?php else : ?>
                            <span class="status-no">✗ Not Active</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Debug Mode</th>
                    <td>
                        <?php if ( defined( 'KISS_WOO_FAST_SEARCH_DEBUG' ) && KISS_WOO_FAST_SEARCH_DEBUG ) : ?>
                            <span class="status-yes">✓ Enabled</span>
                        <?php else : ?>
                            <span class="status-no">✗ Disabled</span>
                        <?php endif; ?>
                    </td>
            </table>
        </div>
        <?php
    }

    /**
     * Render URL generation tests.
     */
    private function render_url_tests( $order ): void {
        $order_id     = $order->get_id();
        $order_number = $order->get_order_number();

        // Test different URL generation methods
        $url_method_1 = method_exists( $order, 'get_edit_order_url' ) ? $order->get_edit_order_url() : 'Method not available';
        $url_method_2 = get_edit_post_link( $order_id, 'raw' );
        $url_method_3 = admin_url( 'post.php?post=' . $order_id . '&action=edit' );

        $hpos_enabled = class_exists( 'KISS_Woo_Utils' )
            ? KISS_Woo_Utils::is_hpos_enabled()
            : false;

        $url_method_4 = $hpos_enabled
            ? admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id )
            : admin_url( 'post.php?post=' . $order_id . '&action=edit' );

        // Get the URL from our formatter
        $formatter_url = KISS_Woo_Order_Formatter::format( $order )['view_url'];

        ?>
        <div class="test-section">
            <h2>URL Generation Tests</h2>
            <p><strong>Test Order:</strong> #<?php echo esc_html( $order_number ); ?> (ID: <?php echo esc_html( $order_id ); ?>)</p>

            <table class="widefat">
                <thead>
                    <tr>
                        <th>Method</th>
                        <th>Generated URL</th>
                        <th>Test</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>1. $order->get_edit_order_url()</strong><br><small>Recommended (HPOS-aware)</small></td>
                        <td><code><?php echo esc_html( $url_method_1 ); ?></code></td>
                        <td>
                            <?php if ( $url_method_1 !== 'Method not available' ) : ?>
                                <a href="<?php echo esc_url( $url_method_1 ); ?>" target="_blank" class="button button-small">Test →</a>
                            <?php else : ?>
                                <span class="status-no">Not Available</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>2. get_edit_post_link()</strong><br><small>WordPress function</small></td>
                        <td><code><?php echo esc_html( $url_method_2 ? $url_method_2 : 'NULL/Empty' ); ?></code></td>
                        <td>
                            <?php if ( $url_method_2 ) : ?>
                                <a href="<?php echo esc_url( $url_method_2 ); ?>" target="_blank" class="button button-small">Test →</a>
                            <?php else : ?>
                                <span class="status-no">Empty</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>3. admin_url( post.php )</strong><br><small>Legacy fallback</small></td>
                        <td><code><?php echo esc_html( $url_method_3 ); ?></code></td>
                        <td><a href="<?php echo esc_url( $url_method_3 ); ?>" target="_blank" class="button button-small">Test →</a></td>
                    </tr>
                    <tr>
                        <td><strong>4. HPOS-aware manual</strong><br><small>Based on HPOS detection</small></td>
                        <td><code><?php echo esc_html( $url_method_4 ); ?></code></td>
                        <td><a href="<?php echo esc_url( $url_method_4 ); ?>" target="_blank" class="button button-small">Test →</a></td>
                    </tr>
                    <tr class="highlight">
                        <td><strong>5. KISS_Woo_Order_Formatter</strong><br><small>Currently used by plugin</small></td>
                        <td><code><?php echo esc_html( $formatter_url ); ?></code></td>
                        <td><a href="<?php echo esc_url( $formatter_url ); ?>" target="_blank" class="button button-primary button-small">Test →</a></td>
                    </tr>
                </tbody>
            </table>

            <div class="notice notice-info inline">
                <p><strong>Instructions:</strong> Click each "Test →" button to verify which URL works correctly. The correct URL should take you to the order editor, not the "All Posts" page.</p>
            </div>
        </div>
        <?php
    }

    /**
     * Render AJAX test section.
     */
    private function render_ajax_test( $order ): void {
        $order_number = $order->get_order_number();
        $search_url   = admin_url( 'admin.php?page=kiss-woo-customer-order-search&q=' . urlencode( $order_number ) );

        ?>
        <div class="test-section">
            <h2>Live Search Test</h2>
            <p>Test the actual search functionality with this order number:</p>

            <div class="ajax-test-box">
                <input type="text" id="kiss-test-search" value="<?php echo esc_attr( $order_number ); ?>" class="regular-text" readonly>
                <button type="button" id="kiss-run-search" class="button button-primary">Run Search Test</button>
                <a href="<?php echo esc_url( $search_url ); ?>" class="button">Open Search Page</a>
            </div>

            <div id="kiss-test-results" style="margin-top: 20px;"></div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#kiss-run-search').on('click', function() {
                var $btn = $(this);
                var $results = $('#kiss-test-results');
                var searchTerm = $('#kiss-test-search').val();

                $btn.prop('disabled', true).text('Testing...');
                $results.html('<p>Running search for: <strong>' + searchTerm + '</strong></p>');

                $.ajax({
                    url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
                    method: 'POST',
                    data: {
                        action: 'kiss_woo_customer_search',
                        nonce: '<?php echo esc_js( wp_create_nonce( 'kiss_woo_cos_search' ) ); ?>',
                        q: searchTerm
                    },
                    dataType: 'json'
                }).done(function(resp) {
                    console.log('AJAX Response:', resp);

                    var html = '<div class="notice notice-success"><p><strong>✓ AJAX Request Successful</strong></p></div>';
                    html += '<table class="widefat"><tr><th>Property</th><th>Value</th></tr>';

                    if (resp.success && resp.data) {
                        html += '<tr><td>should_redirect_to_order</td><td><code>' + (resp.data.should_redirect_to_order ? 'true' : 'false') + '</code></td></tr>';
                        html += '<tr><td>redirect_url</td><td><code>' + (resp.data.redirect_url || 'null') + '</code></td></tr>';
                        html += '<tr><td>orders found</td><td><code>' + (resp.data.orders ? resp.data.orders.length : 0) + '</code></td></tr>';

                        if (resp.data.redirect_url) {
                            html += '</table>';
                            html += '<div style="margin-top: 15px;">';
                            html += '<p><strong>Redirect URL:</strong></p>';
                            html += '<p><code style="background: #f0f0f0; padding: 10px; display: block;">' + resp.data.redirect_url + '</code></p>';
                            html += '<a href="' + resp.data.redirect_url + '" target="_blank" class="button button-primary">Test This URL →</a>';
                            html += '</div>';
                        } else {
                            html += '</table>';
                        }

                        if (resp.data.debug) {
                            html += '<details style="margin-top: 15px;"><summary><strong>Debug Data</strong></summary>';
                            html += '<pre style="background: #f0f0f0; padding: 10px; overflow: auto;">' + JSON.stringify(resp.data.debug, null, 2) + '</pre>';
                            html += '</details>';
                        }
                    } else {
                        html += '<tr><td colspan="2">No data returned</td></tr></table>';
                    }

                    $results.html(html);
                }).fail(function(xhr, status, error) {
                    $results.html('<div class="notice notice-error"><p><strong>✗ AJAX Request Failed</strong></p><p>' + error + '</p></div>');
                }).always(function() {
                    $btn.prop('disabled', false).text('Run Search Test');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render styles.
     */
    private function render_styles(): void {
        ?>
        <style>
            .kiss-self-test { max-width: 1200px; }
            .test-section {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 20px;
                margin: 20px 0;
                border-radius: 4px;
            }
            .test-section h2 { margin-top: 0; }
            .status-yes { color: #46b450; font-weight: bold; }
            .status-no { color: #dc3232; font-weight: bold; }
            .highlight { background: #fffbcc; }
            .ajax-test-box {
                background: #f8f9fa;
                padding: 15px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .ajax-test-box input { margin-right: 10px; }
            .notice.inline { margin-top: 15px; }
            code {
                background: #f0f0f0;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 12px;
                word-break: break-all;
            }
            table.widefat th { width: 200px; }
        </style>
        <?php
    }

    // ========================================================================
    // Wholesale Listing Self-Tests (v1.2.5)
    // ========================================================================

    /**
     * Render wholesale tests section.
     */
    private function render_wholesale_tests(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only test trigger.
        $run_tests = isset( $_GET['run_wholesale_tests'] ) && '1' === $_GET['run_wholesale_tests'];

        ?>
        <div class="test-section">
            <h2>🏷️ Wholesale Listing Tests (v1.2.5)</h2>
            <p>Comprehensive regression tests for wholesale order listing, pagination, and performance.</p>

            <p>
                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'run_wholesale_tests', '1' ), 'kiss_woo_wholesale_tests' ) ); ?>" class="button button-primary">
                    🧪 Run Wholesale Tests
                </a>
            </p>

            <?php if ( $run_tests ) : ?>
                <?php check_admin_referer( 'kiss_woo_wholesale_tests' ); ?>
                <?php $test_results = $this->run_wholesale_tests(); ?>

                <table class="widefat" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th>Test Name</th>
                            <th>Status</th>
                            <th>Message</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $test_results as $result ) : ?>
                            <?php
                            $status_class = 'pass' === $result['status'] ? 'status-yes' : ( 'warn' === $result['status'] ? 'status-warning' : 'status-no' );
                            $status_icon  = 'pass' === $result['status'] ? '✓' : ( 'warn' === $result['status'] ? '⚠' : '✗' );
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html( $result['name'] ); ?></strong></td>
                                <td><span class="<?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_icon . ' ' . ucfirst( $result['status'] ) ); ?></span></td>
                                <td><?php echo esc_html( $result['message'] ); ?></td>
                                <td>
                                    <?php if ( ! empty( $result['details'] ) ) : ?>
                                        <code><?php echo esc_html( wp_json_encode( $result['details'], JSON_PRETTY_PRINT ) ); ?></code>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <div class="notice notice-info inline">
                    <p><strong>Available Tests:</strong></p>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li><strong>Wholesale Detection:</strong> Verify wholesale order detection logic</li>
                        <li><strong>Pagination:</strong> Test page 1, page 2, and invalid page handling</li>
                        <li><strong>Performance:</strong> Measure query time (< 500ms threshold for 100 orders)</li>
                        <li><strong>Empty Results:</strong> Handle zero wholesale orders gracefully</li>
                        <li><strong>SQL Syntax:</strong> Verify correct meta FK column (post_id for legacy, order_id for HPOS)</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Run wholesale listing self-tests.
     *
     * @return array Test results.
     */
    private function run_wholesale_tests(): array {
        $results = array();

        $results[] = $this->test_wholesale_detection();
        $results[] = $this->test_wholesale_pagination();
        $results[] = $this->test_wholesale_performance();
        $results[] = $this->test_wholesale_empty();
        $results[] = $this->test_wholesale_sql_syntax();

        return $results;
    }

    /**
     * Test wholesale order detection.
     *
     * @return array Test result.
     */
    private function test_wholesale_detection(): array {
        $t_start = microtime( true );

        try {
            $query   = new KISS_Woo_Order_Query();
            $results = $query->query_orders( 'wholesale', 1, 10 );

            $elapsed_ms = round( ( microtime( true ) - $t_start ) * 1000, 2 );

            // Verify structure.
            $has_structure = isset( $results['orders'], $results['total'], $results['pages'], $results['current_page'] );

            return array(
                'name'    => 'Wholesale Detection',
                'status'  => $has_structure ? 'pass' : 'fail',
                'message' => $has_structure ? "Found {$results['total']} wholesale orders in {$elapsed_ms}ms" : 'Invalid response structure',
                'details' => array(
                    'total_orders' => $results['total'] ?? 0,
                    'pages'        => $results['pages'] ?? 0,
                    'elapsed_ms'   => $elapsed_ms,
                ),
            );
        } catch ( Exception $e ) {
            return array(
                'name'    => 'Wholesale Detection',
                'status'  => 'error',
                'message' => $e->getMessage(),
                'details' => array(),
            );
        }
    }

    /**
     * Test wholesale pagination.
     *
     * @return array Test result.
     */
    private function test_wholesale_pagination(): array {
        $t_start = microtime( true );

        try {
            $query = new KISS_Woo_Order_Query();

            // Test page 1.
            $page1 = $query->query_orders( 'wholesale', 1, 10 );

            // Test page 2 (if exists).
            $page2 = $query->query_orders( 'wholesale', 2, 10 );

            // Test invalid page.
            $page999 = $query->query_orders( 'wholesale', 999, 10 );

            $elapsed_ms = round( ( microtime( true ) - $t_start ) * 1000, 2 );

            $page1_valid   = isset( $page1['current_page'] ) && 1 === $page1['current_page'];
            $page2_valid   = isset( $page2['current_page'] ) && 2 === $page2['current_page'];
            $page999_valid = isset( $page999['orders'] ) && is_array( $page999['orders'] );

            $all_valid = $page1_valid && $page2_valid && $page999_valid;

            return array(
                'name'    => 'Wholesale Pagination',
                'status'  => $all_valid ? 'pass' : 'fail',
                'message' => $all_valid ? "Pagination working correctly ({$elapsed_ms}ms)" : 'Pagination structure invalid',
                'details' => array(
                    'page1_orders'   => count( $page1['orders'] ?? array() ),
                    'page2_orders'   => count( $page2['orders'] ?? array() ),
                    'page999_orders' => count( $page999['orders'] ?? array() ),
                    'elapsed_ms'     => $elapsed_ms,
                ),
            );
        } catch ( Exception $e ) {
            return array(
                'name'    => 'Wholesale Pagination',
                'status'  => 'error',
                'message' => $e->getMessage(),
                'details' => array(),
            );
        }
    }

    /**
     * Test wholesale performance.
     *
     * @return array Test result.
     */
    private function test_wholesale_performance(): array {
        $t_start = microtime( true );

        try {
            $query   = new KISS_Woo_Order_Query();
            $results = $query->query_orders( 'wholesale', 1, 100 );

            $elapsed_ms = round( ( microtime( true ) - $t_start ) * 1000, 2 );

            // Performance threshold: < 500ms for 100 orders.
            $is_fast = $elapsed_ms < 500;

            return array(
                'name'    => 'Wholesale Performance',
                'status'  => $is_fast ? 'pass' : 'warn',
                'message' => $is_fast ? "Query completed in {$elapsed_ms}ms (< 500ms threshold)" : "Query took {$elapsed_ms}ms (> 500ms threshold)",
                'details' => array(
                    'orders_fetched' => count( $results['orders'] ?? array() ),
                    'elapsed_ms'     => $elapsed_ms,
                    'threshold_ms'   => 500,
                    'hpos_enabled'   => KISS_Woo_Utils::is_hpos_enabled(),
                ),
            );
        } catch ( Exception $e ) {
            return array(
                'name'    => 'Wholesale Performance',
                'status'  => 'error',
                'message' => $e->getMessage(),
                'details' => array(),
            );
        }
    }

    /**
     * Test wholesale empty results.
     *
     * @return array Test result.
     */
    private function test_wholesale_empty(): array {
        $t_start = microtime( true );

        try {
            $query = new KISS_Woo_Order_Query();

            // Query with impossible page number to get empty results.
            $results = $query->query_orders( 'wholesale', 99999, 100 );

            $elapsed_ms = round( ( microtime( true ) - $t_start ) * 1000, 2 );

            $has_structure = isset( $results['orders'], $results['total'] );
            $is_empty      = 0 === count( $results['orders'] ?? array() );

            $is_valid = $has_structure && $is_empty;

            return array(
                'name'    => 'Wholesale Empty Results',
                'status'  => $is_valid ? 'pass' : 'fail',
                'message' => $is_valid ? "Empty results handled correctly ({$elapsed_ms}ms)" : 'Empty results structure invalid',
                'details' => array(
                    'orders_count' => count( $results['orders'] ?? array() ),
                    'total'        => $results['total'] ?? 0,
                    'elapsed_ms'   => $elapsed_ms,
                ),
            );
        } catch ( Exception $e ) {
            return array(
                'name'    => 'Wholesale Empty Results',
                'status'  => 'error',
                'message' => $e->getMessage(),
                'details' => array(),
            );
        }
    }

    /**
     * Test wholesale SQL syntax (legacy vs HPOS).
     *
     * Verifies that the correct meta foreign key column is used:
     * - Legacy mode (wp_postmeta): post_id
     * - HPOS mode (wp_wc_orders_meta): order_id
     *
     * @return array Test result.
     */
    private function test_wholesale_sql_syntax(): array {
        global $wpdb;
        $t_start = microtime( true );

        try {
            $is_hpos = KISS_Woo_Utils::is_hpos_enabled();

            // Use reflection to access private method for testing.
            $query      = new KISS_Woo_Order_Query();
            $reflection = new ReflectionClass( $query );
            $method     = $reflection->getMethod( 'get_wholesale_meta_condition' );
            $method->setAccessible( true );

            // Test with correct meta table.
            if ( $is_hpos ) {
                $meta_table = $wpdb->prefix . 'wc_orders_meta';
                $order_col  = 'o.id';
            } else {
                $meta_table = $wpdb->postmeta;
                $order_col  = 'p.ID';
            }

            $sql_condition = $method->invoke( $query, $meta_table, $order_col );

            $elapsed_ms = round( ( microtime( true ) - $t_start ) * 1000, 2 );

            // Verify correct foreign key column is used.
            $expected_fk = $is_hpos ? 'order_id' : 'post_id';
            $has_correct_fk = strpos( $sql_condition, "m.{$expected_fk}" ) !== false;

            // Verify wrong foreign key is NOT used.
            $wrong_fk = $is_hpos ? 'post_id' : 'order_id';
            $has_wrong_fk = strpos( $sql_condition, "m.{$wrong_fk}" ) !== false;

            $is_valid = $has_correct_fk && ! $has_wrong_fk;

            return array(
                'name'    => 'Wholesale SQL Syntax',
                'status'  => $is_valid ? 'pass' : 'fail',
                'message' => $is_valid
                    ? "Correct meta FK column used: m.{$expected_fk} ({$elapsed_ms}ms)"
                    : "Wrong meta FK column! Expected m.{$expected_fk}, SQL: " . substr( $sql_condition, 0, 100 ),
                'details' => array(
                    'hpos_enabled'  => $is_hpos,
                    'expected_fk'   => $expected_fk,
                    'has_correct'   => $has_correct_fk,
                    'has_wrong'     => $has_wrong_fk,
                    'sql_snippet'   => substr( $sql_condition, 0, 200 ),
                    'elapsed_ms'    => $elapsed_ms,
                ),
            );
        } catch ( Exception $e ) {
            return array(
                'name'    => 'Wholesale SQL Syntax',
                'status'  => 'error',
                'message' => $e->getMessage(),
                'details' => array(),
            );
        }
    }
}


