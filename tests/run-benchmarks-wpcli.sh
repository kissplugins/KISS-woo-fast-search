#!/bin/bash
# Run benchmarks via WP-CLI for Local WP compatibility
# Usage: ./tests/run-benchmarks-wpcli.sh [--quick]

# Find WP-CLI or use wp command
if command -v wp &> /dev/null; then
    WP_CLI="wp"
elif [ -f "/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/wp-cli.phar" ]; then
    WP_CLI="php /Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/wp-cli.phar"
else
    echo "Error: WP-CLI not found"
    exit 1
fi

# Get the WordPress root directory (3 levels up from tests/)
WP_ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"

# Change to WordPress root
cd "$WP_ROOT" || exit 1

echo "WordPress root: $WP_ROOT"
echo "Running benchmarks via WP-CLI..."
echo ""

# Build the PHP code to execute
PHP_CODE='
// Load test classes
require_once "wp-content/plugins/KISS-woo-fast-search/tests/fixtures/class-hypercart-test-data-factory.php";
require_once "wp-content/plugins/KISS-woo-fast-search/tests/class-hypercart-performance-benchmark.php";

// Run quick or full benchmark
$quick = isset($argv) && in_array("--quick", $argv);

if ($quick) {
    $benchmark = new Hypercart_Performance_Benchmark();
    $scenario = [
        "term" => "John Smith",
        "description" => "Two-word name search (quick test)",
    ];
    
    echo "\nRunning quick benchmark...\n\n";
    $results = $benchmark->run_comparative_benchmark($scenario);
    $passed = $benchmark->generate_report($results);
    
    exit($passed ? 0 : 1);
} else {
    require_once "wp-content/plugins/KISS-woo-fast-search/tests/run-benchmarks.php";
    $passed = hypercart_run_all_benchmarks();
    exit($passed ? 0 : 1);
}
'

# Run via WP-CLI eval
if [ "$1" = "--quick" ]; then
    $WP_CLI eval "$PHP_CODE" --quick
else
    $WP_CLI eval "$PHP_CODE"
fi

exit $?

