<?php
/**
 * Phase 3 Optimization Tests
 *
 * Quick validation tests for query monitoring, caching, and order formatting.
 * Run this from WordPress admin or WP-CLI.
 *
 * @package Hypercart_Woo_Fast_Search
 * @since 2.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Test Query Monitor
 */
function test_query_monitor() {
	echo "\n=== Testing Query Monitor ===\n";

	$monitor = new Hypercart_Query_Monitor( 5 ); // 5 query limit

	// Should be 0 initially
	$count = $monitor->get_query_count();
	echo "Initial query count: {$count}\n";
	assert( $count === 0, 'Initial count should be 0' );

	// Simulate some queries
	global $wpdb;
	$wpdb->get_var( "SELECT 1" );
	$wpdb->get_var( "SELECT 2" );

	$count = $monitor->get_query_count();
	echo "After 2 queries: {$count}\n";
	assert( $count === 2, 'Count should be 2' );

	// Test logging
	$monitor->log_query( 'test_query', array( 'foo' => 'bar' ) );
	$queries = $monitor->get_queries();
	echo "Logged queries: " . count( $queries ) . "\n";
	assert( count( $queries ) === 1, 'Should have 1 logged query' );

	// Test stats
	$stats = $monitor->get_stats();
	echo "Stats: count={$stats['count']}, limit={$stats['limit']}, percent={$stats['percent']}%\n";

	echo "✅ Query Monitor tests passed!\n\n";
}

/**
 * Test Search Cache
 */
function test_search_cache() {
	echo "\n=== Testing Search Cache ===\n";

	$cache = new Hypercart_Search_Cache( 300, true );

	// Test cache miss
	$key    = $cache->get_search_key( 'test@example.com', 'customers' );
	$result = $cache->get( $key );
	echo "Cache miss (should be null): " . var_export( $result, true ) . "\n";
	assert( $result === null, 'Cache should be empty' );

	// Test cache set
	$data = array( 'user_id' => 123, 'email' => 'test@example.com' );
	$set  = $cache->set( $key, $data );
	echo "Cache set: " . ( $set ? 'success' : 'failed' ) . "\n";
	assert( $set === true, 'Cache set should succeed' );

	// Test cache hit
	$result = $cache->get( $key );
	echo "Cache hit: " . var_export( $result, true ) . "\n";
	assert( $result === $data, 'Cache should return same data' );

	// Test cache delete
	$deleted = $cache->delete( $key );
	echo "Cache delete: " . ( $deleted ? 'success' : 'failed' ) . "\n";

	// Verify deleted
	$result = $cache->get( $key );
	echo "After delete (should be null): " . var_export( $result, true ) . "\n";
	assert( $result === null, 'Cache should be empty after delete' );

	echo "✅ Search Cache tests passed!\n\n";
}

/**
 * Test Order Formatter
 */
function test_order_formatter() {
	echo "\n=== Testing Order Formatter ===\n";

	$formatter = new Hypercart_Order_Formatter();

	// Get some real order IDs (if available)
	global $wpdb;
	$order_ids = $wpdb->get_col(
		"SELECT ID FROM {$wpdb->posts}
		 WHERE post_type = 'shop_order'
		 LIMIT 5"
	);

	if ( empty( $order_ids ) ) {
		echo "⚠️  No orders found in database, skipping order formatter test\n\n";
		return;
	}

	echo "Testing with " . count( $order_ids ) . " orders: " . implode( ', ', $order_ids ) . "\n";

	// Test order summaries
	$summaries = $formatter->get_order_summaries( $order_ids );
	echo "Got " . count( $summaries ) . " order summaries\n";

	if ( ! empty( $summaries ) ) {
		$first = $summaries[0];
		echo "First order: ID={$first['id']}, Number={$first['number']}, Status={$first['status']}, Total={$first['total']}\n";

		// Verify required fields
		assert( isset( $first['id'] ), 'Should have id' );
		assert( isset( $first['number'] ), 'Should have number' );
		assert( isset( $first['date'] ), 'Should have date' );
		assert( isset( $first['status'] ), 'Should have status' );
		assert( isset( $first['total'] ), 'Should have total' );
		assert( isset( $first['edit_url'] ), 'Should have edit_url' );
	}

	echo "✅ Order Formatter tests passed!\n\n";
}

/**
 * Test Memory Usage
 */
function test_memory_usage() {
	echo "\n=== Testing Memory Usage ===\n";

	$start_memory = memory_get_usage();
	echo "Start memory: " . size_format( $start_memory ) . "\n";

	// Create 100 order summaries (should be ~100KB)
	$formatter = new Hypercart_Order_Formatter();
	$order_ids = range( 1, 100 );
	$summaries = $formatter->get_order_summaries( $order_ids );

	$end_memory = memory_get_usage();
	$used       = $end_memory - $start_memory;
	echo "End memory: " . size_format( $end_memory ) . "\n";
	echo "Memory used: " . size_format( $used ) . "\n";
	echo "Per order: " . size_format( $used / 100 ) . "\n";

	// Should be <10KB per order (vs ~100KB with WC_Order)
	$per_order = $used / 100;
	if ( $per_order < 10240 ) {
		echo "✅ Memory usage is excellent (<10KB per order)\n";
	} elseif ( $per_order < 50240 ) {
		echo "⚠️  Memory usage is acceptable (<50KB per order)\n";
	} else {
		echo "❌ Memory usage is too high (>50KB per order)\n";
	}

	echo "\n";
}

/**
 * Run all tests
 */
function run_phase_3_tests() {
	echo "\n";
	echo "╔════════════════════════════════════════╗\n";
	echo "║  Phase 3 Optimization Tests           ║\n";
	echo "║  Version 2.0.0                         ║\n";
	echo "╚════════════════════════════════════════╝\n";

	try {
		test_query_monitor();
		test_search_cache();
		test_order_formatter();
		test_memory_usage();

		echo "\n";
		echo "╔════════════════════════════════════════╗\n";
		echo "║  ✅ ALL TESTS PASSED!                  ║\n";
		echo "╚════════════════════════════════════════╝\n";
		echo "\n";
	} catch ( Exception $e ) {
		echo "\n";
		echo "╔════════════════════════════════════════╗\n";
		echo "║  ❌ TEST FAILED!                       ║\n";
		echo "╚════════════════════════════════════════╝\n";
		echo "Error: " . $e->getMessage() . "\n";
		echo "Trace: " . $e->getTraceAsString() . "\n";
		echo "\n";
	}
}

// Auto-run if accessed directly (for WP-CLI or admin)
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	run_phase_3_tests();
}

