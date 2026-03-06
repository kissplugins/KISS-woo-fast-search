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

        // Enqueue inside the Gutenberg block editor specifically.
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );

        // Add body class for CSS scoping (server-side kill-switch for !important rules).
        add_filter( 'admin_body_class', array( $this, 'add_toolbar_body_class' ) );
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

    /**
     * Add body class for CSS scoping.
     *
     * Provides a kill-switch: if the toolbar is disabled or another plugin
     * conflicts, the body class won't be present and the layout-push rules
     * won't apply.
     *
     * @param string $classes Space-separated list of body classes.
     * @return string
     */
    public function add_toolbar_body_class( string $classes ): string {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return $classes;
        }
        return $classes . ' kiss-toolbar-active';
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

    /**
     * Enqueue specifically for the block editor (Gutenberg).
     * This fires after the editor iframe is set up.
     *
     * @return void
     */
    public function enqueue_block_editor_assets(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $version = defined( 'KISS_WOO_COS_VERSION' ) ? KISS_WOO_COS_VERSION : '1.0.0';

        wp_enqueue_style(
            'kiss-woo-toolbar-editor',
            KISS_WOO_COS_URL . 'admin/css/kiss-woo-toolbar-editor.css',
            array(),
            $version
        );
    }

    public function render_toolbar(): void {
        if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Check if we're in listing mode (wholesale or recent orders)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameters.
        $is_listing_mode = ( isset( $_GET['list_wholesale'] ) && '1' === $_GET['list_wholesale'] ) ||
                          ( isset( $_GET['list_recent'] ) && '1' === $_GET['list_recent'] );

        $toolbar_class = $is_listing_mode ? 'floating-search-toolbar--listing-mode' : '';
        ?>
        <div id="floating-search-toolbar" class="<?php echo esc_attr( $toolbar_class ); ?>">
            <div class="floating-search-toolbar__section floating-search-toolbar__section--left">
                <div class="floating-search-dropdown">
                    <button
                        type="button"
                        id="floating-search-menu-toggle"
                        class="floating-search-menu-toggle"
                        aria-haspopup="true"
                        aria-expanded="false"
                        title="<?php esc_attr_e( 'Quick access to order lists', 'kiss-woo-customer-order-search' ); ?>"
                    >
                        <?php esc_html_e( 'Fast Search...', 'kiss-woo-customer-order-search' ); ?>
                        <span class="floating-search-menu-arrow" aria-hidden="true">▼</span>
                    </button>
                    <ul class="floating-search-menu" role="menu" aria-hidden="true">
                        <li role="none">
                            <button
                                type="button"
                                role="menuitem"
                                id="floating-search-recent"
                                class="floating-search-menu-item"
                                data-action="recent"
                            >
                                <?php esc_html_e( 'Recent Orders', 'kiss-woo-customer-order-search' ); ?>
                            </button>
                        </li>
                        <li role="none">
                            <button
                                type="button"
                                role="menuitem"
                                id="floating-search-wholesale"
                                class="floating-search-menu-item"
                                data-action="wholesale"
                            >
                                <?php esc_html_e( 'Wholesale Orders Only', 'kiss-woo-customer-order-search' ); ?>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="floating-search-toolbar__section floating-search-toolbar__section--search">
                <span class="floating-search-toolbar__speed-label">
                    <?php esc_html_e( 'Ultra Fast Search (3-4x faster)', 'kiss-woo-customer-order-search' ); ?>
                </span>
                <div class="floating-search-toolbar__controls">
                    <div class="floating-search-toggle" role="group" aria-label="<?php esc_attr_e( 'Search scope', 'kiss-woo-customer-order-search' ); ?>">
                        <input type="radio" id="kiss-search-scope-users" name="kiss-search-scope" value="users" checked />
                        <label for="kiss-search-scope-users"><?php esc_html_e( 'Users/Orders', 'kiss-woo-customer-order-search' ); ?></label>
                        <input type="radio" id="kiss-search-scope-coupons" name="kiss-search-scope" value="coupons" />
                        <label for="kiss-search-scope-coupons"><?php esc_html_e( 'Coupons', 'kiss-woo-customer-order-search' ); ?></label>
                        <span class="floating-search-toggle__thumb" aria-hidden="true"></span>
                    </div>
                    <input
                        type="text"
                        id="floating-search-input"
                        class="floating-search-input"
                        placeholder="<?php esc_attr_e( 'Search order ID, email, or name…', 'kiss-woo-customer-order-search' ); ?>"
                        data-placeholder-users="<?php esc_attr_e( 'Search order ID, email, or name…', 'kiss-woo-customer-order-search' ); ?>"
                        data-placeholder-coupons="<?php esc_attr_e( 'Search coupon code or title…', 'kiss-woo-customer-order-search' ); ?>"
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
        </div>
        <?php
    }
}

}

// Bootstrap immediately when this file is included by the main plugin.
// This file is loaded during `plugins_loaded` — do NOT call current_user_can()
// here because the user session is not yet initialized. Capability checks are
// handled inside individual methods that fire on later hooks (admin_enqueue_scripts,
// admin_footer, admin_body_class).
if ( is_admin() ) {
    new KISS_Woo_COS_Floating_Search_Bar();
}
