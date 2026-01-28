<?php
/**
 * Plugin Name: KISS - Faster Customer & Order Search
 * Description: Super-fast customer and WooCommerce order search for support teams. Search by email or name in one simple admin screen.
 * Version: 1.2.5
 * Author: Vishal Kharche
 * Text Domain: kiss-woo-customer-order-search
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'KISS_WOO_COS_VERSION' ) ) {
    define( 'KISS_WOO_COS_VERSION', '1.2.5' );
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
        require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-coupon-formatter.php';
        require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-coupon-search.php';
        require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-order-formatter.php';
        require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-order-resolver.php';
        require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-search.php';
        require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-coupon-lookup.php';
        require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-coupon-backfill.php';
        require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-coupon-cli.php';
        require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-ajax-handler.php';
        require_once KISS_WOO_COS_PATH . 'admin/class-kiss-woo-admin-page.php';
        require_once KISS_WOO_COS_PATH . 'admin/class-kiss-woo-settings.php';
        require_once KISS_WOO_COS_PATH . 'admin/class-kiss-woo-debug-panel.php';

        // Initialize debug tracer (must be first for observability).
        KISS_Woo_Debug_Tracer::init();

        // Floating toolbar integration (admin only).
        if ( is_admin() ) {
            require_once KISS_WOO_COS_PATH . 'toolbar.php';
            require_once KISS_WOO_COS_PATH . 'admin/coupon-diagnostic.php';
        }

        // Init admin page and settings.
        KISS_Woo_COS_Admin_Page::instance();
        KISS_Woo_COS_Settings::instance();

        // Init debug panel (only shows if KISS_WOO_FAST_SEARCH_DEBUG is true).
        $debug_panel = new KISS_Woo_Debug_Panel();
        $debug_panel->register();

        // Init coupon lookup/indexer.
        KISS_Woo_Coupon_Lookup::instance();

        // Register AJAX handler.
        $ajax_handler = new KISS_Woo_Ajax_Handler();
        $ajax_handler->register();

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
}

// Bootstrap.
KISS_Woo_Customer_Order_Search_Plugin::instance();
