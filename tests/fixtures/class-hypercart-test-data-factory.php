<?php
/**
 * Test Data Factory for Hypercart Woo Fast Search
 *
 * Creates test customers, orders, and scenarios for testing
 *
 * @package Hypercart_Woo_Fast_Search
 * @subpackage Tests
 * @since 2.0.0
 */

class Hypercart_Test_Data_Factory {

	/**
	 * Create test customer scenarios
	 *
	 * @return array Array of customer test scenarios
	 */
	public static function create_test_customers() {
		return [
			// Scenario 1: Two-word name (the bug case)
			'john_smith' => [
				'user_login'          => 'johnsmith',
				'user_email'          => 'john.smith@example.com',
				'first_name'          => 'John',
				'last_name'           => 'Smith',
				'billing_first_name'  => 'John',
				'billing_last_name'   => 'Smith',
				'billing_email'       => 'john.smith@example.com',
				'order_count'         => 5,
				'description'         => 'Two-word name - tests name splitting bug',
			],

			// Scenario 2: Single name customer
			'madonna' => [
				'user_login'          => 'madonna',
				'user_email'          => 'madonna@example.com',
				'first_name'          => 'Madonna',
				'last_name'           => '',
				'billing_first_name'  => 'Madonna',
				'billing_last_name'   => '',
				'billing_email'       => 'madonna@example.com',
				'order_count'         => 3,
				'description'         => 'Single name - edge case',
			],

			// Scenario 3: Email mismatch (user email != billing email)
			'email_mismatch' => [
				'user_login'          => 'testuser',
				'user_email'          => 'user@example.com',
				'first_name'          => 'Test',
				'last_name'           => 'User',
				'billing_first_name'  => 'Test',
				'billing_last_name'   => 'User',
				'billing_email'       => 'billing@different.com',
				'order_count'         => 10,
				'description'         => 'Different billing email - tests email search',
			],

			// Scenario 4: Three-word name
			'mary_jane_watson' => [
				'user_login'          => 'mjwatson',
				'user_email'          => 'mary.watson@example.com',
				'first_name'          => 'Mary Jane',
				'last_name'           => 'Watson',
				'billing_first_name'  => 'Mary Jane',
				'billing_last_name'   => 'Watson',
				'billing_email'       => 'mary.watson@example.com',
				'order_count'         => 2,
				'description'         => 'Three-word name - tests complex name splitting',
			],

			// Scenario 5: Special characters in name
			'special_chars' => [
				'user_login'          => 'oconnor',
				'user_email'          => 'sean@example.com',
				'first_name'          => "Seán",
				'last_name'           => "O'Connor",
				'billing_first_name'  => "Seán",
				'billing_last_name'   => "O'Connor",
				'billing_email'       => 'sean@example.com',
				'order_count'         => 1,
				'description'         => 'Special characters - tests Unicode and apostrophes',
			],

			// Scenario 6: Empty billing info (uses user data)
			'no_billing' => [
				'user_login'          => 'nobilling',
				'user_email'          => 'nobilling@example.com',
				'first_name'          => 'No',
				'last_name'           => 'Billing',
				'billing_first_name'  => '',
				'billing_last_name'   => '',
				'billing_email'       => '',
				'order_count'         => 0,
				'description'         => 'No billing info - tests fallback to user data',
			],

			// Scenario 7: High order count customer
			'power_user' => [
				'user_login'          => 'poweruser',
				'user_email'          => 'power@example.com',
				'first_name'          => 'Power',
				'last_name'           => 'User',
				'billing_first_name'  => 'Power',
				'billing_last_name'   => 'User',
				'billing_email'       => 'power@example.com',
				'order_count'         => 100,
				'description'         => 'High order count - tests performance',
			],

			// Scenario 8: Common name (multiple matches)
			'jane_doe' => [
				'user_login'          => 'janedoe',
				'user_email'          => 'jane.doe@example.com',
				'first_name'          => 'Jane',
				'last_name'           => 'Doe',
				'billing_first_name'  => 'Jane',
				'billing_last_name'   => 'Doe',
				'billing_email'       => 'jane.doe@example.com',
				'order_count'         => 7,
				'description'         => 'Common name - tests result ranking',
			],
		];
	}

