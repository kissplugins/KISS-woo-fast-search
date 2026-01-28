<?php
/**
 * PHPUnit Bootstrap for KISS Woo Fast Search tests.
 *
 * Uses Brain\Monkey to mock WordPress functions without loading WordPress core.
 *
 * @package KISS_Woo_Fast_Search\Tests
 */

// Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Base test case with Brain\Monkey setup.
 */
abstract class KISS_Test_Case extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // Mock common WordPress functions.
        Functions\stubs([
            'esc_html'       => function( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); },
            'esc_attr'       => function( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); },
            'esc_url'        => function( $url ) { return filter_var( $url, FILTER_SANITIZE_URL ); },
            'wp_json_encode' => function( $data ) { return json_encode( $data ); },
            'absint'         => function( $val ) { return abs( (int) $val ); },
            'sanitize_text_field' => function( $str ) { return trim( strip_tags( (string) $str ) ); },
            'wp_list_pluck'  => function( $list, $field ) {
                return array_map( function( $item ) use ( $field ) {
                    return is_object( $item ) ? $item->$field : $item[ $field ];
                }, $list );
            },
            'apply_filters'  => function( $tag, $value ) { return $value; },
            'get_edit_user_link' => function( $user_id ) { return "https://example.com/wp-admin/user-edit.php?user_id={$user_id}"; },
            'get_transient'  => function( $key ) { return false; },
            'set_transient'  => function( $key, $value, $expiration = 0 ) { return true; },
            'delete_transient' => function( $key ) { return true; },
            'date_i18n'      => function( $format, $timestamp = false ) {
                return date( $format, $timestamp ?: time() );
            },
            'human_time_diff' => function( $from, $to = 0 ) {
                $diff = abs( ( $to ?: time() ) - $from );
                if ( $diff < 60 ) return $diff . ' secs';
                if ( $diff < 3600 ) return round( $diff / 60 ) . ' mins';
                if ( $diff < 86400 ) return round( $diff / 3600 ) . ' hours';
                return round( $diff / 86400 ) . ' days';
            },
            'current_time'   => function( $type ) { return $type === 'timestamp' ? time() : date( 'Y-m-d H:i:s' ); },

            // Plugin bootstrap / admin helpers (needed when loading the main plugin file in tests).
            'add_action'     => function( $hook, $callback, $priority = 10, $accepted_args = 1 ) { return true; },
            'add_filter'     => function( $hook, $callback, $priority = 10, $accepted_args = 1 ) { return true; },
            'plugin_basename' => function( $file ) { return basename( (string) $file ); },
            'plugin_dir_path' => function( $file ) { return dirname( (string) $file ) . '/'; },
            'plugin_dir_url'  => function( $file ) { return 'https://example.com/wp-content/plugins/' . basename( dirname( (string) $file ) ) . '/'; },
            'admin_url'      => function( $path = '' ) { return 'https://example.com/wp-admin/' . ltrim( (string) $path, '/' ); },
            'is_admin'       => function() { return true; },
            'esc_html_e'     => function( $text, $domain = null ) { echo (string) $text; },
            '__'             => function( $text, $domain = null ) { return $text; },
            'wp_send_json_success' => function( $data ) {
                global $ajax_response;
                $ajax_response = [ 'success' => true, 'data' => $data ];
            },
            'wp_send_json_error' => function( $data, $status_code = null ) {
                global $ajax_response;
                $ajax_response = [ 'success' => false, 'data' => $data, 'status' => $status_code ];
            },
        ]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }
}

// Define plugin constants needed by classes.
if ( ! defined( 'KISS_WOO_COS_VERSION' ) ) {
    define( 'KISS_WOO_COS_VERSION', '1.1.6' );
}
if ( ! defined( 'KISS_WOO_COS_PATH' ) ) {
    define( 'KISS_WOO_COS_PATH', dirname( __DIR__ ) . '/' );
}

// Provide minimal WooCommerce marker class so plugin bootstrap does not immediately bail.
if ( ! class_exists( 'WooCommerce' ) ) {
    class WooCommerce {}
}

// Load real plugin classes used directly by unit tests.
// NOTE: We do NOT load the main plugin file here because it calls add_action() in the constructor,
// which conflicts with Brain\Monkey's Patchwork. Tests that need the main plugin class should
// load it in their setUp() method AFTER Brain\Monkey is initialized.
require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-debug-tracer.php';
require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-utils.php';
require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-search-cache.php';
require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-order-formatter.php';
require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-order-resolver.php';
require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-search.php';
require_once KISS_WOO_COS_PATH . 'includes/class-kiss-woo-ajax-handler.php';

/**
 * WP_User_Query is mocked in individual test files using Mockery.
 * We don't define it here because Mockery needs to create its own mock version.
 */

