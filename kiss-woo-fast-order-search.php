<?php
/**
 * Plugin Name: KISS - Faster Customer & Order Search
 * Description: Super-fast customer and WooCommerce order search for support teams. Search by email or name in one simple admin screen.
 * Version: 1.0.0
 * Author: Vishal Kharche
 * Text Domain: kiss-woo-customer-order-search
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'KISS_WOO_COS_VERSION' ) ) {
    define( 'KISS_WOO_COS_VERSION', '1.0.0' );
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
    }

    /**
     * Load plugin only if WooCommerce is active.
     */
    public function maybe_bootstrap() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'missing_wc_notice' ) );
            return;
        }

        // Include files.
        require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-search.php';
        require_once KISS_WOO_COS_PATH . 'admin/class-kiss-woo-admin-page.php';

        // Init admin page.
        KISS_Woo_COS_Admin_Page::instance();

        // Register AJAX handler.
        add_action( 'wp_ajax_kiss_woo_customer_search', array( $this, 'handle_ajax_search' ) );
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
     * Handle AJAX request for customer & order search.
     */
    public function handle_ajax_search() {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'kiss-woo-customer-order-search' ) ), 403 );
        }

        check_ajax_referer( 'kiss_woo_cos_search', 'nonce' );

        $term = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';

        if ( strlen( $term ) < 2 ) {
            wp_send_json_error( array( 'message' => __( 'Please enter at least 2 characters.', 'kiss-woo-customer-order-search' ) ) );
        }

        $search = new KISS_Woo_COS_Search();

        $customers    = $search->search_customers( $term );
        $guest_orders = $search->search_guest_orders_by_email( $term );

        wp_send_json_success(
            array(
                'customers'    => $customers,
                'guest_orders' => $guest_orders,
            )
        );
    }
}

// Bootstrap.
KISS_Woo_Customer_Order_Search_Plugin::instance();
