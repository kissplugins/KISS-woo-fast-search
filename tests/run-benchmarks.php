<?php
/**
 * Benchmark Test Runner for Hypercart Woo Fast Search
 * 
 * Run from command line:
 * php tests/run-benchmarks.php
 * 
 * Or from WordPress admin:
 * Load this file via admin page
 * 
 * @package Hypercart_Woo_Fast_Search
 * @subpackage Tests
 * @since 2.0.0
 */

// Load WordPress if not already loaded
if ( ! defined( 'ABSPATH' ) ) {
	// Try to find wp-load.php
	$wp_load_paths = [
		__DIR__ . '/../../../../wp-load.php',
		__DIR__ . '/../../../../../wp-load.php',
		__DIR__ . '/../../../../../../wp-load.php',
	];

	foreach ( $wp_load_paths as $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
			break;
		}
	}

	if ( ! defined( 'ABSPATH' ) ) {
		die( "Error: Could not find WordPress installation. Please run from WordPress admin or adjust paths.\n" );
	}
}

// Load test classes
require_once __DIR__ . '/fixtures/class-hypercart-test-data-factory.php';
require_once __DIR__ . '/class-hypercart-performance-benchmark.php';

/**
 * Run all benchmarks
 */
function hypercart_run_all_benchmarks() {
	echo "\n";
	echo "================================================================================\n";
	echo "HYPERCART WOO FAST SEARCH - BENCHMARK TEST SUITE\n";
	echo "================================================================================\n";
	echo "\n";
	echo "This benchmark compares Hypercart Fast Search against:\n";
	echo "  - Stock WooCommerce customer search\n";
	echo "  - Stock WordPress user search\n";
	echo "\n";
	echo "Performance Gates (Minimum Requirements):\n";
	echo "  ✓ Must be at least 10x faster than stock WC\n";
	echo "  ✓ Must use <10 database queries\n";
	echo "  ✓ Must use <50MB memory\n";
	echo "  ✓ Must complete in <2 seconds\n";
	echo "\n";
	echo "================================================================================\n\n";

	$benchmark = new Hypercart_Performance_Benchmark();
	$scenarios = Hypercart_Test_Data_Factory::get_search_scenarios();

	$all_passed = true;
	$results_summary = [];

	foreach ( $scenarios as $scenario_key => $scenario ) {
		echo "Running scenario: {$scenario['description']}\n";
		echo "Search term: \"{$scenario['term']}\"\n";
		echo str_repeat( '-', 80 ) . "\n";

		$results = $benchmark->run_comparative_benchmark( $scenario );
		$passed  = $benchmark->generate_report( $results );

		$results_summary[ $scenario_key ] = [
			'description' => $scenario['description'],
			'passed'      => $passed,
			'results'     => $results,
		];

		if ( ! $passed ) {
			$all_passed = false;
		}

		echo "\n";
	}

	// Final summary
	echo "\n";
	echo "================================================================================\n";
	echo "FINAL SUMMARY\n";
	echo "================================================================================\n\n";

	$passed_count = 0;
	$failed_count = 0;

	foreach ( $results_summary as $scenario_key => $summary ) {
		$status = $summary['passed'] ? '✅ PASS' : '❌ FAIL';
		echo "{$status}: {$summary['description']}\n";

		if ( $summary['passed'] ) {
			$passed_count++;
		} else {
			$failed_count++;
		}
	}

	echo "\n";
	echo "Total Scenarios: " . count( $results_summary ) . "\n";
	echo "Passed: {$passed_count}\n";
	echo "Failed: {$failed_count}\n";
	echo "\n";

	if ( $all_passed ) {
		echo "================================================================================\n";
		echo "✅ ALL BENCHMARKS PASSED - Performance gates met!\n";
		echo "================================================================================\n\n";
		return true;
	} else {
		echo "================================================================================\n";
		echo "❌ SOME BENCHMARKS FAILED - Performance gates not met\n";
		echo "================================================================================\n\n";
		return false;
	}
}

/**
 * Run quick benchmark (single scenario)
 */
function hypercart_run_quick_benchmark() {
	$benchmark = new Hypercart_Performance_Benchmark();
	$scenario  = [
		'term'        => 'John Smith',
		'description' => 'Two-word name search (quick test)',
	];

	echo "\nRunning quick benchmark...\n\n";
	$results = $benchmark->run_comparative_benchmark( $scenario );
	$passed  = $benchmark->generate_report( $results );

	return $passed;
}

// Run benchmarks if called from command line
if ( php_sapi_name() === 'cli' ) {
	$quick = isset( $argv[1] ) && $argv[1] === '--quick';

	if ( $quick ) {
		$passed = hypercart_run_quick_benchmark();
	} else {
		$passed = hypercart_run_all_benchmarks();
	}

	exit( $passed ? 0 : 1 );
}

