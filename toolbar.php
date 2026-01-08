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

    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_head', array( $this, 'output_css' ) );
        add_action( 'admin_footer', array( $this, 'render_toolbar' ) );
        add_action( 'admin_footer', array( $this, 'output_js' ), 20 );
    }

    /**
     * Check if toolbar is globally hidden via settings.
     *
     * @return bool
     */
    private function is_toolbar_hidden() {
        if ( ! class_exists( 'KISS_Woo_COS_Settings' ) ) {
            return false;
        }
        return KISS_Woo_COS_Settings::is_toolbar_hidden();
    }
    
    public function enqueue_assets(): void {
        if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Check if toolbar is globally hidden via settings.
        if ( $this->is_toolbar_hidden() ) {
            return;
        }

        $version = defined( 'KISS_WOO_COS_VERSION' ) ? KISS_WOO_COS_VERSION : '1.0.0';

        // Register a "virtual" script handle so we can localize settings and print inline JS.
        // Using `false` for src is a common WP pattern for inline-only scripts.
        wp_register_script( self::SCRIPT_HANDLE, false, array( 'jquery' ), $version, true );
        wp_enqueue_script( self::SCRIPT_HANDLE );

        wp_localize_script(
            self::SCRIPT_HANDLE,
            'floatingSearchBar',
            [
                'searchUrl' => admin_url( 'admin.php?page=kiss-woo-customer-order-search' ),
                'minChars'  => 2,
            ]
        );
    }
    
    public function output_css(): void {
        // Check if toolbar is globally hidden via settings.
        if ( $this->is_toolbar_hidden() ) {
            return;
        }
        ?>
        <style>
            #floating-search-toolbar {
                position: fixed;
                top: 32px; /* Directly below WP admin bar */
                left: 0;
                right: 0;
                z-index: 9997;
                height: 32px;
                background: #1d2327; /* Match WP admin bar */
                border-bottom: 1px solid #333;
                display: flex;
                align-items: center;
                padding: 0 10px;
                box-sizing: border-box;
            }
            
            /* Adjust for smaller admin bar on mobile */
            @media screen and (max-width: 782px) {
                #floating-search-toolbar {
                    top: 46px;
                    height: 46px;
                }
            }
            
            /* Push admin content down to accommodate toolbar */
            .floating-toolbar-active #wpcontent,
            .floating-toolbar-active #wpfooter {
                margin-top: 32px;
            }
            
            @media screen and (max-width: 782px) {
                .floating-toolbar-active #wpcontent,
                .floating-toolbar-active #wpfooter {
                    margin-top: 46px;
                }
            }
            
            /* Toolbar layout */
            .floating-search-toolbar__section {
                display: flex;
                align-items: center;
                height: 100%;
            }
            
            .floating-search-toolbar__section--search {
                margin-left: auto;
            }
            
            .floating-search-toolbar__speed-label {
                color:rgb(240, 240, 241);
                font-size: 13px;
                font-weight: 600;
                margin-right: 10px;
                white-space: nowrap;
            }
            
            /* Search input styled like WP admin bar */
            .floating-search-input {
                background: #2c3338;
                border: none;
                border-radius: 2px;
                color: #fff;
                font-size: 13px;
                height: 24px;
                padding: 0 8px;
                width: 200px;
                margin-right: 6px;
            }
            
            .floating-search-input::placeholder {
                color: #8c8f94;
            }
            
            .floating-search-input:focus {
                background: #fff;
                color: #1d2327;
                outline: none;
                box-shadow: none;
            }
            
            .floating-search-input:focus::placeholder {
                color: #999;
            }
            
            /* Button styled like admin bar items */
            .floating-search-submit {
                background: transparent;
                border: none;
                color:rgb(240, 240, 241);
                cursor: pointer;
                font-size: 13px;
                height: 32px;
                padding: 0 12px;
                transition: background 0.1s ease-in-out, color 0.1s ease-in-out;
            }
            
            .floating-search-submit:hover,
            .floating-search-submit:focus {
                background: #2271b1;
                color: #fff;
                outline: none;
            }
            
            @media screen and (max-width: 782px) {
                .floating-search-submit {
                    height: 46px;
                }

                .floating-search-input {
                    height: 34px;
                    width: 150px;
                }

                .floating-search-toolbar__speed-label {
                    display: none;
                }
            }
        </style>
        <?php
    }
    
    public function output_js(): void {
        // Check if toolbar is globally hidden via settings.
        if ( $this->is_toolbar_hidden() ) {
            return;
        }
        ?>
        <script>
        (function($) {
            'use strict';

            const toolbar = document.getElementById('floating-search-toolbar');
            const input = document.getElementById('floating-search-input');
            const submitBtn = document.getElementById('floating-search-submit');

            if (!toolbar || !input || !submitBtn) {
                return;
            }
            
            // Add class to body for CSS adjustments
            document.body.classList.add('floating-toolbar-active');
            
            function handleSearch() {
                const searchTerm = input.value.trim();
                if (!searchTerm) {
                    input.focus();
                    return;
                }

                if (floatingSearchBar && floatingSearchBar.minChars && searchTerm.length < floatingSearchBar.minChars) {
                    input.focus();
                    return;
                }

                // Redirect to the KISS search results page with the query param.
                const baseUrl = (floatingSearchBar && floatingSearchBar.searchUrl) ? floatingSearchBar.searchUrl : '';
                if (!baseUrl) {
                    return;
                }

                window.location.href = baseUrl + '&q=' + encodeURIComponent(searchTerm);
            }
            
            submitBtn.addEventListener('click', handleSearch);
            
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    handleSearch();
                }
            });
            
        })(jQuery);
        </script>
        <?php
    }
    
    public function render_toolbar(): void {
        if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Check if toolbar is globally hidden via settings.
        if ( $this->is_toolbar_hidden() ) {
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
                    placeholder="<?php esc_attr_e( 'Search email or name…', 'kiss-woo-customer-order-search' ); ?>"
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