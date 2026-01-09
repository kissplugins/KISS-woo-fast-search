<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KISS_Woo_COS_Admin_Page {

    /**
     * Singleton instance.
     *
     * @var KISS_Woo_COS_Admin_Page|null
     */
    protected static $instance = null;

    /**
     * Get instance.
     *
     * @return KISS_Woo_COS_Admin_Page
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Slug of the admin page.
     *
     * @var string
     */
    protected $page_slug = 'kiss-woo-customer-order-search';

    /**
     * Constructor.
     */
    private function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Register admin menu page under WooCommerce.
     */
    public function register_menu() {
        $parent_slug = 'woocommerce';
        $page_title  = __( 'KISS Customer & Order Search', 'kiss-woo-customer-order-search' );
        $menu_title  = __( 'KISS Search', 'kiss-woo-customer-order-search' );
        $capability  = 'manage_woocommerce';
        $menu_slug   = $this->page_slug;
        $callback    = array( $this, 'render_page' );

        add_submenu_page(
            $parent_slug,
            $page_title,
            $menu_title,
            $capability,
            $menu_slug,
            $callback
        );

        add_submenu_page(
            $parent_slug,
            'KISS Benchmark',
            'KISS Benchmark',
            $capability,
            'kiss-benchmark',
            [$this, 'render_benchmark_page']
        );

    }

    /**
     * Enqueue JS & CSS for this page.
     *
     * @param string $hook
     */
    public function enqueue_assets( $hook ) {
        if ( false === strpos( $hook, $this->page_slug ) ) {
            return;
        }

        wp_enqueue_script(
            'kiss-woo-cos-admin',
            KISS_WOO_COS_URL . 'admin/kiss-woo-admin.js',
            array( 'jquery' ),
            KISS_WOO_COS_VERSION,
            true
        );

        wp_localize_script(
            'kiss-woo-cos-admin',
            'KISSCOS',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'kiss_woo_cos_search' ),
                'i18n'     => array(
                    'searching'  => __( 'Searching...', 'kiss-woo-customer-order-search' ),
                    'no_results' => __( 'No matching customers found.', 'kiss-woo-customer-order-search' ),
                    'guest_title'=> __( 'Guest Orders (no account)', 'kiss-woo-customer-order-search' ),
                ),
            )
        );
    }

    /**
     * Render admin page HTML.
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'kiss-woo-customer-order-search' ) );
        }
        ?>
        <div class="wrap kiss-cos-wrap">
            <h1><?php esc_html_e( 'KISS - Faster Customer & Order Search', 'kiss-woo-customer-order-search' ); ?></h1>

            <p class="description">
                <?php esc_html_e( 'Enter a customer email, partial email, or name to quickly find their account and orders.', 'kiss-woo-customer-order-search' ); ?>
            </p>

            <form id="kiss-cos-search-form" class="kiss-cos-search-form" action="#" method="get" autocomplete="off">
                <input type="text"
                       id="kiss-cos-search-input"
                       class="regular-text"
                       placeholder="<?php esc_attr_e( 'Type email or name and hit Enter…', 'kiss-woo-customer-order-search' ); ?>" />
                <button type="submit" class="button button-primary">
                    <?php esc_html_e( 'Search', 'kiss-woo-customer-order-search' ); ?>
                </button>
                <span id="kiss-cos-search-status" class="kiss-cos-search-status"></span>
            </form>
            <div id="kiss-cos-search-time" class="kiss-cos-search-time"></div>

            <div id="kiss-cos-results" class="kiss-cos-results">
                <!-- Results injected by JS -->
            </div>

            <style>
                .kiss-cos-wrap .kiss-cos-search-form {
                    margin-top: 15px;
                    margin-bottom: 20px;
                }
                .kiss-cos-search-form .regular-text {
                    min-width: 320px;
                }
                .kiss-cos-search-status {
                    margin-left: 10px;
                    font-style: italic;
                }
                .kiss-cos-search-time {
                    margin-top: 8px;
                    font-size: 12px;
                    color: #666;
                }
                .kiss-cos-results .kiss-cos-customer {
                    background: #fff;
                    border: 1px solid #ccd0d4;
                    padding: 12px 14px;
                    margin-bottom: 12px;
                    border-radius: 4px;
                }
                .kiss-cos-results .kiss-cos-customer-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 8px;
                }
                .kiss-cos-results .kiss-cos-customer-name {
                    font-weight: 600;
                    font-size: 14px;
                }
                .kiss-cos-results .kiss-cos-customer-meta {
                    font-size: 12px;
                    color: #666;
                }
                .kiss-cos-results table.kiss-cos-orders-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 8px;
                    font-size: 12px;
                }
                .kiss-cos-results table.kiss-cos-orders-table th,
                .kiss-cos-results table.kiss-cos-orders-table td {
                    border-bottom: 1px solid #eee;
                    padding: 4px 6px;
                    text-align: left;
                }
                .kiss-cos-results .kiss-cos-guest-orders {
                    margin-top: 25px;
                    background: #fff;
                    border: 1px solid #ccd0d4;
                    padding: 12px 14px;
                    margin-bottom: 12px;
                    border-radius: 4px;
                }
                .kiss-cos-results .kiss-cos-guest-orders h2 {
                    margin-bottom: 8px;
                }
                .kiss-status-pill {
                    display: inline-block;
                    padding: 2px 6px;
                    border-radius: 999px;
                    font-size: 11px;
                    line-height: 1.4;
                    background: #f1f1f1;
                }
                .kiss-cos-orders-table a {
                    font-weight: 600;
                    text-decoration: none;
                }
                .kiss-cos-orders-table a:hover {
                    text-decoration: underline;
                }

            </style>
        </div>
        <?php
    }

    public function render_benchmark_page() {
        require_once KISS_WOO_COS_PATH . 'admin/class-kiss-woo-benchmark.php';

        // Verify nonce if query parameter is present
        $query = 'vishal@neochro.me'; // Default query
        $results = null;

        if ( isset( $_GET['q'] ) && isset( $_GET['_wpnonce'] ) ) {
            if ( wp_verify_nonce( $_GET['_wpnonce'], 'kiss_benchmark_search' ) ) {
                $query = sanitize_text_field( $_GET['q'] );
                $results = KISS_Woo_COS_Benchmark::run_tests( $query );
            } else {
                wp_die( esc_html__( 'Security check failed. Please try again.', 'kiss-woo-customer-order-search' ) );
            }
        }
        ?>

        <div class="wrap">
            <h1>KISS Performance Benchmark</h1>

            <form method="GET">
                <input type="hidden" name="page" value="kiss-benchmark">
                <?php wp_nonce_field( 'kiss_benchmark_search', '_wpnonce', false ); ?>
                <input type="text" name="q" value="<?php echo esc_attr( $query ); ?>" placeholder="Enter email to test">
                <button class="button button-primary">Run Benchmark</button>
            </form>

            <?php if ( $results ) : ?>
                <h2>Results (milliseconds)</h2>

                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Test</th>
                            <th>Time (ms)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>WooCommerce Orders Search</td><td><?php echo esc_html( $results['wc_order_search_ms'] ); ?></td></tr>
                        <tr><td>WooCommerce User Search</td><td><?php echo esc_html( $results['wp_user_search_ms'] ); ?></td></tr>
                        <tr><td>KISS Customer Search</td><td><?php echo esc_html( $results['kiss_customer_search_ms'] ); ?></td></tr>
                        <tr><td>KISS Guest Order Search</td><td><?php echo esc_html( $results['kiss_guest_search_ms'] ); ?></td></tr>
                    </tbody>
                </table>

                <p><em>Lower = Faster. KISS should be significantly faster due to optimized lookups and pre-filtered queries.</em></p>
            <?php else : ?>
                <p><em>Enter an email address and click "Run Benchmark" to see performance comparison.</em></p>
            <?php endif; ?>
        </div>

        <?php
    }

}
