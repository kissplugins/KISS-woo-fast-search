<?php
/**
 * Tests for KISS_Woo_COS_Search.
 *
 * Tests the real search_customers() method by stubbing only external dependencies.
 *
 * @package KISS_Woo_Fast_Search\Tests\Unit
 */

namespace KISS\Tests\Unit;

use Mockery;
use Brain\Monkey\Functions;

/**
 * Testable subclass that stubs only database/external dependencies.
 */
class Testable_Search extends \KISS_Woo_COS_Search {

    public array $stubbed_user_ids = [];
    public array $stubbed_billing_meta = [];
    public array $stubbed_order_counts = [];
    public array $stubbed_recent_orders = [];

    /**
     * Stub the customer lookup table query.
     */
    protected function search_user_ids_via_customer_lookup( $term, $limit = 20 ) {
        return $this->stubbed_user_ids;
    }

    /**
     * Stub user meta fetching.
     */
    protected function get_user_meta_for_users( $user_ids, $meta_keys ) {
        return $this->stubbed_billing_meta;
    }

    /**
     * Stub order count queries.
     */
    protected function get_order_counts_for_customers( $user_ids ) {
        return $this->stubbed_order_counts;
    }

    /**
     * Stub recent orders queries.
     */
    protected function get_recent_orders_for_customers( $user_ids ) {
        return $this->stubbed_recent_orders;
    }
}

class SearchTest extends \KISS_Test_Case {

    private Testable_Search $search;

