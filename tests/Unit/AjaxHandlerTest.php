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
        Functions\stubs([
            'wp_send_json_success' => function( $data ) {
                // Capture the response for assertions.
                global $ajax_response;
                $ajax_response = [ 'success' => true, 'data' => $data ];
            },
            'wp_send_json_error' => function( $data, $status_code = null ) {
                global $ajax_response;
                $ajax_response = [ 'success' => false, 'data' => $data, 'status' => $status_code ];
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
        ]);
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
        $mock_order->shouldReceive('get_edit_order_url')->andReturn( 'https://example.com/wp-admin/post.php?post=12345&action=edit' );
        
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
        $plugin->handle_ajax_search();
        
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
        $plugin->handle_ajax_search();

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
        $plugin->handle_ajax_search();

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
        $plugin->handle_ajax_search();

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
        $plugin->handle_ajax_search();

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
        $mock_order->shouldReceive('get_edit_order_url')->andReturn( 'https://example.com/wp-admin/post.php?post=1256171&action=edit' );

        // Mock sequential order number plugin function.
        Functions\when('wc_seq_order_number_pro')->justReturn( (object) [
            'find_order_by_order_number' => function( $order_number ) use ( $mock_order ) {
                return $mock_order;
            }
        ]);
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
        $plugin->handle_ajax_search();

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

