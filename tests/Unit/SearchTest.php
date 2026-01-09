<?php
/**
 * Tests for KISS_Woo_COS_Search.
 *
 * These tests focus on the public API and output structure.
 * Heavy database logic is stubbed via method overrides.
 *
 * @package KISS_Woo_Fast_Search\Tests\Unit
 */

namespace KISS\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;

/**
 * Testable subclass that stubs database methods.
 */
class Testable_Search extends \KISS_Woo_COS_Search {

    public array $stubbed_user_ids = [];
    public array $stubbed_users = [];
    public array $stubbed_billing_meta = [];
    public array $stubbed_order_counts = [];
    public array $stubbed_recent_orders = [];

    protected function search_user_ids_via_customer_lookup( $term, $limit = 20 ) {
        return $this->stubbed_user_ids;
    }

    protected function get_user_meta_for_users( array $user_ids, array $meta_keys ) {
        return $this->stubbed_billing_meta;
    }

    protected function get_order_counts_for_customers( array $user_ids ) {
        return $this->stubbed_order_counts;
    }

    protected function get_recent_orders_for_customers( array $user_ids, int $per_user = 3 ) {
        return $this->stubbed_recent_orders;
    }
}

class SearchTest extends \KISS_Test_Case {

    private Testable_Search $search;

    protected function setUp(): void {
        parent::setUp();
        $this->search = new Testable_Search();
    }

    protected function tearDown(): void {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // search_customers() Tests
    // =========================================================================

    public function test_search_customers_returns_empty_array_for_empty_term(): void {
        $result = $this->search->search_customers( '' );
        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    public function test_search_customers_returns_empty_array_for_whitespace_term(): void {
        $result = $this->search->search_customers( '   ' );
        $this->assertIsArray( $result );
        $this->assertEmpty( $result );
    }

    public function test_search_customers_returns_correct_structure(): void {
        // Stub the lookup to return user IDs.
        $this->search->stubbed_user_ids = [ 101 ];
        $this->search->stubbed_billing_meta = [
            101 => [
                'billing_first_name' => 'John',
                'billing_last_name'  => 'Doe',
                'billing_email'      => 'john@example.com',
            ],
        ];
        $this->search->stubbed_order_counts = [ 101 => 5 ];
        $this->search->stubbed_recent_orders = [ 101 => [] ];

        // Mock WP_User_Query.
        $mock_user = (object) [
            'ID'              => 101,
            'user_email'      => 'johndoe@example.com',
            'display_name'    => 'John Doe',
            'user_registered' => '2024-01-15 10:00:00',
        ];

        // Create a mock WP_User_Query that returns our user.
        $mock_query = Mockery::mock( 'overload:WP_User_Query' );
        $mock_query->shouldReceive( 'get_results' )->andReturn( [ $mock_user ] );

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
        $this->search->stubbed_billing_meta = [
            102 => [], // No billing name.
        ];
        $this->search->stubbed_order_counts = [ 102 => 0 ];
        $this->search->stubbed_recent_orders = [ 102 => [] ];

        $mock_user = (object) [
            'ID'              => 102,
            'user_email'      => 'jane@example.com',
            'display_name'    => 'Jane Smith',
            'user_registered' => '2024-06-20 12:00:00',
        ];

        $mock_query = Mockery::mock( 'overload:WP_User_Query' );
        $mock_query->shouldReceive( 'get_results' )->andReturn( [ $mock_user ] );

        $results = $this->search->search_customers( 'jane' );

        $this->assertCount( 1, $results );
        $this->assertSame( 'Jane Smith', $results[0]['name'] );
    }

    public function test_search_customers_escapes_html_in_output(): void {
        $this->search->stubbed_user_ids = [ 103 ];
        $this->search->stubbed_billing_meta = [
            103 => [
                'billing_first_name' => '<script>alert("xss")</script>',
                'billing_last_name'  => 'Test',
            ],
        ];
        $this->search->stubbed_order_counts = [ 103 => 0 ];
        $this->search->stubbed_recent_orders = [ 103 => [] ];

        $mock_user = (object) [
            'ID'              => 103,
            'user_email'      => 'test@example.com',
            'display_name'    => 'Test User',
            'user_registered' => '2024-01-01 00:00:00',
        ];

        $mock_query = Mockery::mock( 'overload:WP_User_Query' );
        $mock_query->shouldReceive( 'get_results' )->andReturn( [ $mock_user ] );

        $results = $this->search->search_customers( 'test' );

        // Name should be HTML escaped.
        $this->assertStringNotContainsString( '<script>', $results[0]['name'] );
        $this->assertStringContainsString( '&lt;script&gt;', $results[0]['name'] );
    }
}

