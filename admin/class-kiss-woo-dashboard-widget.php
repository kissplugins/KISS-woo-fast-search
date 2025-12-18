<?php
/**
 * Dashboard Widget for KISS Search
 *
 * @package KISS_Woo_Customer_Order_Search
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class KISS_Woo_COS_Dashboard_Widget
 *
 * Adds a dashboard widget with quick search functionality.
 */
class KISS_Woo_COS_Dashboard_Widget {

    /**
     * Widget ID.
     *
     * @var string
     */
    const WIDGET_ID = 'kiss_woo_cos_search_widget';

    /**
     * Singleton instance.
     *
     * @var KISS_Woo_COS_Dashboard_Widget|null
     */
    protected static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return KISS_Woo_COS_Dashboard_Widget
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
        add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
        add_action( 'admin_init', array( $this, 'force_widget_visibility' ) );
    }

    /**
     * Register the dashboard widget.
     */
    public function register_widget() {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            return;
        }

        wp_add_dashboard_widget(
            self::WIDGET_ID,
            __( 'KISS Customer & Order Search', 'kiss-woo-customer-order-search' ),
            array( $this, 'render_widget' )
        );
    }

    /**
     * Force widget to be visible for all users with appropriate capability.
     * Runs on admin_init to ensure the widget is shown by default.
     */
    public function force_widget_visibility() {
        if ( ! is_admin() ) {
            return;
        }

        // Only run on dashboard or when meta-boxes might be hidden.
        $screen = get_current_screen();
        if ( $screen && 'dashboard' !== $screen->id ) {
            return;
        }

        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return;
        }

        // Check if widget is hidden and unhide it.
        $hidden_widgets = get_user_meta( $user_id, 'metaboxhidden_dashboard', true );

        if ( is_array( $hidden_widgets ) && in_array( self::WIDGET_ID, $hidden_widgets, true ) ) {
            // Check if user has ever explicitly hidden it (custom flag).
            $user_dismissed = get_user_meta( $user_id, 'kiss_cos_widget_dismissed', true );
            if ( ! $user_dismissed ) {
                // Remove from hidden list to show by default.
                $hidden_widgets = array_diff( $hidden_widgets, array( self::WIDGET_ID ) );
                update_user_meta( $user_id, 'metaboxhidden_dashboard', $hidden_widgets );
            }
        }
    }

    /**
     * Render the dashboard widget content.
     */
    public function render_widget() {
        $search_page_url = admin_url( 'admin.php?page=kiss-woo-customer-order-search' );
        ?>
        <form id="kiss-cos-dashboard-form" method="get" action="<?php echo esc_url( $search_page_url ); ?>">
            <input type="hidden" name="page" value="kiss-woo-customer-order-search" />
            <p>
                <input type="text"
                       id="kiss-cos-dashboard-input"
                       name="q"
                       class="widefat"
                       placeholder="<?php esc_attr_e( 'Type email or name and hit Enter…', 'kiss-woo-customer-order-search' ); ?>"
                       autocomplete="off" />
            </p>
            <p>
                <button type="submit" class="button button-primary">
                    <?php esc_html_e( 'Search', 'kiss-woo-customer-order-search' ); ?>
                </button>
                <a href="<?php echo esc_url( $search_page_url ); ?>" class="button" style="margin-left: 5px;">
                    <?php esc_html_e( 'Go to Full Search', 'kiss-woo-customer-order-search' ); ?>
                </a>
            </p>
        </form>
        <style>
            #kiss-cos-dashboard-form input.widefat {
                padding: 8px 10px;
                font-size: 14px;
            }
        </style>
        <?php
    }
}

