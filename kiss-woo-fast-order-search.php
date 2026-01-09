<?php
/**
 * Plugin Name: KISS - Faster Customer & Order Search
 * Description: Super-fast customer and WooCommerce order search for support teams. Search by email or name in one simple admin screen.
 * Version: 1.1.7
 * Author: Vishal Kharche
 * Text Domain: kiss-woo-customer-order-search
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'KISS_WOO_COS_VERSION' ) ) {
    define( 'KISS_WOO_COS_VERSION', '1.1.7' );
}
if ( ! defined( 'KISS_WOO_COS_PATH' ) ) {
    define( 'KISS_WOO_COS_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'KISS_WOO_COS_URL' ) ) {
    define( 'KISS_WOO_COS_URL', plugin_dir_url( __FILE__ ) );
}

class KISS_Woo_Customer_Order_Search_Plugin {

    /**
     * Singleton instance.
     *
     * @var KISS_Woo_Customer_Order_Search_Plugin|null
     */
    protected static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return KISS_Woo_Customer_Order_Search_Plugin
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        // Check WooCommerce.
        add_action( 'plugins_loaded', array( $this, 'maybe_bootstrap' ) );
        // Add settings link to plugins page.
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_settings_link' ) );
    }

    /**
     * Load plugin only if WooCommerce is active.
     */
    public function maybe_bootstrap() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'missing_wc_notice' ) );
            return;
        }

        // Include core files.
        require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-debug-tracer.php';
        require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-utils.php';
        require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-search-cache.php';
        require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-order-formatter.php';
        require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-order-resolver.php';
        require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-search.php';
        require_once KISS_WOO_COS_PATH . 'admin/class-kiss-woo-admin-page.php';
        require_once KISS_WOO_COS_PATH . 'admin/class-kiss-woo-settings.php';
        require_once KISS_WOO_COS_PATH . 'admin/class-kiss-woo-debug-panel.php';

        // Initialize debug tracer (must be first for observability).
        KISS_Woo_Debug_Tracer::init();

        // Floating toolbar integration (admin only).
        if ( is_admin() ) {
            require_once KISS_WOO_COS_PATH . 'toolbar.php';
        }

        // Init admin page and settings.
        KISS_Woo_COS_Admin_Page::instance();
        KISS_Woo_COS_Settings::instance();

        // Init debug panel (only shows if KISS_WOO_FAST_SEARCH_DEBUG is true).
        $debug_panel = new KISS_Woo_Debug_Panel();
        $debug_panel->register();

        // Register AJAX handler.
        add_action( 'wp_ajax_kiss_woo_customer_search', array( $this, 'handle_ajax_search' ) );

        // Register diagnostic endpoint (only when debug is enabled).
        if ( defined( 'KISS_WOO_FAST_SEARCH_DEBUG' ) && KISS_WOO_FAST_SEARCH_DEBUG ) {
            add_action( 'admin_init', array( $this, 'maybe_run_diagnostic' ) );
        }
    }

    /**
     * Run diagnostic if requested via URL parameter.
     *
     * Access via: /wp-admin/?kiss_diag=1&order=B331580
     */
    public function maybe_run_diagnostic() {
        if ( ! isset( $_GET['kiss_diag'] ) ) {
            return;
        }

        $diag_file = KISS_WOO_COS_PATH . 'tests/diagnostic-order-search.php';
        if ( file_exists( $diag_file ) ) {
            include $diag_file;
            exit;
        }
    }

    /**
     * Admin notice if WooCommerce is missing.
     */
    public function missing_wc_notice() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        echo '<div class="notice notice-error"><p>';
        esc_html_e( 'KISS - Faster Customer & Order Search requires WooCommerce to be installed and active.', 'kiss-woo-customer-order-search' );
        echo '</p></div>';
    }

    /**
     * Add settings link to plugin action links.
     *
     * @param array $links
     * @return array
     */
    public function add_settings_link( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=kiss-woo-cos-settings' ) . '">' . __( 'Settings', 'kiss-woo-customer-order-search' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Handle AJAX request for customer & order search.
     */
    public function handle_ajax_search() {
        KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'request_start', array(
            'action' => 'kiss_woo_customer_search',
        ) );

        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'auth_failed', array(
                'user_id' => get_current_user_id(),
            ), 'warn' );
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'kiss-woo-customer-order-search' ) ), 403 );
        }

        if ( ! check_ajax_referer( 'kiss_woo_cos_search', 'nonce', false ) ) {
            KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'nonce_failed', array(), 'warn' );
            wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'kiss-woo-customer-order-search' ) ), 403 );
        }

        $term = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';

        if ( strlen( $term ) < 2 ) {
            wp_send_json_error( array( 'message' => __( 'Please enter at least 2 characters.', 'kiss-woo-customer-order-search' ) ) );
        }

        KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'search_term', array(
            'term'   => $term,
            'length' => strlen( $term ),
        ) );

        $t_start = microtime( true );

        // Initialize search components.
        $search         = new KISS_Woo_COS_Search();
        $cache          = new KISS_Woo_Search_Cache();
        $order_resolver = new KISS_Woo_Order_Resolver( $cache );

        // Customer search.
        $done       = KISS_Woo_Debug_Tracer::start_timer( 'AjaxHandler', 'customer_search' );
        $customers  = $search->search_customers( $term );
        $done( array( 'count' => count( $customers ) ) );

        // Guest order search.
        $done         = KISS_Woo_Debug_Tracer::start_timer( 'AjaxHandler', 'guest_search' );
        $guest_orders = $search->search_guest_orders_by_email( $term );
        $done( array( 'count' => count( $guest_orders ) ) );

        // Order number search (if term looks like an order number).
        $orders                   = array();
        $should_redirect_to_order = false;
        $redirect_url             = null;

        if ( $order_resolver->looks_like_order_number( $term ) ) {
            $done       = KISS_Woo_Debug_Tracer::start_timer( 'AjaxHandler', 'order_search' );
            $resolution = $order_resolver->resolve( $term );

            if ( $resolution['order'] ) {
                $formatted = KISS_Woo_Order_Formatter::format( $resolution['order'] );

                // Legacy alias (one version): older JS/tests used `number`.
                if ( ! isset( $formatted['number'] ) ) {
                    $formatted['number'] = (string) $formatted['order_number'];
                }

                $orders                   = array( $formatted );
                $should_redirect_to_order = true;
                $redirect_url             = $formatted['view_url'];

                $done( array( 'found' => true, 'order_id' => $formatted['id'] ) );
            } else {
                $done( array( 'found' => false, 'source' => $resolution['source'] ) );
            }
        } else {
            KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'order_search_skipped', array(
                'reason' => 'Term does not look like order number',
                'term'   => $term,
            ) );
        }

        $elapsed_seconds = round( microtime( true ) - $t_start, 2 );
        $elapsed_ms      = round( ( microtime( true ) - $t_start ) * 1000, 2 );

        KISS_Woo_Debug_Tracer::log( 'AjaxHandler', 'request_complete', array(
            'elapsed_ms'     => $elapsed_ms,
            'customer_count' => count( $customers ),
            'guest_count'    => count( $guest_orders ),
            'order_count'    => count( $orders ),
        ) );

        // Build response.
        $response = array(
            'customers'                => $customers,
            'guest_orders'             => $guest_orders,
            'orders'                   => $orders,
            'should_redirect_to_order' => $should_redirect_to_order,
            'redirect_url'             => $redirect_url,
            'search_time'              => $elapsed_seconds,
            'search_time_ms'           => $elapsed_ms,
        );

        // Add debug data if enabled.
        if ( defined( 'KISS_WOO_FAST_SEARCH_DEBUG' ) && KISS_WOO_FAST_SEARCH_DEBUG ) {
            $response['debug'] = array(
                'traces'         => KISS_Woo_Debug_Tracer::get_traces(),
                'memory_peak_mb' => round( memory_get_peak_usage() / 1024 / 1024, 2 ),
                'php_version'    => PHP_VERSION,
                'wc_version'     => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
            );
        }

        wp_send_json_success( $response );
    }
}

// Bootstrap.
KISS_Woo_Customer_Order_Search_Plugin::instance();
