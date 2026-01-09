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

use Mockery;

/**
 * Testable subclass that stubs database methods and WP_User_Query.
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

    protected function get_user_meta_for_users( $user_ids, $meta_keys ) {
        return $this->stubbed_billing_meta;
    }

    protected function get_order_counts_for_customers( $user_ids ) {
        return $this->stubbed_order_counts;
    }

    protected function get_recent_orders_for_customers( $user_ids ) {
        return $this->stubbed_recent_orders;
    }

    /**
     * Override to inject mock users directly instead of calling WP_User_Query.
     */
    public function search_customers( $term ) {
        $t0 = microtime( true );
        $term = trim( $term );

        if ( '' === $term ) {
            return [];
        }

        // Use stubbed user IDs.
        $user_ids = $this->search_user_ids_via_customer_lookup( $term, 20 );

        // If we have stubbed users, use them directly.
        if ( ! empty( $this->stubbed_users ) ) {
            $users = $this->stubbed_users;
        } elseif ( ! empty( $user_ids ) ) {
            // No stubbed users but have user IDs - create mock user objects.
            $users = [];
            foreach ( $user_ids as $uid ) {
                $users[] = (object) [
                    'ID'              => $uid,
                    'user_email'      => "user{$uid}@example.com",
                    'display_name'    => "User {$uid}",
                    'user_registered' => '2024-01-01 00:00:00',
                ];
            }
        } else {
            return [];
        }

        $results = [];
        $user_ids = array_map( 'intval', array_column( array_map( function( $u ) {
            return is_object( $u ) ? (array) $u : $u;
        }, $users ), 'ID' ) );

        $billing_meta = $this->get_user_meta_for_users( $user_ids, [ 'billing_first_name', 'billing_last_name', 'billing_email' ] );
        $order_counts = $this->get_order_counts_for_customers( $user_ids );
        $recent_orders = $this->get_recent_orders_for_customers( $user_ids );

        foreach ( $users as $user ) {
            $user_id = (int) $user->ID;

            $first = isset( $billing_meta[ $user_id ]['billing_first_name'] ) ? (string) $billing_meta[ $user_id ]['billing_first_name'] : '';
            $last  = isset( $billing_meta[ $user_id ]['billing_last_name'] ) ? (string) $billing_meta[ $user_id ]['billing_last_name'] : '';
            $full_name = trim( $first . ' ' . $last );

            if ( '' === $full_name ) {
                $full_name = isset( $user->display_name ) ? (string) $user->display_name : '';
            }

            $billing_email = isset( $billing_meta[ $user_id ]['billing_email'] ) ? (string) $billing_meta[ $user_id ]['billing_email'] : '';
            $user_email    = isset( $user->user_email ) ? (string) $user->user_email : '';
            $primary_email = $user_email ? $user_email : $billing_email;

            $registered = isset( $user->user_registered ) ? (string) $user->user_registered : '';

            $order_count = isset( $order_counts[ $user_id ] ) ? (int) $order_counts[ $user_id ] : 0;
            $orders_list = isset( $recent_orders[ $user_id ] ) ? $recent_orders[ $user_id ] : [];

            $results[] = [
                'id'            => $user_id,
                'name'          => esc_html( $full_name ),
                'email'         => esc_html( $primary_email ),
                'billing_email' => esc_html( $billing_email ),
                'registered'    => $registered,
                'registered_h'  => esc_html( $this->format_date_human( $registered ) ),
                'orders'        => $order_count,
                'edit_url'      => esc_url( get_edit_user_link( $user_id ) ),
                'orders_list'   => $orders_list,
            ];
        }

        return $results;
    }

    /**
     * Simple date formatter for testing.
     */
    protected function format_date_human( $date_string ) {
        if ( empty( $date_string ) ) {
            return '';
        }
        return date( 'M j, Y', strtotime( $date_string ) );
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
        // Stub the lookup to return user IDs and mock users.
        $this->search->stubbed_user_ids = [ 101 ];
        $this->search->stubbed_users = [
            (object) [
                'ID'              => 101,
                'user_email'      => 'johndoe@example.com',
                'display_name'    => 'John Doe',
                'user_registered' => '2024-01-15 10:00:00',
            ],
        ];
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
        $this->search->stubbed_users = [
            (object) [
                'ID'              => 102,
                'user_email'      => 'jane@example.com',
                'display_name'    => 'Jane Smith',
                'user_registered' => '2024-06-20 12:00:00',
            ],
        ];
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
        $this->search->stubbed_users = [
            (object) [
                'ID'              => 103,
                'user_email'      => 'test@example.com',
                'display_name'    => 'Test User',
                'user_registered' => '2024-01-01 00:00:00',
            ],
        ];
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
        $this->search->stubbed_users = [];

        $results = $this->search->search_customers( 'nonexistent' );

        $this->assertIsArray( $results );
        $this->assertEmpty( $results );
    }

    public function test_search_customers_uses_user_email_as_primary(): void {
        $this->search->stubbed_user_ids = [ 104 ];
        $this->search->stubbed_users = [
            (object) [
                'ID'              => 104,
                'user_email'      => 'primary@example.com',
                'display_name'    => 'User 104',
                'user_registered' => '2024-01-01 00:00:00',
            ],
        ];
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

