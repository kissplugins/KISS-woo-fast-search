<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KISS_Woo_COS_Settings {

    /**
     * Singleton instance.
     *
     * @var KISS_Woo_COS_Settings|null
     */
    protected static $instance = null;

    /**
     * Option name for settings.
     *
     * @var string
     */
    const OPTION_NAME = 'kiss_woo_cos_settings';

    /**
     * Get instance.
     *
     * @return KISS_Woo_COS_Settings
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
        add_action( 'admin_menu', array( $this, 'register_settings_page' ), 99 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // AJAX handlers for coupon lookup table build.
        add_action( 'wp_ajax_kiss_woo_start_coupon_build', array( $this, 'ajax_start_build' ) );
        add_action( 'wp_ajax_kiss_woo_get_build_progress', array( $this, 'ajax_get_progress' ) );
        add_action( 'wp_ajax_kiss_woo_cancel_coupon_build', array( $this, 'ajax_cancel_build' ) );
    }

    /**
     * Register settings page under WooCommerce.
     */
    public function register_settings_page() {
        add_submenu_page(
            'woocommerce',
            __( 'KISS Search Settings', 'kiss-woo-customer-order-search' ),
            __( 'KISS Search Settings', 'kiss-woo-customer-order-search' ),
            'manage_woocommerce',
            'kiss-woo-cos-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings and fields.
     */
    public function register_settings() {
        register_setting(
            'kiss_woo_cos_settings_group',
            self::OPTION_NAME,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'default'           => array(),
            )
        );

        add_settings_section(
            'kiss_woo_cos_toolbar_section',
            __( 'Floating Toolbar Settings', 'kiss-woo-customer-order-search' ),
            array( $this, 'render_toolbar_section' ),
            'kiss-woo-cos-settings'
        );

        add_settings_field(
            'hide_floating_toolbar',
            __( 'Hide Floating Toolbar', 'kiss-woo-customer-order-search' ),
            array( $this, 'render_hide_toolbar_field' ),
            'kiss-woo-cos-settings',
            'kiss_woo_cos_toolbar_section'
        );

        // Coupon Lookup Table section.
        add_settings_section(
            'kiss_woo_cos_coupon_section',
            __( 'Coupon Lookup Table', 'kiss-woo-customer-order-search' ),
            array( $this, 'render_coupon_section' ),
            'kiss-woo-cos-settings'
        );
    }

    /**
     * Enqueue assets for settings page.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_assets( $hook ) {
        if ( 'woocommerce_page_kiss-woo-cos-settings' !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'kiss-woo-settings',
            KISS_WOO_COS_URL . 'admin/js/kiss-woo-settings.js',
            array( 'jquery' ),
            KISS_WOO_COS_VERSION,
            true
        );

        wp_localize_script(
            'kiss-woo-settings',
            'kissWooSettings',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'kiss_woo_coupon_build' ),
                'i18n'     => array(
                    'building'  => __( 'Building...', 'kiss-woo-customer-order-search' ),
                    'complete'  => __( 'Complete!', 'kiss-woo-customer-order-search' ),
                    'error'     => __( 'Error', 'kiss-woo-customer-order-search' ),
                    'cancelled' => __( 'Cancelled', 'kiss-woo-customer-order-search' ),
                ),
            )
        );
    }

    /**
     * Sanitize settings.
     *
     * @param array $input
     * @return array
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();

        if ( isset( $input['hide_floating_toolbar'] ) ) {
            $sanitized['hide_floating_toolbar'] = (bool) $input['hide_floating_toolbar'];
        }

        return $sanitized;
    }

    /**
     * Render toolbar section description.
     */
    public function render_toolbar_section() {
        echo '<p>' . esc_html__( 'Configure the floating admin search toolbar that appears below the WordPress admin bar.', 'kiss-woo-customer-order-search' ) . '</p>';
    }

    /**
     * Render coupon lookup table section.
     */
    public function render_coupon_section() {
        $builder = new KISS_Woo_Coupon_Lookup_Builder();
        $progress = $builder->get_progress();
        $total_coupons = $builder->get_total_coupons();

        $percent = $progress['total'] > 0 ? round( ( $progress['processed'] / $progress['total'] ) * 100 ) : 0;
        $is_running = 'running' === $progress['status'];
        $is_complete = 'complete' === $progress['status'];
        ?>
        <p><?php esc_html_e( 'Build the coupon lookup table for fast coupon search. This process runs in the background.', 'kiss-woo-customer-order-search' ); ?></p>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Total Coupons', 'kiss-woo-customer-order-search' ); ?></th>
                <td><?php echo esc_html( number_format_i18n( $total_coupons ) ); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Indexed Coupons', 'kiss-woo-customer-order-search' ); ?></th>
                <td>
                    <span id="kiss-indexed-count"><?php echo esc_html( number_format_i18n( $progress['processed'] ) ); ?></span>
                    / <?php echo esc_html( number_format_i18n( $progress['total'] > 0 ? $progress['total'] : $total_coupons ) ); ?>
                    (<span id="kiss-indexed-percent"><?php echo esc_html( $percent ); ?></span>%)
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Status', 'kiss-woo-customer-order-search' ); ?></th>
                <td>
                    <span id="kiss-build-status">
                        <?php
                        if ( $is_running ) {
                            echo '<span class="dashicons dashicons-update spin"></span> ';
                            esc_html_e( 'Building...', 'kiss-woo-customer-order-search' );
                        } elseif ( $is_complete ) {
                            echo '<span class="dashicons dashicons-yes-alt"></span> ';
                            esc_html_e( 'Complete', 'kiss-woo-customer-order-search' );
                        } else {
                            echo '<span class="dashicons dashicons-minus"></span> ';
                            esc_html_e( 'Idle', 'kiss-woo-customer-order-search' );
                        }
                        ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Actions', 'kiss-woo-customer-order-search' ); ?></th>
                <td>
                    <button type="button" id="kiss-start-build" class="button button-primary" <?php disabled( $is_running ); ?>>
                        <?php esc_html_e( 'Build Lookup Table', 'kiss-woo-customer-order-search' ); ?>
                    </button>
                    <button type="button" id="kiss-cancel-build" class="button" <?php disabled( ! $is_running ); ?>>
                        <?php esc_html_e( 'Cancel', 'kiss-woo-customer-order-search' ); ?>
                    </button>
                    <span class="spinner" id="kiss-build-spinner" style="float: none; margin: 0 10px;"></span>
                </td>
            </tr>
        </table>

        <style>
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .dashicons.spin {
                animation: spin 1s linear infinite;
            }
        </style>
        <?php
    }

    /**
     * Render hide toolbar checkbox field.
     */
    public function render_hide_toolbar_field() {
        $options = get_option( self::OPTION_NAME, array() );
        $checked = ! empty( $options['hide_floating_toolbar'] );
        ?>
        <label>
            <input 
                type="checkbox" 
                name="<?php echo esc_attr( self::OPTION_NAME ); ?>[hide_floating_toolbar]" 
                value="1" 
                <?php checked( $checked, true ); ?>
            />
            <?php esc_html_e( 'Hide the floating search toolbar for all users', 'kiss-woo-customer-order-search' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'When enabled, the floating toolbar will not appear for any user, regardless of their permissions.', 'kiss-woo-customer-order-search' ); ?>
        </p>
        <?php
    }

    /**
     * Render settings page.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'kiss_woo_cos_settings_group' );
                do_settings_sections( 'kiss-woo-cos-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Check if floating toolbar is hidden.
     *
     * @return bool
     */
    public static function is_toolbar_hidden() {
        $options = get_option( self::OPTION_NAME, array() );
        return ! empty( $options['hide_floating_toolbar'] );
    }

    /**
     * AJAX handler: Start coupon lookup table build.
     *
     * @return void
     */
    public function ajax_start_build() {
        check_ajax_referer( 'kiss_woo_coupon_build', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $builder = new KISS_Woo_Coupon_Lookup_Builder();
        $result  = $builder->start_build();

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX handler: Get build progress.
     *
     * @return void
     */
    public function ajax_get_progress() {
        check_ajax_referer( 'kiss_woo_coupon_build', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $builder  = new KISS_Woo_Coupon_Lookup_Builder();
        $progress = $builder->get_progress();

        wp_send_json_success( $progress );
    }

    /**
     * AJAX handler: Cancel build.
     *
     * @return void
     */
    public function ajax_cancel_build() {
        check_ajax_referer( 'kiss_woo_coupon_build', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $builder = new KISS_Woo_Coupon_Lookup_Builder();
        $result  = $builder->cancel_build();

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }
}

