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
}