    protected function setUp(): void {
        parent::setUp();

        // Stub WordPress functions used by search.
        Functions\stubs([
            'get_option' => function( $option, $default = false ) {
                // Return sensible defaults for WooCommerce options.
                if ( $option === 'woocommerce_custom_orders_table_enabled' ) {
                    return 'yes'; // Enable HPOS by default.
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

        $this->search = new Testable_Search();
    }

    protected function tearDown(): void {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // search_customers() Tests - Testing Real Method
    // =========================================================================

    public function test_search_customers_returns_empty_array_for_empty_term(): void {
        // Mock WP_User_Query to return empty results.
        $user_query_mock = Mockery::mock('overload:WP_User_Query');
        $user_query_mock->shouldReceive('get_results')->andReturn( [] );

        $result = $this->search->search_customers( '' );
        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    public function test_search_customers_returns_empty_array_for_whitespace_term(): void {
        // Mock WP_User_Query to return empty results.
        $user_query_mock = Mockery::mock('overload:WP_User_Query');
        $user_query_mock->shouldReceive('get_results')->andReturn( [] );

        $result = $this->search->search_customers( '   ' );
        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    public function test_search_customers_returns_correct_structure(): void {
        // Stub customer lookup to return user IDs.
        $this->search->stubbed_user_ids = [ 101 ];

        // Mock WP_User_Query to return test user.
        $mock_user = (object) [
            'ID'              => 101,
            'user_email'      => 'johndoe@example.com',
            'display_name'    => 'John Doe',
            'user_registered' => '2024-01-15 10:00:00',
        ];

        // Create a mock WP_User_Query that returns our test user.
        $user_query_mock = Mockery::mock('overload:WP_User_Query');
        $user_query_mock->shouldReceive('get_results')
            ->andReturn( [ $mock_user ] );

        // Stub billing meta.
        $this->search->stubbed_billing_meta = [
            101 => [
                'billing_first_name' => 'John',
                'billing_last_name'  => 'Doe',
                'billing_email'      => 'john@example.com',
            ],
        ];
        $this->search->stubbed_order_counts = [ 101 => 5 ];
        $this->search->stubbed_recent_orders = [ 101 => [] ];

        $results = $this->search->search_customers( 'john' );

        $this->assertIsArray( $results );
        $this->assertCount( 1, $results );

        $customer = $results[0];

        // Verify required fields exist.
        $this->assertArrayHasKey( 'id', $customer );
        $this->assertArrayHasKey( 'name', $customer );
        $this->assertArrayHasKey( 'email', $customer );
        $this->assertArrayHasKey( 'billing_email', $customer );
        $this->assertArrayHasKey( 'registered', $customer );
        $this->assertArrayHasKey( 'registered_h', $customer );
        $this->assertArrayHasKey( 'orders', $customer );
        $this->assertArrayHasKey( 'edit_url', $customer );
        $this->assertArrayHasKey( 'orders_list', $customer );

        // Verify values.
        $this->assertSame( 101, $customer['id'] );
        $this->assertSame( 'John Doe', $customer['name'] );
        $this->assertSame( 5, $customer['orders'] );
    }

    public function test_search_customers_uses_display_name_as_fallback(): void {
        $this->search->stubbed_user_ids = [ 102 ];

        $mock_user = (object) [
            'ID'              => 102,
            'user_email'      => 'jane@example.com',
            'display_name'    => 'Jane Smith',
            'user_registered' => '2024-06-20 12:00:00',
        ];

        $user_query_mock = Mockery::mock('overload:WP_User_Query');
        $user_query_mock->shouldReceive('get_results')
            ->andReturn( [ $mock_user ] );

        $this->search->stubbed_billing_meta = [
            102 => [], // No billing name.
        ];
        $this->search->stubbed_order_counts = [ 102 => 0 ];
        $this->search->stubbed_recent_orders = [ 102 => [] ];

        $results = $this->search->search_customers( 'jane' );

        $this->assertCount( 1, $results );
        $this->assertSame( 'Jane Smith', $results[0]['name'] );
    }

    public function test_search_customers_escapes_html_in_output(): void {
        $this->search->stubbed_user_ids = [ 103 ];

        $mock_user = (object) [
            'ID'              => 103,
            'user_email'      => 'test@example.com',
            'display_name'    => 'Test User',
            'user_registered' => '2024-01-01 00:00:00',
        ];

        $user_query_mock = Mockery::mock('overload:WP_User_Query');
        $user_query_mock->shouldReceive('get_results')
            ->andReturn( [ $mock_user ] );

        $this->search->stubbed_billing_meta = [
            103 => [
                'billing_first_name' => '<script>alert("xss")</script>',
                'billing_last_name'  => 'Test',
            ],
        ];
        $this->search->stubbed_order_counts = [ 103 => 0 ];
        $this->search->stubbed_recent_orders = [ 103 => [] ];

        $results = $this->search->search_customers( 'test' );

        // Name should be HTML escaped.
        $this->assertStringNotContainsString( '<script>', $results[0]['name'] );
        $this->assertStringContainsString( '&lt;script&gt;', $results[0]['name'] );
    }

    public function test_search_customers_returns_empty_when_no_users_found(): void {
        $this->search->stubbed_user_ids = [];

        // Mock WP_User_Query to return empty results.
        $user_query_mock = Mockery::mock('overload:WP_User_Query');
        $user_query_mock->shouldReceive('get_results')
            ->andReturn( [] );

        $results = $this->search->search_customers( 'nonexistent' );

        $this->assertIsArray( $results );
        $this->assertEmpty( $results );
    }

    public function test_search_customers_uses_user_email_as_primary(): void {
        $this->search->stubbed_user_ids = [ 104 ];

        $mock_user = (object) [
            'ID'              => 104,
            'user_email'      => 'primary@example.com',
            'display_name'    => 'User 104',
            'user_registered' => '2024-01-01 00:00:00',
        ];

        $user_query_mock = Mockery::mock('overload:WP_User_Query');
        $user_query_mock->shouldReceive('get_results')
            ->andReturn( [ $mock_user ] );

        $this->search->stubbed_billing_meta = [
            104 => [
                'billing_email' => 'billing@example.com',
            ],
        ];
        $this->search->stubbed_order_counts = [ 104 => 0 ];
        $this->search->stubbed_recent_orders = [ 104 => [] ];

        $results = $this->search->search_customers( 'user' );

        // Primary email should be user_email, not billing_email.
        $this->assertSame( 'primary@example.com', $results[0]['email'] );
        $this->assertSame( 'billing@example.com', $results[0]['billing_email'] );
    }
}