	/**
	 * Create guest order scenarios
	 *
	 * @return array Array of guest order test scenarios
	 */
	public static function create_guest_orders() {
		return [
			'guest_single' => [
				'billing_email'       => 'guest@example.com',
				'billing_first_name'  => 'Guest',
				'billing_last_name'   => 'User',
				'order_count'         => 1,
				'description'         => 'Single guest order',
			],

			'guest_multiple' => [
				'billing_email'       => 'repeat.guest@example.com',
				'billing_first_name'  => 'Repeat',
				'billing_last_name'   => 'Guest',
				'order_count'         => 5,
				'description'         => 'Multiple guest orders same email',
			],
		];
	}

	/**
	 * Create large dataset for performance testing
	 *
	 * @param int $count Number of customers to create
	 * @return array Array of customer data
	 */
	public static function create_large_dataset( $count = 1000 ) {
		$customers = [];
		$first_names = [ 'John', 'Jane', 'Bob', 'Alice', 'Charlie', 'Diana', 'Eve', 'Frank', 'Grace', 'Henry' ];
		$last_names = [ 'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez' ];

		for ( $i = 1; $i <= $count; $i++ ) {
			$first = $first_names[ array_rand( $first_names ) ];
			$last  = $last_names[ array_rand( $last_names ) ];
			$login = strtolower( $first . $last . $i );
			$email = strtolower( $first . '.' . $last . $i . '@example.com' );

			$customers[ "customer_{$i}" ] = [
				'user_login'          => $login,
				'user_email'          => $email,
				'first_name'          => $first,
				'last_name'           => $last,
				'billing_first_name'  => $first,
				'billing_last_name'   => $last,
				'billing_email'       => $email,
				'order_count'         => rand( 0, 20 ),
				'description'         => "Generated customer {$i}",
			];
		}

		return $customers;
	}

	/**
	 * Get search test scenarios
	 *
	 * @return array Array of search term test scenarios
	 */
	public static function get_search_scenarios() {
		return [
			'two_word_name' => [
				'term'            => 'John Smith',
				'expected_match'  => 'john_smith',
				'should_find'     => true,
				'description'     => 'Two-word name search (bug case)',
			],

			'first_name_only' => [
				'term'            => 'John',
				'expected_match'  => 'john_smith',
				'should_find'     => true,
				'description'     => 'First name only',
			],

			'last_name_only' => [
				'term'            => 'Smith',
				'expected_match'  => 'john_smith',
				'should_find'     => true,
				'description'     => 'Last name only',
			],

			'email_search' => [
				'term'            => 'john.smith@example.com',
				'expected_match'  => 'john_smith',
				'should_find'     => true,
				'description'     => 'Email search',
			],

			'partial_email' => [
				'term'            => 'john.smith',
				'expected_match'  => 'john_smith',
				'should_find'     => true,
				'description'     => 'Partial email search',
			],

			'single_name' => [
				'term'            => 'Madonna',
				'expected_match'  => 'madonna',
				'should_find'     => true,
				'description'     => 'Single name search',
			],

			'three_word_name' => [
				'term'            => 'Mary Jane Watson',
				'expected_match'  => 'mary_jane_watson',
				'should_find'     => true,
				'description'     => 'Three-word name search',
			],

			'special_chars' => [
				'term'            => "O'Connor",
				'expected_match'  => 'special_chars',
				'should_find'     => true,
				'description'     => 'Special characters search',
			],

			'no_match' => [
				'term'            => 'NonExistent Person',
				'expected_match'  => null,
				'should_find'     => false,
				'description'     => 'No match scenario',
			],
		];
	}

	/**
	 * Get expected query counts for scenarios
	 *
	 * @return array Expected query counts
	 */
	public static function get_expected_query_counts() {
		return [
			'stock_wc_search'       => 200, // Stock WC is slow
			'stock_wp_search'       => 150, // Stock WP is slow
			'hypercart_current'     => 100, // Current implementation
			'hypercart_target'      => 6,   // Target after refactoring
		];
	}
}

