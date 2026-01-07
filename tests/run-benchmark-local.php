<?php
/**
 * Benchmark Runner for Local WP
 * 
 * This version works with Local WP by using the correct MySQL socket
 * 
 * Usage:
 * /path/to/php tests/run-benchmark-local.php
 */

// Set the correct MySQL port for Local WP (site ID: 9BvW6A7UK, port: 10140)
// We'll override DB_HOST in wp-config by defining it before loading WordPress
define('DB_HOST', '127.0.0.1:10140');

// Find WordPress root
$wp_root_candidates = [
    __DIR__ . '/../../../..',
    __DIR__ . '/../../..',
];

$wp_load_path = null;
foreach ($wp_root_candidates as $candidate) {
    $test_path = realpath($candidate . '/wp-load.php');
    if ($test_path && file_exists($test_path)) {
        $wp_load_path = $test_path;
        break;
    }
}

if (!$wp_load_path) {
    die("ERROR: Could not find wp-load.php\n");
}

echo "================================================================================\n";
echo "HYPERCART WOO FAST SEARCH - BASELINE BENCHMARK\n";
echo "================================================================================\n\n";
echo "Loading WordPress from: " . dirname($wp_load_path) . "\n";
echo "Plugin directory: " . realpath(__DIR__ . '/..') . "\n";
echo "MySQL connection: 127.0.0.1:10140\n\n";

// Bootstrap WordPress
define('WP_USE_THEMES', false);
require_once $wp_load_path;

// Verify WordPress loaded
if (!function_exists('wp_get_current_user')) {
    die("ERROR: WordPress did not load correctly\n");
}

echo "✓ WordPress loaded successfully\n";
echo "✓ Site URL: " . (function_exists('get_site_url') ? get_site_url() : 'N/A') . "\n";
$wc_version = (class_exists('WooCommerce') && function_exists('WC')) ? WC()->version : 'N/A';
echo "✓ WooCommerce active: " . (class_exists('WooCommerce') ? "Yes (v{$wc_version})" : 'No') . "\n";

// Check for wc_customer_lookup table
global $wpdb;
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wc_customer_lookup'");
echo "✓ wc_customer_lookup table: " . ($table_exists ? 'EXISTS' : 'NOT FOUND') . "\n";

// Count customers
$customer_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
echo "✓ Total users: " . number_format($customer_count) . "\n";

if ($table_exists) {
    $wc_customer_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wc_customer_lookup");
    echo "✓ WC customers: " . number_format($wc_customer_count) . "\n";
}

echo "\n";

// Load test classes
require_once __DIR__ . '/fixtures/class-hypercart-test-data-factory.php';
require_once __DIR__ . '/class-hypercart-performance-benchmark.php';

// Check if plugin class exists
if (!class_exists('KISS_Woo_COS_Search')) {
    echo "WARNING: KISS_Woo_COS_Search class not found. Plugin may not be active.\n";
    echo "Attempting to load plugin file...\n";
    
    $plugin_file = __DIR__ . '/../includes/class-kiss-woo-search.php';
    if (file_exists($plugin_file)) {
        require_once $plugin_file;
        echo "✓ Plugin class loaded manually\n\n";
    } else {
        die("ERROR: Could not load plugin class\n");
    }
}

// Run quick benchmark
echo str_repeat('=', 80) . "\n";
echo "RUNNING BASELINE BENCHMARK\n";
echo str_repeat('=', 80) . "\n\n";

$benchmark = new Hypercart_Performance_Benchmark();

// Test with a real customer name if possible
$test_customer = $wpdb->get_row("
    SELECT u.user_login, u.user_email, 
           COALESCE(wc.first_name, um1.meta_value) as first_name,
           COALESCE(wc.last_name, um2.meta_value) as last_name
    FROM {$wpdb->users} u
    LEFT JOIN {$wpdb->prefix}wc_customer_lookup wc ON wc.user_id = u.ID
    LEFT JOIN {$wpdb->usermeta} um1 ON um1.user_id = u.ID AND um1.meta_key = 'first_name'
    LEFT JOIN {$wpdb->usermeta} um2 ON um2.user_id = u.ID AND um2.meta_key = 'last_name'
    WHERE u.ID > 1
    LIMIT 1
");

if ($test_customer && $test_customer->first_name && $test_customer->last_name) {
    $search_term = $test_customer->first_name . ' ' . $test_customer->last_name;
    echo "Using real customer for test: {$search_term}\n\n";
} else {
    $search_term = 'John Smith';
    echo "Using test name: {$search_term}\n\n";
}

$scenario = [
    'term'        => $search_term,
    'description' => 'Baseline benchmark - Two-word name search',
];

try {
    $results = $benchmark->run_comparative_benchmark($scenario);
    $passed  = $benchmark->generate_report($results);
    
    // Save results to file
    $results_file = __DIR__ . '/baseline-metrics.json';
    $results['timestamp'] = date('Y-m-d H:i:s');
    $results['site_url'] = function_exists('get_site_url') ? get_site_url() : 'N/A';
    $results['wc_version'] = (class_exists('WooCommerce') && function_exists('WC')) ? WC()->version : 'N/A';
    $results['customer_count'] = $customer_count;
    
    file_put_contents(
        $results_file,
        json_encode($results, JSON_PRETTY_PRINT)
    );
    
    echo "\n✓ Results saved to: {$results_file}\n\n";
    
    exit($passed ? 0 : 1);
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
}

