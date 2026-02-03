<?php
/**
 * Test the edit_url fix - verify it doesn't have HTML entities
 */

// Load WordPress
require_once __DIR__ . '/../../../wp-load.php';

// Load the search class
require_once __DIR__ . '/includes/class-kiss-woo-search.php';

// Create an instance
$search = new KISS_Woo_COS_Search();

// Search for a user
$results = $search->search_customers( 'noel' );

echo "=== Testing edit_url Fix ===\n\n";

if ( ! empty( $results ) ) {
    $first_result = $results[0];
    
    echo "Customer: " . $first_result['name'] . "\n";
    echo "Email: " . $first_result['email'] . "\n";
    echo "Edit URL: " . $first_result['edit_url'] . "\n\n";
    
    // Check if URL contains HTML entities (bad)
    if ( strpos( $first_result['edit_url'], '&#038;' ) !== false ) {
        echo "❌ FAIL: URL contains HTML entities (&#038;)\n";
        echo "This will break JavaScript redirects!\n";
    } elseif ( strpos( $first_result['edit_url'], '&amp;' ) !== false ) {
        echo "❌ FAIL: URL contains HTML entities (&amp;)\n";
        echo "This will break JavaScript redirects!\n";
    } else {
        echo "✅ PASS: URL is clean (no HTML entities)\n";
        echo "URL contains raw '&' characters as expected for JSON responses\n";
    }
} else {
    echo "No results found for 'noel'\n";
}

