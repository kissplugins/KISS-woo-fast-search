<?php
/**
 * Simple Benchmark Runner - Direct WordPress Bootstrap
 * 
 * This version directly bootstraps WordPress from the plugin directory
 * Works with Local WP installations
 * 
 * Usage from plugin directory:
 * /path/to/php tests/run-benchmark-simple.php
 */

// Find WordPress root by going up from plugin directory
$wp_root_candidates = [
    __DIR__ . '/../../../..',           // Standard: plugins/PLUGIN_NAME/tests -> wp-root
    __DIR__ . '/../../..',              // If in subdirectory
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

echo "Loading WordPress from: " . dirname($wp_load_path) . "\n";
echo "Plugin directory: " . __DIR__ . "/..\n\n";

// Bootstrap WordPress
define('WP_USE_THEMES', false);
require_once $wp_load_path;

// Verify WordPress loaded
if (!function_exists('wp_get_current_user')) {
    die("ERROR: WordPress did not load correctly\n");
}

echo "✓ WordPress loaded successfully\n";
echo "✓ Site URL: " . get_site_url() . "\n";
echo "✓ WooCommerce active: " . (class_exists('WooCommerce') ? 'Yes' : 'No') . "\n\n";

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
echo "RUNNING QUICK BENCHMARK\n";
echo str_repeat('=', 80) . "\n\n";

$benchmark = new Hypercart_Performance_Benchmark();

// Test scenario: Two-word name (the bug case)
$scenario = [
    'term'        => 'John Smith',
    'description' => 'Two-word name search (quick test)',
];

echo "Scenario: {$scenario['description']}\n";
echo "Search term: \"{$scenario['term']}\"\n\n";

try {
    $results = $benchmark->run_comparative_benchmark($scenario);
    $passed  = $benchmark->generate_report($results);
    
    // Save results to file
    $results_file = __DIR__ . '/baseline-metrics.json';
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

