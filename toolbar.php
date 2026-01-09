<?php
/**
 * Floating admin toolbar for the KISS Customer & Order Search plugin.
 *
 * This file is bundled and loaded by the main plugin bootstrap.
 */

if (!defined('ABSPATH')) {
    exit;
}

if ( ! class_exists( 'KISS_Woo_COS_Floating_Search_Bar' ) ) {

class KISS_Woo_COS_Floating_Search_Bar {

    private const SCRIPT_HANDLE = 'kiss-woo-cos-floating-toolbar';

    /**
     * Whether the toolbar is hidden via settings.
     * Cached to avoid repeated checks.
     *
     * @var bool
     */
    private bool $is_hidden;

    public function __construct() {
        // Check once at initialization and cache the result.
        $this->is_hidden = $this->check_if_toolbar_hidden();

        // Short-circuit all hooks if toolbar is hidden.
        if ( $this->is_hidden ) {
            return;
        }

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_footer', array( $this, 'render_toolbar' ) );
    }

    /**
     * Check if toolbar is globally hidden via settings.
     *
     * @return bool
     */
    private function check_if_toolbar_hidden() {
        if ( ! class_exists( 'KISS_Woo_COS_Settings' ) ) {
            return false;
        }
        return KISS_Woo_COS_Settings::is_toolbar_hidden();
    }
    
    public function enqueue_assets(): void {
        if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $version = defined( 'KISS_WOO_COS_VERSION' ) ? KISS_WOO_COS_VERSION : '1.0.0';

        // Enqueue toolbar CSS.
        wp_enqueue_style(
            'kiss-woo-toolbar',
            KISS_WOO_COS_URL . 'admin/css/kiss-woo-toolbar.css',
            array(),
            $version
        );

        // Enqueue toolbar JS.
        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            KISS_WOO_COS_URL . 'admin/js/kiss-woo-toolbar.js',
            array( 'jquery' ),
            $version,
            true
        );

        wp_localize_script(
            self::SCRIPT_HANDLE,
            'floatingSearchBar',
            [
                'searchUrl' => admin_url( 'admin.php?page=kiss-woo-customer-order-search' ),
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'nonce'     => wp_create_nonce( 'kiss_woo_cos_search' ),
                'minChars'  => 2,
            ]
        );
    }

    public function render_toolbar(): void {
        if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        ?>
        <div id="floating-search-toolbar">
            <div class="floating-search-toolbar__section floating-search-toolbar__section--search">
                <span class="floating-search-toolbar__speed-label">
                    <?php esc_html_e( 'Ultra Fast Search (3-4x faster)', 'kiss-woo-customer-order-search' ); ?>
                </span>
                <input
                    type="text"
                    id="floating-search-input"
                    class="floating-search-input"
                    placeholder="<?php esc_attr_e( 'Search order ID, email, or name…', 'kiss-woo-customer-order-search' ); ?>"
                    autocomplete="off"
                />
                <button
                    type="button"
                    id="floating-search-submit"
                    class="floating-search-submit"
                >
                    <?php esc_html_e( 'Search', 'kiss-woo-customer-order-search' ); ?>
                </button>
            </div>
        </div>
        <?php
    }
}

}

// Bootstrap immediately when this file is included by the main plugin.
// This file is loaded during `plugins_loaded`, so hooking `plugins_loaded` here would be too late.
if ( is_admin() && current_user_can( 'manage_woocommerce' ) ) {
    new KISS_Woo_COS_Floating_Search_Bar();
}