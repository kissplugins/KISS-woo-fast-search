<?php
/**
 * Tests for AJAX Handler (handle_ajax_search method).
 *
 * Tests the complete order number lookup → redirect URL flow.
 * This validates the user-facing feature end-to-end.
 *
 * @package KISS_Woo_Fast_Search\Tests\Unit
 */

namespace KISS\Tests\Unit;

use Mockery;
use Brain\Monkey\Functions;

class AjaxHandlerTest extends \KISS_Test_Case {

    protected function setUp(): void {
        parent::setUp();

        // Mock WordPress AJAX functions.
        // Note: In WordPress, wp_send_json_* functions call die() to stop execution.
        // We simulate this by throwing an exception that tests can catch.
        Functions\stubs([
            'wp_send_json_success' => function( $data ) {
                // Capture the response for assertions.
                global $ajax_response;
                $ajax_response = [ 'success' => true, 'data' => $data ];
                // Throw exception to simulate die() and stop execution.
                throw new \Exception( 'AJAX_RESPONSE_SENT' );
            },
            'wp_send_json_error' => function( $data, $status_code = null ) {
                global $ajax_response;
                $ajax_response = [ 'success' => false, 'data' => $data, 'status' => $status_code ];
                // Throw exception to simulate die() and stop execution.
                throw new \Exception( 'AJAX_RESPONSE_SENT' );
            },
            'check_ajax_referer' => function( $action, $query_arg, $die ) {
                return true; // Always pass nonce check in tests.
            },
            'current_user_can' => function( $capability ) {
                return true; // Always pass capability check in tests.
            },
            'get_current_user_id' => function() {
                return 1;
            },
            'wp_unslash' => function( $value ) {
                return stripslashes( $value );
            },
            '__' => function( $text, $domain ) {
                return $text;
            },
            'plugin_basename' => function( $file ) {
                return basename( (string) $file );
            },
            'get_edit_post_link' => function( $post_id, $context = 'display' ) {
                return 'http://example.com/wp-admin/post.php?post=' . $post_id . '&action=edit';
            },
            'get_option' => function( $option, $default = false ) {
                if ( $option === 'woocommerce_custom_orders_table_enabled' ) {
                    return 'yes';
                }
                return $default;
            },
            'esc_html' => function( $text ) {
                return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
            },
            'esc_url' => function( $url ) {
                return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
            },
            'admin_url' => function( $path ) {
                return 'http://example.com/wp-admin/' . $path;
            },
            'wc_get_order_status_name' => function( $status ) {
                $statuses = [
                    'pending'    => 'Pending payment',
                    'processing' => 'Processing',
                    'on-hold'    => 'On hold',
                    'completed'  => 'Completed',
                    'cancelled'  => 'Cancelled',
                    'refunded'   => 'Refunded',
                    'failed'     => 'Failed',
                ];
                $status = str_replace( 'wc-', '', $status );
                return $statuses[ $status ] ?? ucfirst( $status );
            },
            'sanitize_text_field' => function( $str ) {
                return trim( strip_tags( (string) $str ) );
            },
            'wp_strip_all_tags' => function( $string, $remove_breaks = false ) {
                $string = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $string );
                $string = strip_tags( $string );
                if ( $remove_breaks ) {
                    $string = preg_replace( '/[\r\n\t ]+/', ' ', $string );
                }
                return trim( $string );
            },
            'wp_list_pluck' => function( $list, $field ) {
                $result = [];
                foreach ( $list as $item ) {
                    if ( is_object( $item ) ) {
                        $result[] = $item->$field ?? null;
                    } elseif ( is_array( $item ) ) {
                        $result[] = $item[ $field ] ?? null;
                    }
                }
                return $result;
            },
            'human_time_diff' => function( $from, $to = null ) {
                $to = $to ?? time();
                $diff = abs( $to - $from );
                if ( $diff < 60 ) {
                    return $diff . ' seconds';
                } elseif ( $diff < 3600 ) {
                    return floor( $diff / 60 ) . ' minutes';
                } else {
                    return floor( $diff / 3600 ) . ' hours';
                }
            },
        ]);

        // Mock global $wpdb object.
        global $wpdb;
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->usermeta = 'wp_usermeta';
        $wpdb->posts = 'wp_posts';
        $wpdb->postmeta = 'wp_postmeta';

