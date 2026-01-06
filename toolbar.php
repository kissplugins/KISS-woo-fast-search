<?php
/**
 * Plugin Name: Custom Floating Search Bar
 * Description: Adds a secondary floating toolbar with search functionality
 * Version: 1.2.0
 * Text Domain: floating-search
 */

namespace YourNamespace\FloatingSearch;

if (!defined('ABSPATH')) {
    exit;
}

class FloatingSearchBar {
    
    private const NONCE_ACTION = 'floating_search_action';
    private const SCRIPT_HANDLE = 'floating-search-bar';
    
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_footer', [$this, 'render_toolbar']);
    }
    
    public function enqueue_assets(): void {
        add_action('admin_head', [$this, 'output_css']);
        add_action('admin_footer', [$this, 'output_js'], 20);
        
        wp_register_script(self::SCRIPT_HANDLE, false, ['jquery'], '1.2.0', true);
        wp_enqueue_script(self::SCRIPT_HANDLE);
        
        wp_localize_script(self::SCRIPT_HANDLE, 'floatingSearchBar', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE_ACTION),
        ]);
    }
    
    public function output_css(): void {
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
            
            .floating-search-toolbar__label {
                color: #c3c4c7;
                font-size: 13px;
                font-weight: 400;
                padding: 0 12px;
                height: 100%;
                display: flex;
                align-items: center;
                border-right: 1px solid #333;
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
                color: #c3c4c7;
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
            }
        </style>
        <?php
    }
    
    public function output_js(): void {
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
                
                const event = new CustomEvent('floatingSearch:submit', {
                    detail: {
                        term: searchTerm,
                        nonce: floatingSearchBar.nonce,
                        ajaxUrl: floatingSearchBar.ajaxUrl
                    }
                });
                document.dispatchEvent(event);
                
                console.log('FloatingSearch: Ready for integration', searchTerm);
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
        ?>
        <div id="floating-search-toolbar">
            <div class="floating-search-toolbar__section">
                <span class="floating-search-toolbar__label">
                    <?php esc_html_e('Secondary Toolbar', 'floating-search'); ?>
                </span>
            </div>
            
            <div class="floating-search-toolbar__section floating-search-toolbar__section--search">
                <input 
                    type="text" 
                    id="floating-search-input" 
                    class="floating-search-input"
                    placeholder="<?php esc_attr_e('Search...', 'floating-search'); ?>"
                    autocomplete="off"
                />
                <button 
                    type="button" 
                    id="floating-search-submit" 
                    class="floating-search-submit"
                >
                    <?php esc_html_e('Search', 'floating-search'); ?>
                </button>
            </div>
        </div>
        <?php
    }
    
    public static function get_nonce_action(): string {
        return self::NONCE_ACTION;
    }
}

new FloatingSearchBar();