        // Mock wpdb methods.
        $wpdb->shouldReceive('prepare')
            ->andReturnUsing(function($query) {
                // Simple passthrough for tests - just return the query.
                // In real WordPress, this would escape and substitute placeholders.
                return $query;
            });

        $wpdb->shouldReceive('get_results')
            ->andReturn( [] )->byDefault();

        $wpdb->shouldReceive('get_var')
            ->andReturn( null )->byDefault();

        $wpdb->shouldReceive('esc_like')
            ->andReturnUsing(function($text) {
                return addcslashes( (string) $text, '_%\\' );
            });

        // Mock WP_User_Query to return empty results by default.
        // Individual tests can override this if needed.
        $user_query_mock = Mockery::mock('overload:WP_User_Query');
        $user_query_mock->shouldReceive('get_results')->andReturn( [] )->byDefault();

        // Load the main plugin file AFTER Brain\Monkey is set up.
        // This must be done here (not in bootstrap) because the plugin constructor calls add_action(),
        // which conflicts with Patchwork if defined before Brain\Monkey initializes.
        if ( ! class_exists( 'KISS_Woo_Customer_Order_Search_Plugin' ) ) {
            require_once KISS_WOO_COS_PATH . 'kiss-woo-fast-order-search.php';
        }
    }

    protected function tearDown(): void {
        global $ajax_response;
        $ajax_response = null;
        
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // Order Number Lookup → Redirect URL Tests
    // =========================================================================

    public function test_order_number_search_returns_redirect_url_for_valid_order(): void {
        global $ajax_response;
        
        // Mock $_POST data.
        $_POST['q'] = '12345';
        $_POST['nonce'] = 'test_nonce';
        
        // Mock WooCommerce order.
        $mock_order = Mockery::mock('WC_Order');
        $mock_order->shouldReceive('get_id')->andReturn( 12345 );
        $mock_order->shouldReceive('get_order_number')->andReturn( '12345' );
        $mock_order->shouldReceive('get_status')->andReturn( 'completed' );
        $mock_order->shouldReceive('get_date_created')->andReturn( null );
        $mock_order->shouldReceive('get_total')->andReturn( '99.99' );
        $mock_order->shouldReceive('get_currency')->andReturn( 'USD' );
        $mock_order->shouldReceive('get_payment_method_title')->andReturn( 'Credit Card' );
        $mock_order->shouldReceive('get_billing_email')->andReturn( 'customer@example.com' );
        $mock_order->shouldReceive('get_billing_first_name')->andReturn( 'John' );
        $mock_order->shouldReceive('get_billing_last_name')->andReturn( 'Doe' );
        $mock_order->shouldReceive('get_edit_order_url')->andReturn( 'https://example.com/wp-admin/post.php?post=12345&action=edit' );
        $mock_order->shouldReceive('get_formatted_order_total')->andReturn( '$99.99' );
        
        // Mock wc_get_order to return our mock order.
        Functions\when('wc_get_order')->justReturn( $mock_order );
        Functions\when('wc_get_order_statuses')->justReturn( [ 'wc-completed' => 'Completed' ] );
        Functions\when('wc_price')->alias( function( $amount, $args = [] ) {
            $currency = $args['currency'] ?? 'USD';
            return $currency . ' ' . number_format( (float) $amount, 2 );
        });
        Functions\when('admin_url')->alias( function( $path ) {
            return 'https://example.com/wp-admin/' . $path;
        });
        
        // Create plugin instance and call handler.
        $plugin = \KISS_Woo_Customer_Order_Search_Plugin::instance();
        try {
            $plugin->handle_ajax_search();
        } catch ( \Exception $e ) {
            // Expected - wp_send_json_* throws exception to simulate die().
        }

        // Assert response structure.
        $this->assertTrue( $ajax_response['success'] );
        $this->assertArrayHasKey( 'data', $ajax_response );
        
        $data = $ajax_response['data'];
        
        // Assert redirect flags are set.
        $this->assertArrayHasKey( 'should_redirect_to_order', $data );
        $this->assertTrue( $data['should_redirect_to_order'], 'should_redirect_to_order should be true for order number search' );
        
        $this->assertArrayHasKey( 'redirect_url', $data );
        $this->assertNotNull( $data['redirect_url'], 'redirect_url should not be null' );
        $this->assertStringContainsString( 'post.php?post=12345', $data['redirect_url'], 'redirect_url should contain order edit URL' );
        
        // Assert orders array contains the order.
        $this->assertArrayHasKey( 'orders', $data );
        $this->assertCount( 1, $data['orders'], 'Should return exactly one order' );
        
        $order = $data['orders'][0];
        $this->assertSame( 12345, $order['id'] );
        $this->assertSame( '12345', $order['order_number'] );

        // Legacy alias (kept temporarily for backward compatibility)
        if ( isset( $order['number'] ) ) {
            $this->assertSame( '12345', $order['number'] );
        }

        // Assert redirect_url matches the order's view_url.
        $this->assertSame( $order['view_url'], $data['redirect_url'], 'redirect_url should match order view_url' );
    }

    public function test_non_order_search_does_not_set_redirect_flag(): void {
        global $ajax_response;

        // Mock $_POST data with email search.
        $_POST['q'] = 'customer@example.com';
        $_POST['nonce'] = 'test_nonce';

        // Mock empty search results.
        Functions\when('wc_get_order')->justReturn( false );

        // Create plugin instance and call handler.
        $plugin = \KISS_Woo_Customer_Order_Search_Plugin::instance();
        try {
            $plugin->handle_ajax_search();
        } catch ( \Exception $e ) {
            // Expected - wp_send_json_* throws exception to simulate die().
        }

        // Assert response structure.
        $this->assertTrue( $ajax_response['success'] );
        $data = $ajax_response['data'];

        // Assert redirect flags are NOT set for email search.
        $this->assertArrayHasKey( 'should_redirect_to_order', $data );
        $this->assertFalse( $data['should_redirect_to_order'], 'should_redirect_to_order should be false for email search' );

        $this->assertArrayHasKey( 'redirect_url', $data );
        $this->assertNull( $data['redirect_url'], 'redirect_url should be null for email search' );
    }

    public function test_invalid_order_number_returns_no_redirect(): void {
        global $ajax_response;

        // Mock $_POST data with invalid order number.
        $_POST['q'] = '99999999';
        $_POST['nonce'] = 'test_nonce';

        // Mock wc_get_order to return false (order not found).
        Functions\when('wc_get_order')->justReturn( false );

        // Create plugin instance and call handler.
        $plugin = \KISS_Woo_Customer_Order_Search_Plugin::instance();
        try {
            $plugin->handle_ajax_search();
        } catch ( \Exception $e ) {
            // Expected - wp_send_json_* throws exception to simulate die().
        }

        // Assert response structure.
        $this->assertTrue( $ajax_response['success'] );
        $data = $ajax_response['data'];

        // Assert no redirect for invalid order.
        $this->assertFalse( $data['should_redirect_to_order'] );
        $this->assertNull( $data['redirect_url'] );
        $this->assertEmpty( $data['orders'] );
    }

    public function test_response_includes_all_required_fields(): void {
        global $ajax_response;

        // Mock $_POST data.
        $_POST['q'] = 'test';
        $_POST['nonce'] = 'test_nonce';

        // Create plugin instance and call handler.
        $plugin = \KISS_Woo_Customer_Order_Search_Plugin::instance();
        try {
            $plugin->handle_ajax_search();
        } catch ( \Exception $e ) {
            // Expected - wp_send_json_* throws exception to simulate die().
        }

        // Assert response structure.
        $this->assertTrue( $ajax_response['success'] );
        $data = $ajax_response['data'];

        // Assert all required fields are present.
        $this->assertArrayHasKey( 'customers', $data );
        $this->assertArrayHasKey( 'guest_orders', $data );
        $this->assertArrayHasKey( 'orders', $data );
        $this->assertArrayHasKey( 'should_redirect_to_order', $data );
        $this->assertArrayHasKey( 'redirect_url', $data );
        $this->assertArrayHasKey( 'search_time', $data );
        $this->assertArrayHasKey( 'search_time_ms', $data );

        // Assert data types.
        $this->assertIsArray( $data['customers'] );
        $this->assertIsArray( $data['guest_orders'] );
        $this->assertIsArray( $data['orders'] );
        $this->assertIsBool( $data['should_redirect_to_order'] );
    }

    public function test_short_search_term_returns_error(): void {
        global $ajax_response;

        // Mock $_POST data with short term.
        $_POST['q'] = 'a';
        $_POST['nonce'] = 'test_nonce';

        // Create plugin instance and call handler.
        $plugin = \KISS_Woo_Customer_Order_Search_Plugin::instance();
        try {
            $plugin->handle_ajax_search();
        } catch ( \Exception $e ) {
            // Expected - wp_send_json_* throws exception to simulate die().
        }

        // Assert error response.
        $this->assertFalse( $ajax_response['success'] );
        $this->assertArrayHasKey( 'data', $ajax_response );
        $this->assertArrayHasKey( 'message', $ajax_response['data'] );
        $this->assertStringContainsString( 'at least 2 characters', $ajax_response['data']['message'] );
    }

    public function test_sequential_order_number_resolves_correctly(): void {
        global $ajax_response;

        // Mock $_POST data with sequential order number.
        $_POST['q'] = 'B349445';
        $_POST['nonce'] = 'test_nonce';

        // Mock WooCommerce order.
        $mock_order = Mockery::mock('WC_Order');
        $mock_order->shouldReceive('get_id')->andReturn( 1256171 );
        $mock_order->shouldReceive('get_order_number')->andReturn( 'B349445' );
        $mock_order->shouldReceive('get_status')->andReturn( 'completed' );
        $mock_order->shouldReceive('get_date_created')->andReturn( null );
        $mock_order->shouldReceive('get_total')->andReturn( '149.99' );
        $mock_order->shouldReceive('get_currency')->andReturn( 'USD' );
        $mock_order->shouldReceive('get_payment_method_title')->andReturn( 'PayPal' );
        $mock_order->shouldReceive('get_billing_email')->andReturn( 'test@example.com' );
        $mock_order->shouldReceive('get_billing_first_name')->andReturn( 'Jane' );
        $mock_order->shouldReceive('get_billing_last_name')->andReturn( 'Smith' );
        $mock_order->shouldReceive('get_edit_order_url')->andReturn( 'https://example.com/wp-admin/post.php?post=1256171&action=edit' );
        $mock_order->shouldReceive('get_formatted_order_total')->andReturn( '$149.99' );

        // Mock sequential order number plugin.
        $seq_plugin_mock = Mockery::mock();
        $seq_plugin_mock->shouldReceive('find_order_by_order_number')
            ->andReturn( 1256171 );

        Functions\when('wc_seq_order_number_pro')->justReturn( $seq_plugin_mock );
        Functions\when('wc_get_order')->justReturn( $mock_order );
        Functions\when('wc_get_order_statuses')->justReturn( [ 'wc-completed' => 'Completed' ] );
        Functions\when('wc_price')->alias( function( $amount, $args = [] ) {
            $currency = $args['currency'] ?? 'USD';
            return $currency . ' ' . number_format( (float) $amount, 2 );
        });
        Functions\when('admin_url')->alias( function( $path ) {
            return 'https://example.com/wp-admin/' . $path;
        });

        // Create plugin instance and call handler.
        $plugin = \KISS_Woo_Customer_Order_Search_Plugin::instance();
        try {
            $plugin->handle_ajax_search();
        } catch ( \Exception $e ) {
            // Expected - wp_send_json_* throws exception to simulate die().
        }

        // Assert response.
        $this->assertTrue( $ajax_response['success'] );
        $data = $ajax_response['data'];

        // Assert redirect is set for sequential order number.
        $this->assertTrue( $data['should_redirect_to_order'] );
        $this->assertNotNull( $data['redirect_url'] );
        $this->assertStringContainsString( 'post=1256171', $data['redirect_url'] );

        // Assert order data.
        $this->assertCount( 1, $data['orders'] );
        $this->assertSame( 1256171, $data['orders'][0]['id'] );
        $this->assertSame( 'B349445', $data['orders'][0]['order_number'] );

        // Legacy alias (kept temporarily for backward compatibility)
        if ( isset( $data['orders'][0]['number'] ) ) {
            $this->assertSame( 'B349445', $data['orders'][0]['number'] );
        }
    }
}

